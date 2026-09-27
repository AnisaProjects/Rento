<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
require_once __DIR__ . '/includes/enquiry-form.php';

$vhid = intval($_GET['vhid'] ?? 0);
$vehicleQuery = $dbh->prepare("SELECT tblvehicles.*, tblbrands.BrandName, tblbrands.id AS bid FROM tblvehicles
  JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand WHERE tblvehicles.id = :vhid");
$vehicleQuery->bindParam(':vhid', $vhid, PDO::PARAM_INT);
$vehicleQuery->execute();
$car = $vehicleQuery->fetch(PDO::FETCH_OBJ);

// Unknown, missing or inactive vehicle: show the "page not found" page (it can't be booked).
if (!$car || !(int)$car->IsActive) {
  require __DIR__ . '/404.php';
  exit;
}

$carName = vehicle_display_name($car->BrandName, $car->VehiclesTitle);
$carUrl = vehicle_url($car->id, $car->BrandName, $car->VehiclesTitle);

// Every car has one address, car/<brand-model>-<id>. Old links (vehical-details.php?vhid=)
// and outdated names redirect to it permanently.
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
  $canonicalPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . $carUrl;
  if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== $canonicalPath) {
    header('Location: ' . $canonicalPath, true, 301);
    exit;
  }
}

// Returns true for a real calendar date in Y-m-d format.
function is_booking_date(string $value): bool
{
  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date !== false && $date->format('Y-m-d') === $value;
}

// Driver-only cars (admin -> Edit vehicle) are rented with a driver: the page shows an
// enquiry form instead of self-drive booking, and a direct booking POST is refused.
$driverOnly = (int)($car->DriverOnly ?? 0) === 1;
if ($driverOnly) {
  enquiry_handle($dbh, ['vehicle' => $car, 'title' => $carName . ' (with driver)', 'maxPassengers' => max(1, (int)$car->SeatingCapacity)], $carUrl . '#book');
  if (isset($_POST['submit'])) {
    flash_set('error', 'This car is rented with a driver. Please send a request instead.');
    redirect_to($carUrl . '#book');
  }
}

// Booking request. Result is shown as a toast after redirecting (post/redirect/get).
if (isset($_POST['submit'])) {
  $fromdate = (string)($_POST['fromdate'] ?? '');
  $todate = (string)($_POST['todate'] ?? '');
  $message = trim((string)($_POST['message'] ?? ''));
  $useremail = $_SESSION['login'] ?? '';
  $status = 0;
  $bookingno = mt_rand(100000000, 999999999);
  $backToForm = $carUrl . '#book';

  // Server-side checks: the booking form alone can be bypassed with a direct POST.
  if ($useremail === '') {
    flash_set('error', 'Please log in to book this car.');
    flash_open_modal('loginform');
    redirect_to($carUrl);
  }
  if (!is_booking_date($fromdate) || !is_booking_date($todate)) {
    flash_set('error', 'Please choose valid pickup and return dates.');
    redirect_to($backToForm);
  }
  if ($fromdate < date('Y-m-d')) {
    flash_set('error', 'The pickup date cannot be in the past.');
    redirect_to($backToForm);
  }
  if ($todate < $fromdate) {
    flash_set('error', 'The return date must be on or after the pickup date.');
    redirect_to($backToForm);
  }

  // Overlapping bookings for this car, ignoring cancelled ones (Status 2).
  $ret = "SELECT id FROM tblbooking WHERE (:fromdate BETWEEN date(FromDate) AND date(ToDate) OR :todate BETWEEN date(FromDate) AND date(ToDate)
    OR date(FromDate) BETWEEN :fromdate AND :todate) AND VehicleId=:vhid AND (Status IS NULL OR Status <> 2)";
  $query1 = $dbh->prepare($ret);
  $query1->bindParam(':vhid', $vhid, PDO::PARAM_INT);
  $query1->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
  $query1->bindParam(':todate', $todate, PDO::PARAM_STR);
  $query1->execute();
  if ($query1->fetch()) {
    flash_set('error', 'Sorry, this car is already booked for some of those days. Please try other dates.');
    redirect_to($backToForm);
  }

  // The price is worked out here on the server (never taken from the browser) and stored.
  $quote = public_quote($dbh, $car, $fromdate, $todate);
  if (!$quote['ok']) {
    flash_set('error', 'Please choose valid pickup and return dates.');
    redirect_to($backToForm);
  }
  [$quotedPrice, $pricingDetails] = booking_price_record($quote);
  $sql = "INSERT INTO  tblbooking(BookingNumber,userEmail,VehicleId,FromDate,ToDate,message,Status,quoted_price,pricing_details,priced_at)
    VALUES(:bookingno,:useremail,:vhid,:fromdate,:todate,:message,:status,:quoted,:details,NOW())";
  $query = $dbh->prepare($sql);
  $query->bindParam(':bookingno', $bookingno, PDO::PARAM_STR);
  $query->bindParam(':useremail', $useremail, PDO::PARAM_STR);
  $query->bindParam(':vhid', $vhid, PDO::PARAM_STR);
  $query->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
  $query->bindParam(':todate', $todate, PDO::PARAM_STR);
  $query->bindParam(':message', $message, PDO::PARAM_STR);
  $query->bindParam(':status', $status, PDO::PARAM_STR);
  $query->bindParam(':quoted', $quotedPrice, PDO::PARAM_INT);
  $query->bindParam(':details', $pricingDetails, PDO::PARAM_STR);
  $query->execute();
  if ($dbh->lastInsertId()) {
    forecast_request_recompute($dbh, 'new booking #' . $bookingno);
    flash_set('success', 'Booking request sent (#' . $bookingno . ', ' . format_price($quotedPrice) . ' for ' . $quote['rental_days'] . ($quote['rental_days'] === 1 ? ' day' : ' days') . '). We will confirm it shortly.');
    redirect_to('my-booking');
  }
  flash_set('error', 'Something went wrong. Please try again.');
  redirect_to($backToForm);
}

