<?php
session_start();
error_reporting(0);
include('includes/config.php');
?>

<!DOCTYPE HTML>
<html lang="en">

<head>

  <title>Car Rental Portal | Page details</title>
  <!--Bootstrap -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
  <!--Custome Style -->
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <?php if (isset($_GET['type']) && $_GET['type'] === 'aboutus') { ?>
    <!-- NEW: only for the about-us page, doesn't affect anything else -->
    <link rel="stylesheet" href="assets/css/about-us.css" type="text/css">
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap"
      rel="stylesheet">
  <?php } ?>
  <!--OWL Carousel slider-->
  <link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
  <link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
  <!--slick-slider -->
  <link href="assets/css/slick.css" rel="stylesheet">
  <!--bootstrap-slider -->
  <link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
  <!--FontAwesome Font Style -->
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">

  <!-- SWITCHER -->
  <link rel="stylesheet" id="switcher-css" type="text/css" href="assets/switcher/css/switcher.css" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/red.css" title="red" media="all"
    data-default-color="true" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/orange.css" title="orange" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/blue.css" title="blue" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/pink.css" title="pink" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/green.css" title="green" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/purple.css" title="purple" media="all" />

  <!-- Fav and touch icons -->
  <link rel="apple-touch-icon-precomposed" sizes="144x144"
    href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
  <link rel="apple-touch-icon-precomposed" sizes="114x114"
    href="assets/images/favicon-icon/apple-touch-icon-114-precomposed.html">
  <link rel="apple-touch-icon-precomposed" sizes="72x72"
    href="assets/images/favicon-icon/apple-touch-icon-72-precomposed.png">
  <link rel="apple-touch-icon-precomposed" href="assets/images/favicon-icon/apple-touch-icon-57-precomposed.png">
  <link rel="shortcut icon" href="assets/images/favicon-icon/favicon.png">
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,900" rel="stylesheet">
</head>

<body>
  <<!-- Start Switcher -->
    <?php include('includes/colorswitcher.php'); ?>
    <!-- /Switcher -->

    <!--Header-->
    <?php include('includes/header.php'); ?>
    <?php
    $pagetype = $_GET['type'];
    $isAboutUs = ($pagetype === 'aboutus'); // NEW: flag used only to branch the about-us markup below
    $sql = "SELECT type,detail,PageName from tblpages where type=:pagetype";
    $query = $dbh->prepare($sql);
    $query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    $cnt = 1;
    if ($query->rowCount() > 0) {
      foreach ($results as $result) {

        if ($isAboutUs) { ?>

          <!-- ===================== NEW: About Us redesign (self-contained, simplified) ===================== -->

          <section class="page-header aboutus_page aboutus-hero">
            <div class="container">
              <div class="page-header_wrap">
                <span class="aboutus-hero__kicker">EST. 2015 &mdash; KATHMANDU</span>
                <div class="page-heading">
                  <h1>
                    <?php echo htmlentities($result->PageName); ?>
                  </h1>
                </div>
                <ul class="coustom-breadcrumb">
                  <li><a href="#">Home</a></li>
                  <li>
                    <?php echo htmlentities($result->PageName); ?>
                  </li>
                </ul>
              </div>
            </div>
            <div class="dark-overlay"></div>
          </section>

          <?php
          // Temporary safety-patch for a known typo in the CMS content
          // ("ur mission" -> "Our mission"). The real fix is to correct
          // the `detail` field for type=aboutus in tblpages via the admin panel.
          $aboutDetail = isset($result->detail) ? str_ireplace('ur mission', 'Our mission', $result->detail) : '';
          ?>

          <section class="about2-intro">
            <div class="container">
              <span class="about2-eyebrow reveal" style="transition-delay:.05s">OUR STORY</span>
              <h2 class="about2-intro__headline reveal" style="transition-delay:.15s">
                Kathmandu traffic taught us what renters actually need.
              </h2>
              <div class="about2-intro__body reveal" style="transition-delay:.3s">
                <?php echo $aboutDetail; ?>
              </div>
            </div>
          </section>

          <section class="about2-mv">
            <div class="container">
              <div class="about2-mv__grid">
                <div class="about2-mv__card reveal" style="transition-delay:.1s">
                  <span class="about2-eyebrow">OUR MISSION</span>
                  <p>Pair every renter in Nepal with the right car, at a fair price, with nothing hidden.</p>
                </div>
                <div class="about2-mv__card about2-mv__card--dark reveal" style="transition-delay:.25s">
                  <span class="about2-eyebrow about2-eyebrow--light">OUR VISION</span>
                  <p>A country where booking a car takes less time than hailing one on the street.</p>
                </div>
              </div>
            </div>
          </section>

          <?php include('includes/cta-book-car.php'); ?>

        <?php } else { ?>

          <section class="page-header aboutus_page">
            <div class="container">
              <div class="page-header_wrap">
                <div class="page-heading">
                  <h1>
                    <?php echo htmlentities($result->PageName); ?>
                  </h1>
                </div>
                <ul class="coustom-breadcrumb">
                  <li><a href="#">Home</a></li>
                  <li>
                    <?php echo htmlentities($result->PageName); ?>
                  </li>
                </ul>
              </div>
            </div>
            <!-- Dark Overlay-->
            <div class="dark-overlay"></div>
          </section>
          <section class="ABOUT US section-padding">
            <div class="container">
              <div class="section-header text-center">


                <h2>
                  <?php echo htmlentities($result->PageName); ?>
                </h2>
                <p>
                  <?php echo $result->detail; ?>
                </p>
              </div>
            <?php } // end else (unchanged original markup)
    
      }
    } ?>
        <?php if (!$isAboutUs) { ?>
        </div>
      </section>
    <?php } ?>
    <!-- /Abfout-us-->

    <!--Footer -->
    <?php include('includes/footer.php'); ?>
    <!-- /Footer-->

    <!--Back to top-->
    <div id="back-top" class="back-top"> <a href="#top"><i class="fa fa-angle-up" aria-hidden="true"></i> </a> </div>
    <!--/Back to top-->

    <!--Login-Form -->
    <?php include('includes/login.php'); ?>
    <!--/Login-Form -->

    <!--Register-Form -->
    <?php include('includes/registration.php'); ?>

    <!--/Register-Form -->

    <!--Forgot-password-Form -->
    <?php include('includes/forgotpassword.php'); ?>
    <!--/Forgot-password-Form -->

    <!-- Scripts -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/interface.js"></script>
    <!--Switcher-->
    <script src="assets/switcher/js/switcher.js"></script>
    <!--bootstrap-slider-JS-->
    <script src="assets/js/bootstrap-slider.min.js"></script>
    <!--Slider-JS-->
    <script src="assets/js/slick.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>

    <?php if (isset($_GET['type']) && $_GET['type'] === 'aboutus') { ?>
      <!-- NEW: only for the about-us page -->
      <script src="assets/js/site-interactions.js"></script>
      <script src="assets/js/about-us.js"></script>
    <?php } ?>

</body>

<!-- Mirrored from themes.webmasterdriver.net/carforyou/demo/about-us.html by HTTrack Website Copier/3.x [XR&CO'2014], Fri, 16 Jun 2017 07:26:12 GMT -->

</html>