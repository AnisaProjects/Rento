<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

// Rental days as the public site counts them (DATEDIFF, never negative).
const BOOKING_DAYS_SQL = "GREATEST(DATEDIFF(b.ToDate, b.FromDate), 0)";
const UTILISATION_WINDOW_DAYS = 30;

$kpi = [];
// Revenue: confirmed bookings, days x the vehicle's current price per day.
$kpi['revenue'] = (int)$dbh->query("SELECT COALESCE(SUM(" . BOOKING_DAYS_SQL . " * v.PricePerDay), 0)
  FROM tblbooking b JOIN tblvehicles v ON v.id = b.VehicleId WHERE b.Status = 1")->fetchColumn();
// Active: confirmed and not yet finished (on the road now or starting later).
$kpi['active'] = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 1 AND ToDate >= CURDATE()")->fetchColumn();
$kpi['pending'] = (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 0 OR Status IS NULL")->fetchColumn();
$fleet = (int)$dbh->query("SELECT COUNT(*) FROM tblvehicles")->fetchColumn();
// Utilisation: confirmed rental days inside the last 30 days / (fleet x 30 days).
$bookedDays = (int)$dbh->query("SELECT COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(b.ToDate, CURDATE()),
  GREATEST(b.FromDate, CURDATE() - INTERVAL " . UTILISATION_WINDOW_DAYS . " DAY)))), 0) FROM tblbooking b WHERE b.Status = 1")->fetchColumn();
