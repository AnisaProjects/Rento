<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

// Newest cars first.
$featuredCars = $dbh->query("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblvehicles.FuelType,
    tblvehicles.ModelYear, tblvehicles.SeatingCapacity, tblvehicles.Vimage1, tblvehicles.CarType, tblbrands.BrandName
  FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand
  ORDER BY tblvehicles.id DESC LIMIT 6")->fetchAll(PDO::FETCH_OBJ);

// Car types with how many cars and the lowest daily price, for "Browse by type".
$carTypes = [];
foreach ($dbh->query("SELECT CarType, COUNT(*) AS cars, MIN(PricePerDay) AS fromPrice FROM tblvehicles
    WHERE CarType IS NOT NULL AND CarType <> '' GROUP BY CarType ORDER BY MIN(PricePerDay)")->fetchAll(PDO::FETCH_OBJ) as $row) {
  $carTypes[$row->CarType] = $row;
}
$typeInfo = [
  'Hatchback' => ['fa-car-side', 'Easy to park and light on fuel. Great for city trips.'],
  'Sedan' => ['fa-car', 'Comfortable and quiet for highways and family trips.'],
  'SUV' => ['fa-truck-monster', 'Ground clearance and space for hills and rough roads.'],
];

$brands = $dbh->query("SELECT tblbrands.id, tblbrands.BrandName, COUNT(tblvehicles.id) AS cars FROM tblbrands
  LEFT JOIN tblvehicles ON tblvehicles.VehiclesBrand = tblbrands.id GROUP BY tblbrands.id, tblbrands.BrandName
  HAVING cars > 0 ORDER BY tblbrands.BrandName")->fetchAll(PDO::FETCH_OBJ);

$tid = 1;
$query = $dbh->prepare("SELECT tbltestimonial.Testimonial, tblusers.FullName FROM tbltestimonial
  JOIN tblusers ON tbltestimonial.UserEmail = tblusers.EmailId
  WHERE tbltestimonial.status = :tid ORDER BY tbltestimonial.id DESC LIMIT 6");
$query->bindParam(':tid', $tid, PDO::PARAM_STR);
$query->execute();
$testimonials = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Car Rental in Nepal';
  $pageDescription = 'Rento: rent a verified car anywhere in Nepal. Browse hatchbacks, sedans and SUVs, compare daily prices in rupees and book online.';
  include('includes/head.php');
  ?>
  <link rel="preload" as="image" href="assets/images/banner-image-1.webp" imagesrcset="assets/images/banner-image-1-960.webp 960w, assets/images/banner-image-1.webp 1920w" imagesizes="100vw">
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">

    <!-- Hero with search -->
    <section class="r-hero">
      <picture class="r-hero__bg">
        <source type="image/webp" srcset="assets/images/banner-image-1-960.webp 960w, assets/images/banner-image-1.webp 1920w" sizes="100vw">
        <img src="assets/images/banner-image-1.jpg" alt="" width="1918" height="821">
      </picture>
      <div class="container r-hero__inner">
        <div class="r-hero__copy">
          <span class="r-eyebrow">Car rental across Nepal</span>
          <h1>The right car for every road in Nepal</h1>
          <p>Hatchbacks for the city, SUVs for the hills. Choose a car, pick your dates and book online, with clear
            daily prices in rupees.</p>
        </div>

        <form class="r-hero__search" action="car-listing" method="get" aria-label="Find a car">
          <div class="r-field">
            <label class="r-label" for="hero-type">Car type</label>
            <select id="hero-type" name="type" class="r-select">
              <option value="">Any type</option>
              <?php foreach (array_keys($typeInfo) as $type) { ?>
                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="r-field">
            <label class="r-label" for="hero-brand">Brand</label>
            <select id="hero-brand" name="brand" class="r-select">
              <option value="">Any brand</option>
              <?php foreach ($brands as $brand) { ?>
                <option value="<?php echo (int)$brand->id; ?>"><?php echo htmlentities($brand->BrandName); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="r-field">
            <label class="r-label" for="hero-max">Budget per day</label>
            <select id="hero-max" name="max" class="r-select">
              <option value="">Any budget</option>
              <?php foreach ([1000, 2000, 3000, 5000] as $max) { ?>
                <option value="<?php echo $max; ?>">Up to <?php echo format_price($max); ?></option>
              <?php } ?>
            </select>
          </div>
          <button type="submit" class="r-btn r-btn--lg">
            <i class="fa fa-magnifying-glass" aria-hidden="true"></i>Search cars
          </button>
        </form>

        <ul class="r-hero__trust">
          <li><i class="fa fa-circle-check" aria-hidden="true"></i>Cars checked before every trip</li>
          <li><i class="fa fa-tag" aria-hidden="true"></i>Daily prices in rupees</li>
          <li><i class="fa fa-headset" aria-hidden="true"></i>Local support when you need it</li>
        </ul>
      </div>
    </section>

    <!-- Browse by type -->
    <?php if ($carTypes) { ?>
      <section class="r-section r-section--tight">
        <div class="container">
          <div class="r-type-grid">
            <?php foreach ($typeInfo as $type => [$icon, $text]) {
              if (!isset($carTypes[$type])) {
                continue;
              }
              $info = $carTypes[$type]; ?>
              <a class="r-type-card r-reveal" href="car-listing?type=<?php echo urlencode($type); ?>">
                <span class="r-type-card__icon"><i class="fa <?php echo $icon; ?>" aria-hidden="true"></i></span>
                <span class="r-type-card__body">
                  <span class="r-type-card__title"><?php echo $type; ?></span>
                  <span class="r-type-card__text"><?php echo $text; ?></span>
                  <span class="r-type-card__meta"><?php echo (int)$info->cars; ?> <?php echo $info->cars == 1 ? 'car' : 'cars'; ?> &middot; from <?php echo format_price_per_day($info->fromPrice); ?></span>
                </span>
                <i class="fa fa-arrow-right r-type-card__arrow" aria-hidden="true"></i>
              </a>
            <?php } ?>
          </div>
        </div>
      </section>
    <?php } ?>

    <!-- Newest cars -->
    <section class="r-section r-section--muted">
      <div class="container">
        <div class="r-section-row">
          <div class="r-section-head r-section-head--start">
            <span class="r-eyebrow">The fleet</span>
            <h2 class="r-section-title">Recently added cars</h2>
            <p class="r-section-lead">Every car is listed with its real photos, specs and daily price.</p>
          </div>
          <a href="car-listing" class="r-btn r-btn--outline">View all cars <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <?php if ($featuredCars) { ?>
          <div class="r-grid r-grid--cards">
            <?php foreach ($featuredCars as $i => $car) { ?>
              <div class="r-reveal"><?php echo car_card($car, ['badge' => $i === 0 ? 'New' : '']); ?></div>
            <?php } ?>
          </div>
        <?php } else {
          echo empty_state('fa-car', 'No cars listed yet', 'New cars are added regularly. Please check back soon.');
        } ?>
      </div>
    </section>

    <!-- How it works -->
    <section class="r-section">
      <div class="container">
        <div class="r-section-head">
          <span class="r-eyebrow">How it works</span>
          <h2 class="r-section-title">On the road in three steps</h2>
        </div>
        <ol class="r-steps">
          <li class="r-reveal">
            <span class="r-steps__num">1</span>
            <h3>Choose a car</h3>
            <p>Filter by type, brand and budget, or let us suggest a car for your trip.</p>
          </li>
          <li class="r-reveal">
            <span class="r-steps__num">2</span>
            <h3>Pick your dates</h3>
            <p>Choose pickup and return dates and send your booking request in a minute.</p>
          </li>
          <li class="r-reveal">
            <span class="r-steps__num">3</span>
            <h3>Drive away</h3>
            <p>We confirm your booking and the car is ready when you arrive.</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- Why Rento -->
    <section class="r-section r-section--dark">
      <div class="container">
        <div class="r-section-head">
          <span class="r-eyebrow">Why Rento</span>
          <h2 class="r-section-title">Renting a car should be the easy part</h2>
        </div>
        <div class="r-grid r-grid--4">
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></span>
            <h3>Checked cars</h3>
            <p>Cars are inspected and cleaned before every rental.</p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-tag" aria-hidden="true"></i></span>
            <h3>Clear pricing</h3>
            <p>One daily price in rupees, shown before you book.</p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-calendar-days" aria-hidden="true"></i></span>
            <h3>Flexible dates</h3>
            <p>Rent for a day, a weekend or longer trips.</p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-headset" aria-hidden="true"></i></span>
            <h3>Local support</h3>
            <p>Questions before or during your trip? Call or message us.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Find my car (recommendations) -->
    <section class="r-section r-section--tight">
      <div class="container">
        <div class="r-callout r-reveal">
          <span class="r-callout__icon"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i></span>
          <div class="r-callout__body">
            <h2>Not sure which car fits your trip?</h2>
            <p>Tell us your budget and the kind of car you like, and we'll suggest the best matches.</p>
          </div>
          <a href="car-recommendation" class="r-btn r-btn--dark r-btn--lg">Find my car</a>
        </div>
      </div>
    </section>

    <!-- Testimonials (only real, approved ones) -->
    <?php if ($testimonials) { ?>
      <section class="r-section r-section--muted">
        <div class="container">
          <div class="r-section-head">
            <span class="r-eyebrow">Testimonials</span>
            <h2 class="r-section-title">What renters say</h2>
          </div>
          <div class="r-grid r-grid--3">
            <?php foreach ($testimonials as $testimonial) { ?>
              <figure class="r-quote r-reveal">
                <i class="fa fa-quote-left r-quote__mark" aria-hidden="true"></i>
                <blockquote><?php echo htmlentities($testimonial->Testimonial); ?></blockquote>
                <figcaption>
                  <span class="r-avatar" aria-hidden="true"><?php echo htmlentities(initials($testimonial->FullName)); ?></span>
                  <span><?php echo htmlentities($testimonial->FullName); ?></span>
                </figcaption>
              </figure>
            <?php } ?>
          </div>
        </div>
      </section>
    <?php } ?>

    <!-- Brands -->
    <?php if ($brands) { ?>
      <section class="r-section r-section--tight">
        <div class="container">
          <div class="r-brands">
            <h2 class="r-brands__title">Browse by brand</h2>
            <ul class="r-chips">
              <?php foreach ($brands as $brand) { ?>
                <li><a class="r-chip" href="car-listing?brand=<?php echo (int)$brand->id; ?>"><?php echo htmlentities($brand->BrandName); ?> <span class="r-chip__count"><?php echo (int)$brand->cars; ?></span></a></li>
              <?php } ?>
            </ul>
          </div>
        </div>
      </section>
    <?php } ?>

    <?php include('includes/cta-book-car.php'); ?>

  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
