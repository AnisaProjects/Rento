<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['eid'])) {
  $eid = intval($_POST['eid']);
  $status = 1;
  $query = $dbh->prepare("UPDATE tblcontactusquery SET status = :status WHERE id = :eid");
  $query->bindParam(':status', $status, PDO::PARAM_INT);
  $query->bindParam(':eid', $eid, PDO::PARAM_INT);
  $query->execute();
  flash_set('success', 'Query marked as read.');
  redirect_to(current_url());
}

$queries = $dbh->query("SELECT * FROM tblcontactusquery ORDER BY PostingDate DESC")->fetchAll(PDO::FETCH_OBJ);
$unread = count(array_filter($queries, fn($q) => (int)$q->status !== 1));

admin_page_start('Contact Us Queries', 'manage-conactusquery.php', [], [
  'libs' => ['datatables'],
  'lead' => count($queries) . ' messages from the contact form' . ($unread ? ", $unread unread." : ', all read.'),
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search queries" data-empty="No queries yet" data-order='[[4,"desc"]]'>
      <thead>
        <tr>
          <th data-priority="1">From</th>
          <th>Phone</th>
          <th data-priority="3">Message</th>
          <th data-priority="2">Status</th>
          <th>Received</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($queries as $q) {
          $isRead = (int)$q->status === 1; ?>
          <tr>
            <td>
              <span class="a-strong d-block"><?php echo h($q->name); ?></span>
              <span class="a-muted"><?php echo h($q->EmailId); ?></span>
            </td>
            <td><?php echo h($q->ContactNumber); ?></td>
            <td><span class="a-clip" title="<?php echo h($q->Message); ?>"><?php echo h($q->Message); ?></span></td>
            <td data-order="<?php echo $isRead ? 1 : 0; ?>"><?php echo $isRead ? admin_badge('Read', 'neutral', 'fa-envelope-open') : admin_badge('Unread', 'primary', 'fa-envelope'); ?></td>
            <?php echo admin_date_cell($q->PostingDate, true); ?>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('mailto:' . $q->EmailId . '?subject=' . rawurlencode('Re: your message to Rento'), 'fa-reply', 'Reply by email'); ?>
                <?php if (!$isRead) echo admin_action_button(['eid' => $q->id], 'fa-check', 'Mark as read', 'success'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
