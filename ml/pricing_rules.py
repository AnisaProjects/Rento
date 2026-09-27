"""
Rule-based smart pricing: the price of one car on one day, with the reasons.

price = base price x (1 + sum of the factor percentages), the sum capped between
-max_discount and +max_surcharge, rounded to `round_to` rupees.

Factors (sizes are admin settings, see includes/settings.php):
  weekend      the day is a weekend day (Nepal: Friday, Saturday)
  festival     the day is inside a festival row (Festivals page): high or medium demand;
               the strongest festival that day counts
  season       the day is inside a festival row checked as "guidance" (e.g. trekking season)
  lead time    last-minute (few days ahead) or early booking (many days ahead)
  occupancy    share of the car's next N days that are already booked
  demand       booking requests made for the car in the last N days

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

    # The strongest festival that day (seasons are counted separately).
    todays = [f for f in festivals if f.start <= day <= f.end]
    best = None
    for f in todays:
        if f.is_season:
            continue
        pct = s["pricing_festival_high_pct"] if f.demand == "high" else s["pricing_festival_medium_pct"]
        if best is None or pct > best[1]:
            best = (f.name, pct)
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

    booked_pct = round(car.occupancy * 100)
    if car.occupancy * 100 >= s["pricing_occupancy_high"] and s["pricing_occupancy_high_pct"]:
        reasons.append({"label": f"In demand ({booked_pct}% of the next {s['pricing_occupancy_window']} days booked)",
                        "pct": _pct(s["pricing_occupancy_high_pct"])})
    elif car.occupancy * 100 <= s["pricing_occupancy_low"] and s["pricing_occupancy_low_pct"]:
        reasons.append({"label": "Quiet period for this car", "pct": _pct(s["pricing_occupancy_low_pct"])})

    if car.recent_requests >= s["pricing_demand_high_count"] and s["pricing_demand_high_pct"]:
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
