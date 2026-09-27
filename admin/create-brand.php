<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
admin_require_login();

if (isset($_POST['submit'])) {
  $brand = trim((string)($_POST['brand'] ?? ''));
  $query = $dbh->prepare("SELECT COUNT(*) FROM tblbrands WHERE BrandName = :brand");
  $query->bindParam(':brand', $brand, PDO::PARAM_STR);
  $query->execute();
  if ($brand === '') {
    flash_set('error', 'Please enter a brand name.');
    redirect_to(current_url());
  } elseif ($query->fetchColumn() > 0) {
    flash_set('error', "The brand \"$brand\" already exists.");
    redirect_to(current_url());
  }
  $logo = null;
  if (!vehicle_image_missing('brandlogo')) {
    try {
      $logo = store_site_image('brandlogo', 'The logo');
    } catch (RuntimeException $e) {
      flash_set('error', $e->getMessage());
      redirect_to(current_url());
    }
  }
  $query = $dbh->prepare("INSERT INTO tblbrands(BrandName, BrandLogo) VALUES(:brand, :logo)");
  $query->bindParam(':brand', $brand, PDO::PARAM_STR);
  $query->bindValue(':logo', $logo, $logo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
  $query->execute();
  if ($dbh->lastInsertId()) {
    flash_set('success', "Brand \"$brand\" created.");
    redirect_to('manage-brands.php');
  }
  flash_set('error', 'Something went wrong. Please try again.');
  redirect_to(current_url());
}

admin_page_start('Create Brand', 'create-brand.php', [['Brands', 'manage-brands.php']], ['libs' => ['images']]);
?>
<section class="a-card a-form-narrow">
  <form method="post" class="a-card__body" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <label class="form-label" for="brand">Brand name <span class="a-req">*</span></label>
    <input type="text" class="form-control" name="brand" id="brand" maxlength="120" required autofocus>
    <div class="form-text">As customers will see it, for example "Toyota".</div>
    <div class="mt-4">
      <?php echo admin_image_field('brandlogo', 'Logo (optional)', '', 'Shown on the home page brand cards. PNG with a transparent background works best.'); ?>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-4">
      <a class="btn btn-light" href="manage-brands.php">Cancel</a>
      <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-plus" aria-hidden="true"></i>Create brand</button>
    </div>
  </form>
</section>
<?php admin_page_end(); ?>
