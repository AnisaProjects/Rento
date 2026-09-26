<?php
// Shared <head> contents for every public page. Set before including:
//   $pageTitle         page name, shown as "<title> | Rento"
//   $pageDescription   meta description (optional; falls back to the site description)
//   $pageUsesCarousel  true on pages with an Owl carousel (optional)
//   $pageStyles        extra stylesheet URLs loaded after style.css (optional)
$pageTitle = $pageTitle ?? 'Car Rental in Nepal';

// Pages shown at a deeper clean URL (car/..., or a missing URL in any folder) resolve
// relative links from the site root. Pages at the root don't need (or get) a <base>.
$siteBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$requestDir = preg_replace('#[^/]*$#', '', (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$pageDescription = $pageDescription ?? 'Rento: rent a verified car anywhere in Nepal. Browse the fleet, compare daily prices in rupees and book online.';
?>
<meta charset="utf-8">
<?php if ($requestDir !== $siteBase) { ?>
<base href="<?php echo htmlentities($siteBase); ?>">
<?php } ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlentities($pageTitle); ?> | Rento</title>
<meta name="description" content="<?php echo htmlentities($pageDescription); ?>">
<meta name="theme-color" content="#ff5a36">
<link rel="icon" type="image/png" href="assets/images/favicon-icon/favicon.png">
<link rel="apple-touch-icon" href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
<!-- Fonts: Plus Jakarta Sans (headings) + Inter (body) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
<?php if (!empty($pageUsesCarousel)) { ?>
<link rel="stylesheet" href="assets/css/owl.carousel.css">
<?php } ?>
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/style.css">
<?php foreach ($pageStyles ?? [] as $href) { ?>
<link rel="stylesheet" href="<?php echo htmlentities($href); ?>">
<?php } ?>
