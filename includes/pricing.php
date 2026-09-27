<?php
// Smart pricing (Phase 4): read prices from the precomputed forecast and start recomputes.
//
//   pricing_quote($dbh, $car, '2026-10-20', '2026-10-22')   per-day prices, total, reasons
//   forecast_request_recompute($dbh, 'festival changed')     queue + start ml/batch_forecast.py
//   forecast_status($dbh)                                    latest run, coverage
//
// The rules live in ml/pricing_rules.py and run in ml/batch_forecast.py (tbl_price_forecast).
// While PRICING_ENABLED is false, public prices are the base price (pricing_quote(..., false)).

const FORECAST_LOG = __DIR__ . '/../logs/forecast.log';

// Effective pricing settings as passed to Python (defaults + admin changes).
function pricing_settings_json(): string
{
    $out = [];
    foreach (SETTINGS as $key => $def) {
        if (str_starts_with($key, 'pricing_')) {
            $out[$key] = setting($key);
        }
    }
    return json_encode($out);
}

// Environment for the Python scripts: DB settings from includes/config.php (never CLI args).
function forecast_environment(): array
{
    return [
        'RENTO_DB_HOST' => DB_HOST,
        'RENTO_DB_PORT' => (string)(defined('DB_PORT') ? DB_PORT : 3306),
        'RENTO_DB_USER' => DB_USER,
        'RENTO_DB_PASS' => DB_PASS,
        'RENTO_DB_NAME' => DB_NAME,
        'RENTO_PRICING_SETTINGS' => pricing_settings_json(),
        'PATH' => '/usr/bin:/bin',
        'LANG' => 'en_US.UTF-8',
    ];
}

// Queues a recompute and starts it in the background. Returns the run id (0 on failure).
function forecast_request_recompute(PDO $dbh, string $reason): int
{
    try {
        $dbh->prepare("INSERT INTO tbl_forecast_runs (TriggerReason) VALUES (:r)")->execute([':r' => mb_substr($reason, 0, 120)]);
        $runId = (int)$dbh->lastInsertId();
    } catch (PDOException $e) {
        error_log('[Rento] forecast: could not queue a run: ' . $e->getMessage());
        return 0;
    }
    forecast_start(false);
    return $runId;
}

// Runs ml/batch_forecast.py. $wait = true runs it to the end (CLI, tests) and returns the
// exit code; false starts it in the background and returns immediately.
function forecast_start(bool $wait): int
{
    $python = defined('PRICING_PYTHON_BIN') ? PRICING_PYTHON_BIN : '';
    $script = realpath(__DIR__ . '/../ml/batch_forecast.py');
    if (!$script || !is_file($python) || !is_executable($python)) {
        error_log('[Rento] forecast: Python or ml/batch_forecast.py not found (PRICING_PYTHON_BIN=' . $python . ')');
        return 127;
    }
    $log = FORECAST_LOG;
    if (!is_dir(dirname($log))) {
        @mkdir(dirname($log), 0755, true);
    }
    $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' >> ' . escapeshellarg($log) . ' 2>&1';
    if (!$wait) {
        $command = '(' . $command . ') > /dev/null 2>&1 &';
    }
    $process = proc_open(['/bin/sh', '-c', $command], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes, dirname($script), forecast_environment());
    if (!is_resource($process)) {
        error_log('[Rento] forecast: could not start Python');
        return 126;
    }
    return proc_close($process);
}

