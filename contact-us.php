<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

// Contact form. The result is shown on this page after redirecting (post/redirect/get).
if (isset($_POST['send'])) {
  $name = trim((string)($_POST['fullname'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $contactno = trim((string)($_POST['contactno'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));

  if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter your name, a valid email address and a message.');
  } else {
    $sql = "INSERT INTO  tblcontactusquery(name,EmailId,ContactNumber,Message) VALUES(:name,:email,:contactno,:message)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':name', $name, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':contactno', $contactno, PDO::PARAM_STR);
    $query->bindParam(':message', $message, PDO::PARAM_STR);
    $query->execute();
    if ($dbh->lastInsertId()) {
      flash_set('success', 'Thanks! Your message has been sent. We will get back to you shortly.');
    } else {
      flash_set('error', 'Something went wrong. Please try again.');
    }
  }
  redirect_to('contact-us');
}

$contact = site_contact_info($dbh);
$address = trim($contact->Address);
$email = trim($contact->EmailId);
$phone = trim($contact->ContactNo);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Contact Us';
  $pageDescription = 'Contact Rento: address, email, phone number and a form to send us your question.';
  $pageModern = true;
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero('Contact us', 'Questions about a car, a booking or a trip? Send us a message and we will get back to you.'); ?>

    <section class="r-section r-section--muted">
      <div class="container">
        <div class="r-contact">
          <div class="r-panel">
            <h2 class="r-panel__title">Send us a message</h2>
            <?php echo flash_render(); ?>
            <form method="post">
              <?php echo csrf_field(); ?>
              <div class="r-form-row">
                <div class="r-field">
                  <label class="r-label" for="fullname">Full name <span class="r-required">*</span></label>
                  <input type="text" name="fullname" class="r-input" id="fullname" autocomplete="name" required>
                </div>
                <div class="r-field">
                  <label class="r-label" for="emailaddress">Email address <span class="r-required">*</span></label>
                  <input type="email" name="email" class="r-input" id="emailaddress" autocomplete="email" required>
                </div>
              </div>
              <div class="r-field">
                <label class="r-label" for="phonenumber">Phone number</label>
                <input type="tel" name="contactno" class="r-input" id="phonenumber" autocomplete="tel" maxlength="10" pattern="[0-9]+">
              </div>
              <div class="r-field">
                <label class="r-label" for="message">Message <span class="r-required">*</span></label>
                <textarea class="r-textarea" name="message" id="message" rows="5" required></textarea>
              </div>
              <button class="r-btn r-btn--lg" type="submit" name="send">Send message <i class="fa fa-paper-plane" aria-hidden="true"></i></button>
            </form>
          </div>

          <div class="r-contact__info">
            <ul class="r-contact__list">
              <?php if ($phone) { ?>
                <li><span class="r-contact__icon"><i class="fa fa-phone" aria-hidden="true"></i></span>
                  <div><h3>Call us</h3><a href="tel:<?php echo htmlentities($phone); ?>"><?php echo htmlentities($phone); ?></a></div></li>
              <?php } ?>
              <?php if ($email) { ?>
                <li><span class="r-contact__icon"><i class="fa fa-envelope" aria-hidden="true"></i></span>
                  <div><h3>Email</h3><a href="mailto:<?php echo htmlentities($email); ?>"><?php echo htmlentities($email); ?></a></div></li>
              <?php } ?>
              <?php if ($address) { ?>
                <li><span class="r-contact__icon"><i class="fa fa-location-dot" aria-hidden="true"></i></span>
                  <div><h3>Visit us</h3><p><?php echo htmlentities($address); ?></p></div></li>
              <?php } ?>
            </ul>
            <?php if ($address) { ?>
              <div class="r-map">
                <iframe title="Map: <?php echo htmlentities($address); ?>"
                  src="https://maps.google.com/maps?q=<?php echo urlencode($address); ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
                  allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
