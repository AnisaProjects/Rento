<?php
// The vehicle form shared by post-avehical.php (add) and edit-vehicle.php (edit).
// Form field names are the original ones (vehicletitle, brandname, vehicalorcview, ...);
// cartype and removeimg5 are new.
require_once __DIR__ . '/upload.php';

const VEHICLE_CAR_TYPES = ['Hatchback', 'Sedan', 'SUV', 'MUV', 'Crossover', 'Coupe', 'Convertible', 'Pickup', 'Van'];
const VEHICLE_FUEL_TYPES = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];
// Form field => [column, label, icon]
const VEHICLE_FEATURES = [
    'airconditioner' => ['AirConditioner', 'Air conditioner', 'fa-snowflake'],
    'powerdoorlocks' => ['PowerDoorLocks', 'Power door locks', 'fa-lock'],
    'antilockbrakingsys' => ['AntiLockBrakingSystem', 'Anti-lock brakes (ABS)', 'fa-circle-stop'],
    'brakeassist' => ['BrakeAssist', 'Brake assist', 'fa-hand'],
    'powersteering' => ['PowerSteering', 'Power steering', 'fa-dharmachakra'],
    'driverairbag' => ['DriverAirbag', 'Driver airbag', 'fa-user-shield'],
    'passengerairbag' => ['PassengerAirbag', 'Passenger airbag', 'fa-shield-halved'],
    'powerwindow' => ['PowerWindows', 'Power windows', 'fa-window-maximize'],
    'cdplayer' => ['CDPlayer', 'CD player', 'fa-compact-disc'],
    'centrallocking' => ['CentralLocking', 'Central locking', 'fa-key'],
    'crashcensor' => ['CrashSensor', 'Crash sensor', 'fa-car-burst'],
    'leatherseats' => ['LeatherSeats', 'Leather seats', 'fa-couch'],
];

// Reads and checks the posted details. Returns [values keyed by column, error message or ''].
function vehicle_form_input(PDO $dbh): array
{
    $v = [
        'VehiclesTitle' => trim((string)($_POST['vehicletitle'] ?? '')),
        'VehiclesBrand' => (int)($_POST['brandname'] ?? 0),
        'VehiclesOverview' => trim(str_replace("\r\n", "\n", (string)($_POST['vehicalorcview'] ?? ''))), // browsers send CRLF
        // New field seodescription: optional meta description (empty = built from the car's data).
        'SeoDescription' => mb_substr(trim((string)($_POST['seodescription'] ?? '')), 0, 300) ?: null,
        'PricePerDay' => (int)($_POST['priceperday'] ?? 0),
        'FuelType' => (string)($_POST['fueltype'] ?? ''),
        'ModelYear' => (int)($_POST['modelyear'] ?? 0),
        'SeatingCapacity' => (int)($_POST['seatingcapacity'] ?? 0),
        'CarType' => (string)($_POST['cartype'] ?? ''),
        // New field isactive: unticked = hidden from the website and Find My Car.
        'IsActive' => empty($_POST['isactive']) ? 0 : 1,
        // New field driveronly: rented only with a driver (enquiry instead of self-drive booking).
        'DriverOnly' => empty($_POST['driveronly']) ? 0 : 1,
        // New field rugged: off-road capable (ranked higher for mountain and rough-road trips).
        'Rugged' => empty($_POST['rugged']) ? 0 : 1,
    ];
    foreach (VEHICLE_FEATURES as $field => [$column]) {
        $v[$column] = empty($_POST[$field]) ? null : 1;
    }
    $brand = $dbh->prepare("SELECT COUNT(*) FROM tblbrands WHERE id = :id");
    $brand->bindParam(':id', $v['VehiclesBrand'], PDO::PARAM_INT);
    $brand->execute();
    $maxYear = (int)date('Y') + 1;
    $error = match (true) {
        $v['VehiclesTitle'] === '' => 'Please enter the vehicle title.',
        mb_strlen($v['VehiclesTitle']) > 150 => 'The vehicle title can be at most 150 characters.',
        !$brand->fetchColumn() => 'Please choose a brand.',
        $v['VehiclesOverview'] === '' => 'Please write a short overview.',
        $v['PricePerDay'] < 1 || $v['PricePerDay'] > 1000000 => 'Price per day must be a whole number of rupees between 1 and 10,00,000.',
        !in_array($v['FuelType'], VEHICLE_FUEL_TYPES, true) => 'Please choose a fuel type.',
        $v['ModelYear'] < 1980 || $v['ModelYear'] > $maxYear => "Model year must be between 1980 and $maxYear.",
        $v['SeatingCapacity'] < 1 || $v['SeatingCapacity'] > 30 => 'Seats must be between 1 and 30.',
        !in_array($v['CarType'], VEHICLE_CAR_TYPES, true) => 'Please choose a car type.',
        default => '',
    };
    return [$v, $error];
}

