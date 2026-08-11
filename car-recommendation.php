<?php 
session_start();
include('includes/config.php');
include('includes/recommendation_engine.php');
error_reporting(0);

$recommendations = [];
$userEmail = $_SESSION['login'] ?? null;

if(isset($_POST['getRecommendations'])) {
    $budget = intval($_POST['budget']);
    $carType = $_POST['carType'];
    
    $recommendationEngine = new CarRecommendationEngine($dbh);
    $recommendations = $recommendationEngine->getRecommendations($budget, $carType, $userEmail);
}
?>

<!DOCTYPE HTML>
<html lang="en">
<head>
<title>Car Rental Portal | AI Car Recommendations</title>
<!--Bootstrap -->
<link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
<!--Custome Style -->
<link rel="stylesheet" href="assets/css/style.css" type="text/css">
<!--OWL Carousel slider-->
<link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
<link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
<!--slick-slider -->
<link href="assets/css/slick.css" rel="stylesheet">
<!--bootstrap-slider -->
<link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
<!--FontAwesome Font Style -->
<link href="assets/css/font-awesome.min.css" rel="stylesheet">

<!-- SWITCHER -->
<link rel="stylesheet" id="switcher-css" type="text/css" href="assets/switcher/css/switcher.css" media="all" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/red.css" title="red" media="all" data-default-color="true" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/orange.css" title="orange" media="all" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/blue.css" title="blue" media="all" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/pink.css" title="pink" media="all" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/green.css" title="green" media="all" />
<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/purple.css" title="purple" media="all" />

<!-- Fav and touch icons -->
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/images/favicon-icon/apple-touch-icon-114-precomposed.html">
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/images/favicon-icon/apple-touch-icon-72-precomposed.png">
<link rel="apple-touch-icon-precomposed" href="assets/images/favicon-icon/apple-touch-icon-57-precomposed.png">
<link rel="shortcut icon" href="assets/images/favicon-icon/favicon.png">
<link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,900" rel="stylesheet">

<style>
.recommendation-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.recommendation-card:hover {
    border-color: #007bff;
    box-shadow: 0 8px 25px rgba(0,123,255,0.15);
    transform: translateY(-2px);
}

.recommendation-score {
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 14px;
    display: inline-block;
    margin-bottom: 10px;
}

.ai-badge {
    background: linear-gradient(45deg, #6f42c1, #e83e8c);
    color: white;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    padding: 5px 0;
    color: #6c757d;
}

.feature-list li:before {
    content: "✓ ";
    color: #28a745;
    font-weight: bold;
}

.explanation-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin-top: 15px;
    border-radius: 0 5px 5px 0;
}

.budget-slider {
    margin: 20px 0;
}

.form-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.no-recommendations {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-recommendations i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #dee2e6;
}
</style>
</head>

<body>
<!-- Start Switcher -->
<?php include('includes/colorswitcher.php');?>
<!-- /Switcher -->  

<!--Header--> 
<?php include('includes/header.php');?>
<!-- /Header -->

<!--Page Header-->
<section class="page-header listing_page">
  <div class="container">
    <div class="page-header_wrap">
      <div class="page-heading">
        <h1>AI Car Recommendations</h1>
      </div>
      <ul class="coustom-breadcrumb">
        <li><a href="index.php">Home</a></li>
        <li>AI Recommendations</li>
      </ul>
    </div>
  </div>
  <!-- Dark Overlay-->
  <div class="dark-overlay"></div>
</section>
<!-- /Page Header-->

