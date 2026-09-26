<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
require_once('includes/dynamic_pricing.php');

// Unknown or missing vehicle id: show the "page not found" page.
$vehicleCheck = $dbh->prepare("SELECT tblvehicles.id, tblvehicles.VehiclesTitle, tblbrands.BrandName FROM tblvehicles LEFT JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand WHERE tblvehicles.id = :vhid");
$vehicleCheck->bindValue(':vhid', intval($_GET['vhid'] ?? 0), PDO::PARAM_INT);
$vehicleCheck->execute();
$checkedVehicle = $vehicleCheck->fetch(PDO::FETCH_OBJ);
if (!$checkedVehicle) {
  require __DIR__ . '/404.php';
  exit;
}

// Every car has one address, car/<brand-model>-<id>. Old links (vehical-details.php?vhid=)
// and outdated names redirect to it permanently.
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
  $canonicalPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/'
    . vehicle_url($checkedVehicle->id, $checkedVehicle->BrandName, $checkedVehicle->VehiclesTitle);
  if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== $canonicalPath) {
    header('Location: ' . $canonicalPath, true, 301);
    exit;
  }
}
$requestedFromDate = isset($_POST['fromdate']) && $_POST['fromdate'] != '' ? $_POST['fromdate'] : date('Y-m-d', strtotime('+1 day'));
$requestedToDate = isset($_POST['todate']) && $_POST['todate'] != '' ? $_POST['todate'] : date('Y-m-d', strtotime('+2 day'));
// Returns true for a real calendar date in Y-m-d format.
function is_booking_date(string $value): bool
{
  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date !== false && $date->format('Y-m-d') === $value;
}

if (isset($_POST['submit'])) {
  $fromdate = (string)($_POST['fromdate'] ?? '');
  $todate = (string)($_POST['todate'] ?? '');
  $message = $_POST['message'];
  $useremail = $_SESSION['login'] ?? '';
  $status = 0;
  $vhid = intval($_GET['vhid'] ?? 0);
  $bookingno = mt_rand(100000000, 999999999);

  // Server-side checks: the booking form alone can be bypassed with a direct POST.
  if ($useremail === '') {
    echo "<script>alert('Please login to book this car.');</script>";
  } elseif (!is_booking_date($fromdate) || !is_booking_date($todate)) {
    echo "<script>alert('Please choose valid From and To dates.');</script>";
  } elseif ($fromdate < date('Y-m-d')) {
    echo "<script>alert('The From date cannot be in the past.');</script>";
  } elseif ($todate < $fromdate) {
    echo "<script>alert('The To date must be on or after the From date.');</script>";
  } else {
    $ret = "SELECT * FROM tblbooking where (:fromdate BETWEEN date(FromDate) and date(ToDate) || :todate BETWEEN date(FromDate) and date(ToDate) || date(FromDate) BETWEEN :fromdate and :todate) and VehicleId=:vhid";
    $query1 = $dbh->prepare($ret);
    $query1->bindParam(':vhid', $vhid, PDO::PARAM_STR);
    $query1->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
    $query1->bindParam(':todate', $todate, PDO::PARAM_STR);
    $query1->execute();
    $results1 = $query1->fetchAll(PDO::FETCH_OBJ);

    if ($query1->rowCount() == 0) {
      $sql = "INSERT INTO  tblbooking(BookingNumber,userEmail,VehicleId,FromDate,ToDate,message,Status) VALUES(:bookingno,:useremail,:vhid,:fromdate,:todate,:message,:status)";
      $query = $dbh->prepare($sql);
      $query->bindParam(':bookingno', $bookingno, PDO::PARAM_STR);
      $query->bindParam(':useremail', $useremail, PDO::PARAM_STR);
      $query->bindParam(':vhid', $vhid, PDO::PARAM_STR);
      $query->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
      $query->bindParam(':todate', $todate, PDO::PARAM_STR);
      $query->bindParam(':message', $message, PDO::PARAM_STR);
      $query->bindParam(':status', $status, PDO::PARAM_STR);
      $query->execute();
      $lastInsertId = $dbh->lastInsertId();
      if ($lastInsertId) {
        echo "<script>alert('Booking successfull.');</script>";
        echo "<script type='text/javascript'> document.location = 'my-booking'; </script>";
      } else {
        echo "<script>alert('Something went wrong. Please try again');</script>";
        echo "<script type='text/javascript'> document.location = 'car-listing'; </script>";
      }
    } else {
      echo "<script>alert('Car already booked for these days');</script>";
      echo "<script type='text/javascript'> document.location = 'car-listing'; </script>";
    }
  }

}

