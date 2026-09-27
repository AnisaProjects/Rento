"""Tests for pricing_rules.py:  ml/.venv/bin/python -m unittest ml/test_pricing_rules.py"""

import sys
import unittest
from datetime import date
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from pricing_rules import CarContext, Festival, price_for_day  # noqa: E402

SETTINGS = {
    "pricing_max_discount": 15, "pricing_max_surcharge": 25, "pricing_weekend_pct": 8, "pricing_weekend_days": "5,6",
    "pricing_festival_high_pct": 12, "pricing_festival_medium_pct": 5, "pricing_season_pct": 4,
    "pricing_lastminute_days": 2, "pricing_lastminute_pct": 5, "pricing_early_days": 45, "pricing_early_pct": -4,
    "pricing_occupancy_window": 30, "pricing_occupancy_high": 60, "pricing_occupancy_high_pct": 6,
    "pricing_occupancy_low": 10, "pricing_occupancy_low_pct": -3, "pricing_demand_days": 30,
    "pricing_demand_high_count": 3, "pricing_demand_high_pct": 5, "pricing_round_to": 50, "pricing_forecast_days": 60,
    "pricing_travel_days_pct": 15, "_demand_on": True,
}
TODAY = date(2026, 9, 28)  # a Monday
DASHAIN = Festival("Dashain 2026", date(2026, 10, 11), date(2026, 10, 25), "high", False)
DASHAIN_TRAVEL = Festival("Dashain 2026", date(2026, 10, 11), date(2026, 10, 25), "high", False,
                          date(2026, 10, 9), date(2026, 10, 17), None)
TEEJ = Festival("Some holiday", date(2026, 10, 14), date(2026, 10, 14), "medium", False)
SEASON = Festival("Autumn trekking season", date(2026, 10, 1), date(2026, 11, 30), "high", True)


def car(occupancy=0.3, requests=0, base=1000):
    return CarContext(base_price=base, occupancy=occupancy, recent_requests=requests)


class PricingRulesTest(unittest.TestCase):
    def labels(self, result):
        return {r["label"]: r["pct"] for r in result["reasons"]}

    def test_plain_weekday_is_base_price(self):
        r = price_for_day(date(2026, 9, 30), TODAY, car(), [], SETTINGS)  # Wednesday, 2 days ahead -> last minute
        self.assertEqual(self.labels(r), {"Last-minute": 5})
        r = price_for_day(date(2026, 10, 7), TODAY, car(), [], SETTINGS)  # Wednesday, 9 days ahead
        self.assertEqual(r["reasons"], [])
        self.assertEqual(r["price"], 1000)

    def test_weekend(self):
        r = price_for_day(date(2026, 10, 9), TODAY, car(), [], SETTINGS)  # Friday
        self.assertEqual(self.labels(r), {"Weekend": 8})
        self.assertEqual(r["price"], 1100)  # 1080 rounded to 50

    def test_strongest_festival_and_season_both_count(self):
        r = price_for_day(date(2026, 10, 14), TODAY, car(), [DASHAIN, TEEJ, SEASON], SETTINGS)  # Wednesday
        self.assertEqual(self.labels(r), {"Dashain 2026": 12, "Autumn trekking season": 4})
        self.assertEqual(r["price"], 1150)

    def test_cap(self):
        busy = car(occupancy=0.8, requests=5)
        r = price_for_day(date(2026, 10, 16), TODAY, busy, [DASHAIN, SEASON], SETTINGS)  # Friday in Dashain
        self.assertTrue(r["capped"])  # 8 + 12 + 4 + 6 + 5 = 35 -> 25
        self.assertEqual(r["total_pct"], 25)
        self.assertEqual(r["price"], 1250)

    def test_discount_floor_and_quiet_car(self):
        r = price_for_day(date(2026, 11, 18), TODAY, car(occupancy=0.0), [], SETTINGS)  # 51 days ahead
        self.assertEqual(self.labels(r), {"Early booking": -4, "Quiet period for this car": -3})
        self.assertEqual(r["price"], 950)  # 930 -> 950
        strict = dict(SETTINGS, pricing_max_discount=5)
        self.assertEqual(price_for_day(date(2026, 11, 18), TODAY, car(occupancy=0.0), [], strict)["total_pct"], -5)

    def test_travel_days_replace_the_festival_surcharge(self):
        wed_before = price_for_day(date(2026, 10, 14), TODAY, car(), [DASHAIN_TRAVEL], SETTINGS)  # travel window
        self.assertEqual(self.labels(wed_before), {"Dashain 2026: travel days": 15})
        after = price_for_day(date(2026, 10, 21), TODAY, car(), [DASHAIN_TRAVEL], SETTINGS)       # main days only
        self.assertEqual(self.labels(after), {"Dashain 2026": 12})
        before_festival = price_for_day(date(2026, 10, 9), TODAY, car(), [DASHAIN_TRAVEL], SETTINGS)  # Friday, travel
        self.assertEqual(self.labels(before_festival), {"Weekend": 8, "Dashain 2026: travel days": 15})
        own = Festival("Tihar", date(2026, 11, 7), date(2026, 11, 11), "high", False, date(2026, 11, 5), date(2026, 11, 7), 20)
        self.assertEqual(self.labels(price_for_day(date(2026, 11, 5), TODAY, car(), [own], SETTINGS)), {"Tihar: travel days": 20})

    def test_demand_factors_off_until_enough_bookings(self):
        off = dict(SETTINGS, _demand_on=False)
        r = price_for_day(date(2026, 10, 7), TODAY, car(occupancy=0.0, requests=9), [], off)
        self.assertEqual(r["reasons"], [])
        r = price_for_day(date(2026, 10, 7), TODAY, car(occupancy=0.0, requests=9), [], SETTINGS)
        self.assertEqual(self.labels(r), {"Quiet period for this car": -3, "Popular (9 requests in 30 days)": 5})

    def test_booked_day_is_not_available(self):
        context = car()
        context.booked_days = {date(2026, 10, 7)}
        self.assertFalse(price_for_day(date(2026, 10, 7), TODAY, context, [], SETTINGS)["available"])


if __name__ == "__main__":
    unittest.main()