// One image slot: preview, drag and drop target and file input named img$n.
function vehicle_image_slot(int $n, ?string $current, bool $required, bool $canRemove = false, bool $alwaysRequired = false): string
{
    $current = basename((string)$current);
    $hasImage = $current !== '' && is_file(VEHICLE_IMAGE_DIR . $current);
    $src = $hasImage ? 'img/vehicleimages/' . rawurlencode($current) : '';
    $label = $n === 1 ? 'Image 1 (cover)' : "Image $n";
    ob_start(); ?>
    <div class="a-drop<?php echo $hasImage ? ' has-image' : ''; ?>" data-a-drop>
      <div class="a-drop__label">
        <span><?php echo h($label); ?><?php echo $required && !$hasImage ? ' <span class="a-req">*</span>' : ($required ? '' : ' <span class="a-muted">(optional)</span>'); ?></span>
        <?php if ($hasImage) { ?><span class="a-badge a-badge--success" data-a-drop-status>Current</span><?php } elseif ($current !== '') { ?><span class="a-badge a-badge--danger" data-a-drop-status>File missing</span><?php } else { ?><span class="a-badge" data-a-drop-status>Empty</span><?php } ?>
      </div>
      <label class="d-block mb-0" for="img<?php echo $n; ?>">
        <input class="a-drop__input" type="file" id="img<?php echo $n; ?>" name="img<?php echo $n; ?>" accept="image/jpeg,image/png,image/webp"<?php echo ($required && !$hasImage) || $alwaysRequired ? ' required' : ''; ?> aria-label="<?php echo h($label . ($hasImage ? ': replace the photo' : ': choose a photo')); ?>" data-a-drop-input>
        <span class="a-drop__preview">
          <?php if ($hasImage) { ?>
            <img src="<?php echo h($src); ?>" alt="Current <?php echo h(strtolower($label)); ?>" data-a-drop-img>
          <?php } else { ?>
            <span data-a-drop-empty><i class="fa fa-cloud-arrow-up" aria-hidden="true"></i><span class="a-drop__hint">Drop a photo here or click to choose</span></span>
          <?php } ?>
        </span>
      </label>
      <div class="a-drop__foot">
        <span class="a-drop__info" data-a-drop-info><?php echo $hasImage ? 'JPG, PNG or WebP' : 'JPG, PNG or WebP, shrunk before upload'; ?></span>
        <span class="d-inline-flex gap-1">
          <label class="btn btn-light btn-sm" for="img<?php echo $n; ?>"><i class="fa fa-<?php echo $hasImage ? 'rotate' : 'plus'; ?>" aria-hidden="true"></i><?php echo $hasImage ? 'Replace' : 'Choose'; ?></label>
          <?php if ($canRemove && $current !== '') { ?>
            <label class="btn btn-light btn-sm mb-0" data-bs-toggle="tooltip" data-bs-title="Remove this image (and delete the file) when you save">
              <input type="checkbox" class="form-check-input me-1" name="removeimg<?php echo $n; ?>" value="1"> Remove
            </label>
          <?php } ?>
        </span>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// The details, features and image sections. $vehicle is null when adding a vehicle.
function vehicle_form_sections(PDO $dbh, ?object $vehicle): void
{
    $brands = $dbh->query("SELECT id, BrandName FROM tblbrands ORDER BY BrandName")->fetchAll(PDO::FETCH_OBJ);
    // After a failed save, show what the admin typed rather than the stored values.
    $posted = isset($_POST['submit']);
    $val = function (string $field, string $column) use ($vehicle, $posted) {
        return $posted ? ($_POST[$field] ?? '') : ($vehicle->$column ?? '');
    };
    $selectedType = $val('cartype', 'CarType');
    $selectedFuel = $val('fueltype', 'FuelType');
    $selectedBrand = (int)$val('brandname', 'VehiclesBrand');
    $isActive = $posted ? !empty($_POST['isactive']) : ($vehicle === null || (int)$vehicle->IsActive === 1);
    $driverOnly = $posted ? !empty($_POST['driveronly']) : ((int)($vehicle->DriverOnly ?? 0) === 1);
    $rugged = $posted ? !empty($_POST['rugged']) : ((int)($vehicle->Rugged ?? 0) === 1);
    ?>
    <section class="a-card a-form-section">
      <div class="a-card__body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <h2 class="a-card__title mb-1"><i class="fa fa-eye" aria-hidden="true"></i>Visibility</h2>
          <p class="a-muted mb-0">Inactive cars are hidden from the website and Find My Car, and can't be booked.</p>
        </div>
        <div>
          <div class="form-check form-switch fs-5 mb-1">
            <input class="form-check-input" type="checkbox" role="switch" id="isactive" name="isactive" value="1"<?php echo $isActive ? ' checked' : ''; ?>>
            <label class="form-check-label fs-6 fw-semibold" for="isactive">Active (shown on the website)</label>
          </div>
          <div class="form-check form-switch fs-5 mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="driveronly" name="driveronly" value="1"<?php echo $driverOnly ? ' checked' : ''; ?> aria-describedby="driveronly-hint">
            <label class="form-check-label fs-6 fw-semibold" for="driveronly">Driver only</label>
          </div>
          <div class="form-text" id="driveronly-hint">Shown as "With driver": customers send a request instead of booking self-drive.</div>
          <div class="form-check form-switch fs-5 mb-0 mt-2">
            <input class="form-check-input" type="checkbox" role="switch" id="rugged" name="rugged" value="1"<?php echo $rugged ? ' checked' : ''; ?> aria-describedby="rugged-hint">
            <label class="form-check-label fs-6 fw-semibold" for="rugged">Rugged / off-road capable</label>
          </div>
          <div class="form-text" id="rugged-hint">Only for cars built for mountain tracks (high clearance, strong chassis, 4x4 available), e.g. Fortuner, Scorpio, Hilux. Find My Car ranks them first for mountain and rough-road trips.</div>
        </div>
      </div>
    </section>
    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-circle-info" aria-hidden="true"></i>Basic info</h2></div>
      <div class="a-card__body a-form-grid">
        <div>
          <label class="form-label" for="vehicletitle">Vehicle title <span class="a-req">*</span></label>
          <input type="text" id="vehicletitle" name="vehicletitle" class="form-control" maxlength="150" value="<?php echo h($val('vehicletitle', 'VehiclesTitle')); ?>" placeholder="e.g. Toyota Fortuner" required>
        </div>
        <div>
          <label class="form-label" for="brandname">Brand <span class="a-req">*</span></label>
          <select id="brandname" name="brandname" class="form-select" required>
            <option value="">Choose a brand</option>
            <?php foreach ($brands as $brand) { ?>
              <option value="<?php echo (int)$brand->id; ?>"<?php echo $selectedBrand === (int)$brand->id ? ' selected' : ''; ?>><?php echo h($brand->BrandName); ?></option>
            <?php } ?>
          </select>
          <div class="form-text"><a href="create-brand.php">Add a new brand</a></div>
        </div>
        <div class="a-span-all">
          <label class="form-label" for="vehicalorcview">Overview <span class="a-req">*</span></label>
          <textarea id="vehicalorcview" name="vehicalorcview" class="form-control" rows="4" required placeholder="What makes this car a good pick: comfort, boot space, where it drives best…"><?php echo h($val('vehicalorcview', 'VehiclesOverview')); ?></textarea>
        </div>
        <div class="a-span-all">
          <label class="form-label" for="seodescription">Search description <span class="a-muted">(optional)</span></label>
          <textarea id="seodescription" name="seodescription" class="form-control" rows="2" maxlength="300" aria-describedby="seodescription-hint" placeholder="Leave empty to build it from the car's name, type and price"><?php echo h($val('seodescription', 'SeoDescription')); ?></textarea>
          <div class="form-text" id="seodescription-hint">Shown by Google and in share previews. About 150 characters works best.</div>
        </div>
      </div>
    </section>

    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-tag" aria-hidden="true"></i>Pricing and specs</h2></div>
      <div class="a-card__body a-form-grid a-form-grid--3">
        <div>
          <label class="form-label" for="priceperday">Price per day <span class="a-req">*</span></label>
          <div class="input-group">
            <span class="input-group-text">Rs</span>
            <input type="number" id="priceperday" name="priceperday" class="form-control" min="1" max="1000000" step="1" inputmode="numeric" value="<?php echo h($val('priceperday', 'PricePerDay')); ?>" required aria-describedby="price-suggestion">
          </div>
          <?php
          // Smart price suggestion (edit only): the average rule-based price for the next 60 days.
          $suggestion = $vehicle ? vehicle_price_suggestion($dbh, (int)$vehicle->id, (int)$vehicle->PricePerDay) : null; ?>
          <div class="form-text a-price-suggestion" id="price-suggestion">
            <?php if ($suggestion) {
              $diff = $suggestion['suggestion'] - (int)$vehicle->PricePerDay; ?>
              <strong><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Smart price suggestion: <?php echo format_price($suggestion['suggestion']); ?>/day</strong>
              <?php echo $diff ? '(' . ($diff > 0 ? '+' : '−') . format_price(abs($diff)) . ')' : '(the same as now)'; ?>.
              The pricing rules would charge <?php echo format_price(round($suggestion['average'])); ?>/day on average over the next <?php echo $suggestion['days']; ?> days<?php
              echo $suggestion['reasons'] ? ', mainly for ' . h(implode(', ', array_map(fn($l, $n) => $l . ' (' . $n . ' days)', array_keys($suggestion['reasons']), $suggestion['reasons']))) : ''; ?>.
              <?php if ($diff) { ?><button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-use-suggestion="<?php echo (int)$suggestion['suggestion']; ?>">Use it</button><?php } ?>
              <span class="d-block">Rule-based, from your festival calendar and settings; the rules still apply on top of the base price you save.</span>
            <?php } elseif ($vehicle) { ?>
              No smart price suggestion yet: the forecast for this car is missing or out of date. Save, then check again after the recompute.
            <?php } else { ?>
              After you post the car, a smart price suggestion appears here.
            <?php } ?>
          </div>
        </div>
        <div>
          <label class="form-label" for="cartype">Car type <span class="a-req">*</span></label>
          <select id="cartype" name="cartype" class="form-select" required>
            <option value="">Choose a type</option>
            <?php foreach (VEHICLE_CAR_TYPES as $type) { ?>
              <option value="<?php echo $type; ?>"<?php echo $selectedType === $type ? ' selected' : ''; ?>><?php echo $type; ?></option>
            <?php } ?>
          </select>
          <div class="form-text">The website filters by Hatchback, Sedan and SUV.</div>
        </div>
        <div>
          <label class="form-label" for="fueltype">Fuel type <span class="a-req">*</span></label>
          <select id="fueltype" name="fueltype" class="form-select" required>
            <option value="">Choose fuel</option>
            <?php foreach (VEHICLE_FUEL_TYPES as $fuel) { ?>
              <option value="<?php echo $fuel; ?>"<?php echo $selectedFuel === $fuel ? ' selected' : ''; ?>><?php echo $fuel; ?></option>
            <?php } ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="modelyear">Model year <span class="a-req">*</span></label>
          <input type="number" id="modelyear" name="modelyear" class="form-control" min="1980" max="<?php echo (int)date('Y') + 1; ?>" step="1" inputmode="numeric" value="<?php echo h($val('modelyear', 'ModelYear')); ?>" required>
        </div>
        <div>
          <label class="form-label" for="seatingcapacity">Seats <span class="a-req">*</span></label>
          <input type="number" id="seatingcapacity" name="seatingcapacity" class="form-control" min="1" max="30" step="1" inputmode="numeric" value="<?php echo h($val('seatingcapacity', 'SeatingCapacity')); ?>" required>
        </div>
      </div>
    </section>

    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-list-check" aria-hidden="true"></i>Features</h2></div>
      <div class="a-card__body a-checks">
        <?php foreach (VEHICLE_FEATURES as $field => [$column, $label, $icon]) {
          $checked = $posted ? !empty($_POST[$field]) : (int)($vehicle->$column ?? 0) === 1; ?>
          <label class="a-check" for="<?php echo $field; ?>">
            <input class="form-check-input" type="checkbox" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="1"<?php echo $checked ? ' checked' : ''; ?>>
            <i class="fa <?php echo $icon; ?>" aria-hidden="true"></i><?php echo h($label); ?>
          </label>
        <?php } ?>
      </div>
    </section>

    <section class="a-card a-form-section" id="images">
      <div class="a-card__head">
        <h2 class="a-card__title"><i class="fa fa-images" aria-hidden="true"></i>Images</h2>
        <span class="a-muted">Drag photos onto a slot. Large photos are resized in your browser before upload.</span>
      </div>
      <div class="a-card__body">
        <div class="a-images">
          <?php for ($n = 1; $n <= 5; $n++) {
            echo vehicle_image_slot($n, $vehicle ? $vehicle->{"Vimage$n"} : null, $n <= 4, $n === 5);
          } ?>
        </div>
      </div>
    </section>
    <?php
}
