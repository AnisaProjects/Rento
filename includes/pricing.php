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

// Amount of a booking: the stored quoted_price when present (Phase 4 bookings), else
// rental days x the car's base price (older bookings). $b needs FromDate, ToDate,
// PricePerDay and optionally quoted_price / pricing_details.
// Returns ['total', 'rental_days', 'per_day' (from the stored details, may be []), 'source' => quoted|base].
function booking_total(object $b): array
{
    $days = rental_days($b->FromDate ?? null, $b->ToDate ?? null);
    if (isset($b->quoted_price) && $b->quoted_price !== null && $b->quoted_price !== '') {
        $details = json_decode((string)($b->pricing_details ?? ''), true) ?: [];
        return ['total' => (int)$b->quoted_price, 'rental_days' => (int)($details['rental_days'] ?? $days),
            'per_day' => $details['per_day'] ?? [], 'base_total' => (int)($details['base_total'] ?? $days * (int)($b->PricePerDay ?? 0)),
            'pricing' => $details['pricing'] ?? 'base', 'source' => 'quoted'];
    }
    return ['total' => $days * (int)($b->PricePerDay ?? 0), 'rental_days' => $days, 'per_day' => [],
        'base_total' => $days * (int)($b->PricePerDay ?? 0), 'pricing' => 'base', 'source' => 'base'];
}

// SQL for a booking's amount (b = tblbooking, v = tblvehicles), for revenue totals.
function booking_total_sql(): string
{
    return 'COALESCE(b.quoted_price, ' . rental_days_sql('b.FromDate', 'b.ToDate') . ' * v.PricePerDay)';
}

// The quote saved with a new booking: [quoted_price, pricing_details JSON].
function booking_price_record(array $quote): array
{
    return [$quote['total'], json_encode([
        'rental_days' => $quote['rental_days'],
        'base_total' => $quote['base_total'],
        'pricing' => PRICING_ENABLED ? 'smart' : 'base',
        'source' => $quote['source'],
        'per_day' => array_map(fn($d) => ['date' => $d['date'], 'price' => $d['price'], 'pct' => $d['pct'],
            'reasons' => array_map(fn($r) => $r['label'] . ' ' . ($r['pct'] > 0 ? '+' : '') . $r['pct'] . '%', $d['reasons'])], $quote['days']),
    ], JSON_UNESCAPED_UNICODE)];
}

// Model health for admin -> Smart Pricing: which pricer runs, thresholds, freshness,
// coverage and data warnings (each warning is [level: warning|danger|info, text, link]).
function pricing_health(PDO $dbh): array
{
    $confirmed = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 1")->fetchColumn();
    $all = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status IS NULL OR Status <> 2")->fetchColumn();
    $demandMin = (int)setting('pricing_demand_min_bookings');
    $mlMin = (int)setting('pricing_ml_threshold');
    $status = forecast_status($dbh);
    $horizon = (int)setting('pricing_forecast_days');
    $horizonEnd = date('Y-m-d', strtotime('+' . ($horizon - 1) . ' days'));
    $warnings = [];

    $cars = $dbh->query("SELECT id, VehiclesTitle, CarType, SeatingCapacity, PricePerDay, Vimage1 FROM tblvehicles WHERE IsActive = 1")->fetchAll(PDO::FETCH_OBJ);
    foreach ($cars as $car) {
        $missing = array_keys(array_filter([
            'car type' => trim((string)$car->CarType) === '',
            'seats' => (int)$car->SeatingCapacity < 1,
            'base price' => (int)$car->PricePerDay < 1,
            'photo' => trim((string)$car->Vimage1) === '' || str_starts_with((string)$car->Vimage1, 'placeholder-'),
        ]));
        if ($missing) {
            $warnings[] = [in_array('base price', $missing, true) ? 'danger' : 'warning', $car->VehiclesTitle . ': no ' . implode(', no ', $missing) . '.', 'edit-vehicle.php?id=' . $car->id];
        }
    }
    $withForecast = $dbh->query("SELECT DISTINCT f.VehicleId FROM tbl_price_forecast f JOIN tblvehicles v ON v.id = f.VehicleId
        WHERE v.IsActive = 1 AND f.ForecastDate = CURDATE() AND f.BasePrice = v.PricePerDay")->fetchAll(PDO::FETCH_COLUMN);
    $notCovered = count($cars) - count($withForecast);
    if ($notCovered > 0) {
        $warnings[] = ['danger', "$notCovered active car(s) have no up-to-date forecast for today (new car or changed base price): recompute.", null];
    }
    $lastDoneAt = $status['lastDone']->FinishedAt ?? null;
    if (!$lastDoneAt || strtotime($lastDoneAt) < time() - 26 * 3600) {
        $warnings[] = ['warning', 'The forecast was last computed ' . ($lastDoneAt ? date('j M Y, H:i', strtotime($lastDoneAt)) : 'never') . '. The nightly job may not be installed (see tools/com.rento.price-forecast.plist).', null];
    }
    if ($status['last'] && $status['last']->Status === 'failed') {
        $warnings[] = ['danger', 'The last recompute failed: ' . $status['last']->Message, null];
    }
    $lastFestival = $dbh->query("SELECT MAX(GREATEST(EndDate, COALESCE(TravelTo, EndDate))) FROM tblfestivals")->fetchColumn();
    if (!$lastFestival || $lastFestival < $horizonEnd) {
        $warnings[] = ['warning', 'The festival calendar ends ' . ($lastFestival ? date('j M Y', strtotime($lastFestival)) : '(empty)') . ', before the forecast horizon (' . date('j M Y', strtotime($horizonEnd)) . '). Add the next festivals.', 'festivals.php'];
    }
    $unchecked = $dbh->prepare("SELECT FestivalName FROM tblfestivals WHERE Verification IN ('unverified', 'disagreement')
        AND StartDate <= :end AND EndDate >= CURDATE() ORDER BY StartDate");
    $unchecked->execute([':end' => $horizonEnd]);
    foreach ($unchecked->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $warnings[] = ['info', "Festival dates not fully checked, inside the forecast: $name.", 'festivals.php'];
    }
    $overrides = (int)$dbh->query("SELECT COUNT(*) FROM tbl_price_overrides WHERE ToDate >= CURDATE()")->fetchColumn();
    if ($overrides) {
        $warnings[] = ['info', "$overrides manual price override(s) active (they replace the rule-based price on their days).", 'insights.php'];
    }
    if (!PRICING_ENABLED) {
        $warnings[] = ['info', 'PRICING_ENABLED is off in includes/config.php: the website shows base prices.', null];
    }
    return [
        'pricer' => 'Rule-based pricer (ml/pricing_rules.py)',
        'live' => PRICING_ENABLED,
        'confirmed' => $confirmed, 'bookings' => $all,
        'demandMin' => $demandMin, 'demandOn' => $confirmed >= $demandMin,
        'mlMin' => $mlMin, 'mlUnlocked' => $all >= $mlMin, 'mlEnabled' => (bool)setting('pricing_ml_enabled'),
        'status' => $status, 'horizon' => $horizon, 'horizonEnd' => $horizonEnd,
        'activeCars' => count($cars), 'coveredCars' => count($withForecast),
        'warnings' => $warnings,
    ];
}
