"""
CLI inference script invoked from PHP (`includes/dynamic_pricing.php`
-> DynamicPricingService::buildCommand()).

Loads the trained pipeline, builds a single feature row for the
requested vehicle/date range, predicts the demand probability, and
prints ONE line of JSON with the recommended price. PHP reads that
line and decodes it with json_decode().

Usage:
    python predict_price.py \
        --vehicle-id=3 --start-date=2025-12-01 --end-date=2025-12-05 \
        --base-price=50.0 --db-host=localhost --db-user=root \
        --db-pass=secret --db-name=carrental
"""

from __future__ import annotations

import argparse
import json
from datetime import datetime, timedelta
from pathlib import Path

import joblib
import pandas as pd
from sqlalchemy import create_engine

from pricing_utils import PriceAdjustment, enrich_temporal_columns

DEFAULT_MODEL_PATH = Path(__file__).resolve().parent / "models" / "demand_model.pkl"


def parse_args(argv=None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Predict a demand-based price for a vehicle rental.")
    parser.add_argument("--vehicle-id", required=True, type=int)
    parser.add_argument("--start-date", required=True)
    parser.add_argument("--end-date", required=True)
    parser.add_argument("--base-price", type=float, default=None)
    parser.add_argument("--db-host", required=True)
    parser.add_argument("--db-user", required=True)
    parser.add_argument("--db-pass", required=True)
    parser.add_argument("--db-name", required=True)
    parser.add_argument("--db-port", type=int, default=3306)
    parser.add_argument("--model-path", default=str(DEFAULT_MODEL_PATH))
    return parser.parse_args(argv)


def build_engine(args: argparse.Namespace):
    uri = (
        f"mysql+pymysql://{args.db_user}:{args.db_pass}"
        f"@{args.db_host}:{args.db_port}/{args.db_name}"
    )
    return create_engine(uri)


def fetch_vehicle(engine, vehicle_id: int):
    sql = """
        SELECT v.id AS VehicleId, v.PricePerDay, v.ModelYear,
               v.SeatingCapacity, v.FuelType, br.BrandName
        FROM tblvehicles v
        LEFT JOIN tblbrands br ON v.VehiclesBrand = br.id
        WHERE v.id = %(vehicle_id)s
    """
    df = pd.read_sql(sql, engine, params={"vehicle_id": vehicle_id})
    if df.empty:
        return None
    return df.iloc[0].to_dict()


def fetch_recent_bookings_count(engine, vehicle_id: int, as_of: datetime) -> int:
    window_start = as_of - timedelta(days=30)
    sql = """
        SELECT COUNT(*) AS cnt
        FROM tblbooking
        WHERE VehicleId = %(vehicle_id)s
          AND Status = 1
          AND FromDate >= %(window_start)s
          AND FromDate < %(as_of)s
    """
    df = pd.read_sql(sql, engine, params={
        "vehicle_id": vehicle_id,
        "window_start": window_start.strftime("%Y-%m-%d"),
        "as_of": as_of.strftime("%Y-%m-%d"),
    })
    return int(df.iloc[0]["cnt"]) if not df.empty else 0


def emit(payload: dict):
    """Print exactly one line of JSON — this is what PHP's shell_exec() captures."""
    print(json.dumps(payload))


def fallback(status: str, vehicle_id: int, base_price):
    emit({
        "status": status,
        "vehicle_id": vehicle_id,
        "base_price": base_price,
        "recommended_price": base_price,
        "multiplier": 1.0,
        "probability": 0.5,
    })


def main(argv=None):
    args = parse_args(argv)
    model_path = Path(args.model_path)

    if not model_path.exists():
        fallback("model_not_found", args.vehicle_id, args.base_price)
        return

    try:
        engine = build_engine(args)
        vehicle = fetch_vehicle(engine, args.vehicle_id)
    except Exception as exc:  # noqa: BLE001 - must never crash the PHP caller
        fallback("db_error", args.vehicle_id, args.base_price)
        return

    if vehicle is None:
        fallback("vehicle_not_found", args.vehicle_id, args.base_price)
        return

    base_price = args.base_price if args.base_price is not None else float(vehicle["PricePerDay"])

    try:
        booking_date = datetime.now()
        recent_bookings = fetch_recent_bookings_count(engine, args.vehicle_id, booking_date)
        temporal = enrich_temporal_columns(args.start_date, args.end_date, booking_date)

        feature_row = pd.DataFrame([{
            "PricePerDay": float(vehicle["PricePerDay"]),
            "ModelYear": vehicle["ModelYear"],
            "SeatingCapacity": vehicle["SeatingCapacity"],
            "FuelType": vehicle["FuelType"] or "unknown",
            "BrandName": vehicle["BrandName"] or "unknown",
            "recent_vehicle_bookings_30d": recent_bookings,
            **temporal,
        }])

        artifact = joblib.load(model_path)
        pipeline = artifact["pipeline"]
        features = artifact["features"]
        input_cols = features["numeric"] + features["categorical"]

        probability = float(pipeline.predict_proba(feature_row[input_cols])[0][1])

        pricing_config = artifact.get("pricing_config", {})
        adjuster = PriceAdjustment(**pricing_config) if pricing_config else PriceAdjustment()

        result = adjuster.recommended_price(base_price, probability)
        result["status"] = "model"
        result["vehicle_id"] = args.vehicle_id
        emit(result)
    except Exception:  # noqa: BLE001 - always degrade gracefully to base price
        fallback("prediction_error", args.vehicle_id, base_price)


if __name__ == "__main__":
    main()
