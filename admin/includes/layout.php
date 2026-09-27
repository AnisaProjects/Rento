<?php
// Admin page shell and small UI helpers shared by every admin page.
//
//   require_once __DIR__ . '/includes/layout.php';
//   admin_require_login();
//   ... handle POST, then flash_set(...) + redirect_to(current_url()) ...
//   admin_page_start('Manage Brands', 'manage-brands.php', [['Brands', 'manage-brands.php']], ['libs' => ['datatables']]);
//   ... page content ...
//   admin_page_end();
require_once __DIR__ . '/config.php';

// Optional libraries a page can ask for with ['libs' => [...]].
const ADMIN_LIBS = [
    'datatables' => [
        'css' => ['../assets/vendor/datatables/css/dataTables.bootstrap5.min.css', '../assets/vendor/datatables/css/responsive.bootstrap5.min.css'],
        'js' => ['../assets/vendor/datatables/js/dataTables.min.js', '../assets/vendor/datatables/js/dataTables.bootstrap5.min.js', '../assets/vendor/datatables/js/dataTables.responsive.min.js', '../assets/vendor/datatables/js/responsive.bootstrap5.min.js'],
    ],
    'charts' => ['css' => [], 'js' => ['../assets/vendor/chartjs/chart.umd.min.js']],
    'quill' => ['css' => ['../assets/vendor/quill/quill.snow.css'], 'js' => ['../assets/vendor/quill/quill.js']],
    'images' => ['css' => [], 'js' => ['js/image-manager.js']],
];

// Sidebar: [label, icon, file] or [label, icon, [children]]. Each child is [label, file, also-active-for[]].
const ADMIN_MENU = [
    ['Dashboard', 'fa-gauge-high', 'dashboard.php'],
    ['Bookings', 'fa-calendar-check', [
        ['Manage Bookings', 'manage-bookings.php', ['bookig-details.php']],
        ['New', 'new-bookings.php', []],
        ['Confirmed', 'confirmed-bookings.php', []],
        ['Canceled', 'canceled-bookings.php', []],
    ]],
    ['Vehicles', 'fa-car', [
        ['Manage Vehicles', 'manage-vehicles.php', ['edit-vehicle.php', 'changeimage1.php', 'changeimage2.php', 'changeimage3.php', 'changeimage4.php', 'changeimage5.php']],
        ['Post a Vehicle', 'post-avehical.php', []],
    ]],
    ['Brands', 'fa-tags', [
        ['Manage Brands', 'manage-brands.php', ['edit-brand.php']],
        ['Create Brand', 'create-brand.php', []],
    ]],
    ['Registered Users', 'fa-users', 'reg-users.php'],
    ['Testimonials', 'fa-comment-dots', 'testimonials.php'],
    ['Contact Us Queries', 'fa-envelope-open-text', 'manage-conactusquery.php'],
    ['Subscribers', 'fa-at', 'manage-subscribers.php'],
    ['Manage Pages', 'fa-file-lines', 'manage-pages.php'],
    ['Contact Info', 'fa-address-card', 'update-contactinfo.php'],
];

// Sends visitors who are not logged in to the admin login page.
function admin_require_login(): void
{
    if (empty($_SESSION['alogin'])) {
        redirect_to('index.php');
    }
}

// Counts shown as small badges in the sidebar (pending bookings, unread queries).
function admin_menu_counts(PDO $dbh): array
{
    static $counts = null;
    if ($counts === null) {
        $counts = [
            'new-bookings.php' => (int)$dbh->query("SELECT COUNT(*) FROM tblbooking WHERE Status = 0 OR Status IS NULL")->fetchColumn(),
            'manage-conactusquery.php' => (int)$dbh->query("SELECT COUNT(*) FROM tblcontactusquery WHERE status IS NULL OR status = 0")->fetchColumn(),
        ];
    }
    return $counts;
}

