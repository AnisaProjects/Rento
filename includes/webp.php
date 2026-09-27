<?php
// WebP copies of vehicle photos for the public pages (read by vehicle_image() in helpers.php):
//   admin/img/vehicleimages/webp/<file>-640.webp   (cards, thumbnails)
//   admin/img/vehicleimages/webp/<file>-1600.webp  (full-width gallery)
// Used by the admin upload (every new photo) and by tools/generate-webp.php (all photos).

const WEBP_WIDTHS = [640, 1600];
const WEBP_QUALITY = 80;
const VEHICLE_IMAGE_SOURCE_DIR = __DIR__ . '/../admin/img/vehicleimages';

// Writes the WebP sizes for one photo. Images are never upscaled; up-to-date files are
// skipped unless $force. Returns ['made' => n, 'skipped' => n, 'saved' => bytes] or null
// when the file is not a readable image.
function vehicle_webp_generate(string $file, bool $force = false): ?array
{
    $file = basename($file);
    $path = VEHICLE_IMAGE_SOURCE_DIR . '/' . $file;
    $targetDir = VEHICLE_IMAGE_SOURCE_DIR . '/webp';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755)) {
        return null;
    }
    $info = @getimagesize($path);
    $image = match ($info[2] ?? null) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$image) {
        return null;
    }
    $result = ['made' => 0, 'skipped' => 0, 'saved' => 0];
    imagepalettetotruecolor($image);
    [$width, $height] = [imagesx($image), imagesy($image)];
    foreach (WEBP_WIDTHS as $targetWidth) {
        $out = "$targetDir/$file-$targetWidth.webp";
        if (!$force && is_file($out) && filemtime($out) >= filemtime($path)) {
            $result['skipped']++;
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
        $result['made']++;
        if ($targetWidth === max(WEBP_WIDTHS)) {
            $result['saved'] += filesize($path) - filesize($out);
        }
    }
    imagedestroy($image);
    return $result;
}
