<?php
// Server-side copy of the trip parser in assets/js/rento-ai.js: reads a plain-language trip
// ("family trip to Pokhara under Rs 8000, 6 people") and finds the car type, daily budget,
// travellers, fuel and the kind of road. Keep the keyword lists in step with rento-ai.js.

// "cheap" and "luxury" without an amount (Rs per day; same values in assets/js/rento-ai.js).
const TRIP_BUDGET_CHEAP = 5500;
const TRIP_BUDGET_PREMIUM = 15000;
const TRIP_TYPE_RULES = [
    ['SUV', 'mountain', 'rough roads', ['suv', 'mountain', 'mountains', 'hill', 'hills', 'off road', 'offroad', 'off-road', 'rough', 'mustang', 'jomsom', 'manang', 'trek', 'trekking', 'adventure', 'snow', 'monsoon', 'village', 'dirt', 'jungle', 'chitwan', 'group', 'luggage']],
    ['Sedan', 'highway', 'comfortable highway drives', ['sedan', 'highway', 'long drive', 'long trip', 'business', 'meeting', 'comfort', 'comfortable', 'airport', 'wedding', 'family', 'pokhara', 'lumbini', 'quiet']],
    ['Hatchback', 'city', 'easy city driving', ['hatchback', 'city', 'commute', 'daily', 'kathmandu', 'lalitpur', 'patan', 'bhaktapur', 'parking', 'small', 'cheap', 'solo', 'couple', 'errand', 'errands', 'shopping', 'fuel efficient', 'mileage']],
];

function trip_has_word(string $text, string $word): bool
{
    $pattern = preg_replace('/[-\s]/', '[-\s]?', preg_quote($word, '/'));
    return (bool)preg_match('/(^|[^a-z])' . $pattern . '([^a-z]|$)/', $text);
}

function trip_amount(string $raw): ?int
{
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*(k|thousand)?$/', str_replace(',', '', trim($raw)), $m)) {
        return null;
    }
    $n = (float)$m[1] * (!empty($m[2]) ? 1000 : 1);
    return ($n >= 100 && $n <= 100000) ? (int)round($n) : null;
}

// Words that describe who travels and what matters, used by the recommendation engine.
const TRIP_CONTEXT_WORDS = [
    'family' => ['family', 'kids', 'children', 'parents', 'grandparents', 'elders'],
    'luggage' => ['luggage', 'bags', 'suitcases', 'airport', 'moving'],
    'business' => ['business', 'meeting', 'client', 'office', 'wedding'],
    'group' => ['group', 'friends', 'team'],
    'comfort' => ['comfort', 'comfortable', 'quiet', 'long drive', 'long trip', 'luxury', 'premium'],
];
// Car types a visitor can name outright (then the type counts as "asked for").
const TRIP_NAMED_TYPES = ['hatchback' => 'Hatchback', 'sedan' => 'Sedan', 'suv' => 'SUV', 'jeep' => 'SUV', 'muv' => 'MUV',
    'crossover' => 'Crossover', 'coupe' => 'Coupe', 'sports car' => 'Coupe', 'pickup' => 'Pickup', 'van' => 'Van'];

// Returns ['type', 'typeWhy', 'typeNamed' (bool: the visitor typed the type), 'tripType' (city|highway|mountain),
// 'budget', 'people', 'fuel', 'context' => [family, luggage, business, group, comfort]] (null/[] when not found).
function trip_understand(?string $input): array
{
    $text = ' ' . mb_strtolower((string)$input) . ' ';
    $r = ['type' => null, 'typeWhy' => null, 'typeNamed' => false, 'tripType' => null, 'budget' => null, 'people' => null, 'fuel' => null, 'context' => []];
    foreach (TRIP_CONTEXT_WORDS as $context => $words) {
        foreach ($words as $word) {
            if (trip_has_word($text, $word)) { $r['context'][] = $context; break; }
        }
    }

    if (preg_match('/(\d{1,2})\s*(people|persons|person|passengers|pax|seats|of us|adults|friends|members)/', $text, $m)) {
        $r['people'] = (int)$m[1];
    } elseif (preg_match('/(family of|group of)\s*(\d{1,2})/', $text, $m)) {
        $r['people'] = (int)$m[2];
    }

    if (preg_match('/(?:rs\.?|npr|रु|budget|under|below|less than|max(?:imum)?|upto|up to|within)\s*(?:rs\.?|npr)?\s*(\d[\d,]*(?:\.\d+)?\s*(?:k|thousand)?)/u', $text, $m)
        || preg_match('/(\d[\d,]*(?:\.\d+)?\s*(?:k|thousand)?)\s*(?:rs|npr|rupees)/', $text, $m)) {
        $r['budget'] = trip_amount($m[1]);
    }
    if (!$r['budget'] && preg_match('/(cheap|cheapest|budget|low cost|affordable|economy)/', $text)) $r['budget'] = TRIP_BUDGET_CHEAP;
    if (!$r['budget'] && preg_match('/(luxury|premium|best car|top end)/', $text)) $r['budget'] = TRIP_BUDGET_PREMIUM;

    if (str_contains($text, 'electric') || trip_has_word($text, 'ev')) $r['fuel'] = 'Electric';
    elseif (str_contains($text, 'cng')) $r['fuel'] = 'CNG';
    elseif (str_contains($text, 'diesel')) $r['fuel'] = 'Diesel';
    elseif (str_contains($text, 'petrol')) $r['fuel'] = 'Petrol';

    $best = null;
    foreach (TRIP_TYPE_RULES as [$type, $tripType, $why, $words]) {
        $hits = count(array_filter($words, fn($w) => trip_has_word($text, $w)));
        if ($hits && (!$best || $hits > $best[0])) $best = [$hits, $type, $tripType, $why];
    }
    if ($best) {
        $r['tripType'] = $best[2];
    }
    foreach (TRIP_NAMED_TYPES as $word => $type) {
        if (trip_has_word($text, $word)) {
            $r['type'] = $type;
            $r['typeNamed'] = true;
            break;
        }
    }
    if (!$r['typeNamed']) {
        if ($r['people'] && $r['people'] > 5) {
            $r['type'] = 'SUV';
            $r['typeWhy'] = $r['people'] . ' travellers';
        } elseif ($best) {
            $r['type'] = $best[1];
            $r['typeWhy'] = $best[3];
        }
    }
    return $r;
}
