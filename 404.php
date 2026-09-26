<?php
// "Page not found" page. Apache shows it for missing URLs (ErrorDocument in .htaccess),
// and pages include it when their record doesn't exist (vehical-details.php, page.php).
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/config.php';
http_response_code(404);

// For ErrorDocument requests the browser's URL is the missing one (maybe in a
// sub-folder), so resolve every relative link from the site root.
$siteBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <base href="<?php echo htmlentities($siteBase); ?>">
  <?php
  $pageTitle = 'Page Not Found';
  $pageDescription = 'The page you were looking for could not be found. Browse the Rento fleet or search for a car.';
  include(__DIR__ . '/includes/head.php');
  ?>
  <meta name="robots" content="noindex">
</head>

<body>

  <!--Header-->
  <?php include(__DIR__ . '/includes/header.php'); ?>
  <!-- /Header -->

  <section class="page-header listing_page">
    <div class="container">
      <div class="page-header_wrap">
        <div class="page-heading">
          <h1>Page Not Found</h1>
        </div>
        <ul class="coustom-breadcrumb">
          <li><a href="index.php">Home</a></li>
          <li>404</li>
        </ul>
      </div>
    </div>
    <div class="dark-overlay"></div>
  </section>

  <section class="content-page section-padding">
    <div class="container">
      <div class="content-page__card notfound">
        <span class="content-page__icon">
          <i class="fa fa-car" aria-hidden="true"></i>
        </span>
        <span class="content-page__badge">Error 404</span>
        <h2 class="content-page__title">This road doesn't go anywhere</h2>
        <div class="content-page__body">
          <p>The page you were looking for doesn't exist, or the car is no longer listed. Try a search, or pick a car
            from the listing.</p>

          <form class="notfound__search" action="search.php" method="post" role="search">
            <?php echo csrf_field(); ?>
            <label class="visually-hidden" for="notfound-search">Search cars</label>
            <input type="text" id="notfound-search" name="searchdata" class="form-control"
              placeholder="Search by brand, model, fuel type or year" required>
            <button type="submit" class="btn">Search</button>
          </form>

          <div class="notfound__links">
            <a href="index.php" class="btn">Go to Home</a>
            <a href="car-listing.php" class="btn notfound__btn-outline">Browse all cars</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!--Footer -->
  <?php include(__DIR__ . '/includes/footer.php'); ?>
  <!-- /Footer-->

  <!--Login-Form -->
  <?php include(__DIR__ . '/includes/login.php'); ?>
  <!--/Login-Form -->

  <!--Register-Form -->
  <?php include(__DIR__ . '/includes/registration.php'); ?>
  <!--/Register-Form -->

  <!--Forgot-password-Form -->
  <?php include(__DIR__ . '/includes/forgotpassword.php'); ?>
  <!--/Forgot-password-Form -->

  <?php include(__DIR__ . '/includes/scripts.php'); ?>

</body>

</html>
