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
// Drafts (migration 022): a page can hold a draft next to its live text. The website only shows the live
// text; the draft goes live only when an admin publishes it here.
$editDraft = ($_GET['edit'] ?? '') === 'draft';

if (($_POST['submit'] ?? '') === 'SaveDraft' && $page) {
  $draft = clean_cms_html($_POST['pgedetails'] ?? '');
  $dbh->prepare("UPDATE tblpages SET DraftDetail = :d, DraftUpdatedAt = NOW(), DraftNote = COALESCE(DraftNote, :note) WHERE type = :t")
    ->execute([':d' => $draft, ':t' => $pagetype, ':note' => 'Draft: not published yet.']);
  flash_set('success', 'Draft saved. The website still shows the published version.');
  redirect_to('manage-pages.php?type=' . $pagetype);
}
if (isset($_POST['publishdraft']) && $page) {
  $draft = $dbh->prepare("SELECT DraftDetail FROM tblpages WHERE type = :t");
  $draft->execute([':t' => $pagetype]);
  $draft = (string)$draft->fetchColumn();
  if (trim(strip_tags($draft)) === '') {
    flash_set('error', 'There is no draft to publish.');
  } elseif (preg_match('/\[TO CONFIRM/i', $draft) && empty($_POST['confirmplaceholders'])) {
    flash_set('error', 'The draft still contains [TO CONFIRM: …] items. Fill them in (Edit draft) before publishing.');
  } else {
    $dbh->prepare("UPDATE tblpages SET detail = DraftDetail, DraftDetail = NULL, DraftNote = NULL, DraftUpdatedAt = NULL WHERE type = :t")->execute([':t' => $pagetype]);
    flash_set('success', $page[0] . ': the draft is now the published page.');
  }
  redirect_to('manage-pages.php?type=' . $pagetype);
}
if (isset($_POST['discarddraft']) && $page) {
  $dbh->prepare("UPDATE tblpages SET DraftDetail = NULL, DraftNote = NULL, DraftUpdatedAt = NULL WHERE type = :t")->execute([':t' => $pagetype]);
  flash_set('success', 'Draft discarded. The published page is unchanged.');
  redirect_to('manage-pages.php?type=' . $pagetype);
}

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
$pageRow = null;
if ($page) {
  $query = $dbh->prepare("SELECT detail, DraftDetail, DraftNote, DraftUpdatedAt FROM tblpages WHERE type = :pagetype");
  $query->bindParam(':pagetype', $pagetype, PDO::PARAM_STR);
  $query->execute();
  $pageRow = $query->fetch(PDO::FETCH_OBJ) ?: null;
  $hasDraft = $pageRow && trim(strip_tags((string)$pageRow->DraftDetail)) !== '';
  if ($editDraft && !$hasDraft) $editDraft = false;
  $detail = (string)($editDraft ? $pageRow->DraftDetail : ($pageRow->detail ?? ''));
}
$drafts = [];
foreach ($dbh->query("SELECT type FROM tblpages WHERE DraftDetail IS NOT NULL AND DraftDetail <> ''")->fetchAll(PDO::FETCH_COLUMN) as $t) $drafts[$t] = true;

admin_page_start($page ? ($editDraft ? 'Edit draft: ' : 'Edit ') . $page[0] : 'Manage Pages', 'manage-pages.php', $page ? [['Manage Pages', 'manage-pages.php']] : [], [
  'libs' => $page ? ['quill'] : [],
  'lead' => $page ? ($editDraft ? 'You are editing the draft. The website keeps showing the published version until you publish the draft.' : 'Changes show on the website as soon as you save.') : 'Choose a page to edit.',
  'actions' => $page ? '<a class="btn btn-light" href="../' . h($page[1]) . '" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View page</a>' : '',
]);
?>
<nav class="a-page-tabs" aria-label="Pages">
  <?php foreach (CMS_PAGES as $type => [$name]) { ?>
    <a class="a-filter<?php echo $type === $pagetype ? ' is-active' : ''; ?>" href="manage-pages.php?type=<?php echo $type; ?>"<?php echo $type === $pagetype ? ' aria-current="page"' : ''; ?>><?php echo h($name); ?><?php echo isset($drafts[$type]) ? ' <span class="a-filter__count">Draft</span>' : ''; ?></a>
  <?php } ?>
</nav>