<!--Listing-->
<section class="listing-page">
  <div class="container">
    <div class="row">
      
      <!--Recommendation Form-->
      <div class="col-md-12">
        <div class="form-section">
          <h2><i class="fa fa-robot"></i> Get Personalized Car Recommendations</h2>
          <p class="mb-4">Our AI analyzes your preferences and budget to suggest the perfect car for you.</p>
          
          <form method="post" action="">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="budget" class="form-label">Your Budget (per day)</label>
                  <div class="budget-slider">
                    <input type="range" class="form-control-range" id="budget" name="budget" 
                           min="200" max="5000" value="1000" 
                           oninput="document.getElementById('budgetValue').textContent = 'Rs. ' + this.value">
                    <div class="d-flex justify-content-between">
                      <small>Rs. 200</small>
                      <span id="budgetValue" class="badge badge-light">Rs. 1000</span>
                      <small>Rs. 5000</small>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="form-group">
                  <label for="carType" class="form-label">Preferred Car Type</label>
                  <select class="form-control" id="carType" name="carType" required>
                    <option value="Any">Any Type</option>
                    <option value="SUV">SUV</option>
                    <option value="Sedan">Sedan</option>
                    <option value="Hatchback">Hatchback</option>
                  </select>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">&nbsp;</label>
                  <button type="submit" name="getRecommendations" class="btn btn-light btn-block">
                    <i class="fa fa-magic"></i> Get AI Recommendations
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
      
      <!--Recommendations Results-->
      <?php if(!empty($recommendations)): ?>
      <div class="col-md-12">
        <div class="row">
          <div class="col-md-12">
            <h3><i class="fa fa-star"></i> Recommended Cars for You</h3>
            <p class="text-muted">Based on your preferences and our AI analysis</p>
          </div>
        </div>
        
        <div class="row">
          <?php 
          $recommendationEngine = new CarRecommendationEngine($dbh);
          $userPreferences = [];
          if($userEmail) {
              $pastRentals = $recommendationEngine->getUserPastRentals($userEmail);
              $userPreferences = $recommendationEngine->calculateUserPreferences($pastRentals);
          }
          
          foreach($recommendations as $index => $car): 
              $explanations = $recommendationEngine->getRecommendationExplanation($car, $userPreferences);
          ?>
          <div class="col-md-6 col-lg-4">
            <div class="recommendation-card">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="recommendation-score">
                  <i class="fa fa-star"></i> 
                  <?php echo round(($car->recommendationScore ?? 0.8) * 100); ?>% Match
                </span>
                <span class="ai-badge">AI Recommended</span>
              </div>
              
              <div class="text-center mb-3">
                <?php if($car->Vimage1): ?>
                <img src="admin/img/vehicleimages/<?php echo $car->Vimage1; ?>" 
                     class="img-fluid rounded" style="height: 200px; object-fit: cover; width: 100%;" 
                     alt="<?php echo $car->VehiclesTitle; ?>">
                <?php else: ?>
                <div class="bg-light d-flex align-items-center justify-content-center" 
                     style="height: 200px; border-radius: 8px;">
                  <i class="fa fa-car fa-3x text-muted"></i>
                </div>
                <?php endif; ?>
              </div>
              
              <h5 class="mb-2"><?php echo $car->VehiclesTitle; ?></h5>
              <p class="text-muted mb-2"><?php echo $car->BrandName; ?> • <?php echo $car->CarType; ?></p>
              
              <div class="row text-center mb-3">
                <div class="col-4">
                  <small class="text-muted">Price/Day</small>
                  <div class="h5 text-primary">Rs. <?php echo $car->PricePerDay; ?></div>
                </div>
                <div class="col-4">
                  <small class="text-muted">Seating</small>
                  <div class="h6"><?php echo $car->SeatingCapacity; ?> Seats</div>
                </div>
                <div class="col-4">
                  <small class="text-muted">Fuel</small>
                  <div class="h6"><?php echo $car->FuelType; ?></div>
                </div>
              </div>
              
              <ul class="feature-list">
                <?php 
                $features = [];
                if($car->AirConditioner) $features[] = 'AC';
                if($car->PowerSteering) $features[] = 'Power Steering';
                if($car->DriverAirbag) $features[] = 'Airbags';
                if($car->PowerWindows) $features[] = 'Power Windows';
                if($car->CentralLocking) $features[] = 'Central Lock';
                
                foreach(array_slice($features, 0, 3) as $feature): ?>
                <li><?php echo $feature; ?></li>
                <?php endforeach; ?>
              </ul>
              
              <?php if(!empty($explanations)): ?>
              <div class="explanation-box">
                <small><strong>Why we recommend this:</strong></small>
                <ul class="mb-0 mt-1">
                  <?php foreach($explanations as $explanation): ?>
                  <li><small><?php echo $explanation; ?></small></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>
              
              <div class="text-center mt-3">
                <a href="vehical-details.php?vhid=<?php echo $car->id; ?>" 
                   class="btn btn-primary btn-sm">
                  <i class="fa fa-eye"></i> View Details
                </a>
                <a href="vehical-details.php?vhid=<?php echo $car->id; ?>" 
                   class="btn btn-outline-primary btn-sm ml-2">
                  <i class="fa fa-calendar"></i> Book Now
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <?php elseif(isset($_POST['getRecommendations'])): ?>
      <div class="col-md-12">
        <div class="no-recommendations">
          <i class="fa fa-search"></i>
          <h4>No cars found matching your criteria</h4>
          <p>Try adjusting your budget or car type preferences to get better recommendations.</p>
          <a href="car-recommendation.php" class="btn btn-primary">
            <i class="fa fa-refresh"></i> Try Again
          </a>
        </div>
      </div>
      <?php endif; ?>
      
    </div>
  </div>
</section>
<!-- /Listing-->

<!--Footer -->
<?php include('includes/footer.php');?>
<!-- /Footer-->

<!--Back to top-->
<div id="back-top" class="back-top"> <a href="#top"><i class="fa fa-angle-up"></i> </a></div>
<!--/Back to top-->

<!--Login-Form -->
<?php include('includes/login.php');?>
<!--/Login-Form -->

<!--Register-Form -->
<?php include('includes/registration.php');?>
<!--/Register-Form -->

<!-- Scripts -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/interface.js"></script>
<!--bootstrap-slider-JS-->
<script src="assets/js/bootstrap-slider.min.js"></script>
<!--Slider-JS-->
<script src="assets/js/slick.min.js"></script>
<script src="assets/js/owl.carousel.min.js"></script>

<script>
// Budget slider functionality
document.getElementById('budget').addEventListener('input', function() {
    document.getElementById('budgetValue').textContent = 'Rs. ' + this.value;
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});
</script>

</body>
</html>
