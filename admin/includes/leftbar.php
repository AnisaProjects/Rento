<?php
// Admin sidebar (included by admin_page_start). Uses $active and $menuCounts from there.
// On desktop it collapses to icons (toggle in the top bar); on mobile it slides in as an offcanvas.
?>
<aside class="a-sidebar offcanvas-lg offcanvas-start" id="a-sidebar" tabindex="-1" aria-label="Admin menu">
  <div class="a-sidebar__brand">
    <a href="dashboard.php" class="a-logo" aria-label="Rento Admin dashboard"><span class="a-logo__full">Rent<span>o</span></span><span class="a-logo__mark" aria-hidden="true">R<span>.</span></span><small>Admin</small></a>
    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#a-sidebar" aria-label="Close menu"></button>
  </div>
  <nav class="a-sidebar__nav">
    <ul class="a-menu">
      <?php foreach (ADMIN_MENU as $menuIndex => [$menuLabel, $menuIcon, $menuTarget]) {
        if (is_array($menuTarget)) {
          $menuOpen = false;
          foreach ($menuTarget as [, $childFile, $childAlso]) {
            if ($childFile === $active || in_array($active, $childAlso, true)) $menuOpen = true;
          }
          $menuGroupId = 'a-menu-' . $menuIndex; ?>
          <li class="a-menu__group<?php echo $menuOpen ? ' is-active' : ''; ?>">
            <button type="button" class="a-menu__link a-menu__toggle<?php echo $menuOpen ? '' : ' collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#<?php echo $menuGroupId; ?>" aria-expanded="<?php echo $menuOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo $menuGroupId; ?>" data-a-tip="<?php echo h($menuLabel); ?>">
              <i class="fa <?php echo $menuIcon; ?> a-menu__icon" aria-hidden="true"></i>
              <span class="a-menu__label"><?php echo h($menuLabel); ?></span>
              <i class="fa fa-chevron-down a-menu__caret" aria-hidden="true"></i>
            </button>
            <ul class="a-menu__sub collapse<?php echo $menuOpen ? ' show' : ''; ?>" id="<?php echo $menuGroupId; ?>">
              <?php foreach ($menuTarget as [$childLabel, $childFile, $childAlso]) {
                $childActive = $childFile === $active || in_array($active, $childAlso, true); ?>
                <li><a class="a-menu__sublink<?php echo $childActive ? ' is-active' : ''; ?>" href="<?php echo $childFile; ?>"<?php echo $childActive ? ' aria-current="page"' : ''; ?>>
                  <?php echo h($childLabel); ?>
                  <?php if (!empty($menuCounts[$childFile])) { ?><span class="a-menu__count"><?php echo $menuCounts[$childFile]; ?></span><?php } ?>
                </a></li>
              <?php } ?>
            </ul>
          </li>
        <?php } else {
          $itemActive = $menuTarget === $active; ?>
          <li>
            <a class="a-menu__link<?php echo $itemActive ? ' is-active' : ''; ?>" href="<?php echo $menuTarget; ?>"<?php echo $itemActive ? ' aria-current="page"' : ''; ?> data-a-tip="<?php echo h($menuLabel); ?>">
              <i class="fa <?php echo $menuIcon; ?> a-menu__icon" aria-hidden="true"></i>
              <span class="a-menu__label"><?php echo h($menuLabel); ?></span>
              <?php if (!empty($menuCounts[$menuTarget])) { ?><span class="a-menu__count" aria-label="<?php echo $menuCounts[$menuTarget]; ?> unread"><?php echo $menuCounts[$menuTarget]; ?></span><?php } ?>
            </a>
          </li>
        <?php }
      } ?>
    </ul>
  </nav>
  <div class="a-sidebar__foot">
    <a class="a-menu__link" href="../" target="_blank" rel="noopener" data-a-tip="View website">
      <i class="fa fa-arrow-up-right-from-square a-menu__icon" aria-hidden="true"></i><span class="a-menu__label">View website</span>
    </a>
  </div>
</aside>
