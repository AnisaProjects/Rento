<?php
// Add or edit a package (?id=<id> to edit). Photos use the Phase 0 upload checks + WebP.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/vehicle-form.php';
admin_require_login();

const PACKAGE_GALLERY_SLOTS = 4;   // new gallery photos per save
const PACKAGE_MAX_DAYS = 30;       // itinerary rows

$id = intval($_GET['id'] ?? 0);
$package = null;
if ($id) {
  $query = $dbh->prepare("SELECT * FROM tblpackages WHERE id = :id");
  $query->execute([':id' => $id]);
  $package = $query->fetch(PDO::FETCH_OBJ);
  if (!$package) {
    flash_set('error', 'That package no longer exists.');
    redirect_to('manage-packages.php');
  }
}
$categories = $dbh->query("SELECT id, CategoryName, IsActive FROM tblpackagecategories ORDER BY SortOrder, CategoryName")->fetchAll(PDO::FETCH_OBJ);

if (isset($_POST['savepackage'])) {
  $title = trim((string)($_POST['packagetitle'] ?? ''));
  $categoryId = (int)($_POST['categoryid'] ?? 0);
  $prices = [];
  foreach (VEHICLE_CAR_TYPES as $type) {
    $price = (int)preg_replace('/\D/', '', (string)($_POST['price'][$type] ?? ''));
    if ($price > 0) $prices[$type] = min($price, 10000000);
  }
  $itinerary = [];
  foreach ((array)($_POST['daytitle'] ?? []) as $i => $dayTitle) {
    $dayTitle = trim((string)$dayTitle);
    $dayText = trim((string)($_POST['daytext'][$i] ?? ''));
    if ($dayTitle !== '' || $dayText !== '') {
      $itinerary[] = ['day' => max(1, (int)($_POST['daynumber'][$i] ?? count($itinerary) + 1)), 'title' => mb_substr($dayTitle, 0, 150), 'text' => mb_substr($dayText, 0, 1000)];
    }
  }
  $itinerary = array_slice($itinerary, 0, PACKAGE_MAX_DAYS);
  $starting = (int)preg_replace('/\D/', '', (string)($_POST['startingprice'] ?? ''));
  if ($starting <= 0 && $prices) $starting = min($prices);
  $days = (int)($_POST['durationdays'] ?? 1);
  $categoryIds = array_map(fn($c) => (int)$c->id, $categories);
  $error = match (true) {
    $title === '' || mb_strlen($title) > 150 => 'Please enter a title (up to 150 characters).',
    $categoryId && !in_array($categoryId, $categoryIds, true) => 'Please choose a category.',
    $days < 1 || $days > 365 => 'Duration in days must be between 1 and 365.',
    $starting <= 0 => 'Please enter a starting price or at least one price per vehicle type.',
    default => '',
  };
  if ($error === '') {
    // Photos: validate and store before saving the rest.
    $cover = $package->CoverImage ?? PACKAGE_PLACEHOLDER;
    $oldCover = null;
    $newGallery = [];
    try {
      if (!vehicle_image_missing('img_cover')) {
        $oldCover = $cover;
        $cover = store_site_image('img_cover', 'The cover photo');
      } elseif (!empty($_POST['removecover'])) {
        $oldCover = $cover;
        $cover = PACKAGE_PLACEHOLDER;
      }
      for ($n = 1; $n <= PACKAGE_GALLERY_SLOTS; $n++) {
        if (!vehicle_image_missing("img_gallery_$n")) {
          $newGallery[] = store_site_image("img_gallery_$n", "Gallery photo $n");
        }
      }
    } catch (RuntimeException $e) {
      $error = $e->getMessage();
    }
  }
  if ($error !== '') {
    flash_set('error', $error);
    $_SESSION['package_form'] = $_POST;
    redirect_to(current_url());
  }

  $values = [
    ':cat' => $categoryId ?: null,
    ':title' => $title,
    ':slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-'),
    ':summary' => mb_substr(trim((string)($_POST['summary'] ?? '')), 0, 300),
    ':description' => trim((string)($_POST['description'] ?? '')),
    ':durationlabel' => mb_substr(trim((string)($_POST['durationlabel'] ?? '')), 0, 80),
    ':days' => $days,
    ':route' => mb_substr(trim((string)($_POST['routestops'] ?? '')), 0, 300),
    ':starting' => $starting,
    ':prices' => json_encode($prices),
    ':itinerary' => json_encode($itinerary, JSON_UNESCAPED_UNICODE),
    ':inclusions' => implode("\n", package_lines((string)($_POST['inclusions'] ?? ''))),
    ':exclusions' => implode("\n", package_lines((string)($_POST['exclusions'] ?? ''))),
    ':cover' => $cover,
    ':sort' => (int)($_POST['sortorder'] ?? 0),
    ':active' => empty($_POST['isactive']) ? 0 : 1,
  ];
  if ($package) {
    $dbh->prepare("UPDATE tblpackages SET CategoryId = :cat, Title = :title, Slug = :slug, Summary = :summary, Description = :description,
      DurationLabel = :durationlabel, DurationDays = :days, RouteStops = :route, StartingPrice = :starting, PricesJson = :prices,
      ItineraryJson = :itinerary, Inclusions = :inclusions, Exclusions = :exclusions, CoverImage = :cover, SortOrder = :sort, IsActive = :active
      WHERE id = :id")->execute($values + [':id' => $id]);
  } else {
    $dbh->prepare("INSERT INTO tblpackages (CategoryId, Title, Slug, Summary, Description, DurationLabel, DurationDays, RouteStops, StartingPrice,
      PricesJson, ItineraryJson, Inclusions, Exclusions, CoverImage, SortOrder, IsActive)
      VALUES (:cat, :title, :slug, :summary, :description, :durationlabel, :days, :route, :starting, :prices, :itinerary, :inclusions, :exclusions, :cover, :sort, :active)")->execute($values);
    $id = (int)$dbh->lastInsertId();
  }
  if ($oldCover) delete_site_image($oldCover);
  // Gallery: replace or remove existing photos, add new ones at the end.
  $existing = $dbh->prepare("SELECT id, ImagePath FROM tblpackageimages WHERE PackageId = :id");
  $existing->execute([':id' => $id]);
  foreach ($existing->fetchAll(PDO::FETCH_OBJ) as $img) {
    if (!vehicle_image_missing('img_existing_' . $img->id)) {
      try {
        $path = store_site_image('img_existing_' . $img->id, 'A gallery photo');
        $dbh->prepare("UPDATE tblpackageimages SET ImagePath = :path WHERE id = :img")->execute([':path' => $path, ':img' => $img->id]);
        delete_site_image($img->ImagePath);
      } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
      }
    }
  }
  foreach ((array)($_POST['removegallery'] ?? []) as $imageId) {
    $img = $dbh->prepare("SELECT ImagePath FROM tblpackageimages WHERE id = :img AND PackageId = :id");
    $img->execute([':img' => (int)$imageId, ':id' => $id]);
    if ($path = $img->fetchColumn()) {
      $dbh->prepare("DELETE FROM tblpackageimages WHERE id = :img")->execute([':img' => (int)$imageId]);
      delete_site_image($path);
    }
  }
  $nextSort = (int)$dbh->query("SELECT COALESCE(MAX(SortOrder), 0) FROM tblpackageimages WHERE PackageId = " . (int)$id)->fetchColumn();
  foreach ($newGallery as $path) {
    $dbh->prepare("INSERT INTO tblpackageimages (PackageId, ImagePath, SortOrder) VALUES (:id, :path, :sort)")->execute([':id' => $id, ':path' => $path, ':sort' => $nextSort += 10]);
  }
  flash_set('success', 'Package ' . $title . ' saved' . ($values[':active'] ? ' and active on the website.' : ' (inactive, not shown on the website).'));
  redirect_to('package-edit.php?id=' . $id);
}

