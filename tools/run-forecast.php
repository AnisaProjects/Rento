<?php
// Recompute the smart-pricing forecast from the command line (the nightly launchd job):
//   /Applications/MAMP/bin/php/php8.3.14/bin/php tools/run-forecast.php [nightly]
// It queues a run and waits for ml/batch_forecast.py to finish. DB settings come from
// includes/config.php and reach Python as environment variables (never CLI arguments).
// Exit code: 0 = done, other = failed (details in logs/forecast.log and admin -> Smart Pricing).
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require __DIR__ . '/../includes/config.php';

$reason = preg_replace('/[^a-z0-9 _-]/i', '', $argv[1] ?? 'command line') ?: 'command line';
$dbh->prepare("INSERT INTO tbl_forecast_runs (TriggerReason) VALUES (:r)")->execute([':r' => $reason]);
$code = forecast_start(true);
$status = forecast_status($dbh);
$last = $status['last'];
echo date('Y-m-d H:i:s') . " forecast ($reason): " . ($last->Status ?? 'unknown') . ' - ' . ($last->Message ?? '') . PHP_EOL;
exit($code === 0 && ($last->Status ?? '') === 'done' ? 0 : 1);
