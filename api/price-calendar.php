<?php
// GET api/price-calendar.php?car=<id>&month=YYYY-MM
// -> the price for each remaining day of the month (days beyond the forecast use the base
//    price and are marked "estimate").
require __DIR__ . '/_bootstrap.php';
api_require_get();
api_rate_limit('api_calendar', 120);

$car = api_car();
$month = api_param('month') ?: date('Y-m');
$first = DateTime::createFromFormat('!Y-m-d', $month . '-01');
if (!preg_match('/^\d{4}-\d{2}$/', $month) || !$first || $first->format('Y-m') !== $month) {
    api_error(400, 'invalid_month', 'month must be YYYY-MM.');
}
$thisMonth = new DateTime('first day of this month');
$thisMonth->setTime(0, 0);
if ($first < $thisMonth || $first > (clone $thisMonth)->modify('+12 months')) {
    api_error(400, 'month_out_of_range', 'month must be this month or one of the next 12.');
}

$from = max($first->format('Y-m-d'), date('Y-m-d'));
$to = $first->format('Y-m-t');
$quote = public_quote($dbh, $car, $from, $to);
$prices = array_column($quote['days'], 'price');
api_json([
    'ok' => true,
    'car' => api_car_summary($car),
    'month' => $month,
    'currency' => 'NPR',
    'pricing' => PRICING_ENABLED ? 'smart' : 'base',
    'min_price' => $prices ? min($prices) : null,
    'max_price' => $prices ? max($prices) : null,
    'days' => array_map(fn($d) => [
        'date' => $d['date'], 'price' => $d['price'], 'change_pct' => $d['pct'],
        'available' => $d['available'] && vehicle_is_free($dbh, (int)$car->id, $d['date'], $d['date']),
        'estimate' => $d['source'] === 'base' && PRICING_ENABLED,
        'reasons' => array_map(fn($r) => $r['label'], $d['reasons']),
    ], $quote['days']),
]);
