"""
Precompute smart prices: every active car x the next N days -> tbl_price_forecast.

Started by PHP (includes/pricing.php: admin "Recompute prices", automatic after changes)
or by tools/run-forecast.php (nightly launchd job). PHP passes:
  RENTO_DB_*                database settings (see rento_db.py), never CLI arguments
  RENTO_PRICING_SETTINGS    JSON of the effective pricing settings (admin -> Smart Pricing)

Queued rows in tbl_forecast_runs are picked up together; a MySQL named lock makes
overlapping runs wait for each other. Exit code 0 on success.
"""

from __future__ import annotations

import json
import os
import sys
import traceback
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import rento_db  # noqa: E402
from pricing_rules import CarContext, Festival, price_for_day  # noqa: E402

LOCK_NAME = "rento_price_forecast"
LOCK_WAIT_SECONDS = 300


def load_settings() -> dict:
    raw = os.environ.get("RENTO_PRICING_SETTINGS")
    if not raw:
        raise RuntimeError("RENTO_PRICING_SETTINGS is not set (start this through PHP).")
    return json.loads(raw)


def claim_runs(cur) -> list:
    cur.execute("SELECT id FROM tbl_forecast_runs WHERE Status = 'queued' ORDER BY id")
    ids = [row["id"] for row in cur.fetchall()]
    if ids:
        marks = ",".join(["%s"] * len(ids))
        cur.execute(f"UPDATE tbl_forecast_runs SET Status = 'running', StartedAt = NOW() WHERE id IN ({marks})", ids)
    return ids


def finish_runs(cur, ids: list, status: str, cars: int, rows: int, message: str) -> None:
    if not ids:
        return
    marks = ",".join(["%s"] * len(ids))
    cur.execute(
        f"UPDATE tbl_forecast_runs SET Status = %s, FinishedAt = NOW(), Cars = %s, RowsWritten = %s, Message = %s WHERE id IN ({marks})",
        [status, cars, rows, message[:500], *ids],
    )


def compute(cur, settings: dict, today: date) -> tuple[list, int]:
    horizon = int(settings["pricing_forecast_days"])
    last_day = today + timedelta(days=horizon - 1)

    cur.execute("SELECT id, PricePerDay FROM tblvehicles WHERE IsActive = 1 AND PricePerDay > 0")
    cars = cur.fetchall()

    cur.execute(
        "SELECT FestivalName, StartDate, EndDate, DemandLevel, Verification, TravelFrom, TravelTo, TravelPct FROM tblfestivals"
        " WHERE (EndDate >= %s AND StartDate <= %s) OR (TravelTo >= %s AND TravelFrom <= %s)",
        [today, last_day, today, last_day],
    )
    festivals = [Festival(r["FestivalName"], r["StartDate"], r["EndDate"], r["DemandLevel"], r["Verification"] == "guidance",
                          r["TravelFrom"], r["TravelTo"], None if r["TravelPct"] is None else float(r["TravelPct"]))
                 for r in cur.fetchall()]

    # Occupancy and demand only count once there are enough real confirmed bookings.
    cur.execute("SELECT COUNT(*) n FROM tblbooking WHERE Status = 1")
    confirmed = int(cur.fetchone()["n"])
    settings["_demand_on"] = confirmed >= int(settings.get("pricing_demand_min_bookings", 0))
    settings["_confirmed"] = confirmed

    # Bookings that block a car (pending or confirmed), for availability and occupancy.
    window_end = today + timedelta(days=int(settings["pricing_occupancy_window"]) - 1)
    cur.execute(
        "SELECT VehicleId, FromDate, ToDate FROM tblbooking WHERE (Status IS NULL OR Status <> 2) AND ToDate >= %s AND FromDate <= %s",
        [today, max(last_day, window_end)],
    )
    booked = {}
    for r in cur.fetchall():
        try:
            start = date.fromisoformat(str(r["FromDate"])[:10])
            end = date.fromisoformat(str(r["ToDate"])[:10])
        except ValueError:
            continue
        d = max(start, today)
        while d <= end:
            booked.setdefault(r["VehicleId"], set()).add(d)
            d += timedelta(days=1)

    cur.execute(
        "SELECT VehicleId, COUNT(*) n FROM tblbooking WHERE (Status IS NULL OR Status <> 2) AND PostingDate >= %s GROUP BY VehicleId",
        [today - timedelta(days=int(settings["pricing_demand_days"]))],
    )
    requests = {r["VehicleId"]: int(r["n"]) for r in cur.fetchall()}

    # Manual overrides (admin -> AI Insights): the newest one wins on a day.
    cur.execute("SELECT VehicleId, FromDate, ToDate, Price, Note FROM tbl_price_overrides WHERE ToDate >= %s AND FromDate <= %s ORDER BY id",
                [today, last_day])
    overrides = {}
    for r in cur.fetchall():
        d = max(r["FromDate"], today)
        while d <= min(r["ToDate"], last_day):
            overrides[(r["VehicleId"], d)] = r
            d += timedelta(days=1)

    window = int(settings["pricing_occupancy_window"])
    rows = []
    for car in cars:
        days_booked = booked.get(car["id"], set())
        occupancy_days = sum(1 for i in range(window) if today + timedelta(days=i) in days_booked)
        context = CarContext(
            base_price=int(car["PricePerDay"]),
            occupancy=occupancy_days / window,
            recent_requests=requests.get(car["id"], 0),
            booked_days=days_booked,
        )
        for i in range(horizon):
            day = today + timedelta(days=i)
            result = price_for_day(day, today, context, festivals, settings)
            meta = {"reasons": result["reasons"], "capped": result["capped"]}
            override = overrides.get((car["id"], day))
            if override:
                price = int(override["Price"])
                change = round((price / context.base_price - 1) * 100, 2) if context.base_price else 0
                result["price"], result["multiplier"] = price, round(price / context.base_price, 4) if context.base_price else 1
                meta = {"reasons": [{"label": "Manual price" + (f": {override['Note']}" if override["Note"] else ""), "pct": change}],
                        "capped": False, "override": True, "rules": result["reasons"]}
            rows.append((car["id"], day, context.base_price, result["multiplier"], result["price"],
                         json.dumps(meta, ensure_ascii=False), 1 if result["available"] else 0))
    return rows, len(cars)


