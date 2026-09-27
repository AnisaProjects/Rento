<div class="modal fade r-modal" id="forgotpassword" tabindex="-1" aria-labelledby="forgotpassword-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="forgotpassword-title">Reset your password</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="r-modal__lead">Enter the email you registered with and we'll send you a link to set a new password.
          The link is valid for 30 minutes.</p>
        <!-- Requests a time-limited reset link; the new password is set on reset-password.php -->
        <form name="chngpwd" method="post" action="reset-password">
          <?php echo csrf_field(); ?>
          <div class="r-field">
            <label class="r-label" for="forgot-email">Email address</label>
            <input id="forgot-email" type="email" name="email" class="r-input" autocomplete="email" required>
          </div>
          <button type="submit" value="Send Reset Link" name="update" class="r-btn r-btn--block r-btn--lg">Send reset link</button>
        </form>
      </div>
      <div class="modal-footer">
        <p><a href="#loginform" data-bs-toggle="modal"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to log in</a></p>
      </div>
    </div>
  </div>
</div>
