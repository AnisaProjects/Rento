<?php
// Old URL of the CMS pages. page.php?type=... permanently redirects to each page's own
// file so existing links and bookmarks keep working; any other type is a 404.
const CMS_PAGE_URLS = ['aboutus' => 'about-us', 'faqs' => 'faqs', 'terms' => 'terms', 'privacy' => 'privacy'];

$pagetype = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (isset(CMS_PAGE_URLS[$pagetype])) {
    header('Location: ' . CMS_PAGE_URLS[$pagetype], true, 301);
    exit;
}
require __DIR__ . '/404.php';
