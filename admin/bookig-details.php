<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/bookings.php';
admin_require_login();

$bid = intval($_GET['bid'] ?? 0);
if (admin_booking_actions($dbh)) {
  redirect_to(current_url());
}

$query = $dbh->prepare(BOOKING_SELECT . " WHERE b.id = :bid");
$query->bindParam(':bid', $bid, PDO::PARAM_INT);
$query->execute();
$b = $query->fetch(PDO::FETCH_OBJ);
if (!$b) {
  flash_set('error', 'That booking no longer exists.');
  redirect_to('manage-bookings.php');
}

$status = (int)$b->Status;
$days = admin_rental_days($b->FromDate, $b->ToDate);
$rate = (int)$b->PricePerDay;
$today = date('Y-m-d');
$vehicleName = $b->VehiclesTitle !== null ? vehicle_display_name($b->BrandName, $b->VehiclesTitle) : 'Removed vehicle';
$customerBookings = $dbh->prepare("SELECT COUNT(*) FROM tblbooking WHERE userEmail = :email");
$customerBookings->execute([':email' => $b->userEmail]);
$customerBookings = (int)$customerBookings->fetchColumn();

// Timeline: requested -> confirmed / cancelled -> pick-up -> return.
$steps = [['Booking requested', admin_date($b->PostingDate, true), 'is-done', 'fa-paper-plane']];
if ($status === 2) {
  $steps[] = ['Cancelled', $b->LastUpdationDate ? admin_date($b->LastUpdationDate, true) : '', 'is-cancelled', 'fa-xmark'];
} else {
  $steps[] = $status === 1
    ? ['Confirmed', $b->LastUpdationDate ? admin_date($b->LastUpdationDate, true) : '', 'is-done', 'fa-check']
    : ['Waiting for confirmation', 'Use Confirm or Cancel at the top of the page', 'is-current', 'fa-hourglass-half'];
  $started = $status === 1 && $today >= $b->FromDate;
  $ended = $status === 1 && $today >= $b->ToDate;
  // A booking still pending after its dates can't happen as booked any more.
  $note = fn(string $date, bool $done) => $done ? '' : ($today > $date ? ' (date has passed)' : ' (upcoming)');
  $steps[] = ['Pick-up', admin_date($b->FromDate) . $note((string)$b->FromDate, $started), $started ? 'is-done' : '', 'fa-key'];
  $steps[] = ['Return', admin_date($b->ToDate) . $note((string)$b->ToDate, $ended), $ended ? 'is-done' : '', 'fa-flag-checkered'];
}

admin_page_start('Booking #' . $b->BookingNumber, 'bookig-details.php', [['Bookings', 'manage-bookings.php']], [
  'lead' => 'Booked on ' . admin_date($b->PostingDate, true),
  'actions' => admin_booking_buttons($b, true)
    . '<button type="button" class="btn btn-light" onclick="window.print()"><i class="fa fa-print" aria-hidden="true"></i>Print</button>',
]);
?>
<div class="a-detail-grid">
  <div>
    <section class="a-card">
      <div class="a-card__head">
        <h2 class="a-card__title"><i class="fa fa-route" aria-hidden="true"></i>Status</h2>
        <?php echo admin_booking_badge($status); ?>
      </div>
      <div class="a-card__body">
        <ol class="a-timeline">
          <?php foreach ($steps as [$title, $meta, $class, $icon]) { ?>
            <li class="<?php echo $class; ?>">
              <span class="a-timeline__dot"><i class="fa <?php echo $class === 'is-done' ? 'fa-check' : $icon; ?>" aria-hidden="true"></i></span>
              <span class="a-timeline__title"><?php echo h($title); ?></span>
              <span class="a-timeline__meta"><?php echo h($meta); ?></span>
            </li>
          <?php } ?>
        </ol>
      </div>
    </section>

    <section class="a-card">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-car" aria-hidden="true"></i>Trip</h2></div>
      <div class="a-card__body">
        <div class="a-booking-vehicle mb-3">
          <?php echo admin_vehicle_thumb($b->Vimage1, $vehicleName); ?>
          <div>
            <?php if ($b->VehiclesTitle !== null) { ?>
              <a class="a-strong text-decoration-none" href="edit-vehicle.php?id=<?php echo (int)$b->vid; ?>"><?php echo h($vehicleName); ?></a>
            <?php } else { ?><span class="a-strong"><?php echo h($vehicleName); ?></span><?php } ?>
            <div class="a-muted"><?php echo format_price_per_day($rate); ?></div>
          </div>
        </div>
        <dl class="a-dl">
          <dt>Pick-up</dt><dd><?php echo admin_date($b->FromDate); ?></dd>
          <dt>Return</dt><dd><?php echo admin_date($b->ToDate); ?></dd>
          <dt>Length</dt><dd><?php echo $days; ?> day<?php echo $days === 1 ? '' : 's'; ?></dd>
          <dt>Message</dt><dd><?php echo trim((string)$b->message) !== '' ? nl2br(h($b->message)) : '<span class="a-muted">No message</span>'; ?></dd>
          <dt>Last change</dt><dd><?php echo admin_date($b->LastUpdationDate, true) ?: '<span class="a-muted">Not changed since booking</span>'; ?></dd>
        </dl>
      </div>
    </section>
  </div>

  <div>
    <section class="a-card">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-user" aria-hidden="true"></i>Customer</h2></div>
      <div class="a-card__body">
        <div class="a-customer">
          <span class="a-customer__avatar" aria-hidden="true"><?php echo h(initials($b->FullName ?? $b->userEmail)); ?></span>
          <div>
            <span class="a-strong d-block"><?php echo $b->FullName !== null ? h($b->FullName) : 'Deleted account'; ?></span>
            <span class="a-muted"><?php echo $customerBookings; ?> booking<?php echo $customerBookings === 1 ? '' : 's'; ?><?php echo $b->RegDate ? ' · customer since ' . admin_date($b->RegDate) : ''; ?></span>
          </div>
        </div>
        <dl class="a-dl">
          <dt>Email</dt><dd><a href="mailto:<?php echo h($b->userEmail); ?>"><?php echo h($b->userEmail); ?></a></dd>
          <dt>Phone</dt><dd><?php echo $b->ContactNo ? '<a href="tel:' . h($b->ContactNo) . '">' . h($b->ContactNo) . '</a>' : '<span class="a-muted">Not given</span>'; ?></dd>
          <dt>Address</dt><dd><?php echo h(implode(', ', array_filter([$b->Address, $b->City, $b->Country]))) ?: '<span class="a-muted">Not given</span>'; ?></dd>
        </dl>
      </div>
    </section>

    <section class="a-card">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-receipt" aria-hidden="true"></i>Price</h2></div>
      <div class="a-card__body">
        <ul class="a-price">
          <li><span>Daily price</span><span class="a-money"><?php echo format_price($rate); ?></span></li>
          <li><span>Days</span><span>× <?php echo $days; ?></span></li>
          <li><span class="a-strong">Total</span><span class="a-price__total"><?php echo format_price($days * $rate); ?></span></li>
        </ul>
        <p class="a-muted mb-0 mt-2">Worked out from the car's current daily price.</p>
      </div>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
