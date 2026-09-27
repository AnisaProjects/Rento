<?php
// Email: optional SMTP settings (tblmailsettings) and "Send test email". When set up, the site emails
// password reset links, booking confirmations and package enquiry confirmations (includes/mailer.php).
// Without it, development mode keeps showing reset links on screen and nothing is emailed.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

$accountEmail = $dbh->prepare("SELECT Email FROM admin WHERE UserName = :username");
$accountEmail->execute([':username' => $_SESSION['alogin']]);
$accountEmail = (string)$accountEmail->fetchColumn();

if (isset($_POST['savemail'])) {
  $in = [
    'smtp_host' => mb_substr(trim((string)($_POST['smtphost'] ?? '')), 0, 190),
    'smtp_port' => (string)(int)($_POST['smtpport'] ?? 587),
    'smtp_encryption' => (string)($_POST['smtpencryption'] ?? 'tls'),
    'smtp_username' => mb_substr(trim((string)($_POST['smtpusername'] ?? '')), 0, 190),
    'smtp_password' => (string)($_POST['smtppassword'] ?? ''),
    'mail_from' => trim((string)($_POST['mailfrom'] ?? '')),
    'mail_from_name' => mb_substr(trim((string)($_POST['mailfromname'] ?? '')), 0, 100),
    'mail_reply_to' => trim((string)($_POST['mailreplyto'] ?? '')),
  ];
  $error = match (true) {
    $in['smtp_host'] !== '' && !preg_match('/^[a-z0-9.\-]+$/i', $in['smtp_host']) => 'The SMTP server should be a host name such as smtp.example.com.',
    (int)$in['smtp_port'] < 1 || (int)$in['smtp_port'] > 65535 => 'The port should be a number between 1 and 65535 (usually 587 or 465).',
    !isset(MAIL_ENCRYPTIONS[$in['smtp_encryption']]) => 'Please choose the encryption.',
    $in['mail_from'] !== '' && !filter_var($in['mail_from'], FILTER_VALIDATE_EMAIL) => 'The "From" address is not a valid email address.',
    $in['smtp_host'] !== '' && $in['mail_from'] === '' => 'Please enter the "From" address the emails are sent from.',
    $in['mail_reply_to'] !== '' && !filter_var($in['mail_reply_to'], FILTER_VALIDATE_EMAIL) => 'The reply-to address is not a valid email address.',
    $in['smtp_password'] !== '' && mail_key() === null => 'APP_KEY is not set (includes/config.local.php or RENTO_APP_KEY), so the password cannot be stored safely.',
    default => null,
  };
  if ($error) {
    flash_set('error', $error);
  } else {
    mail_save_settings($dbh, $in, !empty($_POST['clearpassword']));
    flash_set('success', $in['smtp_host'] === '' ? 'Saved. Email stays off until an SMTP server is entered.' : 'Email settings saved. Send a test email to check them.');
  }
  redirect_to('email-settings.php');
}

if (isset($_POST['testmail'])) {
  $to = trim((string)($_POST['testto'] ?? ''));
  [$html, $text] = mail_layout('Test email', [
    'This is a test from ' . content_raw('site_name') . ' admin → Email. If you can read it, password reset links, booking confirmations and enquiry confirmations will reach your customers.',
    'Sent ' . date('j M Y, H:i') . ' (Nepal time).',
  ]);
  if (mail_send($dbh, $to, 'Test email | ' . content_raw('site_name'), $html, $text, $error, 'test')) {
    flash_set('success', 'Test email sent to ' . $to . '. Check the inbox (and the spam folder).');
  } else {
    flash_set('error', 'The test email was not sent: ' . $error);
  }
  redirect_to('email-settings.php');
}

