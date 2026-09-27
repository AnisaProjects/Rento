<?php
/**
 * Car recommendations (Find My Car, the car-listing smart search, api/recommend.php).
 *
 * Read-only: nothing is written to the database. Each active, self-drive car gets a real
 * weighted score from the criteria the visitor gave, and a reason for every one:
 *
 *   type 30 · seats 20 · budget 20 · trip type 10 · fuel 10 · past rentals 10 (logged-in, with history)
 *
 * Only criteria that were actually given count, so the match % is the share of the weights
 * the car earned: 100% = fits everything that was asked. Cars that can't work are left out
 * (too few seats, booked on the dates, more than 10% over budget). Driver-only cars are
 * returned separately as "With driver: request" suggestions.
 *
 *   $engine = new CarRecommendationEngine($dbh);
 *   $result = $engine->recommend(['type' => 'SUV', 'budget' => 3000, 'people' => 6, 'tripType' => 'mountain',
 *                                 'fuel' => null, 'from' => '2026-10-14', 'to' => '2026-10-16'], $userEmail);
 *   $result['matches']      [['car' => obj, 'score' => 0.87, 'percent' => 87, 'price' => ..., 'reasons' => [...]], ...]
 *   $result['withDriver']   driver-only cars that fit the type and seats
 */
class CarRecommendationEngine
{
    const WEIGHTS = ['type' => 30, 'seats' => 20, 'budget' => 20, 'tripType' => 10, 'fuel' => 10, 'history' => 10];
    const OVER_BUDGET_TOLERANCE = 0.10;
    // How well each car type suits another requested type (1 = exact).
    const TYPE_SIMILARITY = [
        'SUV' => ['Crossover' => 0.7, 'MUV' => 0.6, 'Pickup' => 0.5],
        'Sedan' => ['Hatchback' => 0.4, 'Crossover' => 0.4, 'Coupe' => 0.3],
        'Hatchback' => ['Crossover' => 0.5, 'Sedan' => 0.4],
        'MUV' => ['SUV' => 0.7, 'Van' => 0.5],
        'Crossover' => ['SUV' => 0.7, 'Hatchback' => 0.5],
    ];
    // How well each car type suits a kind of trip.
    const TRIP_SUITABILITY = [
        'mountain' => ['SUV' => 1, 'Pickup' => 1, 'Crossover' => 0.8, 'MUV' => 0.7, 'Van' => 0.5, 'Sedan' => 0.3, 'Hatchback' => 0.2],
        'highway' => ['Sedan' => 1, 'SUV' => 1, 'MUV' => 1, 'Crossover' => 0.8, 'Hatchback' => 0.6, 'Van' => 0.7],
        'city' => ['Hatchback' => 1, 'Crossover' => 0.8, 'Sedan' => 0.8, 'SUV' => 0.5, 'MUV' => 0.4, 'Van' => 0.3],
    ];
    const TRIP_LABELS = ['mountain' => 'mountain and rough roads', 'highway' => 'long highway drives', 'city' => 'city driving'];

