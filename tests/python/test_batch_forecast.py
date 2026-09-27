"""pytest: the forecast pipeline (ml/batch_forecast.compute) with a fake database cursor."""
import json
from datetime import date, timedelta

import batch_forecast

from test_pricing_properties import S, TODAY


class FakeCursor:
    """Answers compute()'s queries in order, like a PyMySQL DictCursor."""

    def __init__(self, cars, festivals, confirmed, bookings, requests, overrides):
        self.answers = [cars, festivals, [{"n": confirmed}], bookings, requests, overrides]
        self.queries = []
        self._last = None

    def execute(self, sql, params=None):
        self.queries.append(sql)
        self._last = self.answers[len(self.queries) - 1]

    def fetchall(self):
        return self._last

    def fetchone(self):
        return self._last[0]


def run(confirmed=2, bookings=(), overrides=()):
    cars = [{"id": 7, "PricePerDay": 10000}, {"id": 6, "PricePerDay": 5000}]
    fest = [{"FestivalName": "Dashain", "StartDate": date(2026, 10, 11), "EndDate": date(2026, 10, 25), "DemandLevel": "high",
             "Verification": "official", "TravelFrom": date(2026, 10, 9), "TravelTo": date(2026, 10, 17), "TravelPct": None}]
    cur = FakeCursor(cars, fest, confirmed, list(bookings), [{"VehicleId": 7, "n": 4}], list(overrides))
    rows, n = batch_forecast.compute(cur, dict(S), TODAY)
    return rows, n, cur


def test_one_row_per_car_and_day():
    rows, n, _ = run()
    assert n == 2 and len(rows) == 2 * 60
    assert {(r[0], r[1]) for r in rows} == {(c, TODAY + timedelta(days=i)) for c in (7, 6) for i in range(60)}


def test_rows_are_capped_rounded_and_explained():
    rows, _, _ = run()
    for vid, day, base, mult, price, meta, available in rows:
        assert 0.85 <= mult <= 1.25 and price % 50 == 0
        assert isinstance(json.loads(meta)["reasons"], list)


def test_demand_factors_stay_off_below_the_threshold():
    rows, _, _ = run(confirmed=2)
    dyn = ("In demand", "Popular", "Quiet period")
    assert not any(x["label"].startswith(dyn) for r in rows for x in json.loads(r[5])["reasons"])
    rows, _, _ = run(confirmed=50)
    assert any(x["label"].startswith("Popular") for r in rows if r[0] == 7 for x in json.loads(r[5])["reasons"])


def test_booked_days_become_unavailable():
    b = [{"VehicleId": 7, "FromDate": date(2026, 10, 1), "ToDate": date(2026, 10, 3)}]
    rows, _, _ = run(bookings=b)
    unavailable = {r[1] for r in rows if r[0] == 7 and r[6] == 0}
    assert unavailable == {date(2026, 10, 1), date(2026, 10, 2), date(2026, 10, 3)}


def test_manual_override_wins_and_keeps_the_rule_reasons():
    o = [{"VehicleId": 6, "FromDate": date(2026, 10, 12), "ToDate": date(2026, 10, 12), "Price": 4000, "Note": "Promo"}]
    rows, _, _ = run(overrides=o)
    row = next(r for r in rows if r[0] == 6 and r[1] == date(2026, 10, 12))
    meta = json.loads(row[5])
    assert row[4] == 4000 and meta["override"] is True and meta["reasons"][0]["label"] == "Manual price: Promo" and meta["rules"]
