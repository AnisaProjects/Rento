<div class="modal fade" id="forgotpassword">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">

        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
        <h3 class="modal-title">Password Recovery</h3>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="forgotpassword_wrap">
            <div class="col-md-12">
              <!-- Requests a time-limited reset link; the new password is set on reset-password.php -->
              <form name="chngpwd" method="post" action="reset-password.php">
<?php echo csrf_field(); ?>
                <div class="form-group">
                  <input type="email" name="email" class="form-control" placeholder="Your Email address*" required="">
                </div>
                <div class="form-group">
                  <input type="submit" value="Send Reset Link" name="update" class="btn btn-block">
                </div>
              </form>
              <div class="text-center">
                <p class="gray_text">Enter your registered email address and we will send you a link to reset your
                  password. The link is valid for 30 minutes.</p>
                <p><a href="#loginform" data-toggle="modal" data-dismiss="modal"><i class="fa fa-angle-double-left"
                      aria-hidden="true"></i> Back to Login</a></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
