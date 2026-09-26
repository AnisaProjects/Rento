<?php
require_once __DIR__ . '/includes/security.php';
error_reporting(0);
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
      $devResetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/reset-password.php?token=' . $newToken;
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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rento - Reset Password</title>
  <!--Bootstrap -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
  <!--Custome Style -->
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <!--OWL Carousel slider-->
  <link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
  <link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
  <!--slick-slider -->
  <link href="assets/css/slick.css" rel="stylesheet">
  <!--bootstrap-slider -->
  <link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
  <!--FontAwesome Font Style -->
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">

  <!-- SWITCHER -->
  <link rel="stylesheet" id="switcher-css" type="text/css" href="assets/switcher/css/switcher.css" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/red.css" title="red" media="all"
    data-default-color="true" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/orange.css" title="orange" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/blue.css" title="blue" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/pink.css" title="pink" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/green.css" title="green" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/purple.css" title="purple" media="all" />

  <!-- Fav and touch icons -->
  <link rel="apple-touch-icon-precomposed" sizes="144x144"
    href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
  <link rel="apple-touch-icon-precomposed" sizes="72x72"
    href="assets/images/favicon-icon/apple-touch-icon-72-precomposed.png">
  <link rel="apple-touch-icon-precomposed" href="assets/images/favicon-icon/apple-touch-icon-57-precomposed.png">
  <link rel="shortcut icon" href="assets/images/favicon-icon/favicon.png">
  <!-- Google-Font-->
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,900" rel="stylesheet">
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
    .errorWrap {
      padding: 10px;
      margin: 0 0 20px 0;
      background: #fff;
      border-left: 4px solid #dd3d36;
      box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
      border-radius: 4px;
    }

    .succWrap {
      padding: 10px;
      margin: 0 0 20px 0;
      background: #fff;
      border-left: 4px solid #5cb85c;
      box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
      border-radius: 4px;
    }

    .devWrap {
      padding: 12px;
      margin: 0 0 20px 0;
      background: #fff8e1;
      border: 1px dashed #f0ad4e;
      border-radius: 4px;
      word-break: break-all;
    }

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
          <li><a href="index.php">Home</a></li>
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
          <?php if ($error) { ?>
            <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
          <?php } else if ($msg) { ?>
            <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
          <?php } ?>

          <?php if ($devResetLink) { ?>
            <div class="devWrap">
              <strong>Development mode:</strong> there is no mail server, so the reset link is shown here instead of
              being emailed (set APP_DEV_MODE to false in includes/config.php to hide it).<br>
              <a href="<?php echo htmlentities($devResetLink); ?>"><?php echo htmlentities($devResetLink); ?></a>
            </div>
          <?php } ?>

          <?php if ($showResetForm) { ?>
            <form name="resetpwd" method="post" action="reset-password.php" onSubmit="return resetpassword_valid();">
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
              <a href="#loginform" class="btn" data-toggle="modal">Login</a>
              &nbsp;
              <a href="#forgotpassword" data-toggle="modal">Request a new reset link</a>
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
  <!--/Forgot-password-Form -->

  <!-- Scripts -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/interface.js"></script>
  <!--Switcher-->
  <script src="assets/switcher/js/switcher.js"></script>
  <!--Slider-JS-->
  <script src="assets/js/slick.min.js"></script>
  <script src="assets/js/owl.carousel.min.js"></script>

</body>

</html>