// Values for the form: what was typed after an error, else the saved package.
$form = $_SESSION['package_form'] ?? null;
unset($_SESSION['package_form']);
$val = function (string $field, string $column, $default = '') use ($form, $package) {
  if ($form !== null) return (string)($form[$field] ?? '');
  return $package ? (string)($package->$column ?? '') : (string)$default;
};
$savedPrices = $form !== null ? array_map('strval', (array)($form['price'] ?? [])) : ($package ? package_prices($package) : []);
$days = $package ? package_itinerary($package) : [];
if ($form !== null) {
  $days = [];
  foreach ((array)($form['daytitle'] ?? []) as $i => $t) $days[] = ['day' => (int)($form['daynumber'][$i] ?? 0), 'title' => $t, 'text' => $form['daytext'][$i] ?? ''];
}
if (!$days) $days = [['day' => 1, 'title' => '', 'text' => '']];
$gallery = [];
if ($package) {
  $g = $dbh->prepare("SELECT id, ImagePath FROM tblpackageimages WHERE PackageId = :id ORDER BY SortOrder, id");
  $g->execute([':id' => $package->id]);
  $gallery = $g->fetchAll(PDO::FETCH_OBJ);
}
$isActive = $form !== null ? !empty($form['isactive']) : (bool)($package->IsActive ?? 0);
$cover = $package ? package_cover($package) : PACKAGE_PLACEHOLDER;

