<?php
// Packages & services: list, activate/deactivate and delete (edit in package-edit.php).
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
admin_require_login();

if (isset($_POST['togglepackage'])) {
  $id = intval($_POST['togglepackage']);
  $dbh->prepare("UPDATE tblpackages SET IsActive = 1 - IsActive WHERE id = :id")->execute([':id' => $id]);
  $row = $dbh->prepare("SELECT Title, IsActive FROM tblpackages WHERE id = :id");
  $row->execute([':id' => $id]);
  $row = $row->fetch(PDO::FETCH_OBJ);
  flash_set($row ? 'success' : 'error', $row ? $row->Title . ($row->IsActive ? ' is now active on the website.' : ' is now hidden from the website.') : 'That package no longer exists.');
  redirect_to(current_url());
}

if (isset($_POST['delpackage'])) {
  $id = intval($_POST['delpackage']);
  $row = $dbh->prepare("SELECT Title, CoverImage FROM tblpackages WHERE id = :id");
  $row->execute([':id' => $id]);
  $row = $row->fetch(PDO::FETCH_OBJ);
  if ($row) {
    $images = $dbh->prepare("SELECT ImagePath FROM tblpackageimages WHERE PackageId = :id");
    $images->execute([':id' => $id]);
    $dbh->beginTransaction();
    $dbh->prepare("DELETE FROM tblpackageimages WHERE PackageId = :id")->execute([':id' => $id]);
    // Enquiries are kept; they still show the package title they were sent for.
    $dbh->prepare("UPDATE tblpackageenquiries SET PackageId = NULL WHERE PackageId = :id")->execute([':id' => $id]);
    $dbh->prepare("DELETE FROM tblpackages WHERE id = :id")->execute([':id' => $id]);
    $dbh->commit();
    foreach (array_merge([$row->CoverImage], $images->fetchAll(PDO::FETCH_COLUMN)) as $path) {
      delete_site_image($path);
    }
    flash_set('success', 'Package ' . $row->Title . ' deleted. Its enquiries were kept.');
  } else {
    flash_set('error', 'That package no longer exists.');
  }
  redirect_to(current_url());
}

$packages = $dbh->query("SELECT p.id, p.Title, p.DurationLabel, p.StartingPrice, p.CoverImage, p.SortOrder, p.IsActive, p.PricesJson,
    c.CategoryName, c.IsActive AS CategoryActive,
    (SELECT COUNT(*) FROM tblpackageenquiries e WHERE e.PackageId = p.id) AS enquiries,
    (SELECT COUNT(*) FROM tblpackageenquiries e WHERE e.PackageId = p.id AND e.Status = 'new') AS newEnquiries
  FROM tblpackages p LEFT JOIN tblpackagecategories c ON c.id = p.CategoryId ORDER BY p.SortOrder, p.Title")->fetchAll(PDO::FETCH_OBJ);
$active = count(array_filter($packages, fn($p) => (int)$p->IsActive === 1));

admin_page_start('Manage Packages', 'manage-packages.php', [['Packages', 'manage-packages.php']], [
  'libs' => ['datatables'],
  'lead' => count($packages) . ' packages, ' . $active . ' active on the website.' . ($active ? '' : ' The Packages page and menu link appear once one is active.'),
  'actions' => '<a class="btn btn-light" href="package-categories.php"><i class="fa fa-folder-tree" aria-hidden="true"></i>Categories</a>'
    . '<a class="btn btn-primary" href="package-edit.php"><i class="fa fa-plus" aria-hidden="true"></i>Add package</a>',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search packages" data-empty="No packages yet" data-order='[[4,"asc"]]'>
      <thead>
        <tr>
          <th data-priority="1">Package</th>
          <th>Category</th>
          <th>Duration</th>
          <th data-priority="3">From</th>
          <th>Order</th>
          <th>Enquiries</th>
          <th data-priority="2">Status</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($packages as $p) {
          $cover = package_cover($p);
          $vehicleTypes = implode(', ', array_keys(package_prices($p))); ?>
          <tr>
            <td>
              <div class="a-vehicle-cell">
                <img class="a-thumb" src="../<?php echo h($cover); ?>" alt="" width="72" height="48" loading="lazy">
                <div>
                  <a class="a-strong text-decoration-none" href="package-edit.php?id=<?php echo (int)$p->id; ?>"><?php echo h($p->Title); ?></a>
                  <div class="a-muted"><?php echo h($vehicleTypes ?: 'No prices set'); ?></div>
                </div>
              </div>
            </td>
            <td><?php echo $p->CategoryName !== null ? h($p->CategoryName) . ((int)$p->CategoryActive ? '' : ' ' . admin_badge('Hidden category', 'warning')) : '<span class="a-muted">None</span>'; ?></td>
            <td><?php echo h($p->DurationLabel); ?></td>
            <td data-order="<?php echo (int)$p->StartingPrice; ?>"><span class="a-money"><?php echo format_price($p->StartingPrice); ?></span></td>
            <td><?php echo (int)$p->SortOrder; ?></td>
            <td data-order="<?php echo (int)$p->enquiries; ?>">
              <?php if ((int)$p->enquiries) { ?><a href="package-enquiries.php?package=<?php echo (int)$p->id; ?>"><?php echo (int)$p->enquiries; ?></a><?php echo (int)$p->newEnquiries ? ' ' . admin_badge((int)$p->newEnquiries . ' new', 'primary') : ''; ?><?php } else { ?><span class="a-muted">0</span><?php } ?>
            </td>
            <td data-order="<?php echo (int)$p->IsActive; ?>"><?php echo (int)$p->IsActive ? admin_badge('Active', 'success', 'fa-eye') : admin_badge('Inactive', 'neutral', 'fa-eye-slash'); ?></td>
            <td>
              <div class="a-actions">
                <?php echo (int)$p->IsActive ? admin_icon_link('../' . package_url($p->id, $p->Title), 'fa-arrow-up-right-from-square', 'View on website') : ''; ?>
                <?php echo admin_icon_link('package-edit.php?id=' . (int)$p->id, 'fa-pen', 'Edit package', 'primary'); ?>
                <?php echo (int)$p->IsActive
                  ? admin_action_button(['togglepackage' => $p->id], 'fa-eye-slash', 'Make inactive', '', 'Hide ' . $p->Title . ' from the website?')
                  : admin_action_button(['togglepackage' => $p->id], 'fa-eye', 'Make active', 'success', 'Show ' . $p->Title . ' on the website? Check its prices first.'); ?>
                <?php echo admin_action_button(['delpackage' => $p->id], 'fa-trash', 'Delete package', 'danger', 'Delete ' . $p->Title . ' and its photos? Enquiries for it are kept.'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
