<?php
require_once __DIR__ . '/includes/security.php';
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
} else {
  if (isset($_POST['updateprofile'])) {
    $name = $_POST['fullname'];
    $mobileno = $_POST['mobilenumber'];
    $dob = $_POST['dob'];
    $adress = $_POST['address'];
    $city = $_POST['city'];
    $country = $_POST['country'];
    $email = $_SESSION['login'];
    $sql = "update tblusers set FullName=:name,ContactNo=:mobileno,dob=:dob,Address=:adress,City=:city,Country=:country where EmailId=:email";
    $query = $dbh->prepare($sql);
    $query->bindParam(':name', $name, PDO::PARAM_STR);
    $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
    $query->bindParam(':dob', $dob, PDO::PARAM_STR);
    $query->bindParam(':adress', $adress, PDO::PARAM_STR);
    $query->bindParam(':city', $city, PDO::PARAM_STR);
    $query->bindParam(':country', $country, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
    $msg = "Profile Updated Successfully";
  }

  ?>
  <!DOCTYPE HTML>
  <html lang="en">

  <head>
    <?php
    $pageTitle = 'My Profile';
    include('includes/head.php');
    ?>
    <style>

      /* ---- Profile card UI ---- */
      .profile_wrap {
        background: #fff;
        border-radius: 8px;
        padding: 30px 30px 20px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        border: 1px solid #eee;
      }

      .profile_wrap h5.uppercase {
        font-family: var(--rento-font-heading);
        font-size: 1.1em;
        letter-spacing: 1px;
        color: #4f5362;
        padding-bottom: 12px;
        margin-bottom: 25px;
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

      .profile_wrap .control-label i {
        color: #37a6c4;
        margin-right: 6px;
        width: 14px;
        text-align: center;
      }

      .profile_wrap .form-control.white_bg {
        background: #f8f9fa;
        border: 1px solid #e2e5e9;
        border-radius: 6px;
        height: 46px;
        padding: 10px 15px;
        transition: border-color .2s, box-shadow .2s;
      }

      .profile_wrap textarea.form-control.white_bg {
        height: auto;
      }

      .profile_wrap .form-control.white_bg:focus {
        border-color: #37a6c4;
        box-shadow: 0 0 0 3px rgba(55, 166, 196, .15);
        outline: none;
      }

      .profile_wrap .form-control[readonly] {
        background: #eef0f2;
        color: #888;
        cursor: not-allowed;
      }

      .profile_wrap .meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px dashed #eee;
        font-size: .88em;
        color: #888;
      }

      .profile_wrap .meta-row strong {
        color: #4f5362;
      }

      .profile_wrap .form-row-2 {
        display: flex;
        gap: 20px;
      }

      .profile_wrap .form-row-2 .form-group {
        flex: 1;
        min-width: 0;
      }

      @media (max-width: 767px) {
        .profile_wrap .form-row-2 {
          flex-direction: column;
          gap: 0;
        }

        .profile_wrap {
          padding: 22px 18px 14px;
        }
      }

      .profile_wrap .btn {
        background: #37a6c4;
        color: #fff;
        border-radius: 6px;
        padding: 12px 28px;
        border: none;
        font-family: var(--rento-font-heading);
        letter-spacing: .5px;
        transition: background .2s;
      }

      .profile_wrap .btn:hover {
        background: #2c849c;
        color: #fff;
      }

      .profile_wrap .angle_arrow {
        margin-left: 6px;
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
            <h1>Your Profile</h1>
          </div>
          <ul class="coustom-breadcrumb">
            <li><a href="#">Home</a></li>
            <li>Profile</li>
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

            <div class="row">
              <div class="col-md-3 col-sm-3">
                <?php include('includes/sidebar.php'); ?>
              </div>
              <div class="col-md-6 col-sm-8">
                <div class="profile_wrap">
                  <h5 class="uppercase underline">General Settings</h5>

                  <?php echo flash_message('success', $msg ?? ''); ?>

                  <div class="meta-row">
                    <span><strong>Reg Date:</strong> <?php echo htmlentities($result->RegDate); ?></span>
                    <?php if ($result->UpdationDate != "") { ?>
                      <span><strong>Last Update:</strong> <?php echo htmlentities($result->UpdationDate); ?></span>
                    <?php } ?>
                  </div>

                  <form method="post">
<?php echo csrf_field(); ?>
                    <div class="form-group">
                      <label class="control-label"><i class="fa fa-user"></i>Full Name</label>
                      <input class="form-control white_bg" name="fullname"
                        value="<?php echo htmlentities($result->FullName); ?>" id="fullname" type="text" required>
                    </div>

                    <div class="form-group">
                      <label class="control-label"><i class="fa fa-envelope"></i>Email Address</label>
                      <input class="form-control white_bg" value="<?php echo htmlentities($result->EmailId); ?>"
                        name="emailid" id="email" type="email" required readonly>
                    </div>

                    <div class="form-row-2">
                      <div class="form-group">
                        <label class="control-label"><i class="fa fa-phone"></i>Phone Number</label>
                        <input class="form-control white_bg" name="mobilenumber"
                          value="<?php echo htmlentities($result->ContactNo); ?>" id="phone-number" type="text" required>
                      </div>
                      <div class="form-group">
                        <label class="control-label"><i class="fa fa-cake-candles"></i>Date of Birth (dd/mm/yyyy)</label>
                        <input class="form-control white_bg" value="<?php echo htmlentities($result->dob); ?>" name="dob"
                          placeholder="dd/mm/yyyy" id="birth-date" type="text">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="control-label"><i class="fa fa-location-dot"></i>Your Address</label>
                      <textarea class="form-control white_bg" name="address"
                        rows="4"><?php echo htmlentities($result->Address); ?></textarea>
                    </div>

                    <div class="form-row-2">
                      <div class="form-group">
                        <label class="control-label"><i class="fa fa-globe"></i>Country</label>
                        <input class="form-control white_bg" id="country" name="country"
                          value="<?php echo htmlentities($result->Country); ?>" type="text">
                      </div>
                      <div class="form-group">
                        <label class="control-label"><i class="fa fa-building"></i>City</label>
                        <input class="form-control white_bg" id="city" name="city"
                          value="<?php echo htmlentities($result->City); ?>" type="text">
                      </div>
                    </div>

                    <div class="form-group">
                      <button type="submit" name="updateprofile" class="btn">Save Changes <span class="angle_arrow"><i
                            class="fa fa-angle-right" aria-hidden="true"></i></span></button>
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