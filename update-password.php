<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  header('Location: ./');
} else {
  if (isset($_POST['updatepass'])) {
    $email = $_SESSION['login'];
    $sql = "SELECT id, Password FROM tblusers WHERE EmailId=:email";
    $query = $dbh->prepare($sql);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
    $user = $query->fetch(PDO::FETCH_OBJ);
    if ((string)($_POST['newpassword'] ?? '') !== (string)($_POST['confirmpassword'] ?? '')) {
      $error = "New Password and Confirm Password do not match";
    } elseif ($user && password_check_and_upgrade((string)($_POST['password'] ?? ''), $user->Password, function () {})) {
      $newpassword = password_hash_for_storage((string)$_POST['newpassword']);
      $con = "update tblusers set Password=:newpassword where id=:id";
      $chngpwd1 = $dbh->prepare($con);
      $chngpwd1->bindParam(':id', $user->id, PDO::PARAM_INT);
      $chngpwd1->bindParam(':newpassword', $newpassword, PDO::PARAM_STR);
      $chngpwd1->execute();
      $msg = "Your Password succesfully changed";
    } else {
      $error = "Your current password is wrong";
    }
  }

  ?>
  <!DOCTYPE HTML>
  <html lang="en">

  <head>
    <?php
    $pageTitle = 'Update Password';
    include('includes/head.php');
    ?>
    <script type="text/javascript">
      function updatepassword_valid() {
        if (document.chngpwd.newpassword.value != document.chngpwd.confirmpassword.value) {
          alert("New Password and Confirm Password Field do not match  !!");
          document.chngpwd.confirmpassword.focus();
          return false;
        }
        return true;
      }
    </script>
    <style>

      /* ---- Update password card UI ---- */
      .profile_wrap {
        background: #fff;
        border-radius: 8px;
        padding: 0 0 20px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        border: 1px solid #eee;
        overflow: hidden;
      }

      .profile_wrap .field-title {
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
        padding: 16px 30px;
        margin: 0 0 25px;
      }

      .profile_wrap .field-title h6 {
        margin: 0;
        font-family: var(--rento-font-heading);
        letter-spacing: 1px;
        color: #4f5362;
        font-size: 1.05em;
      }

      .profile_wrap form {
        padding: 0 30px;
      }

      .profile_wrap .rento-flash {
        margin: 0 0 20px;
      }

      .profile_wrap .form-group {
        margin-bottom: 20px;
      }

      .profile_wrap .control-label {
        font-weight: 600;
        color: #4f5362;
        font-size: .92em;
        margin-bottom: 6px;
        display: block;
      }

      .profile_wrap .form-control.white_bg {
        background: #f8f9fa;
        border: 1px solid #e2e5e9;
        border-radius: 6px;
        height: 46px;
        padding: 10px 15px;
        transition: border-color .2s, box-shadow .2s;
      }

      .profile_wrap .form-control.white_bg:focus {
        border-color: #37a6c4;
        box-shadow: 0 0 0 3px rgba(55, 166, 196, .15);
        outline: none;
      }

      .profile_wrap .btn-block {
        background: #37a6c4;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 12px 28px;
        font-family: var(--rento-font-heading);
        letter-spacing: .5px;
        transition: background .2s;
      }

      .profile_wrap .btn-block:hover {
        background: #2c849c;
      }

      @media (max-width: 767px) {
        .profile_wrap form {
          padding: 0 18px;
        }

        .profile_wrap .field-title {
          padding: 14px 18px;
        }
      }

      /* ---- Robust profile/password page layout ---- */
      .user_profile.inner_pages .row.profile-row {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
      }

      .user_profile.inner_pages .row.profile-row>[class*="col-"] {
        float: none;
        width: auto;
      }

      .user_profile.inner_pages .row.profile-row .col-md-3 {
        flex: 0 0 260px;
      }

      .user_profile.inner_pages .row.profile-row .col-md-6,
      .user_profile.inner_pages .row.profile-row .col-md-8 {
        flex: 1 1 0;
        min-width: 0;
      }

      @media (max-width: 767px) {
        .user_profile.inner_pages .row.profile-row {
          flex-direction: column;
          gap: 0;
        }
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
            <h1>Update Password</h1>
          </div>
          <ul class="coustom-breadcrumb">
            <li><a href="#">Home</a></li>
            <li>Update Password</li>
          </ul>
        </div>
      </div>
      <!-- Dark Overlay-->
      <div class="dark-overlay"></div>
    </section>
    <!-- /Page Header-->

    <?php
    $useremail = $_SESSION['login'];
    $sql = "SELECT * from tblusers where EmailId=:useremail";
    $query = $dbh->prepare($sql);
    $query->bindParam(':useremail', $useremail, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    $cnt = 1;
    if ($query->rowCount() > 0) {
      foreach ($results as $result) { ?>
        <section class="user_profile inner_pages">
          <div class="container">
            <div class="user_profile_info gray-bg padding_4x4_40">
              <div class="upload_user_logo"> <img src="assets/images/dealer-logo.jpg" alt="image">
              </div>

              <div class="dealer_info">
                <h5><?php echo htmlentities($result->FullName); ?></h5>
                <p><?php echo htmlentities($result->Address); ?><br>
                  <?php echo htmlentities($result->City); ?>&nbsp;<?php echo htmlentities($result->Country); ?></p>
              </div>
            </div>
            <div class="row profile-row">
              <div class="col-md-3 col-sm-3">
                <?php include('includes/sidebar.php'); ?>
              </div>
              <div class="col-md-6 col-sm-8">
                <div class="profile_wrap">
                  <div class="field-title">
                    <h6>Update Password</h6>
                  </div>

                  <form name="chngpwd" method="post" onSubmit="return updatepassword_valid();">
<?php echo csrf_field(); ?>
                    <?php echo !empty($error) ? flash_message('error', $error) : flash_message('success', $msg ?? ''); ?>

                    <div class="form-group">
                      <label class="control-label">Current Password</label>
                      <input class="form-control white_bg" id="password" name="password" type="password" required>
                    </div>

                    <div class="form-group">
                      <label class="control-label">New Password</label>
                      <input class="form-control white_bg" id="newpassword" type="password" name="newpassword" required>
                    </div>

                    <div class="form-group">
                      <label class="control-label">Confirm Password</label>
                      <input class="form-control white_bg" id="confirmpassword" type="password" name="confirmpassword"
                        required>
                    </div>

                    <div class="form-group">
                      <input type="submit" value="Update" name="updatepass" id="submit" class="btn btn-block">
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </section>
        <!--/Profile-setting-->
      <?php }
    } ?>

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
<?php } ?>