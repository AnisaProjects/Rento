<?php
// Rento configuration: the only place that holds DB credentials and app flags.
// Both the site and admin/ load this file (admin/includes/config.php requires it).

// DB credentials (MAMP: MySQL 8 on port 8889; PHP connects through the socket).
define('DB_HOST', 'localhost');
define('DB_PORT', 8889);
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'rento');

// Development mode. MAMP has no mail server, so while this is true the
// password reset link is shown on screen instead of being emailed.
// Set it to false on any server that real users can reach.
define('APP_DEV_MODE', true);

// Rate limiting (includes/rate_limit.php): failed logins and reset requests allowed
// per IP and per email within the window; the registration email check is per IP.
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_MINUTES', 15);
define('RATE_LIMIT_EMAIL_CHECKS', 20);

// Dynamic (AI) pricing. While false, every page shows the base PricePerDay and the
// Python model is never called. Phase 4 turns this back on with proper data.
define('PRICING_ENABLED', false);

// Python used by includes/dynamic_pricing.php. It must have the packages in ml/requirements.txt.
define('PRICING_PYTHON_BIN', '/opt/homebrew/bin/python3');

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
?>