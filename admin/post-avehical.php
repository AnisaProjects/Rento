<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/vehicle-form.php';
admin_require_login();

if (isset($_POST['submit'])) {
  [$v, $error] = vehicle_form_input($dbh);
  // Check every image before saving any; images 1-4 are required, image 5 is optional.
  if ($error === '') {
    try {
      foreach ([1, 2, 3, 4, 5] as $n) {
        if ($n === 5 && vehicle_image_missing('img5')) continue;
        validate_vehicle_image("img$n", "Image $n");
      }
      foreach ([1, 2, 3, 4, 5] as $n) {
        $v["Vimage$n"] = ($n === 5 && vehicle_image_missing('img5')) ? '' : store_vehicle_image("img$n");
      }
    } catch (RuntimeException $e) {
      $error = $e->getMessage();
    }
  }
  if ($error === '') {
    $columns = array_keys($v);
    $sql = "INSERT INTO tblvehicles(" . implode(',', $columns) . ") VALUES(:" . implode(',:', $columns) . ")";
    $query = $dbh->prepare($sql);
    foreach ($v as $column => $value) {
      $query->bindValue(':' . $column, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }
    $query->execute();
    $newId = (int)$dbh->lastInsertId();
    if ($newId) {
      forecast_request_recompute($dbh, 'vehicle added: ' . $v['VehiclesTitle']);
      flash_set('success', 'Vehicle posted: ' . $v['VehiclesTitle'] . ' is now listed on the website.');
      redirect_to('edit-vehicle.php?id=' . $newId);
    }
    $error = 'Something went wrong. Please try again.';
  }
  // Shown on this render; the typed details stay in the form (images must be chosen again).
  flash_set('error', $error . (vehicle_image_missing('img1') ? '' : ' Please choose the images again.'));
}

admin_page_start('Post a Vehicle', 'post-avehical.php', [['Vehicles', 'manage-vehicles.php']], [
  'libs' => ['images'],
  'lead' => 'Add a car to the fleet. It appears on the website as soon as you save.',
]);
?>
<form method="post" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <?php vehicle_form_sections($dbh, null); ?>
  <div class="a-form-actions">
    <a class="btn btn-light" href="manage-vehicles.php">Cancel</a>
    <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Post vehicle</button>
  </div>
</form>
<?php admin_page_end(); ?>