$kpi['utilisation'] = $fleet > 0 ? round($bookedDays / ($fleet * UTILISATION_WINDOW_DAYS) * 100, 1) : 0;
$kpi['newUsers'] = (int)$dbh->query("SELECT COUNT(*) FROM tblusers WHERE RegDate >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$kpi['newUsersLast'] = (int)$dbh->query("SELECT COUNT(*) FROM tblusers WHERE RegDate >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
  AND RegDate < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$kpi['unread'] = (int)$dbh->query("SELECT COUNT(*) FROM tblcontactusquery WHERE status IS NULL OR status = 0")->fetchColumn();
$totals = $dbh->query("SELECT (SELECT COUNT(*) FROM tblbooking) bookings, (SELECT COUNT(*) FROM tblusers) users,
  (SELECT COUNT(*) FROM tblbrands) brands, (SELECT COUNT(*) FROM tblsubscribers) subscribers,
  (SELECT COUNT(*) FROM tbltestimonial) testimonials")->fetch(PDO::FETCH_OBJ);

// Chart: bookings per month for the last 12 months, by status (by the date the booking was made).
$months = [];
for ($i = 11; $i >= 0; $i--) {
  $key = date('Y-m', strtotime("first day of -$i month"));
  $months[$key] = ['label' => date('M y', strtotime($key . '-01')), 0 => 0, 1 => 0, 2 => 0];
}
$rows = $dbh->query("SELECT DATE_FORMAT(PostingDate, '%Y-%m') ym, COALESCE(Status, 0) st, COUNT(*) n FROM tblbooking
  WHERE PostingDate >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY ym, st")->fetchAll(PDO::FETCH_OBJ);
foreach ($rows as $row) {
  if (isset($months[$row->ym])) $months[$row->ym][min(2, (int)$row->st)] += (int)$row->n;
}
$statusCounts = [0, 0, 0];
foreach ($dbh->query("SELECT COALESCE(Status, 0) st, COUNT(*) n FROM tblbooking GROUP BY st")->fetchAll(PDO::FETCH_OBJ) as $row) {
  $statusCounts[min(2, (int)$row->st)] += (int)$row->n;
}
$topVehicles = $dbh->query("SELECT CONCAT(br.BrandName, ' ', v.VehiclesTitle) name, COUNT(b.id) n
  FROM tblbooking b JOIN tblvehicles v ON v.id = b.VehicleId LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
  GROUP BY v.id, name ORDER BY n DESC, v.id LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

$chartData = [
  'months' => array_column($months, 'label'),
  'pending' => array_column($months, 0),
  'confirmed' => array_column($months, 1),
  'cancelled' => array_column($months, 2),
  'status' => $statusCounts,
  'topNames' => array_map(fn($v) => trim(preg_replace('/^(\S+) \1 /i', '$1 ', $v->name)), $topVehicles),
  'topCounts' => array_map(fn($v) => (int)$v->n, $topVehicles),
];

// Recent activity
$recentBookings = $dbh->query("SELECT b.id, b.BookingNumber, b.Status, b.PostingDate, u.FullName, b.userEmail, v.VehiclesTitle
  FROM tblbooking b LEFT JOIN tblusers u ON u.EmailId = b.userEmail LEFT JOIN tblvehicles v ON v.id = b.VehicleId
  ORDER BY b.PostingDate DESC, b.id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);
$recentQueries = $dbh->query("SELECT id, name, EmailId, Message, PostingDate, status FROM tblcontactusquery ORDER BY PostingDate DESC, id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);
$recentUsers = $dbh->query("SELECT id, FullName, EmailId, RegDate FROM tblusers ORDER BY RegDate DESC, id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

admin_page_start('Dashboard', 'dashboard.php', [], [
  'libs' => ['charts'],
  'lead' => 'Welcome back, ' . ($_SESSION['alogin'] ?? '') . '. Here is how Rento is doing today, ' . date('j F Y') . '.',
  'actions' => '<a class="btn btn-light" href="new-bookings.php"><i class="fa fa-clock" aria-hidden="true"></i>Pending bookings</a>'
    . '<a class="btn btn-primary" href="post-avehical.php"><i class="fa fa-plus" aria-hidden="true"></i>Post a vehicle</a>',
]);
?>

<section class="a-kpis" aria-label="Key figures">
  <a class="a-kpi a-kpi--success" href="confirmed-bookings.php">
    <span class="a-kpi__icon"><i class="fa fa-sack-dollar" aria-hidden="true"></i></span>
    <span class="a-kpi__label">Total revenue</span>
    <span class="a-kpi__value"><?php echo format_price($kpi['revenue']); ?></span>
    <span class="a-kpi__note">Confirmed bookings, days × price per day</span>
  </a>
  <a class="a-kpi a-kpi--info" href="confirmed-bookings.php">
    <span class="a-kpi__icon"><i class="fa fa-car-side" aria-hidden="true"></i></span>
    <span class="a-kpi__label">Active bookings</span>
    <span class="a-kpi__value"><?php echo $kpi['active']; ?></span>
    <span class="a-kpi__note">Confirmed, ending today or later</span>
  </a>
  <a class="a-kpi a-kpi--warning" href="new-bookings.php">
    <span class="a-kpi__icon"><i class="fa fa-hourglass-half" aria-hidden="true"></i></span>
    <span class="a-kpi__label">Pending bookings</span>
    <span class="a-kpi__value"><?php echo $kpi['pending']; ?></span>
    <span class="a-kpi__note"><?php echo $kpi['pending'] ? 'Waiting for you to confirm' : 'All caught up'; ?></span>
  </a>
  <a class="a-kpi" href="manage-vehicles.php">
    <span class="a-kpi__icon"><i class="fa fa-chart-pie" aria-hidden="true"></i></span>
    <span class="a-kpi__label">Fleet utilisation</span>
    <span class="a-kpi__value"><?php echo rtrim(rtrim(number_format($kpi['utilisation'], 1), '0'), '.'); ?>%</span>
    <span class="a-kpi__note"><?php echo $bookedDays; ?> of <?php echo $fleet * UTILISATION_WINDOW_DAYS; ?> car-days booked, last <?php echo UTILISATION_WINDOW_DAYS; ?> days</span>
  </a>
  <a class="a-kpi a-kpi--dark" href="reg-users.php">
    <span class="a-kpi__icon"><i class="fa fa-user-plus" aria-hidden="true"></i></span>
    <span class="a-kpi__label">New users this month</span>
    <span class="a-kpi__value"><?php echo $kpi['newUsers']; ?></span>
    <span class="a-kpi__note"><?php echo $kpi['newUsersLast']; ?> last month · <?php echo (int)$totals->users; ?> in total</span>
  </a>
  <a class="a-kpi" href="manage-conactusquery.php">
    <span class="a-kpi__icon"><i class="fa fa-envelope" aria-hidden="true"></i></span>
    <span class="a-kpi__label">Unread queries</span>
    <span class="a-kpi__value"><?php echo $kpi['unread']; ?></span>
    <span class="a-kpi__note"><?php echo $kpi['unread'] ? 'Contact form messages to read' : 'Inbox clear'; ?></span>
  </a>
</section>

<div class="a-grid-2">
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-chart-column" aria-hidden="true"></i>Bookings per month</h2>
      <span class="a-muted">Last 12 months, by booking date</span>
    </div>
    <div class="a-card__body">
      <div class="a-chart"><canvas id="chart-months" role="img" aria-label="Bookings per month for the last 12 months"></canvas></div>
    </div>
  </section>
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-circle-half-stroke" aria-hidden="true"></i>Bookings by status</h2>
      <span class="a-muted"><?php echo (int)$totals->bookings; ?> in total</span>
    </div>
    <div class="a-card__body">
      <div class="a-chart"><canvas id="chart-status" role="img" aria-label="Bookings by status: <?php echo $statusCounts[0]; ?> pending, <?php echo $statusCounts[1]; ?> confirmed, <?php echo $statusCounts[2]; ?> cancelled"></canvas></div>
    </div>
  </section>
</div>

<section class="a-card">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa fa-trophy" aria-hidden="true"></i>Top vehicles</h2>
    <span class="a-muted">Most booked, all time</span>
  </div>
  <div class="a-card__body">
    <?php if ($topVehicles) { ?>
      <div class="a-chart a-chart--sm"><canvas id="chart-top" role="img" aria-label="Most booked vehicles"></canvas></div>
    <?php } else { ?>
      <p class="a-empty"><i class="fa fa-car" aria-hidden="true"></i>No bookings yet.</p>
    <?php } ?>
  </div>
</section>

<div class="a-grid-3 mt-4">
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-calendar-check" aria-hidden="true"></i>Latest bookings</h2>
      <a href="manage-bookings.php" class="small">View all</a>
    </div>
    <?php if ($recentBookings) { ?>
      <ul class="a-activity">
        <?php foreach ($recentBookings as $b) { ?>
          <li>
            <span class="a-activity__icon"><i class="fa fa-calendar" aria-hidden="true"></i></span>
            <div class="a-activity__main">
              <a class="a-activity__title" href="bookig-details.php?bid=<?php echo (int)$b->id; ?>">#<?php echo h($b->BookingNumber); ?> · <?php echo h($b->VehiclesTitle ?? 'Removed vehicle'); ?></a>
              <span class="a-activity__meta"><?php echo h($b->FullName ?? $b->userEmail); ?> · <?php echo admin_date($b->PostingDate); ?></span>
            </div>
            <?php echo admin_booking_badge($b->Status); ?>
          </li>
        <?php } ?>
      </ul>
    <?php } else { ?><p class="a-empty">No bookings yet.</p><?php } ?>
  </section>
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-envelope-open-text" aria-hidden="true"></i>Latest queries</h2>
      <a href="manage-conactusquery.php" class="small">View all</a>
    </div>
    <?php if ($recentQueries) { ?>
      <ul class="a-activity">
        <?php foreach ($recentQueries as $q) { ?>
          <li>
            <span class="a-activity__icon"><i class="fa fa-message" aria-hidden="true"></i></span>
            <div class="a-activity__main">
              <span class="a-activity__title"><?php echo h($q->name); ?></span>
              <span class="a-activity__meta"><?php echo h(mb_strimwidth((string)$q->Message, 0, 60, '…')); ?> · <?php echo admin_date($q->PostingDate); ?></span>
            </div>
            <?php echo (int)$q->status === 1 ? admin_badge('Read') : admin_badge('Unread', 'primary'); ?>
          </li>
        <?php } ?>
      </ul>
    <?php } else { ?><p class="a-empty">No queries yet.</p><?php } ?>
  </section>
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-user-plus" aria-hidden="true"></i>New registrations</h2>
      <a href="reg-users.php" class="small">View all</a>
    </div>
    <?php if ($recentUsers) { ?>
      <ul class="a-activity">
        <?php foreach ($recentUsers as $u) { ?>
          <li>
            <span class="a-activity__icon"><?php echo h(initials((string)$u->FullName)); ?></span>
            <div class="a-activity__main">
              <span class="a-activity__title"><?php echo h($u->FullName); ?></span>
              <span class="a-activity__meta"><?php echo h($u->EmailId); ?> · <?php echo admin_date($u->RegDate); ?></span>
            </div>
          </li>
        <?php } ?>
      </ul>
    <?php } else { ?><p class="a-empty">No users yet.</p><?php } ?>
  </section>
</div>

<?php
$json = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
admin_page_end(<<<JS
(function () {
  var d = $json;
  var css = getComputedStyle(document.documentElement);
  var c = function (name) { return css.getPropertyValue(name).trim(); };
  var colors = { pending: c('--rento-warning'), confirmed: c('--rento-success'), cancelled: c('--rento-danger'), brand: c('--rento-primary') };
  Chart.defaults.font.family = c('--rento-font-body');
  Chart.defaults.color = c('--rento-gray-500');
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) Chart.defaults.animation = false;
  var grid = { color: c('--rento-gray-100') };
  var intTicks = { precision: 0 };

  new Chart(document.getElementById('chart-months'), {
    type: 'bar',
    data: { labels: d.months, datasets: [
      { label: 'Confirmed', data: d.confirmed, backgroundColor: colors.confirmed, borderRadius: 4 },
      { label: 'Pending', data: d.pending, backgroundColor: colors.pending, borderRadius: 4 },
      { label: 'Cancelled', data: d.cancelled, backgroundColor: colors.cancelled, borderRadius: 4 }
    ] },
    options: { maintainAspectRatio: false, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: intTicks, grid: grid } }, plugins: { legend: { position: 'bottom' } } }
  });

  new Chart(document.getElementById('chart-status'), {
    type: 'doughnut',
    data: { labels: ['Pending', 'Confirmed', 'Cancelled'], datasets: [{ data: d.status, backgroundColor: [colors.pending, colors.confirmed, colors.cancelled], borderWidth: 0 }] },
    options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
  });

  var top = document.getElementById('chart-top');
  if (top) new Chart(top, {
    type: 'bar',
    data: { labels: d.topNames, datasets: [{ label: 'Bookings', data: d.topCounts, backgroundColor: colors.brand, borderRadius: 6, maxBarThickness: 28 }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: intTicks, grid: grid }, y: { grid: { display: false } } }, plugins: { legend: { display: false } } }
  });
})();
JS);