// Price for the default dates (tomorrow to the day after). With PRICING_ENABLED this is the
// smart price from the forecast; the booking card then asks api/price-quote.php for the
// chosen dates. Without it, the base price.
$defaultFrom = date('Y-m-d', strtotime('+1 day'));
$defaultTo = date('Y-m-d', strtotime('+2 day'));
$defaultQuote = public_quote($dbh, $car, $defaultFrom, $defaultTo);
$displayPrice = $defaultQuote['ok'] ? (int)$defaultQuote['days'][0]['price'] : (int)$car->PricePerDay; // tomorrow

$photos = array_values(array_filter([$car->Vimage1, $car->Vimage2, $car->Vimage3, $car->Vimage4, $car->Vimage5]));

$featureLabels = [
  'AirConditioner' => ['fa-snowflake', 'Air conditioning'],
  'PowerSteering' => ['fa-dharmachakra', 'Power steering'],
  'PowerWindows' => ['fa-window-maximize', 'Power windows'],
  'PowerDoorLocks' => ['fa-lock', 'Power door locks'],
  'CentralLocking' => ['fa-key', 'Central locking'],
  'AntiLockBrakingSystem' => ['fa-circle-stop', 'ABS brakes'],
  'BrakeAssist' => ['fa-gauge-high', 'Brake assist'],
  'DriverAirbag' => ['fa-user-shield', 'Driver airbag'],
  'PassengerAirbag' => ['fa-shield-halved', 'Passenger airbag'],
  'CrashSensor' => ['fa-car-burst', 'Crash sensor'],
  'CDPlayer' => ['fa-music', 'Music system'],
  'LeatherSeats' => ['fa-couch', 'Leather seats'],
];
$features = [];
foreach ($featureLabels as $column => $label) {
  if (!empty($car->$column)) {
    $features[] = $label;
  }
}

