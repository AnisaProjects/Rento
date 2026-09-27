<?php
// Builds the default share image (Open Graph / X card), 1200 × 630:
//   /Applications/MAMP/bin/php/php8.3.14/bin/php tools/make-share-image.php [photo] [out]
// A hero photo, darkened on the left, with the Rento wordmark and a tagline. Uses macOS's Arial;
// any other bold TTF works (edit FONT_*). A custom image can be set in admin -> Site content -> Site & SEO.
const FONT_HEAVY = '/System/Library/Fonts/Supplemental/Arial Black.ttf';
const FONT_BOLD = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
$root = dirname(__DIR__);
$photo = $argv[1] ?? $root . '/assets/images/banner-image-1.jpg';
$out = $argv[2] ?? $root . '/assets/images/share-default.jpg';
[$W, $H] = [1200, 630];

$src = imagecreatefromstring((string)file_get_contents($photo)) ?: exit("Can't read $photo\n");
$img = imagecreatetruecolor($W, $H);
// Cover-crop the photo into 1200 × 630.
$sw = imagesx($src); $sh = imagesy($src);
$scale = max($W / $sw, $H / $sh);
$cw = (int)round($W / $scale); $ch = (int)round($H / $scale);
imagecopyresampled($img, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $W, $H, $cw, $ch);

// Dark gradient from the left (ink-900 #101114) so the text reads on any photo.
for ($x = 0; $x < $W; $x++) {
    $alpha = (int)max(0, min(127, 127 * ($x / $W - 0.05) / 0.75)); // 0 = opaque at the left
    imageline($img, $x, 0, $x, $H, imagecolorallocatealpha($img, 16, 17, 20, min(127, max(18, $alpha))));
}
$white = imagecolorallocate($img, 255, 255, 255);
$orange = imagecolorallocate($img, 255, 90, 54);
$grey = imagecolorallocate($img, 214, 216, 222);

// Wordmark "Rento" with an orange last letter, as in the site header.
$size = 112; $x = 80; $y = 300;
$box = imagettfbbox($size, 0, FONT_HEAVY, 'Rent');
imagettftext($img, $size, 0, $x, $y, $white, FONT_HEAVY, 'Rent');
imagettftext($img, $size, 0, $x + ($box[2] - $box[0]) + 4, $y, $orange, FONT_HEAVY, 'o');
imagefilledrectangle($img, $x + 4, $y + 36, $x + 124, $y + 44, $orange);
imagettftext($img, 40, 0, $x, $y + 112, $white, FONT_BOLD, 'Car rental in Nepal');
imagettftext($img, 26, 0, $x, $y + 168, $grey, FONT_BOLD, 'Self-drive and with driver  ·  Prices in rupees');

imageinterlace($img, true);
imagejpeg($img, $out, 86);
echo "Wrote $out (" . round(filesize($out) / 1024) . " KB)\n";
