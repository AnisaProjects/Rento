<?php
// "Request booking" enquiry form, shared by package pages and driver-only vehicle pages.
// Requests are stored in tblpackageenquiries (PackageId or VehicleId) and handled in
// admin -> Packages -> Enquiries. CSRF (global), rate limit 'package_enquiry', post/redirect/get.
//
//   enquiry_handle($dbh, ['package' => $pkg])          call before any output; redirects on POST
//   enquiry_handle($dbh, ['vehicle' => $car, 'title' => 'Toyota Hiace (with driver)'])
//   echo enquiry_form($dbh, $actionUrl, $vehicleTypes)  $vehicleTypes: [type => from price] or []

const ENQUIRY_MAX_PASSENGERS = 60;

function enquiry_handle(PDO $dbh, array $target, string $back): void
{
    if (!isset($_POST['requestbooking'])) {
        return;
    }
    $in = [
        'fullname' => trim((string)($_POST['fullname'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'traveldate' => (string)($_POST['traveldate'] ?? ''),
        'pickuplocation' => trim((string)($_POST['pickuplocation'] ?? '')),
        'passengers' => (int)($_POST['passengers'] ?? 0),
        'vehicletype' => (string)($_POST['vehicletype'] ?? ''),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];
    $_SESSION['enquiry_form'] = $in;
    if (rate_limit_exceeded($dbh, 'package_enquiry', $in['email'] ?: null)) {
        flash_set('error', RATE_LIMIT_MESSAGE);
        redirect_to($back);
    }
    $types = $target['types'] ?? [];
    $maxPassengers = (int)($target['maxPassengers'] ?? ENQUIRY_MAX_PASSENGERS);
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['traveldate'])
        && checkdate((int)substr($in['traveldate'], 5, 2), (int)substr($in['traveldate'], 8, 2), (int)substr($in['traveldate'], 0, 4));
    $error = match (true) {
        $in['fullname'] === '' || mb_strlen($in['fullname']) > 120 => 'Please enter your name.',
        !preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $in['phone']) => 'Please enter a phone number we can call, e.g. 98XXXXXXXX.',
        $in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address, or leave it empty.',
        !$validDate || $in['traveldate'] < date('Y-m-d') => 'Please choose a travel date from today onwards.',
        $in['traveldate'] > date('Y-m-d', strtotime('+2 years')) => 'Please choose a travel date within the next two years.',
        $in['pickuplocation'] === '' || mb_strlen($in['pickuplocation']) > 200 => 'Please tell us where to pick you up.',
        $in['passengers'] < 1 || $in['passengers'] > $maxPassengers => 'Please enter the number of passengers (1 to ' . $maxPassengers . ').',
        $types && !isset($types[$in['vehicletype']]) => 'Please choose a vehicle type.',
        mb_strlen($in['notes']) > 1000 => 'Please keep the notes under 1,000 characters.',
        default => '',
    };
    // Every request counts toward the limit, so the form can't be used to flood the inbox.
    rate_limit_record($dbh, 'package_enquiry', $in['email'] ?: null);
    if ($error !== '') {
        flash_set('error', $error);
        redirect_to($back);
    }
    $package = $target['package'] ?? null;
    $vehicle = $target['vehicle'] ?? null;
    $title = (string)($target['title'] ?? ($package->Title ?? ''));
    $insert = $dbh->prepare("INSERT INTO tblpackageenquiries (PackageId, VehicleId, PackageTitle, FullName, Phone, Email, TravelDate, PickupLocation, Passengers, VehicleType, Notes)
        VALUES (:pid, :vid, :title, :name, :phone, :email, :date, :pickup, :passengers, :vehicle, :notes)");
    $insert->execute([
        ':pid' => $package->id ?? null, ':vid' => $vehicle->id ?? null, ':title' => mb_substr($title, 0, 150),
        ':name' => $in['fullname'], ':phone' => $in['phone'], ':email' => $in['email'] !== '' ? $in['email'] : null,
        ':date' => $in['traveldate'], ':pickup' => $in['pickuplocation'], ':passengers' => $in['passengers'],
        ':vehicle' => $in['vehicletype'] !== '' ? $in['vehicletype'] : ($vehicle->CarType ?? null),
        ':notes' => $in['notes'] !== '' ? $in['notes'] : null,
    ]);
    unset($_SESSION['enquiry_form']);
    flash_set('success', 'Thank you! We received your request for ' . $title . ' and will call you to confirm the price and details.');
    redirect_to($back);
}

// The form. $types: [vehicle type => starting price] (empty = no vehicle type choice).
function enquiry_form(PDO $dbh, string $action, array $types = [], int $maxPassengers = ENQUIRY_MAX_PASSENGERS, string $button = 'Send request'): string
{
    // What was typed after an error, or the logged-in customer's details.
    $form = $_SESSION['enquiry_form'] ?? [];
    unset($_SESSION['enquiry_form']);
    if (!$form && !empty($_SESSION['login'])) {
        $me = $dbh->prepare("SELECT FullName, EmailId, ContactNo FROM tblusers WHERE EmailId = :email");
        $me->execute([':email' => $_SESSION['login']]);
        if ($row = $me->fetch(PDO::FETCH_OBJ)) {
            $form = ['fullname' => $row->FullName, 'email' => $row->EmailId, 'phone' => $row->ContactNo];
        }
    }
    $f = fn(string $key) => htmlentities((string)($form[$key] ?? ''));
    ob_start(); ?>
    <form method="post" action="<?php echo htmlentities($action); ?>">
      <?php echo csrf_field(); ?>
      <div class="r-field">
        <label class="r-label" for="enq-name">Full name <span class="r-required">*</span></label>
        <input class="r-input" id="enq-name" name="fullname" autocomplete="name" maxlength="120" required value="<?php echo $f('fullname'); ?>">
      </div>
      <div class="r-form-row">
        <div class="r-field">
          <label class="r-label" for="enq-phone">Phone <span class="r-required">*</span></label>
          <input class="r-input" id="enq-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="<?php echo $f('phone'); ?>" placeholder="98XXXXXXXX">
        </div>
        <div class="r-field">
          <label class="r-label" for="enq-email">Email</label>
          <input class="r-input" id="enq-email" name="email" type="email" autocomplete="email" maxlength="120" value="<?php echo $f('email'); ?>">
        </div>
      </div>
      <div class="r-form-row">
        <div class="r-field">
          <label class="r-label" for="enq-date">Travel date <span class="r-required">*</span></label>
          <input class="r-input" id="enq-date" name="traveldate" type="date" min="<?php echo date('Y-m-d'); ?>" required value="<?php echo $f('traveldate'); ?>">
        </div>
        <div class="r-field">
          <label class="r-label" for="enq-pax">Passengers <span class="r-required">*</span></label>
          <input class="r-input" id="enq-pax" name="passengers" type="number" min="1" max="<?php echo $maxPassengers; ?>" inputmode="numeric" required value="<?php echo $f('passengers') ?: '2'; ?>">
        </div>
      </div>
      <div class="r-field">
        <label class="r-label" for="enq-pickup">Pickup location <span class="r-required">*</span></label>
        <input class="r-input" id="enq-pickup" name="pickuplocation" maxlength="200" required value="<?php echo $f('pickuplocation'); ?>" placeholder="Hotel, area or airport">
      </div>
      <?php if ($types) { ?>
        <div class="r-field">
          <label class="r-label" for="enq-vehicle">Vehicle type <span class="r-required">*</span></label>
          <select class="r-select" id="enq-vehicle" name="vehicletype" required>
            <?php foreach ($types as $type => $price) { ?>
              <option value="<?php echo htmlentities($type); ?>"<?php echo ($form['vehicletype'] ?? '') === $type ? ' selected' : ''; ?>><?php echo htmlentities($type); ?> · from <?php echo format_price($price); ?></option>
            <?php } ?>
          </select>
        </div>
      <?php } ?>
      <div class="r-field">
        <label class="r-label" for="enq-notes">Notes</label>
        <textarea class="r-textarea" id="enq-notes" name="notes" rows="3" maxlength="1000" placeholder="Trip plan, flight number, stops you'd like, luggage…"><?php echo $f('notes'); ?></textarea>
      </div>
      <button class="r-btn r-btn--block r-btn--lg" type="submit" name="requestbooking" value="1"><i class="fa fa-paper-plane" aria-hidden="true"></i><?php echo htmlentities($button); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
