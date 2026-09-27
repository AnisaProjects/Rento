<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
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
    $logo = $brandRow->BrandLogo;
    try {
      if (!vehicle_image_missing('brandlogo')) {
        $logo = store_site_image('brandlogo', 'The logo');
      } elseif (!empty($_POST['removelogo'])) {
        $logo = null;
      }
    } catch (RuntimeException $e) {
      flash_set('error', $e->getMessage());
      redirect_to(current_url());
    }
    $query = $dbh->prepare("UPDATE tblbrands SET BrandName = :brand, BrandLogo = :logo WHERE id = :id");
    $query->bindParam(':brand', $brand, PDO::PARAM_STR);
    $query->bindValue(':logo', $logo, $logo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    if ($logo !== $brandRow->BrandLogo) {
      delete_site_image($brandRow->BrandLogo);
    }
    flash_set('success', 'Brand updated.');
    redirect_to('manage-brands.php');
  }
  redirect_to(current_url());
}

admin_page_start('Edit Brand', 'edit-brand.php', [['Brands', 'manage-brands.php']], ['libs' => ['images']]);
?>
<section class="a-card a-form-narrow">
  <form method="post" class="a-card__body" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <label class="form-label" for="brand">Brand name <span class="a-req">*</span></label>
    <input type="text" class="form-control" value="<?php echo h($brandRow->BrandName); ?>" name="brand" id="brand" maxlength="120" required>
    <div class="form-text">Created <?php echo admin_date($brandRow->CreationDate); ?><?php echo $brandRow->UpdationDate ? ' · last updated ' . admin_date($brandRow->UpdationDate) : ''; ?></div>
    <div class="mt-4">
      <?php $logoFile = (string)$brandRow->BrandLogo;
      echo admin_image_field('brandlogo', 'Logo (optional)', $logoFile !== '' && is_file(__DIR__ . '/../' . $logoFile) ? '../' . $logoFile : '',
        'Shown on the home page brand cards. PNG with a transparent background works best.', $logoFile !== '' ? 'removelogo' : ''); ?>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-4">
      <a class="btn btn-light" href="manage-brands.php">Cancel</a>
      <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save changes</button>
    </div>
  </form>
</section>
<?php admin_page_end(); ?>
