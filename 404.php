<?php
// "Page not found" page. Apache shows it for missing URLs (ErrorDocument in .htaccess),
// and pages include it when their record doesn't exist (vehical-details.php, page.php).
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/config.php';
http_response_code(404);

?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Page Not Found';
  $pageDescription = 'The page you were looking for could not be found. Browse the Rento fleet or search for a car.';
  include(__DIR__ . '/includes/head.php');
  ?>
  <meta name="robots" content="noindex">
</head>

<body class="r-page">

  <?php include(__DIR__ . '/includes/header.php'); ?>

  <main id="main">
    <section class="r-section r-notfound">
      <div class="container">
        <div class="r-notfound__inner">
          <p class="r-notfound__code" aria-hidden="true">404</p>
          <h1>This road doesn't go anywhere</h1>
          <p class="r-section-lead">The page you were looking for doesn't exist, or the car is no longer listed.</p>
          <form class="r-notfound__search" action="car-listing" method="get" role="search">
            <label class="visually-hidden" for="notfound-search">Search cars</label>
            <input type="search" id="notfound-search" name="q" class="r-input" placeholder="Search by brand, model, fuel type or year" required>
            <button type="submit" class="r-btn">Search</button>
          </form>
          <div class="r-panel__actions">
            <a href="./" class="r-btn r-btn--dark">Go to home</a>
            <a href="car-listing" class="r-btn r-btn--outline">Browse all cars</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include(__DIR__ . '/includes/footer.php'); ?>
  <?php include(__DIR__ . '/includes/login.php'); ?>
  <?php include(__DIR__ . '/includes/registration.php'); ?>
  <?php include(__DIR__ . '/includes/forgotpassword.php'); ?>
  <?php include(__DIR__ . '/includes/scripts.php'); ?>

</body>

</html>
