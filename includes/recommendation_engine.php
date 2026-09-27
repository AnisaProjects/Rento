<?php
/**
 * Car recommendations (Find My Car, the car-listing smart search, api/recommend.php).
 *
 * Read-only: nothing is written to the database. Each active, self-drive car gets a real
 * weighted score from the criteria that apply to the trip, with a reason for each:
 *
 *   type      30 when the visitor named it ("SUV"), 15 when it was only inferred from the trip
 *   seats     15 (when travellers are known, or the trip is a family / group trip)
 *   budget    20 (graded: comfortably within > just within > up to 10% over)
 *   road      15 (city / highway / mountain, from the trip)
 *   comfort   10 (family, business, luggage or comfort trips)
 *   fuel      10 (when asked)
 *   history   10 (logged-in users with confirmed rentals)
 *   age        5 (always: newer models score a little higher)
 *
 * Match % = share of the applicable weights the car earned, so 100% only when a car fits
 * everything and ties only when cars really are equal on every criterion. Cars that can't
 * work are left out (too few seats, booked on the dates, more than 10% over budget).
 * Sports cars (Coupe/Convertible, or a known sports model) score low for family, luggage and
 * rough-road trips and get a caution. Reasons never claim the visitor asked for something
 * they didn't: an inferred type is "Sedan: suggested for highway trips".
 * Driver-only cars are returned separately as "With driver: request" suggestions.
 *
 *   $result = (new CarRecommendationEngine($dbh))->recommend([
 *       'type' => 'SUV', 'typeNamed' => true, 'budget' => 3000, 'people' => 6, 'tripType' => 'mountain',
 *       'context' => ['family'], 'fuel' => null, 'from' => '2026-10-14', 'to' => '2026-10-16'], $userEmail);
 *   $result['matches']  [['car', 'score', 'percent', 'price', 'tripTotal', 'reasons' => [...], 'cautions' => [...]], ...]
 */
class CarRecommendationEngine
{
    const WEIGHTS = ['typeNamed' => 30, 'typeInferred' => 15, 'seats' => 15, 'budget' => 20, 'road' => 15,
        'comfort' => 10, 'fuel' => 10, 'history' => 10, 'age' => 5];
    const OVER_BUDGET_TOLERANCE = 0.10;
    // How well a car type stands in for a requested type (1 = the same type).
    const TYPE_SIMILARITY = [
        'SUV' => ['Crossover' => 0.7, 'MUV' => 0.6, 'Pickup' => 0.5, 'Van' => 0.3],
        'Sedan' => ['Hatchback' => 0.4, 'Crossover' => 0.4, 'SUV' => 0.3, 'Coupe' => 0.2],
        'Hatchback' => ['Crossover' => 0.5, 'Sedan' => 0.4],
        'MUV' => ['SUV' => 0.7, 'Van' => 0.6],
        'Crossover' => ['SUV' => 0.7, 'Hatchback' => 0.5],
        'Van' => ['MUV' => 0.7],
        'Coupe' => ['Convertible' => 0.8, 'Sedan' => 0.3],
    ];
    // How well each car type suits a kind of road.
    const ROAD_SUITABILITY = [
        'mountain' => ['SUV' => 1, 'Pickup' => 1, 'Crossover' => 0.75, 'MUV' => 0.7, 'Van' => 0.5, 'Sedan' => 0.3, 'Hatchback' => 0.25, 'sports' => 0.05],
        'highway' => ['Sedan' => 1, 'SUV' => 0.95, 'MUV' => 0.9, 'Crossover' => 0.8, 'Van' => 0.7, 'Hatchback' => 0.6, 'Pickup' => 0.6, 'sports' => 0.6],
        'city' => ['Hatchback' => 1, 'Crossover' => 0.85, 'Sedan' => 0.75, 'SUV' => 0.5, 'MUV' => 0.4, 'Pickup' => 0.35, 'Van' => 0.3, 'sports' => 0.5],
    ];
    const ROAD_LABELS = ['mountain' => 'mountain and rough roads', 'highway' => 'highway trips', 'city' => 'city driving'];
    // Comfort for passengers, and room for luggage, by type.
    const COMFORT = ['Sedan' => 1, 'SUV' => 1, 'MUV' => 1, 'Crossover' => 0.8, 'Van' => 0.8, 'Hatchback' => 0.5, 'Pickup' => 0.4, 'sports' => 0.1];
    const LUGGAGE = ['SUV' => 1, 'MUV' => 1, 'Van' => 1, 'Pickup' => 0.8, 'Sedan' => 0.7, 'Crossover' => 0.7, 'Hatchback' => 0.4, 'sports' => 0.05];
    const SPORTS_TYPES = ['Coupe', 'Convertible'];
    const SPORTS_MODELS = '/\b(gt-?r|supra|370z|350z|911|amg gt|corvette|miata|mx-5)\b/i';

    private PDO $dbh;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    private function cars(): array
    {
        return $this->dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Vimage1,
            v.CarType, v.DriverOnly, br.BrandName FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
            WHERE v.IsActive = 1 ORDER BY v.id")->fetchAll(PDO::FETCH_OBJ);
    }

