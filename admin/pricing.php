<?php
// Smart pricing: settings (limits and factor sizes), forecast status and "Recompute prices".
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

$bookingCount = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status IS NULL OR Status <> 2")->fetchColumn();
$mlThreshold = (int)setting('pricing_ml_threshold');

// Live status for the page while a recompute runs (?status=json).
if (($_GET['status'] ?? '') === 'json') {
  $st = forecast_status($dbh);
  header('Content-Type: application/json');
  echo json_encode(['busy' => $st['busy'], 'status' => $st['last']->Status ?? null, 'message' => $st['last']->Message ?? null,
    'finished' => $st['last']->FinishedAt ?? null, 'rows' => (int)($st['coverage']->rows_ ?? 0)]);
  exit;
}

if (isset($_POST['recompute'])) {
  $runId = forecast_request_recompute($dbh, 'admin: Recompute prices');
  flash_set($runId ? 'success' : 'error', $runId ? 'Recompute started. Prices update in a few seconds.' : 'The recompute could not be started. See logs/forecast.log.');
  redirect_to('pricing.php');
}

if (isset($_POST['savesettings'])) {
  $upsert = $dbh->prepare("INSERT INTO tblsettings (SettingKey, SettingValue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
  $remove = $dbh->prepare("DELETE FROM tblsettings WHERE SettingKey = :k");
  $errors = [];
  $changed = 0;
  foreach (SETTINGS as $key => [$label, $type, $default, , $min, $max]) {
    $raw = $_POST['s'][$key] ?? null;
    if ($type === 'bool') {
      $value = empty($raw) ? '0' : '1';
      if ($key === 'pricing_ml_enabled' && $value === '1' && $bookingCount < $mlThreshold) {
        $errors[] = "The model can only be switched on after $mlThreshold bookings (there are $bookingCount).";
        continue;
      }
    } elseif ($type === 'days') {
      $days = array_values(array_unique(array_filter(array_map('intval', (array)$raw), fn($d) => $d >= 1 && $d <= 7)));
      sort($days);
      $value = implode(',', $days);
    } else {
      $raw = trim((string)$raw);
      if ($raw === '' || !is_numeric($raw)) {
        $errors[] = "$label: enter a number.";
        continue;
      }
      $number = $type === 'int' ? (int)round((float)$raw) : round((float)$raw, 2);
      if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
        $errors[] = "$label must be between $min and $max.";
        continue;
      }
      $value = (string)$number;
    }
    $isDefault = (string)$value === (string)$default;
    $saved = settings_saved()[$key] ?? null;
    if ($isDefault && $saved !== null) {
      $remove->execute([':k' => $key]);
      $changed++;
    } elseif (!$isDefault && $saved !== (string)$value) {
      $upsert->execute([':k' => $key, ':v' => $value]);
      $changed++;
    }
  }
  if ($changed) {
    settings_saved($dbh, true);
    forecast_request_recompute($dbh, 'pricing settings changed');
  }
  flash_set($errors ? 'error' : 'success', $errors ? implode(' ', $errors) . ($changed ? " The other $changed change(s) were saved." : '')
    : ($changed ? "Pricing settings saved ($changed change" . ($changed === 1 ? '' : 's') . '). Prices are being recomputed.' : 'Nothing changed.'));
  redirect_to(current_url());
}

$groups = [
  'Limits' => ['pricing_max_discount', 'pricing_max_surcharge', 'pricing_round_to', 'pricing_forecast_days'],
  'Weekend' => ['pricing_weekend_pct', 'pricing_weekend_days'],
  'Festivals and seasons' => ['pricing_festival_high_pct', 'pricing_festival_medium_pct', 'pricing_travel_days_pct', 'pricing_season_pct'],
  'Lead time' => ['pricing_lastminute_days', 'pricing_lastminute_pct', 'pricing_early_days', 'pricing_early_pct'],
  'Occupancy of the car' => ['pricing_demand_min_bookings', 'pricing_occupancy_window', 'pricing_occupancy_high', 'pricing_occupancy_high_pct', 'pricing_occupancy_low', 'pricing_occupancy_low_pct'],
  'Recent demand for the car' => ['pricing_demand_days', 'pricing_demand_high_count', 'pricing_demand_high_pct'],
  'Machine-learning model' => ['pricing_ml_threshold', 'pricing_ml_enabled'],
];
$dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

