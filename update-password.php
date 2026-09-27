<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your account.');
  flash_open_modal('loginform');
  redirect_to('./');
}

if (isset($_POST['updatepass'])) {
  $email = $_SESSION['login'];
  $sql = "SELECT id, Password FROM tblusers WHERE EmailId=:email";
  $query = $dbh->prepare($sql);
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->execute();
  $user = $query->fetch(PDO::FETCH_OBJ);
  if ((string)($_POST['newpassword'] ?? '') !== (string)($_POST['confirmpassword'] ?? '')) {
    flash_set('error', 'New password and confirmation do not match.');
  } elseif ($user && password_check_and_upgrade((string)($_POST['password'] ?? ''), $user->Password, function () {})) {
    $newpassword = password_hash_for_storage((string)$_POST['newpassword']);
    $con = "update tblusers set Password=:newpassword where id=:id";
    $chngpwd1 = $dbh->prepare($con);
    $chngpwd1->bindParam(':id', $user->id, PDO::PARAM_INT);
    $chngpwd1->bindParam(':newpassword', $newpassword, PDO::PARAM_STR);
    $chngpwd1->execute();
    flash_set('success', 'Your password has been changed.');
  } else {
    flash_set('error', 'Your current password is wrong.');
  }
  redirect_to('update-password');
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Change Password';
  $pageNoindex = true; // account / utility page: not for search engines
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo account_start($dbh, 'update-password', 'Change password', 'Choose a password you don\'t use on other sites.'); ?>
    <div class="r-panel r-panel--narrow">
      <h2 class="r-panel__title">Change your password</h2>
      <?php echo flash_render(); ?>
      <form name="chngpwd" method="post" onsubmit="return document.getElementById('newpassword').value === document.getElementById('confirmpassword').value;">
        <?php echo csrf_field(); ?>
        <div class="r-field">
          <label class="r-label" for="password">Current password</label>
          <div class="r-password">
            <input class="r-input" id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="button" class="r-password__toggle" aria-controls="password" aria-label="Show password" aria-pressed="false"><i class="fa fa-eye" aria-hidden="true"></i></button>
          </div>
        </div>
        <div class="r-field">
          <label class="r-label" for="newpassword">New password</label>
          <div class="r-password">
            <input class="r-input" id="newpassword" name="newpassword" type="password" autocomplete="new-password" required>
            <button type="button" class="r-password__toggle" aria-controls="newpassword" aria-label="Show password" aria-pressed="false"><i class="fa fa-eye" aria-hidden="true"></i></button>
          </div>
        </div>
        <div class="r-field">
          <label class="r-label" for="confirmpassword">Confirm new password</label>
          <input class="r-input" id="confirmpassword" name="confirmpassword" type="password" autocomplete="new-password" required
            oninput="var ok = this.value === document.getElementById('newpassword').value; var h = document.getElementById('pw-match'); h.textContent = this.value ? (ok ? 'Passwords match.' : 'Passwords do not match.') : ''; h.className = 'r-hint ' + (ok ? 'r-hint--success' : 'r-hint--error');">
          <p class="r-hint" id="pw-match" aria-live="polite"></p>
        </div>
        <button type="submit" name="updatepass" class="r-btn r-btn--lg">Update password</button>
      </form>
    </div>
    <?php echo account_end(); ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