// Opens the page: <head>, sidebar, top bar and the page heading.
//   $active  the file name of the menu item to highlight
//   $crumbs  [[label, url], ...] between Dashboard and this page
//   $opts    'libs' => [...ADMIN_LIBS keys], 'actions' => HTML for the heading's right side,
//            'lead' => one line under the title, 'bodyClass' => extra body class
function admin_page_start(string $title, string $active, array $crumbs = [], array $opts = []): void
{
    global $dbh;
    $GLOBALS['adminLibs'] = $opts['libs'] ?? [];
    $adminName = (string)($_SESSION['alogin'] ?? '');
    $menuCounts = admin_menu_counts($dbh);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#101114">
<title><?php echo htmlentities($title); ?> | Rento Admin</title>
<script>try { if (localStorage.getItem('rento-admin-collapsed') === '1') document.documentElement.classList.add('a-collapsed'); } catch (e) {}</script>
<link rel="icon" type="image/png" href="../assets/images/favicon-icon/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
<?php foreach ($GLOBALS['adminLibs'] as $lib) { foreach (ADMIN_LIBS[$lib]['css'] ?? [] as $href) { ?>
<link rel="stylesheet" href="<?php echo htmlentities($href); ?>">
<?php } } ?>
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="a-body <?php echo htmlentities($opts['bodyClass'] ?? ''); ?>">
<a class="a-skip" href="#a-main">Skip to content</a>
<div class="a-shell">
<?php include __DIR__ . '/leftbar.php'; ?>
<div class="a-main">
<?php include __DIR__ . '/header.php'; ?>
<main id="a-main" class="a-content" tabindex="-1">
  <div class="a-page-head">
    <div>
      <?php if ($active !== 'dashboard.php') { ?><nav aria-label="Breadcrumb">
        <ol class="a-breadcrumb">
          <li><a href="dashboard.php">Dashboard</a></li>
          <?php foreach ($crumbs as [$label, $url]) { ?>
            <li><a href="<?php echo htmlentities($url); ?>"><?php echo htmlentities($label); ?></a></li>
          <?php } ?>
          <li aria-current="page"><?php echo htmlentities($title); ?></li>
        </ol>
      </nav><?php } ?>
      <h1 class="a-page-title"><?php echo htmlentities($title); ?></h1>
      <?php if (!empty($opts['lead'])) { ?><p class="a-page-lead"><?php echo htmlentities($opts['lead']); ?></p><?php } ?>
    </div>
    <?php if (!empty($opts['actions'])) { ?><div class="a-page-actions"><?php echo $opts['actions']; ?></div><?php } ?>
  </div>
<?php
}

// Closes the page: toasts, the shared confirmation modal and scripts.
//   $inlineScript  page-specific JavaScript, run after the libraries
function admin_page_end(string $inlineScript = ''): void
{
    ?>
</main>
</div>
</div>
<?php echo flash_render_toasts(); ?>
<div class="modal fade" id="a-confirm" tabindex="-1" aria-labelledby="a-confirm-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="a-confirm-title">Are you sure?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><p class="mb-0" data-confirm-text></p><div data-confirm-extra></div></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Go back</button>
        <button type="button" class="btn btn-danger" data-confirm-ok>Yes, continue</button>
      </div>
    </div>
  </div>
</div>
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php foreach ($GLOBALS['adminLibs'] ?? [] as $lib) { foreach (ADMIN_LIBS[$lib]['js'] ?? [] as $src) { ?>
<script src="<?php echo htmlentities($src); ?>"></script>
<?php } } ?>
<script src="js/admin.js"></script>
<?php if ($inlineScript !== '') { ?><script><?php echo $inlineScript; ?></script><?php } ?>
</body>
</html>
<?php
}

// Status pill: $variant is success | warning | danger | info | neutral.
function admin_badge(string $label, string $variant = 'neutral', string $icon = ''): string
{
    $iconHtml = $icon !== '' ? '<i class="fa ' . $icon . '" aria-hidden="true"></i>' : '';
    return '<span class="a-badge a-badge--' . $variant . '">' . $iconHtml . htmlentities($label) . '</span>';
}

