<?php
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');
include('includes/recommendation_engine.php');

$recommendations = [];
$userEmail = $_SESSION['login'] ?? null;
$carTypes = ['Any' => 'Any type', 'Hatchback' => 'Hatchback', 'Sedan' => 'Sedan', 'SUV' => 'SUV'];
$budget = 1000;
$carType = 'Any';
$trip = '';
$searched = isset($_POST['getRecommendations']);

if ($searched) {
  $budget = max(200, min(5000, intval($_POST['budget'] ?? 1000)));
  $carType = isset($carTypes[$_POST['carType'] ?? '']) ? $_POST['carType'] : 'Any';
  // Optional plain-language description; the browser turns it into the budget and car type above.
  $trip = is_string($_POST['trip'] ?? null) ? mb_substr(trim($_POST['trip']), 0, 200) : '';

  $recommendationEngine = new CarRecommendationEngine($dbh);
  $recommendations = array_values($recommendationEngine->getRecommendations($budget, $carType, $userEmail));
  $userPreferences = $recommendationEngine->getUserPreferences($userEmail);
}
$fleetCount = (int)$dbh->query("SELECT COUNT(*) FROM tblvehicles WHERE IsActive = 1")->fetchColumn();
// Example prompts: admin -> Site content -> Other pages.
$examples = array_map(fn($n) => content_raw('findcar_example_' . $n), [1, 2, 3, 4]);
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
  <?php
  $pageTitle = content_raw('seo_findcar_title');
  $pageDescription = content_raw('seo_findcar_description');
  $pageScripts = ['assets/js/rento-ai.js'];
  include('includes/head.php');
  ?>
</head>

