<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

// Filters come from the URL (GET), so every result list can be bookmarked and shared:
//   q (keyword), type, brand (id), fueltype, max (price per day), sort, page
const LISTING_PER_PAGE = 12;
const LISTING_SORTS = [
  'newest' => ['Newest first', 'tblvehicles.id DESC'],
  'price_asc' => ['Price: low to high', 'tblvehicles.PricePerDay ASC, tblvehicles.id DESC'],
  'price_desc' => ['Price: high to low', 'tblvehicles.PricePerDay DESC, tblvehicles.id DESC'],
  'year_desc' => ['Model year: newest', 'tblvehicles.ModelYear DESC, tblvehicles.id DESC'],
];
const LISTING_TYPES = ['Hatchback', 'Sedan', 'SUV'];
const LISTING_BUDGETS = [1000, 2000, 3000, 5000];

$brands = $dbh->query("SELECT id, BrandName FROM tblbrands ORDER BY BrandName")->fetchAll(PDO::FETCH_OBJ);
$brandNames = [];
foreach ($brands as $brand) {
  $brandNames[(int)$brand->id] = $brand->BrandName;
}
$fuelTypes = $dbh->query("SELECT DISTINCT FuelType FROM tblvehicles WHERE FuelType IS NOT NULL AND FuelType <> '' ORDER BY FuelType")->fetchAll(PDO::FETCH_COLUMN);

$param = fn(string $key) => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
$filters = [
  'q' => mb_substr($param('q'), 0, 80),
  'type' => in_array($param('type'), LISTING_TYPES, true) ? $param('type') : '',
  'brand' => isset($brandNames[(int)$param('brand')]) ? (int)$param('brand') : '',
  'fueltype' => in_array($param('fueltype'), $fuelTypes, true) ? $param('fueltype') : '',
  'max' => in_array((int)$param('max'), LISTING_BUDGETS, true) ? (int)$param('max') : '',
];
$sort = isset(LISTING_SORTS[$param('sort')]) ? $param('sort') : 'newest';
$page = max(1, (int)$param('page'));

// Build the WHERE clause from the active filters (all values are bound).
$where = [];
$bind = [];
if ($filters['q'] !== '') {
  $where[] = "(tblvehicles.VehiclesTitle LIKE :q OR tblbrands.BrandName LIKE :q OR tblvehicles.FuelType LIKE :q
    OR tblvehicles.CarType LIKE :q OR tblvehicles.ModelYear LIKE :q)";
  $bind[':q'] = '%' . addcslashes($filters['q'], '%_\\') . '%';
}
if ($filters['type'] !== '') {
  $where[] = "tblvehicles.CarType = :type";
  $bind[':type'] = $filters['type'];
}
if ($filters['brand'] !== '') {
  $where[] = "tblvehicles.VehiclesBrand = :brand";
  $bind[':brand'] = $filters['brand'];
}
if ($filters['fueltype'] !== '') {
  $where[] = "tblvehicles.FuelType = :fuel";
  $bind[':fuel'] = $filters['fueltype'];
}
if ($filters['max'] !== '') {
  $where[] = "tblvehicles.PricePerDay <= :max";
  $bind[':max'] = $filters['max'];
}
$from = "FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$count = $dbh->prepare("SELECT COUNT(*) $from");
$count->execute($bind);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / LISTING_PER_PAGE));
$page = min($page, $pages);