    public static function isSportsCar(object $car): bool
    {
        return in_array((string)$car->CarType, self::SPORTS_TYPES, true) || preg_match(self::SPORTS_MODELS, (string)$car->VehiclesTitle);
    }

    /** Types and brands of the user's confirmed past rentals. */
    public function getUserPreferences(?string $userEmail): array
    {
        $prefs = ['types' => [], 'brands' => [], 'count' => 0];
        if (!$userEmail) {
            return $prefs;
        }
        $query = $this->dbh->prepare("SELECT v.CarType, br.BrandName FROM tblbooking b JOIN tblvehicles v ON v.id = b.VehicleId
            LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand WHERE b.userEmail = :email AND b.Status = 1");
        $query->execute([':email' => $userEmail]);
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $prefs['count']++;
            if ($row->CarType) $prefs['types'][$row->CarType] = ($prefs['types'][$row->CarType] ?? 0) + 1;
            if ($row->BrandName) $prefs['brands'][$row->BrandName] = ($prefs['brands'][$row->BrandName] ?? 0) + 1;
        }
        return $prefs;
    }

    // Daily price for the trip: the smart price on the dates (average), else the base price.
    private function dailyPrice(object $car, ?string $from, ?string $to): array
    {
        if ($from && $to && function_exists('public_quote')) {
            $quote = public_quote($this->dbh, $car, $from, $to);
            if ($quote['ok']) {
                return [(int)round($quote['total'] / max(1, $quote['rental_days'])), $quote['total'], $quote['available'], $quote];
            }
        }
        return [(int)$car->PricePerDay, null, true, null];
    }

