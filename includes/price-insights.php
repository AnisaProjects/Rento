<?php
// Card prices and card badges from the smart-pricing forecast (tbl_price_forecast).
// Everything here is computed from real data; a badge only appears when its statement is true.
//
//   car_price_info($dbh, $carId) -> ['from' => lowest available price in the next 30 days,
//                                    'base', 'fromDate', 'avg30', 'badges' => [[label, tooltip, kind], ...]]
//
// Badges (at most two per card, in this order):
//   Below market          self-drive car whose 30-day average is under the self-drive market rate
//                         for its category (docs/market-benchmarks.json); no badge without data
//   Best value this week  lowest average price over the next 7 days among the active self-drive
//                         cars of the same type (at least two of them, no tie)
//   Price drops soon      the next 3 days average at least 5% above days 4-10

const PRICE_INSIGHT_DAYS = 30;
const PRICE_DROP_MIN = 0.05;
const MARKET_BENCHMARKS_FILE = __DIR__ . '/../docs/market-benchmarks.json';

function market_benchmarks(): ?array
{
    static $data = false;
    if ($data === false) {
        $data = is_file(MARKET_BENCHMARKS_FILE) ? json_decode((string)file_get_contents(MARKET_BENCHMARKS_FILE), true) : null;
    }
    return is_array($data) ? $data : null;
}

// The comparable market category for a car, or null when there is no self-drive data for it.
function market_category(object $car): ?string
{
    $type = (string)$car->CarType;
    $electric = strcasecmp((string)$car->FuelType, 'Electric') === 0;
    return match (true) {
        $type === 'Hatchback' => 'hatchback',
        $type === 'Sedan' => 'sedan',
        in_array($type, ['SUV', 'Crossover'], true) && (int)$car->SeatingCapacity >= 7 => 'suv_7seat',
        in_array($type, ['SUV', 'Crossover'], true) && $electric => 'suv_ev',
        in_array($type, ['SUV', 'Crossover'], true) => 'suv',
        default => null,
    };
}

