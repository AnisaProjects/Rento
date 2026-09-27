<?php
// Login modal. The form posts to the current page; afterwards the visitor is
// redirected back to it and sees the result as a toast.
if (isset($_POST['login'])) {

  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (rate_limit_exceeded($dbh, 'login', $email)) {
    flash_set('error', RATE_LIMIT_MESSAGE);
    redirect_to(current_url());
  }

  $sql = "SELECT id, EmailId, Password, FullName
            FROM tblusers
            WHERE EmailId = :email";
  $query = $dbh->prepare($sql);
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->execute();
  $result = $query->fetch(PDO::FETCH_OBJ);

  // Old MD5 hashes are accepted once and replaced with a bcrypt hash.
  $validLogin = $result && password_check_and_upgrade($password, $result->Password, function ($newHash) use ($dbh, $result) {
    $upgrade = $dbh->prepare("UPDATE tblusers SET Password = :password WHERE id = :id");
    $upgrade->bindParam(':password', $newHash, PDO::PARAM_STR);
    $upgrade->bindParam(':id', $result->id, PDO::PARAM_INT);
    $upgrade->execute();
  });

  if ($validLogin) {
    rate_limit_clear($dbh, 'login', $email);
    session_login_regenerate();

    // Store login information in session
    $_SESSION['login'] = $result->EmailId;
    $_SESSION['fname'] = $result->FullName;

    flash_set('success', 'Welcome back, ' . (strtok((string)$result->FullName, ' ') ?: 'there') . '!');
  } else {
    rate_limit_record($dbh, 'login', $email);
    flash_set('error', 'Invalid email or password.');
    flash_open_modal('loginform');
  }
  redirect_to(current_url());
}
?>

<!-- Login Modal -->
<div class="modal fade r-modal" id="loginform" tabindex="-1" aria-labelledby="loginform-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="loginform-title">Welcome back</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="r-modal__lead">Log in to book cars and manage your trips.</p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <div class="r-field">
            <label class="r-label" for="login-email">Email address</label>
            <input type="email" id="login-email" name="email" class="r-input" autocomplete="email" required>
          </div>
          <div class="r-field">
            <label class="r-label" for="login-password">Password</label>
            <div class="r-password">
              <input type="password" id="login-password" name="password" class="r-input" autocomplete="current-password" required>
              <button type="button" class="r-password__toggle" aria-controls="login-password" aria-label="Show password" aria-pressed="false">
                <i class="fa fa-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <p class="r-modal__aside"><a href="#forgotpassword" data-bs-toggle="modal">Forgot password?</a></p>
          <button type="submit" name="login" value="Login" class="r-btn r-btn--block r-btn--lg">Log in</button>
        </form>
      </div>
      <div class="modal-footer">
        <p>New to Rento? <a href="#signupform" data-bs-toggle="modal">Create an account</a></p>
      </div>
    </div>
  </div>
</div>
