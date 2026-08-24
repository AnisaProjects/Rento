<?php
session_start();
error_reporting(0);
include('includes/config.php');

$pagetype = isset($_GET['type']) ? $_GET['type'] : 'aboutus';
$sql = "SELECT type,detail,PageName from tblpages where type=:pagetype";
$query = $dbh->prepare($sql);
$query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_OBJ);
?>

<!DOCTYPE HTML>
<html lang="en">

<head>

    <title>Car Rental Portal | Page details</title>
    <!--Bootstrap -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
    <!--Custome Style -->
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <!--About Us page redesign (extends style.css, doesn't replace it) -->
    <link rel="stylesheet" href="assets/css/about-us.css" type="text/css">
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
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
</head>

<body>
    <!-- Start Switcher -->
    <?php include('includes/colorswitcher.php'); ?>
    <!-- /Switcher -->

    <!--Header-->
    <?php include('includes/header.php'); ?>

    <?php if ($result) { ?>

        <!-- Hero -->
        <section class="page-header aboutus_page aboutus-hero">
            <div class="container">
                <div class="page-header_wrap">
                    <span class="aboutus-hero__kicker">EST. 2015 &mdash; KATHMANDU</span>
                    <div class="page-heading">
                        <h1><?php echo htmlentities($result->PageName); ?></h1>
                    </div>
                    <ul class="coustom-breadcrumb">
                        <li><a href="index.php">Home</a></li>
                        <li><?php echo htmlentities($result->PageName); ?></li>
                    </ul>
                </div>
            </div>
            <!-- Dark Overlay-->
            <div class="dark-overlay"></div>
        </section>

        <!-- 1. Intro — short, comes straight from tblpages so it stays admin-editable -->
        <section class="about2-intro reveal">
            <div class="container">
                <div class="about2-intro__grid">
                    <h2 class="about2-intro__headline">
                        Kathmandu traffic taught us what renters actually need.
                    </h2>
                    <div class="about2-intro__body">
                        <?php echo $result->detail; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Mission & Vision — short, side by side -->
        <section class="about2-mv">
            <div class="container">
                <div class="about2-mv__grid">
                    <div class="about2-mv__card reveal">
                        <span class="about2-eyebrow">OUR MISSION</span>
                        <p>Pair every renter in Nepal with the right car, at a fair price, with nothing hidden.</p>
                    </div>
                    <div class="about2-mv__card about2-mv__card--dark reveal">
                        <span class="about2-eyebrow about2-eyebrow--light">OUR VISION</span>
                        <p>A country where booking a car takes less time than hailing one on the street.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. The number that matters -->
        <section class="about2-stat reveal">
            <div class="container">
                <div class="about2-stat__row">
                    <div class="about2-stat__count">
                        <span class="about2-counter" data-count-target="120">0</span><span
                            class="about2-counter__suffix">+</span>
                    </div>
                    <p>cities covered, and counting on the meter</p>
                </div>
            </div>
        </section>

        <div class="about2-hr"></div>

        <!-- 4. Journey / milestones -->
        <section class="about2-journey">
            <div class="container">
                <div class="about2-journey__head reveal">
                    <span class="about2-eyebrow">THE ROUTE SO FAR</span>
                    <h2>Four stops, one standard</h2>
                </div>

                <ol class="about2-route">
                    <li class="reveal">
                        <span class="about2-route__year">2015</span>
                        <div class="about2-route__stop">
                            <h5>First twelve cars</h5>
                            <p>Launched with a dozen vehicles and one rule: buy only from official dealerships.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-route__year">2018</span>
                        <div class="about2-route__stop">
                            <h5>The verified-dealer model</h5>
                            <p>Every listing tied to a real dealership record — no private resellers, no surprises.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-route__year">2021</span>
                        <div class="about2-route__stop">
                            <h5>Fifty partner cities</h5>
                            <p>Automatic transmission became standard across every booking class, not just premium.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-route__year">TODAY</span>
                        <div class="about2-route__stop">
                            <h5>120+ cities, one helpline</h5>
                            <p>Same standard everywhere: verified cars, honest pricing, a person who actually answers.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <!-- 5. Manifest / standards -->
        <section class="about2-manifest">
            <div class="container">
                <div class="about2-manifest__head reveal">
                    <span class="about2-eyebrow about2-eyebrow--light">THE MANIFEST</span>
                    <h2>What every rental has to clear</h2>
                </div>

                <ul class="about2-checklist">
                    <li class="reveal">
                        <span class="about2-checklist__num">01</span>
                        <div>
                            <h5>Bought from official dealerships only</h5>
                            <p>No private resellers in the fleet, ever.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-checklist__num">02</span>
                        <div>
                            <h5>Air conditioning, power steering, electric windows</h5>
                            <p>Standard equipment on every single vehicle we list.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-checklist__num">03</span>
                        <div>
                            <h5>Automatic available in every class</h5>
                            <p>From compact to premium — not reserved for the top tier.</p>
                        </div>
                    </li>
                    <li class="reveal">
                        <span class="about2-checklist__num">04</span>
                        <div>
                            <h5>No automaker exclusivity</h5>
                            <p>We're not tied to one brand, so the pick across makes and models stays honest.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- 6. CTA -->
        <section class="about2-cta reveal">
            <div class="container">
                <div class="about2-cta__inner">
                    <div>
                        <span class="about2-eyebrow about2-eyebrow--light">READY WHEN YOU ARE</span>
                        <h2>Your next ride is a few taps away</h2>
                        <p>Browse the fleet, pick your dates, and we'll have the keys waiting.</p>
                    </div>
                    <a href="car-listing.php" class="about2-btn">Browse cars <i class="fa fa-angle-right"></i></a>
                </div>
            </div>
        </section>

    <?php } else { ?>

        <section class="section-padding">
            <div class="container text-center">
                <h3>We couldn't find that page.</h3>
                <p><a href="index.php">Back to home</a></p>
            </div>
        </section>

    <?php } ?>

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

    <!-- Site-wide interactions: sticky header + back-to-top.
       Move this <script> tag into header.php or footer.php so every page gets it,
       not just this one. -->
    <script src="assets/js/site-interactions.js"></script>
    <!-- Page-only: scroll reveals + the odometer count-up on this page -->
    <script src="assets/js/about-us.js"></script>

</body>

</html>