$s = mail_settings($dbh);
$configured = mail_configured($dbh);
$library = mail_library_available();
$logLines = is_file(MAIL_LOG) ? array_slice(file(MAIL_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -8) : [];

admin_page_start('Email', 'email-settings.php', [], [
  'lead' => $configured ? 'Email is on: reset links, booking and enquiry confirmations are sent through ' . $s['smtp_host'] . '.'
    : 'Email is off. ' . (APP_DEV_MODE ? 'Development mode shows password reset links on screen instead.' : 'Password reset links cannot be sent until it is set up.'),
]);
?>
<?php if (!$library) { ?>
  <div class="a-notice a-notice--warning mb-3" role="note"><i class="fa fa-triangle-exclamation mt-1" aria-hidden="true"></i><div>
    PHPMailer is not installed, so no email can be sent. Run <code>composer install --no-dev</code> in the site folder (docs/deployment.md).
  </div></div>
<?php } ?>
<div class="a-grid-2">
  <section class="a-card" aria-labelledby="smtp-title">
    <div class="a-card__head"><h2 class="a-card__title" id="smtp-title"><i class="fa fa-server" aria-hidden="true"></i>SMTP server</h2>
      <?php echo $configured ? admin_badge('On', 'success', 'fa-circle-check') : admin_badge('Off'); ?></div>
    <form method="post" class="a-card__body a-form-grid">
      <?php echo csrf_field(); ?>
      <p class="a-span-all a-muted mb-0">Use the SMTP details from your email provider (for example your hosting company, Google Workspace, Zoho or an email service). Leave the server empty to switch email off.</p>
      <div class="a-span-all">
        <label class="form-label" for="smtphost">SMTP server</label>
        <input class="form-control" id="smtphost" name="smtphost" maxlength="190" value="<?php echo h($s['smtp_host']); ?>" placeholder="smtp.example.com" autocomplete="off" spellcheck="false">
      </div>
      <div>
        <label class="form-label" for="smtpport">Port</label>
        <input class="form-control" id="smtpport" name="smtpport" type="number" min="1" max="65535" value="<?php echo h($s['smtp_port']); ?>">
      </div>
      <div>
        <label class="form-label" for="smtpencryption">Encryption</label>
        <select class="form-select" id="smtpencryption" name="smtpencryption">
          <?php foreach (MAIL_ENCRYPTIONS as $key => $label) { ?><option value="<?php echo h($key); ?>"<?php echo $s['smtp_encryption'] === $key ? ' selected' : ''; ?>><?php echo h($label); ?></option><?php } ?>
        </select>
      </div>
      <div>
        <label class="form-label" for="smtpusername">Username</label>
        <input class="form-control" id="smtpusername" name="smtpusername" maxlength="190" value="<?php echo h($s['smtp_username']); ?>" autocomplete="off" spellcheck="false">
      </div>
      <div>
        <label class="form-label" for="smtppassword">Password</label>
        <div class="a-pass">
          <input class="form-control" id="smtppassword" name="smtppassword" type="password" autocomplete="new-password" aria-describedby="smtppassword-hint" placeholder="<?php echo $s['has_password'] ? 'Saved, leave empty to keep it' : ''; ?>">
          <button type="button" data-a-pass="smtppassword" aria-label="Show password"><i class="fa fa-eye" aria-hidden="true"></i></button>
        </div>
        <div class="form-text" id="smtppassword-hint">Stored encrypted and never shown again.<?php if ($s['has_password']) { ?> <label class="ms-1"><input type="checkbox" class="form-check-input me-1" name="clearpassword" value="1">Remove the saved password</label><?php } ?></div>
      </div>
      <div>
        <label class="form-label" for="mailfrom">"From" address</label>
        <input class="form-control" id="mailfrom" name="mailfrom" type="email" maxlength="150" value="<?php echo h($s['mail_from']); ?>" placeholder="bookings@example.com">
      </div>
      <div>
        <label class="form-label" for="mailfromname">"From" name</label>
        <input class="form-control" id="mailfromname" name="mailfromname" maxlength="100" value="<?php echo h($s['mail_from_name']); ?>" placeholder="<?php echo content('site_name'); ?>">
      </div>
      <div class="a-span-all">
        <label class="form-label" for="mailreplyto">Reply-to address <span class="a-muted">(optional)</span></label>
        <input class="form-control" id="mailreplyto" name="mailreplyto" type="email" maxlength="150" value="<?php echo h($s['mail_reply_to']); ?>">
      </div>
      <div class="a-span-all d-flex justify-content-end">
        <button class="btn btn-primary" name="savemail" type="submit"><i class="fa fa-floppy-disk" aria-hidden="true"></i>Save settings</button>
      </div>
    </form>
  </section>
  <div>
    <section class="a-card" aria-labelledby="test-title">
      <div class="a-card__head"><h2 class="a-card__title" id="test-title"><i class="fa fa-paper-plane" aria-hidden="true"></i>Send test email</h2></div>
      <form method="post" class="a-card__body">
        <?php echo csrf_field(); ?>
        <label class="form-label" for="testto">Send to</label>
        <input class="form-control mb-3" id="testto" name="testto" type="email" required value="<?php echo h($accountEmail); ?>" placeholder="you@example.com">
        <button class="btn btn-light w-100" name="testmail" type="submit"<?php echo $configured ? '' : ' disabled aria-describedby="test-off"'; ?>><i class="fa fa-paper-plane" aria-hidden="true"></i>Send test email</button>
        <?php if (!$configured) { ?><p class="form-text mb-0" id="test-off">Save an SMTP server and a "From" address first.</p><?php } ?>
      </form>
    </section>
    <section class="a-card mt-4" aria-labelledby="what-title">
      <div class="a-card__head"><h2 class="a-card__title" id="what-title"><i class="fa fa-list-check" aria-hidden="true"></i>What is sent</h2></div>
      <div class="a-card__body">
        <ul class="mb-3">
          <li>Password reset links: customers, and admins who saved an <a href="change-password.php">account email</a><?php echo $accountEmail === '' ? ' (<strong>yours is not set</strong>)' : ''; ?>.</li>
          <li>"We received your booking request", with the saved price.</li>
          <li>"Your booking is confirmed", when you confirm a booking.</li>
          <li>"We received your request" for package and with-driver enquiries that include an email address.</li>
        </ul>
        <h3 class="fs-6">Recent sends</h3>
        <?php if ($logLines) { ?>
          <ul class="a-muted small mb-0"><?php foreach (array_reverse($logLines) as $line) { ?><li><?php echo h($line); ?></li><?php } ?></ul>
        <?php } else { ?><p class="a-muted small mb-0">Nothing sent yet.</p><?php } ?>
      </div>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
