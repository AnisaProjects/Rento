<?php
// Header search (POST "searchdata") and old search links: show the results on the car listing,
// whose URL can be bookmarked and shared (car-listing?q=...).
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

$keyword = trim((string)($_POST['searchdata'] ?? $_GET['q'] ?? ''));
redirect_to('car-listing' . ($keyword !== '' ? '?' . http_build_query(['q' => mb_substr($keyword, 0, 80)]) : ''));
