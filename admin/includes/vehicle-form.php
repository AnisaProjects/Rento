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
        'VehiclesOverview' => trim((string)($_POST['vehicalorcview'] ?? '')),
        'PricePerDay' => (int)($_POST['priceperday'] ?? 0),
        'FuelType' => (string)($_POST['fueltype'] ?? ''),
        'ModelYear' => (int)($_POST['modelyear'] ?? 0),
        'SeatingCapacity' => (int)($_POST['seatingcapacity'] ?? 0),
        'CarType' => (string)($_POST['cartype'] ?? ''),
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
        <input class="a-drop__input" type="file" id="img<?php echo $n; ?>" name="img<?php echo $n; ?>" accept="image/jpeg,image/png,image/webp"<?php echo ($required && !$hasImage) || $alwaysRequired ? ' required' : ''; ?> data-a-drop-input>
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
    ?>
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
      </div>
    </section>

    <section class="a-card a-form-section">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-tag" aria-hidden="true"></i>Pricing and specs</h2></div>
      <div class="a-card__body a-form-grid a-form-grid--3">
        <div>
          <label class="form-label" for="priceperday">Price per day <span class="a-req">*</span></label>
          <div class="input-group">
            <span class="input-group-text">Rs</span>
            <input type="number" id="priceperday" name="priceperday" class="form-control" min="1" max="1000000" step="1" inputmode="numeric" value="<?php echo h($val('priceperday', 'PricePerDay')); ?>" required>
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