$list = $dbh->prepare("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblvehicles.FuelType,
    tblvehicles.ModelYear, tblvehicles.SeatingCapacity, tblvehicles.Vimage1, tblvehicles.CarType, tblbrands.BrandName
  $from ORDER BY " . LISTING_SORTS[$sort][1] . " LIMIT " . LISTING_PER_PAGE . " OFFSET " . (($page - 1) * LISTING_PER_PAGE));
$list->execute($bind);
$cars = $list->fetchAll(PDO::FETCH_OBJ);

// URL of this listing with some parameters changed (null removes one).
function listing_url(array $filters, string $sort, array $changes = []): string
{
  $params = array_merge($filters, ['sort' => $sort === 'newest' ? '' : $sort], $changes);
  $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
  return 'car-listing' . ($params ? '?' . http_build_query($params) : '');
}

$activeChips = [];
if ($filters['q'] !== '') $activeChips[] = ['"' . $filters['q'] . '"', 'q'];
if ($filters['type'] !== '') $activeChips[] = [$filters['type'], 'type'];
if ($filters['brand'] !== '') $activeChips[] = [$brandNames[$filters['brand']], 'brand'];
if ($filters['fueltype'] !== '') $activeChips[] = [$filters['fueltype'], 'fueltype'];
if ($filters['max'] !== '') $activeChips[] = ['Up to ' . format_price($filters['max']), 'max'];

$heading = $filters['q'] !== '' ? 'Results for "' . $filters['q'] . '"' : ($filters['type'] !== '' ? $filters['type'] . ' cars' : 'All cars');
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = $heading === 'All cars' ? 'Car Listing' : $heading;
  $pageDescription = 'Browse every car available to rent with Rento: filter by type, brand, fuel and budget, with daily prices in rupees.';
  include('includes/head.php');
  ?>
  <?php if ($activeChips || $page > 1) { ?><meta name="robots" content="noindex, follow"><?php } ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero($heading, 'Filter by type, brand, fuel and budget. Every price is per day, in rupees.', $heading === 'All cars' ? [] : [['Cars', 'car-listing']]); ?>

    <section class="r-section r-section--muted r-listing">
      <div class="container">
        <div class="r-listing__layout">

          <aside class="r-filters" aria-label="Filters">
            <button class="r-btn r-btn--outline r-btn--block r-filters__toggle" type="button" data-bs-toggle="collapse"
              data-bs-target="#r-filter-form" aria-expanded="false" aria-controls="r-filter-form">
              <i class="fa fa-sliders" aria-hidden="true"></i>Filters<?php echo $activeChips ? ' (' . count($activeChips) . ')' : ''; ?>
            </button>
            <form class="r-filters__form collapse" id="r-filter-form" action="car-listing" method="get">
              <h2 class="r-filters__title">Filter cars</h2>
              <div class="r-field">
                <label class="r-label" for="f-q">Keyword</label>
                <input type="search" id="f-q" name="q" class="r-input" value="<?php echo htmlentities($filters['q']); ?>" placeholder="e.g. Fortuner, CNG, 2019">
              </div>
              <div class="r-field">
                <label class="r-label" for="f-type">Car type</label>
                <select id="f-type" name="type" class="r-select">
                  <option value="">Any type</option>
                  <?php foreach (LISTING_TYPES as $type) { ?>
                    <option value="<?php echo $type; ?>" <?php echo $filters['type'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="r-field">
                <label class="r-label" for="f-brand">Brand</label>
                <select id="f-brand" name="brand" class="r-select">
                  <option value="">Any brand</option>
                  <?php foreach ($brands as $brand) { ?>
                    <option value="<?php echo (int)$brand->id; ?>" <?php echo $filters['brand'] === (int)$brand->id ? 'selected' : ''; ?>><?php echo htmlentities($brand->BrandName); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="r-field">
                <label class="r-label" for="f-fuel">Fuel type</label>
                <select id="f-fuel" name="fueltype" class="r-select">
                  <option value="">Any fuel</option>
                  <?php foreach ($fuelTypes as $fuel) { ?>
                    <option value="<?php echo htmlentities($fuel); ?>" <?php echo $filters['fueltype'] === $fuel ? 'selected' : ''; ?>><?php echo htmlentities($fuel); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="r-field">
                <label class="r-label" for="f-max">Budget per day</label>
                <select id="f-max" name="max" class="r-select">
                  <option value="">Any budget</option>
                  <?php foreach (LISTING_BUDGETS as $max) { ?>
                    <option value="<?php echo $max; ?>" <?php echo $filters['max'] === $max ? 'selected' : ''; ?>>Up to <?php echo format_price($max); ?></option>
                  <?php } ?>
                </select>
              </div>
              <?php if ($sort !== 'newest') { ?><input type="hidden" name="sort" value="<?php echo htmlentities($sort); ?>"><?php } ?>
              <button type="submit" class="r-btn r-btn--block">Show cars</button>
              <?php if ($activeChips) { ?><a class="r-filters__clear" href="car-listing">Clear all filters</a><?php } ?>
            </form>
          </aside>

          <div class="r-listing__results">
            <div class="r-toolbar">
              <p class="r-toolbar__count" role="status"><strong><?php echo $total; ?></strong> <?php echo $total === 1 ? 'car' : 'cars'; ?> available</p>
              <form class="r-toolbar__sort" action="car-listing" method="get">
                <?php foreach ($filters as $key => $value) {
                  if ($value !== '') { ?><input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlentities((string)$value); ?>"><?php }
                } ?>
                <label class="r-label" for="sort">Sort by</label>
                <select id="sort" name="sort" class="r-select" onchange="this.form.submit()">
                  <?php foreach (LISTING_SORTS as $key => [$label]) { ?>
                    <option value="<?php echo $key; ?>" <?php echo $sort === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                  <?php } ?>
                </select>
                <noscript><button type="submit" class="r-btn r-btn--sm">Sort</button></noscript>
              </form>
            </div>

            <?php if ($activeChips) { ?>
              <ul class="r-chips r-active-filters" aria-label="Active filters">
                <?php foreach ($activeChips as [$label, $key]) { ?>
                  <li><a class="r-chip" href="<?php echo htmlentities(listing_url($filters, $sort, [$key => null, 'page' => null])); ?>"><?php echo htmlentities($label); ?> <i class="fa fa-xmark" aria-hidden="true"></i><span class="visually-hidden"> (remove filter)</span></a></li>
                <?php } ?>
              </ul>
            <?php } ?>

            <?php if ($cars) { ?>
              <div class="r-grid r-grid--cards">
                <?php foreach ($cars as $car) {
                  echo car_card($car, ['heading' => 'h2', 'sizes' => '(min-width: 1200px) 300px, (min-width: 768px) 45vw, 100vw']);
                } ?>
              </div>

              <?php if ($pages > 1) { ?>
                <nav class="r-pagination" aria-label="Pages">
                  <?php if ($page > 1) { ?><a class="r-pagination__link" href="<?php echo htmlentities(listing_url($filters, $sort, ['page' => $page - 1 > 1 ? $page - 1 : null])); ?>" rel="prev"><i class="fa fa-arrow-left" aria-hidden="true"></i> Previous</a><?php } ?>
                  <?php for ($p = 1; $p <= $pages; $p++) { ?>
                    <a class="r-pagination__link <?php echo $p === $page ? 'is-current' : ''; ?>" href="<?php echo htmlentities(listing_url($filters, $sort, ['page' => $p > 1 ? $p : null])); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo $p; ?></a>
                  <?php } ?>
                  <?php if ($page < $pages) { ?><a class="r-pagination__link" href="<?php echo htmlentities(listing_url($filters, $sort, ['page' => $page + 1])); ?>" rel="next">Next <i class="fa fa-arrow-right" aria-hidden="true"></i></a><?php } ?>
                </nav>
              <?php } ?>
            <?php } else {
              echo empty_state('fa-magnifying-glass', 'No cars match these filters', 'Try removing a filter or raising your budget. New cars are added regularly.', 'Show all cars', 'car-listing');
            } ?>
          </div>

        </div>
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
