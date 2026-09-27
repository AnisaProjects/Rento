<?php
// GET api/price-quote.php?car=<id>&from=YYYY-MM-DD&to=YYYY-MM-DD
// -> total for the rental (inclusive days), per-day prices with reasons, and availability.
require __DIR__ . '/_bootstrap.php';
api_require_get();
api_rate_limit('api_quote', 120);

const QUOTE_MAX_DAYS = 90;

$car = api_car();
$from = api_date('from');
$to = api_date('to');
if (!$from || !$to) {
    api_error(400, 'invalid_dates', 'from and to must be dates as YYYY-MM-DD.');
}
if ($from < date('Y-m-d')) {
    api_error(400, 'date_in_past', 'The pickup date cannot be in the past.');
}
if ($to < $from) {
    api_error(400, 'invalid_range', 'The return date must be on or after the pickup date.');
}
if (rental_days($from, $to) > QUOTE_MAX_DAYS) {
    api_error(400, 'range_too_long', 'Quotes cover at most ' . QUOTE_MAX_DAYS . ' days.');
}
if ((int)$car->DriverOnly) {
    api_error(409, 'driver_only', 'This car is only rented with a driver. Send a request on the car page.', ['car' => api_car_summary($car)]);
}

$quote = public_quote($dbh, $car, $from, $to);
api_json([
    'ok' => true,
    'car' => api_car_summary($car),
    'from' => $from,
    'to' => $to,
    'rental_days' => $quote['rental_days'],
    'currency' => 'NPR',
    'total' => $quote['total'],
    'base_total' => $quote['base_total'],
    'difference' => $quote['total'] - $quote['base_total'],
    'available' => $quote['available'],
    'pricing' => PRICING_ENABLED ? 'smart' : 'base',
    'source' => $quote['source'],
    'reasons' => array_map(fn($r) => ['label' => $r['label'], 'pct' => $r['pct'], 'days' => $r['days']], $quote['reasons']),
    'reason_text' => pricing_reason_lines($quote),
    'per_day' => array_map(fn($d) => [
        'date' => $d['date'], 'price' => $d['price'], 'change_pct' => $d['pct'],
        'reasons' => $d['reasons'], 'capped' => $d['capped'], 'available' => $d['available'],
    ], $quote['days']),
]);
