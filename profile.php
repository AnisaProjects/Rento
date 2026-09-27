<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
if (empty($_SESSION['login'])) {
  flash_set('info', 'Please log in to see your account.');
  flash_open_modal('loginform');
  redirect_to('./');
}

if (isset($_POST['updateprofile'])) {
  $name = trim((string)($_POST['fullname'] ?? ''));
  $mobileno = trim((string)($_POST['mobilenumber'] ?? ''));
  $dob = trim((string)($_POST['dob'] ?? ''));
  $adress = trim((string)($_POST['address'] ?? ''));
  $city = trim((string)($_POST['city'] ?? ''));
  $country = trim((string)($_POST['country'] ?? ''));
  $email = $_SESSION['login'];
  if ($name === '') {
    flash_set('error', 'Please enter your full name.');
  } else {
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
    flash_set('success', 'Your profile has been saved.');
  }
  redirect_to('profile');
}

$query = $dbh->prepare("SELECT * FROM tblusers WHERE EmailId=:useremail");
$query->bindParam(':useremail', $_SESSION['login'], PDO::PARAM_STR);
$query->execute();
$user = $query->fetch(PDO::FETCH_OBJ);
// dob was free text before; the date picker only accepts Y-m-d.
$dobValue = ($user && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$user->dob)) ? $user->dob : '';
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'My Profile';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo account_start($dbh, 'profile', 'Profile', 'Keep your contact details up to date so we can reach you about bookings.'); ?>
    <div class="r-panel">
      <h2 class="r-panel__title">Personal details</h2>
      <?php if ($user) { ?>
        <p class="r-meta">
          <span>Registered <?php echo htmlentities(date('j M Y', strtotime($user->RegDate))); ?></span>
          <?php if ($user->UpdationDate) { ?><span>Last updated <?php echo htmlentities(date('j M Y, H:i', strtotime($user->UpdationDate))); ?></span><?php } ?>
        </p>
      <?php } ?>
      <?php echo flash_render(); ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="r-form-row">
          <div class="r-field">
            <label class="r-label" for="fullname">Full name <span class="r-required">*</span></label>
            <input class="r-input" id="fullname" name="fullname" value="<?php echo htmlentities($user->FullName ?? ''); ?>" autocomplete="name" required>
          </div>
          <div class="r-field">
            <label class="r-label" for="emailid">Email address</label>
            <input class="r-input" id="emailid" name="emailid" type="email" value="<?php echo htmlentities($user->EmailId ?? ''); ?>" readonly>
            <p class="r-hint">Your email is your login and can't be changed here.</p>
          </div>
        </div>
        <div class="r-form-row">
          <div class="r-field">
            <label class="r-label" for="mobilenumber">Phone number</label>
            <input class="r-input" id="mobilenumber" name="mobilenumber" type="tel" maxlength="10" value="<?php echo htmlentities($user->ContactNo ?? ''); ?>" autocomplete="tel">
          </div>
          <div class="r-field">
            <label class="r-label" for="dob">Date of birth</label>
            <input class="r-input" id="dob" name="dob" type="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlentities($dobValue); ?>" autocomplete="bday">
          </div>
        </div>
        <div class="r-field">
          <label class="r-label" for="address">Address</label>
          <textarea class="r-textarea" id="address" name="address" rows="3" autocomplete="street-address"><?php echo htmlentities($user->Address ?? ''); ?></textarea>
        </div>
        <div class="r-form-row">
          <div class="r-field">
            <label class="r-label" for="city">City</label>
            <input class="r-input" id="city" name="city" value="<?php echo htmlentities($user->City ?? ''); ?>" autocomplete="address-level2">
          </div>
          <div class="r-field">
            <label class="r-label" for="country">Country</label>
            <input class="r-input" id="country" name="country" value="<?php echo htmlentities($user->Country ?? ''); ?>" autocomplete="country-name">
          </div>
        </div>
        <button type="submit" name="updateprofile" class="r-btn r-btn--lg">Save changes</button>
      </form>
    </div>
    <?php echo account_end(); ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
