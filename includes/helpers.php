<?php
// Small formatting helpers shared by the site and admin.

// Nepalese rupees with thousand separators: 4500 => "Rs 4,500".
function format_price($amount): string
{
    return 'Rs ' . number_format((float)$amount);
}

// Daily rental price: 4500 => "Rs 4,500/day".
function format_price_per_day($amount): string
{
    return format_price($amount) . '/day';
}

// "Brand Model" for titles and alt text, without repeating the brand when the
// model name already starts with it ("BMW" + "BMW 5 Series" => "BMW 5 Series").
function vehicle_display_name(?string $brand, ?string $title): string
{
    $brand = trim((string)$brand);
    $title = trim((string)$title);
    if ($brand === '' || stripos($title, $brand) === 0) {
        return $title;
    }
    return $brand . ' ' . $title;
}

// A vehicle photo as <picture>: WebP (see tools/generate-webp.php) with the original
// upload as the fallback, plus width/height, lazy loading and alt text.
//   $opts: 'class' => img class, 'sizes' => sizes attribute, 'lazy' => false for photos
//          visible on first paint.
function vehicle_image(?string $file, string $alt, array $opts = []): string
{
    $file = basename((string)$file);
    if ($file === '') {
        return '';
    }
    $dir = __DIR__ . '/../admin/img/vehicleimages/';
    $url = 'admin/img/vehicleimages/';
    $attrs = ['src' => $url . rawurlencode($file), 'alt' => $alt];
    if (!empty($opts['class'])) {
        $attrs['class'] = $opts['class'];
    }
    $size = @getimagesize($dir . $file);
    if ($size) {
        $attrs['width'] = $size[0];
        $attrs['height'] = $size[1];
    }
    $attrs['loading'] = ($opts['lazy'] ?? true) ? 'lazy' : 'eager';
    $attrs['decoding'] = 'async';
    $img = '<img';
    foreach ($attrs as $name => $value) {
        $img .= ' ' . $name . '="' . htmlentities((string)$value) . '"';
    }
    $img .= '>';

    $sources = [];
    foreach ([640, 1600] as $width) {
        $webp = $file . '-' . $width . '.webp';
        $webpSize = @getimagesize($dir . 'webp/' . $webp);
        if ($webpSize) {
            $sources[$webpSize[0]] = $url . 'webp/' . rawurlencode($webp) . ' ' . $webpSize[0] . 'w';
        }
    }
    if (!$sources) {
        return $img;
    }
    return '<picture><source type="image/webp" srcset="' . htmlentities(implode(', ', $sources)) . '" sizes="'
        . htmlentities($opts['sizes'] ?? '100vw') . '">' . $img . '</picture>';
}

// URL-friendly name: "Maruti Suzuki Wagon R" => "maruti-suzuki-wagon-r".
function vehicle_slug(?string $brand, ?string $title): string
{
    $name = vehicle_display_name($brand, $title);
    $ascii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name) : $name;
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
}

// Clean URL of a vehicle page, relative to the site root: "car/maruti-suzuki-wagon-r-1".
// The number at the end is the id; the name part is only for people and search engines.
function vehicle_url($id, ?string $brand = null, ?string $title = null): string
{
    $slug = vehicle_slug($brand, $title);
    return 'car/' . ($slug === '' ? '' : $slug . '-') . (int)$id;
}
?>
