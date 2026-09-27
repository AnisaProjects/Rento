<?php
// AI Insights: what the smart pricing suggests for each active car, with Apply (base price =
// suggestion) and Override (manual price for dates). Rule-based until the ML model unlocks.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

const INSIGHT_HORIZON = 60;

// Forecast rows per car for the next 60 days.
function insight_rows(PDO $dbh): array
{
    $rows = [];
    $query = $dbh->query("SELECT VehicleId, ForecastDate, BasePrice, Price, Multiplier, Reasons, IsAvailable FROM tbl_price_forecast
        WHERE ForecastDate BETWEEN CURDATE() AND CURDATE() + INTERVAL " . (INSIGHT_HORIZON - 1) . " DAY ORDER BY ForecastDate");
    foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
        $row->meta = json_decode((string)$row->Reasons, true) ?: [];
        $rows[(int)$row->VehicleId][] = $row;
    }
    return $rows;
}

// Suggested base price: the shared helper (includes/price-insights.php), 60 days.
function insight_suggestion(int $carId, int $base): ?int
{
    global $dbh;
    return vehicle_price_suggestion($dbh, $carId, $base, INSIGHT_HORIZON)['suggestion'] ?? null;
}

$cars = $dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.CarType, v.DriverOnly, v.Vimage1, br.BrandName FROM tblvehicles v
  LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE v.IsActive = 1 ORDER BY v.VehiclesTitle")->fetchAll(PDO::FETCH_OBJ);
$carById = [];
foreach ($cars as $c) $carById[(int)$c->id] = $c;

if (isset($_POST['applybase'])) {
  $car = $carById[intval($_POST['applybase'])] ?? null;
  $rows = $car ? (insight_rows($dbh)[(int)$car->id] ?? []) : [];
  $suggestion = $car ? insight_suggestion((int)$car->id, (int)$car->PricePerDay) : null;
  if (!$car || !$suggestion) {
    flash_set('error', 'There is no suggestion for that car yet. Recompute prices first.');
  } else {
    $dbh->prepare("UPDATE tblvehicles SET PricePerDay = :p WHERE id = :id")->execute([':p' => $suggestion, ':id' => $car->id]);
    forecast_request_recompute($dbh, 'insights: base price applied for ' . $car->VehiclesTitle);
    flash_set('success', vehicle_display_name($car->BrandName, $car->VehiclesTitle) . ': base price changed from ' . format_price($car->PricePerDay) . ' to ' . format_price($suggestion) . '. Prices are being recomputed.');
  }
  redirect_to(current_url());
}

