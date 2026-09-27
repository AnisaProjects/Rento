"""
Evaluate the rule-based smart pricer on the live 60-day forecast (tbl_price_forecast).

  1. Sample breakdowns for typical days: normal, weekend, early booking, Dashain travel days,
     Dashain main days, Tihar travel days, Tihar main days, and a day where the factors hit the cap.
  2. Cap and rounding checks on every forecast row: the multiplier stays within
     [1 - max_discount, 1 + max_surcharge], prices are multiples of round_to, and
     price == round(base x multiplier / round_to) x round_to.
  3. Quote consistency: GET api/price-quote.php totals equal the sum of the forecast day prices.
  4. Chart: the next 60 days of prices for two cars, as % of the usual price, with festival periods.

Usage: ml/.venv/bin/python tests/evaluation/evaluate_pricing.py [base_url]
Output: docs/report/results/pricing_eval.json, docs/report/figures/pricing_60_days.png
"""
from __future__ import annotations

import json
import subprocess
import sys
import urllib.request
from collections import defaultdict
from datetime import date, timedelta
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import matplotlib.ticker  # noqa: E402,F401

ROOT = Path(__file__).resolve().parents[2]
BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8888/Rento").rstrip("/")
PHP = "/Applications/MAMP/bin/php/php8.3.14/bin/php"
CARS = [7, 6]  # Toyota Fortuner (Rs 10,000), Nissan Sunny (Rs 5,000)


def labels(row):
    return [r["label"] for r in (row["Reasons"] or {}).get("reasons", [])]


