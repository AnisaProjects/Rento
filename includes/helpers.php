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
?>
