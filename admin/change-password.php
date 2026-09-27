<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['submit'])) {
  $username = $_SESSION['alogin'];
  $query = $dbh->prepare("SELECT id, Password FROM admin WHERE UserName = :username");
  $query->bindParam(':username', $username, PDO::PARAM_STR);
  $query->execute();
  $admin = $query->fetch(PDO::FETCH_OBJ);
  $new = (string)($_POST['newpassword'] ?? '');
  if ($new !== (string)($_POST['confirmpassword'] ?? '')) {
    flash_set('error', 'New password and confirm password do not match.');
  } elseif (strlen($new) < ADMIN_PASSWORD_MIN) {
    flash_set('error', 'The new password must be at least ' . ADMIN_PASSWORD_MIN . ' characters.');
  } elseif ($admin && password_check_and_upgrade((string)($_POST['password'] ?? ''), $admin->Password, function () {})) {
    $newpassword = password_hash_for_storage($new);
    $query = $dbh->prepare("UPDATE admin SET Password = :newpassword WHERE id = :id");
    $query->bindParam(':id', $admin->id, PDO::PARAM_INT);
    $query->bindParam(':newpassword', $newpassword, PDO::PARAM_STR);
    $query->execute();
    // Any reset links still open for this admin stop working.
    $dbh->prepare("UPDATE tblpasswordresets SET UsedAt = NOW() WHERE AccountType = 'admin' AND UserId = :id AND UsedAt IS NULL")->execute([':id' => $admin->id]);
    flash_set('success', 'Your password has been changed.');
  } else {
    flash_set('error', 'Your current password is not correct.');
  }
  redirect_to(current_url());
}

$passwordField = function (string $name, string $label, string $autocomplete, string $hint = '') {
  ?>
  <div class="mb-3">
    <label class="form-label" for="<?php echo $name; ?>"><?php echo h($label); ?></label>
    <div class="a-pass">
      <input type="password" class="form-control" name="<?php echo $name; ?>" id="<?php echo $name; ?>" autocomplete="<?php echo $autocomplete; ?>" required<?php echo $name !== 'password' ? ' minlength="' . ADMIN_PASSWORD_MIN . '"' : ''; ?>>
      <button type="button" data-a-pass="<?php echo $name; ?>" aria-label="Show password"><i class="fa fa-eye" aria-hidden="true"></i></button>
    </div>
    <?php if ($hint) { ?><div class="form-text"><?php echo h($hint); ?></div><?php } ?>
  </div>
  <?php
};

admin_page_start('Change Password', 'change-password.php', [], ['lead' => 'Signed in as ' . $_SESSION['alogin'] . '.']);
?>
<section class="a-card a-form-narrow">
  <form method="post" name="chngpwd" class="a-card__body" id="chngpwd">
    <?php echo csrf_field(); ?>
    <?php $passwordField('password', 'Current password', 'current-password'); ?>
    <?php $passwordField('newpassword', 'New password', 'new-password', 'At least ' . ADMIN_PASSWORD_MIN . ' characters.'); ?>
    <?php $passwordField('confirmpassword', 'Confirm new password', 'new-password'); ?>
    <div class="form-text mb-3" id="pw-match" aria-live="polite"></div>
    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="dashboard.php">Cancel</a>
      <button class="btn btn-primary" name="submit" type="submit"><i class="fa fa-key" aria-hidden="true"></i>Change password</button>
    </div>
  </form>
</section>
<?php admin_page_end(<<<'JS'
(function () {
  var form = document.getElementById('chngpwd');
  var next = form.newpassword, confirm = form.confirmpassword, hint = document.getElementById('pw-match');
  function check() {
    var ok = !confirm.value || next.value === confirm.value;
    confirm.setCustomValidity(ok ? '' : 'The passwords do not match.');
    hint.textContent = confirm.value ? (ok ? 'Passwords match.' : 'Passwords do not match.') : '';
    hint.className = 'form-text mb-3 ' + (ok ? 'text-success' : 'text-danger');
  }
  next.addEventListener('input', check);
  confirm.addEventListener('input', check);
})();
JS); ?>