// Latest run and forecast coverage, for the admin page and API health.
function forecast_status(PDO $dbh): array
{
    $last = $dbh->query("SELECT * FROM tbl_forecast_runs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ) ?: null;
    $lastDone = $dbh->query("SELECT * FROM tbl_forecast_runs WHERE Status = 'done' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ) ?: null;
    $coverage = $dbh->query("SELECT COUNT(*) AS rows_, COUNT(DISTINCT VehicleId) AS cars, MIN(ForecastDate) AS first_, MAX(ForecastDate) AS last_, MAX(ComputedAt) AS computed
        FROM tbl_price_forecast WHERE ForecastDate >= CURDATE()")->fetch(PDO::FETCH_OBJ);
    $activeCars = (int)$dbh->query("SELECT COUNT(*) FROM tblvehicles WHERE IsActive = 1")->fetchColumn();
    // A run that has been "running" for over 10 minutes has died.
    $stale = $last && $last->Status === 'running' && $last->StartedAt && strtotime($last->StartedAt) < time() - 600;
    return [
        'last' => $last, 'lastDone' => $lastDone, 'coverage' => $coverage, 'activeCars' => $activeCars,
        'busy' => $last && in_array($last->Status, ['queued', 'running'], true) && !$stale,
        'stale' => $stale,
    ];
}

// Price of a car for each day from $from to $to (inclusive, rental_days()).
//   $smart false = base price only (what the website shows while PRICING_ENABLED is off)
// Returns ['ok', 'error', 'days' => [[date, price, base, pct, reasons, available, source]],
//          'total', 'base_total', 'rental_days', 'reasons' => [[label, pct, days]], 'source', 'available']
function pricing_quote(PDO $dbh, object $car, string $from, string $to, bool $smart = true): array
{
    $days = rental_days($from, $to);
    if ($days < 1) {
        return ['ok' => false, 'error' => 'invalid_dates'];
    }
    $base = (int)$car->PricePerDay;
    $rows = [];
    if ($smart) {
        $query = $dbh->prepare("SELECT ForecastDate, Price, BasePrice, Multiplier, Reasons, IsAvailable FROM tbl_price_forecast
            WHERE VehicleId = :id AND ForecastDate BETWEEN :from AND :to");
        $query->execute([':id' => $car->id, ':from' => $from, ':to' => $to]);
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $rows[$row->ForecastDate] = $row;
        }
    }
    // Days without a forecast row are treated as available here; the booking form still
    // checks for overlapping bookings before saving.
    $out = ['ok' => true, 'rental_days' => $days, 'days' => [], 'total' => 0, 'base_total' => 0, 'reasons' => [], 'available' => true];
    $missing = 0;
    $date = new DateTimeImmutable($from);
    for ($i = 0; $i < $days; $i++, $date = $date->modify('+1 day')) {
        $d = $date->format('Y-m-d');
        $row = $rows[$d] ?? null;
        // A forecast computed for an older base price is ignored.
        if ($row && (int)$row->BasePrice === $base) {
            $meta = json_decode((string)$row->Reasons, true) ?: [];
            $reasons = $meta['reasons'] ?? [];
            $day = ['date' => $d, 'price' => (int)$row->Price, 'base' => $base, 'pct' => round(((float)$row->Multiplier - 1) * 100, 2),
                'reasons' => $reasons, 'capped' => !empty($meta['capped']), 'available' => (bool)$row->IsAvailable, 'source' => 'forecast'];
        } else {
            if ($smart) $missing++;
            $day = ['date' => $d, 'price' => $base, 'base' => $base, 'pct' => 0, 'reasons' => [], 'capped' => false, 'available' => true, 'source' => 'base'];
        }
        $out['days'][] = $day;
        $out['total'] += $day['price'];
        $out['base_total'] += $base;
        $out['available'] = $out['available'] && $day['available'];
        foreach ($day['reasons'] as $reason) {
            $key = $reason['label'] . '|' . $reason['pct'];
            $out['reasons'][$key] ??= ['label' => $reason['label'], 'pct' => (float)$reason['pct'], 'days' => 0];
            $out['reasons'][$key]['days']++;
        }
    }
    $out['reasons'] = array_values($out['reasons']);
    $out['source'] = !$smart ? 'base' : ($missing === 0 ? 'forecast' : ($missing === $days ? 'base' : 'mixed'));
    if ($smart && $missing) {
        error_log(sprintf('[Rento] pricing: no forecast for car %d on %d of %d day(s) from %s; base price used', $car->id, $missing, $days, $from));
    }
    return $out;
}

// "Dashain 2026 +12% (3 days)" style lines for a quote.
function pricing_reason_lines(array $quote): array
{
    return array_map(function ($r) use ($quote) {
        $pct = ($r['pct'] > 0 ? '+' : '') . rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') . '%';
        return $r['label'] . ' ' . $pct . ($quote['rental_days'] > 1 ? ' (' . $r['days'] . ' of ' . $quote['rental_days'] . ' days)' : '');
    }, $quote['reasons']);
}

// True when the car has no pending or confirmed booking overlapping $from..$to (inclusive),
// the same rule as the booking form.
function vehicle_is_free(PDO $dbh, int $carId, string $from, string $to): bool
{
    $query = $dbh->prepare("SELECT 1 FROM tblbooking WHERE VehicleId = :id AND (Status IS NULL OR Status <> 2)
        AND date(FromDate) <= :to AND date(ToDate) >= :from LIMIT 1");
    $query->execute([':id' => $carId, ':from' => $from, ':to' => $to]);
    return !$query->fetchColumn();
}

// Public quote: smart prices when PRICING_ENABLED, otherwise the base price.
function public_quote(PDO $dbh, object $car, string $from, string $to): array
{
    $quote = pricing_quote($dbh, $car, $from, $to, PRICING_ENABLED);
    if ($quote['ok']) {
        $quote['available'] = $quote['available'] && vehicle_is_free($dbh, (int)$car->id, $from, $to);
    }
    return $quote;
}
