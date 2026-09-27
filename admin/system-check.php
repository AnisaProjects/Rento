<?php
// System check: the production-readiness checks (includes/production-check.php) run inside the web server,
// so they see HTTPS, cookies and the web server's php.ini. The same checks: tools/check-production.php.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/production-check.php';
admin_require_login();

$probe = isset($_GET['probe']) ? seo_base_url() : null; // HTTP checks of protected files take a few seconds
$checks = production_checks($dbh, ['web' => true, 'url' => $probe]);
$counts = array_count_values(array_column($checks, 0)) + ['fail' => 0, 'warn' => 0, 'pass' => 0, 'info' => 0];
$labels = ['fail' => ['Unsafe', 'danger', 'fa-circle-xmark'], 'warn' => ['Check', 'warning', 'fa-triangle-exclamation'], 'pass' => ['OK', 'success', 'fa-circle-check'], 'info' => ['Note', 'info', 'fa-circle-info']];

admin_page_start('System check', 'system-check.php', [], [
  'lead' => $counts['fail'] . ' unsafe, ' . $counts['warn'] . ' to check, ' . $counts['pass'] . ' OK' . (APP_DEV_MODE ? '. Development mode: some items are expected while the site runs on MAMP.' : '.'),
  'actions' => '<a class="btn btn-light" href="system-check.php?probe=1"><i class="fa fa-shield-halved" aria-hidden="true"></i>Also test protected files</a>',
]);
?>
<section class="a-card">
  <div class="a-table-wrap">
    <table class="table a-table mb-0">
      <caption class="visually-hidden">Production checks</caption>
      <thead><tr><th scope="col">Status</th><th scope="col">Check</th><th scope="col">What to do</th></tr></thead>
      <tbody>
        <?php foreach (['fail', 'warn', 'pass', 'info'] as $level) { foreach ($checks as [$l, $title, $detail]) { if ($l !== $level) continue; ?>
          <tr>
            <td class="text-nowrap"><?php echo admin_badge($labels[$l][0], $labels[$l][1], $labels[$l][2]); ?></td>
            <td class="a-strong"><?php echo h($title); ?></td>
            <td class="a-muted"><?php echo $l === 'pass' ? '' : h($detail); ?></td>
          </tr>
        <?php } } ?>
      </tbody>
    </table>
  </div>
</section>
<p class="a-muted mt-3 small">Command line: <code>php tools/check-production.php https://your-site/</code>. Deployment steps: <code>docs/deployment.md</code>.</p>
<?php admin_page_end(); ?>
