"""
Response times of the JSON API and the main pages on the local server (MAMP), N sequential requests each.
Reports median, 95th percentile and max in milliseconds. Stays under the API rate limits and removes its
own rate-limit records afterwards.

Usage: ml/.venv/bin/python tests/evaluation/measure_performance.py [base_url] [N]
Output: docs/report/results/performance.json
"""
from __future__ import annotations

import json
import statistics
import subprocess
import sys
import time
import urllib.request
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8888/Rento").rstrip("/")
N = int(sys.argv[2]) if len(sys.argv) > 2 else 30
MYSQL = ["/Applications/MAMP/Library/bin/mysql57/bin/mysql", "-uroot", "-proot", "-P8889", "-h127.0.0.1", "rento", "-e"]


def timed(url: str) -> tuple[float, int, int]:
    t = time.perf_counter()
    with urllib.request.urlopen(url) as r:
        body = r.read()
        status = r.status
    return (time.perf_counter() - t) * 1000, status, len(body)


def main() -> int:
    d1 = (date.today() + timedelta(days=14)).isoformat()
    d2 = (date.today() + timedelta(days=16)).isoformat()
    month = (date.today().replace(day=1) + timedelta(days=32)).strftime("%Y-%m")
    targets = {
        "GET api/price-quote.php (3 days)": f"{BASE}/api/price-quote.php?car=7&from={d1}&to={d2}",
        "GET api/price-calendar.php (1 month)": f"{BASE}/api/price-calendar.php?car=7&month={month}",
        "GET api/recommend.php (trip text)": f"{BASE}/api/recommend.php?trip=family%20trip%20to%20Pokhara%20under%20Rs%208000",
        "GET / (home page)": f"{BASE}/",
        "GET car-listing": f"{BASE}/car-listing",
        "GET car/toyota-fortuner-7": f"{BASE}/car/toyota-fortuner-7",
        "GET car-recommendation": f"{BASE}/car-recommendation",
    }
    out = {"base_url": BASE, "requests_each": N, "date": date.today().isoformat(), "server": "MAMP on macOS (Apache 2.4.62, PHP 8.3.14, MySQL 8.0.40), same machine as the client", "results": {}}
    for name, url in targets.items():
        timed(url)  # warm-up
        runs = [timed(url) for _ in range(N)]
        ms = sorted(r[0] for r in runs)
        out["results"][name] = {"median_ms": round(statistics.median(ms), 1), "p95_ms": round(ms[int(0.95 * (len(ms) - 1))], 1),
                                "max_ms": round(ms[-1], 1), "status": sorted({r[1] for r in runs}), "bytes": runs[-1][2]}
        print(f"{name:40s} median {out['results'][name]['median_ms']:7.1f} ms  p95 {out['results'][name]['p95_ms']:7.1f} ms  {runs[-1][2]:,} bytes")
    subprocess.run(MYSQL + ["DELETE FROM tblratelimits WHERE Action LIKE 'api_%' AND IpAddress IN ('127.0.0.1','::1')"], capture_output=True)
    (ROOT / "docs/report/results/performance.json").write_text(json.dumps(out, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
