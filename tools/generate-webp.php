<?php
// Generates WebP copies of all vehicle photos for the public pages (see includes/webp.php).
// The admin upload already does this for each new photo; run this after copying photos in
// by hand, or with --force to rebuild everything:
//   /Applications/MAMP/bin/php/php8.3.14/bin/php tools/generate-webp.php [--force]
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require __DIR__ . '/../includes/webp.php';

$force = in_array('--force', $argv, true);
$made = 0;
$skipped = 0;
$savedBytes = 0;
foreach (scandir(VEHICLE_IMAGE_SOURCE_DIR) as $file) {
    if (!is_file(VEHICLE_IMAGE_SOURCE_DIR . "/$file") || !preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
        continue;
    }
    $result = vehicle_webp_generate($file, $force);
    if ($result === null) {
        echo "skip (not a readable image): $file\n";
        continue;
    }
    $made += $result['made'];
    $skipped += $result['skipped'];
    $savedBytes += $result['saved'];
}
printf("WebP files written: %d, up to date: %d, full-size saving vs originals: %.1f MB\n", $made, $skipped, $savedBytes / 1048576);