// Similar cars: same brand or same type, never the car being viewed.
$similarQuery = $dbh->prepare("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblvehicles.FuelType,
    tblvehicles.ModelYear, tblvehicles.SeatingCapacity, tblvehicles.Vimage1, tblvehicles.CarType, tblvehicles.DriverOnly, tblbrands.BrandName
  FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand
  WHERE tblvehicles.IsActive = 1 AND tblvehicles.id <> :id AND (tblvehicles.VehiclesBrand = :bid OR tblvehicles.CarType = :type)
  ORDER BY (tblvehicles.CarType = :type) DESC, ABS(tblvehicles.PricePerDay - :price) LIMIT 3");
$similarQuery->bindValue(':id', (int)$car->id, PDO::PARAM_INT);
$similarQuery->bindValue(':bid', (int)$car->bid, PDO::PARAM_INT);
$similarQuery->bindValue(':type', (string)$car->CarType, PDO::PARAM_STR);
$similarQuery->bindValue(':price', (int)$car->PricePerDay, PDO::PARAM_INT);
$similarQuery->execute();
$similarCars = $similarQuery->fetchAll(PDO::FETCH_OBJ);

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$today = date('Y-m-d');
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $carName;
  $pageDescription = 'Rent the ' . $carName . ' in Nepal from ' . format_price_per_day($car->PricePerDay) . '. See photos, features and availability, and book online.';
  $pageUsesCarousel = true;
  if (PRICING_ENABLED && !$driverOnly) $pageScripts = ['assets/js/price-calendar.js'];
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main" class="r-car">
    <div class="container">
      <nav aria-label="Breadcrumb">
        <ol class="r-breadcrumb r-breadcrumb--light">
          <li><a href="./">Home</a></li>
          <li><a href="car-listing">Cars</a></li>
          <li aria-current="page"><?php echo htmlentities($carName); ?></li>
        </ol>
      </nav>

      <div class="r-car__head">
        <div>
          <div class="r-car-card__brand"><?php echo htmlentities($car->BrandName); ?></div>
          <h1 class="r-car__title"><?php echo htmlentities($car->VehiclesTitle); ?></h1>
          <ul class="r-chips">
            <?php if ($car->CarType) { ?><li><span class="r-chip"><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($car->CarType); ?></span></li><?php } ?>
            <?php if ($car->FuelType) { ?><li><span class="r-chip"><i class="fa fa-gas-pump" aria-hidden="true"></i><?php echo htmlentities($car->FuelType); ?></span></li><?php } ?>
            <?php if ($car->SeatingCapacity) { ?><li><span class="r-chip"><i class="fa fa-user-group" aria-hidden="true"></i><?php echo (int)$car->SeatingCapacity; ?> seats</span></li><?php } ?>
            <?php if ($car->ModelYear) { ?><li><span class="r-chip"><i class="fa fa-calendar" aria-hidden="true"></i><?php echo (int)$car->ModelYear; ?> model</span></li><?php } ?>
            <?php if ($driverOnly) { ?><li><span class="r-chip"><i class="fa fa-id-card" aria-hidden="true"></i>With driver</span></li><?php } ?>
          </ul>
        </div>
        <div class="r-car__price">
          <div class="r-price r-price--lg"><?php echo format_price($displayPrice); ?><small>/day</small></div>
          <?php if (PRICING_ENABLED) { ?>
            <span class="r-car__price-note">Price for tomorrow · changes with your dates</span>
          <?php } ?>
          <a href="#book" class="r-btn r-car__book-jump"><?php echo $driverOnly ? 'Request with driver' : 'Book this car'; ?></a>
        </div>
      </div>

      <div class="r-car__layout">
        <div class="r-car__main">

          <?php if ($photos) { ?>
            <div class="r-gallery">
              <div id="listing_img_slider" class="owl-carousel r-gallery__main">
                <?php foreach ($photos as $i => $photo) { ?>
                  <div><?php echo vehicle_image($photo, $carName . ' – photo ' . ($i + 1), ['sizes' => '(min-width: 1200px) 760px, 100vw', 'lazy' => $i > 0]); ?></div>
                <?php } ?>
              </div>
              <?php if (count($photos) > 1) { ?>
                <div class="r-gallery__thumbs" role="group" aria-label="Photos">
                  <?php foreach ($photos as $i => $photo) { ?>
                    <button type="button" class="r-gallery__thumb <?php echo $i === 0 ? 'is-active' : ''; ?>" data-slide="<?php echo $i; ?>" aria-label="Show photo <?php echo $i + 1; ?>">
                      <?php echo vehicle_image($photo, '', ['sizes' => '120px']); ?>
                    </button>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>

          <section class="r-car__section" aria-labelledby="overview-title">
            <h2 id="overview-title">Overview</h2>
            <div class="r-car__overview"><?php echo nl2br(htmlentities(trim((string)$car->VehiclesOverview))); ?></div>
          </section>

          <?php if ($features) { ?>
            <section class="r-car__section" aria-labelledby="features-title">
              <h2 id="features-title">Features</h2>
              <ul class="r-features">
                <?php foreach ($features as [$icon, $label]) { ?>
                  <li><i class="fa <?php echo $icon; ?>" aria-hidden="true"></i><?php echo $label; ?></li>
                <?php } ?>
              </ul>
            </section>
          <?php } ?>
        </div>

        <aside class="r-car__aside" id="book" aria-labelledby="book-title">
          <div class="r-book">
            <?php if ($driverOnly) { ?>
              <h2 id="book-title" class="r-book__title">Rent with a driver</h2>
              <div class="r-price"><?php echo format_price($displayPrice); ?><small>/day, from</small></div>
              <p class="r-hint">This car is only rented with one of our drivers. Send your trip details and we call you to confirm the price and pickup. No payment now.</p>
              <?php echo flash_render(); ?>
              <?php echo enquiry_form($dbh, $carUrl . '#book', [], max(1, (int)$car->SeatingCapacity), 'Request with driver'); ?>
            <?php } else { ?>
            <h2 id="book-title" class="r-book__title">Book this car</h2>
            <div class="r-price"><?php echo format_price($displayPrice); ?><small>/day</small></div>
            <?php if (PRICING_ENABLED) { ?>
              <div class="r-pcal" data-price-calendar data-car="<?php echo (int)$car->id; ?>" data-api="api/price-calendar.php" data-base="<?php echo (int)$car->PricePerDay; ?>" hidden>
                <div class="r-pcal__head">
                  <button type="button" class="r-pcal__nav" data-pcal-prev aria-label="Previous month"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                  <strong data-pcal-title aria-live="polite"></strong>
                  <button type="button" class="r-pcal__nav" data-pcal-next aria-label="Next month"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="r-pcal__week" aria-hidden="true"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>
                <div class="r-pcal__grid" data-pcal-grid role="group" aria-label="Prices per day<?php echo !empty($_SESSION['login']) ? '. Tap a pickup day, then a return day.' : ''; ?>"></div>
                <ul class="r-pcal__legend" aria-hidden="true">
                  <li><span class="is-cheap"></span>Cheaper</li><li><span class="is-normal"></span>Usual price</li><li><span class="is-high"></span>Higher</li><li><span class="is-booked"></span>Booked</li>
                </ul>
                <p class="r-pcal__detail" data-pcal-detail aria-live="polite">Hover or tap a day to see its price.</p>
              </div>
            <?php } ?>

            <?php if (!empty($_SESSION['login'])) { ?>
              <form method="post" class="r-book__form" data-price="<?php echo (float)$car->PricePerDay; ?>" data-car="<?php echo (int)$car->id; ?>" data-quote-url="api/price-quote.php">
                <?php echo csrf_field(); ?>
                <div class="r-form-row">
                  <div class="r-field">
                    <label class="r-label" for="fromdate">Pickup date</label>
                    <input type="date" id="fromdate" name="fromdate" class="r-input" min="<?php echo $today; ?>" value="<?php echo $tomorrow; ?>" required>
                  </div>
                  <div class="r-field">
                    <label class="r-label" for="todate">Return date</label>
                    <input type="date" id="todate" name="todate" class="r-input" min="<?php echo $today; ?>" value="<?php echo $defaultTo; ?>" required>
                  </div>
                </div>
                <div class="r-field">
                  <label class="r-label" for="message">Message <span class="r-hint">(optional)</span></label>
                  <textarea id="message" name="message" class="r-textarea" rows="3" maxlength="255" placeholder="Pickup time, trip plans, questions..."></textarea>
                </div>
                <dl class="r-book__total" aria-live="polite" data-quote-box>
                  <div><dt data-days><?php echo $defaultQuote['rental_days'] ?? 2; ?> days</dt><dd data-total><?php echo format_price($defaultQuote['total'] ?? $displayPrice * 2); ?></dd></div>
                </dl>
                <?php $defaultReasons = $defaultQuote['ok'] ? pricing_reason_lines($defaultQuote) : []; ?>
                <ul class="r-book__reasons" data-reasons<?php echo $defaultReasons ? '' : ' hidden'; ?>>
                  <?php foreach ($defaultReasons as $line) { ?><li><?php echo htmlentities($line); ?></li><?php } ?>
                </ul>
                <details class="r-book__days" data-days-box hidden>
                  <summary>Price per day</summary>
                  <ul data-day-list></ul>
                </details>
                <p class="r-book__hint" data-pcal-hint hidden><i class="fa fa-piggy-bank" aria-hidden="true"></i><span><span data-pcal-hint-text></span> <button type="button" class="r-link-inline" data-pcal-hint-apply>Use these dates</button></span></p>
                <p class="r-book__unavailable" data-unavailable hidden role="alert"><i class="fa fa-circle-exclamation" aria-hidden="true"></i> This car is already booked on some of these days. Please try other dates.</p>
                <button type="submit" name="submit" value="Book Now" class="r-btn r-btn--block r-btn--lg">Request booking</button>
                <p class="r-hint r-book__note"><i class="fa fa-circle-info" aria-hidden="true"></i> No payment now. We confirm your booking and get in touch.</p>
              </form>
            <?php } else { ?>
              <p class="r-book__guest">Log in or create a free account to pick dates and send a booking request.</p>
              <a href="#loginform" class="r-btn r-btn--block r-btn--lg" data-bs-toggle="modal">Log in to book</a>
              <p class="r-hint r-book__note">New here? <a href="#signupform" data-bs-toggle="modal">Create an account</a></p>
            <?php } ?>
            <?php } ?>
          </div>
        </aside>
      </div>
    </div>

    <?php if ($similarCars) { ?>
      <section class="r-section r-section--muted">
        <div class="container">
          <div class="r-section-head r-section-head--start">
            <h2 class="r-section-title">Similar cars</h2>
          </div>
          <div class="r-grid r-grid--cards">
            <?php foreach ($similarCars as $similar) {
              echo car_card($similar);
            } ?>
          </div>
        </div>
      </section>
    <?php } ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>
  <script>
    $(function () {
      // Photo gallery with thumbnails.
      var $slider = $('#listing_img_slider');
      $slider.owlCarousel({
        items: 1,
        loop: false,
        nav: true,
        dots: false,
        smartSpeed: 400,
        navText: ['<i class="fa fa-angle-left" aria-hidden="true"></i><span class="visually-hidden">Previous photo</span>',
          '<i class="fa fa-angle-right" aria-hidden="true"></i><span class="visually-hidden">Next photo</span>']
      });
      // Owl marks its arrow buttons role="presentation", hiding them from screen readers.
      $slider.find('.owl-nav button').removeAttr('role');
      $slider.on('changed.owl.carousel', function (event) {
        $('.r-gallery__thumb').removeClass('is-active').eq(event.item.index).addClass('is-active');
      });
      $('.r-gallery__thumb').on('click', function () {
        $slider.trigger('to.owl.carousel', [Number(this.dataset.slide), 300]);
      });

      // Live booking total from api/price-quote.php (smart prices when PRICING_ENABLED; the
      // server recalculates the price again when the booking is saved). Rental days are
      // inclusive: 1st to 3rd = 3 days, same day = 1. Falls back to days x base price offline.
      var form = document.querySelector('.r-book__form');
      if (!form) return;
      var basePrice = Number(form.dataset.price);
      var from = document.getElementById('fromdate');
      var to = document.getElementById('todate');
      var submit = form.querySelector('[type="submit"]');
      var box = form.querySelector('[data-quote-box]');
      var reasonsList = form.querySelector('[data-reasons]');
      var daysBox = form.querySelector('[data-days-box]');
      var dayList = form.querySelector('[data-day-list]');
      var unavailable = form.querySelector('[data-unavailable]');
      var fmt = function (n) { return 'Rs ' + Math.round(n).toLocaleString('en-US'); };
      var pct = function (p) { return (p > 0 ? '+' : '') + (Math.round(p * 10) / 10) + '%'; };
      var request = 0;
      function showDays(n) { form.querySelector('[data-days]').textContent = n + (n === 1 ? ' day' : ' days'); }
      function fallback() {
        var days = from.value && to.value ? Math.max(1, Math.round((new Date(to.value) - new Date(from.value)) / 86400000) + 1) : 1;
        showDays(days);
        form.querySelector('[data-total]').textContent = fmt(days * basePrice);
      }
      function update() {
        if (from.value) to.min = from.value;
        if (from.value && to.value && to.value < from.value) to.value = from.value;
        if (!from.value || !to.value) return;
        var id = ++request;
        box.setAttribute('aria-busy', 'true');
        fetch(form.dataset.quoteUrl + '?car=' + form.dataset.car + '&from=' + from.value + '&to=' + to.value, { headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (q) {
            if (id !== request) return;
            box.removeAttribute('aria-busy');
            if (!q.ok) { fallback(); return; }
            showDays(q.rental_days);
            form.querySelector('[data-total]').textContent = fmt(q.total);
            reasonsList.innerHTML = '';
            q.reason_text.forEach(function (line) { var li = document.createElement('li'); li.textContent = line; reasonsList.appendChild(li); });
            reasonsList.hidden = q.reason_text.length === 0;
            dayList.innerHTML = '';
            q.per_day.forEach(function (d) {
              var li = document.createElement('li');
              var date = new Date(d.date + 'T00:00:00');
              li.innerHTML = '<span></span><strong></strong>';
              li.firstChild.textContent = date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) + (d.change_pct ? ' (' + pct(d.change_pct) + ')' : '');
              li.lastChild.textContent = fmt(d.price);
              dayList.appendChild(li);
            });
            daysBox.hidden = q.per_day.length < 2 || q.pricing !== 'smart';
            unavailable.hidden = q.available;
            submit.disabled = !q.available;
          })
          .catch(function () { if (id === request) { box.removeAttribute('aria-busy'); fallback(); } });
      }
      from.addEventListener('change', update);
      to.addEventListener('change', update);
      update();
    });
  </script>

</body>

</html>