admin_page_start('Smart Pricing', 'pricing.php', [], [
  'lead' => 'Prices = base price × (1 + the factors that apply), kept between −' . setting('pricing_max_discount') . '% and +' . setting('pricing_max_surcharge') . '%.' . (PRICING_ENABLED ? '' : ' Smart prices are not shown on the website yet (PRICING_ENABLED is off).'),
]);
?>
<?php
$status = forecast_status($dbh);
$last = $status['last'];
$cov = $status['coverage'];
$previewCars = $dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, br.BrandName FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
  WHERE v.IsActive = 1 ORDER BY v.VehiclesTitle")->fetchAll(PDO::FETCH_OBJ);
$previewId = intval($_GET['car'] ?? ($previewCars[0]->id ?? 0));
$preview = null;
foreach ($previewCars as $pc) if ((int)$pc->id === $previewId) $preview = $pc;
$runVariant = ['done' => 'success', 'failed' => 'danger', 'running' => 'info', 'queued' => 'warning'][$last->Status ?? ''] ?? 'neutral';
?>
<?php $health = pricing_health($dbh); ?>
<section class="a-card" id="model-health">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa fa-heart-pulse" aria-hidden="true"></i>Model health</h2>
    <?php echo $health['live'] ? admin_badge('Live on the website', 'success', 'fa-circle-check') : admin_badge('Not live (base prices shown)', 'warning'); ?>
  </div>
  <div class="a-card__body">
    <div class="a-kpis mb-3">
      <div class="a-kpi a-kpi--success">
        <span class="a-kpi__label">Active pricer</span>
        <span class="a-kpi__value fs-6"><?php echo h($health['pricer']); ?></span>
        <span class="a-kpi__note">Gradient-boosting model: <?php echo $health['mlUnlocked'] ? ($health['mlEnabled'] ? 'switched on (not used by the forecast yet)' : 'unlocked, off') : 'locked'; ?></span>
      </div>
      <div class="a-kpi<?php echo $health['demandOn'] ? ' a-kpi--success' : ' a-kpi--warning'; ?>">
        <span class="a-kpi__label">Occupancy &amp; demand factors</span>
        <span class="a-kpi__value"><?php echo $health['confirmed']; ?> / <?php echo $health['demandMin']; ?></span>
        <span class="a-kpi__note">Confirmed bookings · factors <?php echo $health['demandOn'] ? 'on' : 'off until the threshold'; ?></span>
      </div>
      <div class="a-kpi a-kpi--info">
        <span class="a-kpi__label">ML model threshold</span>
        <span class="a-kpi__value"><?php echo $health['bookings']; ?> / <?php echo $health['mlMin']; ?></span>
        <span class="a-kpi__note">Bookings (not cancelled)</span>
      </div>
      <div class="a-kpi<?php echo $health['coveredCars'] === $health['activeCars'] ? ' a-kpi--success' : ' a-kpi--warning'; ?>">
        <span class="a-kpi__label">Forecast coverage</span>
        <span class="a-kpi__value"><?php echo $health['coveredCars']; ?> / <?php echo $health['activeCars']; ?> cars</span>
        <span class="a-kpi__note"><?php echo $health['horizon']; ?> days, to <?php echo admin_date($health['horizonEnd']); ?> · last run <?php echo $health['status']['lastDone'] ? admin_date($health['status']['lastDone']->FinishedAt, true) : 'never'; ?></span>
      </div>
    </div>
    <?php if ($health['warnings']) { ?>
      <h3 class="fs-6 mb-2">Data warnings</h3>
      <ul class="a-health-list">
        <?php foreach ($health['warnings'] as [$level, $text, $link]) { ?>
          <li class="a-health-list__<?php echo $level; ?>"><i class="fa fa-<?php echo $level === 'danger' ? 'circle-exclamation' : ($level === 'warning' ? 'triangle-exclamation' : 'circle-info'); ?>" aria-hidden="true"></i>
            <span><?php echo h($text); ?><?php if ($link) { ?> <a href="<?php echo h($link); ?>">Fix</a><?php } ?></span></li>
        <?php } ?>
      </ul>
    <?php } else { ?><p class="mb-0 text-success"><i class="fa fa-circle-check" aria-hidden="true"></i> No data warnings.</p><?php } ?>
  </div>
</section>

