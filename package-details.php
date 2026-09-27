<?php
// One package: itinerary, prices per vehicle type, inclusions/exclusions, suitable cars and
// a "Request booking" form (stored as a package enquiry; no payment). Clean URL:
// package/<title>-<id> (see .htaccess); inactive packages are "not found".
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
require_once __DIR__ . '/includes/enquiry-form.php';

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

// ---- Request booking (enquiry): includes/enquiry-form.php ----
enquiry_handle($dbh, ['package' => $package, 'types' => $prices], $packageUrl . '#request');

$gallery = $dbh->prepare("SELECT ImagePath FROM tblpackageimages WHERE PackageId = :id ORDER BY SortOrder, id");
$gallery->execute([':id' => $package->id]);
$gallery = array_values(array_filter($gallery->fetchAll(PDO::FETCH_COLUMN), fn($path) => is_file(__DIR__ . '/' . $path)));

// Active cars of the vehicle types this package is offered in.
$suitableCars = [];
if ($prices) {
  $types = array_keys($prices);
  $placeholders = implode(',', array_fill(0, count($types), '?'));
  $cars = $dbh->prepare("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblvehicles.FuelType, tblvehicles.ModelYear,
      tblvehicles.SeatingCapacity, tblvehicles.Vimage1, tblvehicles.CarType, tblvehicles.DriverOnly, tblbrands.BrandName
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
              <?php echo enquiry_form($dbh, $packageUrl . '#request', $prices); ?>
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
