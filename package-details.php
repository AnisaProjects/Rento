<?php
// One package: itinerary, prices per vehicle type, inclusions/exclusions, suitable cars and
// a "Request booking" form (stored as a package enquiry; no payment). Clean URL:
// package/<title>-<id> (see .htaccess); inactive packages are "not found".
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

$pid = intval($_GET['pid'] ?? 0);
$query = $dbh->prepare("SELECT p.*, c.CategoryName, c.Slug AS CategorySlug, c.Icon, c.IsActive AS CategoryActive FROM tblpackages p
  LEFT JOIN tblpackagecategories c ON c.id = p.CategoryId WHERE p.id = :id");
$query->bindParam(':id', $pid, PDO::PARAM_INT);
$query->execute();
$package = $query->fetch(PDO::FETCH_OBJ);
if (!$package || !(int)$package->IsActive || ($package->CategoryId && !(int)$package->CategoryActive)) {
  require __DIR__ . '/404.php';
  exit;
}

$packageUrl = package_url($package->id, $package->Title);
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
  $canonicalPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . $packageUrl;
  if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== $canonicalPath) {
    header('Location: ' . $canonicalPath, true, 301);
    exit;
  }
}

$prices = package_prices($package);
$itinerary = package_itinerary($package);
$inclusions = package_lines($package->Inclusions);
$exclusions = package_lines($package->Exclusions);

// ---- Request booking (enquiry) ----
const ENQUIRY_MAX_PASSENGERS = 60;
if (isset($_POST['requestbooking'])) {
  $back = $packageUrl . '#request';
  $in = [
    'fullname' => trim((string)($_POST['fullname'] ?? '')),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'traveldate' => (string)($_POST['traveldate'] ?? ''),
    'pickuplocation' => trim((string)($_POST['pickuplocation'] ?? '')),
    'passengers' => (int)($_POST['passengers'] ?? 0),
    'vehicletype' => (string)($_POST['vehicletype'] ?? ''),
    'notes' => trim((string)($_POST['notes'] ?? '')),
  ];
  $_SESSION['enquiry_form'] = $in;
  if (rate_limit_exceeded($dbh, 'package_enquiry', $in['email'] ?: null)) {
    flash_set('error', RATE_LIMIT_MESSAGE);
    redirect_to($back);
  }
  $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['traveldate']) && checkdate((int)substr($in['traveldate'], 5, 2), (int)substr($in['traveldate'], 8, 2), (int)substr($in['traveldate'], 0, 4));
  $error = match (true) {
    $in['fullname'] === '' || mb_strlen($in['fullname']) > 120 => 'Please enter your name.',
    !preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $in['phone']) => 'Please enter a phone number we can call, e.g. 98XXXXXXXX.',
    $in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address, or leave it empty.',
    !$validDate || $in['traveldate'] < date('Y-m-d') => 'Please choose a travel date from today onwards.',
    $in['traveldate'] > date('Y-m-d', strtotime('+2 years')) => 'Please choose a travel date within the next two years.',
    $in['pickuplocation'] === '' || mb_strlen($in['pickuplocation']) > 200 => 'Please tell us where to pick you up.',
    $in['passengers'] < 1 || $in['passengers'] > ENQUIRY_MAX_PASSENGERS => 'Please enter the number of passengers (1 to ' . ENQUIRY_MAX_PASSENGERS . ').',
    $prices && !isset($prices[$in['vehicletype']]) => 'Please choose a vehicle type.',
    mb_strlen($in['notes']) > 1000 => 'Please keep the notes under 1,000 characters.',
    default => '',
  };
  // Every request counts toward the limit, so the form can't be used to flood the inbox.
  rate_limit_record($dbh, 'package_enquiry', $in['email'] ?: null);
  if ($error !== '') {
    flash_set('error', $error);
    redirect_to($back);
  }
  $insert = $dbh->prepare("INSERT INTO tblpackageenquiries (PackageId, PackageTitle, FullName, Phone, Email, TravelDate, PickupLocation, Passengers, VehicleType, Notes)
    VALUES (:pid, :title, :name, :phone, :email, :date, :pickup, :passengers, :vehicle, :notes)");
  $insert->execute([
    ':pid' => $package->id, ':title' => $package->Title, ':name' => $in['fullname'], ':phone' => $in['phone'],
    ':email' => $in['email'] !== '' ? $in['email'] : null, ':date' => $in['traveldate'], ':pickup' => $in['pickuplocation'],
    ':passengers' => $in['passengers'], ':vehicle' => $in['vehicletype'] !== '' ? $in['vehicletype'] : null,
    ':notes' => $in['notes'] !== '' ? $in['notes'] : null,
  ]);
  unset($_SESSION['enquiry_form']);
  flash_set('success', 'Thank you! We received your request for ' . $package->Title . ' and will call you to confirm the price and details.');
  redirect_to($back);
}

