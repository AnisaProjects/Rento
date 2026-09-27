<?php
// Admin top bar (included by admin_page_start). Uses $adminName from there.
?>
<header class="a-topbar">
  <button type="button" class="a-topbar__btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#a-sidebar" aria-controls="a-sidebar" aria-label="Open menu">
    <i class="fa fa-bars" aria-hidden="true"></i>
  </button>
  <button type="button" class="a-topbar__btn d-none d-lg-inline-flex" data-a-collapse aria-label="Collapse menu" aria-pressed="false">
    <i class="fa fa-bars-staggered" aria-hidden="true"></i>
  </button>
  <span class="a-topbar__title"><?php echo h($title); ?></span>
  <div class="a-topbar__end">
    <button type="button" class="a-topbar__btn" data-theme-toggle aria-label="Colour theme">
      <i class="fa fa-circle-half-stroke" aria-hidden="true"></i>
    </button>
    <a class="a-topbar__btn d-none d-sm-inline-flex" href="../" target="_blank" rel="noopener" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="View website" aria-label="View website">
      <i class="fa fa-globe" aria-hidden="true"></i>
    </a>
    <div class="dropdown">
      <button class="a-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu: <?php echo h($adminName); ?>">
        <span class="a-user__avatar" aria-hidden="true"><?php echo h(strtoupper(substr($adminName, 0, 1))); ?></span>
        <span class="a-user__name"><?php echo h($adminName); ?></span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><span class="dropdown-item-text small text-body-secondary">Signed in as <strong><?php echo h($adminName); ?></strong></span></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="change-password.php"><i class="fa fa-key fa-fw me-2" aria-hidden="true"></i>Change password</a></li>
        <li><form method="post" action="logout.php"><?php echo csrf_field(); ?><button type="submit" class="dropdown-item"><i class="fa fa-arrow-right-from-bracket fa-fw me-2" aria-hidden="true"></i>Log out</button></form></li>
      </ul>
    </div>
  </div>
</header>
