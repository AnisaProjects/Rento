<?php
if (isset($_POST['signup'])) {
  $fname = $_POST['fullname'];
  $email = trim($_POST['emailid']);
  $mobile = $_POST['mobileno'];
  $plainPassword = (string)($_POST['password'] ?? '');

  $existing = $dbh->prepare("SELECT id FROM tblusers WHERE EmailId = :email");
  $existing->bindParam(':email', $email, PDO::PARAM_STR);
  $existing->execute();

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Please enter a valid email address.');</script>";
  } elseif ($plainPassword !== (string)($_POST['confirmpassword'] ?? '')) {
    echo "<script>alert('Password and Confirm Password do not match.');</script>";
  } elseif ($existing->fetch()) {
    echo "<script>alert('This email is already registered. Please login instead.');</script>";
  } else {
    $password = password_hash_for_storage($plainPassword);
    $sql = "INSERT INTO  tblusers(FullName,EmailId,ContactNo,Password) VALUES(:fname,:email,:mobile,:password)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':fname', $fname, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':mobile', $mobile, PDO::PARAM_STR);
    $query->bindParam(':password', $password, PDO::PARAM_STR);
    $query->execute();
    $lastInsertId = $dbh->lastInsertId();
    if ($lastInsertId) {
      echo "<script>alert('Registration successfull. Now you can login');</script>";
    } else {
      echo "<script>alert('Something went wrong. Please try again');</script>";
    }
  }
}

?>


<script>
  function checkAvailability() {
    $("#loaderIcon").show();
    jQuery.ajax({
      url: "check_availability.php",
      data: 'emailid=' + $("#emailid").val(),
      type: "POST",
      success: function (data) {
        $("#user-availability-status").html(data);
        $("#loaderIcon").hide();
      },
      error: function () { }
    });
  }
</script>
<script type="text/javascript">
  function registration_valid() {
    var password = document.forms["signup"]["password"].value.trim();
    var confirmpassword = document.forms["signup"]["confirmpassword"].value.trim();
    if (password !== confirmpassword) {
      alert("Password and Confirm Password do not match.");
      document.forms["signup"]["confirmpassword"].focus();
      return false;
    }
    return true;
  }
</script>
<div class="modal fade" id="signupform" tabindex="-1" aria-labelledby="signupform-title" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="signupform-title">Sign Up</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="signup_wrap">
            <div class="col-md-12 col-sm-6">
              <form method="post" name="signup" onSubmit="return registration_valid();">
<?php echo csrf_field(); ?>
                <div class="form-group">
                  <label class="visually-hidden" for="signup-fullname">Full name</label>
                  <input id="signup-fullname" type="text" class="form-control" name="fullname" placeholder="Full Name" required="required">
                </div>
                <div class="form-group">
                  <label class="visually-hidden" for="signup-mobile">Mobile number</label>
                  <input id="signup-mobile" type="text" class="form-control" name="mobileno" placeholder="Mobile Number" maxlength="10"
                    required="required">
                </div>
                <div class="form-group">
                  <label class="visually-hidden" for="emailid">Email address</label>
                  <input type="email" class="form-control" name="emailid" id="emailid" onBlur="checkAvailability()"
                    placeholder="Email Address" required="required">
                  <span id="user-availability-status" style="font-size:12px;"></span>
                </div>
                <div class="form-group">
                  <label class="visually-hidden" for="signup-password">Password</label>
                  <input id="signup-password" type="password" class="form-control" name="password" placeholder="Password"
                    required="required">
                </div>
                <div class="form-group">
                  <label class="visually-hidden" for="signup-confirmpassword">Confirm password</label>
                  <input id="signup-confirmpassword" type="password" class="form-control" name="confirmpassword" placeholder="Confirm Password"
                    required="required">
                </div>
                <div class="form-group checkbox">
                  <input type="checkbox" id="terms_agree" required="required" checked="">
                  <label for="terms_agree">I Agree with <a href="terms" target="_blank" rel="noopener">Terms and Conditions</a></label>
                </div>
                <div class="form-group">
                  <input type="submit" value="Sign Up" name="signup" id="submit" class="btn btn-block">
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
      <div class="modal-footer text-center">
        <p>Already got an account? <a href="#loginform" data-bs-toggle="modal">Login Here</a></p>
      </div>
    </div>
  </div>
</div>