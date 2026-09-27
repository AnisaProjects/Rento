<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your account.');
  flash_open_modal('loginform');
  redirect_to('./');
}

$sql = "SELECT tblvehicles.Vimage1 AS Vimage1, tblvehicles.VehiclesTitle, tblvehicles.id AS vid, tblbrands.BrandName, tblbooking.FromDate,
    tblbooking.ToDate, tblbooking.message, tblbooking.Status, tblbooking.PostingDate, tblvehicles.PricePerDay, tblbooking.quoted_price, tblbooking.pricing_details,
    " . rental_days_sql('tblbooking.FromDate', 'tblbooking.ToDate') . " AS totaldays, tblbooking.BookingNumber
  FROM tblbooking JOIN tblvehicles ON tblbooking.VehicleId = tblvehicles.id JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand
  WHERE tblbooking.userEmail = :useremail ORDER BY tblbooking.id DESC";
$query = $dbh->prepare($sql);
$query->bindParam(':useremail', $_SESSION['login'], PDO::PARAM_STR);
$query->execute();
$bookings = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'My Bookings';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo account_start($dbh, 'my-booking', 'My bookings', 'Your booking requests and their status.'); ?>
    <?php if ($bookings) {
      foreach ($bookings as $booking) {
        // Stored price for new bookings, days x base price for older ones (booking_total()).
        $amount = booking_total($booking);
        $days = $amount['rental_days'];
        $carName = vehicle_display_name($booking->BrandName, $booking->VehiclesTitle);
        $url = vehicle_url($booking->vid, $booking->BrandName, $booking->VehiclesTitle); ?>
        <article class="r-booking">
          <a class="r-booking__media" href="<?php echo htmlentities($url); ?>" tabindex="-1" aria-hidden="true">
            <?php echo vehicle_image($booking->Vimage1, '', ['sizes' => '160px']); ?>
          </a>
          <div>
            <div class="r-booking__head">
              <div>
                <h2 class="r-booking__title"><a href="<?php echo htmlentities($url); ?>"><?php echo htmlentities($carName); ?></a></h2>
                <span class="r-booking__number">Booking #<?php echo htmlentities($booking->BookingNumber); ?> &middot; requested <?php echo htmlentities(date('j M Y', strtotime($booking->PostingDate))); ?></span>
              </div>
              <?php echo booking_status_badge($booking->Status); ?>
            </div>
            <dl class="r-booking__facts">
              <div><dt>Pickup</dt><dd><?php echo htmlentities(date('D, j M Y', strtotime($booking->FromDate))); ?></dd></div>
              <div><dt>Return</dt><dd><?php echo htmlentities(date('D, j M Y', strtotime($booking->ToDate))); ?></dd></div>
              <div><dt><?php echo $days; ?> <?php echo $days === 1 ? 'day' : 'days'; ?><?php echo $amount['source'] === 'base' ? ' &times; ' . format_price($booking->PricePerDay) : ''; ?></dt><dd><?php echo format_price($amount['total']); ?></dd></div>
            </dl>
            <p class="r-booking__links"><a href="invoice?booking=<?php echo urlencode($booking->BookingNumber); ?>"><i class="fa fa-file-invoice" aria-hidden="true"></i><?php echo (int)$booking->Status === 1 ? 'Invoice' : 'Booking summary'; ?></a></p>
            <?php if (trim((string)$booking->message) !== '') { ?>
              <p class="r-booking__message"><strong>Your message:</strong> <?php echo htmlentities($booking->message); ?></p>
            <?php } ?>
          </div>
        </article>
      <?php }
    } else {
      echo empty_state('fa-calendar-check', 'No bookings yet', 'When you request a car, it shows up here with its status.', 'Browse cars', 'car-listing', 'h2');
    } ?>
    <?php echo account_end(); ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