?>


<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  // Title and description name the car being viewed.
  $metaQuery = $dbh->prepare("SELECT tblvehicles.VehiclesTitle, tblvehicles.PricePerDay, tblbrands.BrandName FROM tblvehicles JOIN tblbrands ON tblbrands.id = tblvehicles.VehiclesBrand WHERE tblvehicles.id = :vhid");
  $metaQuery->bindValue(':vhid', intval($_GET['vhid'] ?? 0), PDO::PARAM_INT);
  $metaQuery->execute();
  $metaVehicle = $metaQuery->fetch(PDO::FETCH_OBJ);
  $pageTitle = $metaVehicle ? vehicle_display_name($metaVehicle->BrandName, $metaVehicle->VehiclesTitle) : 'Vehicle Details';
  $pageDescription = $metaVehicle ? 'Rent the ' . $pageTitle . ' in Nepal from ' . format_price_per_day($metaVehicle->PricePerDay) . '. See photos, features and availability, and book online.' : null;
  $pageUsesCarousel = true;
  include('includes/head.php');
  ?>
</head>

<body>


  <!--Header-->
  <?php include('includes/header.php'); ?>
  <!-- /Header -->

  <!--Listing-Image-Slider-->

  <?php
  $vhid = intval($_GET['vhid'] ?? 0);
  $sql = "SELECT tblvehicles.*,tblbrands.BrandName,tblbrands.id as bid  from tblvehicles join tblbrands on tblbrands.id=tblvehicles.VehiclesBrand where tblvehicles.id=:vhid";
  $query = $dbh->prepare($sql);
  $query->bindParam(':vhid', $vhid, PDO::PARAM_STR);
  $query->execute();
  $results = $query->fetchAll(PDO::FETCH_OBJ);
  $cnt = 1;
  if ($query->rowCount() > 0) {
    foreach ($results as $result) {
      $similarBrandId = $result->bid;
      $pricingQuote = dynamic_price_quote($dbh, (int) $result->id, [
        'start_date' => $requestedFromDate,
        'end_date' => $requestedToDate,
        'base_price' => (float) $result->PricePerDay
      ]);
      $displayPrice = isset($pricingQuote['recommended_price']) && $pricingQuote['recommended_price'] > 0
        ? $pricingQuote['recommended_price']
        : $result->PricePerDay;
      ?>

      <section class="vehicle-slider-wrap">
        <div id="listing_img_slider" class="owl-carousel">
          <div><?php echo vehicle_image($result->Vimage1, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle) . ' – photo 1', ['class' => 'img-fluid', 'sizes' => '100vw', 'lazy' => false]); ?></div>
          <div><?php echo vehicle_image($result->Vimage2, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle) . ' – photo 2', ['class' => 'img-fluid', 'sizes' => '100vw']); ?></div>
          <div><?php echo vehicle_image($result->Vimage3, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle) . ' – photo 3', ['class' => 'img-fluid', 'sizes' => '100vw']); ?></div>
          <div><?php echo vehicle_image($result->Vimage4, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle) . ' – photo 4', ['class' => 'img-fluid', 'sizes' => '100vw']); ?></div>
          <?php if ($result->Vimage5 == "") {

          } else {
            ?>
            <div><?php echo vehicle_image($result->Vimage5, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle) . ' – photo 5', ['class' => 'img-fluid', 'sizes' => '100vw']); ?></div>
          <?php } ?>
        </div>
      </section>
      <!--/Listing-Image-Slider-->


      <!--Listing-detail-->
      <section class="listing-detail">
        <div class="container">
          <div class="listing_detail_head row">
            <div class="col-md-9">
              <h2><?php echo htmlentities($result->BrandName); ?> , <?php echo htmlentities($result->VehiclesTitle); ?></h2>
            </div>
            <div class="col-md-3">
              <div class="price_info">
                <p><?php echo format_price($displayPrice); ?> </p>Per Day
                <?php if (($pricingQuote['status'] ?? '') === 'model'): ?>
                  <small class="text-muted">Dynamic price
                    (<?php echo sprintf('%+0.1f%%', (($pricingQuote['multiplier'] ?? 1) - 1) * 100); ?>)</small>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-9">
              <div class="main_features">
                <ul>

                  <li> <i class="fa fa-calendar" aria-hidden="true"></i>
                    <h5><?php echo htmlentities($result->ModelYear); ?></h5>
                    <p>Reg.Year</p>
                  </li>
                  <li> <i class="fa fa-gears" aria-hidden="true"></i>
                    <h5><?php echo htmlentities($result->FuelType); ?></h5>
                    <p>Fuel Type</p>
                  </li>

                  <li> <i class="fa fa-user-plus" aria-hidden="true"></i>
                    <h5><?php echo htmlentities($result->SeatingCapacity); ?></h5>
                    <p>Seats</p>
                  </li>
                </ul>
              </div>
              <div class="listing_more_info">
                <div class="listing_detail_wrap">
                  <!-- Nav tabs -->
                  <ul class="nav nav-tabs gray-bg" role="tablist">
                    <li role="presentation"><a href="#vehicle-overview" class="active" aria-controls="vehicle-overview"
                        role="tab" data-bs-toggle="tab" aria-selected="true">Vehicle Overview</a></li>

                    <li role="presentation"><a href="#accessories" aria-controls="accessories" role="tab"
                        data-bs-toggle="tab" aria-selected="false">Accessories</a></li>
                  </ul>

                  <!-- Tab panes -->
                  <div class="tab-content">
                    <!-- vehicle-overview -->
                    <div role="tabpanel" class="tab-pane fade show active" id="vehicle-overview">

                      <p><?php echo htmlentities($result->VehiclesOverview); ?></p>
                    </div>


                    <!-- Accessories -->
                    <div role="tabpanel" class="tab-pane fade" id="accessories">
                      <!--Accessories-->
                      <table>
                        <thead>
                          <tr>
                            <th colspan="2">Accessories</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>Air Conditioner</td>
                            <?php if ($result->AirConditioner == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>AntiLock Braking System</td>
                            <?php if ($result->AntiLockBrakingSystem == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Power Steering</td>
                            <?php if ($result->PowerSteering == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>


                          <tr>

                            <td>Power Windows</td>

                            <?php if ($result->PowerWindows == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>CD Player</td>
                            <?php if ($result->CDPlayer == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Leather Seats</td>
                            <?php if ($result->LeatherSeats == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Central Locking</td>
                            <?php if ($result->CentralLocking == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Power Door Locks</td>
                            <?php if ($result->PowerDoorLocks == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>
                          <tr>
                            <td>Brake Assist</td>
                            <?php if ($result->BrakeAssist == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Driver Airbag</td>
                            <?php if ($result->DriverAirbag == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Passenger Airbag</td>
                            <?php if ($result->PassengerAirbag == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                          <tr>
                            <td>Crash Sensor</td>
                            <?php if ($result->CrashSensor == 1) {
                              ?>
                              <td><i class="fa fa-check" aria-hidden="true"></i></td>
                            <?php } else { ?>
                              <td><i class="fa fa-xmark" aria-hidden="true"></i></td>
                            <?php } ?>
                          </tr>

                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

              </div>
            <?php }
  } ?>

        </div>

        <!--Side-Bar-->
        <aside class="col-md-3">

          <div class="booking-widget">
            <div class="filter-widget__head">
              <span class="filter-widget__icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
              <h5>Book Now</h5>
            </div>
            <form class="filter-widget__form" method="post">
<?php echo csrf_field(); ?>
              <div class="form-group">
                <label>From Date</label>
                <input type="date" class="form-control" name="fromdate" placeholder="From Date" required>
              </div>
              <div class="form-group">
                <label>To Date</label>
                <input type="date" class="form-control" name="todate" placeholder="To Date" required>
              </div>
              <div class="form-group">
                <label>Message</label>
                <textarea rows="4" class="form-control" name="message" placeholder="Any special request?"
                  required></textarea>
              </div>
              <?php if (!empty($_SESSION['login'])) { ?>
                <div class="form-group">
                  <input type="submit" class="btn btn-block" name="submit" value="Book Now">
                </div>
              <?php } else { ?>
                <a href="#loginform" class="btn btn-block uppercase" data-bs-toggle="modal">Login to
                  Book</a>
              <?php } ?>
            </form>

            <div class="booking-widget__share">
              <span>Share this car</span>
              <div class="booking-widget__share-icons">
                <a href="#" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                <a href="#" aria-label="Share on Twitter"><i class="fa-brands fa-twitter" aria-hidden="true"></i></a>
                <a href="#" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                <a href="#" aria-label="Share on Google Plus"><i class="fa-brands fa-google-plus-g" aria-hidden="true"></i></a>
              </div>
            </div>
          </div>

        </aside>
        <!--/Side-Bar-->
      </div>

      <div class="space-20"></div>
      <div class="divider"></div>

      <!--Similar-Cars-->
      <div class="similar_cars">
        <h3>Similar Cars</h3>
        <div class="car-grid">
          <?php
          $bid = $similarBrandId ?? 0;
          $sql = "SELECT tblvehicles.VehiclesTitle,tblbrands.BrandName,tblvehicles.PricePerDay,tblvehicles.FuelType,tblvehicles.ModelYear,tblvehicles.id,tblvehicles.SeatingCapacity,tblvehicles.VehiclesOverview,tblvehicles.Vimage1 from tblvehicles join tblbrands on tblbrands.id=tblvehicles.VehiclesBrand where tblvehicles.VehiclesBrand=:bid";
          $query = $dbh->prepare($sql);
          $query->bindParam(':bid', $bid, PDO::PARAM_STR);
          $query->execute();
          $results = $query->fetchAll(PDO::FETCH_OBJ);
          $cnt = 1;
          if ($query->rowCount() > 0) {
            foreach ($results as $result) {
              $overview = isset($result->VehiclesOverview) && $result->VehiclesOverview
                ? substr(strip_tags($result->VehiclesOverview), 0, 70) . '...'
                : 'No description available.';
              ?>
              <div class="col-list-3">
                <div class="recent-car-list">
                  <div class="car-info-box">
                    <a href="<?php echo htmlentities(vehicle_url($result->id, $result->BrandName ?? '', $result->VehiclesTitle ?? '')); ?>" class="car-img-wrap">
                      <?php echo vehicle_image($result->Vimage1, vehicle_display_name($result->BrandName ?? '', $result->VehiclesTitle), ['sizes' => '(min-width: 992px) 360px, (min-width: 576px) 50vw, 100vw']); ?>
                    </a>
                    <ul class="car-stats">
                      <li><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($result->FuelType); ?></li>
                      <li><i class="fa fa-calendar" aria-hidden="true"></i><?php echo htmlentities($result->ModelYear); ?>
                      </li>
                      <li><i class="fa fa-user" aria-hidden="true"></i><?php echo htmlentities($result->SeatingCapacity); ?>
                        seats</li>
                    </ul>
                  </div>
                  <div class="car-body">
                    <div class="car-title-m">
                      <h6><a href="<?php echo htmlentities(vehicle_url($result->id, $result->BrandName ?? '', $result->VehiclesTitle ?? '')); ?>">
                          <?php echo htmlentities($result->BrandName); ?> ,
                          <?php echo htmlentities($result->VehiclesTitle); ?></a></h6>
                      <span class="price"><?php echo format_price($result->PricePerDay); ?><small>/Day</small></span>
                    </div>
                    <div class="inventory_info_m">
                      <p><?php echo htmlentities($overview); ?></p>
                    </div>
                  </div>
                </div>
              </div>
            <?php }
          } else { ?>
            <p>No similar cars found for this brand right now.</p>
          <?php } ?>
        </div>
      </div>
      <!--/Similar-Cars-->

    </div>
  </section>
  <!--/Listing-detail-->

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

  <script>
    $(document).ready(function () {
      $('#listing_img_slider').owlCarousel({
        items: 1,
        loop: true,
        nav: true,
        dots: true,
        smartSpeed: 400,
        navText: ['<i class="fa fa-angle-left" aria-hidden="true"></i><span class="visually-hidden">Previous photo</span>',
          '<i class="fa fa-angle-right" aria-hidden="true"></i><span class="visually-hidden">Next photo</span>']
      });
    });
  </script>

</body>

</html>