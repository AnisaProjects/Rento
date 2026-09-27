<?php
// Template for the CMS pages (About Us, FAQs, Terms, Privacy). The text comes from
// tblpages and is edited in admin -> Manage Pages. Each page file (about-us.php,
// faqs.php, terms.php, privacy.php) sets $pagetype and includes this file.
require_once __DIR__ . '/security.php';
include('includes/config.php');

$pagetype = $pagetype ?? '';
$isAboutUs = ($pagetype === 'aboutus');

$query = $dbh->prepare("SELECT type, detail, PageName FROM tblpages WHERE type = :pagetype");
$query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
$query->execute();
$cmsPage = $query->fetch(PDO::FETCH_OBJ);

$pageTitles = ['aboutus' => 'About Us', 'faqs' => 'FAQs', 'terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy'];
$pageLeads = [
  'aboutus' => 'Est. 2022 in Kathmandu. Cars for city runs and mountain roads, with prices you can see before you book.',
  'faqs' => 'Answers to the questions renters ask us most.',
  'terms' => 'The terms that apply when you rent a car with Rento.',
  'privacy' => 'How we collect, use and protect your personal information.',
];
$heading = trim($cmsPage->PageName ?? '') ?: ($pageTitles[$pagetype] ?? 'Information');
$detail = $cmsPage->detail ?? '';
if ($isAboutUs) {
  // Known typo in the stored About text ("ur mission"); fix it in admin -> Manage Pages.
  $detail = str_ireplace('ur mission', 'Our mission', $detail);
}
$detail = clean_cms_html($detail);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $pageTitles[$pagetype] ?? 'Information';
  $pageDescription = 'Rento ' . $pageTitle . ': how renting a car with Rento in Nepal works.';
  $pageModern = true;
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero($heading, $pageLeads[$pagetype] ?? ''); ?>

    <?php if ($isAboutUs) { ?>
      <section class="r-section">
        <div class="container">
          <div class="r-about">
            <div class="r-about__intro r-reveal">
              <span class="r-eyebrow">Our story</span>
              <h2 class="r-section-title">Kathmandu traffic taught us what renters actually need.</h2>
            </div>
            <div class="r-prose r-reveal"><?php echo $detail !== '' ? $detail : '<p>More about Rento is coming soon.</p>'; ?></div>
          </div>
        </div>
      </section>

      <section class="r-section r-section--muted">
        <div class="container">
          <div class="r-grid r-grid--3">
            <div class="r-panel r-reveal">
              <span class="r-eyebrow">Our mission</span>
              <p class="r-panel__big">Pair every renter in Nepal with the right car, at a fair price, with nothing hidden.</p>
            </div>
            <div class="r-panel r-panel--dark r-reveal">
              <span class="r-eyebrow">Our vision</span>
              <p class="r-panel__big">A country where booking a car takes less time than hailing one on the street.</p>
            </div>
            <div class="r-panel r-reveal">
              <span class="r-eyebrow">How we work</span>
              <p class="r-panel__big">Checked cars, clear daily prices in rupees, and a real person to call.</p>
            </div>
          </div>
        </div>
      </section>

      <?php include('includes/cta-book-car.php'); ?>

    <?php } else { ?>
      <section class="r-section">
        <div class="container">
          <div class="r-article">
            <?php if ($detail !== '') { ?>
              <div class="r-prose"><?php echo $detail; ?></div>
            <?php } else {
              echo empty_state('fa-folder-open', 'Nothing here yet', 'This page has no content yet. Please check back soon, or contact us with your question.', 'Contact us', 'contact-us');
            } ?>
            <aside class="r-article__aside">
              <div class="r-panel">
                <h2 class="r-panel__title">Still have a question?</h2>
                <p>We're happy to help with bookings, cars and trips.</p>
                <a class="r-btn r-btn--block" href="contact-us">Contact us</a>
              </div>
            </aside>
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