admin_page_start($package ? 'Edit Package' : 'Add Package', 'package-edit.php', [['Packages', 'manage-packages.php']], [
  'libs' => ['images'],
  'lead' => $package ? $package->Title : 'Packages are enquiry-only: customers send a request and you confirm the price.',
  'actions' => $package && (int)$package->IsActive ? '<a class="btn btn-light" href="../' . h(package_url($package->id, $package->Title)) . '" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View on website</a>' : '',
]);
?>
<form method="post" enctype="multipart/form-data" id="package-form">
  <?php echo csrf_field(); ?>
  <section class="a-card a-form-section">
    <div class="a-card__body d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h2 class="a-card__title mb-1"><i class="fa fa-eye" aria-hidden="true"></i>Visibility</h2>
        <p class="a-muted mb-0">Inactive packages are hidden from the website. Check prices and photos before activating.</p>
      </div>
      <div class="form-check form-switch fs-5 mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="isactive" name="isactive" value="1"<?php echo $isActive ? ' checked' : ''; ?>>
        <label class="form-check-label fs-6 fw-semibold" for="isactive">Active (shown on the website)</label>
      </div>
    </div>
  </section>

  <section class="a-card a-form-section">
    <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-circle-info" aria-hidden="true"></i>Basic info</h2></div>
    <div class="a-card__body a-form-grid">
      <div class="a-span-all">
        <label class="form-label" for="packagetitle">Title <span class="a-req">*</span></label>
        <input class="form-control" id="packagetitle" name="packagetitle" maxlength="150" required value="<?php echo h($val('packagetitle', 'Title')); ?>">
      </div>
      <div>
        <label class="form-label" for="categoryid">Category</label>
        <select class="form-select" id="categoryid" name="categoryid">
          <option value="0">No category</option>
          <?php foreach ($categories as $c) { ?>
            <option value="<?php echo (int)$c->id; ?>"<?php echo (int)$val('categoryid', 'CategoryId') === (int)$c->id ? ' selected' : ''; ?>><?php echo h($c->CategoryName); ?><?php echo (int)$c->IsActive ? '' : ' (hidden)'; ?></option>
          <?php } ?>
        </select>
        <div class="form-text"><a href="package-categories.php">Manage categories</a></div>
      </div>
      <div>
        <label class="form-label" for="sortorder">Display order</label>
        <input class="form-control" type="number" id="sortorder" name="sortorder" step="1" value="<?php echo h($val('sortorder', 'SortOrder', '100')); ?>">
        <div class="form-text">Lower numbers come first.</div>
      </div>
      <div class="a-span-all">
        <label class="form-label" for="summary">Summary</label>
        <input class="form-control" id="summary" name="summary" maxlength="300" value="<?php echo h($val('summary', 'Summary')); ?>" placeholder="One sentence for the package card">
      </div>
      <div class="a-span-all">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="4"><?php echo h($val('description', 'Description')); ?></textarea>
      </div>
      <div>
        <label class="form-label" for="durationlabel">Duration (as shown)</label>
        <input class="form-control" id="durationlabel" name="durationlabel" maxlength="80" value="<?php echo h($val('durationlabel', 'DurationLabel')); ?>" placeholder="e.g. 3 days / 2 nights">
      </div>
      <div>
        <label class="form-label" for="durationdays">Duration in days <span class="a-req">*</span></label>
        <input class="form-control" type="number" id="durationdays" name="durationdays" min="1" max="365" required value="<?php echo h($val('durationdays', 'DurationDays', '1')); ?>">
      </div>
      <div class="a-span-all">
        <label class="form-label" for="routestops">Route or stops</label>
        <input class="form-control" id="routestops" name="routestops" maxlength="300" value="<?php echo h($val('routestops', 'RouteStops')); ?>" placeholder="e.g. Kathmandu – Bandipur – Pokhara">
      </div>
    </div>
  </section>

  <section class="a-card a-form-section">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-tag" aria-hidden="true"></i>Prices</h2>
      <span class="a-muted">Leave a vehicle type empty if the package isn't offered with it.</span>
    </div>
    <div class="a-card__body a-form-grid a-form-grid--3">
      <?php foreach (VEHICLE_CAR_TYPES as $type) { ?>
        <div>
          <label class="form-label" for="price-<?php echo $type; ?>"><?php echo $type; ?></label>
          <div class="input-group">
            <span class="input-group-text">Rs</span>
            <input class="form-control" type="number" min="0" step="100" inputmode="numeric" id="price-<?php echo $type; ?>" name="price[<?php echo $type; ?>]" value="<?php echo h($savedPrices[$type] ?? ''); ?>">
          </div>
        </div>
      <?php } ?>
      <div>
        <label class="form-label" for="startingprice">Starting price</label>
        <div class="input-group">
          <span class="input-group-text">Rs</span>
          <input class="form-control" type="number" min="0" step="100" inputmode="numeric" id="startingprice" name="startingprice" value="<?php echo h($val('startingprice', 'StartingPrice')); ?>">
        </div>
        <div class="form-text">Shown as "From Rs …". Empty = the lowest price above.</div>
      </div>
    </div>
  </section>

  <section class="a-card a-form-section">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-route" aria-hidden="true"></i>Itinerary</h2>
      <button type="button" class="btn btn-light btn-sm" data-add-day><i class="fa fa-plus" aria-hidden="true"></i>Add day</button>
    </div>
    <div class="a-card__body" id="itinerary">
      <?php foreach ($days as $i => $day) { ?>
        <div class="a-day a-form-grid mb-3" data-day>
          <div>
            <label class="form-label">Day</label>
            <input class="form-control" type="number" min="1" max="<?php echo PACKAGE_MAX_DAYS; ?>" name="daynumber[]" value="<?php echo max(1, (int)$day['day'] ?: $i + 1); ?>" aria-label="Day number">
          </div>
          <div>
            <label class="form-label">Title</label>
            <div class="d-flex gap-2">
              <input class="form-control" name="daytitle[]" maxlength="150" value="<?php echo h($day['title']); ?>" aria-label="Day title">
              <button type="button" class="a-icon-btn a-icon-btn--danger flex-shrink-0 align-self-center" data-remove-day aria-label="Remove this day"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            </div>
          </div>
          <div class="a-span-all">
            <label class="form-label">What happens</label>
            <textarea class="form-control" name="daytext[]" rows="2" maxlength="1000" aria-label="Day description"><?php echo h($day['text']); ?></textarea>
          </div>
        </div>
      <?php } ?>
    </div>
  </section>

  <section class="a-card a-form-section">
    <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-list-check" aria-hidden="true"></i>Included and not included</h2></div>
    <div class="a-card__body a-form-grid">
      <div>
        <label class="form-label" for="inclusions">Included (one per line)</label>
        <textarea class="form-control" id="inclusions" name="inclusions" rows="6" placeholder="Driver&#10;Fuel&#10;Tolls"><?php echo h($val('inclusions', 'Inclusions')); ?></textarea>
      </div>
      <div>
        <label class="form-label" for="exclusions">Not included (one per line)</label>
        <textarea class="form-control" id="exclusions" name="exclusions" rows="6" placeholder="Hotels&#10;Meals&#10;Entry fees"><?php echo h($val('exclusions', 'Exclusions')); ?></textarea>
      </div>
    </div>
  </section>

  <section class="a-card a-form-section">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-images" aria-hidden="true"></i>Photos</h2>
      <span class="a-muted">Large photos are resized in your browser before upload.</span>
    </div>
    <div class="a-card__body">
      <div class="a-images">
        <?php echo admin_image_field('img_cover', 'Cover photo', '../' . $cover, $cover === PACKAGE_PLACEHOLDER ? 'Placeholder for now: upload a real photo' : 'Shown on cards and behind the title', $cover !== PACKAGE_PLACEHOLDER ? 'removecover' : ''); ?>
        <?php foreach ($gallery as $img) { ?>
          <?php echo admin_image_field('img_existing_' . (int)$img->id, 'Gallery photo', is_file(__DIR__ . '/../' . $img->ImagePath) ? '../' . $img->ImagePath : '', '', 'removegallery[]', 'Remove', (string)(int)$img->id); ?>
        <?php } ?>
        <?php for ($n = 1; $n <= PACKAGE_GALLERY_SLOTS; $n++) {
          echo admin_image_field('img_gallery_' . $n, 'Add gallery photo', '', 'Optional');
        } ?>
      </div>
    </div>
  </section>

  <div class="a-form-actions">
    <a class="btn btn-light" href="manage-packages.php">Back to packages</a>
    <button class="btn btn-primary" name="savepackage" value="1" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save package</button>
  </div>
