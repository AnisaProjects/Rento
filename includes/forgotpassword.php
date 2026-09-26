<div class="modal fade" id="forgotpassword" tabindex="-1" aria-labelledby="forgotpassword-title" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">

        <h3 class="modal-title" id="forgotpassword-title">Password Recovery</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="forgotpassword_wrap">
            <div class="col-md-12">
              <!-- Requests a time-limited reset link; the new password is set on reset-password.php -->
              <form name="chngpwd" method="post" action="reset-password.php">
<?php echo csrf_field(); ?>
                <div class="form-group">
                  <label class="visually-hidden" for="forgot-email">Email address</label>
                  <input id="forgot-email" type="email" name="email" class="form-control" placeholder="Your Email address*" required="">
                </div>
                <div class="form-group">
                  <input type="submit" value="Send Reset Link" name="update" class="btn btn-block">
                </div>
              </form>
              <div class="text-center">
                <p class="gray_text">Enter your registered email address and we will send you a link to reset your
                  password. The link is valid for 30 minutes.</p>
                <p><a href="#loginform" data-bs-toggle="modal"><i class="fa fa-angles-left"
                      aria-hidden="true"></i> Back to Login</a></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
