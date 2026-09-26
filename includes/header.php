<header>
  <div class="default-header">
    <div class="container">
      <div class="row">
        <div class="col-5 col-sm-3 col-md-2">
          <div class="logo"> <a href="index.php"><img src="assets/images/rentoindia_logo.jpeg" alt="Rento" /></a> </div>
        </div>
        <div class="col-7 col-sm-9 col-md-10">
          <div class="header_info">
            <?php
            $sql = "SELECT EmailId,ContactNo from tblcontactusinfo";
            $query = $dbh->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
            foreach ($results as $result) {
              $email = $result->EmailId;
              $contactno = $result->ContactNo;
            }
            ?>

            <div class="header_widgets">
              <div class="circle_icon"> <i class="fa fa-envelope" aria-hidden="true"></i> </div>
              <p class="uppercase_text">For Support Mail us : </p>
              <a href="mailto:<?php echo htmlentities($email); ?>"><?php echo htmlentities($email); ?></a>
            </div>
            <div class="header_widgets">
              <div class="circle_icon"> <i class="fa fa-phone" aria-hidden="true"></i> </div>
              <p class="uppercase_text">Service Helpline Call Us: </p>
              <a href="tel:<?php echo htmlentities($contactno); ?>"><?php echo htmlentities($contactno); ?></a>
            </div>
            <div class="social-follow">

            </div>
            <?php if (empty($_SESSION['login'])) {
              ?>
              <div class="login_btn"> <a href="#loginform" class="btn btn-xs uppercase" data-bs-toggle="modal">Login / Register</a> </div>
            <?php } else {

              echo "WELCOME TO RENTO";
            } ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav id="navigation_bar" class="navbar navbar-expand-md">
    <div class="container">
      <button id="menu_slide" class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse"
        data-bs-target="#navigation" aria-controls="navigation" aria-expanded="false">
        <span class="visually-hidden">Toggle navigation</span>
        <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span>
      </button>
      <div class="header_wrap">
        <?php if (isset($_SESSION['login']) && !empty($_SESSION['login'])) { ?>

          <div class="user_login">
            <ul>
              <li class="dropdown">
                <a href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                  <i class="fa fa-circle-user" aria-hidden="true"></i>

                  <?php
                  $user_email = $_SESSION['login'];

                  $sql = "SELECT FullName FROM tblusers WHERE EmailId=:email";
                  $query = $dbh->prepare($sql);
                  $query->bindParam(':email', $user_email, PDO::PARAM_STR);
                  $query->execute();

                  $result = $query->fetch(PDO::FETCH_OBJ);

                  if ($result) {
                    echo htmlentities($result->FullName);
                  }
                  ?>

                  <i class="fa fa-angle-down" aria-hidden="true"></i>
                </a>

                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="profile.php">Profile Settings</a></li>
                  <li><a class="dropdown-item" href="update-password.php">Update Password</a></li>
                  <li><a class="dropdown-item" href="my-booking.php">My Booking</a></li>
                  <li><a class="dropdown-item" href="post-testimonial.php">Post a Testimonial</a></li>
                  <li><a class="dropdown-item" href="my-testimonials.php">My Testimonial</a></li>
                  <li><a class="dropdown-item" href="logout.php">Sign Out</a></li>
                </ul>

              </li>
            </ul>
          </div>

        <?php } ?>
        <div class="header_search">
          <button type="button" id="search_toggle" aria-label="Show search" aria-controls="header-search-form"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></button>
          <form action="search.php" method="post" id="header-search-form">
<?php echo csrf_field(); ?>
            <input type="text" placeholder="Search..." name="searchdata" class="form-control" required="true" aria-label="Search cars">
            <button type="submit" aria-label="Search"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></button>
          </form>
        </div>
      </div>
      <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
      <div class="collapse navbar-collapse" id="navigation">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link<?php echo ($current_page == 'index.php') ? ' active" aria-current="page' : ''; ?>" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?php echo ($current_page == 'about-us.php') ? ' active" aria-current="page' : ''; ?>" href="about-us.php">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?php echo ($current_page == 'car-listing.php') ? ' active" aria-current="page' : ''; ?>" href="car-listing.php">Car Listing</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?php echo ($current_page == 'faqs.php') ? ' active" aria-current="page' : ''; ?>" href="faqs.php">FAQs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?php echo ($current_page == 'contact-us.php') ? ' active" aria-current="page' : ''; ?>" href="contact-us.php">Contact Us</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  <!-- Navigation end -->

</header>