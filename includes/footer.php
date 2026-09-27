<?php
// Newsletter sign-up (the form below posts to the current page). Handled here because
// the footer is on every page; the result is shown as a toast after redirecting back.
if (isset($_POST['emailsubscibe'])) {
  $subscriberemail = trim((string)($_POST['subscriberemail'] ?? ''));

  if (!filter_var($subscriberemail, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address.');
  } else {
    $subscriberQuery = $dbh->prepare("SELECT id FROM tblsubscribers WHERE SubscriberEmail=:subscriberemail");
    $subscriberQuery->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
    $subscriberQuery->execute();

    if ($subscriberQuery->fetch()) {
      flash_set('info', 'You are already subscribed. Thank you!');
    } else {
      $subscriberInsert = $dbh->prepare("INSERT INTO tblsubscribers(SubscriberEmail) VALUES(:subscriberemail)");
      $subscriberInsert->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
      $subscriberInsert->execute();
      flash_set('success', 'Subscribed! We will send you our best deals.');
    }
  }
  redirect_to(current_url());
}

$contact = site_contact_info($dbh);
// Inline icons, so no icon font has to load for them.
$socialIcons = [
  'facebook' => ['Facebook', '<path d="M13.5 22v-8.2h2.8l.4-3.2h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.3v3.2h2.8V22h3.4z"/>'],
  'instagram' => ['Instagram', '<path d="M12 7.2A4.8 4.8 0 1 0 12 16.8 4.8 4.8 0 0 0 12 7.2zm0 7.9a3.1 3.1 0 1 1 0-6.2 3.1 3.1 0 0 1 0 6.2zm6.1-8.1a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0zM21.9 8.2c-.1-1.5-.4-2.9-1.5-4-1.1-1.1-2.5-1.4-4-1.5-1.6-.1-6.3-.1-7.9 0-1.5.1-2.9.4-4 1.5-1.1 1.1-1.4 2.5-1.5 4-.1 1.6-.1 6.3 0 7.9.1 1.5.4 2.9 1.5 4 1.1 1.1 2.5 1.4 4 1.5 1.6.1 6.3.1 7.9 0 1.5-.1 2.9-.4 4-1.5 1.1-1.1 1.4-2.5 1.5-4 .1-1.6.1-6.3 0-7.9zm-2.1 9.6a3.2 3.2 0 0 1-1.8 1.8c-1.3.5-4.2.4-5.9.4s-4.6.1-5.9-.4a3.2 3.2 0 0 1-1.8-1.8c-.5-1.3-.4-4.2-.4-5.8s-.1-4.6.4-5.8a3.2 3.2 0 0 1 1.8-1.8c1.3-.5 4.2-.4 5.9-.4s4.6-.1 5.9.4a3.2 3.2 0 0 1 1.8 1.8c.5 1.3.4 4.2.4 5.8s.1 4.6-.4 5.8z"/>'],
  'x' => ['X', '<path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L1.9 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/>'],
  'tiktok' => ['TikTok', '<path d="M16.6 2h-3.3v13.4a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.2a6.2 6.2 0 1 0 5.3 6.2V8.6a7.9 7.9 0 0 0 4.6 1.5V6.8a4.6 4.6 0 0 1-4.6-4.8z"/>'],
  'youtube' => ['YouTube', '<path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.7 15V9l5.8 3-5.8 3z"/>'],
  'whatsapp' => ['WhatsApp', '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.1 5.1 0 0 0 1.1 2.7 11.7 11.7 0 0 0 4.5 4c.6.3 1.1.4 1.5.5a3.6 3.6 0 0 0 1.6.1 2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/>'],
  'linkedin' => ['LinkedIn', '<path d="M6.9 21H3.2V9h3.7v12zM5 7.4a2.1 2.1 0 1 1 0-4.3 2.1 2.1 0 0 1 0 4.3zM21 21h-3.7v-5.8c0-1.4 0-3.2-2-3.2s-2.3 1.5-2.3 3.1V21H9.3V9h3.5v1.6h.1c.5-.9 1.7-2 3.5-2 3.8 0 4.5 2.5 4.5 5.7V21z"/>'],
];
// Only networks with a URL (admin -> Site content -> Footer & social) are shown.
$socials = site_social_links();
?>

<footer class="r-footer">
  <div class="container">
    <div class="r-footer__top">
      <div>
        <a class="r-footer__brand" href="./"><?php echo site_wordmark(); ?></a>
        <p><?php echo content('footer_about'); ?></p>
        <?php if ($socials) { ?>
          <ul class="r-social">
            <?php foreach ($socials as $socialNetwork => $socialUrl) {
              if (!isset($socialIcons[$socialNetwork])) {
                continue;
              } ?>
              <li><a href="<?php echo htmlentities($socialUrl); ?>" target="_blank" rel="noopener" aria-label="<?php echo $socialIcons[$socialNetwork][0]; ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $socialIcons[$socialNetwork][1]; ?></svg></a></li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>

      <div>
        <h2 class="r-footer__title">Explore</h2>
        <ul class="r-footer__links">
          <li><a href="car-listing">All cars</a></li>
          <li><a href="car-recommendation">Find my car</a></li>
          <li><a href="about-us">About Rento</a></li>
          <li><a href="contact-us">Contact us</a></li>
        </ul>
      </div>

      <div>
        <h2 class="r-footer__title">Help</h2>
        <ul class="r-footer__links">
          <li><a href="faqs">FAQs</a></li>
          <li><a href="terms">Terms of use</a></li>
          <li><a href="privacy">Privacy policy</a></li>
          <li><a href="admin/">Admin login</a></li>
        </ul>
      </div>

      <div>
        <h2 class="r-footer__title">Get in touch</h2>
        <ul class="r-footer__links r-footer__contact">
          <?php if ($contact->Address) { ?>
            <li><i class="fa fa-location-dot" aria-hidden="true"></i><span><?php echo htmlentities($contact->Address); ?></span></li>
          <?php } ?>
          <?php if ($contact->ContactNo) { ?>
            <li><i class="fa fa-phone" aria-hidden="true"></i><a href="tel:<?php echo htmlentities($contact->ContactNo); ?>"><?php echo htmlentities($contact->ContactNo); ?></a></li>
          <?php } ?>
          <?php if ($contact->EmailId) { ?>
            <li><i class="fa fa-envelope" aria-hidden="true"></i><a href="mailto:<?php echo htmlentities($contact->EmailId); ?>"><?php echo htmlentities($contact->EmailId); ?></a></li>
          <?php } ?>
        </ul>
        <form method="post" class="r-newsletter" aria-label="Newsletter">
          <?php echo csrf_field(); ?>
          <label class="visually-hidden" for="newsletter-email">Email address</label>
          <input type="email" id="newsletter-email" name="subscriberemail" class="r-input" required placeholder="Your email">
          <button type="submit" name="emailsubscibe" class="r-btn r-btn--sm">Subscribe</button>
        </form>
        <p class="r-hint"><?php echo content('footer_newsletter_hint'); ?></p>
      </div>
    </div>

    <div class="r-footer__bottom">
      <p>&copy; <?php echo date('Y'); ?> <?php echo content('footer_copyright'); ?></p>
      <p><?php echo content('footer_tagline'); ?></p>
    </div>
  </div>
</footer>

<button type="button" class="r-back-top" id="r-back-top" aria-label="Back to top">
  <i class="fa fa-arrow-up" aria-hidden="true"></i>
</button>