def main() -> int:
    data = json.loads(subprocess.check_output([PHP, str(ROOT / "tools/export-report-data.php")]))
    s = data["settings"]
    lo, hi, step = 1 - float(s["pricing_max_discount"]) / 100, 1 + float(s["pricing_max_surcharge"]) / 100, int(s["pricing_round_to"])
    rows = data["forecast"]
    cars = {int(c["id"]): c for c in data["cars"]}
    by_car = defaultdict(list)
    for r in rows:
        by_car[int(r["VehicleId"])].append(r)

    # 2. Caps and rounding on every row.
    bad_cap = [r for r in rows if not (lo - 1e-9 <= float(r["Multiplier"]) <= hi + 1e-9)]
    bad_round = [r for r in rows if int(r["Price"]) % step]
    bad_formula = [r for r in rows if not (r["Reasons"] or {}).get("override") and
                   int(r["Price"]) != int(round(int(r["BasePrice"]) * float(r["Multiplier"]) / step) * step)]
    capped = [r for r in rows if (r["Reasons"] or {}).get("capped")]
    raw_sum = lambda r: sum(x["pct"] for x in (r["Reasons"] or {}).get("reasons", []))
    over = [r for r in rows if raw_sum(r) > (hi - 1) * 100 + 1e-9]
    checks = {"rows": len(rows), "cars": len(by_car), "days_per_car": len(next(iter(by_car.values()))),
              "limits": {"min_multiplier": lo, "max_multiplier": hi, "round_to": step},
              "multiplier_range_seen": [min(float(r["Multiplier"]) for r in rows), max(float(r["Multiplier"]) for r in rows)],
              "rows_outside_limits": len(bad_cap), "rows_not_rounded": len(bad_round), "rows_formula_mismatch": len(bad_formula),
              "rows_where_factors_exceed_cap": len(over), "max_uncapped_sum_pct": max(raw_sum(r) for r in rows),
              "rows_marked_capped": len(capped)}

    # 1. Sample breakdowns (Fortuner).
    fr = by_car[7]
    def first(pred):
        return next((r for r in fr if pred(r)), None)
    samples = {
        "Normal day (no factor)": first(lambda r: not labels(r)),
        "Last minute (0-2 days ahead)": first(lambda r: any(l.startswith("Last") for l in labels(r))),
        "Weekend (no festival)": first(lambda r: any(l.startswith("Weekend") for l in labels(r)) and not any("Dashain" in l or "Tihar" in l or "Chhath" in l for l in labels(r))),
        "Early booking (45+ days ahead)": first(lambda r: any("Early" in l for l in labels(r)) and not any("Dashain" in l or "Tihar" in l for l in labels(r))),
        "Dashain travel days": first(lambda r: any("Dashain" in l and "travel" in l for l in labels(r))),
        "Dashain main days": first(lambda r: any(l.startswith("Dashain") and "travel" not in l for l in labels(r))),
        "Tihar travel days": first(lambda r: any("Tihar" in l and "travel" in l for l in labels(r))),
        "Tihar main days": first(lambda r: any(l.startswith("Tihar") and "travel" not in l for l in labels(r))),
        "Factors exceed the cap": max(fr, key=raw_sum) if any(raw_sum(r) > (hi - 1) * 100 for r in fr) else None,
    }
    breakdown = {}
    for name, r in samples.items():
        if not r:
            breakdown[name] = None
            continue
        d = date.fromisoformat(r["ForecastDate"])
        breakdown[name] = {"date": r["ForecastDate"], "weekday": d.strftime("%a"), "base": int(r["BasePrice"]),
                           "factors": [{"label": x["label"], "pct": x["pct"]} for x in r["Reasons"]["reasons"]],
                           "sum_pct": round(raw_sum(r), 2), "applied_pct": round((float(r["Multiplier"]) - 1) * 100, 2),
                           "price": int(r["Price"]), "capped": bool(r["Reasons"].get("capped"))}

    # 3. Quote API consistency.
    quotes = []
    for car in CARS:
        days = by_car[car]
        for start, n in [(0, 3), (12, 3), (40, 2)]:
            a, b = days[start]["ForecastDate"], days[start + n - 1]["ForecastDate"]
            with urllib.request.urlopen(f"{BASE}/api/price-quote.php?car={car}&from={a}&to={b}") as resp:
                q = json.loads(resp.read())
            expected = sum(int(x["Price"]) for x in days[start:start + n])
            quotes.append({"car": cars[car]["VehiclesTitle"], "from": a, "to": b, "api_total": q.get("total"),
                           "sum_of_forecast_days": expected, "match": q.get("total") == expected})

    # 4. Chart.
    plt.rcParams.update({"font.family": "DejaVu Sans", "font.size": 10, "axes.spines.top": False, "axes.spines.right": False, "figure.dpi": 150})
    fig, ax = plt.subplots(figsize=(11, 3.8))
    start = date.fromisoformat(fr[0]["ForecastDate"])
    end = date.fromisoformat(fr[-1]["ForecastDate"])
    for f in data["festivals"]:
        fs, fe = date.fromisoformat(f["StartDate"]), date.fromisoformat(f["EndDate"])
        if f["Verification"] != "guidance" and fe >= start and fs <= end and f["DemandLevel"] == "high":
            ax.axvspan(max(fs, start), min(fe, end) + timedelta(days=1), color="#ff5a36", alpha=0.12, lw=0)
            ax.text(max(fs, start), 0, f["FestivalName"].split(" ")[0], fontsize=8, color="#c43c1a")
            if f["TravelFrom"]:
                ts, te = date.fromisoformat(f["TravelFrom"]), date.fromisoformat(f["TravelTo"])
                ax.axvspan(max(ts, start), min(te, end) + timedelta(days=1), color="#ffb238", alpha=0.18, lw=0)
    # Every car gets the same percentages while the occupancy and demand factors are off, so one car is shown.
    base = int(cars[7]["PricePerDay"])
    xs = [date.fromisoformat(r["ForecastDate"]) for r in fr]
    ax.step(xs, [int(r["Price"]) for r in fr], where="post", color="#101114", lw=1.5, label=f'Toyota Fortuner, usual Rs {base:,}/day')
    ax.axhline(base, color="#9497a6", lw=0.8, ls="--")
    ax.axhline(base * hi, color="#b3261e", lw=0.8, ls=":", label="caps (-15% / +25%)")
    ax.axhline(base * lo, color="#b3261e", lw=0.8, ls=":")
    ax.set_ylim(base * lo - 400, base * hi + 800)
    for t in ax.texts: t.set_y(base * hi + 350)
    ax.set_ylabel("price per day (Rs)")
    ax.yaxis.set_major_formatter(matplotlib.ticker.FuncFormatter(lambda v, _: f"{v:,.0f}"))
    ax.set_title("Smart price for the next 60 days (orange: festival, amber: travel days). All cars follow the same percentages\nwhile the occupancy and demand factors are off (2 of 50 confirmed bookings).", fontsize=9.5)
    ax.legend(frameon=False, loc="lower left")
    fig.tight_layout()
    fig.savefig(ROOT / "docs/report/figures/pricing_60_days.png")
    plt.close(fig)

    out = {"generated": data["generated"], "confirmed_bookings": data["counts"]["confirmed"],
           "demand_factors_on": data["counts"]["confirmed"] >= int(s["pricing_demand_min_bookings"]),
           "settings": {k: v for k, v in s.items() if k.startswith("pricing_")}, "checks": checks,
           "sample_breakdowns_fortuner": breakdown, "quote_consistency": quotes}
    (ROOT / "docs/report/results/pricing_eval.json").write_text(json.dumps(out, indent=2))
    print(json.dumps(checks, indent=1))
    for n, b in breakdown.items():
        print(f"{n:32s}", "-" if not b else f'{b["date"]} {b["weekday"]}  {", ".join(x["label"] + " " + format(x["pct"], "+g") + "%" for x in b["factors"]) or "none"}  -> sum {b["sum_pct"]:+g}%, applied {b["applied_pct"]:+g}%, Rs {b["price"]:,}')
    print("quotes:", sum(q["match"] for q in quotes), "/", len(quotes), "match")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