<?php if ($page && $hasDraft && !$editDraft) { ?>
  <section class="a-card mb-4" aria-labelledby="draft-title">
    <div class="a-card__head"><h2 class="a-card__title" id="draft-title"><i class="fa fa-file-pen" aria-hidden="true"></i>Draft waiting for review</h2>
      <?php echo admin_badge('Not published', 'warning'); ?></div>
    <div class="a-card__body">
      <div class="a-notice a-notice--warning mb-3" role="note"><i class="fa fa-triangle-exclamation mt-1" aria-hidden="true"></i><div>
        <?php echo h($pageRow->DraftNote ?: 'Draft: not published yet.'); ?>
        <?php if ($pageRow->DraftUpdatedAt) { ?><span class="d-block a-muted small mt-1">Saved <?php echo admin_date($pageRow->DraftUpdatedAt, true); ?>.</span><?php } ?>
        <?php if (preg_match_all('/\[TO CONFIRM/i', (string)$pageRow->DraftDetail, $m)) { ?><strong class="d-block mt-1"><?php echo count($m[0]); ?> item(s) marked [TO CONFIRM: …] still need your details.</strong><?php } ?>
      </div></div>
      <details class="mb-3">
        <summary class="fw-semibold">Preview the draft</summary>
        <div class="a-draft-preview mt-3" tabindex="0" role="region" aria-label="Draft preview"><?php echo clean_cms_html((string)$pageRow->DraftDetail); ?></div>
      </details>
      <div class="d-flex flex-wrap gap-2 justify-content-end">
        <form method="post" data-confirm="Delete this draft? The published page stays as it is." data-confirm-title="Discard the draft?" data-confirm-ok="Discard draft" data-confirm-danger>
          <?php echo csrf_field(); ?><button class="btn btn-light" type="submit" name="discarddraft" value="1"><i class="fa fa-trash" aria-hidden="true"></i>Discard draft</button>
        </form>
        <a class="btn btn-light" href="manage-pages.php?type=<?php echo h($pagetype); ?>&amp;edit=draft"><i class="fa fa-pen" aria-hidden="true"></i>Edit draft</a>
        <form method="post" data-confirm="The draft replaces the page customers see now. Only publish legal text after you have checked it, ideally with a lawyer." data-confirm-title="Publish the draft?" data-confirm-ok="Publish draft">
          <?php echo csrf_field(); ?><button class="btn btn-primary" type="submit" name="publishdraft" value="1"><i class="fa fa-upload" aria-hidden="true"></i>Publish draft</button>
        </form>
      </div>
    </div>
  </section>
<?php } ?>
<?php if ($page) { ?>
  <form method="post" class="a-card" id="page-form">
    <?php echo csrf_field(); ?>
    <div class="a-card__body">
      <?php if ($pagetype === 'faqs') { ?>
        <div class="a-notice mb-3"><i class="fa fa-circle-info mt-1" aria-hidden="true"></i><div>Each question needs its own heading (Heading 3); the text under it is the answer. The FAQs page turns them into an accordion.</div></div>
      <?php } ?>
      <label class="form-label" for="pgedetails"><?php echo $editDraft ? 'Draft content' : 'Page content'; ?></label>
      <div class="a-editor">
        <div id="page-editor" hidden></div>
        <textarea class="form-control" rows="16" name="pgedetails" id="pgedetails" required><?php echo h($detail); ?></textarea>
      </div>
    </div>
    <div class="a-card__foot d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="manage-pages.php<?php echo $editDraft ? '?type=' . h($pagetype) : ''; ?>">Cancel</a>
      <?php if ($editDraft) { ?>
        <button type="submit" name="submit" value="SaveDraft" class="btn btn-primary"><i class="fa fa-floppy-disk" aria-hidden="true"></i>Save draft</button>
      <?php } else { ?>
        <button type="submit" name="submit" value="SaveDraft" class="btn btn-light"<?php echo !empty($hasDraft) ? ' data-bs-toggle="tooltip" data-bs-title="Replaces the saved draft"' : ''; ?>><i class="fa fa-file-pen" aria-hidden="true"></i>Save as draft</button>
        <button type="submit" name="submit" value="Update" class="btn btn-primary"><i class="fa fa-check" aria-hidden="true"></i>Save page</button>
      <?php } ?>
    </div>
  </form>
<?php } else { ?>
  <div class="a-grid-3">
    <?php foreach (CMS_PAGES as $type => [$name, $url]) { ?>
      <a class="a-kpi" href="manage-pages.php?type=<?php echo $type; ?>">
        <span class="a-kpi__icon"><i class="fa fa-file-lines" aria-hidden="true"></i></span>
        <span class="a-kpi__label">Page</span>
        <span class="a-kpi__value fs-5"><?php echo h($name); ?></span>
        <span class="a-kpi__note">/<?php echo h($url); ?><?php echo isset($drafts[$type]) ? ' · <strong>draft waiting for review</strong>' : ''; ?></span>
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
  // Tab and Shift+Tab move the keyboard focus out of the editor instead of inserting a tab or
  // indenting (otherwise keyboard users are trapped in it). Returning true lets the browser handle the key.
  var leaveOnTab = function () { return true; };
  var quill = new Quill(holder, {
    theme: 'snow',
    placeholder: 'Write the page content…',
    modules: {
      keyboard: {
        bindings: {
          tab: { key: 'Tab', handler: leaveOnTab },
          indent: { key: 'Tab', format: ['blockquote', 'indent', 'list'], handler: leaveOnTab },
          outdent: { key: 'Tab', shiftKey: true, format: ['blockquote', 'indent', 'list'], handler: leaveOnTab },
          'indent code-block': null,
          'outdent code-block': null
        }
      },
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
