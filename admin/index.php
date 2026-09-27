<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';

if (!empty($_SESSION['alogin'])) {
  redirect_to('dashboard.php');
}

if (isset($_POST['login'])) {
  $email = (string)($_POST['username'] ?? '');
  if (rate_limit_exceeded($dbh, 'admin_login', $email)) {
    flash_set('error', RATE_LIMIT_MESSAGE);
    redirect_to(current_url());
  }
  $query = $dbh->prepare("SELECT id, UserName, Password FROM admin WHERE UserName = :email");
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->execute();
  $admin = $query->fetch(PDO::FETCH_OBJ);
  // Old MD5 hashes are accepted once and replaced with a bcrypt hash.
  $validLogin = $admin && password_check_and_upgrade((string)($_POST['password'] ?? ''), $admin->Password, function ($newHash) use ($dbh, $admin) {
    $upgrade = $dbh->prepare("UPDATE admin SET Password = :password WHERE id = :id");
    $upgrade->bindParam(':password', $newHash, PDO::PARAM_STR);
    $upgrade->bindParam(':id', $admin->id, PDO::PARAM_INT);
    $upgrade->execute();
  });
  if ($validLogin) {
    rate_limit_clear($dbh, 'admin_login', $email);
    session_login_regenerate();
    $_SESSION['alogin'] = $admin->UserName;
    redirect_to('dashboard.php');
  }
  rate_limit_record($dbh, 'admin_login', $email);
  flash_set('error', 'That username and password do not match.');
  $_SESSION['admin_login_name'] = $email;
  redirect_to(current_url());
}

$lastName = (string)($_SESSION['admin_login_name'] ?? '');
unset($_SESSION['admin_login_name']);

admin_auth_start('Sign in');
?>
<h1>Admin sign in</h1>
<p class="a-muted mb-4">Sign in to manage Rento.</p>
<form method="post">
  <?php echo csrf_field(); ?>
  <div class="mb-3">
    <label class="form-label" for="username">Username</label>
    <input type="text" class="form-control" name="username" id="username" value="<?php echo h($lastName); ?>" autocomplete="username" required<?php echo $lastName === '' ? ' autofocus' : ''; ?>>
  </div>
  <?php echo admin_password_input('password', 'Password', 'current-password'); ?>
  <button class="btn btn-primary w-100 btn-lg mt-2" name="login" type="submit"><i class="fa fa-arrow-right-to-bracket" aria-hidden="true"></i>Sign in</button>
</form>
<div class="a-auth__links">
  <a href="forgot-password.php">Forgot password?</a>
  <a href="../"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to the website</a>
</div>
<?php admin_auth_end($lastName !== '' ? "document.getElementById('password').focus();" : ''); ?>
