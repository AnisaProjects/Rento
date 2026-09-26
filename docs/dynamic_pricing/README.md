# Dynamic Pricing Update Overview

This document explains the new dynamic pricing capability, the files it touched, and how each piece works so future contributors can extend or troubleshoot it quickly.

## Folder Map

```
carrental/
├─ includes/dynamic_pricing.php        # PHP bridge to the ML model
├─ vehical-details.php                 # Frontend hookup to show dynamic rate
└─ ml/                                 # Python tooling (trainer + inference)
   ├─ pricing_model.py
   ├─ predict_price.py
   ├─ pricing_utils.py
   ├─ requirements.txt
   └─ README.md
```

## What Changed (Files & Purpose)

| File                           | Purpose                                                                                                                                                                                                                  |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `ml/pricing_model.py`          | CLI trainer that reads `tblbooking`, `tblvehicles`, `tblbrands`, engineers features (seasonality, lead time, recent demand), fits a GradientBoosting classifier, and saves the pipeline to `ml/models/demand_model.pkl`. |
| `ml/predict_price.py`          | CLI inference script that PHP calls. It loads the saved pipeline, gathers fresh vehicle context + bookings, predicts demand probability, and outputs JSON with a multiplier and recommended price.                       |
| `ml/pricing_utils.py`          | Shared helpers for both scripts (season buckets, feature creation, price-adjustment dataclass).                                                                                                                          |
| `ml/requirements.txt`          | Dependency list for the virtual environment (NumPy, pandas, scikit-learn, SQLAlchemy, PyMySQL, joblib).                                                                                                                  |
| `ml/README.md`                 | Step-by-step setup instructions (venv, training command, inference test).                                                                                                                                                |
| `includes/dynamic_pricing.php` | New PHP service that shells out to `predict_price.py`, passes DB credentials, caches results per vehicle/date combo, and falls back to base price if the model is unavailable.                                           |
| `vehical-details.php`          | Uses `dynamic_price_quote()` to display the recommended rate and a small surge/discount tag next to the price box.                                                                                                       |

## How It Works End-to-End

1. **Training Phase (offline / cron job)**
   - Activate the Python venv, install `ml/requirements.txt`.
   - Run `python ml/pricing_model.py --db-host=... --db-user=... --db-pass=... --db-name=carrental`.
   - The script fetches bookings + inventory, augments them with temporal features, synthesizes low-demand rows, fits the model, prints a classification report, and writes `ml/models/demand_model.pkl` (plus a `.json` summary and parquet snapshot for audits).

2. **Inference Phase (per page view)**
   - `vehical-details.php` calls `dynamic_price_quote()` via the shared PDO connection.
   - `dynamic_pricing.php` composes a shell command: `<python> ml/predict_price.py --vehicle-id=... --start-date=... --end-date=... --base-price=... --db-* creds`.
   - The Python script:
     1. Fetches the vehicle row + recent bookings (last 30 days).
     2. Builds a feature frame using `pricing_utils`.
     3. Loads the saved model and calculates demand probability.
     4. Translates probability to a multiplier (surge/discount rules) and prints a JSON payload.
   - PHP parses the JSON, caches it for identical parameters during the request lifecycle, and returns the `recommended_price`, `multiplier`, and metadata.
   - The template shows the dynamic rate (formatted to 2 decimals). If the status is `model`, it renders a badge with the percent change (`multiplier - 1`). If anything fails (no model, CLI error), it silently shows the original `PricePerDay`.

## Operational Notes

- **Python binary:** override with `PRICING_PYTHON_BIN` env var or `$options['python']` when instantiating `DynamicPricingService`.
- **Model file location:** defaults to `ml/models/demand_model.pkl`. Pass `model_path` in the options array if you relocate it.
- **Database access:** uses the same credentials defined in `includes/config.php`. No schema changes were required.
- **Fallback behavior:** If the CLI outputs nothing, fails JSON decoding, or no model is present, PHP returns the base price with status explaining the reason (`script_missing`, `no_output`, `json_error`, `model_not_found`, etc.).
- **Extending to other pages:** Call `dynamic_price_quote($dbh, $vehicleId, ['start_date' => ..., 'end_date' => ...])` anywhere you need the real-time rate; reuse the `recommended_price` field in the template.
