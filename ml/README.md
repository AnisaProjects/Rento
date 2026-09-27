# Rento smart pricing (Python)

The Python side of smart pricing: it works out the price of every active car for each of the next 60 days,
and stores it in `tbl_price_forecast`. PHP only reads that table (`includes/pricing.php`), so no page waits
for Python. How the whole system fits together is in the main [README](../README.md#how-smart-pricing-works).

| File | What it does |
|---|---|
| `pricing_rules.py` | The rules: price of one car on one day, with the reasons (weekend, festival, travel days, season, lead time, occupancy, demand), capped and rounded. No database access, so it is easy to test |
| `batch_forecast.py` | Loads cars, bookings, festivals, overrides and settings, runs the rules for every car × day, and replaces the forecast in one transaction. Records the run in `tbl_forecast_runs` |
| `rento_db.py` | Database connection. Settings come only from `RENTO_DB_*` environment variables (or an INI file named by `RENTO_DB_CONFIG`), never from command-line arguments |
| `test_pricing_rules.py` | Unit tests for the rules |
| `pricing_model.py`, `predict_price.py`, `pricing_utils.py` | The gradient-boosting demand model, **locked** until 200 confirmed bookings (admin → Smart Pricing → Model health). Not used by the forecast yet |
| `requirements.txt` | Pinned versions (Python 3.12). The forecast needs only PyMySQL; the rest is for the model |

## Set up

```sh
/opt/homebrew/opt/python@3.12/bin/python3.12 -m venv ml/.venv      # Linux: python3.12 -m venv ml/.venv
ml/.venv/bin/pip install -r ml/requirements.txt
(cd ml && .venv/bin/python -m unittest -q test_pricing_rules)       # 8 tests
```

## Run

You don't normally start the Python script yourself:

- **Admin → Smart Pricing → Recompute prices**, and the automatic recomputes after bookings, festival
  and price changes, queue a run. PHP then starts `batch_forecast.py` with the database settings and the
  pricing settings (`RENTO_PRICING_SETTINGS`, JSON) in its environment.
- **Nightly:** `php tools/run-forecast.php nightly`, from launchd on the Mac or cron on a server
  (docs/deployment.md).
- **By hand:** `php tools/run-forecast.php "reason"`. It waits for the run and prints the result line.

Overlapping runs wait for each other through a MySQL named lock. The output goes to `logs/forecast.log`.

## Change the rules

- **Sizes** (percentages, limits, rounding, weekend days, thresholds) are admin settings, not code: admin →
  Smart Pricing (`includes/settings.php` lists them with their defaults).
- **Festival dates** are in admin → Festivals.
- **To add a new kind of factor:**
  1. Add it to `price_for_day()` in `pricing_rules.py`, with a reason label.
  2. Add a test.
  3. Add its setting to `includes/settings.php`.
