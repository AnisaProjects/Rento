<?php
// Production-readiness checks, shared by tools/check-production.php (command line) and
// admin -> System check (runs inside the web server, so it also sees HTTPS and Apache's php.ini).
//
//   production_checks($dbh, ['web' => bool, 'url' => base URL to probe over HTTP or null])
//   -> [['fail'|'warn'|'pass'|'info', 'Title', 'What to do'], ...]
// Nothing here prints or returns a password or key; values are only described.

function production_checks(PDO $dbh, array $opts = []): array
{
    $web = !empty($opts['web']);
    $out = [];
    $add = function (string $level, string $title, string $detail = '') use (&$out) { $out[] = [$level, $title, $detail]; };
    $root = dirname(__DIR__);

    // ---------- App configuration ----------
    APP_DEV_MODE
        ? $add('fail', 'Development mode is on', 'Set RENTO_DEV_MODE=0 (or DEV_MODE => false in includes/config.local.php). Dev mode shows password reset links on screen when email is off.')
        : $add('pass', 'Development mode is off');
    $local = $root . '/includes/config.local.php';
    $fromEnv = getenv('RENTO_DB_USER') !== false && getenv('RENTO_DB_USER') !== '';
    if ($fromEnv) {
        $add('pass', 'Database settings come from environment variables');
    } elseif (is_file($local)) {
        $perms = fileperms($local) & 0o777;
        ($perms & 0o007)
            ? $add('warn', 'includes/config.local.php is readable by other users (' . decoct($perms) . ')', 'chmod 600 includes/config.local.php (or 640 with the web server group).')
            : $add('pass', 'Secrets are in includes/config.local.php (not in git, mode ' . decoct($perms) . ')');
    } else {
        $add('fail', 'No database settings found', 'Set RENTO_DB_* environment variables or create includes/config.local.php (docs/deployment.md). The code only falls back to MAMP defaults in dev mode.');
    }

    // ---------- Database account ----------
    $user = (string)DB_USER;
    if ($user === 'root') {
        $add('fail', 'The site connects to MySQL as root', 'Create a user with SELECT, INSERT, UPDATE, DELETE on the database only (docs/deployment.md) and use it.');
    }
    if (in_array((string)DB_PASS, ['', 'root', 'password', 'rento', '123456'], true)) {
        $add('fail', 'The database password is empty or a common default', 'Use a long random password for the database user.');
    } elseif (strlen((string)DB_PASS) < 16) {
        $add('warn', 'The database password is short', 'Use at least 16 random characters.');
    }
    try {
        $grants = implode("\n", $dbh->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN));
        $current = (string)$dbh->query("SELECT CURRENT_USER()")->fetchColumn();
        if (preg_match('/GRANT ALL PRIVILEGES ON \*\.\*|\bSUPER\b|WITH GRANT OPTION/i', $grants)) {
            $add('fail', "Database user $current has server-wide or admin privileges", 'The app only needs SELECT, INSERT, UPDATE, DELETE on its own database.');
        } elseif (preg_match('/\b(DROP|ALTER|CREATE|FILE|PROCESS)\b/i', $grants)) {
            $add('warn', "Database user $current can change the schema", 'Run migrations with a separate admin account; keep the app user to SELECT, INSERT, UPDATE, DELETE.');
        } else {
            $add('pass', "Database user $current has only data privileges");
        }
    } catch (PDOException $e) {
        $add('warn', 'Could not read the database user\'s privileges');
    }
    $add('info', 'MySQL ' . $dbh->query("SELECT VERSION()")->fetchColumn() . ', PHP ' . PHP_VERSION);
    version_compare(PHP_VERSION, '8.1', '<') && $add('fail', 'PHP is older than 8.1', 'Use PHP 8.2 or 8.3.');

    // ---------- Keys, email ----------
    function_exists('mail_key') && mail_key() === null
        ? $add('warn', 'APP_KEY is not set', 'Set RENTO_APP_KEY (32 random bytes, base64) or APP_KEY in config.local.php; it encrypts the SMTP password.')
        : $add('pass', 'APP_KEY is set');
    if (function_exists('mail_configured')) {
        mail_configured($dbh)
            ? $add('pass', 'Email (SMTP) is set up')
            : $add(APP_DEV_MODE ? 'warn' : 'fail', 'Email (SMTP) is not set up', 'Admin -> Email. Without it customers cannot reset their password in production.');
        mail_library_available() || $add('fail', 'PHPMailer is not installed', 'Run composer install --no-dev in the site folder.');
    }
    $adminEmail = (int)$dbh->query("SELECT COUNT(*) FROM admin WHERE Email IS NOT NULL AND Email <> ''")->fetchColumn();
    $adminEmail ? $add('pass', 'Admin account email is set') : $add('warn', 'No admin account has an email address', 'Admin -> Change password -> Account email, so admin reset links can be emailed.');

    // ---------- HTTPS and address ----------
    $siteUrl = trim(content_raw('site_url'));
    if ($siteUrl === '') {
        $add(APP_DEV_MODE ? 'warn' : 'fail', 'Site address is not set', 'Admin -> Site content -> Site & SEO -> Site address (https://…). Used for canonical links, the sitemap and emails.');
    } elseif (!str_starts_with(strtolower($siteUrl), 'https://')) {
        $add('fail', 'Site address does not use https', 'Serve the site over HTTPS and set the address to https://…');
    } else {
        $add('pass', 'Site address uses https: ' . $siteUrl);
    }
    if ($web) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $proxied = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        if ($https) $add('pass', 'This page was served over HTTPS (session cookies get the Secure flag)');
        elseif ($proxied) $add('warn', 'HTTPS ends at a proxy', 'PHP sees http, so session cookies are not marked Secure. Set HTTPS=on for PHP (e.g. SetEnvIf X-Forwarded-Proto https HTTPS=on) behind a trusted proxy.');
        else $add(APP_DEV_MODE ? 'warn' : 'fail', 'This page was served over plain http', 'Install a TLS certificate and redirect http to https.');
    }

    // ---------- PHP settings ----------
    $iniDisplay = strtolower((string)get_cfg_var('display_errors'));
    if (in_array($iniDisplay, ['1', 'on', 'stdout', 'stderr'], true)) {
        $add('warn', 'display_errors is on in php.ini' . ($web ? '' : ' (command-line php.ini; check the web one in admin -> System check)'), 'The app turns it off at runtime, but startup errors could still show. Set display_errors = Off.');
    } else {
        $add('pass', 'display_errors is off in php.ini' . ($web ? '' : ' (command line)'));
    }
    ini_get('display_errors') && !in_array(strtolower((string)ini_get('display_errors')), ['0', 'off', ''], true)
        ? $add('fail', 'PHP errors are shown on pages', 'includes/config.php should set display_errors to 0.')
        : $add('pass', 'PHP errors are hidden from visitors and logged');
    ini_get('log_errors') ? $add('pass', 'Errors are logged' . (ini_get('error_log') ? ' to ' . ini_get('error_log') : ' (web server log)')) : $add('warn', 'log_errors is off', 'Set log_errors = On and an error_log path.');
    (int)ini_get('expose_php') ? $add('warn', 'expose_php is on', 'Set expose_php = Off so responses don\'t advertise the PHP version.') : $add('pass', 'expose_php is off');
    $web && (int)ini_get('session.cookie_httponly') === 0 && $add('info', 'session.cookie_httponly is off in php.ini (the app sets HttpOnly itself)');

    // ---------- Files and jobs ----------
    is_dir($root . '/.git') && $add('info', 'A .git folder is in the web root', 'It is blocked by .htaccess; better to deploy without it.');
    is_writable($root . '/logs') ? $add('pass', 'logs/ is writable') : $add('fail', 'logs/ is not writable', 'The forecast and mail logs go there.');
    is_file($root . '/logs/.htaccess') ? $add('pass', 'logs/ is blocked from the web') : $add('fail', 'logs/.htaccess is missing');
    $python = defined('PRICING_PYTHON_BIN') ? PRICING_PYTHON_BIN : '';
    $python !== '' && is_executable($python) ? $add('pass', 'Python for smart pricing is installed') : $add('fail', 'The pricing Python (ml/.venv) is missing', 'python3.12 -m venv ml/.venv && ml/.venv/bin/pip install -r ml/requirements.txt');
    $lastRun = $dbh->query("SELECT MAX(FinishedAt) FROM tbl_forecast_runs WHERE Status = 'done'")->fetchColumn();
    if (!$lastRun) $add('fail', 'Prices have never been computed', 'Run tools/run-forecast.php and schedule it nightly (cron).');
    elseif (strtotime($lastRun) < time() - 36 * 3600) $add('warn', 'Prices were last computed ' . $lastRun, 'The nightly job (cron or launchd) does not seem to run.');
    else $add('pass', 'Prices were computed ' . $lastRun);

    // ---------- Content left from the template ----------
    foreach ($dbh->query("SELECT type, detail, DraftDetail FROM tblpages")->fetchAll(PDO::FETCH_OBJ) as $page) {
        if (preg_match('/yahoo|lorem ipsum|at vero eos/i', (string)$page->detail)) {
            $add('fail', ucfirst($page->type) . ' page shows template text', 'Publish the reviewed draft in admin -> Manage Pages.');
        }
        if (trim((string)$page->DraftDetail) !== '') $add('info', ucfirst($page->type) . ' has a draft waiting for review');
    }
    $address = (string)$dbh->query("SELECT Address FROM tblcontactusinfo LIMIT 1")->fetchColumn();
    preg_match('/laxmi nagar|j&k block/i', $address) && $add('warn', 'The contact address looks like template data ("' . $address . '")', 'Admin -> Contact Info.');

    // ---------- Protected paths (HTTP) ----------
    $base = $opts['url'] ?? null;
    if ($base) {
        $base = rtrim($base, '/') . '/';
        $exposed = 0; $reached = true;
        foreach (['includes/config.local.php', 'includes/config.php', 'vendor/autoload.php', 'composer.json', 'logs/forecast.log',
            'database/migrations/001_password_resets.sql', 'ml/pricing_rules.py', 'docs/decisions.md', 'tools/run-forecast.php', '.git/HEAD', '.gitignore'] as $path) {
            $code = production_http_status($base . $path);
            if ($code === null) { $add('warn', "Could not reach $base to test protected files"); $reached = false; break; }
            if (!in_array($code, [403, 404], true)) $exposed++;
            in_array($code, [403, 404], true) ? null : $add('fail', "$path is reachable over the web (HTTP $code)", 'Check that .htaccess files are honoured (AllowOverride All) or block the path in the server config.');
        }
        if ($reached && !$exposed) $add('pass', 'Internal files are not served (' . $base . ')');
        $plain = production_http_status(preg_replace('#^https://#', 'http://', $base), false);
        if (str_starts_with($base, 'https://') && $plain !== null && !in_array($plain, [301, 302, 307, 308], true)) {
            $add('warn', 'http:// does not redirect to https://', 'Redirect all plain-http requests to https.');
        }
    }
    return $out;
}

function production_http_status(string $url, bool $follow = false): ?int
{
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 8, 'follow_location' => $follow ? 1 : 0, 'max_redirects' => 0],
        'ssl' => ['verify_peer' => true]]);
    $h = @get_headers($url, false, $ctx);
    if (!$h || !preg_match('#HTTP/\S+\s+(\d{3})#', $h[0], $m)) return null;
    return (int)$m[1];
}
