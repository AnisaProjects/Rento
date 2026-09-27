<?php
// App settings edited in admin -> Pricing (tblsettings, migration 011). Only changed values
// are stored; SETTINGS holds the defaults. ml/batch_forecast.py reads the same table.
//   setting('pricing_max_discount')  -> float|int|string

// key => [label, type (int|float|bool|days), default, hint, min, max]
const SETTINGS = [
    'pricing_max_discount' => ['Maximum discount (%)', 'float', 15, 'A price never goes more than this below the base price.', 0, 50],
    'pricing_max_surcharge' => ['Maximum surcharge (%)', 'float', 25, 'A price never goes more than this above the base price.', 0, 100],
    'pricing_weekend_pct' => ['Weekend pickup (%)', 'float', 8, 'Added on the weekend days below.', -50, 100],
    'pricing_weekend_days' => ['Weekend days', 'days', '5,6', 'Nepal: Friday and Saturday (the day before and the day off).', null, null],
    'pricing_festival_high_pct' => ['Festival, high demand (%)', 'float', 12, 'Dashain, Tihar, Chhath, New Year… (Festivals page).', -50, 100],
    'pricing_festival_medium_pct' => ['Festival, medium demand (%)', 'float', 5, 'Single-day holidays.', -50, 100],
    'pricing_travel_days_pct' => ['Festival travel days (%)', 'float', 15, 'Default for the travel-days window before a festival (Festivals page); each festival can set its own.', -50, 100],
    'pricing_season_pct' => ['Travel season (%)', 'float', 4, 'Festival rows checked as "General guidance (season)", e.g. trekking seasons.', -50, 100],
    'pricing_lastminute_days' => ['Last minute: up to N days ahead', 'int', 2, '', 0, 30],
    'pricing_lastminute_pct' => ['Last minute (%)', 'float', 5, '', -50, 100],
    'pricing_early_days' => ['Early booking: at least N days ahead', 'int', 45, '', 1, 365],
    'pricing_early_pct' => ['Early booking (%)', 'float', -4, 'Negative = discount.', -50, 100],
    'pricing_occupancy_window' => ['Occupancy window (days)', 'int', 30, 'How far ahead a car\'s bookings are counted.', 7, 120],
    'pricing_occupancy_high' => ['High occupancy from (% of days booked)', 'float', 60, '', 1, 100],
    'pricing_occupancy_high_pct' => ['High occupancy (%)', 'float', 6, '', -50, 100],
    'pricing_occupancy_low' => ['Low occupancy up to (% of days booked)', 'float', 10, '', 0, 99],
    'pricing_occupancy_low_pct' => ['Low occupancy (%)', 'float', -3, 'Negative = discount.', -50, 100],
    'pricing_demand_days' => ['Recent demand window (days)', 'int', 30, 'Booking requests made for the car in this many past days.', 7, 365],
    'pricing_demand_high_count' => ['High demand from N requests', 'int', 3, '', 1, 100],
    'pricing_demand_high_pct' => ['High demand (%)', 'float', 5, '', -50, 100],
    'pricing_demand_min_bookings' => ['Confirmed bookings needed for the occupancy and demand factors', 'int', 50, 'Below this, those two factors stay off so a new site doesn\'t discount every day as "quiet".', 0, 100000],
    'pricing_round_to' => ['Round prices to (Rs)', 'int', 50, '', 1, 1000],
    'pricing_forecast_days' => ['Forecast days ahead', 'int', 60, 'Prices are precomputed for this many days.', 7, 365],
    'pricing_ml_enabled' => ['Use the gradient-boosting model', 'bool', 0, 'Stays off until there are enough real bookings.', null, null],
    'pricing_ml_threshold' => ['Bookings needed for the model', 'int', 200, '', 10, 100000],
];

function settings_saved(?PDO $dbh = null, bool $reload = false): array
{
    static $saved = null;
    if ($saved === null || $reload) {
        $saved = [];
        $dbh = $dbh ?? ($GLOBALS['dbh'] ?? null);
        if ($dbh instanceof PDO) {
            try {
                foreach ($dbh->query("SELECT SettingKey, SettingValue FROM tblsettings") as $row) {
                    $saved[$row['SettingKey']] = (string)$row['SettingValue'];
                }
            } catch (PDOException $e) {
                $saved = []; // migration 011 not applied yet: defaults
            }
        }
    }
    return $saved;
}

// Current value of a setting, typed.
function setting(string $key)
{
    $def = SETTINGS[$key] ?? null;
    if ($def === null) {
        return null;
    }
    $value = settings_saved()[$key] ?? $def[2];
    return match ($def[1]) {
        'int', 'bool' => (int)$value,
        'float' => (float)$value,
        default => (string)$value,
    };
}
