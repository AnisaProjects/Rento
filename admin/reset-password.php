<?php
// Admin password reset, step 2: set a new password with the link from forgot-password.php.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';

const ADMIN_RESET_INVALID = 'This reset link is invalid, has expired or was already used. Please request a new one.';

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');

// The open admin reset request for a valid, unused, unexpired token, or false.
function admin_find_reset(PDO $dbh, string $token)
{
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return false;
  }
  $query = $dbh->prepare("SELECT id, UserId FROM tblpasswordresets
    WHERE TokenHash = :hash AND AccountType = 'admin' AND UsedAt IS NULL AND ExpiresAt > NOW()");
  $query->execute([':hash' => hash('sha256', $token)]);
  return $query->fetch(PDO::FETCH_OBJ);
}

$reset = admin_find_reset($dbh, $token);

if (isset($_POST['resetpassword'])) {
  $new = (string)($_POST['newpassword'] ?? '');
  if (!$reset) {
    flash_set('error', ADMIN_RESET_INVALID);
    redirect_to('forgot-password.php');
  } elseif ($new !== (string)($_POST['confirmpassword'] ?? '')) {
    flash_set('error', 'New password and confirm password do not match.');
  } elseif (strlen($new) < ADMIN_PASSWORD_MIN) {
    flash_set('error', 'The new password must be at least ' . ADMIN_PASSWORD_MIN . ' characters.');
  } else {
    $dbh->beginTransaction();
    $dbh->prepare("UPDATE admin SET Password = :password WHERE id = :id")
      ->execute([':password' => password_hash_for_storage($new), ':id' => $reset->UserId]);
    $dbh->prepare("UPDATE tblpasswordresets SET UsedAt = NOW() WHERE AccountType = 'admin' AND UserId = :id AND UsedAt IS NULL")
      ->execute([':id' => $reset->UserId]);
    $dbh->commit();
    flash_set('success', 'Your password has been changed. Please sign in with the new password.');
    redirect_to('index.php');
  }
  redirect_to('reset-password.php?token=' . $token);
}

admin_auth_start('Set a new password');
?>
<h1>Set a new password</h1>
<?php if ($reset) { ?>
  <p class="a-muted mb-4">Choose a new password for the admin panel.</p>
  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="token" value="<?php echo h($token); ?>">
    <?php echo admin_password_input('newpassword', 'New password', 'new-password', ADMIN_PASSWORD_MIN, 'At least ' . ADMIN_PASSWORD_MIN . ' characters.'); ?>
    <?php echo admin_password_input('confirmpassword', 'Confirm new password', 'new-password', ADMIN_PASSWORD_MIN); ?>
    <button class="btn btn-primary w-100 btn-lg mt-2" name="resetpassword" type="submit"><i class="fa fa-key" aria-hidden="true"></i>Save new password</button>
  </form>
<?php } else { ?>
  <div class="a-notice a-notice--error mb-3" role="alert"><i class="fa fa-circle-exclamation mt-1" aria-hidden="true"></i><div><?php echo h(ADMIN_RESET_INVALID); ?></div></div>
  <a class="btn btn-primary w-100" href="forgot-password.php">Request a new link</a>
<?php } ?>
<div class="a-auth__links"><a href="index.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to sign in</a></div>
<?php admin_auth_end(); ?>
