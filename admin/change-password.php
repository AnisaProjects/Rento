<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

// New form: the account's email address (password reset links go there once email is set up).
// Field names adminemail + emailpassword; changing it asks for the current password.
if (isset($_POST['saveemail'])) {
  $admin = $dbh->prepare("SELECT id, Password FROM admin WHERE UserName = :username");
  $admin->execute([':username' => $_SESSION['alogin']]);
  $admin = $admin->fetch(PDO::FETCH_OBJ);
  $email = trim((string)($_POST['adminemail'] ?? ''));
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address, or leave it empty.');
  } elseif (!$admin || !password_check_and_upgrade((string)($_POST['emailpassword'] ?? ''), $admin->Password, function () {})) {
    flash_set('error', 'Your current password is not correct.');
  } else {
    $dbh->prepare("UPDATE admin SET Email = :email WHERE id = :id")->execute([':email' => $email !== '' ? mb_substr($email, 0, 150) : null, ':id' => $admin->id]);
    flash_set('success', $email !== '' ? 'Account email saved.' : 'Account email removed.');
  }
  redirect_to(current_url());
}

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

$accountEmail = $dbh->prepare("SELECT Email FROM admin WHERE UserName = :username");
$accountEmail->execute([':username' => $_SESSION['alogin']]);
$accountEmail = (string)$accountEmail->fetchColumn();

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
<section class="a-card a-form-narrow mt-4" aria-labelledby="account-email-title">
  <div class="a-card__head"><h2 class="a-card__title" id="account-email-title"><i class="fa fa-envelope" aria-hidden="true"></i>Account email</h2></div>
  <form method="post" class="a-card__body">
    <?php echo csrf_field(); ?>
    <p class="a-muted">Where password reset links are sent once email is set up (<a href="email-settings.php">Email</a>). Without it, a reset link can only be shown on screen in development mode.</p>
    <div class="mb-3">
      <label class="form-label" for="adminemail">Email address</label>
      <input type="email" class="form-control" name="adminemail" id="adminemail" maxlength="150" autocomplete="email" value="<?php echo h($accountEmail); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label" for="emailpassword">Current password</label>
      <div class="a-pass">
        <input type="password" class="form-control" name="emailpassword" id="emailpassword" autocomplete="current-password" required>
        <button type="button" data-a-pass="emailpassword" aria-label="Show password"><i class="fa fa-eye" aria-hidden="true"></i></button>
      </div>
      <div class="form-text">Needed to change where reset links go.</div>
    </div>
    <div class="d-flex justify-content-end">
      <button class="btn btn-primary" name="saveemail" type="submit"><i class="fa fa-floppy-disk" aria-hidden="true"></i>Save email</button>
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