// Values for the form: what was typed after an error, or the logged-in customer's details.
$form = $_SESSION['enquiry_form'] ?? [];
unset($_SESSION['enquiry_form']);
if (!$form && !empty($_SESSION['login'])) {
  $me = $dbh->prepare("SELECT FullName, EmailId, ContactNo FROM tblusers WHERE EmailId = :email");
  $me->execute([':email' => $_SESSION['login']]);
  if ($row = $me->fetch(PDO::FETCH_OBJ)) {
    $form = ['fullname' => $row->FullName, 'email' => $row->EmailId, 'phone' => $row->ContactNo];
  }
}
$f = fn(string $key) => htmlentities((string)($form[$key] ?? ''));

$gallery = $dbh->prepare("SELECT ImagePath FROM tblpackageimages WHERE PackageId = :id ORDER BY SortOrder, id");
$gallery->execute([':id' => $package->id]);
$gallery = array_values(array_filter($gallery->fetchAll(PDO::FETCH_COLUMN), fn($path) => is_file(__DIR__ . '/' . $path)));

// Active cars of the vehicle types this package is offered in.
$suitableCars = [];
if ($prices) {
  $types = array_keys($prices);
  $placeholders = implode(',', array_fill(0, count($types), '?'));
  $cars = $dbh->prepare("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblvehicles.FuelType, tblvehicles.ModelYear,
      tblvehicles.SeatingCapacity, tblvehicles.Vimage1, tblvehicles.CarType, tblbrands.BrandName
    FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand
    WHERE tblvehicles.IsActive = 1 AND tblvehicles.CarType IN ($placeholders) ORDER BY tblvehicles.PricePerDay LIMIT 3");
  $cars->execute($types);
  $suitableCars = $cars->fetchAll(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $package->Title;
  $pageDescription = trim($package->Summary ?: $package->Title) . ' From ' . format_price($package->StartingPrice) . '. Send Rento a booking request.';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php
    $packageFacts = '<ul class="r-package-facts">'
      . ($package->CategoryName ? '<li><i class="fa ' . htmlentities($package->Icon) . '" aria-hidden="true"></i>' . htmlentities($package->CategoryName) . '</li>' : '')
      . ($package->DurationLabel ? '<li><i class="fa fa-clock" aria-hidden="true"></i>' . htmlentities($package->DurationLabel) . '</li>' : '')
      . '<li><i class="fa fa-tag" aria-hidden="true"></i>From ' . format_price($package->StartingPrice) . '</li></ul>';
    // The placeholder photo is only used on cards, not behind the page title.
    $heroImage = package_cover($package) === PACKAGE_PLACEHOLDER ? '' : package_cover($package);
    echo page_hero($package->Title, (string)$package->Summary, [[content_raw('packages_title'), 'packages']], $heroImage, $packageFacts);
    ?>

    <section class="r-section">
      <div class="container">
        <div class="r-package-layout">
          <div class="r-package-main">
            <?php if ($package->Description) { ?>
              <div class="r-prose r-package-intro"><?php echo nl2br(htmlentities($package->Description)); ?></div>
            <?php } ?>

            <?php if ($package->RouteStops) { ?>
              <p class="r-package-route"><i class="fa fa-route" aria-hidden="true"></i><?php echo htmlentities($package->RouteStops); ?></p>
            <?php } ?>

            <?php if ($itinerary) { ?>
              <h2 class="r-section-title r-package-h">Itinerary</h2>
              <ol class="r-itinerary">
                <?php foreach ($itinerary as $day) { ?>
                  <li class="r-reveal">
                    <span class="r-itinerary__day">Day <?php echo max(1, $day['day']); ?></span>
                    <div>
                      <?php if ($day['title'] !== '') { ?><h3><?php echo htmlentities($day['title']); ?></h3><?php } ?>
                      <?php if ($day['text'] !== '') { ?><p><?php echo htmlentities($day['text']); ?></p><?php } ?>
                    </div>
                  </li>
                <?php } ?>
              </ol>
            <?php } ?>

            <?php if ($inclusions || $exclusions) { ?>
              <div class="r-package-lists">
                <?php if ($inclusions) { ?>
                  <div class="r-panel">
                    <h2 class="r-panel__title">What's included</h2>
                    <ul class="r-checklist r-checklist--yes">
                      <?php foreach ($inclusions as $line) { ?><li><i class="fa fa-circle-check" aria-hidden="true"></i><?php echo htmlentities($line); ?></li><?php } ?>
                    </ul>
                  </div>
                <?php } ?>
                <?php if ($exclusions) { ?>
                  <div class="r-panel">
                    <h2 class="r-panel__title">Not included</h2>
                    <ul class="r-checklist r-checklist--no">
                      <?php foreach ($exclusions as $line) { ?><li><i class="fa fa-circle-xmark" aria-hidden="true"></i><?php echo htmlentities($line); ?></li><?php } ?>
                    </ul>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>

            <?php if ($gallery) { ?>
              <h2 class="r-section-title r-package-h">Photos</h2>
              <ul class="r-gallery-grid">
                <?php foreach ($gallery as $path) { ?>
                  <li class="r-reveal"><?php echo site_picture($path, $package->Title, ['sizes' => '(min-width: 992px) 380px, 50vw']); ?></li>
                <?php } ?>
              </ul>
            <?php } ?>
          </div>

          <aside class="r-package-aside" id="request">
            <div class="r-book">
              <h2 class="r-book__title">Request booking</h2>
              <?php if ($prices) { ?>
                <ul class="r-package-prices" aria-label="Starting prices per vehicle">
                  <?php foreach ($prices as $type => $price) { ?>
                    <li><span><?php echo htmlentities($type); ?></span><strong><?php echo format_price($price); ?></strong></li>
                  <?php } ?>
                </ul>
              <?php } ?>
              <p class="r-hint">Send your request and we call you to confirm the price, car and pickup time. No payment now.</p>
              <?php echo flash_render(); ?>
              <form method="post" action="<?php echo htmlentities($packageUrl); ?>#request">
                <?php echo csrf_field(); ?>
                <div class="r-field">
                  <label class="r-label" for="enq-name">Full name <span class="r-required">*</span></label>
                  <input class="r-input" id="enq-name" name="fullname" autocomplete="name" maxlength="120" required value="<?php echo $f('fullname'); ?>">
                </div>
                <div class="r-form-row">
                  <div class="r-field">
                    <label class="r-label" for="enq-phone">Phone <span class="r-required">*</span></label>
                    <input class="r-input" id="enq-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="<?php echo $f('phone'); ?>" placeholder="98XXXXXXXX">
                  </div>
                  <div class="r-field">
                    <label class="r-label" for="enq-email">Email</label>
                    <input class="r-input" id="enq-email" name="email" type="email" autocomplete="email" maxlength="120" value="<?php echo $f('email'); ?>">
                  </div>
                </div>
                <div class="r-form-row">
                  <div class="r-field">
                    <label class="r-label" for="enq-date">Travel date <span class="r-required">*</span></label>
                    <input class="r-input" id="enq-date" name="traveldate" type="date" min="<?php echo date('Y-m-d'); ?>" required value="<?php echo $f('traveldate'); ?>">
                  </div>
                  <div class="r-field">
                    <label class="r-label" for="enq-pax">Passengers <span class="r-required">*</span></label>
                    <input class="r-input" id="enq-pax" name="passengers" type="number" min="1" max="<?php echo ENQUIRY_MAX_PASSENGERS; ?>" inputmode="numeric" required value="<?php echo $f('passengers') ?: '2'; ?>">
                  </div>
                </div>
                <div class="r-field">
                  <label class="r-label" for="enq-pickup">Pickup location <span class="r-required">*</span></label>
                  <input class="r-input" id="enq-pickup" name="pickuplocation" maxlength="200" required value="<?php echo $f('pickuplocation'); ?>" placeholder="Hotel, area or airport">
                </div>
                <?php if ($prices) { ?>
                  <div class="r-field">
                    <label class="r-label" for="enq-vehicle">Vehicle type <span class="r-required">*</span></label>
                    <select class="r-select" id="enq-vehicle" name="vehicletype" required>
                      <?php foreach ($prices as $type => $price) { ?>
                        <option value="<?php echo htmlentities($type); ?>"<?php echo ($form['vehicletype'] ?? '') === $type ? ' selected' : ''; ?>><?php echo htmlentities($type); ?> · from <?php echo format_price($price); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                <?php } ?>
                <div class="r-field">
                  <label class="r-label" for="enq-notes">Notes</label>
                  <textarea class="r-textarea" id="enq-notes" name="notes" rows="3" maxlength="1000" placeholder="Flight number, stops you'd like, luggage…"><?php echo $f('notes'); ?></textarea>
                </div>
                <button class="r-btn r-btn--block r-btn--lg" type="submit" name="requestbooking" value="1"><i class="fa fa-paper-plane" aria-hidden="true"></i>Send request</button>
              </form>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <?php if ($suitableCars) { ?>
      <section class="r-section r-section--muted">
        <div class="container">
          <div class="r-section-head r-section-head--start">
            <span class="r-eyebrow">Suitable cars</span>
            <h2 class="r-section-title">Cars of this type in our fleet</h2>
            <p class="r-section-lead">Packages come with a driver. These are examples of the cars we use; you can also rent them self-drive.</p>
          </div>
          <div class="r-grid r-grid--cards">
            <?php foreach ($suitableCars as $car) echo car_card($car, ['heading' => 'h3']); ?>
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

</body>

</html>
