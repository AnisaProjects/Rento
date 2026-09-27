<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
admin_require_login();

// type => [name, public URL]
const CMS_PAGES = [
  'aboutus' => ['About Us', 'about-us'],
  'faqs' => ['FAQs', 'faqs'],
  'terms' => ['Terms and Conditions', 'terms'],
  'privacy' => ['Privacy Policy', 'privacy'],
];
$pagetype = (string)($_GET['type'] ?? '');
$page = CMS_PAGES[$pagetype] ?? null;

if (($_POST['submit'] ?? '') === 'Update' && $page) {
  // Strip script/event handlers but keep the formatting from the editor.
  $pagedetails = clean_cms_html($_POST['pgedetails'] ?? '');
  $query = $dbh->prepare("UPDATE tblpages SET detail = :pagedetails WHERE type = :pagetype");
  $query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
  $query->bindParam(':pagedetails', $pagedetails, PDO::PARAM_STR);
  $query->execute();
  flash_set('success', $page[0] . ' page updated.');
  redirect_to(current_url());
}

$detail = '';
if ($page) {
  $query = $dbh->prepare("SELECT detail FROM tblpages WHERE type = :pagetype");
  $query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
  $query->execute();
  $detail = (string)$query->fetchColumn();
}

admin_page_start($page ? 'Edit ' . $page[0] : 'Manage Pages', 'manage-pages.php', $page ? [['Manage Pages', 'manage-pages.php']] : [], [
  'libs' => $page ? ['quill'] : [],
  'lead' => $page ? 'Changes show on the website as soon as you save.' : 'Choose a page to edit.',
  'actions' => $page ? '<a class="btn btn-light" href="../' . h($page[1]) . '" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View page</a>' : '',
]);
?>
<nav class="a-page-tabs" aria-label="Pages">
  <?php foreach (CMS_PAGES as $type => [$name]) { ?>
    <a class="a-filter<?php echo $type === $pagetype ? ' is-active' : ''; ?>" href="manage-pages.php?type=<?php echo $type; ?>"<?php echo $type === $pagetype ? ' aria-current="page"' : ''; ?>><?php echo h($name); ?></a>
  <?php } ?>
</nav>

<?php if ($page) { ?>
  <form method="post" class="a-card" id="page-form">
    <?php echo csrf_field(); ?>
    <div class="a-card__body">
      <?php if ($pagetype === 'faqs') { ?>
        <div class="a-notice mb-3"><i class="fa fa-circle-info mt-1" aria-hidden="true"></i><div>Each question needs its own heading (Heading 3); the text under it is the answer. The FAQs page turns them into an accordion.</div></div>
      <?php } ?>
      <label class="form-label" for="pgedetails">Page content</label>
      <div class="a-editor">
        <div id="page-editor" hidden></div>
        <textarea class="form-control" rows="16" name="pgedetails" id="pgedetails" required><?php echo h($detail); ?></textarea>
      </div>
    </div>
    <div class="a-card__foot d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="manage-pages.php">Cancel</a>
      <button type="submit" name="submit" value="Update" class="btn btn-primary"><i class="fa fa-check" aria-hidden="true"></i>Save page</button>
    </div>
  </form>
<?php } else { ?>
  <div class="a-grid-3">
    <?php foreach (CMS_PAGES as $type => [$name, $url]) { ?>
      <a class="a-kpi" href="manage-pages.php?type=<?php echo $type; ?>">
        <span class="a-kpi__icon"><i class="fa fa-file-lines" aria-hidden="true"></i></span>
        <span class="a-kpi__label">Page</span>
        <span class="a-kpi__value fs-5"><?php echo h($name); ?></span>
        <span class="a-kpi__note">/<?php echo h($url); ?></span>
      </a>
    <?php } ?>
  </div>
<?php } ?>

<?php admin_page_end($page ? <<<'JS'
(function () {
  // Quill replaces the textarea; the textarea stays as the submitted field (pgedetails)
  // and as the editor when JavaScript is off.
  var textarea = document.getElementById('pgedetails');
  var holder = document.getElementById('page-editor');
  holder.hidden = false;
  textarea.hidden = true;
  textarea.required = false;
  var quill = new Quill(holder, {
    theme: 'snow',
    placeholder: 'Write the page content…',
    modules: {
      toolbar: [
        [{ header: [2, 3, 4, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link', 'blockquote'],
        ['clean']
      ]
    }
  });
  quill.setContents(quill.clipboard.convert({ html: textarea.value }), 'silent');
  quill.root.setAttribute('aria-label', 'Page content');
  document.querySelectorAll('.ql-header .ql-picker-label').forEach(function (el) { el.setAttribute('aria-label', 'Text style'); });
  document.getElementById('page-form').addEventListener('submit', function () {
    // Semantic HTML gives real <ul>/<ol> lists; Quill 2.0.3 writes every space as &nbsp;.
    var html = quill.getLength() > 1 ? quill.getSemanticHTML().replace(/&nbsp;/g, ' ') : '';
    // Quill opens every link in a new tab; links to Rento's own pages should not.
    var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
    doc.querySelectorAll('a[href]').forEach(function (a) {
      if (!/^[a-z][a-z0-9+.-]*:/i.test(a.getAttribute('href'))) {
        a.removeAttribute('target');
        a.removeAttribute('rel');
      }
    });
    textarea.value = html ? doc.body.firstChild.innerHTML : '';
  });
})();
JS : ''); ?>
