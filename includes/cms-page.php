<?php
// Template for the CMS pages (About Us, FAQs, Terms, Privacy). The text comes from
// tblpages and is edited in admin -> Manage Pages. Each page file (about-us.php,
// faqs.php, terms.php, privacy.php) sets $pagetype and includes this file.
require_once __DIR__ . '/security.php';
include('includes/config.php');

$pagetype = $pagetype ?? '';
$isAboutUs = ($pagetype === 'aboutus');

// Icon mapping for the generic "content page" template — add new types here
// and they automatically get a matching icon, no markup changes needed.
$pageIcons = [
  'faqs' => 'fa fa-circle-question',
  'contactus' => 'fa fa-envelope',
  'privacy' => 'fa fa-shield-halved',
  'terms' => 'fa-regular fa-file-lines',
];
$pageIcon = isset($pageIcons[$pagetype]) ? $pageIcons[$pagetype] : 'fa fa-circle-info';
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitles = ['aboutus' => 'About Us', 'faqs' => 'FAQs', 'terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy'];
  $pageTitle = $pageTitles[$pagetype] ?? 'Information';
  $pageDescription = 'Rento ' . $pageTitle . ': how renting a car with Rento in Nepal works.';
  $pageStyles = $isAboutUs ? ['assets/css/about-us.css'] : [];
  $pageScripts = $isAboutUs ? ['assets/js/about-us.js'] : [];
  include('includes/head.php');
  ?>
</head>

<body>


  <!--Header-->
  <?php include('includes/header.php'); ?>

  <?php
  $sql = "SELECT type,detail,PageName from tblpages where type=:pagetype";
  $query = $dbh->prepare($sql);
  $query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
  $query->execute();
  $results = $query->fetchAll(PDO::FETCH_OBJ);

  if ($query->rowCount() > 0) {
    foreach ($results as $result) {

      if ($isAboutUs) { ?>

        <!-- ===================== About Us redesign (self-contained) ===================== -->

        <section class="page-header aboutus_page aboutus-hero">
          <div class="container">
            <div class="page-header_wrap">
              <span class="aboutus-hero__kicker">EST. 2022 &mdash; KATHMANDU</span>
              <div class="page-heading">
                <h1><?php echo htmlentities($result->PageName); ?></h1>
              </div>
              <ul class="coustom-breadcrumb">
                <li><a href="./index.php">Home</a></li>
                <li><?php echo htmlentities($result->PageName); ?></li>
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
              <?php echo clean_cms_html($aboutDetail); ?>
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

        <!-- ===================== Generic content page (FAQs, Contact Us, Terms, Privacy, ...) ===================== -->

        <section class="page-header aboutus_page">
          <div class="container">
            <div class="page-header_wrap">
              <div class="page-heading">
                <h1><?php echo htmlentities($result->PageName); ?></h1>
              </div>
              <ul class="coustom-breadcrumb">
                <li><a href="index.php">Home</a></li>
                <li><?php echo htmlentities($result->PageName); ?></li>
              </ul>
            </div>
          </div>
          <div class="dark-overlay"></div>
        </section>

        <section class="content-page section-padding">
          <div class="container">
            <div class="content-page__card">
              <span class="content-page__icon">
                <i class="<?php echo $pageIcon; ?>" aria-hidden="true"></i>
              </span>
              <span class="content-page__badge"><?php echo htmlentities(strtoupper($pagetype)); ?></span>
              <h2 class="content-page__title"><?php echo htmlentities($result->PageName); ?></h2>
              <div class="content-page__body">
                <?php echo clean_cms_html($result->detail); ?>
              </div>
            </div>
          </div>
        </section>

      <?php }
    }
  } else { ?>

    <!-- No row found in tblpages for this type: fail gracefully instead of rendering nothing -->
    <section class="page-header aboutus_page">
      <div class="container">
        <div class="page-header_wrap">
          <div class="page-heading">
            <h1><?php echo htmlentities(ucfirst($pagetype ?: 'Page')); ?></h1>
          </div>
          <ul class="coustom-breadcrumb">
            <li><a href="index.php">Home</a></li>
            <li><?php echo htmlentities(ucfirst($pagetype ?: 'Page')); ?></li>
          </ul>
        </div>
      </div>
      <div class="dark-overlay"></div>
    </section>

    <section class="content-page section-padding">
      <div class="container">
        <div class="content-page__card content-page__card--empty">
          <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
          <p>No content has been added for this page yet.</p>
        </div>
      </div>
    </section>

  <?php } ?>

  <!--Footer -->
  <?php include('includes/footer.php'); ?>
  <!-- /Footer-->


  <!--Login-Form -->
  <?php include('includes/login.php'); ?>
  <!--/Login-Form -->

  <!--Register-Form -->
  <?php include('includes/registration.php'); ?>
  <!--/Register-Form -->

  <!--Forgot-password-Form -->
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>