// Price info for every active car, computed once per request.
function car_price_infos(PDO $dbh): array
{
    static $infos = null;
    if ($infos !== null) {
        return $infos;
    }
    $infos = [];
    $cars = $dbh->query("SELECT id, VehiclesTitle, PricePerDay, CarType, FuelType, SeatingCapacity, DriverOnly FROM tblvehicles WHERE IsActive = 1")->fetchAll(PDO::FETCH_OBJ);
    $days = [];
    if (PRICING_ENABLED) {
        $query = $dbh->prepare("SELECT VehicleId, ForecastDate, Price, BasePrice, IsAvailable FROM tbl_price_forecast
            WHERE ForecastDate BETWEEN CURDATE() AND CURDATE() + INTERVAL " . (PRICE_INSIGHT_DAYS - 1) . " DAY ORDER BY ForecastDate");
        $query->execute();
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $days[(int)$row->VehicleId][] = $row;
        }
    }
    $avg = fn(array $rows) => $rows ? array_sum(array_map(fn($r) => (int)$r->Price, $rows)) / count($rows) : null;
    foreach ($cars as $car) {
        $base = (int)$car->PricePerDay;
        // Ignore a forecast computed for an older base price.
        $rows = array_values(array_filter($days[(int)$car->id] ?? [], fn($r) => (int)$r->BasePrice === $base));
        $free = array_values(array_filter($rows, fn($r) => (int)$r->IsAvailable === 1));
        $info = ['car' => $car, 'base' => $base, 'from' => $base, 'fromDate' => null, 'avg30' => null, 'avg7' => null,
            'soon' => null, 'later' => null, 'laterFrom' => null, 'badges' => [], 'hasForecast' => (bool)$rows];
        if ($free) {
            $cheapest = array_reduce($free, fn($c, $r) => $c === null || (int)$r->Price < (int)$c->Price ? $r : $c);
            $info['from'] = (int)$cheapest->Price;
            $info['fromDate'] = $cheapest->ForecastDate;
            $info['avg30'] = $avg($free);
            $info['avg7'] = $avg(array_slice($free, 0, 7));
            $info['soon'] = $avg(array_slice($rows, 0, 3));
            $later = array_slice($rows, 3, 7);
            $info['later'] = $avg($later);
            $info['laterFrom'] = $later[0]->ForecastDate ?? null;
        }
        $infos[(int)$car->id] = $info;
    }

    // Best value this week: per type, among self-drive cars with a forecast.
    $byType = [];
    foreach ($infos as $id => $info) {
        if ($info['avg7'] !== null && !(int)$info['car']->DriverOnly && $info['car']->CarType) {
            $byType[$info['car']->CarType][$id] = $info['avg7'];
        }
    }
    $benchmarks = market_benchmarks();
    foreach ($infos as $id => &$info) {
        $car = $info['car'];
        $selfDrive = !(int)$car->DriverOnly;
        // Below market
        $category = market_category($car);
        $bench = $category && $benchmarks ? ($benchmarks['categories'][$category] ?? null) : null;
        $compare = $info['avg30'] ?? ($info['hasForecast'] ? null : $info['base']);
        if ($selfDrive && $bench && $compare !== null && $compare < $bench['price']) {
            $info['badges'][] = ['label' => 'Below market', 'kind' => 'market',
                'tooltip' => 'Our average over the next ' . PRICE_INSIGHT_DAYS . ' days is ' . format_price(round($compare)) . '/day; the self-drive market rate for '
                    . $bench['label'] . ' is ' . format_price($bench['price']) . '/day (' . $benchmarks['source'] . ', researched ' . date('j M Y', strtotime($benchmarks['researched'])) . ').'];
        }
        // Best value this week
        $peers = $byType[$car->CarType] ?? [];
        if ($selfDrive && count($peers) >= 2 && isset($peers[$id])) {
            $lowest = min($peers);
            $winners = array_keys($peers, $lowest);
            if (count($winners) === 1 && $winners[0] === $id) {
                $info['badges'][] = ['label' => 'Best value this week', 'kind' => 'value',
                    'tooltip' => 'Lowest average price among our ' . count($peers) . ' ' . $car->CarType . 's for the next 7 days: ' . format_price(round($lowest)) . '/day.'];
            }
        }
        // Price drops soon
        if ($info['soon'] !== null && $info['later'] !== null && $info['soon'] >= $info['later'] * (1 + PRICE_DROP_MIN)) {
            $info['badges'][] = ['label' => 'Price drops soon', 'kind' => 'drop',
                'tooltip' => 'About ' . format_price(round($info['soon'])) . '/day for the next 3 days, then about ' . format_price(round($info['later'])) . '/day from ' . date('j M', strtotime($info['laterFrom'])) . '.'];
        }
        $info['badges'] = array_slice($info['badges'], 0, 2);
    }
    unset($info);
    return $infos;
}

function car_price_info(PDO $dbh, int $carId): ?array
{
    return car_price_infos($dbh)[$carId] ?? null;
}

// Suggested base price for a car: the average rule-based price (no manual overrides) over the
// next $days of the forecast, rounded like prices. Also used by admin -> AI Insights.
// Returns null without a current forecast, else ['suggestion', 'average', 'days', 'reasons' => [label => days]].
function vehicle_price_suggestion(PDO $dbh, int $carId, int $base, int $days = 60): ?array
{
    $query = $dbh->prepare("SELECT Price, Reasons FROM tbl_price_forecast WHERE VehicleId = :id AND BasePrice = :base
        AND ForecastDate BETWEEN CURDATE() AND CURDATE() + INTERVAL " . max(1, $days - 1) . " DAY");
    $query->execute([':id' => $carId, ':base' => $base]);
    $prices = [];
    $impact = [];
    $count = [];
    foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
        $meta = json_decode((string)$row->Reasons, true) ?: [];
        if (!empty($meta['override'])) continue;
        $prices[] = (int)$row->Price;
        foreach ($meta['reasons'] ?? [] as $r) {
            $impact[$r['label']] = ($impact[$r['label']] ?? 0) + abs((float)$r['pct']);
            $count[$r['label']] = ($count[$r['label']] ?? 0) + 1;
        }
    }
    if (!$prices) {
        return null;
    }
    arsort($impact);
    $step = max(1, (int)setting('pricing_round_to'));
    $average = array_sum($prices) / count($prices);
    $reasons = [];
    foreach (array_slice(array_keys($impact), 0, 3) as $label) {
        $reasons[$label] = $count[$label];
    }
    return ['suggestion' => (int)(round($average / $step) * $step), 'average' => $average, 'days' => count($prices), 'reasons' => $reasons];
}
