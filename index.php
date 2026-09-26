<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

?>

<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Car Rental in Nepal';
  $pageDescription = 'Rento: rent a verified car anywhere in Nepal. Browse hatchbacks, sedans and SUVs, compare daily prices in rupees and book online.';
  $pageUsesCarousel = true;
  include('includes/head.php');
  ?>
</head>

<body>


  <!--Header-->
  <?php include('includes/header.php'); ?>
  <!-- /Header -->

  <!-- Banners -->
  <!-- /Banners -->
  <!-- newly added banner section -->
  <!-- Banners -->
  <div class="banner-carousel owl-carousel owl-theme">

    <section class="banner-section" style="background-image:url(./assets/images/banner-image-1.jpg);">
      <div class="container">
        <div class="div_zindex">
          <div class="row">
            <div class="col-md-5 offset-md-7">
              <div class="banner_content">
                <h1>Find Your Perfect Rental Car</h1>
                <p>Best price guarantee on all our premium vehicles across Nepal.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="banner-section" style="background-image:url(./assets/images/banner-image-2.jpg);">
      <div class="container">
        <div class="div_zindex">
          <div class="row">
            <div class="col-md-5 offset-md-7">
              <div class="banner_content">
                <h1>Drive With Comfort</h1>
                <p>Wide range of cars available for daily and long-term rental.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="banner-section" style="background-image:url(./assets/images/banner-image-2.jpg);">
      <div class="container">
        <div class="div_zindex">
          <div class="row">
            <div class="col-md-5 offset-md-7">
              <div class="banner_content">
                <h1>Drive With Comfort</h1>
                <p>Wide range of cars available for daily and long-term rental.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="banner-section" style="background-image:url(./assets/images/banner-image-1.jpg);">
      <div class="container">
        <div class="div_zindex">
          <div class="row">
            <div class="col-md-5 offset-md-7">
              <div class="banner_content">
                <h1>Find Your Perfect Rental Car</h1>
                <p>Best price guarantee on all our premium vehicles across Nepal.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="banner-section" style="background-image:url(./assets/images/banner-image-1.jpg);">
      <div class="container">
        <div class="div_zindex">
          <div class="row">
            <div class="col-md-5 offset-md-7">
              <div class="banner_content">
                <h1>Find Your Perfect Rental Car</h1>
                <p>Best price guarantee on all our premium vehicles across Nepal.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>
  <!-- /Banners -->

  <section class="section-padding gray-bg">
    <div class="container">
      <div class="section-header text-center">
        <h2>Find the Best <span>CarForYou</span></h2>
        <p>There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in
          some form, by injected humour, or randomised words which don't look even slightly believable. If you are going
          to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of
          text.</p>
      </div>
      <div class="row">

        <!-- Nav tabs -->
        <div class="recent-tab">
          <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#resentnewcar" class="active" role="tab" data-bs-toggle="tab" aria-controls="resentnewcar" aria-selected="true">New Car</a></li>
          </ul>
        </div>


        <div class="tab-content">
          <div role="tabpanel" class="tab-pane show active" id="resentnewcar">
            <div class="car-grid">

              <?php
              $sql = "SELECT tblvehicles.VehiclesTitle, tblbrands.BrandName, tblvehicles.PricePerDay,
                             tblvehicles.FuelType, tblvehicles.ModelYear, tblvehicles.id,
                             tblvehicles.SeatingCapacity, tblvehicles.VehiclesOverview, tblvehicles.Vimage1
                      FROM tblvehicles
                      JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand
                      LIMIT 9";
              $query = $dbh->prepare($sql);
              $query->execute();
              $results = $query->fetchAll(PDO::FETCH_OBJ);

              if ($query->rowCount() > 0) {
                $i = 0;
                foreach ($results as $result) {
                  $delay = $i * 0.12;
                  $overview = $result->VehiclesOverview
                    ? substr(strip_tags($result->VehiclesOverview), 0, 70) . '...'
                    : 'No description available.';
                  ?>

                  <div class="col-list-3 fade-up" style="animation-delay: <?php echo $delay; ?>s;">
                    <div class="recent-car-list">

                      <?php if ($i === 0) { ?>
                        <span class="car-badge">New Car</span>
                      <?php } ?>

                      <div class="car-info-box">
                        <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>" class="car-img-wrap">
                          <?php echo vehicle_image($result->Vimage1, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle), ['sizes' => '(min-width: 992px) 360px, (min-width: 576px) 50vw, 100vw']); ?>
                        </a>
                        <ul class="car-stats">
                          <li><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($result->FuelType); ?>
                          </li>
                          <li><i class="fa fa-calendar"
                              aria-hidden="true"></i><?php echo htmlentities($result->ModelYear); ?>
                          </li>
                          <li><i class="fa fa-user"
                              aria-hidden="true"></i><?php echo htmlentities($result->SeatingCapacity); ?> seats</li>
                        </ul>
                      </div>

                      <div class="car-body">
                        <div class="car-title-m">
                          <h6><a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>">
                              <?php echo htmlentities($result->VehiclesTitle); ?></a></h6>
                          <span class="price"><?php echo format_price($result->PricePerDay); ?><small>/Day</small></span>
                        </div>
                        <div class="inventory_info_m">
                          <p><?php echo htmlentities($overview); ?></p>
                        </div>
                      </div>

                    </div>
                  </div>

                  <?php
                  $i++;
                }
              } else {
                echo '<p class="text-center">No vehicles found.</p>';
              }
              ?>

            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- recently listed cars  -->
  <section class="fun-facts-section">
    <div class="container div_zindex">
      <div class="row">
        <div class="col-lg-3 col-6 col-sm-3">
          <div class="fun-facts-m">
            <div class="cell">
              <h2><i class="fa fa-calendar" aria-hidden="true"></i>40+</h2>
              <p>Years In Business</p>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-6 col-sm-3">
          <div class="fun-facts-m">
            <div class="cell">
              <h2><i class="fa fa-car" aria-hidden="true"></i>1200+</h2>
              <p>New Cars For Sale</p>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-6 col-sm-3">
          <div class="fun-facts-m">
            <div class="cell">
              <h2><i class="fa fa-car" aria-hidden="true"></i>1000+</h2>
              <p>Used Cars For Sale</p>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-6 col-sm-3">
          <div class="fun-facts-m">
            <div class="cell">
              <h2><i class="fa-regular fa-circle-user" aria-hidden="true"></i>600+</h2>
              <p>Satisfied Customers</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>
  <!-- /Fun Facts-->


  <!--Testimonial -->
  <section class="section-padding testimonial-section parallex-bg">
    <div class="container div_zindex">
      <div class="section-header white-text text-center">
        <span class="section-tag">Testimonials</span>
        <h2>Our Satisfied <span>Customers</span></h2>
        <p class="section-sub">Real feedback from renters who trusted us with their journey.</p>
      </div>

      <!-- Trust stats bar -->
      <div class="trust-bar">
        <div class="trust-item">
          <h3>4.9<span>/5</span></h3>
          <p>Average Rating</p>
        </div>
        <div class="trust-divider"></div>
        <div class="trust-item">
          <h3>2,500<span>+</span></h3>
          <p>Happy Customers</p>
        </div>
        <div class="trust-divider"></div>
        <div class="trust-item">
          <h3>120<span>+</span></h3>
          <p>Cities Covered</p>
        </div>
        <div class="trust-divider"></div>
        <div class="trust-item">
          <h3>98<span>%</span></h3>
          <p>Would Recommend</p>
        </div>
      </div>

      <div class="row">
        <div class="testimonial-grid">
          <?php
          $tid = 1;
          $sql = "SELECT tbltestimonial.Testimonial, tblusers.FullName
                  FROM tbltestimonial
                  JOIN tblusers ON tbltestimonial.UserEmail = tblusers.EmailId
                  WHERE tbltestimonial.status = :tid
                  LIMIT 6";
          $query = $dbh->prepare($sql);
          $query->bindParam(':tid', $tid, PDO::PARAM_STR);
          $query->execute();
          $results = $query->fetchAll(PDO::FETCH_OBJ);

          // Rotating accent palette so avatars aren't all the same color
          $avatarColors = [
            ['#ff5a36', '#ffb238'],
            ['#2563eb', '#60a5fa'],
            ['#16a34a', '#4ade80'],
            ['#9333ea', '#c084fc'],
            ['#ea580c', '#fb923c'],
            ['#0891b2', '#22d3ee'],
          ];

          if ($query->rowCount() > 0) {
            $i = 0;
            foreach ($results as $result) {
              $delay = $i * 0.1;
              $nameParts = explode(' ', trim($result->FullName));
              $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
              $colorPair = $avatarColors[$i % count($avatarColors)];
              ?>

              <div class="testimonial-m fade-up" style="animation-delay: <?php echo $delay; ?>s;">


                <div class="testimonial-content">
                  <span class="quote-icon"><i class="fa fa-quote-right" aria-hidden="true"></i></span>

                  <div class="testimonial-stars">
                    <?php for ($s = 0; $s < 5; $s++) { ?>
                      <i class="fa fa-star" aria-hidden="true"></i>
                    <?php } ?>
                  </div>

                  <p class="testimonial-text"><?php echo htmlentities($result->Testimonial); ?></p>

                  <div class="testimonial-heading">
                    <span class="testimonial-avatar"
                      style="background: linear-gradient(135deg, <?php echo $colorPair[0]; ?>, <?php echo $colorPair[1]; ?>);">
                      <?php echo htmlentities($initials); ?>
                    </span>
                    <div class="testimonial-author">
                      <h5><?php echo htmlentities($result->FullName); ?>
                        <i class="fa fa-circle-check verified-badge" aria-hidden="true" title="Verified Customer"></i>
                      </h5>
                      <span class="client-designation">Verified Customer</span>
                    </div>
                  </div>
                </div>


              </div>

              <?php
              $i++;
            }
          } else {
            echo '<p class="text-center white-text">No testimonials yet.</p>';
          }
          ?>
        </div>
      </div>
    </div>

    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>
  <!-- /Testimonial-->


  <!--Footer -->
  <?php include('includes/cta-book-car.php'); ?>
  <?php include('includes/footer.php'); ?>

  <!-- /Footer-->


  <!--Login-Form -->
  <?php include('includes/login.php'); ?>
  <!--/Login-Form -->

  <!--Register-Form -->
  <?php include('includes/registration.php'); ?>

  <!--/Register-Form -->

  <!--Forgot-password-Form -->
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>
  <script>
    $(document).ready(function () {
      $('.banner-carousel').owlCarousel({
        items: 1,
        loop: true,
        nav: false,
        dots: false,
        autoplay: true,
        autoplayTimeout: 3000,
        animateOut: 'fadeOut',
        animateIn: 'fadeIn',
        smartSpeed: 1000
      });
    });
  </script>

</body>


</html>