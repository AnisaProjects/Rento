<?php

if (isset($_POST['login'])) {

  $email = trim($_POST['email']);
  $password = (string)($_POST['password'] ?? '');

  if (rate_limit_exceeded($dbh, 'login', $email)) {
    echo "<script type='text/javascript'>
                alert(" . json_encode(RATE_LIMIT_MESSAGE) . ");
              </script>";
  } else {

    $sql = "SELECT id, EmailId, Password, FullName
              FROM tblusers
              WHERE EmailId = :email";

    $query = $dbh->prepare($sql);

    $query->bindParam(':email', $email, PDO::PARAM_STR);

    $query->execute();

    $result = $query->fetch(PDO::FETCH_OBJ);

    // Old MD5 hashes are accepted once and replaced with a bcrypt hash.
    $validLogin = $result && password_check_and_upgrade($password, $result->Password, function ($newHash) use ($dbh, $result) {
      $upgrade = $dbh->prepare("UPDATE tblusers SET Password = :password WHERE id = :id");
      $upgrade->bindParam(':password', $newHash, PDO::PARAM_STR);
      $upgrade->bindParam(':id', $result->id, PDO::PARAM_INT);
      $upgrade->execute();
    });

    if ($validLogin) {

      rate_limit_clear($dbh, 'login', $email);
      session_login_regenerate();

      // Store login information in session
      $_SESSION['login'] = $result->EmailId;
      $_SESSION['fname'] = $result->FullName;

      // Redirect to current page
      $currentpage = json_encode($_SERVER['REQUEST_URI'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

      echo "<script type='text/javascript'>
                  document.location = $currentpage;
                </script>";

      exit();

    } else {

      rate_limit_record($dbh, 'login', $email);
      echo "<script type='text/javascript'>
                  alert('Invalid Email or Password');
                </script>";
    }
  }
}

?>


<!-- Login Modal -->
<div class="modal fade" id="loginform" tabindex="-1" aria-labelledby="loginform-title" aria-hidden="true">

  <div class="modal-dialog" role="document">

    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header">

        <h3 class="modal-title" id="loginform-title">Login</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>


      <!-- Body -->
      <div class="modal-body">

        <div class="row">

          <div class="login_wrap">

            <div class="col-md-12 col-sm-6">

              <form method="post">
<?php echo csrf_field(); ?>

                <!-- Email -->
                <div class="form-group">

                  <label class="visually-hidden" for="login-email">Email address</label>
                  <input id="login-email" type="email" class="form-control" name="email" placeholder="Email address*" required>

                </div>


                <!-- Password -->
                <div class="form-group">

                  <label class="visually-hidden" for="login-password">Password</label>
                  <input id="login-password" type="password" class="form-control" name="password" placeholder="Password*" required>

                </div>


                <!-- Remember Me -->
                <div class="form-group checkbox">

                  <input type="checkbox" id="remember">

                  <label for="remember">
                    Remember Me
                  </label>

                </div>


                <!-- Login Button -->
                <div class="form-group">

                  <input type="submit" name="login" value="Login" class="btn btn-block">

                </div>

              </form>

            </div>

          </div>

        </div>

      </div>


      <!-- Footer -->
      <div class="modal-footer text-center">

        <p>
          Don't have an account?
          <a href="#signupform" data-bs-toggle="modal">
            Signup Here
          </a>
        </p>

        <p>
          <a href="#forgotpassword" data-bs-toggle="modal">
            Forgot Password?
          </a>
        </p>

      </div>

    </div>

  </div>

</div>