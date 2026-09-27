<?php
// GET api/recommend.php?trip=<text>[&budget=3000][&type=SUV][&seats=6][&fuel=Diesel][&from=YYYY-MM-DD&to=YYYY-MM-DD]
// -> active self-drive cars ranked by their real match score, with reasons; driver-only
//    cars that fit are listed separately under with_driver. Explicit parameters win over
//    what is read from the trip text.
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/recommendation_engine.php';
require_once __DIR__ . '/../includes/trip-parser.php';
api_require_get();
api_rate_limit('api_recommend', 60);

const RECOMMEND_TYPES = ['Hatchback', 'Sedan', 'SUV', 'MUV', 'Crossover', 'Coupe', 'Convertible', 'Pickup', 'Van'];
const RECOMMEND_FUELS = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];

$trip = mb_substr(api_param('trip'), 0, 200);
$understood = trip_understand($trip);

$type = api_param('type');
if ($type !== '' && $type !== 'Any' && !in_array($type, RECOMMEND_TYPES, true)) {
    api_error(400, 'invalid_type', 'type must be one of: Any, ' . implode(', ', RECOMMEND_TYPES) . '.');
}
$budget = api_param('budget');
if ($budget !== '' && (!ctype_digit($budget) || (int)$budget < 100 || (int)$budget > 1000000)) {
    api_error(400, 'invalid_budget', 'budget must be a whole number of rupees per day (100 to 1,000,000).');
}
$seats = api_param('seats');
if ($seats !== '' && (!ctype_digit($seats) || (int)$seats < 1 || (int)$seats > 60)) {
    api_error(400, 'invalid_seats', 'seats must be between 1 and 60.');
}
$fuel = api_param('fuel');
if ($fuel !== '' && !in_array($fuel, RECOMMEND_FUELS, true)) {
    api_error(400, 'invalid_fuel', 'fuel must be one of: ' . implode(', ', RECOMMEND_FUELS) . '.');
}
$from = api_param('from') !== '' ? api_date('from') : null;
$to = api_param('to') !== '' ? api_date('to') : null;
if ((api_param('from') !== '' && !$from) || (api_param('to') !== '' && !$to)) {
    api_error(400, 'invalid_dates', 'from and to must be dates as YYYY-MM-DD.');
}
if (($from && !$to) || ($to && !$from)) {
    api_error(400, 'invalid_dates', 'Give both from and to, or neither.');
}
if ($from && ($from < date('Y-m-d') || $to < $from || rental_days($from, $to) > 90)) {
    api_error(400, 'invalid_range', 'Dates must be from today, with the return on or after the pickup, at most 90 days.');
}
if ($trip === '' && $type === '' && $budget === '' && $seats === '' && $fuel === '') {
    api_error(400, 'missing_trip', 'Describe the trip (trip=...) or give at least one of type, budget, seats or fuel.');
}

$criteria = [
    'type' => $type !== '' ? $type : ($understood['type'] ?? 'Any'),
    'typeNamed' => $type !== '' ? $type !== 'Any' : $understood['typeNamed'],
    'typeWhy' => $understood['typeWhy'],
    'context' => $understood['context'],
    'budget' => $budget !== '' ? (int)$budget : $understood['budget'],
    'people' => $seats !== '' ? (int)$seats : $understood['people'],
    'fuel' => $fuel !== '' ? $fuel : $understood['fuel'],
    'tripType' => $understood['tripType'],
    'from' => $from,
    'to' => $to,
];
// Anonymous API: no past rentals are used.
$result = (new CarRecommendationEngine($dbh))->recommend($criteria, null, 12);
$format = fn($m) => [
    'car' => api_car_summary($m['car']),
    'score' => $m['score'] !== null ? round($m['score'], 3) : null,
    'match_percent' => $m['percent'],
    'price_per_day' => $m['price'],
    'trip_total' => $m['tripTotal'],
    'reasons' => $m['reasons'],
    'cautions' => $m['cautions'],
];
api_json([
    'ok' => true,
    'understood' => array_filter([
        'type' => $criteria['type'] !== 'Any' ? $criteria['type'] : null, 'budget' => $criteria['budget'],
        'type_named' => $criteria['typeNamed'] ?: null, 'context' => $criteria['context'] ?: null,
        'seats' => $criteria['people'], 'fuel' => $criteria['fuel'], 'trip_type' => $criteria['tripType'],
        'from' => $from, 'to' => $to,
    ], fn($v) => $v !== null),
    'pricing' => PRICING_ENABLED ? 'smart' : 'base',
    'count' => count($result['matches']),
    'excluded' => $result['excluded'],
    'matches' => array_map($format, $result['matches']),
    'with_driver' => array_map($format, $result['withDriver']),
]);
