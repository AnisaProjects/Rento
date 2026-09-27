<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

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
    $query = $dbh->prepare("DELETE FROM tblvehicles WHERE id = :delid");
    $query->bindParam(':delid', $delid, PDO::PARAM_INT);
    $query->execute();
    flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Vehicle deleted.' : 'That vehicle no longer exists.');
  }
  redirect_to(current_url());
}

$vehicles = $dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.CarType, v.Vimage1,
    br.BrandName, (SELECT COUNT(*) FROM tblbooking b WHERE b.VehicleId = v.id) AS bookings
  FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand ORDER BY v.id DESC")->fetchAll(PDO::FETCH_OBJ);

admin_page_start('Manage Vehicles', 'manage-vehicles.php', [['Vehicles', 'manage-vehicles.php']], [
  'libs' => ['datatables'],
  'lead' => count($vehicles) . ' vehicles in the fleet.',
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
                  <div class="a-muted"><?php echo h($v->CarType ?: 'No type set'); ?></div>
                </div>
              </div>
            </td>
            <td><?php echo $v->BrandName !== null ? h($v->BrandName) : admin_badge('No brand', 'danger'); ?></td>
            <td data-order="<?php echo (int)$v->PricePerDay; ?>"><span class="a-money"><?php echo format_price($v->PricePerDay); ?></span></td>
            <td><?php echo h($v->FuelType); ?></td>
            <td><?php echo (int)$v->SeatingCapacity; ?></td>
            <td><?php echo h($v->ModelYear); ?></td>
            <td><?php echo (int)$v->bookings; ?></td>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('../' . vehicle_url($v->id, $v->BrandName, $v->VehiclesTitle), 'fa-eye', 'View on website'); ?>
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
