<?php
// Old URL of the CMS pages. page.php?type=... permanently redirects to each page's own
// file so existing links and bookmarks keep working; other types show the template's
// "page not found" state as before.
const CMS_PAGE_URLS = ['aboutus' => 'about-us.php', 'faqs' => 'faqs.php', 'terms' => 'terms.php', 'privacy' => 'privacy.php'];

$pagetype = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (isset(CMS_PAGE_URLS[$pagetype])) {
    header('Location: ' . CMS_PAGE_URLS[$pagetype], true, 301);
    exit;
}
require __DIR__ . '/includes/cms-page.php';
