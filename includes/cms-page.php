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

if ($isAboutUs) {
  // Real fleet numbers and photos for the About page.
  $fleet = $dbh->query("SELECT COUNT(*) AS cars, COUNT(DISTINCT VehiclesBrand) AS brands, COUNT(DISTINCT NULLIF(CarType, '')) AS types,
      MIN(PricePerDay) AS fromPrice FROM tblvehicles")->fetch(PDO::FETCH_OBJ);
  $fleetPhotos = $dbh->query("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.Vimage1, tblvehicles.Vimage2, tblbrands.BrandName
    FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand WHERE tblvehicles.Vimage1 <> '' ORDER BY tblvehicles.id DESC LIMIT 6")->fetchAll(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $pageTitles[$pagetype] ?? 'Information';
  $pageDescription = 'Rento ' . $pageTitle . ': how renting a car with Rento in Nepal works.';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero($heading, $pageLeads[$pagetype] ?? '', [], $isAboutUs ? 'aboutus-page-header-img' : ''); ?>

    <?php if ($isAboutUs) { ?>
      <section class="r-section">
        <div class="container">
          <div class="r-about">
            <div class="r-reveal">
              <span class="r-eyebrow">Our story</span>
              <h2 class="r-section-title">Kathmandu traffic taught us what renters actually need.</h2>
              <div class="r-prose"><?php echo $detail !== '' ? $detail : '<p>More about Rento is coming soon.</p>'; ?></div>
            </div>
            <?php if (count($fleetPhotos) >= 3) { ?>
              <div class="r-collage r-reveal" aria-hidden="true">
                <?php foreach (array_slice($fleetPhotos, 0, 3) as $i => $photo) { ?>
                  <div class="r-collage__item r-collage__item--<?php echo $i + 1; ?>"><?php echo vehicle_image($photo->Vimage1, '', ['sizes' => $i === 0 ? '(min-width: 992px) 380px, 60vw' : '(min-width: 992px) 240px, 40vw']); ?></div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
        </div>
      </section>

      <?php if ($fleet && $fleet->cars) { ?>
        <section class="r-section--tight r-stats-band">
          <div class="container">
            <dl class="r-stats">
              <div><dt>Cars in the fleet</dt><dd><?php echo (int)$fleet->cars; ?></dd></div>
              <div><dt>Brands</dt><dd><?php echo (int)$fleet->brands; ?></dd></div>
              <div><dt>Car types</dt><dd><?php echo (int)$fleet->types; ?></dd></div>
              <div><dt>Daily prices from</dt><dd><?php echo format_price($fleet->fromPrice); ?></dd></div>
            </dl>
          </div>
        </section>
      <?php } ?>

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

      <section class="r-photo-band">
        <picture>
          <source type="image/webp" srcset="assets/images/fun-facts-bg.webp">
          <img src="assets/images/fun-facts-bg.jpg" alt="" loading="lazy">
        </picture>
        <div class="container r-photo-band__copy r-reveal">
          <span class="r-eyebrow">On the road</span>
          <p class="r-photo-band__quote">From the Ring Road to the Prithvi Highway, we keep cars ready for the way Nepal drives.</p>
        </div>
      </section>

      <?php if ($fleetPhotos) { ?>
        <section class="r-section">
          <div class="container">
            <div class="r-section-row">
              <div class="r-section-head r-section-head--start">
                <span class="r-eyebrow">The fleet</span>
                <h2 class="r-section-title">Meet some of our cars</h2>
              </div>
              <a href="car-listing" class="r-btn r-btn--outline">See every car <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <ul class="r-gallery-grid">
              <?php foreach ($fleetPhotos as $photo) { ?>
                <li class="r-reveal">
                  <a href="<?php echo htmlentities(vehicle_url($photo->id, $photo->BrandName, $photo->VehiclesTitle)); ?>">
                    <?php echo vehicle_image($photo->Vimage1, vehicle_display_name($photo->BrandName, $photo->VehiclesTitle), ['sizes' => '(min-width: 992px) 380px, 50vw']); ?>
                    <span class="r-gallery-grid__label"><?php echo htmlentities(vehicle_display_name($photo->BrandName, $photo->VehiclesTitle)); ?></span>
                  </a>
                </li>
              <?php } ?>
            </ul>
          </div>
        </section>
      <?php } ?>

      <?php include('includes/cta-book-car.php'); ?>

    <?php } else { ?>
      <section class="r-section">
        <div class="container">
          <div class="r-article">
            <?php $faqs = $pagetype === 'faqs' ? faq_items($detail) : []; ?>
            <?php if ($faqs) { ?>
              <div>
                <div class="r-faq-search">
                  <label class="visually-hidden" for="faq-filter">Search the FAQs</label>
                  <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                  <input type="search" id="faq-filter" class="r-input" placeholder="Search the questions..." autocomplete="off">
                </div>
                <div class="accordion r-faq" id="faq-list">
                  <?php foreach ($faqs as $i => $faq) { ?>
                    <div class="accordion-item r-faq__item">
                      <h2 class="accordion-header" id="faq-h-<?php echo $i; ?>">
                        <button class="accordion-button <?php echo $i === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse"
                          data-bs-target="#faq-<?php echo $i; ?>" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-controls="faq-<?php echo $i; ?>">
                          <?php echo htmlentities($faq['question']); ?>
                        </button>
                      </h2>
                      <div id="faq-<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" aria-labelledby="faq-h-<?php echo $i; ?>" data-bs-parent="#faq-list">
                        <div class="accordion-body r-prose"><?php echo $faq['answer']; ?></div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
                <p class="r-faq__none r-hint" hidden>No questions match your search. <a href="contact-us">Ask us directly</a>.</p>
              </div>
            <?php } elseif ($detail !== '') { ?>
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
  <?php if ($pagetype === 'faqs') { ?>
    <script>
      // Filter the FAQ accordion as the visitor types.
      (function () {
        var input = document.getElementById('faq-filter');
        if (!input) return;
        var items = document.querySelectorAll('.r-faq__item');
        var none = document.querySelector('.r-faq__none');
        input.addEventListener('input', function () {
          var q = input.value.trim().toLowerCase();
          var shown = 0;
          items.forEach(function (item) {
            var match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
            item.hidden = !match;
            if (match) shown++;
          });
          none.hidden = shown > 0;
        });
      })();
    </script>
  <?php } ?>

</body>

</html>
