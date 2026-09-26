<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

// Reset links stop working after this many minutes.
const RESET_TOKEN_TTL_MINUTES = 30;
const RESET_LINK_INVALID = "This reset link is invalid, has expired or was already used. Please request a new one.";

$error = '';
$msg = '';
$devResetLink = '';
$showResetForm = false;
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');

// Returns the reset request for a valid, unused, unexpired token, or false.
function find_reset_request(PDO $dbh, string $token)
{
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return false;
  }
  $tokenHash = hash('sha256', $token);
  $query = $dbh->prepare("SELECT id, UserId FROM tblpasswordresets WHERE TokenHash=:hash AND UsedAt IS NULL AND ExpiresAt > NOW()");
  $query->bindParam(':hash', $tokenHash, PDO::PARAM_STR);
  $query->execute();
  return $query->fetch(PDO::FETCH_OBJ);
}

if (isset($_POST['update'])) {
  // Step 1: the Forgot Password modal posts the email here.
  $email = trim((string)($_POST['email'] ?? ''));
  $user = false;
  if (rate_limit_exceeded($dbh, 'password_reset', $email)) {
    $error = RATE_LIMIT_MESSAGE;
  } else {
    // Every request counts, not only failures, so the form can't be used to flood an inbox.
    rate_limit_record($dbh, 'password_reset', $email);
    $query = $dbh->prepare("SELECT id FROM tblusers WHERE EmailId=:email");
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
    $user = $query->fetch(PDO::FETCH_OBJ);
  }

  if ($user) {
    // Only the newest link for a user works.
    $expire = $dbh->prepare("UPDATE tblpasswordresets SET UsedAt=NOW() WHERE UserId=:uid AND UsedAt IS NULL");
    $expire->bindParam(':uid', $user->id, PDO::PARAM_INT);
    $expire->execute();

    $newToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $newToken);
    $insert = $dbh->prepare("INSERT INTO tblpasswordresets(UserId, TokenHash, ExpiresAt) VALUES(:uid, :hash, NOW() + INTERVAL " . RESET_TOKEN_TTL_MINUTES . " MINUTE)");
    $insert->bindParam(':uid', $user->id, PDO::PARAM_INT);
    $insert->bindParam(':hash', $tokenHash, PDO::PARAM_STR);
    $insert->execute();

    if (APP_DEV_MODE) {
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $devResetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/reset-password?token=' . $newToken;
    }
    // TODO: when a mail server is available, email the link here instead of showing it.
  }
  // Same message whether or not the email exists, so this form can't be used to discover accounts.
  if (!$error) $msg = "If that email is registered, a password reset link has been sent to it. The link is valid for " . RESET_TOKEN_TTL_MINUTES . " minutes.";
} elseif (isset($_POST['resetpassword'])) {
  // Step 3: save the new password.
  $reset = find_reset_request($dbh, $token);
  $newpassword = (string)($_POST['newpassword'] ?? '');
  if (!$reset) {
    $error = RESET_LINK_INVALID;
  } elseif ($newpassword === '' || $newpassword !== (string)($_POST['confirmpassword'] ?? '')) {
    $error = "New Password and Confirm Password do not match";
    $showResetForm = true;
  } else {
    $passwordHash = password_hash_for_storage($newpassword);
    $dbh->beginTransaction();
    $update = $dbh->prepare("UPDATE tblusers SET Password=:password WHERE id=:uid");
    $update->bindParam(':password', $passwordHash, PDO::PARAM_STR);
    $update->bindParam(':uid', $reset->UserId, PDO::PARAM_INT);
    $update->execute();
    $used = $dbh->prepare("UPDATE tblpasswordresets SET UsedAt=NOW() WHERE UserId=:uid AND UsedAt IS NULL");
    $used->bindParam(':uid', $reset->UserId, PDO::PARAM_INT);
    $used->execute();
    $dbh->commit();
    $msg = "Your password has been reset. You can now login with your new password.";
  }
} elseif ($token !== '') {
  // Step 2: the link opens the new-password form.
  if (find_reset_request($dbh, $token)) {
    $showResetForm = true;
  } else {
    $error = RESET_LINK_INVALID;
  }
} else {
  $error = "To reset your password, use \"Forgot Password?\" in the login window to request a reset link.";
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Reset Password';
  $pageDescription = 'Reset your Rento account password with a secure, time-limited link.';
  include('includes/head.php');
  ?>
  <script type="text/javascript">
    function resetpassword_valid() {
      if (document.resetpwd.newpassword.value != document.resetpwd.confirmpassword.value) {
        alert("New Password and Confirm Password Field do not match  !!");
        document.resetpwd.confirmpassword.focus();
        return false;
      }
      return true;
    }
  </script>
  <style>

    .reset_wrap {
      max-width: 560px;
      margin: 0 auto;
      background: #fff;
      border-radius: 8px;
      padding: 0 0 20px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
      border: 1px solid #eee;
      overflow: hidden;
    }

    .reset_wrap .field-title {
      background: #f8f9fa;
      border-bottom: 1px solid #eee;
      padding: 16px 30px;
      margin: 0 0 25px;
    }

    .reset_wrap .field-title h6 {
      margin: 0;
      letter-spacing: 1px;
      color: #4f5362;
      font-size: 1.05em;
    }

    .reset_wrap .reset_body {
      padding: 0 30px;
    }

    .reset_wrap .control-label {
      font-weight: 600;
      color: #4f5362;
      margin-bottom: 6px;
      display: block;
    }
  </style>
</head>

<body>

  <!--Header-->
  <?php include('includes/header.php'); ?>
  <!-- /Header -->
  <!--Page Header-->
  <section class="page-header profile_page">
    <div class="container">
      <div class="page-header_wrap">
        <div class="page-heading">
          <h1>Reset Password</h1>
        </div>
        <ul class="coustom-breadcrumb">
          <li><a href="./">Home</a></li>
          <li>Reset Password</li>
        </ul>
      </div>
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>
  <!-- /Page Header-->

  <section class="user_profile inner_pages">
    <div class="container">
      <div class="reset_wrap">
        <div class="field-title">
          <h6>Reset Password</h6>
        </div>
        <div class="reset_body">
          <?php echo !empty($error) ? flash_message('error', $error) : flash_message('success', $msg ?? ''); ?>

          <?php
          // Development mode only: no mail server, so the link is shown instead of emailed.
          if ($devResetLink) {
            echo flash_message('warning', '<strong>Development mode:</strong> there is no mail server, so the reset link is shown here instead of being emailed (set APP_DEV_MODE to false in includes/config.php to hide it).<br><a href="'
              . htmlentities($devResetLink) . '">' . htmlentities($devResetLink) . '</a>', true);
          }
          ?>

          <?php if ($showResetForm) { ?>
            <form name="resetpwd" method="post" action="reset-password" onSubmit="return resetpassword_valid();">
<?php echo csrf_field(); ?>
              <input type="hidden" name="token" value="<?php echo htmlentities($token); ?>">
              <div class="form-group">
                <label class="control-label" for="newpassword">New Password</label>
                <input class="form-control white_bg" id="newpassword" type="password" name="newpassword" required>
              </div>
              <div class="form-group">
                <label class="control-label" for="confirmpassword">Confirm Password</label>
                <input class="form-control white_bg" id="confirmpassword" type="password" name="confirmpassword" required>
              </div>
              <div class="form-group">
                <input type="submit" value="Reset Password" name="resetpassword" class="btn btn-block">
              </div>
            </form>
          <?php } else { ?>
            <p>
              <a href="#loginform" class="btn" data-bs-toggle="modal">Login</a>
              &nbsp;
              <a href="#forgotpassword" data-bs-toggle="modal">Request a new reset link</a>
            </p>
          <?php } ?>
        </div>
      </div>
    </div>
  </section>

  <!--Footer -->
  <?php include('includes/footer.php'); ?>
  <!-- /Footer-->

  <!--Login-Form -->
  <?php include('includes/login.php'); ?>
  <!--/Login-Form -->

  <!--Register-Form -->
  <?php include('includes/registration.php'); ?>
  <!--/Register-Form -->

  <!--Forgot-password-Form -->
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
