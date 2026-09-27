"""
Evaluate the recommendation engine through the real endpoint (GET api/recommend.php).

For each trip in recommendation_scenarios.json (expected cars written down before running):
  - precision@3 = relevant cars among the top 3 / 3
  - normalised precision@3 = relevant cars among the top 3 / min(3, number of relevant cars)
  - hit@1 = the first result is relevant (and is the required 'top1' when given)
and rule-consistency checks on every returned car: seats >= required, price within the budget + 10%,
active, not driver-only, scores in descending order, no false "as you asked" reasons, fuel honesty.

Usage: ml/.venv/bin/python tests/evaluation/evaluate_recommendations.py [base_url]
Output: docs/report/results/recommendation_eval.json (+ printed table)
"""
from __future__ import annotations

import json
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8888/Rento").rstrip("/")
PHP = "/Applications/MAMP/bin/php/php8.3.14/bin/php"


def main() -> int:
    data = json.loads(subprocess.check_output([PHP, str(ROOT / "tools/export-report-data.php")]))
    cars = {int(c["id"]): c for c in data["cars"]}
    scen = json.loads((ROOT / "tests/evaluation/recommendation_scenarios.json").read_text())["scenarios"]
    rows, checks = [], []
    for s in scen:
        t0 = time.perf_counter()
        with urllib.request.urlopen(f"{BASE}/api/recommend.php?" + urllib.parse.urlencode({"trip": s["trip"]})) as r:
            res = json.loads(r.read())
        ms = (time.perf_counter() - t0) * 1000
        matches = res.get("matches", [])
        ids = [m["car"]["id"] for m in matches]
        rel = set(s["relevant"])
        top3 = ids[:3]
        hits = len([i for i in top3 if i in rel])
        row = {"id": s["id"], "trip": s["trip"], "understood": res.get("understood"), "returned": len(ids),
               "top3": [f'{m["car"]["name"]} ({m["match_percent"]}%)' for m in matches[:3]],
               "relevant": sorted(rel), "precision_at_3": hits / 3 if rel else None,
               "norm_precision_at_3": hits / min(3, len(rel)) if rel else None,
               "hit_at_1": (bool(ids) and ids[0] in rel and (s.get("top1") is None or ids[0] == s["top1"])) if rel else None,
               "ms": round(ms, 1)}
        # Rule-consistency checks on every returned car.
        problems = []
        for m in matches:
            c = cars[m["car"]["id"]]
            if s.get("min_seats") and int(c["SeatingCapacity"]) < s["min_seats"]:
                problems.append(f'{c["VehiclesTitle"]}: {c["SeatingCapacity"]} seats < {s["min_seats"]}')
            if s.get("max_budget") and m["price_per_day"] > s["max_budget"] * 1.10:
                problems.append(f'{c["VehiclesTitle"]}: Rs {m["price_per_day"]} over budget')
            if not int(c["IsActive"]):
                problems.append(f'{c["VehiclesTitle"]}: inactive car returned')
            if int(c["DriverOnly"]):
                problems.append(f'{c["VehiclesTitle"]}: driver-only car in self-drive matches')
            if not (res.get("understood") or {}).get("type_named") and any("you asked for" in x for x in m["reasons"]):
                problems.append(f'{c["VehiclesTitle"]}: "you asked for" although the type was only inferred')
            if s.get("no_fuel_claim") and any(s["no_fuel_claim"] in x and "as you asked" in x for x in m["reasons"]) and c["FuelType"] != s["no_fuel_claim"]:
                problems.append(f'{c["VehiclesTitle"]}: claims {s["no_fuel_claim"]} but is {c["FuelType"]}')
        pct = [m["match_percent"] for m in matches]
        if pct != sorted(pct, reverse=True):
            problems.append("scores not in descending order")
        if s.get("no_fuel_claim"):
            row["electric_cars_in_fleet"] = sum(1 for c in cars.values() if int(c["IsActive"]) and c["FuelType"] == s["no_fuel_claim"])
        row["rule_problems"] = problems
        checks.append(len(problems) == 0)
        rows.append(row)
    scored = [r for r in rows if r["precision_at_3"] is not None]
    summary = {"scenarios": len(rows), "scored": len(scored),
               "mean_precision_at_3": sum(r["precision_at_3"] for r in scored) / len(scored),
               "mean_norm_precision_at_3": sum(r["norm_precision_at_3"] for r in scored) / len(scored),
               "hit_at_1": sum(1 for r in scored if r["hit_at_1"]) / len(scored),
               "rule_checks_passed": f"{sum(checks)}/{len(checks)}",
               "median_ms": sorted(r["ms"] for r in rows)[len(rows) // 2]}
    out = {"generated": data["generated"], "base_url": BASE, "summary": summary, "scenarios": rows}
    (ROOT / "docs/report/results/recommendation_eval.json").write_text(json.dumps(out, indent=2))
    for r in rows:
        p = "-" if r["precision_at_3"] is None else f'{r["precision_at_3"]:.2f}/{r["norm_precision_at_3"]:.2f}/{"Y" if r["hit_at_1"] else "N"}'
        print(f'{r["id"]} {r["trip"][:44]:44s} {p:14s} {"; ".join(r["top3"])[:95]}  {"OK" if not r["rule_problems"] else r["rule_problems"]}')
    print(json.dumps(summary, indent=1))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
