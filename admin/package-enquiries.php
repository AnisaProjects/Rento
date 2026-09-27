<?php
// Package enquiries ("Request booking" on a package page): status new -> contacted ->
// confirmed / cancelled. Filters: ?status=new|contacted|confirmed|cancelled, ?package=<id>.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['setstatus'], $_POST['enquiryid'])) {
  $status = (string)$_POST['setstatus'];
  if (isset(PACKAGE_ENQUIRY_STATUSES[$status])) {
    $query = $dbh->prepare("UPDATE tblpackageenquiries SET Status = :status WHERE id = :id");
    $query->execute([':status' => $status, ':id' => intval($_POST['enquiryid'])]);
    flash_set($query->rowCount() ? 'success' : 'info', $query->rowCount() ? 'Enquiry marked as ' . strtolower(PACKAGE_ENQUIRY_STATUSES[$status][0]) . '.' : 'Nothing changed.');
  }
  redirect_to(current_url());
}

$filter = isset(PACKAGE_ENQUIRY_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : 'all';
$packageFilter = intval($_GET['package'] ?? 0);
$where = [];
$bind = [];
if ($filter !== 'all') { $where[] = 'e.Status = :status'; $bind[':status'] = $filter; }
if ($packageFilter) { $where[] = 'e.PackageId = :pid'; $bind[':pid'] = $packageFilter; }
$query = $dbh->prepare("SELECT e.*, p.id AS pkgId, p.Title AS CurrentTitle, p.IsActive AS PackageActive, v.VehiclesTitle, br.BrandName FROM tblpackageenquiries e
  LEFT JOIN tblpackages p ON p.id = e.PackageId LEFT JOIN tblvehicles v ON v.id = e.VehicleId LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY e.CreatedAt DESC, e.id DESC");
$query->execute($bind);
$enquiries = $query->fetchAll(PDO::FETCH_OBJ);
$counts = ['all' => 0] + array_fill_keys(array_keys(PACKAGE_ENQUIRY_STATUSES), 0);
foreach ($dbh->query("SELECT Status, COUNT(*) n FROM tblpackageenquiries GROUP BY Status") as $row) {
  $counts[$row['Status']] = (int)$row['n'];
  $counts['all'] += (int)$row['n'];
}
$suffix = $packageFilter ? '&package=' . $packageFilter : '';

admin_page_start('Package Enquiries', 'package-enquiries.php', [['Packages', 'manage-packages.php']], [
  'libs' => ['datatables'],
  'lead' => $counts['new'] ? $counts['new'] . ' new request(s) waiting for a call.' : 'Booking requests sent from the package pages.',
]);
?>
<nav class="a-filters mb-3" aria-label="Filter enquiries by status">
  <a class="a-filter<?php echo $filter === 'all' ? ' is-active' : ''; ?>" href="package-enquiries.php<?php echo $packageFilter ? '?package=' . $packageFilter : ''; ?>">All <span class="a-filter__count"><?php echo $counts['all']; ?></span></a>
  <?php foreach (PACKAGE_ENQUIRY_STATUSES as $key => [$label]) { ?>
    <a class="a-filter<?php echo $filter === $key ? ' is-active' : ''; ?>" href="package-enquiries.php?status=<?php echo $key . $suffix; ?>"><?php echo $label; ?> <span class="a-filter__count"><?php echo $counts[$key]; ?></span></a>
  <?php } ?>
  <?php if ($packageFilter) { ?><a class="a-filter" href="package-enquiries.php<?php echo $filter !== 'all' ? '?status=' . $filter : ''; ?>"><i class="fa fa-xmark" aria-hidden="true"></i>All packages</a><?php } ?>
</nav>

<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search enquiries" data-empty="No enquiries yet" data-order='[[0,"desc"]]'>
      <thead>
        <tr>
          <th>Received</th>
          <th data-priority="1">Customer</th>
          <th data-priority="3">Package</th>
          <th>Travel date</th>
          <th>Pickup</th>
          <th>Pax</th>
          <th>Vehicle</th>
          <th>Notes</th>
          <th data-priority="2">Status</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($enquiries as $e) {
          [$statusLabel, $variant] = PACKAGE_ENQUIRY_STATUSES[$e->Status] ?? [$e->Status, 'neutral']; ?>
          <tr>
            <?php echo admin_date_cell($e->CreatedAt, true); ?>
            <td>
              <span class="a-strong d-block"><?php echo h($e->FullName); ?></span>
              <a class="a-muted d-block" href="tel:<?php echo h(preg_replace('/[^0-9+]/', '', $e->Phone)); ?>"><?php echo h($e->Phone); ?></a>
              <?php if ($e->Email) { ?><a class="a-muted" href="mailto:<?php echo h($e->Email); ?>"><?php echo h($e->Email); ?></a><?php } ?>
            </td>
            <td>
              <?php if ($e->pkgId) { ?><a href="package-edit.php?id=<?php echo (int)$e->pkgId; ?>"><?php echo h($e->CurrentTitle); ?></a>
              <?php } elseif ($e->VehicleId && $e->VehiclesTitle !== null) { ?><a href="edit-vehicle.php?id=<?php echo (int)$e->VehicleId; ?>"><?php echo h(vehicle_display_name($e->BrandName, $e->VehiclesTitle)); ?></a> <span class="a-muted">(with driver)</span>
              <?php } else { ?><?php echo h($e->PackageTitle); ?> <span class="a-muted">(deleted)</span><?php } ?>
              <?php if ($e->AdminNotes) { ?><i class="fa fa-note-sticky text-body-secondary ms-1" title="Has admin notes" aria-label="Has admin notes"></i><?php } ?>
            </td>
            <td data-order="<?php echo h($e->TravelDate); ?>" class="text-nowrap"><?php echo admin_date($e->TravelDate); ?><?php echo $e->TravelDate < date('Y-m-d') ? ' <span class="a-muted">(past)</span>' : ''; ?></td>
            <td><?php echo h($e->PickupLocation); ?></td>
            <td><?php echo (int)$e->Passengers; ?></td>
            <td><?php echo h($e->VehicleType ?: '–'); ?></td>
            <td><span class="a-clip" title="<?php echo h($e->Notes); ?>"><?php echo h($e->Notes ?: '–'); ?></span></td>
            <td data-order="<?php echo array_search($e->Status, array_keys(PACKAGE_ENQUIRY_STATUSES)); ?>"><?php echo admin_badge($statusLabel, $variant); ?></td>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('package-enquiry.php?id=' . (int)$e->id, 'fa-eye', 'View and add notes'); ?>
                <?php echo admin_icon_link('tel:' . preg_replace('/[^0-9+]/', '', $e->Phone), 'fa-phone', 'Call ' . $e->FullName); ?>
                <?php if ($e->Status === 'new') echo admin_action_button(['enquiryid' => $e->id, 'setstatus' => 'contacted'], 'fa-headset', 'Mark as contacted', 'primary'); ?>
                <?php if (!in_array($e->Status, ['confirmed', 'cancelled'], true)) echo admin_action_button(['enquiryid' => $e->id, 'setstatus' => 'confirmed'], 'fa-check', 'Confirm', 'success', 'Mark this request from ' . $e->FullName . ' as confirmed?'); ?>
                <?php if ($e->Status !== 'cancelled') echo admin_action_button(['enquiryid' => $e->id, 'setstatus' => 'cancelled'], 'fa-xmark', 'Cancel', 'danger', 'Mark this request from ' . $e->FullName . ' as cancelled?'); ?>
                <?php if ($e->Status === 'cancelled') echo admin_action_button(['enquiryid' => $e->id, 'setstatus' => 'new'], 'fa-rotate-left', 'Reopen as new'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
