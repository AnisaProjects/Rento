<?php
// Checks the settings that matter before real customers use the site and warns about anything unsafe:
//   /path/to/php tools/check-production.php [https://www.example.com/]
// With a URL it also checks over HTTP that internal files (config, logs, vendor, .git, ...) are not served
// and that http redirects to https. Exit code 1 when a check fails. The same checks run inside the web
// server in admin -> System check (that one sees HTTPS and the web server's php.ini).
// Never prints passwords or keys.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/tools/check-production.php';
require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/production-check.php';

$url = $argv[1] ?? (trim(content_raw('site_url')) ?: null);
$checks = production_checks($dbh, ['web' => false, 'url' => $url]);
$icons = ['fail' => "\033[31mFAIL\033[0m", 'warn' => "\033[33mWARN\033[0m", 'pass' => "\033[32mPASS\033[0m", 'info' => "\033[36mINFO\033[0m"];
if (!stream_isatty(STDOUT)) $icons = ['fail' => 'FAIL', 'warn' => 'WARN', 'pass' => 'PASS', 'info' => 'INFO'];
$count = ['fail' => 0, 'warn' => 0, 'pass' => 0, 'info' => 0];
echo "Rento production check (" . date('Y-m-d H:i') . ($url ? ", $url" : ', no URL: HTTP checks skipped') . ")\n\n";
foreach (['fail', 'warn', 'pass', 'info'] as $level) {
    foreach ($checks as [$l, $title, $detail]) {
        if ($l !== $level) continue;
        $count[$l]++;
        echo str_pad($icons[$l], 5) . "  $title\n" . ($detail !== '' && $l !== 'pass' ? "       -> $detail\n" : '');
    }
}
echo "\n{$count['fail']} failed, {$count['warn']} warnings, {$count['pass']} passed.\n";
exit($count['fail'] ? 1 : 0);
