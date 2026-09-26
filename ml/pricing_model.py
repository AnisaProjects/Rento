"""
Trainer for the dynamic pricing demand model.

Reads historical bookings + vehicle inventory from MySQL, engineers
features, balances the dataset with synthetic low-demand examples,
fits a GradientBoostingClassifier inside a preprocessing Pipeline, and
persists the whole pipeline to disk for `predict_price.py` to use.

Usage:
    python pricing_model.py \
        --db-host=localhost --db-user=root --db-pass=secret \
        --db-name=carrental --negative-samples=3 --minimum-rows=20
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timedelta
from pathlib import Path

import joblib
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.impute import SimpleImputer
from sklearn.metrics import classification_report
from sklearn.model_selection import StratifiedKFold, cross_val_predict
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sqlalchemy import create_engine

from pricing_utils import (
    ALL_FEATURES,
    CATEGORICAL_FEATURES,
    FEATURE_SCHEMA,
    NUMERIC_FEATURES,
    enrich_temporal_columns,
)

MODEL_DIR = Path(__file__).resolve().parent / "models"
DATA_CACHE_DIR = Path(__file__).resolve().parent / "data_cache"
MODEL_PATH = MODEL_DIR / "demand_model.pkl"


def parse_args(argv=None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train the dynamic pricing demand model.")
    parser.add_argument("--db-host", required=True)
    parser.add_argument("--db-user", required=True)
    parser.add_argument("--db-pass", required=True)
    parser.add_argument("--db-name", required=True)
    parser.add_argument("--db-port", type=int, default=3306)
    parser.add_argument(
        "--negative-samples", type=int, default=3,
        help="Synthetic low-demand rows generated per vehicle.",
    )
    parser.add_argument(
        "--minimum-rows", type=int, default=20,
        help="Abort training if fewer than this many total rows are available.",
    )
    parser.add_argument("--model-path", default=str(MODEL_PATH))
    return parser.parse_args(argv)


def build_engine(args: argparse.Namespace):
    uri = (
        f"mysql+pymysql://{args.db_user}:{args.db_pass}"
        f"@{args.db_host}:{args.db_port}/{args.db_name}"
    )
    return create_engine(uri)


def fetch_raw_data(engine) -> pd.DataFrame:
    """Pull confirmed bookings joined with vehicle + brand info."""
    sql = """
        SELECT
            b.VehicleId,
            b.FromDate,
            b.ToDate,
            b.PostingDate,
            v.PricePerDay,
            v.ModelYear,
            v.SeatingCapacity,
            v.FuelType,
            br.BrandName
        FROM tblbooking b
        JOIN tblvehicles v ON b.VehicleId = v.id
        LEFT JOIN tblbrands br ON v.VehiclesBrand = br.id
        WHERE b.Status = 1
    """
    return pd.read_sql(sql, engine)


def compute_recent_bookings(bookings: pd.DataFrame) -> pd.DataFrame:
    """
    For every booking row, count how many *other* bookings the same
    vehicle had in the 30 days leading up to its FromDate. This becomes
    the `recent_vehicle_bookings_30d` demand signal.
    """
    bookings = bookings.sort_values("FromDate").reset_index(drop=True)
    counts = []
    for _, row in bookings.iterrows():
        window_start = row["FromDate"] - timedelta(days=30)
        mask = (
            (bookings["VehicleId"] == row["VehicleId"]) &
            (bookings["FromDate"] >= window_start) &
            (bookings["FromDate"] < row["FromDate"])
        )
        counts.append(int(mask.sum()))
    bookings["recent_vehicle_bookings_30d"] = counts
    return bookings


def engineer_positive_examples(raw: pd.DataFrame) -> pd.DataFrame:
    """Real bookings = high-demand (label 1) examples."""
    df = raw.copy()
    df["FromDate"] = pd.to_datetime(df["FromDate"])
    df["ToDate"] = pd.to_datetime(df["ToDate"])
    df["PostingDate"] = pd.to_datetime(df["PostingDate"])

    df = compute_recent_bookings(df)

    temporal_rows = df.apply(
        lambda r: enrich_temporal_columns(r["FromDate"], r["ToDate"], r["PostingDate"]),
        axis=1,
        result_type="expand",
    )
    df = pd.concat([df, temporal_rows], axis=1)
    df["demand_label"] = 1
    return df


def generate_synthetic_negatives(engine, negative_samples: int) -> pd.DataFrame:
    """
    For every distinct vehicle, synthesize `negative_samples` future
    date windows with zero recent bookings and label them low demand
    (label 0). This balances the dataset since real booking history is
    inherently all "someone wanted this" (positive) examples.
    """
    vehicles_sql = """
        SELECT v.id AS VehicleId, v.PricePerDay, v.ModelYear,
               v.SeatingCapacity, v.FuelType, br.BrandName
        FROM tblvehicles v
        LEFT JOIN tblbrands br ON v.VehiclesBrand = br.id
    """
    vehicles = pd.read_sql(vehicles_sql, engine)

    rows = []
    today = datetime.now()
    for _, vehicle in vehicles.iterrows():
        for i in range(negative_samples):
            start = today + timedelta(days=30 + i * 15)
            end = start + timedelta(days=2)
            temporal = enrich_temporal_columns(start, end, today)
            row = {
                "VehicleId": vehicle["VehicleId"],
                "PricePerDay": vehicle["PricePerDay"],
                "ModelYear": vehicle["ModelYear"],
                "SeatingCapacity": vehicle["SeatingCapacity"],
                "FuelType": vehicle["FuelType"],
                "BrandName": vehicle["BrandName"],
                "recent_vehicle_bookings_30d": 0,
                "demand_label": 0,
            }
            row.update(temporal)
            rows.append(row)

    return pd.DataFrame(rows)


def build_pipeline(early_stopping: bool = True) -> Pipeline:
    """
    ColumnTransformer (median-impute + scale numerics, most-frequent
    impute + one-hot categoricals) feeding into a GradientBoosting
    classifier. Hyperparameters match ml/README.md exactly.

    `early_stopping=False` drops `validation_fraction`/`n_iter_no_change`.
    GradientBoostingClassifier's early stopping does its OWN internal
    stratified train/validation split on every `.fit()` call, which
    requires at least 2 examples of every class in whatever data it's
    given. When training on a small/imbalanced sample (e.g. inside a
    cross-validation fold with only 1-2 real bookings per class), that
    internal split can fail even though the outer split was fine. Turn
    early stopping off in that situation and let it use the fixed
    `n_estimators` instead.
    """
    numeric_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="median")),
        ("scaler", StandardScaler()),
    ])

    categorical_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="most_frequent")),
        ("onehot", OneHotEncoder(handle_unknown="ignore")),
    ])

    preprocessor = ColumnTransformer(transformers=[
        ("num", numeric_transformer, NUMERIC_FEATURES),
        ("cat", categorical_transformer, CATEGORICAL_FEATURES),
    ])

    model_kwargs = dict(
        n_estimators=400,
        learning_rate=0.05,
        max_depth=3,
        random_state=42,
    )
    if early_stopping:
        model_kwargs.update(validation_fraction=0.1, n_iter_no_change=5)

    model = GradientBoostingClassifier(**model_kwargs)

    return Pipeline(steps=[
        ("features", preprocessor),
        ("model", model),
    ])


def main(argv=None):
    args = parse_args(argv)
    MODEL_DIR.mkdir(parents=True, exist_ok=True)
    DATA_CACHE_DIR.mkdir(parents=True, exist_ok=True)

    engine = build_engine(args)

    raw = fetch_raw_data(engine)
    if raw.empty:
        print("No booking history found in tblbooking; cannot train.", file=sys.stderr)
        sys.exit(1)

    positives = engineer_positive_examples(raw)
    negatives = generate_synthetic_negatives(engine, args.negative_samples)

    dataset = pd.concat([positives, negatives], ignore_index=True, sort=False)

    if len(dataset) < args.minimum_rows:
        print(
            f"Not enough rows to train ({len(dataset)} < {args.minimum_rows}). "
            "Lower --minimum-rows or gather more booking history.",
            file=sys.stderr,
        )
        sys.exit(1)

    dataset[NUMERIC_FEATURES] = dataset[NUMERIC_FEATURES].apply(pd.to_numeric, errors="coerce")
    for col in CATEGORICAL_FEATURES:
        dataset[col] = dataset[col].fillna("unknown").astype(str)

    try:
        dataset.to_parquet(DATA_CACHE_DIR / "training_snapshot.parquet", index=False)
    except ImportError:
        # pyarrow/fastparquet not installed - fall back to CSV so the
        # debug snapshot still gets written without blocking training.
        dataset.to_csv(DATA_CACHE_DIR / "training_snapshot.csv", index=False)
        print(
            "Note: pyarrow/fastparquet not installed, wrote training_snapshot.csv "
            "instead of .parquet. Run 'pip install pyarrow' for parquet support.",
            file=sys.stderr,
        )

    X = dataset[ALL_FEATURES]
    y = dataset["demand_label"]

    class_counts = y.value_counts()
    minority_count = int(class_counts.min()) if len(class_counts) > 1 else 0

    # GradientBoosting's internal early-stopping split needs enough of
    # the minority class to survive being split again inside a fold.
    # 20 is a conservative floor for that inner 90/10 split to reliably
    # leave >=2 minority examples on each side.
    early_stopping = minority_count >= 20

    if minority_count < 2:
        # Not enough of the rarer class to hold any out for evaluation.
        # A random train/test split would either error out or (worse)
        # silently omit that class from the test set, producing a
        # meaningless "100% accuracy" report like the one that motivated
        # this fix. Be honest about it instead of faking a score.
        print(
            f"WARNING: only {minority_count} example(s) of the minority class "
            f"({class_counts.idxmin() if len(class_counts) > 1 else 'n/a'}). "
            "Skipping held-out evaluation - add more real bookings to "
            "tblbooking to get a meaningful classification report.\n"
            "Training on the full dataset without a test split.",
            file=sys.stderr,
        )
        pipeline = build_pipeline(early_stopping=False)
        pipeline.fit(X, y)
        report = (
            "No evaluation performed: insufficient data to hold out a "
            f"test set (minority class has only {minority_count} example(s))."
        )
        print(report)
    else:
        # Cross-validation instead of a single random split: every row
        # gets evaluated exactly once as it rotates through the held-out
        # fold, so small/imbalanced datasets can't get "lucky" (or
        # unlucky) the way one random 80/20 split can.
        n_splits = max(2, min(5, minority_count))
        cv = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)

        if not early_stopping:
            print(
                f"Note: only {minority_count} example(s) of the minority class - "
                "disabling GradientBoosting's internal early stopping (needs more "
                "data than that to split safely) and using a fixed tree count instead.",
                file=sys.stderr,
            )

        y_pred = cross_val_predict(build_pipeline(early_stopping=early_stopping), X, y, cv=cv)
        report = classification_report(y, y_pred, zero_division=0)
        print(f"Cross-validated ({n_splits}-fold) classification report:\n")
        print(report)

        # Refit on 100% of the data for the artifact we actually ship -
        # cross-validation above was purely for an honest performance
        # estimate, not the model we deploy.
        pipeline = build_pipeline(early_stopping=early_stopping)
        pipeline.fit(X, y)

    artifact = {
        "pipeline": pipeline,
        "features": FEATURE_SCHEMA,
        "trained_at": datetime.now().isoformat(),
        "n_rows": len(dataset),
        "pricing_config": {
            "low_threshold": 0.35,
            "high_threshold": 0.70,
            "max_surge_multiplier": 1.35,
            "max_discount_multiplier": 0.80,
        },
    }

    model_path = Path(args.model_path)
    joblib.dump(artifact, model_path)

    summary = {
        "trained_at": artifact["trained_at"],
        "n_rows": artifact["n_rows"],
        "features": FEATURE_SCHEMA,
        "classification_report": report,
    }
    with open(str(model_path) + ".json", "w") as fh:
        json.dump(summary, fh, indent=2)

    print(f"Saved model to {model_path}")


if __name__ == "__main__":
    main()