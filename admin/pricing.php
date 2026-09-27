<?php
// Smart pricing: settings (limits and factor sizes), forecast status and "Recompute prices".
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

$bookingCount = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status IS NULL OR Status <> 2")->fetchColumn();
$mlThreshold = (int)setting('pricing_ml_threshold');

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
    if (function_exists('forecast_request_recompute')) forecast_request_recompute($dbh, 'settings changed');
  }
  flash_set($errors ? 'error' : 'success', $errors ? implode(' ', $errors) . ($changed ? " The other $changed change(s) were saved." : '')
    : ($changed ? "Pricing settings saved ($changed change" . ($changed === 1 ? '' : 's') . '). Prices are being recomputed.' : 'Nothing changed.'));
  redirect_to(current_url());
}

$groups = [
  'Limits' => ['pricing_max_discount', 'pricing_max_surcharge', 'pricing_round_to', 'pricing_forecast_days'],
  'Weekend' => ['pricing_weekend_pct', 'pricing_weekend_days'],
  'Festivals and seasons' => ['pricing_festival_high_pct', 'pricing_festival_medium_pct', 'pricing_season_pct'],
  'Lead time' => ['pricing_lastminute_days', 'pricing_lastminute_pct', 'pricing_early_days', 'pricing_early_pct'],
  'Occupancy of the car' => ['pricing_occupancy_window', 'pricing_occupancy_high', 'pricing_occupancy_high_pct', 'pricing_occupancy_low', 'pricing_occupancy_low_pct'],
  'Recent demand for the car' => ['pricing_demand_days', 'pricing_demand_high_count', 'pricing_demand_high_pct'],
  'Machine-learning model' => ['pricing_ml_threshold', 'pricing_ml_enabled'],
];
$dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

admin_page_start('Smart Pricing', 'pricing.php', [], [
  'lead' => 'Prices = base price × (1 + the factors that apply), kept between −' . setting('pricing_max_discount') . '% and +' . setting('pricing_max_surcharge') . '%.' . (PRICING_ENABLED ? '' : ' Smart prices are not shown on the website yet (PRICING_ENABLED is off).'),
]);
?>
<!-- forecast-status -->
<form method="post">
  <?php echo csrf_field(); ?>
  <?php foreach ($groups as $groupLabel => $keys) { ?>
    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-sliders" aria-hidden="true"></i><?php echo h($groupLabel); ?></h2></div>
      <div class="a-card__body a-form-grid a-form-grid--3">
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
<?php admin_page_end(); ?>
