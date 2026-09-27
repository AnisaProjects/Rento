<?php
// Site header: logo, main navigation, search, and the login button or account menu.
// On small screens the navigation moves into an off-canvas panel (#r-mobile-nav).
$contact = site_contact_info($dbh);
$current_page = basename($_SERVER['PHP_SELF']);

// [url, label, pages that mark it active]
$navItems = [
  ['./', 'Home', ['index.php']],
  ['car-listing', 'Cars', ['car-listing.php', 'vehical-details.php', 'search.php', 'search-carresult.php', 'search-brandresult.php']],
  ['car-recommendation', 'Find My Car', ['car-recommendation.php']],
  // Shown once at least one package is active (admin -> Packages).
  ['packages', 'Packages', ['packages.php', 'package-details.php']],
  ['about-us', 'About', ['about-us.php']],
  ['faqs', 'FAQs', ['faqs.php']],
  ['contact-us', 'Contact', ['contact-us.php']],
];

if (!packages_active_count($dbh)) {
  $navItems = array_values(array_filter($navItems, fn($navItem) => $navItem[0] !== 'packages'));
}

$accountName = '';
if (!empty($_SESSION['login'])) {
  $accountQuery = $dbh->prepare("SELECT FullName FROM tblusers WHERE EmailId=:email");
  $accountQuery->bindParam(':email', $_SESSION['login'], PDO::PARAM_STR);
  $accountQuery->execute();
  $accountName = (string)($accountQuery->fetchColumn() ?: $_SESSION['login']);
}
$accountLinks = [
  ['profile', 'fa-user', 'Profile settings'],
  ['my-booking', 'fa-calendar-check', 'My bookings'],
  ['my-testimonials', 'fa-comment-dots', 'My testimonials'],
  ['post-testimonial', 'fa-pen-to-square', 'Write a testimonial'],
  ['update-password', 'fa-key', 'Change password'],
];
?>
<a class="r-skip-link" href="#main">Skip to content</a>

<header class="r-header" id="top">
  <div class="container">
    <div class="r-header__bar">
      <a class="r-header__logo r-wordmark" href="./" aria-label="<?php echo content('site_name'); ?> home"><?php
        $siteLogo = content_image('site_logo');
        echo $siteLogo !== '' ? site_picture($siteLogo, content_raw('site_name'), ['imgClass' => 'r-header__logo-img', 'priority' => true]) : site_wordmark(); ?></a>

      <nav aria-label="Main">
        <ul class="r-nav">
          <?php foreach ($navItems as [$navUrl, $navLabel, $navPages]) {
            $navActive = in_array($current_page, $navPages, true); ?>
            <li><a href="<?php echo $navUrl; ?>" class="<?php echo $navActive ? 'is-active' : ''; ?>" <?php echo $navActive ? 'aria-current="page"' : ''; ?>><?php echo $navLabel; ?></a></li>
          <?php } ?>
        </ul>
      </nav>

      <div class="r-header__actions">
        <?php if ($contact->ContactNo) { ?>
          <a class="r-header__phone" href="tel:<?php echo htmlentities($contact->ContactNo); ?>">
            <i class="fa fa-phone" aria-hidden="true"></i><?php echo htmlentities($contact->ContactNo); ?>
          </a>
        <?php } ?>

        <button type="button" class="r-icon-btn" data-bs-toggle="collapse" data-bs-target="#r-search"
          aria-expanded="false" aria-controls="r-search" aria-label="Search cars">
          <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
        </button>

        <?php if ($accountName !== '') { ?>
          <div class="dropdown">
            <button type="button" class="r-account-btn" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="r-avatar" aria-hidden="true"><?php echo htmlentities(initials($accountName)); ?></span>
              <span class="r-account-btn__name"><?php echo htmlentities(strtok($accountName, ' ')); ?></span>
              <i class="fa fa-angle-down" aria-hidden="true"></i>
              <span class="visually-hidden">Account menu</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end r-dropdown">
              <li class="dropdown-header">Signed in as<br><strong><?php echo htmlentities($accountName); ?></strong></li>
              <?php foreach ($accountLinks as [$linkUrl, $linkIcon, $linkLabel]) { ?>
                <li><a class="dropdown-item" href="<?php echo $linkUrl; ?>"><i class="fa <?php echo $linkIcon; ?>" aria-hidden="true"></i><?php echo $linkLabel; ?></a></li>
              <?php } ?>
              <li><hr class="dropdown-divider"></li>
              <li><form method="post" action="logout"><?php echo csrf_field(); ?><button type="submit" class="dropdown-item"><i class="fa fa-arrow-right-from-bracket" aria-hidden="true"></i>Sign out</button></form></li>
            </ul>
          </div>
        <?php } else { ?>
          <a href="#loginform" class="r-btn r-btn--sm" data-bs-toggle="modal" aria-label="Login or register">
            <i class="fa fa-user" aria-hidden="true"></i><span class="r-header__login-text">Login / Register</span>
          </a>
        <?php } ?>

        <button type="button" class="r-icon-btn r-menu-toggle" data-bs-toggle="offcanvas" data-bs-target="#r-mobile-nav"
          aria-controls="r-mobile-nav" aria-label="Open menu">
          <i class="fa fa-bars" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="collapse r-search-panel" id="r-search">
    <div class="container">
      <form action="search" method="post" role="search">
        <?php echo csrf_field(); ?>
        <label class="visually-hidden" for="header-search">Search cars</label>
        <input type="text" id="header-search" name="searchdata" class="r-input" required
          placeholder="Search by brand, model, fuel type or year">
        <button type="submit" class="r-btn">Search</button>
      </form>
    </div>
  </div>
</header>

<div class="offcanvas offcanvas-end r-offcanvas" tabindex="-1" id="r-mobile-nav" aria-labelledby="r-mobile-nav-title">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title h5" id="r-mobile-nav-title">Menu</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="r-offcanvas__nav">
      <?php foreach ($navItems as [$navUrl, $navLabel, $navPages]) { ?>
        <li><a href="<?php echo $navUrl; ?>" class="<?php echo in_array($current_page, $navPages, true) ? 'is-active' : ''; ?>"><?php echo $navLabel; ?></a></li>
      <?php } ?>
    </ul>
    <?php if ($accountName !== '') { ?>
      <ul class="r-offcanvas__nav">
        <?php foreach ($accountLinks as [$linkUrl, $linkIcon, $linkLabel]) { ?>
          <li><a href="<?php echo $linkUrl; ?>"><?php echo $linkLabel; ?></a></li>
        <?php } ?>
        <li><form method="post" action="logout"><?php echo csrf_field(); ?><button type="submit" class="r-link-button">Sign out</button></form></li>
      </ul>
    <?php } else { ?>
      <a href="#loginform" class="r-btn r-btn--block" data-bs-toggle="modal">Login / Register</a>
    <?php } ?>
    <?php if ($contact->ContactNo) { ?>
      <p class="mt-4 mb-0"><a class="r-header__phone" href="tel:<?php echo htmlentities($contact->ContactNo); ?>"><i class="fa fa-phone" aria-hidden="true"></i><?php echo htmlentities($contact->ContactNo); ?></a></p>
    <?php } ?>
  </div>
</div>