// Booking status (0 or NULL = new, 1 = confirmed, 2 = cancelled).
function admin_booking_badge($status): string
{
    return match ((int)$status) {
        1 => admin_badge('Confirmed', 'success', 'fa-circle-check'),
        2 => admin_badge('Cancelled', 'danger', 'fa-circle-xmark'),
        default => admin_badge('Pending', 'warning', 'fa-clock'),
    };
}

// Icon-only link with a tooltip.
function admin_icon_link(string $href, string $icon, string $label, string $variant = ''): string
{
    return '<a class="a-icon-btn' . ($variant ? ' a-icon-btn--' . $variant : '') . '" href="' . htmlentities($href)
        . '" data-bs-toggle="tooltip" data-bs-title="' . htmlentities($label) . '" aria-label="' . htmlentities($label) . '">'
        . '<i class="fa ' . $icon . '" aria-hidden="true"></i></a>';
}

// Icon-only POST action (with the CSRF token). $fields are the hidden inputs the handler reads.
// $confirm: message for the confirmation modal (empty = no confirmation).
// $extraHtml: optional form controls shown inside the confirmation modal (e.g. radio choices).
function admin_action_button(array $fields, string $icon, string $label, string $variant = '', string $confirm = '', string $extraHtml = '', string $action = ''): string
{
    $html = '<form method="post" class="a-inline-form"' . ($action !== '' ? ' action="' . htmlentities($action) . '"' : '');
    if ($confirm !== '') {
        $html .= ' data-confirm="' . htmlentities($confirm) . '" data-confirm-title="' . htmlentities($label) . '?"'
            . ' data-confirm-ok="' . htmlentities($label) . '"' . ($variant === 'danger' ? ' data-confirm-danger' : '');
    }
    $html .= '>' . csrf_field();
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlentities($name) . '" value="' . htmlentities((string)$value) . '">';
    }
    if ($extraHtml !== '') {
        $html .= '<template data-confirm-template>' . $extraHtml . '</template>';
    }
    $html .= '<button type="submit" class="a-icon-btn' . ($variant ? ' a-icon-btn--' . $variant : '') . '" data-bs-toggle="tooltip" data-bs-title="'
        . htmlentities($label) . '" aria-label="' . htmlentities($label) . '"><i class="fa ' . $icon . '" aria-hidden="true"></i></button></form>';
    return $html;
}

// Vehicle thumbnail for tables (falls back to an icon when the file is missing).
function admin_vehicle_thumb(?string $file, string $alt = ''): string
{
    $file = basename((string)$file);
    if ($file === '' || !is_file(__DIR__ . '/../img/vehicleimages/' . $file)) {
        return '<span class="a-thumb a-thumb--empty" aria-hidden="true"><i class="fa fa-car"></i></span>';
    }
    $webp = 'img/vehicleimages/webp/' . $file . '-640.webp';
    $src = is_file(__DIR__ . '/../' . $webp) ? $webp : 'img/vehicleimages/' . $file;
    return '<img class="a-thumb" src="' . htmlentities(str_replace('%2F', '/', rawurlencode($src))) . '" alt="' . htmlentities($alt) . '" loading="lazy" width="72" height="48">';
}

// Dates as "27 Sep 2026" (and time when asked). Empty for missing values.
function admin_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return '';
    }
    $time = strtotime($value);
    return $time ? date($withTime ? 'j M Y, H:i' : 'j M Y', $time) : htmlentities($value);
}

// Sortable date cell: shows the friendly date, sorts by the real one.
function admin_date_cell(?string $value, bool $withTime = false): string
{
    $time = $value ? strtotime($value) : false;
    return '<td data-order="' . ($time ?: 0) . '">' . admin_date($value, $withTime) . '</td>';
}

// Number of rental days between two Y-m-d dates, the same way the public site counts them.
function admin_rental_days(?string $from, ?string $to): int
{
    $start = strtotime((string)$from);
    $end = strtotime((string)$to);
    return ($start && $end && $end > $start) ? (int)round(($end - $start) / 86400) : 0;
}