if (isset($_POST['addoverride'])) {
  $car = $carById[intval($_POST['addoverride'])] ?? null;
  $fromDate = (string)($_POST['overridefrom'] ?? '');
  $toDate = (string)($_POST['overrideto'] ?? '');
  $price = (int)preg_replace('/\D/', '', (string)($_POST['overrideprice'] ?? ''));
  $note = mb_substr(trim((string)($_POST['overridenote'] ?? '')), 0, 200);
  $isDate = fn($d) => ($x = DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
  $error = match (true) {
    !$car => 'Please choose an active car.',
    !$isDate($fromDate) || !$isDate($toDate) => 'Please enter valid dates.',
    $fromDate < date('Y-m-d') => 'The override can\'t start in the past.',
    $toDate < $fromDate => 'The end date must be on or after the start date.',
    $price < 1 || $price > 10000000 => 'Please enter a price in rupees.',
    default => '',
  };
  if ($error) {
    flash_set('error', $error);
  } else {
    $dbh->prepare("INSERT INTO tbl_price_overrides (VehicleId, FromDate, ToDate, Price, Note, CreatedBy) VALUES (:v, :f, :t, :p, :n, :by)")
      ->execute([':v' => $car->id, ':f' => $fromDate, ':t' => $toDate, ':p' => $price, ':n' => $note !== '' ? $note : null, ':by' => $_SESSION['alogin']]);
    forecast_request_recompute($dbh, 'insights: manual price for ' . $car->VehiclesTitle);
    flash_set('success', 'Manual price ' . format_price($price) . '/day set for ' . vehicle_display_name($car->BrandName, $car->VehiclesTitle)
      . ' from ' . date('j M', strtotime($fromDate)) . ' to ' . date('j M Y', strtotime($toDate)) . '. Prices are being recomputed.'
      . ($toDate > date('Y-m-d', strtotime('+' . (int)setting('pricing_forecast_days') - 1 . ' days')) ? ' Days beyond the forecast are applied as they come into range.' : ''));
  }
  redirect_to(current_url());
}

if (isset($_POST['deloverride'])) {
  $query = $dbh->prepare("DELETE FROM tbl_price_overrides WHERE id = :id");
  $query->execute([':id' => intval($_POST['deloverride'])]);
  if ($query->rowCount()) forecast_request_recompute($dbh, 'insights: manual price removed');
  flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Manual price removed. Prices are being recomputed.' : 'That override no longer exists.');
  redirect_to(current_url());
}

$rowsByCar = insight_rows($dbh);
$overrides = [];
foreach ($dbh->query("SELECT * FROM tbl_price_overrides WHERE ToDate >= CURDATE() ORDER BY FromDate")->fetchAll(PDO::FETCH_OBJ) as $o) {
  $overrides[(int)$o->VehicleId][] = $o;
}
$bookingCount = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status IS NULL OR Status <> 2")->fetchColumn();
$mlThreshold = (int)setting('pricing_ml_threshold');
$status = forecast_status($dbh);

$insights = [];
foreach ($cars as $car) {
  $rows = $rowsByCar[(int)$car->id] ?? [];
  $base = (int)$car->PricePerDay;
  $first30 = array_slice($rows, 0, 30);
  $avg = fn($list) => $list ? array_sum(array_map(fn($r) => (int)$r->Price, $list)) / count($list) : null;
  // Main reasons: ranked by total effect (percent x days), shown with their number of days.
  $reasonDays = [];
  $reasonImpact = [];
  foreach ($rows as $r) {
    foreach ($r->meta['reasons'] ?? [] as $reason) {
      $reasonDays[$reason['label']] = ($reasonDays[$reason['label']] ?? 0) + 1;
      $reasonImpact[$reason['label']] = ($reasonImpact[$reason['label']] ?? 0) + abs((float)$reason['pct']);
    }
  }
  arsort($reasonImpact);
  $reasonDays = array_replace(array_intersect_key($reasonImpact, $reasonDays), $reasonDays);
  $reasonDays = array_replace(array_map(fn() => 0, $reasonImpact), $reasonDays);
  $low = $rows ? array_reduce($rows, fn($c, $r) => !$c || (int)$r->Price < (int)$c->Price ? $r : $c) : null;
  $high = $rows ? array_reduce($rows, fn($c, $r) => !$c || (int)$r->Price > (int)$c->Price ? $r : $c) : null;
  $insights[] = ['car' => $car, 'rows' => $rows, 'avg30' => $avg($first30), 'avg60' => $avg($rows), 'low' => $low, 'high' => $high,
    'reasons' => array_slice($reasonDays, 0, 3, true), 'suggestion' => insight_suggestion((int)$car->id, $base), 'stale' => $rows && (int)$rows[0]->BasePrice !== $base];
}
$chartCarId = intval($_GET['car'] ?? ($cars[0]->id ?? 0));
$chartRows = $rowsByCar[$chartCarId] ?? [];
$chartCar = $carById[$chartCarId] ?? null;
$chart = [
  'labels' => array_map(fn($r) => date('j M', strtotime($r->ForecastDate)), $chartRows),
  'price' => array_map(fn($r) => (int)$r->Price, $chartRows),
  'base' => array_map(fn($r) => (int)$r->BasePrice, $chartRows),
  'demand' => array_map(fn($r) => round(array_sum(array_map(fn($x) => max(0, (float)$x['pct']), $r->meta['reasons'] ?? [])), 1), $chartRows),
  'booked' => array_map(fn($r) => (int)$r->IsAvailable ? null : (int)$r->Price, $chartRows),
];

admin_page_start('AI Insights', 'insights.php', [], [
  'libs' => ['charts'],
  'lead' => 'Suggested prices for the next ' . INSIGHT_HORIZON . ' days, from the pricing rules and your festival calendar.',
  'actions' => '<a class="btn btn-light" href="pricing.php"><i class="fa fa-sliders" aria-hidden="true"></i>Pricing settings</a>',
]);
?>
<div class="a-notice mb-4"><i class="fa fa-scale-balanced mt-1" aria-hidden="true"></i><div>
  <strong>Rule-based suggestions.</strong> These come from the pricing rules (weekends, festivals and travel days, seasons, lead time<?php echo $bookingCount >= (int)setting('pricing_demand_min_bookings') ? ', occupancy and demand' : ''; ?>), not from a trained model.
  The machine-learning model unlocks at <?php echo $mlThreshold; ?> bookings (now <?php echo $bookingCount; ?>).
  Forecast last computed <?php echo $status['lastDone'] ? admin_date($status['lastDone']->FinishedAt, true) : 'never'; ?>.
</div></div>

<section class="a-card">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa fa-lightbulb" aria-hidden="true"></i>Suggestions per car</h2>
    <span class="a-muted">Apply sets the base price to the suggestion; the rules then apply on top of it.</span>
  </div>
  <div class="a-card__body a-card__body--flush">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Price suggestions per car">
      <table class="table a-table mb-0">
        <thead>
          <tr><th class="ps-4">Car</th><th>Base</th><th>Avg 30 days</th><th>Avg 60 days</th><th>Lowest day</th><th>Highest day</th><th>Main reasons</th><th>Suggestion</th><th class="pe-4">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($insights as $in) {
            $car = $in['car'];
            $name = vehicle_display_name($car->BrandName, $car->VehiclesTitle); ?>
            <tr>
              <td class="ps-4"><div class="a-vehicle-cell"><?php echo admin_vehicle_thumb($car->Vimage1); ?><div><a class="a-strong text-decoration-none" href="insights.php?car=<?php echo (int)$car->id; ?>#chart"><?php echo h($name); ?></a><div class="a-muted"><?php echo h($car->CarType ?: 'No type'); ?><?php echo (int)$car->DriverOnly ? ' · with driver' : ''; ?></div></div></div></td>
              <td><span class="a-money"><?php echo format_price($car->PricePerDay); ?></span></td>
              <?php if (!$in['rows']) { ?>
                <td colspan="5" class="a-muted">No forecast yet: recompute prices on the Smart Pricing page.</td><td>–</td>
              <?php } else { ?>
                <td><span class="a-money"><?php echo format_price(round($in['avg30'])); ?></span></td>
                <td><span class="a-money"><?php echo format_price(round($in['avg60'])); ?></span></td>
                <td class="text-nowrap"><?php echo format_price($in['low']->Price); ?><span class="a-muted d-block"><?php echo date('D j M', strtotime($in['low']->ForecastDate)); ?></span></td>
                <td class="text-nowrap"><?php echo format_price($in['high']->Price); ?><span class="a-muted d-block"><?php echo date('D j M', strtotime($in['high']->ForecastDate)); ?></span></td>
                <td class="a-muted small"><?php echo $in['reasons'] ? h(implode(' · ', array_map(fn($l, $n) => $l . ' (' . $n . ' days)', array_keys($in['reasons']), $in['reasons']))) : 'No adjustments: base price every day'; ?></td>
                <td><?php echo $in['suggestion'] ? '<span class="a-money">' . format_price($in['suggestion']) . '</span>' . ($in['suggestion'] !== (int)$car->PricePerDay ? '<span class="a-muted d-block">' . sprintf('%+d%%', round(($in['suggestion'] / max(1, $car->PricePerDay) - 1) * 100)) . '</span>' : '<span class="a-muted d-block">same</span>') : '–'; ?></td>
              <?php } ?>
              <td class="pe-4">
                <div class="a-actions">
                  <?php if ($in['suggestion'] && $in['suggestion'] !== (int)$car->PricePerDay && !$in['stale']) {
                    echo admin_action_button(['applybase' => $car->id], 'fa-check', 'Apply suggestion', 'success',
                      'Change the base price of ' . $name . ' from ' . format_price($car->PricePerDay) . ' to ' . format_price($in['suggestion'])
                      . '? The weekend, festival and other rules will then apply on top of the new base price.');
                  } ?>
                  <button type="button" class="a-icon-btn a-icon-btn--primary" data-bs-toggle="modal" data-bs-target="#override-modal" data-override-car="<?php echo (int)$car->id; ?>" data-override-name="<?php echo h($name); ?>" data-override-price="<?php echo (int)$car->PricePerDay; ?>" aria-label="Set a manual price for <?php echo h($name); ?>" title="Set a manual price"><i class="fa fa-pen-to-square" aria-hidden="true"></i></button>
                </div>
              </td>
            </tr>
            <?php foreach ($overrides[(int)$car->id] ?? [] as $o) { ?>
              <tr class="table-light">
                <td class="ps-5 small" colspan="7"><i class="fa fa-hand text-body-secondary me-1" aria-hidden="true"></i>Manual price <strong><?php echo format_price($o->Price); ?>/day</strong>, <?php echo date('j M', strtotime($o->FromDate)); ?> – <?php echo date('j M Y', strtotime($o->ToDate)); ?><?php echo $o->Note ? ' · ' . h($o->Note) : ''; ?> <span class="a-muted">(<?php echo h($o->CreatedBy); ?>, <?php echo admin_date($o->CreatedAt); ?>)</span></td>
                <td></td>
                <td class="pe-4"><?php echo admin_action_button(['deloverride' => $o->id], 'fa-trash', 'Remove manual price', 'danger', 'Remove this manual price? The rule-based prices come back for those days.'); ?></td>
              </tr>
            <?php } ?>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="a-card" id="chart">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa fa-chart-line" aria-hidden="true"></i>Next <?php echo INSIGHT_HORIZON; ?> days: price and demand</h2>
    <form method="get" action="insights.php#chart">
      <label class="visually-hidden" for="car">Car</label>
      <select class="form-select form-select-sm" id="car" name="car" onchange="this.form.submit()">
        <?php foreach ($cars as $c) { ?><option value="<?php echo (int)$c->id; ?>"<?php echo (int)$c->id === $chartCarId ? ' selected' : ''; ?>><?php echo h(vehicle_display_name($c->BrandName, $c->VehiclesTitle)); ?></option><?php } ?>
      </select>
    </form>
  </div>
  <div class="a-card__body">
    <?php if ($chartRows) { ?>
      <div class="a-chart" style="height:320px"><canvas id="insight-chart" role="img" aria-label="Price per day and demand signal for <?php echo h($chartCar ? vehicle_display_name($chartCar->BrandName, $chartCar->VehiclesTitle) : ''); ?> for the next <?php echo INSIGHT_HORIZON; ?> days"></canvas></div>
      <p class="a-muted small mb-0 mt-2">Demand signal = the day's surcharges from weekends, festivals, travel days and seasons (%). Red dots = days already booked.</p>
    <?php } else { ?><p class="a-empty">No forecast for this car yet.</p><?php } ?>
  </div>
</section>

<div class="modal fade" id="override-modal" tabindex="-1" aria-labelledby="override-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content" data-confirm="Set this manual price? It replaces the rule-based price on those days until you remove it." data-confirm-title="Set a manual price?" data-confirm-ok="Set manual price">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="addoverride" value="">
      <div class="modal-header"><h2 class="modal-title fs-5" id="override-title">Manual price</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p class="a-muted" data-override-for></p>
        <div class="row g-3">
          <div class="col-6"><label class="form-label" for="overridefrom">From</label><input class="form-control" type="date" id="overridefrom" name="overridefrom" min="<?php echo date('Y-m-d'); ?>" required></div>
          <div class="col-6"><label class="form-label" for="overrideto">To</label><input class="form-control" type="date" id="overrideto" name="overrideto" min="<?php echo date('Y-m-d'); ?>" required></div>
          <div class="col-12"><label class="form-label" for="overrideprice">Price per day</label><div class="input-group"><span class="input-group-text">Rs</span><input class="form-control" type="number" min="1" step="50" id="overrideprice" name="overrideprice" required></div>
            <div class="form-text">Used exactly as entered: the pricing limits don't apply to manual prices.</div></div>
          <div class="col-12"><label class="form-label" for="overridenote">Note (optional)</label><input class="form-control" id="overridenote" name="overridenote" maxlength="200" placeholder="e.g. Wedding season deal"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Set manual price</button></div>
    </form>
  </div>
</div>
<?php
$json = json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
admin_page_end(<<<JS
(function () {
  // Fill the manual-price form for the car whose button was clicked.
  var modal = document.getElementById('override-modal');
  modal.addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget;
    modal.querySelector('[name=addoverride]').value = b.dataset.overrideCar;
    modal.querySelector('[data-override-for]').textContent = b.dataset.overrideName + ' · base price Rs ' + Number(b.dataset.overridePrice).toLocaleString('en-US') + '/day';
    modal.querySelector('#overrideprice').value = b.dataset.overridePrice;
  });
  // The confirmation modal opens on top: close this one first.
  modal.querySelector('form').addEventListener('submit', function () { bootstrap.Modal.getInstance(modal).hide(); }, true);

  var canvas = document.getElementById('insight-chart');
  if (!canvas) return;
  var d = $json;
  var css = getComputedStyle(document.documentElement), c = function (n) { return css.getPropertyValue(n).trim(); };
  Chart.defaults.font.family = c('--rento-font-body');
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;
  new Chart(canvas, {
    data: {
      labels: d.labels,
      datasets: [
        { type: 'line', label: 'Price per day (Rs)', data: d.price, borderColor: c('--rento-button'), backgroundColor: c('--rento-button'), pointRadius: 0, tension: 0.2, yAxisID: 'y' },
        { type: 'line', label: 'Base price', data: d.base, borderColor: c('--rento-gray-400'), borderDash: [6, 4], pointRadius: 0, yAxisID: 'y' },
        { type: 'scatter', label: 'Booked', data: d.booked, backgroundColor: c('--rento-danger'), pointRadius: 4, yAxisID: 'y' },
        { type: 'bar', label: 'Demand signal (%)', data: d.demand, backgroundColor: 'rgba(47, 127, 209, .25)', yAxisID: 'y1' }
      ]
    },
    options: {
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      scales: {
        y: { position: 'left', title: { display: true, text: 'Rs per day' } },
        y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Demand %' } },
        x: { ticks: { maxTicksLimit: 12 } }
      },
      plugins: { legend: { position: 'bottom' } }
    }
  });
})();
JS);
