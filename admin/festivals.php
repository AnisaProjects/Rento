<?php
// Festivals & travel peaks (tblfestivals): view, add, edit and delete, so the calendar
// can be updated every year. Used later by smart pricing (Phase 4).
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

const FESTIVAL_DEMAND = ['high' => 'High', 'medium' => 'Medium'];
const FESTIVAL_VERIFICATION = [
  'official' => 'Official (government list)',
  'two sources' => 'Two sources agree',
  'disagreement' => 'Sources disagree',
  'unverified' => 'Unverified',
  'guidance' => 'General guidance (season)',
];

// Reads and checks the form. Returns [values, error].
function festival_input(): array
{
  $v = [
    'FestivalName' => trim((string)($_POST['festivalname'] ?? '')),
    'StartDate' => (string)($_POST['startdate'] ?? ''),
    'EndDate' => (string)($_POST['enddate'] ?? ''),
    'DemandLevel' => (string)($_POST['demandlevel'] ?? ''),
    'Verification' => (string)($_POST['verification'] ?? ''),
    'SourceUrl' => trim((string)($_POST['sourceurl'] ?? '')),
    'Notes' => trim((string)($_POST['notes'] ?? '')),
  ];
  $isDate = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
  $error = match (true) {
    $v['FestivalName'] === '' => 'Please enter the festival name.',
    !$isDate($v['StartDate']) || !$isDate($v['EndDate']) => 'Please enter valid start and end dates.',
    $v['EndDate'] < $v['StartDate'] => 'The end date must be on or after the start date.',
    !isset(FESTIVAL_DEMAND[$v['DemandLevel']]) => 'Please choose the demand level.',
    !isset(FESTIVAL_VERIFICATION[$v['Verification']]) => 'Please choose how the dates were checked.',
    $v['SourceUrl'] !== '' && !preg_match('#^https?://\S+$#i', $v['SourceUrl']) => 'The source must be a full link starting with https://.',
    default => '',
  };
  $v['SourceUrl'] = $v['SourceUrl'] !== '' ? mb_substr($v['SourceUrl'], 0, 500) : null;
  $v['FestivalName'] = mb_substr($v['FestivalName'], 0, 150);
  return [$v, $error];
}

