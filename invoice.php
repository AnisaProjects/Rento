<?php
// Printable invoice / booking receipt for a customer's own booking (My Bookings -> Invoice).
//   invoice?booking=<BookingNumber>
// Shows the price stored when the booking was made (quoted_price, per-day breakdown and
// reasons); older bookings without a stored price show days x base price.
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your invoice.');
  flash_open_modal('loginform');
  redirect_to('my-booking');
}
$number = preg_replace('/\D/', '', (string)($_GET['booking'] ?? ''));
$query = $dbh->prepare("SELECT b.*, v.VehiclesTitle, v.PricePerDay, v.CarType, br.BrandName, u.FullName, u.ContactNo, u.Address, u.City
  FROM tblbooking b JOIN tblvehicles v ON v.id = b.VehicleId LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
  LEFT JOIN tblusers u ON u.EmailId = b.userEmail
  WHERE b.BookingNumber = :number AND b.userEmail = :email");
$query->execute([':number' => $number, ':email' => $_SESSION['login']]);
$b = $query->fetch(PDO::FETCH_OBJ);
if (!$b) {
  require __DIR__ . '/404.php';
  exit;
}
$amount = booking_total($b);
$contact = site_contact_info($dbh);
$carName = vehicle_display_name($b->BrandName, $b->VehiclesTitle);
$status = match ((int)$b->Status) { 1 => 'Confirmed', 2 => 'Cancelled', default => 'Awaiting confirmation' };
$issued = $b->priced_at ?: $b->PostingDate;
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Invoice #' . $b->BookingNumber;
  $pageNoindex = true; // account / utility page: not for search engines
  include('includes/head.php');
  ?>
</head>

<body class="r-page r-invoice-page">
  <main id="main" class="r-invoice">
    <div class="r-invoice__actions">
      <a class="r-btn r-btn--outline r-btn--sm" href="my-booking"><i class="fa fa-arrow-left" aria-hidden="true"></i>My bookings</a>
      <button type="button" class="r-btn r-btn--sm" onclick="window.print()"><i class="fa fa-print" aria-hidden="true"></i>Print or save as PDF</button>
    </div>

    <article class="r-invoice__sheet">
      <header class="r-invoice__head">
        <div>
          <div class="r-invoice__brand"><?php echo site_wordmark(); ?></div>
          <p class="r-invoice__from">
            <?php if ($contact->Address) echo htmlentities($contact->Address) . '<br>'; ?>
            <?php if ($contact->ContactNo) echo htmlentities($contact->ContactNo) . '<br>'; ?>
            <?php if ($contact->EmailId) echo htmlentities($contact->EmailId); ?>
          </p>
        </div>
        <div class="r-invoice__meta">
          <h1><?php echo (int)$b->Status === 1 ? 'Booking invoice' : 'Booking summary'; ?></h1>
          <dl>
            <div><dt>Booking</dt><dd>#<?php echo htmlentities($b->BookingNumber); ?></dd></div>
            <div><dt>Date</dt><dd><?php echo date('j M Y', strtotime($issued)); ?></dd></div>
            <div><dt>Status</dt><dd><?php echo $status; ?></dd></div>
          </dl>
        </div>
      </header>

      <section class="r-invoice__parties">
        <div>
          <h2>Billed to</h2>
          <p><strong><?php echo htmlentities($b->FullName ?? ''); ?></strong><br>
            <?php echo htmlentities($b->userEmail); ?><?php echo $b->ContactNo ? '<br>' . htmlentities($b->ContactNo) : ''; ?>
            <?php echo trim(($b->Address ?? '') . ' ' . ($b->City ?? '')) !== '' ? '<br>' . htmlentities(trim(implode(', ', array_filter([$b->Address, $b->City])))) : ''; ?></p>
        </div>
        <div>
          <h2>Rental</h2>
          <p><strong><?php echo htmlentities($carName); ?></strong><?php echo $b->CarType ? ' (' . htmlentities($b->CarType) . ')' : ''; ?><br>
            Pickup <?php echo date('D j M Y', strtotime($b->FromDate)); ?><br>
            Return <?php echo date('D j M Y', strtotime($b->ToDate)); ?><br>
            <?php echo $amount['rental_days']; ?> rental day<?php echo $amount['rental_days'] === 1 ? '' : 's'; ?> (pickup and return day both count)</p>
        </div>
      </section>

      <table class="r-invoice__table">
        <thead><tr><th scope="col">Day</th><th scope="col">Details</th><th scope="col" class="r-invoice__num">Price</th></tr></thead>
        <tbody>
          <?php if ($amount['source'] === 'quoted' && $amount['per_day']) {
            foreach ($amount['per_day'] as $d) { ?>
              <tr>
                <td><?php echo date('D j M', strtotime($d['date'])); ?></td>
                <td><?php echo !empty($d['reasons']) ? htmlentities(implode(', ', $d['reasons'])) : 'Standard daily price'; ?></td>
                <td class="r-invoice__num"><?php echo format_price($d['price']); ?></td>
              </tr>
            <?php }
          } else { ?>
            <tr>
              <td><?php echo $amount['rental_days']; ?> days</td>
              <td><?php echo $amount['rental_days']; ?> × <?php echo format_price($b->PricePerDay); ?> daily price</td>
              <td class="r-invoice__num"><?php echo format_price($amount['total']); ?></td>
            </tr>
          <?php } ?>
        </tbody>
        <tfoot>
          <?php if ($amount['source'] === 'quoted' && $amount['base_total'] !== $amount['total']) { ?>
            <tr class="r-invoice__sub"><td colspan="2">Standard price for these days</td><td class="r-invoice__num"><?php echo format_price($amount['base_total']); ?></td></tr>
            <tr class="r-invoice__sub"><td colspan="2"><?php echo $amount['total'] > $amount['base_total'] ? 'Festival, weekend and other adjustments' : 'Savings'; ?></td><td class="r-invoice__num"><?php echo ($amount['total'] > $amount['base_total'] ? '+ ' : '− ') . format_price(abs($amount['total'] - $amount['base_total'])); ?></td></tr>
          <?php } ?>
          <tr class="r-invoice__total"><td colspan="2">Total (NPR)</td><td class="r-invoice__num"><?php echo format_price($amount['total']); ?></td></tr>
        </tfoot>
      </table>

      <footer class="r-invoice__foot">
        <p><?php echo $amount['source'] === 'quoted'
          ? 'Price as quoted when you sent the booking request on ' . date('j M Y, H:i', strtotime($issued)) . '.'
          : 'This booking was made before prices were stored with bookings; the total uses the car\'s daily price.'; ?>
          No payment was taken online. <?php echo (int)$b->Status === 1 ? 'Payment is arranged directly with Rento.' : ((int)$b->Status === 2 ? 'This booking was cancelled; nothing is due.' : 'Nothing is due until we confirm the booking.'); ?></p>
        <?php if (trim((string)$b->message) !== '') { ?><p><strong>Your message:</strong> <?php echo htmlentities($b->message); ?></p><?php } ?>
      </footer>
    </article>
  </main>
  <?php include('includes/scripts.php'); ?>
</body>

</html>
