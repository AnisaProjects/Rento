<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
admin_require_login();

// Quick show/hide from the list (new field toggleactive = vehicle id).
if (isset($_POST['toggleactive'])) {
  $id = intval($_POST['toggleactive']);
  $query = $dbh->prepare("UPDATE tblvehicles SET IsActive = 1 - IsActive WHERE id = :id");
  $query->execute([':id' => $id]);
  $state = $dbh->prepare("SELECT IsActive, VehiclesTitle FROM tblvehicles WHERE id = :id");
  $state->execute([':id' => $id]);
  $row = $state->fetch(PDO::FETCH_OBJ);
  if ($row) forecast_request_recompute($dbh, 'vehicle ' . ($row->IsActive ? 'activated' : 'deactivated') . ': ' . $row->VehiclesTitle);
  flash_set($row ? 'success' : 'error', $row ? $row->VehiclesTitle . ($row->IsActive ? ' is now active and shown on the website.' : ' is now inactive and hidden from the website.') : 'That vehicle no longer exists.');
  redirect_to(current_url());
}

if (isset($_POST['del'])) {
  $delid = intval($_POST['del']);
  // Vehicles with bookings are kept so the booking history keeps its vehicle.
  $query = $dbh->prepare("SELECT COUNT(*) FROM tblbooking WHERE VehicleId = :id");
  $query->bindParam(':id', $delid, PDO::PARAM_INT);
  $query->execute();
  $bookingCount = (int)$query->fetchColumn();
  if ($bookingCount > 0) {
    flash_set('error', "This vehicle has $bookingCount booking(s), so it can't be deleted.");
  } else {
    $query = $dbh->prepare("SELECT Vimage1, Vimage2, Vimage3, Vimage4, Vimage5 FROM tblvehicles WHERE id = :delid");
    $query->bindParam(':delid', $delid, PDO::PARAM_INT);
    $query->execute();
    $images = array_filter((array)$query->fetch(PDO::FETCH_NUM));
    $query = $dbh->prepare("DELETE FROM tblvehicles WHERE id = :delid");
    $query->bindParam(':delid', $delid, PDO::PARAM_INT);
    $query->execute();
    if ($query->rowCount()) {
      delete_unused_vehicle_images($dbh, $images);
      forecast_request_recompute($dbh, 'vehicle deleted');
    }
    flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Vehicle deleted.' : 'That vehicle no longer exists.');
  }
  redirect_to(current_url());
}

$vehicles = $dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.CarType, v.IsActive, v.DriverOnly, v.Vimage1,
    br.BrandName, (SELECT COUNT(*) FROM tblbooking b WHERE b.VehicleId = v.id) AS bookings
  FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand ORDER BY v.id DESC")->fetchAll(PDO::FETCH_OBJ);

admin_page_start('Manage Vehicles', 'manage-vehicles.php', [['Vehicles', 'manage-vehicles.php']], [
  'libs' => ['datatables'],
  'lead' => count($vehicles) . ' vehicles, ' . count(array_filter($vehicles, fn($v) => (int)$v->IsActive === 1)) . ' active on the website.',
  'actions' => '<a class="btn btn-primary" href="post-avehical.php"><i class="fa fa-plus" aria-hidden="true"></i>Post a vehicle</a>',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search vehicles" data-empty="No vehicles yet" data-order='[[0,"desc"]]'>
      <thead>
        <tr>
          <th>#</th>
          <th data-priority="1">Vehicle</th>
          <th>Brand</th>
          <th data-priority="2">Price per day</th>
          <th>Fuel</th>
          <th>Seats</th>
          <th>Year</th>
          <th>Bookings</th>
          <th data-priority="2">Status</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vehicles as $v) {
          $name = vehicle_display_name($v->BrandName, $v->VehiclesTitle); ?>
          <tr>
            <td class="a-muted"><?php echo (int)$v->id; ?></td>
            <td>
              <div class="a-vehicle-cell">
                <?php echo admin_vehicle_thumb($v->Vimage1); ?>
                <div>
                  <a class="a-strong text-decoration-none" href="edit-vehicle.php?id=<?php echo (int)$v->id; ?>"><?php echo h($v->VehiclesTitle); ?></a>
                  <div class="a-muted"><?php echo h($v->CarType ?: 'No type set'); ?><?php echo (int)$v->DriverOnly ? ' · with driver only' : ''; ?></div>
                </div>
              </div>
            </td>
            <td><?php echo $v->BrandName !== null ? h($v->BrandName) : admin_badge('No brand', 'danger'); ?></td>
            <td data-order="<?php echo (int)$v->PricePerDay; ?>"><span class="a-money"><?php echo format_price($v->PricePerDay); ?></span></td>
            <td><?php echo h($v->FuelType); ?></td>
            <td><?php echo (int)$v->SeatingCapacity; ?></td>
            <td><?php echo h($v->ModelYear); ?></td>
            <td><?php echo (int)$v->bookings; ?></td>
            <td data-order="<?php echo (int)$v->IsActive; ?>"><?php echo (int)$v->IsActive ? admin_badge('Active', 'success', 'fa-eye') : admin_badge('Inactive', 'neutral', 'fa-eye-slash'); ?></td>
            <td>
              <div class="a-actions">
                <?php echo (int)$v->IsActive
                  ? admin_icon_link('../' . vehicle_url($v->id, $v->BrandName, $v->VehiclesTitle), 'fa-arrow-up-right-from-square', 'View on website')
                  : ''; ?>
                <?php echo (int)$v->IsActive
                  ? admin_action_button(['toggleactive' => $v->id], 'fa-eye-slash', 'Make inactive', '', 'Hide ' . $name . ' from the website and Find My Car?')
                  : admin_action_button(['toggleactive' => $v->id], 'fa-eye', 'Make active', 'success', 'Show ' . $name . ' on the website?'); ?>
                <?php echo admin_icon_link('edit-vehicle.php?id=' . (int)$v->id, 'fa-pen', 'Edit vehicle', 'primary'); ?>
                <?php echo admin_icon_link('edit-vehicle.php?id=' . (int)$v->id . '#images', 'fa-images', 'Manage images'); ?>
                <?php if ((int)$v->bookings > 0) { ?>
                  <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Has bookings, so it can't be deleted"><button type="button" class="a-icon-btn" disabled aria-label="Delete (not possible: has bookings)"><i class="fa fa-trash" aria-hidden="true"></i></button></span>
                <?php } else {
                  echo admin_action_button(['del' => $v->id], 'fa-trash', 'Delete vehicle', 'danger', 'Delete ' . $name . '? This can\'t be undone.');
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
