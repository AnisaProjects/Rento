<?php
// Newsletter sign-up (the form below posts to the current page). Handled here because
// the footer is on every page; the result is shown as a toast after redirecting back.
if (isset($_POST['emailsubscibe'])) {
  $subscriberemail = trim((string)($_POST['subscriberemail'] ?? ''));

  if (!filter_var($subscriberemail, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address.');
  } else {
    $query = $dbh->prepare("SELECT id FROM tblsubscribers WHERE SubscriberEmail=:subscriberemail");
    $query->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
    $query->execute();

    if ($query->fetch()) {
      flash_set('info', 'You are already subscribed. Thank you!');
    } else {
      $query = $dbh->prepare("INSERT INTO tblsubscribers(SubscriberEmail) VALUES(:subscriberemail)");
      $query->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
      $query->execute();
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
  'linkedin' => ['LinkedIn', '<path d="M6.9 21H3.2V9h3.7v12zM5 7.4a2.1 2.1 0 1 1 0-4.3 2.1 2.1 0 0 1 0 4.3zM21 21h-3.7v-5.8c0-1.4 0-3.2-2-3.2s-2.3 1.5-2.3 3.1V21H9.3V9h3.5v1.6h.1c.5-.9 1.7-2 3.5-2 3.8 0 4.5 2.5 4.5 5.7V21z"/>'],
];
$socials = array_filter(SOCIAL_LINKS);
?>

<footer class="r-footer">
  <div class="container">
    <div class="r-footer__top">
      <div>
        <a class="r-footer__brand" href="./">Rent<span>o</span></a>
        <p>Verified cars and clear daily prices in rupees, across Nepal. Booking a car should be the easy part of the
          trip.</p>
        <?php if ($socials) { ?>
          <ul class="r-social">
            <?php foreach ($socials as $network => $url) {
              if (!isset($socialIcons[$network])) {
                continue;
              } ?>
              <li><a href="<?php echo htmlentities($url); ?>" target="_blank" rel="noopener" aria-label="<?php echo $socialIcons[$network][0]; ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $socialIcons[$network][1]; ?></svg></a></li>
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
        <p class="r-hint">Deals and new cars, about once a month.</p>
      </div>
    </div>

    <div class="r-footer__bottom">
      <p>&copy; <?php echo date('Y'); ?> Rento. All rights reserved.</p>
      <p>Car rental in Nepal &middot; prices in NPR</p>
    </div>
  </div>
</footer>

<button type="button" class="r-back-top" id="r-back-top" aria-label="Back to top">
  <i class="fa fa-arrow-up" aria-hidden="true"></i>
</button>
