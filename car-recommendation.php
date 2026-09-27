<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
include('includes/recommendation_engine.php');

$recommendations = [];
$userEmail = $_SESSION['login'] ?? null;
$carTypes = ['Any' => 'Any type', 'Hatchback' => 'Hatchback', 'Sedan' => 'Sedan', 'SUV' => 'SUV'];
$budget = 1000;
$carType = 'Any';
$searched = isset($_POST['getRecommendations']);

if ($searched) {
  $budget = max(200, min(5000, intval($_POST['budget'] ?? 1000)));
  $carType = isset($carTypes[$_POST['carType'] ?? '']) ? $_POST['carType'] : 'Any';

  $recommendationEngine = new CarRecommendationEngine($dbh);
  $recommendations = $recommendationEngine->getRecommendations($budget, $carType, $userEmail);
  $userPreferences = $recommendationEngine->getUserPreferences($userEmail);
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = 'Find My Car';
  $pageDescription = 'Tell Rento your budget and the type of car you like, and get car suggestions that fit your trip.';
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <?php echo page_hero('Find my car', 'Set your daily budget and the kind of car you like. We suggest the best matches' . ($userEmail ? ', using your past rentals too.' : '.')); ?>

    <section class="r-section r-section--muted">
      <div class="container">
        <form method="post" class="r-match-form" aria-label="Your preferences">
          <?php echo csrf_field(); ?>
          <div class="r-field">
            <label class="r-label" for="budget">Budget per day: <output id="budgetValue" for="budget"><?php echo format_price($budget); ?></output></label>
            <input type="range" class="form-range r-range" id="budget" name="budget" min="200" max="5000" step="100" value="<?php echo $budget; ?>"
              oninput="document.getElementById('budgetValue').textContent = 'Rs ' + Number(this.value).toLocaleString('en-US')">
            <div class="r-range__scale"><span>Rs 200</span><span>Rs 5,000</span></div>
          </div>
          <fieldset class="r-field">
            <legend class="r-label">Car type</legend>
            <div class="r-segmented">
              <?php foreach ($carTypes as $value => $label) { ?>
                <input type="radio" class="btn-check" name="carType" id="type-<?php echo $value; ?>" value="<?php echo $value; ?>" <?php echo $carType === $value ? 'checked' : ''; ?>>
                <label for="type-<?php echo $value; ?>"><?php echo $label; ?></label>
              <?php } ?>
            </div>
          </fieldset>
          <button type="submit" name="getRecommendations" class="r-btn r-btn--lg">
            <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i>Show my matches
          </button>
        </form>

        <?php if ($searched) { ?>
          <div class="r-section-head r-section-head--start r-match-results">
            <h2 class="r-section-title"><?php echo $recommendations ? 'Your best matches' : 'No matches yet'; ?></h2>
            <?php if ($recommendations) { ?>
              <p class="r-section-lead">Cars up to <?php echo format_price_per_day($budget); ?><?php echo $carType !== 'Any' ? ', ' . htmlentities($carType) : ''; ?>, best match first.</p>
            <?php } ?>
          </div>

          <?php if ($recommendations) { ?>
            <div class="r-grid r-grid--cards">
              <?php foreach ($recommendations as $car) {
                $reasons = [];
                if ((int)$car->PricePerDay <= $budget) {
                  $reasons[] = 'Within your budget of ' . format_price_per_day($budget);
                }
                if ($carType !== 'Any' && ($car->CarType ?? '') === $carType) {
                  $reasons[] = 'The ' . $carType . ' you asked for';
                }
                foreach ($recommendationEngine->getRecommendationExplanation($car, $userPreferences) as $explanation) {
                  $reasons[] = $explanation;
                }
                // A match % only when there is a real score (from the user's rental history).
                $badge = isset($car->recommendationScore) ? round($car->recommendationScore * 100) . '% match' : '';
                echo car_card($car, ['heading' => 'h3', 'badge' => $badge, 'reasons' => array_slice($reasons, 0, 3)]);
              } ?>
            </div>
          <?php } else {
            echo empty_state('fa-magnifying-glass', 'No cars fit that budget and type', 'Try a higher budget or "Any type". You can also browse every car.', 'Browse all cars', 'car-listing');
          } ?>
        <?php } ?>
      </div>
    </section>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>

</body>

</html>
