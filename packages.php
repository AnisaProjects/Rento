<?php
// Packages & Services: active packages with category filters (?category=<slug>).
// Packages are managed in admin -> Packages; the texts here in admin -> Site content.
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

$categories = $dbh->query("SELECT c.id, c.CategoryName, c.Slug, c.Icon, COUNT(p.id) AS packages FROM tblpackagecategories c
  JOIN tblpackages p ON p.CategoryId = c.id AND p.IsActive = 1
  WHERE c.IsActive = 1 GROUP BY c.id, c.CategoryName, c.Slug, c.Icon, c.SortOrder ORDER BY c.SortOrder, c.CategoryName")->fetchAll(PDO::FETCH_OBJ);
$selected = null;
foreach ($categories as $category) {
  if ($category->Slug === ($_GET['category'] ?? '')) $selected = $category;
}

$sql = "SELECT p.*, c.CategoryName, c.Icon FROM tblpackages p LEFT JOIN tblpackagecategories c ON c.id = p.CategoryId
  WHERE p.IsActive = 1 AND (c.id IS NULL OR c.IsActive = 1)" . ($selected ? " AND p.CategoryId = :cid" : '') . " ORDER BY p.SortOrder, p.Title";
$query = $dbh->prepare($sql);
if ($selected) $query->bindValue(':cid', (int)$selected->id, PDO::PARAM_INT);
$query->execute();
$packages = $query->fetchAll(PDO::FETCH_OBJ);
$total = array_sum(array_map(fn($c) => (int)$c->packages, $categories));
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $selected ? $selected->CategoryName . ' packages' : content_raw('seo_packages_title');
  $pageDescription = content_raw('seo_packages_description');
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero(content_raw('packages_title'), content_raw('packages_lead')); ?>

    <section class="r-section r-section--muted">
      <div class="container">
        <?php if ($categories) { ?>
          <nav class="r-package-filters" aria-label="Package categories">
            <a class="r-chip<?php echo $selected ? '' : ' is-active'; ?>" href="packages"<?php echo $selected ? '' : ' aria-current="page"'; ?>>All <span><?php echo $total; ?></span></a>
            <?php foreach ($categories as $category) {
              $isSelected = $selected && $selected->id === $category->id; ?>
              <a class="r-chip<?php echo $isSelected ? ' is-active' : ''; ?>" href="packages?category=<?php echo urlencode($category->Slug); ?>"<?php echo $isSelected ? ' aria-current="page"' : ''; ?>>
                <i class="fa <?php echo htmlentities($category->Icon); ?>" aria-hidden="true"></i><?php echo htmlentities($category->CategoryName); ?> <span><?php echo (int)$category->packages; ?></span>
              </a>
            <?php } ?>
          </nav>
        <?php } ?>

        <?php if ($packages) { ?>
          <div class="r-grid r-grid--packages">
            <?php foreach ($packages as $package) {
              echo '<div class="r-reveal">' . package_card($package, 'h2') . '</div>';
            } ?>
          </div>
          <p class="r-hint r-package-note"><i class="fa fa-circle-info" aria-hidden="true"></i> Prices are starting prices per vehicle. Send a request and we confirm the final price, car and pickup time with you. No payment is taken online.</p>
        <?php } else {
          echo empty_state('fa-route', 'Packages are coming soon', 'We are preparing tours, airport transfers and cars with a driver. In the meantime, browse our self-drive cars or ask us about your trip.', 'Contact us', 'contact-us', 'h2');
        } ?>
      </div>
    </section>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
