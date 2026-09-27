<?php
// Admin password reset, step 1: ask for the username and create a time-limited link.
// Uses tblpasswordresets (AccountType = 'admin') and the Phase 0 rate limiting.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';

const ADMIN_RESET_TTL_MINUTES = 30;

$sent = false;
$devResetLink = '';
if (isset($_POST['request'])) {
  $username = trim((string)($_POST['username'] ?? ''));
  if (rate_limit_exceeded($dbh, 'admin_password_reset', $username)) {
    flash_set('error', RATE_LIMIT_MESSAGE);
    redirect_to(current_url());
  }
  // Every request counts, not only failures.
  rate_limit_record($dbh, 'admin_password_reset', $username);
  $query = $dbh->prepare("SELECT id FROM admin WHERE UserName = :username");
  $query->bindParam(':username', $username, PDO::PARAM_STR);
  $query->execute();
  $admin = $query->fetch(PDO::FETCH_OBJ);
  if ($admin) {
    // Only the newest link works.
    $dbh->prepare("UPDATE tblpasswordresets SET UsedAt = NOW() WHERE AccountType = 'admin' AND UserId = :id AND UsedAt IS NULL")->execute([':id' => $admin->id]);
    $token = bin2hex(random_bytes(32));
    $insert = $dbh->prepare("INSERT INTO tblpasswordresets(UserId, AccountType, TokenHash, ExpiresAt)
      VALUES(:id, 'admin', :hash, NOW() + INTERVAL " . ADMIN_RESET_TTL_MINUTES . " MINUTE)");
    $insert->execute([':id' => $admin->id, ':hash' => hash('sha256', $token)]);
    if (APP_DEV_MODE) {
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $devResetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/reset-password.php?token=' . $token;
    }
    // TODO: the admin table has no email address yet; when it does (and a mail server
    // is available), email the link instead of showing it.
  }
  // The same answer whether or not the username exists.
  $sent = true;
}

admin_auth_start('Forgot password');
?>
<h1>Forgot your password?</h1>
<?php if ($sent) { ?>
  <div class="a-notice a-notice--success mb-3" role="status">
    <i class="fa fa-circle-check mt-1" aria-hidden="true"></i>
    <div>If that username exists, a reset link has been created. It works once, for <?php echo ADMIN_RESET_TTL_MINUTES; ?> minutes.</div>
  </div>
  <?php if ($devResetLink) { ?>
    <div class="a-notice a-notice--warning mb-3">
      <i class="fa fa-flask mt-1" aria-hidden="true"></i>
      <div><strong>Development mode:</strong> there is no mail server, so here is the link:<br><a href="<?php echo h($devResetLink); ?>"><?php echo h($devResetLink); ?></a></div>
    </div>
  <?php } ?>
  <a class="btn btn-light w-100" href="index.php"><i class="fa fa-arrow-left" aria-hidden="true"></i>Back to sign in</a>
<?php } else { ?>
  <p class="a-muted mb-4">Enter your admin username and we'll create a link to set a new password.</p>
  <form method="post">
    <?php echo csrf_field(); ?>
    <div class="mb-3">
      <label class="form-label" for="username">Username</label>
      <input type="text" class="form-control" name="username" id="username" autocomplete="username" required autofocus>
    </div>
    <button class="btn btn-primary w-100 btn-lg" name="request" type="submit"><i class="fa fa-paper-plane" aria-hidden="true"></i>Create reset link</button>
  </form>
  <div class="a-auth__links"><a href="index.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to sign in</a></div>
<?php } ?>
<?php admin_auth_end(); ?>
