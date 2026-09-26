# Dynamic Pricing Companion

The scripts inside `carrental/ml` train and serve a lightweight demand model
that integrates with the PHP frontend through `includes/dynamic_pricing.php`.

## 1. Install Python dependencies

```bash
cd carrental/ml
python -m venv .venv
.venv\Scripts\activate  # Windows
pip install -r requirements.txt
```

## 2. Train / refresh the model

```bash
python pricing_model.py ^
  --db-host=localhost ^
  --db-user=root ^
  --db-pass=secret ^
  --db-name=carrental
```

The command stores the fitted artifact at `ml/models/demand_model.pkl` and a
diagnostic snapshot under `ml/data_cache/training_snapshot.parquet`.

## 3. Test inference from PHP

```bash
python predict_price.py --vehicle-id=3 --start-date=2025-12-01 --end-date=2025-12-05
```

The script prints a JSON payload containing the recommended daily rate, model
probability, and supporting metadata. The PHP helper wraps the same CLI.

Environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `PYTHON_BIN`)
override the related CLI arguments to simplify deployment.

