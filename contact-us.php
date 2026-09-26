<?php
require_once __DIR__ . '/includes/security.php';
error_reporting(0);
include('includes/config.php');
if (isset($_POST['send'])) {
  $name = $_POST['fullname'];
  $email = $_POST['email'];
  $contactno = $_POST['contactno'];
  $message = $_POST['message'];
  $sql = "INSERT INTO  tblcontactusquery(name,EmailId,ContactNumber,Message) VALUES(:name,:email,:contactno,:message)";
  $query = $dbh->prepare($sql);
  $query->bindParam(':name', $name, PDO::PARAM_STR);
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->bindParam(':contactno', $contactno, PDO::PARAM_STR);
  $query->bindParam(':message', $message, PDO::PARAM_STR);
  $query->execute();
  $lastInsertId = $dbh->lastInsertId();
  if ($lastInsertId) {
    $msg = "Query Sent. We will contact you shortly";
  } else {
    $error = "Something went wrong. Please try again";
  }

}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Contact Us';
  $pageDescription = 'Contact Rento: address, email, phone number and a form to send us your question.';
  include('includes/head.php');
  ?>
</head>

<body>


  <!--Header-->
  <?php include('includes/header.php'); ?>
  <!-- /Header -->

  <!--Page Header-->
  <section class="page-header contactus_page">
    <div class="container">
      <div class="page-header_wrap">
        <div class="page-heading">
          <h1>Contact Us</h1>
        </div>
        <ul class="coustom-breadcrumb">
          <li><a href="#">Home</a></li>
          <li>Contact Us</li>
        </ul>
      </div>
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>
  <!-- /Page Header-->

  <!--Contact-us-->
  <section class="contact_us section-padding">
    <div class="container">
      <div class="row">
        <div class="col-md-6">
          <h3>Get in touch using the form below</h3>
          <?php echo !empty($error) ? flash_message('error', $error) : flash_message('success', $msg ?? ''); ?>
          <div class="contact_form gray-bg">
            <form method="post">
<?php echo csrf_field(); ?>
              <div class="form-group">
                <label class="control-label">Full Name <span>*</span></label>
                <input type="text" name="fullname" class="form-control white_bg" id="fullname" required>
              </div>
              <div class="form-group">
                <label class="control-label">Email Address <span>*</span></label>
                <input type="email" name="email" class="form-control white_bg" id="emailaddress" required>
              </div>
              <div class="form-group">
                <label class="control-label">Phone Number <span>*</span></label>
                <input type="text" name="contactno" class="form-control white_bg" id="phonenumber" required
                  maxlength="10" pattern="[0-9]+">
              </div>
              <div class="form-group">
                <label class="control-label">Message <span>*</span></label>
                <textarea class="form-control white_bg" name="message" rows="4" required></textarea>
              </div>
              <div class="form-group">
                <button class="btn" type="submit" name="send">Send Message <span class="angle_arrow"><i
                      class="fa fa-angle-right" aria-hidden="true"></i></span></button>
              </div>
            </form>
          </div>
        </div>
        <div class="col-md-6">
          <h3>Contact Info</h3>
          <div class="contact_detail">
            <?php
            $pagetype = $_GET['type'];
            $sql = "SELECT Address,EmailId,ContactNo from tblcontactusinfo";
            $query = $dbh->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
            $cnt = 1;
            if ($query->rowCount() > 0) {
              foreach ($results as $result) {
                // Trim stray whitespace that may exist in the stored data
                // (e.g. a leading space before the email address)
                $address = trim($result->Address);
                $email = trim($result->EmailId);
                $phone = trim($result->ContactNo);
                ?>
                <ul>
                  <li>
                    <div class="icon_wrap"><i class="fa fa-location-dot" aria-hidden="true"></i></div>
                    <div class="contact_info_m"><?php echo htmlentities($address); ?></div>
                  </li>
                  <li>
                    <div class="icon_wrap"><i class="fa fa-phone" aria-hidden="true"></i></div>
                    <div class="contact_info_m"><a
                        href="tel:<?php echo htmlentities($phone); ?>"><?php echo htmlentities($phone); ?></a>
                    </div>
                  </li>
                  <li>
                    <div class="icon_wrap"><i class="fa-regular fa-envelope" aria-hidden="true"></i></div>
                    <div class="contact_info_m"><a
                        href="mailto:<?php echo htmlentities($email); ?>"><?php echo htmlentities($email); ?></a>
                    </div>
                  </li>
                </ul>

                <?php if ($address) { ?>
                  <div class="map_wrap">
                    <iframe
                      src="https://maps.google.com/maps?q=<?php echo urlencode($address); ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
                      allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                  </div>
                <?php } ?>

              <?php }
            } ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- /Contact-us-->


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