<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your account.');
  flash_open_modal('loginform');
  redirect_to('./');
}

if (isset($_POST['submit'])) {
  $testimonoial = trim((string)($_POST['testimonial'] ?? ''));
  $email = $_SESSION['login'];
  if (mb_strlen($testimonoial) < 10) {
    flash_set('error', 'Please write at least a sentence (10 characters or more).');
    redirect_to('post-testimonial');
  }
  $sql = "INSERT INTO  tbltestimonial(UserEmail,Testimonial) VALUES(:email,:testimonoial)";
  $query = $dbh->prepare($sql);
  $query->bindParam(':testimonoial', $testimonoial, PDO::PARAM_STR);
  $query->bindParam(':email', $email, PDO::PARAM_STR);
  $query->execute();
  if ($dbh->lastInsertId()) {
    flash_set('success', 'Thank you! Your testimonial was sent and will appear once approved.');
    redirect_to('my-testimonials');
  }
  flash_set('error', 'Something went wrong. Please try again.');
  redirect_to('post-testimonial');
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Write a Testimonial';
  $pageNoindex = true; // account / utility page: not for search engines
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo account_start($dbh, 'post-testimonial', 'Write a testimonial', 'Share how your trip went. We publish testimonials after a quick review.'); ?>
    <div class="r-panel">
      <h2 class="r-panel__title">Your testimonial</h2>
      <?php echo flash_render(); ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="r-field">
          <label class="r-label" for="testimonial">What was it like renting with Rento? <span class="r-required">*</span></label>
          <textarea class="r-textarea" id="testimonial" name="testimonial" rows="6" minlength="10" maxlength="1000" required
            oninput="document.getElementById('testimonial-count').textContent = this.value.length + ' / 1000'"></textarea>
          <p class="r-hint" id="testimonial-count" aria-live="polite">0 / 1000</p>
        </div>
        <button type="submit" name="submit" class="r-btn r-btn--lg">Send testimonial</button>
      </form>
    </div>
    <?php echo account_end(); ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
