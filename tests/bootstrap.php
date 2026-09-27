<?php
// PHPUnit bootstrap: loads the application code as the pages do (includes/config.php also connects to the
// database with the app user from includes/config.local.php).
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/Rento/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
$_SERVER['HTTP_HOST'] = 'localhost:8888';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/trip-parser.php';
require_once __DIR__ . '/../includes/recommendation_engine.php';
$GLOBALS['dbh'] = $dbh;
require_once __DIR__ . '/Integration/SiteClient.php';
