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
// Texts: admin -> Site content -> Home page.
$typeInfo = [
  'Hatchback' => ['fa-car-side', content('home_type_hatchback')],
  'Sedan' => ['fa-car', content('home_type_sedan')],
  'SUV' => ['fa-truck-monster', content('home_type_suv')],
];

// Brands with cars: count, lowest daily price and the photo of their cheapest car.
$brands = $dbh->query("SELECT tblbrands.id, tblbrands.BrandName, tblbrands.BrandLogo, COUNT(tblvehicles.id) AS cars, MIN(tblvehicles.PricePerDay) AS fromPrice,
    (SELECT v2.Vimage1 FROM tblvehicles v2 WHERE v2.VehiclesBrand = tblbrands.id ORDER BY v2.PricePerDay, v2.id LIMIT 1) AS photo
  FROM tblbrands JOIN tblvehicles ON tblvehicles.VehiclesBrand = tblbrands.id
  GROUP BY tblbrands.id, tblbrands.BrandName, tblbrands.BrandLogo ORDER BY cars DESC, tblbrands.BrandName")->fetchAll(PDO::FETCH_OBJ);

// Hero slides: admin -> Site content -> Home page. Values are HTML-escaped.
$heroSlides = [];
foreach ([1, 2, 3] as $n) {
  $heroSlides[] = [content_image("home_hero_{$n}_image"), content("home_hero_{$n}_eyebrow"), content("home_hero_{$n}_title"),
    content("home_hero_{$n}_text"), content("home_hero_{$n}_button"), content("home_hero_{$n}_link")];
}
$heroPreload = preg_replace('/\.[a-z0-9]+$/i', '', $heroSlides[0][0]);

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
  $pageTitle = content_raw('seo_home_title');
  $pageDescription = content_raw('seo_home_description');
  include('includes/head.php');
  ?>
  <?php if (is_file(__DIR__ . '/' . $heroPreload . '-960.webp')) { ?>
    <link rel="preload" as="image" href="<?php echo htmlentities($heroPreload); ?>.webp" imagesrcset="<?php echo htmlentities($heroPreload); ?>-960.webp 960w, <?php echo htmlentities($heroPreload); ?>.webp 1920w" imagesizes="100vw" fetchpriority="high">
  <?php } ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">

    <!-- Hero: rotating slides, with the search card fixed below them -->
    <section class="r-hero" aria-label="Featured">
      <div id="r-hero-carousel" class="carousel slide carousel-fade r-hero__carousel">
        <div class="carousel-inner">
          <?php foreach ($heroSlides as $i => [$image, $eyebrow, $title, $text, $cta, $url]) { ?>
            <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo ($i + 1) . ' of ' . count($heroSlides); ?>">
              <?php echo site_picture($image, '', ['class' => 'r-hero__bg', 'sizes' => '100vw', 'priority' => $i === 0]); ?>
              <div class="container r-hero__copy">
                <span class="r-eyebrow"><?php echo $eyebrow; ?></span>
                <<?php echo $i === 0 ? 'h1' : 'p'; ?> class="r-hero__title"><?php echo $title; ?></<?php echo $i === 0 ? 'h1' : 'p'; ?>>
                <p><?php echo $text; ?></p>
                <a class="r-btn r-btn--light" href="<?php echo $url; ?>"><?php echo $cta; ?> <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          <?php } ?>
        </div>
        <div class="container r-hero__controls">
          <div class="r-hero__dots">
            <?php foreach ($heroSlides as $i => $slide) { ?>
              <button type="button" data-bs-target="#r-hero-carousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" <?php echo $i === 0 ? 'aria-current="true"' : ''; ?> aria-label="Slide <?php echo $i + 1; ?>"></button>
            <?php } ?>
          </div>
          <button type="button" class="r-hero__pause" data-r-carousel-toggle aria-label="Pause slideshow"><i class="fa fa-pause" aria-hidden="true"></i></button>
        </div>
      </div>

      <div class="container r-hero__search-wrap">
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
          <li><i class="fa fa-circle-check" aria-hidden="true"></i><?php echo content('home_trust_1'); ?></li>
          <li><i class="fa fa-tag" aria-hidden="true"></i><?php echo content('home_trust_2'); ?></li>
          <li><i class="fa fa-headset" aria-hidden="true"></i><?php echo content('home_trust_3'); ?></li>
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
            <span class="r-eyebrow"><?php echo content('home_fleet_eyebrow'); ?></span>
            <h2 class="r-section-title"><?php echo content('home_fleet_title'); ?></h2>
            <p class="r-section-lead"><?php echo content('home_fleet_lead'); ?></p>
          </div>
          <a href="car-listing" class="r-btn r-btn--outline"><?php echo content('home_fleet_button'); ?> <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
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
          <span class="r-eyebrow"><?php echo content('home_steps_eyebrow'); ?></span>
          <h2 class="r-section-title"><?php echo content('home_steps_title'); ?></h2>
        </div>
        <ol class="r-steps">
          <li class="r-reveal">
            <span class="r-steps__num">1</span>
            <h3><?php echo content('home_step_1_title'); ?></h3>
            <p><?php echo content('home_step_1_text'); ?></p>
          </li>
          <li class="r-reveal">
            <span class="r-steps__num">2</span>
            <h3><?php echo content('home_step_2_title'); ?></h3>
            <p><?php echo content('home_step_2_text'); ?></p>
          </li>
          <li class="r-reveal">
            <span class="r-steps__num">3</span>
            <h3><?php echo content('home_step_3_title'); ?></h3>
            <p><?php echo content('home_step_3_text'); ?></p>
          </li>
        </ol>
      </div>
    </section>

    <!-- Why Rento -->
    <section class="r-section r-section--dark">
      <div class="container">
        <div class="r-section-head">
          <span class="r-eyebrow"><?php echo content('home_why_eyebrow'); ?></span>
          <h2 class="r-section-title"><?php echo content('home_why_title'); ?></h2>
        </div>
        <div class="r-grid r-grid--4">
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></span>
            <h3><?php echo content('home_why_1_title'); ?></h3>
            <p><?php echo content('home_why_1_text'); ?></p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-tag" aria-hidden="true"></i></span>
            <h3><?php echo content('home_why_2_title'); ?></h3>
            <p><?php echo content('home_why_2_text'); ?></p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-calendar-days" aria-hidden="true"></i></span>
            <h3><?php echo content('home_why_3_title'); ?></h3>
            <p><?php echo content('home_why_3_text'); ?></p>
          </div>
          <div class="r-feature r-reveal">
            <span class="r-feature__icon"><i class="fa fa-headset" aria-hidden="true"></i></span>
            <h3><?php echo content('home_why_4_title'); ?></h3>
            <p><?php echo content('home_why_4_text'); ?></p>
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
            <h2><?php echo content('home_findcar_title'); ?></h2>
            <p><?php echo content('home_findcar_text'); ?></p>
          </div>
          <a href="car-recommendation" class="r-btn r-btn--dark r-btn--lg"><?php echo content('home_findcar_button'); ?></a>
        </div>
      </div>
    </section>

    <!-- Testimonials (only real, approved ones) -->
    <?php if ($testimonials) { ?>
      <section class="r-section r-section--muted">
        <div class="container">
          <div class="r-section-head">
            <span class="r-eyebrow"><?php echo content('home_testimonials_eyebrow'); ?></span>
            <h2 class="r-section-title"><?php echo content('home_testimonials_title'); ?></h2>
          </div>
          <div class="r-slider" data-r-slider>
            <div class="r-slider__track" tabindex="0" aria-label="Testimonials">
              <?php foreach ($testimonials as $testimonial) { ?>
                <figure class="r-quote r-slider__item">
                  <i class="fa fa-quote-left r-quote__mark" aria-hidden="true"></i>
                  <blockquote><?php echo htmlentities($testimonial->Testimonial); ?></blockquote>
                  <figcaption>
                    <span class="r-avatar" aria-hidden="true"><?php echo htmlentities(initials($testimonial->FullName)); ?></span>
                    <span><?php echo htmlentities($testimonial->FullName); ?></span>
                  </figcaption>
                </figure>
              <?php } ?>
            </div>
            <div class="r-slider__nav">
              <button type="button" class="r-icon-btn" data-r-slider-prev aria-label="Previous testimonials"><i class="fa fa-arrow-left" aria-hidden="true"></i></button>
              <button type="button" class="r-icon-btn" data-r-slider-next aria-label="Next testimonials"><i class="fa fa-arrow-right" aria-hidden="true"></i></button>
            </div>
          </div>
        </div>
      </section>
    <?php } ?>

    <!-- Brands -->
    <?php if ($brands) { ?>
      <section class="r-section">
        <div class="container">
          <div class="r-section-row">
            <div class="r-section-head r-section-head--start">
              <span class="r-eyebrow"><?php echo content('home_brands_eyebrow'); ?></span>
              <h2 class="r-section-title"><?php echo content('home_brands_title'); ?></h2>
            </div>
          </div>
          <div class="r-slider" data-r-slider>
            <ul class="r-slider__track r-brand-track" aria-label="Brands">
              <?php foreach ($brands as $brand) { ?>
                <li class="r-slider__item">
                  <a class="r-brand-card" href="car-listing?brand=<?php echo (int)$brand->id; ?>">
                    <span class="r-brand-card__media<?php echo $brand->BrandLogo && is_file(__DIR__ . '/' . $brand->BrandLogo) ? ' r-brand-card__media--logo' : ''; ?>"><?php
                      // Brand logo (admin -> Brands) when there is one, otherwise a photo of the brand's cheapest car.
                      echo $brand->BrandLogo && is_file(__DIR__ . '/' . $brand->BrandLogo)
                        ? site_picture($brand->BrandLogo, $brand->BrandName . ' logo')
                        : vehicle_image($brand->photo, '', ['sizes' => '280px']); ?></span>
                    <span class="r-brand-card__body">
                      <span class="r-brand-card__name"><?php echo htmlentities($brand->BrandName); ?></span>
                      <span class="r-brand-card__meta"><?php echo (int)$brand->cars; ?> <?php echo $brand->cars == 1 ? 'car' : 'cars'; ?> &middot; from <?php echo format_price_per_day($brand->fromPrice); ?></span>
                    </span>
                    <span class="r-brand-card__go" aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
                  </a>
                </li>
              <?php } ?>
            </ul>
            <div class="r-slider__nav">
              <button type="button" class="r-icon-btn" data-r-slider-prev aria-label="Previous brands"><i class="fa fa-arrow-left" aria-hidden="true"></i></button>
              <button type="button" class="r-icon-btn" data-r-slider-next aria-label="Next brands"><i class="fa fa-arrow-right" aria-hidden="true"></i></button>
            </div>
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
