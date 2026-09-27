<?php
// Sign-up modal. The form posts to the current page; afterwards the visitor is
// redirected back to it and sees the result as a toast (and the right form re-opens).
if (isset($_POST['signup'])) {
  $fname = trim((string)($_POST['fullname'] ?? ''));
  $email = trim((string)($_POST['emailid'] ?? ''));
  $mobile = trim((string)($_POST['mobileno'] ?? ''));
  $plainPassword = (string)($_POST['password'] ?? '');

  $existing = $dbh->prepare("SELECT id FROM tblusers WHERE EmailId = :email");
  $existing->bindParam(':email', $email, PDO::PARAM_STR);
  $existing->execute();

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address.');
    flash_open_modal('signupform');
  } elseif ($plainPassword !== (string)($_POST['confirmpassword'] ?? '')) {
    flash_set('error', 'Password and Confirm Password do not match.');
    flash_open_modal('signupform');
  } elseif ($existing->fetch()) {
    flash_set('error', 'This email is already registered. Please log in instead.');
    flash_open_modal('loginform');
  } else {
    $password = password_hash_for_storage($plainPassword);
    $sql = "INSERT INTO  tblusers(FullName,EmailId,ContactNo,Password) VALUES(:fname,:email,:mobile,:password)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':fname', $fname, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':mobile', $mobile, PDO::PARAM_STR);
    $query->bindParam(':password', $password, PDO::PARAM_STR);
    $query->execute();
    if ($dbh->lastInsertId()) {
      flash_set('success', 'Your account is ready. Please log in.');
      flash_open_modal('loginform');
    } else {
      flash_set('error', 'Something went wrong. Please try again.');
      flash_open_modal('signupform');
    }
  }
  redirect_to(current_url());
}
?>

<script>
  // Tells the visitor whether the email is free (check_availability.php) when they leave the field.
  function checkAvailability() {
    var email = document.getElementById('emailid').value;
    if (!email) return;
    jQuery.ajax({
      url: "check_availability.php",
      data: { emailid: email },
      type: "POST",
      success: function (data) {
        jQuery("#user-availability-status").html(data);
      }
    });
  }

  // Live "passwords match" hint; also blocks submitting mismatched passwords.
  function registration_valid() {
    var form = document.forms["signup"];
    if (form["password"].value !== form["confirmpassword"].value) {
      form["confirmpassword"].focus();
      return false;
    }
    return true;
  }

  document.addEventListener('input', function (event) {
    if (event.target.id !== 'signup-password' && event.target.id !== 'signup-confirmpassword') return;
    var pass = document.getElementById('signup-password').value;
    var confirm = document.getElementById('signup-confirmpassword');
    var hint = document.getElementById('signup-match');
    if (!confirm.value) {
      hint.textContent = '';
      confirm.classList.remove('is-invalid');
      return;
    }
    var ok = pass === confirm.value;
    hint.textContent = ok ? 'Passwords match.' : 'Passwords do not match.';
    hint.className = 'r-hint ' + (ok ? 'r-hint--success' : 'r-hint--error');
    confirm.classList.toggle('is-invalid', !ok);
  });
</script>

<div class="modal fade r-modal" id="signupform" tabindex="-1" aria-labelledby="signupform-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="signupform-title">Create your account</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="r-modal__lead">It takes a minute, and you can book straight away.</p>
        <form method="post" name="signup" onSubmit="return registration_valid();">
          <?php echo csrf_field(); ?>
          <div class="r-field">
            <label class="r-label" for="signup-fullname">Full name</label>
            <input type="text" id="signup-fullname" name="fullname" class="r-input" autocomplete="name" required>
          </div>
          <div class="r-form-row">
            <div class="r-field">
              <label class="r-label" for="emailid">Email address</label>
              <input type="email" id="emailid" name="emailid" class="r-input" autocomplete="email" onBlur="checkAvailability()" required>
              <div id="user-availability-status" class="r-hint" aria-live="polite"></div>
            </div>
            <div class="r-field">
              <label class="r-label" for="signup-mobile">Mobile number</label>
              <input type="tel" id="signup-mobile" name="mobileno" class="r-input" autocomplete="tel" maxlength="10" required>
            </div>
          </div>
          <div class="r-form-row">
            <div class="r-field">
              <label class="r-label" for="signup-password">Password</label>
              <div class="r-password">
                <input type="password" id="signup-password" name="password" class="r-input" autocomplete="new-password" required>
                <button type="button" class="r-password__toggle" aria-controls="signup-password" aria-label="Show password" aria-pressed="false">
                  <i class="fa fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>
            <div class="r-field">
              <label class="r-label" for="signup-confirmpassword">Confirm password</label>
              <input type="password" id="signup-confirmpassword" name="confirmpassword" class="r-input" autocomplete="new-password" required>
              <div id="signup-match" class="r-hint" aria-live="polite"></div>
            </div>
          </div>
          <div class="r-field">
            <label class="r-check" for="terms_agree">
              <input type="checkbox" id="terms_agree" required>
              <span>I agree to the <a href="terms" target="_blank" rel="noopener">Terms and Conditions</a></span>
            </label>
          </div>
          <button type="submit" value="Sign Up" name="signup" id="submit" class="r-btn r-btn--block r-btn--lg">Create account</button>
        </form>
      </div>
      <div class="modal-footer">
        <p>Already have an account? <a href="#loginform" data-bs-toggle="modal">Log in</a></p>
      </div>
    </div>
  </div>
</div>
