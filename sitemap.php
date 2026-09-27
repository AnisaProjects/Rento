<?php
// sitemap.xml (served at /sitemap.xml by .htaccess): every indexable public page. Active vehicles and
// packages, type / brand / package-category lists and the content pages. Account, search-result and
// utility pages are left out (they are noindex, see includes/seo.php).
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

$urls = []; // [path, lastmod (Y-m-d or null), changefreq, priority]
$add = function (string $path, ?string $lastmod = null, string $freq = 'weekly', string $priority = '0.6') use (&$urls) {
  $urls[] = [$path, $lastmod ? substr($lastmod, 0, 10) : null, $freq, $priority];
};
$fleetUpdated = $dbh->query("SELECT MAX(COALESCE(UpdationDate, RegDate)) FROM tblvehicles WHERE IsActive = 1")->fetchColumn();

$add('', $fleetUpdated, 'daily', '1.0');
$add('car-listing', $fleetUpdated, 'daily', '0.9');
$add('car-recommendation', null, 'monthly', '0.7');
foreach ($dbh->query("SELECT DISTINCT CarType FROM tblvehicles WHERE IsActive = 1 AND CarType <> '' ORDER BY CarType")->fetchAll(PDO::FETCH_COLUMN) as $type) {
  $add('car-listing?type=' . rawurlencode($type), $fleetUpdated, 'weekly', '0.7');
}
foreach ($dbh->query("SELECT DISTINCT VehiclesBrand FROM tblvehicles WHERE IsActive = 1 ORDER BY VehiclesBrand")->fetchAll(PDO::FETCH_COLUMN) as $brand) {
  $add('car-listing?brand=' . (int)$brand, $fleetUpdated, 'weekly', '0.6');
}
foreach ($dbh->query("SELECT v.id, v.VehiclesTitle, b.BrandName, COALESCE(v.UpdationDate, v.RegDate) AS changed FROM tblvehicles v
    JOIN tblbrands b ON b.id = v.VehiclesBrand WHERE v.IsActive = 1 ORDER BY v.id")->fetchAll(PDO::FETCH_OBJ) as $car) {
  // Prices change daily with smart pricing, so car pages are 'daily'.
  $add(vehicle_url($car->id, $car->BrandName, $car->VehiclesTitle), $car->changed, 'daily', '0.8');
}
if (packages_active_count($dbh) > 0) {
  $add('packages', null, 'weekly', '0.7');
  foreach ($dbh->query("SELECT c.Slug, MAX(p.UpdatedAt) AS changed FROM tblpackagecategories c JOIN tblpackages p ON p.CategoryId = c.id AND p.IsActive = 1
      WHERE c.IsActive = 1 GROUP BY c.id, c.Slug ORDER BY c.SortOrder, c.id")->fetchAll(PDO::FETCH_OBJ) as $category) {
    $add('packages?category=' . rawurlencode($category->Slug), $category->changed, 'weekly', '0.5');
  }
  foreach ($dbh->query("SELECT p.id, p.Title, p.UpdatedAt FROM tblpackages p LEFT JOIN tblpackagecategories c ON c.id = p.CategoryId
      WHERE p.IsActive = 1 AND (c.id IS NULL OR c.IsActive = 1) ORDER BY p.SortOrder, p.id")->fetchAll(PDO::FETCH_OBJ) as $package) {
    $add(package_url($package->id, $package->Title), $package->UpdatedAt, 'weekly', '0.7');
  }
}
// Content pages (admin -> Manage Pages); only those with content.
$pageUrls = ['aboutus' => 'about-us', 'faqs' => 'faqs', 'terms' => 'terms', 'privacy' => 'privacy'];
foreach ($dbh->query("SELECT type, detail FROM tblpages")->fetchAll(PDO::FETCH_OBJ) as $page) {
  if (isset($pageUrls[$page->type]) && trim(strip_tags((string)$page->detail)) !== '') {
    $add($pageUrls[$page->type], null, 'monthly', $page->type === 'faqs' || $page->type === 'aboutus' ? '0.6' : '0.3');
  }
}
$add('contact-us', null, 'monthly', '0.5');

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $lastmod, $freq, $priority]) {
  echo '  <url><loc>' . htmlspecialchars(seo_url($path), ENT_XML1) . '</loc>'
    . ($lastmod ? '<lastmod>' . $lastmod . '</lastmod>' : '')
    . '<changefreq>' . $freq . '</changefreq><priority>' . $priority . "</priority></url>\n";
}
echo "</urlset>\n";