</form>
<template id="day-template">
  <div class="a-day a-form-grid mb-3" data-day>
    <div><label class="form-label">Day</label><input class="form-control" type="number" min="1" max="<?php echo PACKAGE_MAX_DAYS; ?>" name="daynumber[]" aria-label="Day number"></div>
    <div><label class="form-label">Title</label><div class="d-flex gap-2"><input class="form-control" name="daytitle[]" maxlength="150" aria-label="Day title"><button type="button" class="a-icon-btn a-icon-btn--danger flex-shrink-0 align-self-center" data-remove-day aria-label="Remove this day"><i class="fa fa-xmark" aria-hidden="true"></i></button></div></div>
    <div class="a-span-all"><label class="form-label">What happens</label><textarea class="form-control" name="daytext[]" rows="2" maxlength="1000" aria-label="Day description"></textarea></div>
  </div>
</template>
<?php admin_page_end(<<<'JS'
(function () {
  var list = document.getElementById('itinerary');
  var template = document.getElementById('day-template');
  document.querySelector('[data-add-day]').addEventListener('click', function () {
    var row = template.content.firstElementChild.cloneNode(true);
    row.querySelector('[name="daynumber[]"]').value = list.querySelectorAll('[data-day]').length + 1;
    list.appendChild(row);
    row.querySelector('[name="daytitle[]"]').focus();
  });
  list.addEventListener('click', function (event) {
    var button = event.target.closest('[data-remove-day]');
    if (!button) return;
    var rows = list.querySelectorAll('[data-day]');
    var row = button.closest('[data-day]');
    if (rows.length > 1) row.remove();
    else row.querySelectorAll('input:not([type=number]), textarea').forEach(function (el) { el.value = ''; });
  });
})();
JS); ?>
