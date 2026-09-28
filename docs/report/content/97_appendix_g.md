# APPENDIX G: SOURCE CODE LISTINGS

This appendix reproduces two central source files in full, unchanged from the repository. The rest of the code, including the forecast pipeline, the recommendation engine and the security module, is available in the repository listed in Appendix A.

## G.1 ml/pricing_rules.py

The rule-based pricer: the settings, festival and car context types and the function that prices one car for one day.

```Figure G.1 Listing of ml/pricing_rules.py (119 lines)
"""
Rule-based smart pricing: the price of one car on one day, with the reasons.

price = base price x (1 + sum of the factor percentages), the sum capped between
-max_discount and +max_surcharge, rounded to `round_to` rupees.

Factors (sizes are admin settings, see includes/settings.php):
  weekend      the day is a weekend day (Nepal: Friday, Saturday)
  festival     the day is inside a festival row (Festivals page): high or medium demand;
               the strongest festival that day counts
  travel days  the day is inside a festival's travel window (days before the main days);
               it uses the travel surcharge instead of the festival one (never both)
  season       the day is inside a festival row checked as "guidance" (e.g. trekking season)
  lead time    last-minute (few days ahead) or early booking (many days ahead)
  occupancy    share of the car's next N days that are already booked
  demand       booking requests made for the car in the last N days
               (both only once there are enough real confirmed bookings: settings["_demand_on"])

No database access here, so the rules can be tested on their own (test_pricing_rules.py).
"""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date


@dataclass
class Festival:
    name: str
    start: date
    end: date
    demand: str          # "high" or "medium"
    is_season: bool      # Verification == "guidance"
    travel_from: date | None = None
    travel_to: date | None = None
    travel_pct: float | None = None   # None = settings["pricing_travel_days_pct"]


@dataclass
class CarContext:
    base_price: int
    occupancy: float          # 0..1, share of the next window days that are booked
    recent_requests: int      # bookings made for the car in the demand window
    booked_days: set = field(default_factory=set)


def _pct(value) -> float:
    return round(float(value), 2)


def price_for_day(day: date, today: date, car: CarContext, festivals: list, s: dict) -> dict:
    """Returns {price, multiplier, reasons: [{label, pct}], capped, available}."""
    reasons = []

    weekend_days = {int(d) for d in str(s["pricing_weekend_days"]).split(",") if str(d).strip()}
    if day.isoweekday() in weekend_days and s["pricing_weekend_pct"]:
        reasons.append({"label": "Weekend", "pct": _pct(s["pricing_weekend_pct"])})

    # The strongest festival that day; a travel day uses the travel surcharge instead
    # (seasons are counted separately).
    best = None
    for f in festivals:
        if f.is_season:
            continue
        if f.travel_from and f.travel_to and f.travel_from <= day <= f.travel_to:
            pct = s["pricing_travel_days_pct"] if f.travel_pct is None else f.travel_pct
            label = f"{f.name}: travel days"
        elif f.start <= day <= f.end:
            pct = s["pricing_festival_high_pct"] if f.demand == "high" else s["pricing_festival_medium_pct"]
            label = f.name
        else:
            continue
        if best is None or pct > best[1]:
            best = (label, pct)
    todays = [f for f in festivals if f.start <= day <= f.end]
    if best and best[1]:
        reasons.append({"label": best[0], "pct": _pct(best[1])})
    seasons = [f for f in todays if f.is_season]
    if seasons and s["pricing_season_pct"]:
        reasons.append({"label": seasons[0].name, "pct": _pct(s["pricing_season_pct"])})

    days_ahead = (day - today).days
    if days_ahead <= s["pricing_lastminute_days"] and s["pricing_lastminute_pct"]:
        reasons.append({"label": "Last-minute", "pct": _pct(s["pricing_lastminute_pct"])})
    elif days_ahead >= s["pricing_early_days"] and s["pricing_early_pct"]:
        reasons.append({"label": "Early booking", "pct": _pct(s["pricing_early_pct"])})

    demand_on = s.get("_demand_on", True)
    booked_pct = round(car.occupancy * 100)
    if not demand_on:
        pass
    elif car.occupancy * 100 >= s["pricing_occupancy_high"] and s["pricing_occupancy_high_pct"]:
        reasons.append({"label": f"In demand ({booked_pct}% of the next {s['pricing_occupancy_window']} days booked)",
                        "pct": _pct(s["pricing_occupancy_high_pct"])})
    elif car.occupancy * 100 <= s["pricing_occupancy_low"] and s["pricing_occupancy_low_pct"]:
        reasons.append({"label": "Quiet period for this car", "pct": _pct(s["pricing_occupancy_low_pct"])})

    if demand_on and car.recent_requests >= s["pricing_demand_high_count"] and s["pricing_demand_high_pct"]:
        reasons.append({"label": f"Popular ({car.recent_requests} requests in {s['pricing_demand_days']} days)",
                        "pct": _pct(s["pricing_demand_high_pct"])})

    total = sum(r["pct"] for r in reasons)
    low, high = -float(s["pricing_max_discount"]), float(s["pricing_max_surcharge"])
    capped = total < low or total > high
    total = max(low, min(high, total))

    multiplier = round(1 + total / 100, 4)
    step = max(1, int(s["pricing_round_to"]))
    price = int(round(car.base_price * multiplier / step) * step)
    price = max(step, price)
    return {
        "price": price,
        "multiplier": multiplier,
        "reasons": reasons,
        "capped": capped,
        "total_pct": round(total, 2),
        "available": day not in car.booked_days,
    }
```

## G.2 api/price-quote.php

The price-quote endpoint of the JSON API.

```Figure G.2 Listing of api/price-quote.php (49 lines)
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
```

