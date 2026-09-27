<?php
// robots.txt (served at /robots.txt by .htaccess). Search engines only read it at the root of a host,
// so it takes effect once the site runs at the top of its own domain (docs/deployment.md).
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
$root = (string)parse_url(seo_base_url(), PHP_URL_PATH); // "/" on real hosting, "/Rento/" on MAMP
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
foreach (['admin/', 'api/', 'includes/', 'ml/', 'tools/', 'database/', 'docs/', 'logs/',
    'invoice', 'my-booking', 'my-testimonials', 'post-testimonial', 'profile', 'update-password', 'reset-password', 'logout', 'search'] as $path) {
  echo 'Disallow: ' . $root . $path . "\n";
}
echo "\nSitemap: " . seo_url('sitemap.xml') . "\n";
