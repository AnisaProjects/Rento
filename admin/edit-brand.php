<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

$id = intval($_GET['id'] ?? 0);
$query = $dbh->prepare("SELECT * FROM tblbrands WHERE id = :id");
$query->bindParam(':id', $id, PDO::PARAM_INT);
$query->execute();
$brandRow = $query->fetch(PDO::FETCH_OBJ);
if (!$brandRow) {
  flash_set('error', 'That brand no longer exists.');
  redirect_to('manage-brands.php');
}

if (isset($_POST['submit'])) {
  $brand = trim((string)($_POST['brand'] ?? ''));
  $query = $dbh->prepare("SELECT COUNT(*) FROM tblbrands WHERE BrandName = :brand AND id <> :id");
  $query->bindParam(':brand', $brand, PDO::PARAM_STR);
  $query->bindParam(':id', $id, PDO::PARAM_INT);
  $query->execute();
  if ($brand === '') {
    flash_set('error', 'Please enter a brand name.');
  } elseif ($query->fetchColumn() > 0) {
    flash_set('error', "Another brand is already called \"$brand\".");
  } else {
    $query = $dbh->prepare("UPDATE tblbrands SET BrandName = :brand WHERE id = :id");
    $query->bindParam(':brand', $brand, PDO::PARAM_STR);
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    flash_set('success', 'Brand updated.');
    redirect_to('manage-brands.php');
  }
  redirect_to(current_url());
}

admin_page_start('Edit Brand', 'edit-brand.php', [['Brands', 'manage-brands.php']]);
?>
<section class="a-card a-form-narrow">
  <form method="post" class="a-card__body">
    <?php echo csrf_field(); ?>
    <label class="form-label" for="brand">Brand name <span class="a-req">*</span></label>
    <input type="text" class="form-control" value="<?php echo h($brandRow->BrandName); ?>" name="brand" id="brand" maxlength="120" required>
    <div class="form-text">Created <?php echo admin_date($brandRow->CreationDate); ?><?php echo $brandRow->UpdationDate ? ' · last updated ' . admin_date($brandRow->UpdationDate) : ''; ?></div>
    <div class="d-flex gap-2 justify-content-end mt-4">
      <a class="btn btn-light" href="manage-brands.php">Cancel</a>
      <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save changes</button>
    </div>
  </form>
</section>
<?php admin_page_end(); ?>
