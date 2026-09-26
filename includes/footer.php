<?php
if (isset($_POST['emailsubscibe'])) {
  $subscriberemail = $_POST['subscriberemail'];

  $sql = "SELECT SubscriberEmail FROM tblsubscribers WHERE SubscriberEmail=:subscriberemail";
  $query = $dbh->prepare($sql);
  $query->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
  $query->execute();

  $results = $query->fetchAll(PDO::FETCH_OBJ);

  if ($query->rowCount() > 0) {

    echo "<script>alert('Already Subscribed.');</script>";

  } else {

    $sql = "INSERT INTO tblsubscribers(SubscriberEmail) 
            VALUES(:subscriberemail)";

    $query = $dbh->prepare($sql);
    $query->bindParam(':subscriberemail', $subscriberemail, PDO::PARAM_STR);
    $query->execute();

    $lastInsertId = $dbh->lastInsertId();

    if ($lastInsertId) {
      echo "<script>alert('Subscribed successfully.');</script>";
    } else {
      echo "<script>alert('Something went wrong. Please try again');</script>";
    }
  }
}
?>


<!-- ==================== FOOTER ==================== -->

<footer class="site-footer">

  <!-- Road / moving line -->
  <div class="road"></div>


  <!-- ==================== FOOTER TOP ==================== -->

  <div class="footer-top">

    <div class="container footer-top__grid">


      <!-- ==================== BRAND ==================== -->

      <div class="f-brand">

        <div class="f-brand__logo">
          Rent<span>o</span>
        </div>

        <p class="f-brand__tagline">
          Trusted, verified drivers and a growing fleet across 120+ cities
          in Nepal. Booking a car should be the easy part of the trip.
        </p>

        <div class="f-social">

          <a href="#" aria-label="Facebook">
            <i class="fa fa-facebook" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Twitter">
            <i class="fa fa-twitter" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="LinkedIn">
            <i class="fa fa-linkedin" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Google Plus">
            <i class="fa fa-google-plus" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Instagram">
            <i class="fa fa-instagram" aria-hidden="true"></i>
          </a>

        </div>

      </div>


      <!-- ==================== FOOTER LINKS ==================== -->

      <div class="f-links">


        <!-- ABOUT US -->

        <div class="f-col">

          <h6>About Us</h6>

          <ul>

            <li>
              <a href="page.php?type=aboutus">
                About Us
              </a>
            </li>

            <li>
              <a href="page.php?type=faqs">
                FAQs
              </a>
            </li>

            <li>
              <a href="page.php?type=contact">
                Contact Us
              </a>
            </li>

          </ul>

        </div>


        <!-- LEGAL -->

        <div class="f-col">

          <h6>Legal</h6>

          <ul>

            <li>
              <a href="page.php?type=privacy">
                Privacy
              </a>
            </li>

            <li>
              <a href="page.php?type=terms">
                Terms of Use
              </a>
            </li>

            <li>
              <a href="admin/">
                Admin Login
              </a>
            </li>

          </ul>

        </div>


        <!-- NEWSLETTER -->

        <div class="f-col f-newsletter">

          <h6>Subscribe Newsletter</h6>

          <form method="post">

            <div class="newsletter-form">

              <input type="email" name="subscriberemail" class="newsletter-input" required
                placeholder="Enter email address" />

              <button type="submit" name="emailsubscibe" class="newsletter-btn">
                Subscribe

                <i class="fa fa-angle-right" aria-hidden="true">
                </i>

              </button>

            </div>

          </form>

          <p class="hint">
            *We send great deals and the latest auto news to
            subscribed users every week.
          </p>

        </div>


      </div>
      <!-- END f-links -->


    </div>
    <!-- END container -->

  </div>
  <!-- END footer-top -->


  <!-- ==================== FOOTER BOTTOM ==================== -->

  <div class="footer-bottom">

    <div class="container footer-bottom__row">


      <!-- COPYRIGHT -->

      <p class="copy-right">
        Copyright &copy;
        <?php echo date('Y'); ?>
        Rento. All Rights Reserved
      </p>


      <!-- CONNECT -->

      <div class="connect">

        <p>Connect with us</p>

        <div class="f-social">

          <a href="#" aria-label="Facebook">
            <i class="fa fa-facebook" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Twitter">
            <i class="fa fa-twitter" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="LinkedIn">
            <i class="fa fa-linkedin" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Google Plus">
            <i class="fa fa-google-plus" aria-hidden="true"></i>
          </a>

          <a href="#" aria-label="Instagram">
            <i class="fa fa-instagram" aria-hidden="true"></i>
          </a>

        </div>

      </div>


    </div>

  </div>
  <!-- END footer-bottom -->


</footer>


<!-- ==================== BACK TO TOP ==================== -->

<a href="#top" class="back-top" id="backTop" aria-label="Back to top">
  <i class="fa fa-angle-up" aria-hidden="true"></i>
</a>


<!-- ==================== BACK TO TOP SCRIPT ==================== -->

<script>
  (function () {

    var backTop = document.getElementById('backTop');

    if (!backTop) return;

    window.addEventListener('scroll', function () {

      if (window.scrollY > 500) {

        backTop.classList.add('show');

      } else {

        backTop.classList.remove('show');

      }

    });

  })();
</script>