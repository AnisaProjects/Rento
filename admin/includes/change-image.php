<?php
// Replace one vehicle image. changeimage1.php ... changeimage5.php set $imageNumber and
// include this file, so their URLs (?imgid=<vehicle id>) and the img1-img5 fields still work.
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/vehicle-form.php';
admin_require_login();

$n = (int)$imageNumber;
$id = intval($_GET['imgid'] ?? 0);
$query = $dbh->prepare("SELECT v.id, v.VehiclesTitle, v.Vimage$n AS image, br.BrandName FROM tblvehicles v
  LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE v.id = :id");
$query->bindParam(':id', $id, PDO::PARAM_INT);
$query->execute();
$vehicle = $query->fetch(PDO::FETCH_OBJ);
if (!$vehicle) {
  flash_set('error', 'That vehicle no longer exists.');
  redirect_to('manage-vehicles.php');
}

if (isset($_POST['update'])) {
  try {
    replace_vehicle_image($dbh, $id, $n);
    flash_set('success', "Image $n updated.");
    redirect_to('edit-vehicle.php?id=' . $id . '#images');
  } catch (RuntimeException $e) {
    flash_set('error', $e->getMessage());
    redirect_to(current_url());
  }
}

$name = vehicle_display_name($vehicle->BrandName, $vehicle->VehiclesTitle);
admin_page_start("Vehicle Image $n", 'changeimage' . $n . '.php', [['Vehicles', 'manage-vehicles.php'], [$name, 'edit-vehicle.php?id=' . $id]], [
  'libs' => ['images'],
  'lead' => 'Replace image ' . $n . ' of ' . $name . '.',
]);
?>
<form method="post" enctype="multipart/form-data" class="a-card a-form-narrow">
  <div class="a-card__body">
    <?php echo csrf_field(); ?>
    <?php echo vehicle_image_slot($n, $vehicle->image, true, false, true); ?>
    <div class="d-flex gap-2 justify-content-end mt-3">
      <a class="btn btn-light" href="edit-vehicle.php?id=<?php echo $id; ?>#images">Cancel</a>
      <button class="btn btn-primary" name="update" type="submit"><i class="fa fa-upload" aria-hidden="true"></i>Upload image <?php echo $n; ?></button>
    </div>
  </div>
</form>
<?php admin_page_end(); ?>
