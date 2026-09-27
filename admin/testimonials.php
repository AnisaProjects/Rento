<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

// eid = hide from the website (status 0); aeid = show on the website (status 1).
foreach (['eid' => 0, 'aeid' => 1] as $field => $status) {
  if (isset($_POST[$field])) {
    $id = intval($_POST[$field]);
    $query = $dbh->prepare("UPDATE tbltestimonial SET status = :status WHERE id = :id");
    $query->bindParam(':status', $status, PDO::PARAM_INT);
    $query->bindParam(':id', $id, PDO::PARAM_INT);
    $query->execute();
    flash_set('success', $status ? 'Testimonial approved: it now shows on the website.' : 'Testimonial hidden from the website.');
    redirect_to(current_url());
  }
}

// LEFT JOIN: testimonials kept after their author's account was deleted still appear here.
$testimonials = $dbh->query("SELECT t.id, t.UserEmail, t.Testimonial, t.PostingDate, t.status, u.FullName
  FROM tbltestimonial t LEFT JOIN tblusers u ON u.EmailId = t.UserEmail ORDER BY t.PostingDate DESC")->fetchAll(PDO::FETCH_OBJ);
$shown = count(array_filter($testimonials, fn($t) => (int)$t->status === 1));

admin_page_start('Manage Testimonials', 'testimonials.php', [], [
  'libs' => ['datatables'],
  'lead' => count($testimonials) . ' testimonials, ' . $shown . ' shown on the website.',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search testimonials" data-empty="No testimonials yet" data-order='[[3,"desc"]]'>
      <thead>
        <tr>
          <th data-priority="1">Customer</th>
          <th data-priority="3">Testimonial</th>
          <th data-priority="2">Status</th>
          <th>Posted</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($testimonials as $t) {
          $isShown = (int)$t->status === 1; ?>
          <tr>
            <td>
              <span class="a-strong d-block"><?php echo $t->FullName !== null ? h($t->FullName) : 'Deleted account'; ?></span>
              <span class="a-muted"><?php echo h($t->UserEmail); ?></span>
            </td>
            <td><span class="a-clip" title="<?php echo h($t->Testimonial); ?>"><?php echo h($t->Testimonial); ?></span></td>
            <td data-order="<?php echo $isShown ? 1 : 0; ?>"><?php echo $isShown ? admin_badge('Shown on website', 'success', 'fa-eye') : admin_badge('Hidden', 'neutral', 'fa-eye-slash'); ?></td>
            <?php echo admin_date_cell($t->PostingDate, true); ?>
            <td>
              <div class="a-actions">
                <?php echo $isShown
                  ? admin_action_button(['eid' => $t->id], 'fa-eye-slash', 'Hide from website', '', 'This testimonial will no longer show on the website.')
                  : admin_action_button(['aeid' => $t->id], 'fa-check', 'Approve and show', 'success'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
