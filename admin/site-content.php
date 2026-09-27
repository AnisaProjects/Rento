<?php
// Admin -> Site content: edit the texts and images registered in includes/site-content.php.
// Only changed values are stored (tblsitecontent); an empty field uses the default text.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/upload.php';
admin_require_login();

$group = (string)($_GET['group'] ?? 'home');
if (!isset(SITE_CONTENT[$group])) {
  redirect_to('site-content.php?group=home');
}
[$groupLabel, $groupLead, $items] = SITE_CONTENT[$group];

// Links may be site pages (car-listing?type=SUV) or full http(s) URLs; social links must be full URLs.
function site_content_valid_url(string $key, string $value): bool
{
  if ($value === '') return true;
  if (preg_match('#^https?://[^\s<>"]+$#i', $value)) return true;
  // Social links and the site address must be full http(s) URLs.
  return !str_starts_with($key, 'social_') && $key !== 'site_url' && preg_match('#^[a-z0-9][a-z0-9/_\-.?=&%\#]*$#i', $value);
}

if (isset($_POST['savecontent'])) {
  $saved = site_content_saved($dbh);
  $upsert = $dbh->prepare("INSERT INTO tblsitecontent(ContentKey, ContentValue) VALUES(:k, :v) ON DUPLICATE KEY UPDATE ContentValue = VALUES(ContentValue)");
  $remove = $dbh->prepare("DELETE FROM tblsitecontent WHERE ContentKey = :k");
  $errors = [];
  $changed = 0;
  foreach ($items as $key => [$label, $type, $default]) {
    if ($type === 'image') {
      $field = 'img_' . $key;
      $old = $saved[$key] ?? '';
      if (!vehicle_image_missing($field)) {
        try {
          $path = store_site_image($field, $label);
          $upsert->execute([':k' => $key, ':v' => $path]);
          delete_site_image($old);
          $changed++;
        } catch (RuntimeException $e) {
          $errors[] = $e->getMessage();
        }
      } elseif (!empty($_POST['reset_' . $key]) && $old !== '') {
        $remove->execute([':k' => $key]);
        delete_site_image($old);
        $changed++;
      }
      continue;
    }
    $value = trim(str_replace("\r\n", "\n", (string)($_POST['c'][$key] ?? '')));
    if ($type !== 'textarea') $value = preg_replace('/\s+/', ' ', $value);
    if ($type === 'url' && !site_content_valid_url($key, $value)) {
      $errors[] = "$label: enter a full link starting with https://" . (str_starts_with($key, 'social_') ? '' : ', or a page on this site such as car-listing') . '.';
      continue;
    }
    $isDefault = $value === '' || $value === $default;
    if ($isDefault && isset($saved[$key])) {
      $remove->execute([':k' => $key]);
      $changed++;
    } elseif (!$isDefault && ($saved[$key] ?? null) !== $value) {
      $upsert->execute([':k' => $key, ':v' => mb_substr($value, 0, 5000)]);
      $changed++;
    }
  }
  if ($errors) {
    flash_set('error', implode(' ', $errors) . ($changed ? " The other $changed change(s) were saved." : ''));
  } else {
    flash_set('success', $changed ? "$groupLabel saved ($changed change" . ($changed === 1 ? '' : 's') . ').' : 'Nothing changed.');
  }
  redirect_to(current_url());
}

$saved = site_content_saved($dbh);
$preview = ['home' => '../', 'about' => '../about-us', 'pages' => '../car-listing', 'footer' => '../', 'seo' => '../'][$group];

admin_page_start($groupLabel, 'site-content.php?group=' . $group, [['Site content', 'site-content.php?group=home']], [
  'libs' => ['images'],
  'lead' => $groupLead,
  'actions' => '<a class="btn btn-light" href="' . $preview . '" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>View website</a>',
]);
?>
<nav class="a-page-tabs" aria-label="Site content sections">
  <?php foreach (SITE_CONTENT as $key => [$label]) { ?>
    <a class="a-filter<?php echo $key === $group ? ' is-active' : ''; ?>" href="site-content.php?group=<?php echo $key; ?>"<?php echo $key === $group ? ' aria-current="page"' : ''; ?>><?php echo h($label); ?></a>
  <?php } ?>
</nav>

<form method="post" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <section class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa fa-pen-ruler" aria-hidden="true"></i><?php echo h($groupLabel); ?></h2>
      <span class="a-muted">Leave a text empty to go back to the default. Changed items are marked.</span>
    </div>
    <div class="a-card__body a-form-grid">
      <?php $section = null;
      foreach ($items as $key => $item) {
        [$label, $type, $default] = $item;
        // Items are grouped by the part of the label before ":" (e.g. "Slide 1: title").
        $itemSection = str_contains($label, ':') ? trim(strstr($label, ':', true)) : '';
        $itemSection = preg_replace('/^(Step|Reason|Box) \d+$/', '$1s', $itemSection);
        if ($itemSection !== $section) {
          $section = $itemSection;
          if ($section !== '') echo '<h3 class="a-span-all a-form-section-title">' . h($section) . '</h3>';
        }
        $label = $itemSection !== '' && str_contains($label, ':') ? ucfirst(trim(substr(strstr($label, ':'), 1))) . (preg_match('/\d+/', $item[0], $m) && str_ends_with($section, 's') ? ' ' . $m[0] : '') : $label;
        $hint = $item[3] ?? '';
        $isChanged = isset($saved[$key]) && $saved[$key] !== '';
        $id = 'c-' . $key;
        $wide = $type === 'textarea'; ?>
        <div class="<?php echo $wide ? 'a-span-all' : ''; ?>">
          <?php if ($type === 'image') {
            $current = content_image($key);
            echo admin_image_field('img_' . $key, $label . ($isChanged ? ' (changed)' : ''), $current !== '' ? '../' . $current : '',
              $hint, $isChanged ? 'reset_' . $key : '', $default !== '' ? 'Restore default' : 'Remove');
          } else { ?>
            <label class="form-label" for="<?php echo $id; ?>"><?php echo h($label); ?><?php if ($isChanged) { ?> <span class="a-badge a-badge--primary">Changed</span><?php } ?></label>
            <?php if ($type === 'textarea') { ?>
              <textarea class="form-control" id="<?php echo $id; ?>" name="c[<?php echo $key; ?>]" rows="3" placeholder="<?php echo h($default); ?>"><?php echo h($saved[$key] ?? $default); ?></textarea>
            <?php } else { ?>
              <input class="form-control" type="text" id="<?php echo $id; ?>" name="c[<?php echo $key; ?>]" value="<?php echo h($saved[$key] ?? $default); ?>" placeholder="<?php echo h($default); ?>"<?php echo $type === 'url' ? ' inputmode="url" spellcheck="false"' : ''; ?>>
            <?php } ?>
            <?php if ($hint !== '' || ($isChanged && $default !== '')) { ?>
              <div class="form-text"><?php echo h(str_replace('{year}', date('Y'), $hint)); ?><?php echo $isChanged && $default !== '' ? ($hint ? ' ' : '') . 'Default: “' . h($default) . '”' : ''; ?></div>
            <?php } ?>
          <?php } ?>
        </div>
      <?php } ?>
    </div>
  </section>
  <div class="a-form-actions">
    <button class="btn btn-primary" name="savecontent" type="submit"><i class="fa fa-check" aria-hidden="true"></i>Save <?php echo h(strtolower($groupLabel)); ?></button>
  </div>
</form>
<?php admin_page_end(); ?>
