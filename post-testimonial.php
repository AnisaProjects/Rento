<?php
require_once __DIR__ . '/includes/security.php';
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
} else {
  if (isset($_POST['submit'])) {
    $testimonoial = $_POST['testimonial'];
    $email = $_SESSION['login'];
    $sql = "INSERT INTO  tbltestimonial(UserEmail,Testimonial) VALUES(:email,:testimonoial)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':testimonoial', $testimonoial, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);

    $query->execute();
    $lastInsertId = $dbh->lastInsertId();
    if ($lastInsertId) {
      $msg = "Testimonail submitted successfully";
    } else {
      $error = "Something went wrong. Please try again";
    }

  }
  ?>
  <!DOCTYPE HTML>
  <html lang="en">

  <head>
    <?php
    $pageTitle = 'Post a Testimonial';
    include('includes/head.php');
    ?>
    <style>
      .errorWrap {
        padding: 10px;
        margin: 0 0 20px 0;
        background: #fff;
        border-left: 4px solid #dd3d36;
        -webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
        box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
      }

      .succWrap {
        padding: 10px;
        margin: 0 0 20px 0;
        background: #fff;
        border-left: 4px solid #5cb85c;
        -webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
        box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
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
            <h1>Post Testimonial</h1>
          </div>
          <ul class="coustom-breadcrumb">
            <li><a href="#">Home</a></li>
            <li>Post Testimonial</li>
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
                  <?php echo htmlentities($result->City); ?>&nbsp;<?php echo htmlentities($result->Country);
      }
    } ?></p>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3 col-sm-3">
            <?php include('includes/sidebar.php'); ?>
          </div>
          <div class="col-md-6 col-sm-8">
            <div class="profile_wrap">
              <h5 class="uppercase underline">Post a Testimonial</h5>
              <?php if ($error) { ?>
                <div class="errorWrap"><strong>ERROR</strong>:<?php echo htmlentities($error); ?> </div><?php } else if ($msg) { ?>
                  <div class="succWrap"><strong>SUCCESS</strong>:<?php echo htmlentities($msg); ?> </div><?php } ?>
              <form method="post">
<?php echo csrf_field(); ?>


                <div class="form-group">
                  <label class="control-label">Testimonail</label>
                  <textarea class="form-control white_bg" name="testimonial" rows="4" required=""></textarea>
                </div>


                <div class="form-group">
                  <button type="submit" name="submit" class="btn">Save <span class="angle_arrow"><i
                        class="fa fa-angle-right" aria-hidden="true"></i></span></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!--/Profile-setting-->

    <<!--Footer -->
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