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
// Festival and travel-day surcharges, only for packages that opt in (admin -> Edit package).
$surcharges = (int)($package->FestivalPricing ?? 0) ? package_surcharge_windows($dbh) : [];
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
  $pageDescription = trim((string)($package->SeoDescription ?? '')) ?: trim($package->Summary ?: $package->Title) . ' From ' . format_price($package->StartingPrice) . '. Send Rento a booking request.';
  // Search and sharing: TouristTrip with the itinerary and the starting price.
  $pageCanonical = package_url($package->id, $package->Title);
  if (package_cover($package) !== PACKAGE_PLACEHOLDER) $pageImage = package_cover($package);
  $seoTrip = ['@type' => 'TouristTrip', 'name' => $package->Title, 'url' => seo_url($pageCanonical),
    'description' => trim((string)($package->Summary ?: $package->Description)), 'provider' => ['@id' => seo_url() . '#business'],
    'offers' => ['@type' => 'Offer', 'price' => (int)$package->StartingPrice, 'priceCurrency' => 'NPR', 'url' => seo_url($pageCanonical),
      'availability' => 'https://schema.org/InStock']];
  if (!empty($package->CategoryName)) $seoTrip['touristType'] = $package->CategoryName;
  if ($itinerary = package_itinerary($package)) {
    $seoTrip['itinerary'] = ['@type' => 'ItemList', 'numberOfItems' => count($itinerary), 'itemListElement' => array_map(fn($d, $i) => ['@type' => 'ListItem',
      'position' => $i + 1, 'item' => ['@type' => 'TouristAttraction', 'name' => $d['title'] ?: 'Day ' . $d['day'], 'description' => $d['text']]], $itinerary, array_keys($itinerary))];
  }
  if (isset($pageImage)) $seoTrip['image'] = seo_url($pageImage);
  $pageJsonLd = [$seoTrip, seo_breadcrumbs([[content_raw('seo_packages_title'), 'packages'], [$package->Title, $pageCanonical]])];
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

            <?php if ($surcharges) { ?>
              <h2 class="r-section-title r-package-h">Festival dates</h2>
              <p class="r-hint">Demand is highest around festivals, so these dates cost more. Prices below are starting prices for each vehicle.</p>
              <div class="r-table-wrap">
                <table class="r-festival-prices">
                  <thead><tr><th scope="col">Dates</th><th scope="col">Why</th><?php foreach ($prices as $type => $price) { ?><th scope="col" class="r-num"><?php echo htmlentities($type); ?></th><?php } ?></tr></thead>
                  <tbody>
                    <?php foreach (array_slice($surcharges, 0, 10) as $w) { ?>
                      <tr>
                        <td class="text-nowrap"><?php echo date('j M', strtotime($w['from'])) . ($w['to'] !== $w['from'] ? ' – ' . date('j M Y', strtotime($w['to'])) : date(' Y', strtotime($w['from']))); ?></td>
                        <td><?php echo htmlentities($w['label']); ?><?php echo $w['provisional'] ? ' <span class="r-hint">(dates to be confirmed)</span>' : ''; ?> <span class="r-hint">+<?php echo rtrim(rtrim(number_format($w['pct'], 1), '0'), '.'); ?>%</span></td>
                        <?php foreach ($prices as $type => $price) { ?><td class="r-num"><?php echo format_price(package_price_with($price, $w['pct'])); ?></td><?php } ?>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
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
              <?php if ($surcharges) { ?>
                <p class="r-hint">Festival dates cost more: see "Festival dates".</p>
              <?php } ?>
              <?php echo enquiry_form($dbh, $packageUrl . '#request', $prices); ?>
              <?php if ($surcharges) { ?>
                <p class="r-book__hint" data-festival-note hidden><i class="fa fa-calendar-day" aria-hidden="true"></i><span></span></p>
                <script>
                  // Festival price for the chosen travel date (same windows as the table).
                  (function () {
                    var windows = <?php echo json_encode(array_map(fn($w) => [$w['from'], $w['to'], $w['label'], $w['pct']], $surcharges), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
                    var prices = <?php echo json_encode(array_map(fn($p) => (int)$p, $prices), JSON_HEX_TAG); ?>;
                    var step = <?php echo max(1, (int)setting('pricing_round_to')); ?>;
                    var date = document.getElementById('enq-date'), type = document.getElementById('enq-vehicle');
                    var note = document.querySelector('[data-festival-note]');
                    function update() {
                      var d = date.value, best = null;
                      windows.forEach(function (w) { if (d >= w[0] && d <= w[1] && (!best || w[3] > best[3])) best = w; });
                      if (!best || !type || !prices[type.value]) { note.hidden = true; return; }
                      var price = Math.round(prices[type.value] * (1 + best[3] / 100) / step) * step;
                      note.querySelector('span').textContent = best[2] + ' (+' + best[3] + '%): ' + type.value + ' from Rs ' + price.toLocaleString('en-US') + ' on this date.';
                      note.hidden = false;
                    }
                    date.addEventListener('change', update);
                    if (type) type.addEventListener('change', update);
                    update();
                  })();
                </script>
              <?php } ?>
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