<body class="r-page">

  <?php include('includes/header.php'); ?>

  <main id="main">
    <section class="r-ai-hero">
      <div class="container">
        <nav aria-label="Breadcrumb">
          <ol class="r-breadcrumb">
            <li><a href="./">Home</a></li>
            <li aria-current="page">Find my car</li>
          </ol>
        </nav>
        <span class="r-eyebrow"><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> <?php echo content('findcar_eyebrow'); ?></span>
        <h1><?php echo content('findcar_title'); ?></h1>
        <p class="r-ai-hero__lead"><?php echo content('findcar_lead'); ?> We match it against <?php echo $fleetCount; ?> cars<?php echo $userEmail ? ' and your past rentals' : ''; ?>.</p>

        <form method="post" class="r-ai-form" id="ai-form" aria-label="Describe your trip">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="getRecommendations" value="1">
          <div class="r-ai-prompt">
            <label class="visually-hidden" for="trip">Describe your trip</label>
            <i class="fa fa-wand-magic-sparkles r-ai-prompt__icon" aria-hidden="true"></i>
            <textarea id="trip" name="trip" rows="1" maxlength="200" placeholder="e.g. Weekend in Pokhara with family, under Rs 3,000 a day"><?php echo htmlentities($trip); ?></textarea>
            <button type="submit" class="r-btn r-btn--lg r-ai-prompt__submit"><span>Find matches</span><i class="fa fa-arrow-right" aria-hidden="true"></i></button>
          </div>

          <div class="r-ai-understood" id="ai-understood" aria-live="polite" hidden></div>

          <div class="r-ai-examples">
            <span class="r-ai-examples__label">Try</span>
            <?php foreach ($examples as $example) { ?>
              <button type="button" class="r-ai-example" data-prompt="<?php echo htmlentities($example); ?>"><?php echo htmlentities($example); ?></button>
            <?php } ?>
          </div>

          <details class="r-ai-tune" <?php echo $searched ? 'open' : ''; ?>>
            <summary><i class="fa fa-sliders" aria-hidden="true"></i>Fine-tune budget and car type</summary>
            <div class="r-ai-tune__body">
              <div class="r-field">
                <label class="r-label" for="budget">Budget per day: <output id="budgetValue" for="budget"><?php echo format_price($budget); ?></output></label>
                <input type="range" class="form-range r-range" id="budget" name="budget" min="200" max="5000" step="100" value="<?php echo $budget; ?>">
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
            </div>
          </details>
        </form>
      </div>
    </section>

    <div class="r-ai-thinking" id="ai-thinking" hidden role="status" aria-live="polite">
      <div class="r-ai-thinking__card">
        <div class="r-ai-orb" aria-hidden="true"></div>
        <p class="r-ai-thinking__title">Finding your best matches</p>
        <ol class="r-ai-steps">
          <li data-step>Reading your trip</li>
          <li data-step>Checking <?php echo $fleetCount; ?> cars</li>
          <li data-step>Matching budget and car type</li>
          <li data-step><?php echo $userEmail ? 'Ranking with your rental history' : 'Ranking the best matches'; ?></li>
        </ol>
      </div>
    </div>

    <?php if ($searched) { ?>
      <section class="r-section r-section--muted" id="results" tabindex="-1">
        <div class="container">
          <div class="r-ai-summary">
            <div>
              <span class="r-eyebrow"><?php echo count($recommendations); ?> <?php echo count($recommendations) === 1 ? 'match' : 'matches'; ?></span>
              <h2 class="r-section-title"><?php echo $recommendations ? 'Your best matches' : 'No matches yet'; ?></h2>
              <?php if ($trip !== '') { ?><p class="r-ai-summary__trip">“<?php echo htmlentities($trip); ?>”</p><?php } ?>
            </div>
            <ul class="r-chips" aria-label="Search used">
              <li><span class="r-chip"><i class="fa fa-tag" aria-hidden="true"></i>Up to <?php echo format_price_per_day($budget); ?></span></li>
              <li><span class="r-chip"><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($carTypes[$carType]); ?></span></li>
            </ul>
          </div>

          <?php if ($recommendations) { ?>
            <div class="r-grid r-grid--cards r-ai-results">
              <?php foreach ($recommendations as $i => $car) {
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
                $badge = isset($car->recommendationScore) ? round($car->recommendationScore * 100) . '% match' : ($i === 0 ? 'Best match' : '');
                echo '<div class="r-ai-result" style="--i:' . min($i, 8) . '">' . car_card($car, ['heading' => 'h3', 'badge' => $badge, 'reasons' => array_slice($reasons, 0, 3)]) . '</div>';
              } ?>
            </div>
          <?php } else {
            echo empty_state('fa-magnifying-glass', 'No cars fit that budget and type', 'Try a higher budget or "Any type" under Fine-tune. You can also browse every car.', 'Browse all cars', 'car-listing');
          } ?>
        </div>
      </section>
    <?php } ?>
  </main>

  <?php include('includes/footer.php'); ?>
  <?php include('includes/login.php'); ?>
  <?php include('includes/registration.php'); ?>
  <?php include('includes/forgotpassword.php'); ?>
  <?php include('includes/scripts.php'); ?>
  <script>
    (function () {
      var form = document.getElementById('ai-form');
      var trip = document.getElementById('trip');
      var understood = document.getElementById('ai-understood');
      var budget = document.getElementById('budget');
      var budgetValue = document.getElementById('budgetValue');

      function showBudget() {
        budgetValue.textContent = RentoAI.formatPrice(budget.value);
      }
      function grow() {
        trip.style.height = 'auto';
        trip.style.height = Math.min(trip.scrollHeight, 160) + 'px';
      }
      // As the visitor types, show what was understood and set the matching filters.
      function sync() {
        var result = RentoAI.understand(trip.value);
        RentoAI.renderChips(understood, result);
        if (result.budget) {
          budget.value = Math.max(200, Math.min(5000, Math.round(result.budget / 100) * 100));
          showBudget();
        }
        if (result.type) document.getElementById('type-' + result.type).checked = true;
        grow();
      }

      trip.addEventListener('input', sync);
      budget.addEventListener('input', showBudget);
      trip.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          form.requestSubmit();
        }
      });
      document.querySelectorAll('.r-ai-example').forEach(function (button) {
        button.addEventListener('click', function () {
          trip.value = button.dataset.prompt;
          sync();
          trip.focus();
        });
      });
      form.addEventListener('submit', function (event) {
        if (form.dataset.sending) return;
        event.preventDefault();
        form.dataset.sending = '1';
        RentoAI.think(document.getElementById('ai-thinking'), 450, function () { form.submit(); });
      });

      if (trip.value) RentoAI.renderChips(understood, RentoAI.understand(trip.value));
      grow();
      <?php if ($searched) { ?>
        var results = document.getElementById('results');
        results.scrollIntoView({ block: 'start' });
        results.focus({ preventScroll: true });
      <?php } ?>
    })();
  </script>

</body>

</html>
