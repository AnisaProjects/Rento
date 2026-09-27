<?php
// Package categories (Tour, Airport transfer, ...): add, edit, show/hide, delete.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

// Icons offered for a category (Font Awesome 6 names).
const PACKAGE_CATEGORY_ICONS = [
  'fa-route' => 'Route', 'fa-plane-arrival' => 'Plane', 'fa-city' => 'City', 'fa-clock' => 'Clock',
  'fa-champagne-glasses' => 'Celebration', 'fa-briefcase' => 'Briefcase', 'fa-om' => 'Pilgrimage (Om)',
  'fa-dharmachakra' => 'Pilgrimage (wheel)', 'fa-mountain-sun' => 'Mountains', 'fa-car' => 'Car', 'fa-van-shuttle' => 'Van',
];

if (isset($_POST['savecategory'])) {
  $id = intval($_POST['savecategory']);
  $name = trim((string)($_POST['categoryname'] ?? ''));
  $icon = (string)($_POST['icon'] ?? 'fa-route');
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
  $clash = $dbh->prepare("SELECT COUNT(*) FROM tblpackagecategories WHERE Slug = :slug AND id <> :id");
  $clash->execute([':slug' => $slug, ':id' => $id]);
  if ($name === '' || mb_strlen($name) > 80) {
    flash_set('error', 'Please enter a category name (up to 80 characters).');
  } elseif ($clash->fetchColumn()) {
    flash_set('error', "A category called \"$name\" already exists.");
  } else {
    $values = [':name' => $name, ':slug' => $slug, ':icon' => isset(PACKAGE_CATEGORY_ICONS[$icon]) ? $icon : 'fa-route',
      ':sort' => (int)($_POST['sortorder'] ?? 0), ':active' => empty($_POST['isactive']) ? 0 : 1];
    if ($id) {
      $dbh->prepare("UPDATE tblpackagecategories SET CategoryName = :name, Slug = :slug, Icon = :icon, SortOrder = :sort, IsActive = :active WHERE id = :id")->execute($values + [':id' => $id]);
    } else {
      $dbh->prepare("INSERT INTO tblpackagecategories (CategoryName, Slug, Icon, SortOrder, IsActive) VALUES (:name, :slug, :icon, :sort, :active)")->execute($values);
    }
    flash_set('success', "Category \"$name\" saved.");
  }
  redirect_to('package-categories.php');
}

if (isset($_POST['delcategory'])) {
  $id = intval($_POST['delcategory']);
  $used = $dbh->prepare("SELECT COUNT(*) FROM tblpackages WHERE CategoryId = :id");
  $used->execute([':id' => $id]);
  if ($count = (int)$used->fetchColumn()) {
    flash_set('error', "This category has $count package(s). Move them to another category first.");
  } else {
    $dbh->prepare("DELETE FROM tblpackagecategories WHERE id = :id")->execute([':id' => $id]);
    flash_set('success', 'Category deleted.');
  }
  redirect_to('package-categories.php');
}

$categories = $dbh->query("SELECT c.*, COUNT(p.id) AS packages, SUM(p.IsActive = 1) AS activePackages FROM tblpackagecategories c
  LEFT JOIN tblpackages p ON p.CategoryId = c.id GROUP BY c.id ORDER BY c.SortOrder, c.CategoryName")->fetchAll(PDO::FETCH_OBJ);
$editing = null;
foreach ($categories as $c) {
  if ((int)$c->id === intval($_GET['edit'] ?? 0)) $editing = $c;
}

admin_page_start('Package Categories', 'package-categories.php', [['Packages', 'manage-packages.php']], [
  'lead' => 'Categories are the filters on the Packages page. Hidden categories hide their packages too.',
]);
?>
<div class="a-grid-2">
  <section class="a-card">
    <div class="a-card__body a-card__body--flush">
      <div class="table-responsive">
        <table class="table a-table mb-0">
          <thead><tr><th class="ps-4">Category</th><th>Packages</th><th>Order</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c) { ?>
              <tr>
                <td class="ps-4"><i class="fa <?php echo h($c->Icon); ?> me-2 text-body-secondary" aria-hidden="true"></i><span class="a-strong"><?php echo h($c->CategoryName); ?></span></td>
                <td><?php echo (int)$c->packages; ?> <span class="a-muted">(<?php echo (int)$c->activePackages; ?> active)</span></td>
                <td><?php echo (int)$c->SortOrder; ?></td>
                <td><?php echo (int)$c->IsActive ? admin_badge('Shown', 'success') : admin_badge('Hidden'); ?></td>
                <td class="text-end pe-4">
                  <div class="a-actions">
                    <?php echo admin_icon_link('package-categories.php?edit=' . (int)$c->id, 'fa-pen', 'Edit category', 'primary'); ?>
                    <?php if ((int)$c->packages) { ?>
                      <button type="button" class="a-icon-btn" aria-disabled="true" data-bs-toggle="tooltip" data-bs-title="Has packages, so it can't be deleted" aria-label="Delete (not possible: has packages)"><i class="fa fa-trash" aria-hidden="true"></i></button>
                    <?php } else {
                      echo admin_action_button(['delcategory' => $c->id], 'fa-trash', 'Delete category', 'danger', 'Delete the category ' . $c->CategoryName . '?');
                    } ?>
                  </div>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="a-card">
    <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-folder-plus" aria-hidden="true"></i><?php echo $editing ? 'Edit ' . h($editing->CategoryName) : 'Add a category'; ?></h2></div>
    <form method="post" class="a-card__body">
      <?php echo csrf_field(); ?>
      <div class="mb-3">
        <label class="form-label" for="categoryname">Name <span class="a-req">*</span></label>
        <input class="form-control" id="categoryname" name="categoryname" maxlength="80" required value="<?php echo h($editing->CategoryName ?? ''); ?>">
      </div>
      <div class="mb-3">
        <label class="form-label" for="icon">Icon</label>
        <select class="form-select" id="icon" name="icon">
          <?php foreach (PACKAGE_CATEGORY_ICONS as $key => $label) { ?><option value="<?php echo $key; ?>"<?php echo ($editing->Icon ?? 'fa-route') === $key ? ' selected' : ''; ?>><?php echo h($label); ?></option><?php } ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="sortorder">Display order</label>
        <input class="form-control" type="number" id="sortorder" name="sortorder" value="<?php echo (int)($editing->SortOrder ?? 100); ?>">
      </div>
      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" id="isactive" name="isactive" value="1"<?php echo !$editing || (int)$editing->IsActive ? ' checked' : ''; ?>>
        <label class="form-check-label" for="isactive">Shown on the website</label>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <?php if ($editing) { ?><a class="btn btn-light" href="package-categories.php">Cancel</a><?php } ?>
        <button class="btn btn-primary" name="savecategory" value="<?php echo (int)($editing->id ?? 0); ?>" type="submit"><i class="fa fa-check" aria-hidden="true"></i><?php echo $editing ? 'Save category' : 'Add category'; ?></button>
      </div>
    </form>
  </section>
</div>
<?php admin_page_end(); ?>