def main() -> int:
    settings = load_settings()
    conn = rento_db.connect()
    run_ids = []
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT GET_LOCK(%s, %s) AS got", [LOCK_NAME, LOCK_WAIT_SECONDS])
            if not cur.fetchone()["got"]:
                print("Another recompute is still running; this one will be picked up by it.", file=sys.stderr)
                return 0
            run_ids = claim_runs(cur)
            if not run_ids:  # started without a queued request (e.g. by hand)
                cur.execute("INSERT INTO tbl_forecast_runs (TriggerReason, Status, StartedAt) VALUES ('manual (command line)', 'running', NOW())")
                run_ids = [cur.lastrowid]
            conn.commit()

            today = date.today()
            rows, cars = compute(cur, settings, today)
            # Replace the whole forecast in one transaction: readers see the old or the new one.
            cur.execute("DELETE FROM tbl_price_forecast")
            cur.executemany(
                "INSERT INTO tbl_price_forecast (VehicleId, ForecastDate, BasePrice, Multiplier, Price, Reasons, IsAvailable, RunId)"
                " VALUES (%s, %s, %s, %s, %s, %s, %s, " + str(int(run_ids[-1])) + ")",
                rows,
            )
            ml_note = ""
            if int(settings.get("pricing_ml_enabled", 0)):
                ml_note = " The gradient-boosting model is switched on but not used by the forecast yet."
            demand_note = "" if settings["_demand_on"] else (
                f" Occupancy and demand factors off ({settings['_confirmed']} of {settings['pricing_demand_min_bookings']} confirmed bookings).")
            finish_runs(cur, run_ids, "done", cars, len(rows),
                        f"{cars} cars x {settings['pricing_forecast_days']} days from {today.isoformat()}.{demand_note}{ml_note}")
            conn.commit()
            cur.execute("SELECT RELEASE_LOCK(%s)", [LOCK_NAME])
        print(f"Forecast written: {len(rows)} rows for {cars} cars.")
        return 0
    except Exception as exc:  # recorded for the admin page, details to the log
        conn.rollback()
        traceback.print_exc()
        try:
            with conn.cursor() as cur:
                finish_runs(cur, run_ids, "failed", 0, 0, f"{type(exc).__name__}: {exc}")
                conn.commit()
        except Exception:
            pass
        return 1
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