if (isset($_POST['addfestival']) || isset($_POST['updatefestival'])) {
  [$v, $error] = festival_input();
  $id = intval($_POST['updatefestival'] ?? 0);
  if ($error !== '') {
    flash_set('error', $error);
    $_SESSION['festival_form'] = $_POST;
    redirect_to('festivals.php' . ($id ? '?edit=' . $id : '') . '#festival-form');
  }
  if ($id) {
    $query = $dbh->prepare("UPDATE tblfestivals SET FestivalName = :FestivalName, StartDate = :StartDate, EndDate = :EndDate, DemandLevel = :DemandLevel,
      Verification = :Verification, SourceUrl = :SourceUrl, Notes = :Notes WHERE id = :id");
    $query->execute($v + [':id' => $id]);
    flash_set('success', $v['FestivalName'] . ' updated.');
  } else {
    $query = $dbh->prepare("INSERT INTO tblfestivals (FestivalName, StartDate, EndDate, DemandLevel, Verification, SourceUrl, Notes)
      VALUES (:FestivalName, :StartDate, :EndDate, :DemandLevel, :Verification, :SourceUrl, :Notes)");
    $query->execute($v);
    flash_set('success', $v['FestivalName'] . ' added.');
  }
  redirect_to('festivals.php');
}

if (isset($_POST['delfestival'])) {
  $query = $dbh->prepare("DELETE FROM tblfestivals WHERE id = :id");
  $query->execute([':id' => intval($_POST['delfestival'])]);
  flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Festival deleted.' : 'That festival no longer exists.');
  redirect_to('festivals.php');
}

$festivals = $dbh->query("SELECT * FROM tblfestivals ORDER BY StartDate, EndDate")->fetchAll(PDO::FETCH_OBJ);
$editId = intval($_GET['edit'] ?? 0);
$editing = null;
foreach ($festivals as $f) {
  if ((int)$f->id === $editId) $editing = $f;
}
// After a failed save, show what was typed.
$form = $_SESSION['festival_form'] ?? null;
unset($_SESSION['festival_form']);
$val = function (string $field, string $column) use ($form, $editing) {
  return $form !== null ? (string)($form[$field] ?? '') : (string)($editing->$column ?? '');
};
$today = date('Y-m-d');
$upcoming = array_values(array_filter($festivals, fn($f) => $f->EndDate >= $today));
$next = $upcoming[0] ?? null;
$toCheck = count(array_filter($upcoming, fn($f) => in_array($f->Verification, ['unverified', 'disagreement'], true)));

admin_page_start('Festivals & Travel Peaks', 'festivals.php', [], [
  'libs' => ['datatables'],
  'lead' => count($upcoming) . ' upcoming' . ($next ? ' · next: ' . $next->FestivalName . ' from ' . admin_date($next->StartDate) : '') . ($toCheck ? " · $toCheck to double-check" : ''),
  'actions' => '<a class="btn btn-primary" href="festivals.php#festival-form"><i class="fa fa-plus" aria-hidden="true"></i>Add festival</a>',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search festivals" data-empty="No festivals yet" data-order='[[1,"asc"]]' data-page-length="25">
      <thead>
        <tr>
          <th data-priority="1">Festival</th>
          <th data-priority="2">Dates</th>
          <th>Days</th>
          <th>Demand</th>
          <th>Checked</th>
          <th>Source</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($festivals as $f) {
          $days = (int)round((strtotime($f->EndDate) - strtotime($f->StartDate)) / 86400) + 1;
          $past = $f->EndDate < $today;
          $now = !$past && $f->StartDate <= $today;
          $variant = ['official' => 'success', 'two sources' => 'info', 'disagreement' => 'danger', 'unverified' => 'warning', 'guidance' => 'neutral'][$f->Verification] ?? 'neutral'; ?>
          <tr<?php echo $past ? ' class="opacity-50"' : ''; ?>>
            <td>
              <span class="a-strong d-block"><?php echo h($f->FestivalName); ?><?php echo $now ? ' ' . admin_badge('Now', 'primary') : ''; ?></span>
              <?php if ($f->Notes) { ?><span class="a-muted a-clip" title="<?php echo h($f->Notes); ?>"><?php echo h($f->Notes); ?></span><?php } ?>
            </td>
            <td data-order="<?php echo h($f->StartDate); ?>" class="text-nowrap"><?php echo admin_date($f->StartDate); ?><?php echo $f->EndDate !== $f->StartDate ? ' – ' . admin_date($f->EndDate) : ''; ?></td>
            <td><?php echo $days; ?></td>
            <td data-order="<?php echo $f->DemandLevel === 'high' ? 2 : 1; ?>"><?php echo $f->DemandLevel === 'high' ? admin_badge('High', 'primary', 'fa-fire') : admin_badge('Medium'); ?></td>
            <td><?php echo admin_badge(FESTIVAL_VERIFICATION[$f->Verification] ?? $f->Verification, $variant); ?></td>
            <td><?php echo $f->SourceUrl ? '<a href="' . h($f->SourceUrl) . '" target="_blank" rel="noopener noreferrer">' . h(parse_url($f->SourceUrl, PHP_URL_HOST)) . '</a>' : '<span class="a-muted">None</span>'; ?></td>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('festivals.php?edit=' . (int)$f->id . '#festival-form', 'fa-pen', 'Edit', 'primary'); ?>
                <?php echo admin_action_button(['delfestival' => $f->id], 'fa-trash', 'Delete festival', 'danger', 'Delete ' . $f->FestivalName . '?'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>

<section class="a-card" id="festival-form">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa fa-calendar-plus" aria-hidden="true"></i><?php echo $editing ? 'Edit ' . h($editing->FestivalName) : 'Add a festival or travel peak'; ?></h2>
    <?php if ($editing) { ?><a href="festivals.php#festival-form" class="small">Add a new one instead</a><?php } ?>
  </div>
  <form method="post" class="a-card__body a-form-grid">
    <?php echo csrf_field(); ?>
    <div class="a-span-all">
      <label class="form-label" for="festivalname">Name <span class="a-req">*</span></label>
      <input class="form-control" id="festivalname" name="festivalname" maxlength="150" required value="<?php echo h($val('festivalname', 'FestivalName')); ?>" placeholder="e.g. Dashain 2027 (BS 2084)">
    </div>
    <div>
      <label class="form-label" for="startdate">Start date <span class="a-req">*</span></label>
      <input class="form-control" type="date" id="startdate" name="startdate" required value="<?php echo h($val('startdate', 'StartDate')); ?>">
    </div>
    <div>
      <label class="form-label" for="enddate">End date <span class="a-req">*</span></label>
      <input class="form-control" type="date" id="enddate" name="enddate" required value="<?php echo h($val('enddate', 'EndDate')); ?>">
      <div class="form-text">The same as the start date for one-day festivals.</div>
    </div>
    <div>
      <label class="form-label" for="demandlevel">Demand level <span class="a-req">*</span></label>
      <select class="form-select" id="demandlevel" name="demandlevel" required>
        <?php foreach (FESTIVAL_DEMAND as $key => $label) { ?><option value="<?php echo $key; ?>"<?php echo $val('demandlevel', 'DemandLevel') === $key ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?>
      </select>
      <div class="form-text">High: Dashain, Tihar, Chhath, New Year and other multi-day peaks.</div>
    </div>
    <div>
      <label class="form-label" for="verification">How the dates were checked <span class="a-req">*</span></label>
      <select class="form-select" id="verification" name="verification" required>
        <?php foreach (FESTIVAL_VERIFICATION as $key => $label) { ?><option value="<?php echo h($key); ?>"<?php echo ($val('verification', 'Verification') ?: 'unverified') === $key ? ' selected' : ''; ?>><?php echo h($label); ?></option><?php } ?>
      </select>
    </div>
    <div class="a-span-all">
      <label class="form-label" for="sourceurl">Source link</label>
      <input class="form-control" type="url" id="sourceurl" name="sourceurl" maxlength="500" value="<?php echo h($val('sourceurl', 'SourceUrl')); ?>" placeholder="https://www.moha.gov.np/...">
    </div>
    <div class="a-span-all">
      <label class="form-label" for="notes">Notes</label>
      <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Key days, BS dates, where sources disagree…"><?php echo h($val('notes', 'Notes')); ?></textarea>
    </div>
    <div class="a-span-all d-flex justify-content-end gap-2">
      <?php if ($editing) { ?>
        <a class="btn btn-light" href="festivals.php">Cancel</a>
        <button class="btn btn-primary" name="updatefestival" value="<?php echo (int)$editing->id; ?>" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save changes</button>
      <?php } else { ?>
        <button class="btn btn-primary" name="addfestival" value="1" type="submit"><i class="fa fa-plus" aria-hidden="true"></i>Add festival</button>
      <?php } ?>
    </div>
  </form>
</section>
<?php admin_page_end(); ?>