    private PDO $dbh;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    // Active cars (self-drive and driver-only).
    private function cars(): array
    {
        return $this->dbh->query("SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Vimage1,
            v.CarType, v.DriverOnly, br.BrandName FROM tblvehicles v LEFT JOIN tblbrands br ON br.id = v.VehiclesBrand
            WHERE v.IsActive = 1 ORDER BY v.id")->fetchAll(PDO::FETCH_OBJ);
    }

    /** Types and brands of the user's confirmed past rentals: ['types' => [type => n], 'brands' => [brand => n], 'count' => n]. */
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

    /**
     * $trip: type ('Any' or a car type), budget (Rs per day), people, fuel, tripType, from, to.
     * Returns ['matches' => [...], 'withDriver' => [...], 'criteria' => [...], 'excluded' => n].
     */
    public function recommend(array $trip, ?string $userEmail = null, int $limit = 12): array
    {
        $type = !empty($trip['type']) && $trip['type'] !== 'Any' ? (string)$trip['type'] : null;
        $budget = !empty($trip['budget']) ? (int)$trip['budget'] : null;
        $people = !empty($trip['people']) ? (int)$trip['people'] : null;
        $fuel = !empty($trip['fuel']) ? (string)$trip['fuel'] : null;
        $tripType = isset(self::TRIP_SUITABILITY[$trip['tripType'] ?? '']) ? $trip['tripType'] : null;
        $from = $trip['from'] ?? null;
        $to = $trip['to'] ?? null;
        $prefs = $this->getUserPreferences($userEmail);

        $matches = [];
        $withDriver = [];
        $excluded = 0;
        foreach ($this->cars() as $car) {
            $carType = (string)$car->CarType;
            $seats = (int)$car->SeatingCapacity;
            [$price, $tripTotal, $available, $quote] = $this->dailyPrice($car, $from, $to);
            // Hard limits: it has to fit, be free, and be close to the budget.
            if ($people && $seats < $people) { $excluded++; continue; }
            if ($from && $to && !$available) { $excluded++; continue; }
            if ($budget && $price > $budget * (1 + self::OVER_BUDGET_TOLERANCE)) { $excluded++; continue; }

            $earned = 0.0;
            $possible = 0.0;
            $reasons = [];
            $add = function (string $criterion, float $score, ?string $reason) use (&$earned, &$possible, &$reasons) {
                $weight = self::WEIGHTS[$criterion];
                $possible += $weight;
                $earned += $weight * max(0, min(1, $score));
                if ($reason !== null) $reasons[] = $reason;
            };

            if ($type) {
                $similarity = $carType === $type ? 1.0 : (self::TYPE_SIMILARITY[$type][$carType] ?? 0.0);
                $add('type', $similarity, $similarity >= 1 ? "The $type you asked for"
                    : ($similarity > 0 ? "$carType, close to the $type you asked for" : null));
            }
            if ($people) {
                $spare = $seats - $people;
                $add('seats', $spare <= 2 ? 1.0 : 0.8, "$seats seats for your $people " . ($people === 1 ? 'traveller' : 'travellers'));
            }
            if ($budget) {
                $priceText = format_price($price) . '/day' . ($tripTotal !== null ? ' on your dates' : '');
                $add('budget', $price <= $budget ? 1.0 : 0.4, $price <= $budget
                    ? "$priceText, within your " . format_price($budget) . ' budget'
                    : "$priceText, slightly over your " . format_price($budget) . ' budget');
            }
            if ($tripType) {
                $suit = self::TRIP_SUITABILITY[$tripType][$carType] ?? 0.5;
                $add('tripType', $suit, $suit >= 0.8 ? 'Good for ' . self::TRIP_LABELS[$tripType] : null);
            }
            if ($fuel) {
                $add('fuel', strcasecmp((string)$car->FuelType, $fuel) === 0 ? 1.0 : 0.0,
                    strcasecmp((string)$car->FuelType, $fuel) === 0 ? $car->FuelType . ', as you asked' : null);
            }
            if ($prefs['count'] > 0) {
                $typeTimes = $prefs['types'][$carType] ?? 0;
                $brandTimes = $prefs['brands'][(string)$car->BrandName] ?? 0;
                $history = min(1.0, ($typeTimes ? 0.7 : 0) + ($brandTimes ? 0.3 : 0));
                $add('history', $history, $typeTimes ? "You've rented a $carType before" : ($brandTimes ? "You've rented a {$car->BrandName} before" : null));
            }
            if ($from && $to) {
                $reasons[] = 'Free on your dates';
            }
            $score = $possible > 0 ? $earned / $possible : null;
            $entry = [
                'car' => $car,
                'score' => $score,
                'percent' => $score !== null ? (int)round($score * 100) : null,
                'price' => $price,
                'tripTotal' => $tripTotal,
                'quote' => $quote,
                'reasons' => $reasons,
            ];
            if ((int)$car->DriverOnly) {
                $withDriver[] = $entry;
            } elseif ($type === null || $score === null || $score > 0) {
                $matches[] = $entry;
            }
        }
        $sort = function ($a, $b) {
            // Best score first; the cheaper car first when scores are equal.
            return [($b['score'] ?? 0), $a['price']] <=> [($a['score'] ?? 0), $b['price']];
        };
        usort($matches, $sort);
        usort($withDriver, $sort);
        // With a type asked for, cars that share nothing with it are not "matches".
        if ($type) {
            $matches = array_values(array_filter($matches, fn($m) => $m['score'] === null || $m['score'] >= 0.35));
        }
        return [
            'matches' => array_slice($matches, 0, $limit),
            'withDriver' => array_slice($withDriver, 0, 3),
            'criteria' => array_filter(['type' => $type, 'budget' => $budget, 'people' => $people, 'fuel' => $fuel,
                'tripType' => $tripType, 'from' => $from, 'to' => $to, 'history' => $prefs['count'] ?: null]),
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