    public function recommend(array $trip, ?string $userEmail = null, int $limit = 12): array
    {
        $type = !empty($trip['type']) && $trip['type'] !== 'Any' ? (string)$trip['type'] : null;
        $typeNamed = $type !== null && !empty($trip['typeNamed']);
        $typeWhy = $trip['typeWhy'] ?? null;
        $budget = !empty($trip['budget']) ? (int)$trip['budget'] : null;
        $people = !empty($trip['people']) ? (int)$trip['people'] : null;
        $fuel = !empty($trip['fuel']) ? (string)$trip['fuel'] : null;
        $road = isset(self::ROAD_SUITABILITY[$trip['tripType'] ?? '']) ? $trip['tripType'] : null;
        $context = array_values(array_intersect((array)($trip['context'] ?? []), ['family', 'luggage', 'business', 'group', 'comfort']));
        $family = in_array('family', $context, true);
        // A family or group trip needs at least five seats even without a number.
        $minSeats = max($people ?? 0, ($family || in_array('group', $context, true)) ? 5 : 0);
        $wantsComfort = $family || array_intersect($context, ['business', 'comfort']);
        $wantsLuggage = in_array('luggage', $context, true);
        $from = $trip['from'] ?? null;
        $to = $trip['to'] ?? null;
        $prefs = $this->getUserPreferences($userEmail);

        $matches = [];
        $withDriver = [];
        $excluded = 0;
        foreach ($this->cars() as $car) {
            $carType = (string)$car->CarType;
            $sports = self::isSportsCar($car);
            $kind = $sports ? 'sports' : $carType;
            $seats = (int)$car->SeatingCapacity;
            [$price, $tripTotal, $available, $quote] = $this->dailyPrice($car, $from, $to);
            if ($minSeats && $seats < $minSeats) { $excluded++; continue; }
            if ($from && $to && !$available) { $excluded++; continue; }
            if ($budget && $price > $budget * (1 + self::OVER_BUDGET_TOLERANCE)) { $excluded++; continue; }

            $earned = 0.0;
            $possible = 0.0;
            $reasons = [];
            $cautions = [];
            $add = function (string $criterion, float $score) use (&$earned, &$possible) {
                $weight = self::WEIGHTS[$criterion];
                $possible += $weight;
                $earned += $weight * max(0, min(1, $score));
            };

            if ($type) {
                $similarity = $carType === $type ? 1.0 : (self::TYPE_SIMILARITY[$type][$carType] ?? 0.0);
                if ($sports && $type !== 'Coupe') $similarity = min($similarity, 0.2);
                $add($typeNamed ? 'typeNamed' : 'typeInferred', $similarity);
                if ($similarity >= 1) {
                    $reasons[] = $typeNamed ? "The $type you asked for"
                        : ($family ? "Suits a family trip ($type)" : "$type: suggested for " . ($typeWhy && str_contains($typeWhy, 'travellers') ? $typeWhy : ($road ? self::ROAD_LABELS[$road] : 'this trip')));
                } elseif ($similarity >= 0.4) {
                    $reasons[] = $typeNamed ? "$carType, close to the $type you asked for"
                        : "$carType, also fine for " . ($family ? 'a family trip' : ($road ? self::ROAD_LABELS[$road] : 'this trip'));
                }
            }
            if ($minSeats) {
                $spare = $seats - $minSeats;
                $add('seats', $spare <= 2 ? 1.0 : ($spare <= 4 ? 0.85 : 0.7));
                $reasons[] = $people ? "$seats seats for your $people " . ($people === 1 ? 'traveller' : 'travellers') : "$seats seats for the " . ($family ? 'family' : 'group');
            }
            if ($budget) {
                $share = $price / $budget;
                $add('budget', $share <= 0.8 ? 1.0 : ($share <= 1 ? 1 - 0.5 * ($share - 0.8) : 0.4));
                $priceText = format_price($price) . '/day' . ($tripTotal !== null ? ' on your dates' : '');
                if ($share <= 1) $reasons[] = "$priceText, within your " . format_price($budget) . ' budget';
                else $cautions[] = "$priceText, slightly over your " . format_price($budget) . ' budget';
            }
            if ($road) {
                $suit = self::ROAD_SUITABILITY[$road][$kind] ?? 0.5;
                $add('road', $suit);
                if ($suit >= 0.8) $reasons[] = 'Good for ' . self::ROAD_LABELS[$road];
                elseif ($suit <= 0.3) $cautions[] = ($sports ? 'Sports car: ' : "$carType: ") . 'not ideal for ' . self::ROAD_LABELS[$road];
            }
            if ($wantsComfort || $wantsLuggage) {
                $parts = [];
                if ($wantsComfort) $parts[] = self::COMFORT[$kind] ?? 0.6;
                if ($wantsLuggage) $parts[] = self::LUGGAGE[$kind] ?? 0.6;
                $comfort = array_sum($parts) / count($parts);
                $add('comfort', $comfort);
                if ($comfort >= 0.9) $reasons[] = $wantsLuggage && !$wantsComfort ? 'Room for luggage' : ($family ? 'Comfortable for families' : 'Comfortable ride');
                elseif ($comfort <= 0.3) $cautions[] = ($sports ? 'Sports car: ' : '') . 'not ideal for a ' . ($family ? 'family trip' : ($wantsLuggage ? 'trip with luggage' : 'comfortable trip'));
            }
            if ($fuel) {
                $same = strcasecmp((string)$car->FuelType, $fuel) === 0;
                $add('fuel', $same ? 1.0 : 0.0);
                if ($same) $reasons[] = $car->FuelType . ', as you asked';
            }
            if ($prefs['count'] > 0) {
                $typeTimes = $prefs['types'][$carType] ?? 0;
                $brandTimes = $prefs['brands'][(string)$car->BrandName] ?? 0;
                $add('history', min(1.0, ($typeTimes ? 0.7 : 0) + ($brandTimes ? 0.3 : 0)));
                if ($typeTimes) $reasons[] = "You've rented a $carType before";
                elseif ($brandTimes) $reasons[] = "You've rented a {$car->BrandName} before";
            }
            $year = (int)$car->ModelYear;
            $add('age', $year ? max(0, min(1, ($year - (int)date('Y') + 12) / 12)) : 0.3);
            if ($year >= (int)date('Y') - 4) $reasons[] = "$year model";
            if ($from && $to) $reasons[] = 'Free on your dates';

            $score = $possible > 0 ? $earned / $possible : null;
            $entry = ['car' => $car, 'score' => $score, 'percent' => $score !== null ? (int)round($score * 100) : null,
                'price' => $price, 'tripTotal' => $tripTotal, 'quote' => $quote, 'reasons' => $reasons, 'cautions' => $cautions];
            if ((int)$car->DriverOnly) {
                $withDriver[] = $entry;
            } else {
                $matches[] = $entry;
            }
        }
        $sort = fn($a, $b) => [($b['score'] ?? 0), $a['price']] <=> [($a['score'] ?? 0), $b['price']];
        usort($matches, $sort);
        usort($withDriver, $sort);
        // Weak matches are dropped once a type is known; the rest are shown with their real %.
        $matches = array_values(array_filter($matches, fn($m) => $m['score'] === null || $m['score'] >= 0.4));
        return [
            'matches' => array_slice($matches, 0, $limit),
            'withDriver' => array_slice($withDriver, 0, 3),
            'criteria' => array_filter(['type' => $type, 'typeNamed' => $typeNamed ?: null, 'budget' => $budget, 'people' => $people,
                'minSeats' => $minSeats ?: null, 'fuel' => $fuel, 'road' => $road, 'context' => $context ?: null,
                'from' => $from, 'to' => $to, 'history' => $prefs['count'] ?: null]),
            'excluded' => $excluded,
        ];
    }

    /** Scores for a given list of cars (the car-listing smart search), keyed by car id. */
    public function scoreCars(array $cars, array $trip, ?string $userEmail = null): array
    {
        $all = $this->recommend($trip, $userEmail, 1000);
        $scores = [];
        foreach (array_merge($all['matches'], $all['withDriver']) as $m) {
            $scores[(int)$m['car']->id] = $m;
        }
        return array_intersect_key($scores, array_flip(array_map(fn($c) => (int)$c->id, $cars)));
    }
}
