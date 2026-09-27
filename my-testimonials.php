<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your account.');
  flash_open_modal('loginform');
  redirect_to('./');
}

$query = $dbh->prepare("SELECT * FROM tbltestimonial WHERE UserEmail=:useremail ORDER BY id DESC");
$query->bindParam(':useremail', $_SESSION['login'], PDO::PARAM_STR);
$query->execute();
$testimonials = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'My Testimonials';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo account_start($dbh, 'my-testimonials', 'My testimonials', 'Testimonials appear on the home page once we approve them.'); ?>
    <?php if ($testimonials) { ?>
      <p class="mb-4"><a class="r-btn r-btn--outline r-btn--sm" href="post-testimonial"><i class="fa fa-plus" aria-hidden="true"></i>Write another</a></p>
      <?php foreach ($testimonials as $testimonial) { ?>
        <article class="r-testimonial">
          <div class="r-testimonial__head">
            <span>Posted <?php echo htmlentities(date('j M Y', strtotime($testimonial->PostingDate))); ?></span>
            <?php echo $testimonial->status == 1
              ? '<span class="r-badge r-badge--success">Published</span>'
              : '<span class="r-badge r-badge--warning">Awaiting approval</span>'; ?>
          </div>
          <p><?php echo nl2br(htmlentities($testimonial->Testimonial)); ?></p>
        </article>
      <?php }
    } else {
      echo empty_state('fa-comment-dots', 'No testimonials yet', 'Rented a car with us? Tell other renters how it went.', 'Write a testimonial', 'post-testimonial', 'h2');
    } ?>
    <?php echo account_end(); ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
