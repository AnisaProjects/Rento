<?php
// Generates WebP copies of the vehicle photos for the public pages:
//   admin/img/vehicleimages/webp/<file>-640.webp   (cards, thumbnails)
//   admin/img/vehicleimages/webp/<file>-1600.webp  (full-width gallery)
// Images are never upscaled. Existing WebP files are skipped unless the original is newer.
// Run after uploading new vehicle photos:
//   /Applications/MAMP/bin/php/php8.3.14/bin/php tools/generate-webp.php [--force]
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

const WEBP_WIDTHS = [640, 1600];
const WEBP_QUALITY = 80;

$sourceDir = __DIR__ . '/../admin/img/vehicleimages';
$targetDir = $sourceDir . '/webp';
$force = in_array('--force', $argv, true);
if (!is_dir($targetDir) && !mkdir($targetDir, 0755)) {
    exit("Cannot create $targetDir\n");
}

$made = 0;
$skipped = 0;
$savedBytes = 0;
foreach (scandir($sourceDir) as $file) {
    $path = "$sourceDir/$file";
    if (!is_file($path) || !preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
        continue;
    }
    $info = @getimagesize($path);
    $image = match ($info[2] ?? null) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$image) {
        echo "skip (not a readable image): $file\n";
        continue;
    }
    imagepalettetotruecolor($image);
    [$width, $height] = [imagesx($image), imagesy($image)];
    foreach (WEBP_WIDTHS as $targetWidth) {
        $out = "$targetDir/$file-$targetWidth.webp";
        if (!$force && is_file($out) && filemtime($out) >= filemtime($path)) {
            $skipped++;
            continue;
        }
        $newWidth = min($width, $targetWidth);
        $newHeight = (int)round($height * $newWidth / $width);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagewebp($resized, $out, WEBP_QUALITY);
        imagedestroy($resized);
        $made++;
        if ($targetWidth === max(WEBP_WIDTHS)) {
            $savedBytes += filesize($path) - filesize($out);
        }
    }
    imagedestroy($image);
}
printf("WebP files written: %d, up to date: %d, full-size saving vs originals: %.1f MB\n", $made, $skipped, $savedBytes / 1048576);
