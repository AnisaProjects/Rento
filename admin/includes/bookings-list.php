<?php
// One bookings page for every status. The old URLs set $bookingFilter and include this file:
//   manage-bookings.php (all, or ?status=new|confirmed|canceled), new-bookings.php,
//   confirmed-bookings.php, canceled-bookings.php
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/bookings.php';
admin_require_login();

// filter => [page, title, SQL condition]
const BOOKING_FILTERS = [
    'all' => ['manage-bookings.php', 'Manage Bookings', '1 = 1'],
    'new' => ['new-bookings.php', 'New Bookings', '(b.Status = 0 OR b.Status IS NULL)'],
    'confirmed' => ['confirmed-bookings.php', 'Confirmed Bookings', 'b.Status = 1'],
    'canceled' => ['canceled-bookings.php', 'Canceled Bookings', 'b.Status = 2'],
];
if ($bookingFilter === 'all' && isset(BOOKING_FILTERS[$_GET['status'] ?? ''])) {
    $bookingFilter = $_GET['status'];
}
[$filterPage, $filterTitle, $filterSql] = BOOKING_FILTERS[$bookingFilter];

if (admin_booking_actions($dbh)) {
    redirect_to(current_url());
}

$counts = $dbh->query("SELECT COUNT(*) AS allb, SUM(Status = 0 OR Status IS NULL) AS newb, SUM(Status = 1) AS confirmedb, SUM(Status = 2) AS canceledb FROM tblbooking")->fetch(PDO::FETCH_ASSOC);
$bookings = $dbh->query(BOOKING_SELECT . " WHERE $filterSql ORDER BY b.PostingDate DESC, b.id DESC")->fetchAll(PDO::FETCH_OBJ);
$filterLabels = ['all' => 'All', 'new' => 'New', 'confirmed' => 'Confirmed', 'canceled' => 'Canceled'];

admin_page_start($filterTitle, $filterPage, [['Bookings', 'manage-bookings.php']], [
    'libs' => ['datatables'],
    'lead' => $bookingFilter === 'new' && $bookings ? count($bookings) . ' booking(s) waiting for you to confirm or cancel.' : count($bookings) . ' booking(s).',
]);
?>
<nav class="a-filters mb-3" aria-label="Filter bookings by status">
  <?php foreach ($filterLabels as $key => $label) { ?>
    <a class="a-filter<?php echo $key === $bookingFilter ? ' is-active' : ''; ?>" href="<?php echo BOOKING_FILTERS[$key][0]; ?>"<?php echo $key === $bookingFilter ? ' aria-current="page"' : ''; ?>>
      <?php echo $label; ?> <span class="a-filter__count"><?php echo (int)$counts[$key . 'b']; ?></span>
    </a>
  <?php } ?>
</nav>

<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search bookings" data-empty="No <?php echo $bookingFilter === 'all' ? '' : strtolower($filterLabels[$bookingFilter]) . ' '; ?>bookings" data-order='[[6,"desc"]]'>
      <thead>
        <tr>
          <th data-priority="1">Booking</th>
          <th data-priority="3">Customer</th>
          <th>Vehicle</th>
          <th>Dates</th>
          <th>Total</th>
          <th data-priority="2">Status</th>
          <th>Booked on</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b) {
          $amount = booking_total($b);
          $days = $amount['rental_days'];
          $name = $b->VehiclesTitle !== null ? vehicle_display_name($b->BrandName, $b->VehiclesTitle) : 'Removed vehicle'; ?>
          <tr>
            <td><a class="a-strong text-decoration-none" href="bookig-details.php?bid=<?php echo (int)$b->id; ?>">#<?php echo h($b->BookingNumber); ?></a></td>
            <td>
              <span class="a-strong d-block"><?php echo $b->FullName !== null ? h($b->FullName) : 'Deleted account'; ?></span>
              <span class="a-muted"><?php echo h($b->userEmail); ?></span>
            </td>
            <td>
              <div class="a-vehicle-cell">
                <?php echo admin_vehicle_thumb($b->Vimage1); ?>
                <span><?php echo h($name); ?></span>
              </div>
            </td>
            <td data-order="<?php echo (int)strtotime((string)$b->FromDate); ?>">
              <span class="d-block text-nowrap"><?php echo admin_date($b->FromDate); ?> – <?php echo admin_date($b->ToDate); ?></span>
              <span class="a-muted"><?php echo $days; ?> day<?php echo $days === 1 ? '' : 's'; ?></span>
            </td>
            <td data-order="<?php echo $amount['total']; ?>"><span class="a-money"><?php echo format_price($amount['total']); ?></span><?php echo $amount['source'] === 'base' ? '<span class="a-muted d-block">base price</span>' : ''; ?></td>
            <td data-order="<?php echo (int)$b->Status; ?>"><?php echo admin_booking_badge($b->Status); ?></td>
            <?php echo admin_date_cell($b->PostingDate, true); ?>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('bookig-details.php?bid=' . (int)$b->id, 'fa-eye', 'View booking'); ?>
                <?php echo admin_booking_buttons($b); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
