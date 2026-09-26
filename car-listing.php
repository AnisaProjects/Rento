<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
error_reporting(0);
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Car Listing';
  $pageDescription = 'Browse every car available to rent with Rento: prices per day in rupees, fuel type, seats and model year.';
  include('includes/head.php');
  ?>
</head>

<body>


  <!--Header-->
  <?php include('includes/header.php'); ?>
  <!-- /Header -->

  <!--Page Header-->
  <section class="page-header listing_page">
    <div class="container">
      <div class="page-header_wrap">
        <div class="page-heading">
          <h1>Car Listing</h1>
        </div>
        <ul class="coustom-breadcrumb">
          <li><a href="index.php">Home</a></li>
          <li>Car Listing</li>
        </ul>
      </div>
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>
  <!-- /Page Header-->

  <!--Listing-->
  <section class="listing-page listing-page--modern">
    <div class="container">
      <div class="row">
        <div class="col-md-9 order-md-2">

          <?php
          //Query for Listing count
          $sql = "SELECT id from tblvehicles";
          $query = $dbh->prepare($sql);
          $query->execute();
          $results = $query->fetchAll(PDO::FETCH_OBJ);
          $cnt = $query->rowCount();
          ?>
          <div class="listing-toolbar">
            <p class="listing-toolbar__count"><span><?php echo htmlentities($cnt); ?></span> Listings Found</p>
          </div>

          <div class="listing-grid">
            <?php
            $sql = "SELECT tblvehicles.*,tblbrands.BrandName,tblbrands.id as bid  from tblvehicles join tblbrands on tblbrands.id=tblvehicles.VehiclesBrand";
            $query = $dbh->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
            $cnt = 1;
            if ($query->rowCount() > 0) {
              foreach ($results as $result) {
                $overview = isset($result->VehiclesOverview) && $result->VehiclesOverview
                  ? substr(strip_tags($result->VehiclesOverview), 0, 90) . '...'
                  : null;
                ?>
                <div class="listing-card">
                  <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>" class="listing-card__img">
                    <?php echo vehicle_image($result->Vimage1, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle), ['sizes' => '(min-width: 992px) 300px, (min-width: 768px) 40vw, 100vw']); ?>
                  </a>
                  <div class="listing-card__body">
                    <div class="listing-card__top">
                      <div>
                        <span class="listing-card__brand"><?php echo htmlentities($result->BrandName); ?></span>
                        <h5 class="listing-card__title">
                          <a
                            href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>"><?php echo htmlentities($result->VehiclesTitle); ?></a>
                        </h5>
                      </div>
                      <div class="listing-card__price">
                        <?php echo format_price($result->PricePerDay); ?><small>/day</small>
                      </div>
                    </div>

                    <?php if ($overview) { ?>
                      <p class="listing-card__desc"><?php echo htmlentities($overview); ?></p>
                    <?php } ?>

                    <ul class="listing-card__stats">
                      <li><i class="fa fa-user" aria-hidden="true"></i><?php echo htmlentities($result->SeatingCapacity); ?>
                        seats</li>
                      <li><i class="fa fa-calendar" aria-hidden="true"></i><?php echo htmlentities($result->ModelYear); ?>
                        model</li>
                      <li><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($result->FuelType); ?></li>
                    </ul>

                    <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>"
                      class="btn listing-card__btn">
                      View Details <span class="angle_arrow"><i class="fa fa-angle-right" aria-hidden="true"></i></span>
                    </a>
                  </div>
                </div>
              <?php }
            } ?>
          </div>

        </div>

        <!--Side-Bar-->
        <aside class="col-md-3 order-md-1">

          <div class="filter-widget">
            <div class="filter-widget__head">
              <span class="filter-widget__icon"><i class="fa fa-filter" aria-hidden="true"></i></span>
              <h5>Find Your Car</h5>
            </div>
            <form class="filter-widget__form" action="search-brandresult.php" method="post">
<?php echo csrf_field(); ?>
              <div class="form-group select">
                <select class="form-control" name="brand" required>
                  <option value="">Select Brand</option>
                  <?php
                  $sql = "SELECT * from  tblbrands ";
                  $query = $dbh->prepare($sql);
                  $query->execute();
                  $results = $query->fetchAll(PDO::FETCH_OBJ);
                  $cnt = 1;
                  if ($query->rowCount() > 0) {
                    foreach ($results as $result) { ?>
                      <option value="<?php echo htmlentities($result->id); ?>">
                        <?php echo htmlentities($result->BrandName); ?>
                      </option>
                    <?php }
                  } ?>
                </select>
              </div>
              <div class="form-group select">
                <select class="form-control" name="fueltype" required>
                  <option value="">Select Fuel Type</option>
                  <option value="Petrol">Petrol</option>
                  <option value="Diesel">Diesel</option>
                  <option value="CNG">CNG</option>
                </select>
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-block"><i class="fa fa-magnifying-glass" aria-hidden="true"></i> Search
                  Car</button>
              </div>
            </form>
          </div>

          <div class="recent-widget">
            <div class="filter-widget__head">
              <span class="filter-widget__icon"><i class="fa fa-car" aria-hidden="true"></i></span>
              <h5>Recently Listed Cars</h5>
            </div>
            <ul class="recent-widget__list">
              <?php
              $sql = "SELECT tblvehicles.*,tblbrands.BrandName,tblbrands.id as bid  from tblvehicles join tblbrands on tblbrands.id=tblvehicles.VehiclesBrand order by id desc limit 4";
              $query = $dbh->prepare($sql);
              $query->execute();
              $results = $query->fetchAll(PDO::FETCH_OBJ);
              $cnt = 1;
              if ($query->rowCount() > 0) {
                foreach ($results as $result) { ?>
                  <li>
                    <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>" class="recent-widget__img">
                      <?php echo vehicle_image($result->Vimage1, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle), ['sizes' => '120px']); ?>
                    </a>
                    <div class="recent-widget__info">
                      <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id); ?>">
                        <span class="recent-widget__brand"><?php echo htmlentities($result->BrandName); ?></span>
                        <?php echo htmlentities($result->VehiclesTitle); ?>
                      </a>
                      <p class="recent-widget__price"><?php echo format_price($result->PricePerDay); ?> <small>/
                          Day</small></p>
                    </div>
                  </li>
                <?php }
              } ?>
            </ul>
          </div>

        </aside>
        <!--/Side-Bar-->
      </div>
    </div>
  </section>
  <!-- /Listing-->

  <!--Footer -->
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

</body>

</html>