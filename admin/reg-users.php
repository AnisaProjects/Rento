<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['del'])) {
  $id = intval($_POST['del']);
  $query = $dbh->prepare("SELECT id, FullName, EmailId FROM tblusers WHERE id = :id");
  $query->bindParam(':id', $id, PDO::PARAM_INT);
  $query->execute();
  $user = $query->fetch(PDO::FETCH_OBJ);
  // Users with bookings are kept so the booking history keeps its customer details.
  $query = $dbh->prepare("SELECT COUNT(*) FROM tblbooking WHERE userEmail = :email");
  $query->bindValue(':email', $user->EmailId ?? '', PDO::PARAM_STR);
  $query->execute();
  $bookingCount = (int)$query->fetchColumn();
  if (!$user) {
    flash_set('error', 'That user no longer exists.');
  } elseif ($bookingCount > 0) {
    flash_set('error', "This user has $bookingCount booking(s) and cannot be deleted.");
  } else {
    // Asked in the delete dialog: keep the user's testimonials, or delete them too.
    $removeTestimonials = ($_POST['testimonials'] ?? 'keep') === 'delete';
    $dbh->beginTransaction();
    if ($removeTestimonials) {
      $query = $dbh->prepare("DELETE FROM tbltestimonial WHERE UserEmail = :email");
      $query->bindParam(':email', $user->EmailId, PDO::PARAM_STR);
      $query->execute();
      $removedTestimonials = $query->rowCount();
    }
    $query = $dbh->prepare("DELETE FROM tblpasswordresets WHERE UserId = :id AND AccountType = 'user'");
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    $query = $dbh->prepare("DELETE FROM tblusers WHERE id = :id");
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    $dbh->commit();
    flash_set('success', 'User ' . $user->FullName . ' deleted' . ($removeTestimonials ? ", with $removedTestimonials testimonial(s)." : '; their testimonials were kept.'));
  }
  redirect_to(current_url());
}

$users = $dbh->query("SELECT u.*,
    (SELECT COUNT(*) FROM tblbooking b WHERE b.userEmail = u.EmailId) AS bookings,
    (SELECT COUNT(*) FROM tbltestimonial t WHERE t.UserEmail = u.EmailId) AS testimonials
  FROM tblusers u ORDER BY u.RegDate DESC")->fetchAll(PDO::FETCH_OBJ);

$deleteChoice = '<fieldset class="mt-3"><legend class="form-label">Their testimonials</legend>'
  . '<div class="form-check"><input class="form-check-input" type="radio" name="testimonials" id="t-keep" value="keep" checked>'
  . '<label class="form-check-label" for="t-keep">Keep them (they stay listed under Testimonials)</label></div>'
  . '<div class="form-check"><input class="form-check-input" type="radio" name="testimonials" id="t-delete" value="delete">'
  . '<label class="form-check-label" for="t-delete">Delete them too</label></div></fieldset>';

admin_page_start('Registered Users', 'reg-users.php', [], [
  'libs' => ['datatables'],
  'lead' => count($users) . ' customer accounts.',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search users" data-empty="No users yet" data-order='[[5,"desc"]]'>
      <thead>
        <tr>
          <th data-priority="1">Name</th>
          <th data-priority="2">Email</th>
          <th>Phone</th>
          <th>City</th>
          <th>Bookings</th>
          <th>Registered</th>
          <th>Date of birth</th>
          <th>Address</th>
          <th>Country</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u) { ?>
          <tr>
            <td>
              <div class="a-vehicle-cell">
                <span class="a-customer__avatar" style="flex-basis:36px;height:36px;font-size:.8rem" aria-hidden="true"><?php echo h(initials($u->FullName)); ?></span>
                <span class="a-strong"><?php echo h($u->FullName); ?></span>
              </div>
            </td>
            <td><a href="mailto:<?php echo h($u->EmailId); ?>"><?php echo h($u->EmailId); ?></a></td>
            <td><?php echo h($u->ContactNo); ?></td>
            <td><?php echo h($u->City); ?></td>
            <td><?php echo (int)$u->bookings ? admin_badge((int)$u->bookings . ' booking' . ((int)$u->bookings === 1 ? '' : 's'), 'info') : '<span class="a-muted">None</span>'; ?></td>
            <?php echo admin_date_cell($u->RegDate); ?>
            <td><?php echo h($u->dob); ?></td>
            <td><?php echo h($u->Address); ?></td>
            <td><?php echo h($u->Country); ?></td>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('mailto:' . $u->EmailId, 'fa-envelope', 'Email ' . $u->FullName); ?>
                <?php if ((int)$u->bookings > 0) { ?>
                  <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Has bookings, so the account is kept"><button type="button" class="a-icon-btn" disabled aria-label="Delete (not possible: has bookings)"><i class="fa fa-trash" aria-hidden="true"></i></button></span>
                <?php } else {
                  $message = 'Delete the account of ' . $u->FullName . ' (' . $u->EmailId . ')? This can\'t be undone.';
                  echo admin_action_button(['del' => $u->id], 'fa-trash', 'Delete user', 'danger', $message, (int)$u->testimonials > 0 ? str_replace('<legend class="form-label">Their testimonials', '<legend class="form-label">Their ' . (int)$u->testimonials . ' testimonial(s)', $deleteChoice) : '');
                } ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
