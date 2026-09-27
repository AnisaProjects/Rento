<?php
// One enquiry (?id=): all details, status buttons and the admin notes editor.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

$id = intval($_GET['id'] ?? 0);
if (isset($_POST['savenotes'])) {
  $notes = trim((string)($_POST['adminnotes'] ?? ''));
  $query = $dbh->prepare("UPDATE tblpackageenquiries SET AdminNotes = :notes WHERE id = :id");
  $query->execute([':notes' => $notes !== '' ? mb_substr($notes, 0, 5000) : null, ':id' => $id]);
  flash_set('success', 'Notes saved.');
  redirect_to(current_url());
}
if (isset($_POST['setstatus']) && isset(PACKAGE_ENQUIRY_STATUSES[$_POST['setstatus']])) {
  $dbh->prepare("UPDATE tblpackageenquiries SET Status = :status WHERE id = :id")->execute([':status' => $_POST['setstatus'], ':id' => $id]);
  flash_set('success', 'Enquiry marked as ' . strtolower(PACKAGE_ENQUIRY_STATUSES[$_POST['setstatus']][0]) . '.');
  redirect_to(current_url());
}

$query = $dbh->prepare("SELECT e.*, p.Title AS CurrentTitle, v.VehiclesTitle, br.BrandName FROM tblpackageenquiries e
  LEFT JOIN tblpackages p ON p.id = e.PackageId
  LEFT JOIN tblvehicles v ON v.id = e.VehicleId LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE e.id = :id");
$query->execute([':id' => $id]);
$e = $query->fetch(PDO::FETCH_OBJ);
if (!$e) {
  flash_set('error', 'That enquiry no longer exists.');
  redirect_to('package-enquiries.php');
}
[$statusLabel, $variant] = PACKAGE_ENQUIRY_STATUSES[$e->Status] ?? [$e->Status, 'neutral'];
$phone = preg_replace('/[^0-9+]/', '', $e->Phone);

$actions = '';
foreach (['contacted' => ['fa-headset', 'Mark as contacted', 'light'], 'confirmed' => ['fa-check', 'Confirm', 'success'], 'cancelled' => ['fa-xmark', 'Cancel', 'light text-danger'], 'new' => ['fa-rotate-left', 'Reopen as new', 'light']] as $status => [$icon, $label, $class]) {
  if ($status === $e->Status || ($status === 'new' && $e->Status !== 'cancelled') || ($status === 'contacted' && $e->Status !== 'new')) continue;
  $actions .= '<form method="post" class="a-inline-form"' . (in_array($status, ['confirmed', 'cancelled'], true) ? ' data-confirm="Mark this request from ' . h($e->FullName) . ' as ' . $status . '?" data-confirm-title="' . h($label) . '?" data-confirm-ok="' . h($label) . '"' . ($status === 'cancelled' ? ' data-confirm-danger' : '') : '') . '>'
    . csrf_field() . '<button class="btn btn-' . $class . '" name="setstatus" value="' . $status . '" type="submit"><i class="fa ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button></form>';
}

admin_page_start('Enquiry from ' . $e->FullName, 'package-enquiry.php', [['Packages', 'manage-packages.php'], ['Enquiries', 'package-enquiries.php']], [
  'lead' => 'Received ' . admin_date($e->CreatedAt, true),
  'actions' => $actions,
]);
?>
<div class="a-detail-grid">
  <div>
    <section class="a-card">
      <div class="a-card__head">
        <h2 class="a-card__title"><i class="fa fa-map-location-dot" aria-hidden="true"></i>Request</h2>
        <?php echo admin_badge($statusLabel, $variant); ?>
      </div>
      <div class="a-card__body">
        <dl class="a-dl">
          <dt>For</dt>
          <dd>
            <?php if ($e->PackageId && $e->CurrentTitle !== null) { ?><a href="package-edit.php?id=<?php echo (int)$e->PackageId; ?>"><?php echo h($e->CurrentTitle); ?></a> <span class="a-muted">(package)</span>
            <?php } elseif ($e->VehicleId && $e->VehiclesTitle !== null) { ?><a href="edit-vehicle.php?id=<?php echo (int)$e->VehicleId; ?>"><?php echo h(vehicle_display_name($e->BrandName, $e->VehiclesTitle)); ?></a> <span class="a-muted">(car with driver)</span>
            <?php } else { ?><?php echo h($e->PackageTitle); ?> <span class="a-muted">(no longer listed)</span><?php } ?>
          </dd>
          <dt>Travel date</dt><dd><?php echo admin_date($e->TravelDate); ?><?php echo $e->TravelDate < date('Y-m-d') ? ' <span class="a-muted">(past)</span>' : ''; ?></dd>
          <dt>Pickup</dt><dd><?php echo h($e->PickupLocation); ?></dd>
          <dt>Passengers</dt><dd><?php echo (int)$e->Passengers; ?></dd>
          <dt>Vehicle type</dt><dd><?php echo h($e->VehicleType ?: '–'); ?></dd>
          <dt>Customer notes</dt><dd><?php echo $e->Notes ? nl2br(h($e->Notes)) : '<span class="a-muted">None</span>'; ?></dd>
          <dt>Last change</dt><dd><?php echo admin_date($e->UpdatedAt, true) ?: '<span class="a-muted">Not changed since it was received</span>'; ?></dd>
        </dl>
      </div>
    </section>

    <section class="a-card">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-note-sticky" aria-hidden="true"></i>Admin notes</h2><span class="a-muted">Only visible in the admin</span></div>
      <form method="post" class="a-card__body">
        <?php echo csrf_field(); ?>
        <label class="visually-hidden" for="adminnotes">Admin notes</label>
        <textarea class="form-control" id="adminnotes" name="adminnotes" rows="6" maxlength="5000" placeholder="Price quoted, car and driver assigned, calls made…"><?php echo h($e->AdminNotes); ?></textarea>
        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-primary" name="savenotes" value="1" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save notes</button>
        </div>
      </form>
    </section>
  </div>

  <div>
    <section class="a-card">
      <div class="a-card__head"><h2 class="a-card__title"><i class="fa fa-user" aria-hidden="true"></i>Customer</h2></div>
      <div class="a-card__body">
        <div class="a-customer">
          <span class="a-customer__avatar" aria-hidden="true"><?php echo h(initials($e->FullName)); ?></span>
          <span class="a-strong"><?php echo h($e->FullName); ?></span>
        </div>
        <dl class="a-dl">
          <dt>Phone</dt><dd><a href="tel:<?php echo h($phone); ?>"><?php echo h($e->Phone); ?></a></dd>
          <dt>Email</dt><dd><?php echo $e->Email ? '<a href="mailto:' . h($e->Email) . '">' . h($e->Email) . '</a>' : '<span class="a-muted">Not given</span>'; ?></dd>
        </dl>
        <div class="d-flex gap-2 mt-3">
          <a class="btn btn-light" href="tel:<?php echo h($phone); ?>"><i class="fa fa-phone" aria-hidden="true"></i>Call</a>
          <?php if ($e->Email) { ?><a class="btn btn-light" href="mailto:<?php echo h($e->Email); ?>?subject=<?php echo rawurlencode('Your Rento request: ' . $e->PackageTitle); ?>"><i class="fa fa-envelope" aria-hidden="true"></i>Email</a><?php } ?>
        </div>
      </div>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
