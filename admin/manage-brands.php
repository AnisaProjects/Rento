<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
admin_require_login();

if (isset($_POST['del'])) {
  $id = intval($_POST['del']);
  // A brand still used by vehicles is kept, so those vehicles don't lose their brand.
  $query = $dbh->prepare("SELECT COUNT(*) FROM tblvehicles WHERE VehiclesBrand = :id");
  $query->bindParam(':id', $id, PDO::PARAM_INT);
  $query->execute();
  $vehicleCount = (int)$query->fetchColumn();
  if ($vehicleCount > 0) {
    flash_set('error', "This brand is used by $vehicleCount vehicle(s). Change or delete those vehicles first.");
  } else {
    $logo = $dbh->prepare("SELECT BrandLogo FROM tblbrands WHERE id = :id");
    $logo->execute([':id' => $id]);
    $logo = $logo->fetchColumn();
    $query = $dbh->prepare("DELETE FROM tblbrands WHERE id = :id");
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    if ($query->rowCount()) {
      delete_site_image($logo ?: null);
    }
    flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Brand deleted.' : 'That brand no longer exists.');
  }
  redirect_to(current_url());
}

$brands = $dbh->query("SELECT br.id, br.BrandName, br.BrandLogo, br.CreationDate, br.UpdationDate,
    (SELECT COUNT(*) FROM tblvehicles v WHERE v.VehiclesBrand = br.id) AS vehicles
  FROM tblbrands br ORDER BY br.BrandName")->fetchAll(PDO::FETCH_OBJ);

admin_page_start('Manage Brands', 'manage-brands.php', [['Brands', 'manage-brands.php']], [
  'libs' => ['datatables'],
  'lead' => count($brands) . ' brands.',
  'actions' => '<a class="btn btn-primary" href="create-brand.php"><i class="fa fa-plus" aria-hidden="true"></i>Create brand</a>',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search brands" data-empty="No brands yet" data-order='[[1,"asc"]]'>
      <thead>
        <tr>
          <th>#</th>
          <th data-priority="1">Brand</th>
          <th>Vehicles</th>
          <th>Created</th>
          <th>Updated</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($brands as $brand) { ?>
          <tr>
            <td class="a-muted"><?php echo (int)$brand->id; ?></td>
            <td>
              <div class="a-vehicle-cell">
                <?php if ($brand->BrandLogo && is_file(__DIR__ . '/../' . $brand->BrandLogo)) { ?>
                  <img class="a-thumb" style="object-fit:contain;background:#fff;border:1px solid var(--rento-gray-200)" src="../<?php echo h($brand->BrandLogo); ?>" alt="" width="72" height="48">
                <?php } else { ?><span class="a-thumb a-thumb--empty" aria-hidden="true"><i class="fa fa-tag"></i></span><?php } ?>
                <span class="a-strong"><?php echo h($brand->BrandName); ?></span>
              </div>
            </td>
            <td><?php echo (int)$brand->vehicles; ?></td>
            <?php echo admin_date_cell($brand->CreationDate); ?>
            <?php echo admin_date_cell($brand->UpdationDate); ?>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('../car-listing?brand=' . (int)$brand->id, 'fa-eye', 'View cars on website'); ?>
                <?php echo admin_icon_link('edit-brand.php?id=' . (int)$brand->id, 'fa-pen', 'Edit brand', 'primary'); ?>
                <?php if ((int)$brand->vehicles > 0) { ?>
                  <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Used by vehicles, so it can't be deleted"><button type="button" class="a-icon-btn" disabled aria-label="Delete (not possible: used by vehicles)"><i class="fa fa-trash" aria-hidden="true"></i></button></span>
                <?php } else {
                  echo admin_action_button(['del' => $brand->id], 'fa-trash', 'Delete brand', 'danger', 'Delete the brand ' . $brand->BrandName . '?');
                } ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
