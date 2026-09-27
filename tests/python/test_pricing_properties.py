"""pytest: properties of the rule-based pricer (ml/pricing_rules.py) that must hold for every day and car."""
import itertools
import json
import random
from datetime import date, timedelta

import pytest

from pricing_rules import CarContext, Festival, price_for_day

S = {
    "pricing_max_discount": 15, "pricing_max_surcharge": 25, "pricing_weekend_pct": 8, "pricing_weekend_days": "5,6",
    "pricing_festival_high_pct": 12, "pricing_festival_medium_pct": 5, "pricing_season_pct": 4,
    "pricing_lastminute_days": 2, "pricing_lastminute_pct": 5, "pricing_early_days": 45, "pricing_early_pct": -4,
    "pricing_occupancy_window": 30, "pricing_occupancy_high": 60, "pricing_occupancy_high_pct": 6,
    "pricing_occupancy_low": 10, "pricing_occupancy_low_pct": -3, "pricing_demand_days": 30,
    "pricing_demand_high_count": 3, "pricing_demand_high_pct": 5, "pricing_round_to": 50, "pricing_forecast_days": 60,
    "pricing_travel_days_pct": 15, "pricing_demand_min_bookings": 50, "_demand_on": True,
}
TODAY = date(2026, 9, 28)  # Monday
DASHAIN = Festival("Dashain", date(2026, 10, 11), date(2026, 10, 25), "high", False, date(2026, 10, 9), date(2026, 10, 17), None)
TIHAR = Festival("Tihar", date(2026, 11, 8), date(2026, 11, 11), "high", False, date(2026, 11, 5), date(2026, 11, 7), None)
MEDIUM = Festival("One-day holiday", date(2026, 10, 30), date(2026, 10, 30), "medium", False)
SEASON = Festival("Trekking season", date(2026, 10, 1), date(2026, 11, 30), "high", True)
ALL = [DASHAIN, TIHAR, MEDIUM, SEASON]


def labels(r):
    return [x["label"] for x in r["reasons"]]


@pytest.mark.parametrize("base", [400, 5000, 7000, 10000, 12345])
@pytest.mark.parametrize("occupancy,requests", [(0.0, 0), (0.05, 0), (0.3, 1), (0.9, 5)])
def test_caps_and_rounding_hold_for_every_day(base, occupancy, requests):
    car = CarContext(base, occupancy, requests)
    for i in range(60):
        day = TODAY + timedelta(days=i)
        r = price_for_day(day, TODAY, car, ALL, S)
        assert 0.85 - 1e-9 <= r["multiplier"] <= 1.25 + 1e-9, (day, r)
        assert r["price"] % 50 == 0
        assert abs(r["price"] - base * r["multiplier"]) <= 25  # rounded to the nearest Rs 50
        raw = sum(x["pct"] for x in r["reasons"])
        assert r["capped"] == (raw > 25 or raw < -15)


def test_random_stacking_never_breaks_the_caps():
    rng = random.Random(42)
    for _ in range(500):
        s = dict(S)
        for k in ["pricing_weekend_pct", "pricing_festival_high_pct", "pricing_travel_days_pct", "pricing_season_pct",
                  "pricing_lastminute_pct", "pricing_occupancy_high_pct", "pricing_demand_high_pct"]:
            s[k] = rng.uniform(0, 30)
        car = CarContext(rng.choice([500, 5000, 10000]), rng.random(), rng.randint(0, 6))
        day = TODAY + timedelta(days=rng.randint(0, 59))
        r = price_for_day(day, TODAY, car, ALL, s)
        assert 0.85 - 1e-9 <= r["multiplier"] <= 1.25 + 1e-9


def test_travel_days_replace_the_festival_surcharge_never_both():
    r = price_for_day(date(2026, 10, 12), TODAY, CarContext(10000, 0.3, 0), [DASHAIN], S)  # travel window and festival overlap
    assert "Dashain: travel days" in labels(r)
    assert "Dashain" not in labels(r)


def test_strongest_festival_counts_once():
    r = price_for_day(date(2026, 10, 30), TODAY, CarContext(10000, 0.3, 0), [MEDIUM, Festival("Big", date(2026, 10, 30), date(2026, 10, 30), "high", False)], S)
    fest = [x for x in r["reasons"] if x["label"] in ("One-day holiday", "Big")]
    assert fest == [{"label": "Big", "pct": 12.0}]


def test_season_is_separate_from_festivals():
    r = price_for_day(date(2026, 10, 20), TODAY, CarContext(10000, 0.3, 0), ALL, S)
    assert "Trekking season" in labels(r) and "Dashain" in labels(r)


@pytest.mark.parametrize("days_ahead,expected", [(0, "Last-minute"), (2, "Last-minute"), (3, None), (44, None), (45, "Early booking")])
def test_lead_time_boundaries(days_ahead, expected):
    day = TODAY + timedelta(days=days_ahead)
    got = [l for l in labels(price_for_day(day, TODAY, CarContext(10000, 0.3, 0), [], S)) if l in ("Last-minute", "Early booking")]
    assert got == ([expected] if expected else [])


def test_weekend_days_are_friday_and_saturday():
    fri, sat, sun = date(2026, 10, 2), date(2026, 10, 3), date(2026, 10, 4)
    car = CarContext(10000, 0.3, 0)
    assert "Weekend" in labels(price_for_day(fri, TODAY, car, [], S))
    assert "Weekend" in labels(price_for_day(sat, TODAY, car, [], S))
    assert "Weekend" not in labels(price_for_day(sun, TODAY, car, [], S))


def test_occupancy_and_demand_only_when_switched_on():
    busy = CarContext(10000, 0.9, 5)
    day = date(2026, 9, 30)
    on = labels(price_for_day(day, TODAY, busy, [], S))
    off = labels(price_for_day(day, TODAY, busy, [], dict(S, _demand_on=False)))
    assert any(l.startswith("In demand") for l in on) and any(l.startswith("Popular") for l in on)
    assert not any(l.startswith(("In demand", "Popular", "Quiet period")) for l in off)
    quiet = labels(price_for_day(day, TODAY, CarContext(10000, 0.0, 0), [], S))
    assert "Quiet period for this car" in quiet


def test_booked_day_is_unavailable():
    day = date(2026, 10, 1)
    r = price_for_day(day, TODAY, CarContext(10000, 0.3, 0, {day}), [], S)
    assert r["available"] is False


def test_every_factor_has_a_readable_reason():
    r = price_for_day(date(2026, 10, 9), TODAY, CarContext(10000, 0.9, 5), ALL, S)  # Friday, travel day, season
    assert r["reasons"] and all(isinstance(x["label"], str) and x["label"] and isinstance(x["pct"], float) for x in r["reasons"])
    json.dumps(r)  # stored as JSON in tbl_price_forecast
