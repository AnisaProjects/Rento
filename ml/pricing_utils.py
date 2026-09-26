"""
Shared helpers for the dynamic pricing ML pipeline.

Used by both `pricing_model.py` (training) and `predict_price.py`
(inference) so that feature engineering is guaranteed to be identical
between the two stages. Keeping this logic in one place is what
prevents "feature mismatch" bugs between training and inference.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, date
from typing import Union

DateLike = Union[str, datetime, date]

# ---------------------------------------------------------------------------
# Feature schema shared by trainer + inference
# ---------------------------------------------------------------------------

NUMERIC_FEATURES = [
    "PricePerDay",
    "ModelYear",
    "SeatingCapacity",
    "lead_time_days",
    "rental_days",
    "recent_vehicle_bookings_30d",
    "booking_month",
    "booking_dow",
    "is_weekend_pickup",
]

CATEGORICAL_FEATURES = [
    "FuelType",
    "BrandName",
    "season_bucket",
]

FEATURE_SCHEMA = {
    "numeric": NUMERIC_FEATURES,
    "categorical": CATEGORICAL_FEATURES,
}

ALL_FEATURES = NUMERIC_FEATURES + CATEGORICAL_FEATURES


# ---------------------------------------------------------------------------
# Season buckets
# ---------------------------------------------------------------------------

_SEASON_BUCKETS = {
    1: "winter",
    2: "winter",
    3: "spring",
    4: "spring",
    5: "summer",
    6: "summer",
    7: "monsoon",
    8: "monsoon",
    9: "autumn",
    10: "autumn",
    11: "festive",
    12: "festive",
}


def season_bucket(month: int) -> str:
    """Map a calendar month (1-12) to a coarse seasonal bucket."""
    return _SEASON_BUCKETS.get(int(month), "unknown")


def _to_datetime(value: DateLike) -> datetime:
    if isinstance(value, datetime):
        return value
    if isinstance(value, date):
        return datetime(value.year, value.month, value.day)
    return datetime.strptime(str(value)[:10], "%Y-%m-%d")


def enrich_temporal_columns(start_date: DateLike, end_date: DateLike, booking_date: DateLike = None) -> dict:
    """
    Compute every temporal feature the model expects, given the rental
    start/end date and (optionally) the date the booking was made.

    Returns a plain dict ready to be merged into a feature row / DataFrame.
    """
    start_dt = _to_datetime(start_date)
    end_dt = _to_datetime(end_date)
    booking_dt = _to_datetime(booking_date) if booking_date else datetime.now()

    rental_days = max((end_dt - start_dt).days, 1)
    lead_time_days = max((start_dt - booking_dt).days, 0)
    booking_month = start_dt.month
    booking_dow = start_dt.weekday()  # 0 = Monday .. 6 = Sunday
    is_weekend_pickup = 1 if booking_dow >= 5 else 0

    return {
        "lead_time_days": lead_time_days,
        "rental_days": rental_days,
        "booking_month": booking_month,
        "booking_dow": booking_dow,
        "is_weekend_pickup": is_weekend_pickup,
        "season_bucket": season_bucket(booking_month),
    }


# ---------------------------------------------------------------------------
# Price adjustment logic
# ---------------------------------------------------------------------------

@dataclass
class PriceAdjustment:
    """
    Translates a demand probability (0-1) into a price multiplier using
    simple, explainable business rules:

        probability >= high_threshold  -> surge pricing (up to max_surge)
        probability <= low_threshold   -> discount pricing (down to max_discount)
        otherwise                      -> base price (no change)

    Multipliers scale linearly within each zone rather than jumping
    straight to the max, so a probability of 0.71 gets a much smaller
    surge than a probability of 0.99.
    """

    low_threshold: float = 0.35
    high_threshold: float = 0.70
    max_surge_multiplier: float = 1.35
    max_discount_multiplier: float = 0.80

    def multiplier_for(self, probability: float) -> float:
        probability = max(0.0, min(1.0, float(probability)))

        if probability >= self.high_threshold:
            span = 1.0 - self.high_threshold
            progress = (probability - self.high_threshold) / span if span else 1.0
            return 1.0 + progress * (self.max_surge_multiplier - 1.0)

        if probability <= self.low_threshold:
            span = self.low_threshold
            progress = (self.low_threshold - probability) / span if span else 1.0
            return 1.0 - progress * (1.0 - self.max_discount_multiplier)

        return 1.0

    def status_for(self, probability: float) -> str:
        if probability >= self.high_threshold:
            return "surge"
        if probability <= self.low_threshold:
            return "discount"
        return "neutral"

    def recommended_price(self, base_price: float, probability: float) -> dict:
        multiplier = self.multiplier_for(probability)
        return {
            "base_price": round(float(base_price), 2),
            "probability": round(float(probability), 4),
            "multiplier": round(multiplier, 4),
            "recommended_price": round(float(base_price) * multiplier, 2),
            "status": self.status_for(probability),
        }