<div class="mb-4 mt-4">
  <section class="a-card" id="forecast-status" data-busy="<?php echo $status['busy'] ? '1' : '0'; ?>">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-calculator" aria-hidden="true"></i>Price forecast</h2>
      <?php if ($last) echo admin_badge(ucfirst($status['stale'] ? 'stalled' : $last->Status), $status['stale'] ? 'danger' : $runVariant); ?>
    </div>
    <div class="a-card__body">
      <dl class="a-dl">
        <dt>Coverage</dt><dd><?php echo (int)$cov->rows_; ?> prices for <?php echo (int)$cov->cars; ?> of <?php echo $status['activeCars']; ?> active cars<?php echo $cov->first_ ? ', ' . admin_date($cov->first_) . ' – ' . admin_date($cov->last_) : ''; ?></dd>
        <dt>Last run</dt><dd><?php echo $last ? h($last->TriggerReason) . ' · ' . (admin_date($last->FinishedAt ?: $last->RequestedAt, true)) : 'Never'; ?></dd>
        <dt>Result</dt><dd data-run-message><?php echo h($last->Message ?? '–'); ?></dd>
        <dt>Automatic</dt><dd>After changes to festivals, pricing settings, vehicles and bookings, and nightly (launchd job).</dd>
      </dl>
      <?php if ($status['activeCars'] > (int)$cov->cars && !$status['busy']) { ?><div class="a-notice a-notice--warning mt-3">Some active cars have no forecast yet: recompute to fill it.</div><?php } ?>
      <form method="post" class="mt-3">
        <?php echo csrf_field(); ?>
        <button class="btn btn-primary" name="recompute" value="1" type="submit"<?php echo $status['busy'] ? ' disabled' : ''; ?> data-recompute>
          <i class="fa fa-rotate<?php echo $status['busy'] ? ' fa-spin' : ''; ?>" aria-hidden="true"></i><?php echo $status['busy'] ? 'Recomputing…' : 'Recompute prices'; ?>
        </button>
      </form>
    </div>
  </section>

  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-eye" aria-hidden="true"></i>Preview: next 14 days</h2>
      <form method="get" class="d-flex gap-2">
        <label class="visually-hidden" for="car">Car</label>
        <select class="form-select form-select-sm" id="car" name="car" onchange="this.form.submit()">
          <?php foreach ($previewCars as $pc) { ?><option value="<?php echo (int)$pc->id; ?>"<?php echo (int)$pc->id === $previewId ? ' selected' : ''; ?>><?php echo h(vehicle_display_name($pc->BrandName, $pc->VehiclesTitle)); ?></option><?php } ?>
        </select>
      </form>
    </div>
    <div class="a-card__body a-card__body--flush">
      <?php if ($preview) {
        $q = pricing_quote($dbh, $preview, date('Y-m-d'), date('Y-m-d', strtotime('+13 days'))); ?>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Price preview for the next 14 days">
          <table class="table a-table mb-0">
            <thead><tr><th class="ps-4">Day</th><th>Price</th><th>Change</th><th class="pe-4">Why</th></tr></thead>
            <tbody>
              <?php foreach ($q['days'] as $d) { ?>
                <tr>
                  <td class="ps-4 text-nowrap"><?php echo date('D j M', strtotime($d['date'])); ?><?php echo $d['available'] ? '' : ' ' . admin_badge('Booked', 'neutral'); ?></td>
                  <td><span class="a-money"><?php echo format_price($d['price']); ?></span></td>
                  <td class="text-nowrap"><?php echo $d['source'] === 'base' ? '<span class="a-muted">base</span>' : ($d['pct'] > 0 ? '+' : '') . rtrim(rtrim(number_format($d['pct'], 1), '0'), '.') . '%' . ($d['capped'] ? ' <span class="a-muted">(capped)</span>' : ''); ?></td>
                  <td class="pe-4 a-muted"><?php echo h(implode(', ', array_map(fn($r) => $r['label'] . ' ' . ($r['pct'] > 0 ? '+' : '') . rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') . '%', $d['reasons']))) ?: '–'; ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <p class="a-muted px-4 py-3 mb-0">Base price <?php echo format_price_per_day($preview->PricePerDay); ?>.</p>
      <?php } else { ?><p class="a-empty">No active cars.</p><?php } ?>
    </div>
  </section>
</div>
<form method="post">
  <?php echo csrf_field(); ?>
  <?php foreach ($groups as $groupLabel => $keys) { ?>
    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-sliders" aria-hidden="true"></i><?php echo h($groupLabel); ?></h2></div>
      <div class="a-card__body a-form-grid a-form-grid--3">
        <?php if ($groupLabel === 'Occupancy of the car') {
          $confirmedCount = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 1")->fetchColumn();
          $demandMin = (int)setting('pricing_demand_min_bookings'); ?>
          <div class="a-span-all">
            <div class="a-notice<?php echo $confirmedCount >= $demandMin ? ' a-notice--success' : ' a-notice--warning'; ?>"><i class="fa fa-<?php echo $confirmedCount >= $demandMin ? 'circle-check' : 'pause'; ?> mt-1" aria-hidden="true"></i><div>
              <strong>Occupancy and recent-demand factors are <?php echo $confirmedCount >= $demandMin ? 'on' : 'off'; ?></strong>:
              <?php echo $confirmedCount; ?> of <?php echo $demandMin; ?> confirmed bookings. <?php echo $confirmedCount >= $demandMin ? '' : 'They switch on by themselves at the next recompute after that.'; ?>
            </div></div>
          </div>
        <?php } ?>
        <?php if ($groupLabel === 'Recent demand for the car') { ?>
          <div class="a-span-all a-muted small">Uses the same confirmed-bookings threshold as occupancy (above).</div>
        <?php } ?>
        <?php if ($groupLabel === 'Machine-learning model') { ?>
          <div class="a-span-all">
            <div class="a-notice"><i class="fa fa-flask mt-1" aria-hidden="true"></i><div>
              <strong><?php echo $bookingCount; ?> of <?php echo $mlThreshold; ?> bookings</strong> needed before the gradient-boosting model can be trained on real data.
              Until then every price comes from the rules above.
              <div class="progress mt-2" role="progressbar" aria-label="Bookings towards the model threshold" aria-valuenow="<?php echo $bookingCount; ?>" aria-valuemin="0" aria-valuemax="<?php echo $mlThreshold; ?>" style="height:8px"><div class="progress-bar" style="width: <?php echo min(100, round($bookingCount / max(1, $mlThreshold) * 100, 1)); ?>%; background: var(--rento-button)"></div></div>
            </div></div>
          </div>
        <?php } ?>
        <?php foreach ($keys as $key) {
          [$label, $type, $default, $hint] = SETTINGS[$key];
          $value = setting($key);
          $id = 's-' . $key; ?>
          <div<?php echo $type === 'days' ? ' class="a-span-all"' : ''; ?>>
            <?php if ($type === 'bool') {
              $locked = $key === 'pricing_ml_enabled' && $bookingCount < $mlThreshold; ?>
              <div class="form-check form-switch mt-4">
                <input class="form-check-input" type="checkbox" role="switch" id="<?php echo $id; ?>" name="s[<?php echo $key; ?>]" value="1"<?php echo $value ? ' checked' : ''; ?><?php echo $locked ? ' disabled' : ''; ?>>
                <label class="form-check-label fw-semibold" for="<?php echo $id; ?>"><?php echo h($label); ?></label>
              </div>
              <div class="form-text"><?php echo h($locked ? "Available after $mlThreshold bookings." : $hint); ?></div>
            <?php } elseif ($type === 'days') {
              $selected = array_map('intval', explode(',', (string)$value)); ?>
              <fieldset>
                <legend class="form-label"><?php echo h($label); ?></legend>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($dayNames as $n => $name) { ?>
                    <label class="a-check" style="min-height:40px"><input class="form-check-input" type="checkbox" name="s[<?php echo $key; ?>][]" value="<?php echo $n; ?>"<?php echo in_array($n, $selected, true) ? ' checked' : ''; ?>><?php echo $name; ?></label>
                  <?php } ?>
                </div>
                <div class="form-text"><?php echo h($hint); ?></div>
              </fieldset>
            <?php } else { ?>
              <label class="form-label" for="<?php echo $id; ?>"><?php echo h($label); ?></label>
              <input class="form-control" type="number" step="<?php echo $type === 'int' ? '1' : '0.5'; ?>" id="<?php echo $id; ?>" name="s[<?php echo $key; ?>]" value="<?php echo h($value); ?>">
              <div class="form-text"><?php echo h(trim($hint . ((string)$value !== (string)$default ? ' Default: ' . $default . '.' : ''))); ?></div>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    </section>
  <?php } ?>
  <div class="a-form-actions">
    <button class="btn btn-primary" name="savesettings" value="1" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save and recompute</button>
  </div>
</form>
<?php admin_page_end(<<<'JS'
(function () {
  // While a recompute runs, check its status every 2 seconds and reload when it ends.
  var card = document.getElementById('forecast-status');
  if (!card || card.dataset.busy !== '1') return;
  var timer = setInterval(function () {
    fetch('pricing.php?status=json', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (s) {
      if (!s.busy) { clearInterval(timer); location.reload(); }
    }).catch(function () { clearInterval(timer); });
  }, 2000);
})();
JS); ?>
