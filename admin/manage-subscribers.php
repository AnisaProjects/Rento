<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

if (isset($_POST['del'])) {
  $id = intval($_POST['del']);
  $query = $dbh->prepare("DELETE FROM tblsubscribers WHERE id = :id");
  $query->bindParam(':id', $id, PDO::PARAM_INT);
  $query->execute();
  flash_set($query->rowCount() ? 'success' : 'error', $query->rowCount() ? 'Subscriber removed.' : 'That subscriber no longer exists.');
  redirect_to(current_url());
}

$subscribers = $dbh->query("SELECT * FROM tblsubscribers ORDER BY PostingDate DESC")->fetchAll(PDO::FETCH_OBJ);
$allEmails = implode(',', array_map(fn($s) => $s->SubscriberEmail, $subscribers));

admin_page_start('Manage Subscribers', 'manage-subscribers.php', [], [
  'libs' => ['datatables'],
  'lead' => count($subscribers) . ' newsletter subscribers.',
  'actions' => $subscribers ? '<a class="btn btn-light" href="mailto:?bcc=' . h(rawurlencode($allEmails)) . '"><i class="fa fa-envelope" aria-hidden="true"></i>Email everyone (BCC)</a>' : '',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table" data-a-table data-search-placeholder="Search subscribers" data-empty="No subscribers yet" data-order='[[1,"desc"]]'>
      <thead>
        <tr>
          <th data-priority="1">Email</th>
          <th>Subscribed</th>
          <th class="no-sort" data-priority="1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscribers as $s) { ?>
          <tr>
            <td class="a-strong"><?php echo h($s->SubscriberEmail); ?></td>
            <?php echo admin_date_cell($s->PostingDate, true); ?>
            <td>
              <div class="a-actions">
                <?php echo admin_icon_link('mailto:' . $s->SubscriberEmail, 'fa-envelope', 'Email subscriber'); ?>
                <?php echo admin_action_button(['del' => $s->id], 'fa-trash', 'Remove subscriber', 'danger', 'Remove ' . $s->SubscriberEmail . ' from the newsletter list?'); ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
