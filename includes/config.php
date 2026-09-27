<?php
// Rento configuration: the only place that holds DB credentials and app flags.
// Both the site and admin/ load this file (admin/includes/config.php requires it).

// Secrets and per-server values come from, in order:
//   1. environment variables RENTO_DB_HOST, RENTO_DB_PORT, RENTO_DB_USER, RENTO_DB_PASS, RENTO_DB_NAME,
//      RENTO_DEV_MODE (set them in the web server / cron on real hosting, see docs/deployment.md)
//   2. includes/config.local.php, not in git: return ['DB_USER' => ..., 'DB_PASS' => ..., ...];
//   3. the MAMP defaults below (dev mode only; production refuses to start without 1 or 2).
$rentoLocal = is_file(__DIR__ . '/config.local.php') ? (array)(include __DIR__ . '/config.local.php') : [];
$rentoConf = function (string $key, $default) use ($rentoLocal) {
    $env = getenv('RENTO_' . $key);
    return $env !== false && $env !== '' ? $env : ($rentoLocal[$key] ?? $default);
};

// Development mode. While this is true (and no SMTP server is set in admin -> Email), the
// password reset link is shown on screen instead of being emailed.
// Set it to false on any server that real users can reach (RENTO_DEV_MODE=0).
define('APP_DEV_MODE', filter_var($rentoConf('DEV_MODE', true), FILTER_VALIDATE_BOOLEAN));

// DB credentials (MAMP: MySQL 8 on port 8889; PHP connects through the socket).
// On this Mac, config.local.php holds the 'rento' user (SELECT/INSERT/UPDATE/DELETE on rento only).
define('DB_HOST', $rentoConf('DB_HOST', 'localhost'));
define('DB_PORT', (int)$rentoConf('DB_PORT', 8889));
define('DB_USER', $rentoConf('DB_USER', APP_DEV_MODE ? 'root' : ''));
define('DB_PASS', $rentoConf('DB_PASS', APP_DEV_MODE ? 'root' : ''));
define('DB_NAME', $rentoConf('DB_NAME', 'rento'));
// Key for secrets stored in the database (the SMTP password, includes/mailer.php): 32 random bytes, base64.
define('APP_KEY', (string)$rentoConf('APP_KEY', ''));
unset($rentoLocal, $rentoConf);

// Rate limiting (includes/rate_limit.php): failed logins and reset requests allowed
// per IP and per email within the window; the registration email check is per IP.
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_MINUTES', 15);
define('RATE_LIMIT_EMAIL_CHECKS', 20);

// Smart pricing (Phase 4). True: prices come from the precomputed forecast
// (tbl_price_forecast, rules in ml/pricing_rules.py, settings in admin -> Smart Pricing).
// False: every page, quote and booking uses the base PricePerDay.
define('PRICING_ENABLED', true);

// Python for the pricing scripts: the dedicated venv (Python 3.12, ml/requirements.txt).
// Scripts get the DB settings from environment variables set by includes/pricing.php.
define('PRICING_PYTHON_BIN', __DIR__ . '/../ml/.venv/bin/python');

// Nepal time for every date ("today", booking dates, forecasts). MySQL and the Python
// scripts use the server's local time, which is Asia/Kathmandu on this Mac.
date_default_timezone_set('Asia/Kathmandu');

// Never show PHP errors on the page; they go to MAMP's log (/Applications/MAMP/logs/php_error.log).
// Dev mode logs everything, including notices and deprecations (site and admin alike).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(APP_DEV_MODE ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (!function_exists('rento_fatal')) {
    // Logs the real error and shows the visitor a generic message.
    function rento_fatal(string $logMessage): void
    {
        error_log('[Rento] ' . $logMessage);
        if (!headers_sent()) {
            http_response_code(500);
        }
        exit('Something went wrong. Please try again later.');
    }
    set_exception_handler(function (Throwable $e) {
        rento_fatal(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    });
}

// Establish database connection.
try {
    $dbh = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
} catch (PDOException $e) {
    rento_fatal('DB connection failed: ' . $e->getMessage());
}

require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/components.php';
require_once __DIR__ . '/site-content.php';
require_once __DIR__ . '/packages.php';
require_once __DIR__ . '/festivals.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/price-insights.php';
?>