<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['submit'])) {
  $address = trim((string)($_POST['address'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $contactno = trim((string)($_POST['contactno'] ?? ''));
  if ($address === '' || $contactno === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please fill in the address, a valid email and a contact number.');
  } else {
    $query = $dbh->prepare("UPDATE tblcontactusinfo SET Address = :address, EmailId = :email, ContactNo = :contactno");
    $query->bindParam(':address', $address, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':contactno', $contactno, PDO::PARAM_STR);
    $query->execute();
    flash_set('success', 'Contact info updated. The website header, footer and contact page now show it.');
  }
  redirect_to(current_url());
}

$info = $dbh->query("SELECT * FROM tblcontactusinfo LIMIT 1")->fetch(PDO::FETCH_OBJ);

admin_page_start('Update Contact Info', 'update-contactinfo.php', [], [
  'lead' => 'Shown in the website header, footer and on the contact page.',
  'actions' => '<a class="btn btn-light" href="../contact-us" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View contact page</a>',
]);
?>
<section class="a-card a-form-narrow">
  <form method="post" name="chngpwd" class="a-card__body">
    <?php echo csrf_field(); ?>
    <div class="mb-3">
      <label class="form-label" for="address">Address <span class="a-req">*</span></label>
      <textarea class="form-control" name="address" id="address" rows="3" required><?php echo h($info->Address ?? ''); ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email <span class="a-req">*</span></label>
      <input type="email" class="form-control" name="email" id="email" value="<?php echo h($info->EmailId ?? ''); ?>" autocomplete="email" required>
    </div>
    <div class="mb-4">
      <label class="form-label" for="contactno">Contact number <span class="a-req">*</span></label>
      <input type="tel" class="form-control" name="contactno" id="contactno" value="<?php echo h($info->ContactNo ?? ''); ?>" maxlength="11" inputmode="tel" required>
      <div class="form-text">Up to 11 digits.</div>
    </div>
    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save contact info</button>
    </div>
  </form>
</section>
<?php admin_page_end(); ?>
