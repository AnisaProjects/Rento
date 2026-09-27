<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/vehicle-form.php';
admin_require_login();

$id = intval($_GET['id'] ?? 0);
$loadVehicle = function () use ($dbh, $id) {
  $query = $dbh->prepare("SELECT v.*, br.BrandName, (SELECT COUNT(*) FROM tblbooking b WHERE b.VehicleId = v.id) AS bookings
    FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE v.id = :id");
  $query->bindParam(':id', $id, PDO::PARAM_INT);
  $query->execute();
  return $query->fetch(PDO::FETCH_OBJ);
};
$vehicle = $loadVehicle();
if (!$vehicle) {
  flash_set('error', 'That vehicle no longer exists.');
  redirect_to('manage-vehicles.php');
}

if (isset($_POST['submit'])) {
  [$v, $error] = vehicle_form_input($dbh);
  // Replaced images: check them all before changing anything.
  $replace = [];
  if ($error === '') {
    try {
      for ($n = 1; $n <= 5; $n++) {
        if (!vehicle_image_missing("img$n")) {
          validate_vehicle_image("img$n", "Image $n");
          $replace[] = $n;
        }
      }
    } catch (RuntimeException $e) {
      $error = $e->getMessage();
    }
  }
  if ($error === '') {
    $sets = implode(', ', array_map(fn($column) => "$column = :$column", array_keys($v)));
    $query = $dbh->prepare("UPDATE tblvehicles SET $sets WHERE id = :id");
    foreach ($v as $column => $value) {
      $query->bindValue(':' . $column, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }
    $query->bindValue(':id', $id, PDO::PARAM_INT);
    $query->execute();
    foreach ($replace as $n) {
      replace_vehicle_image($dbh, $id, $n);
    }
    // Image 5 is optional and can be removed (the file stays on disk).
    if (!empty($_POST['removeimg5']) && !in_array(5, $replace, true)) {
      $dbh->prepare("UPDATE tblvehicles SET Vimage5 = '' WHERE id = :id")->execute([':id' => $id]);
      delete_unused_vehicle_images($dbh, [(string)$vehicle->Vimage5]);
    }
    $notes = [];
    if ($replace) $notes[] = count($replace) === 1 ? 'image ' . $replace[0] . ' replaced' : count($replace) . ' images replaced';
    if (!empty($_POST['removeimg5']) && !in_array(5, $replace, true)) $notes[] = 'image 5 removed';
    flash_set('success', 'Vehicle saved' . ($notes ? ' (' . implode(', ', $notes) . ')' : '') . '.');
    redirect_to(current_url());
  }
  flash_set('error', $error);
}

$name = vehicle_display_name($vehicle->BrandName, $vehicle->VehiclesTitle);
admin_page_start('Edit Vehicle', 'edit-vehicle.php', [['Vehicles', 'manage-vehicles.php']], [
  'libs' => ['images'],
  'lead' => $name . ' · ' . (int)$vehicle->bookings . ' booking' . ((int)$vehicle->bookings === 1 ? '' : 's') . ' · last updated ' . (admin_date($vehicle->UpdationDate ?: $vehicle->RegDate) ?: 'never'),
  'actions' => '<a class="btn btn-light" href="../' . h(vehicle_url($vehicle->id, $vehicle->BrandName, $vehicle->VehiclesTitle)) . '" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View on website</a>',
]);
?>
<form method="post" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <?php vehicle_form_sections($dbh, $vehicle); ?>
  <p class="a-muted mt-3 mb-0">You can also replace one image on its own page:
    <?php for ($n = 1; $n <= 5; $n++) { ?><a href="changeimage<?php echo $n; ?>.php?imgid=<?php echo (int)$vehicle->id; ?>">image <?php echo $n; ?></a><?php echo $n < 5 ? ', ' : '.'; ?><?php } ?>
  </p>
  <div class="a-form-actions">
    <a class="btn btn-light" href="manage-vehicles.php">Back to vehicles</a>
    <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save changes</button>
  </div>
</form>
<?php admin_page_end(); ?>
