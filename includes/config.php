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

// Python used by includes/dynamic_pricing.php. It must have the packages in ml/requirements.txt.
define('PRICING_PYTHON_BIN', '/opt/homebrew/bin/python3');

// Never show PHP errors on the page; they go to MAMP's log (/Applications/MAMP/logs/php_error.log).
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
?>