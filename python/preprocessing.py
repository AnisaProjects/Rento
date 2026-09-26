"""
preprocessing.py
-----------------
Data cleaning & feature engineering stage for the Rento dynamic-pricing pipeline.

Input : raw vehicle listing CSV (scraped data — messy/misaligned columns,
         zero/negative/missing prices, and outlier km values).
Output: clean_vehicles.csv with engineered pricing features ready for
         train.py / test.py.

Usage:
    python preprocessing.py --input vehicles_raw.csv --output clean_vehicles.csv
"""

import argparse
import math
import numpy as np
import pandas as pd

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

# Canonical brand names. If one of these already appears inside the
# vehicle_name (e.g. "Hyundai Creta 2018", "mahindra mahindra scorpio 2016"),
# we use it directly rather than relying on the model lookup below.
CANONICAL_BRANDS = [
    "Toyota", "Suzuki", "Maruti", "Hyundai", "Mahindra", "Tata", "Nissan",
    "Kia", "Ford", "Honda", "Mitsubishi", "Isuzu", "BMW", "Skoda",
    "Volkswagen", "Jeep", "Chevrolet", "Citroen", "Datsun", "Renault",
    "BYD", "MG", "Chery", "Omoda", "NETA", "Seres", "Foton", "ORA",
    "Dongfeng", "King Long", "Fiat",
]

# Model-name -> brand lookup for rows where neither `manufacturer` nor the
# vehicle name itself gives the brand away (e.g. "ALTO", "Scorpio",
# "WAGONR"). Keys are matched as case-insensitive substrings of
# vehicle_name, longest key first, so extend this table as new models
# show up in the raw data rather than guessing.
MODEL_TO_BRAND = {
    # Suzuki
    "wagonr": "Suzuki", "wagoner": "Suzuki", "alto": "Suzuki",
    "swift": "Suzuki", "baleno": "Suzuki", "dzire": "Suzuki",
    "dzier": "Suzuki", "celerio": "Suzuki", "ignis": "Suzuki",
    "brezza": "Suzuki", "s-cross": "Suzuki", "s- presso": "Suzuki",
    "s-presso": "Suzuki", "eeco": "Suzuki", "super carry": "Suzuki",
    "ciaz": "Suzuki", "sx4": "Suzuki",
    # Hyundai
    "grand i10": "Hyundai", "accent": "Hyundai", "creta": "Hyundai",
    "i10": "Hyundai", "i20": "Hyundai", "santro": "Hyundai",
    "sonata": "Hyundai", "tucson": "Hyundai", "venue": "Hyundai",
    "xcent": "Hyundai", "kona": "Hyundai", "nexo": "Hyundai",
    # Kia
    "sorento": "Kia", "sonet": "Kia", "seltos": "Kia", "sportage": "Kia",
    "picanto": "Kia", "grand carnival": "Kia", "shephia": "Kia",
    # Ford
    "aspire": "Ford", "ecosport": "Ford", "figo": "Ford",
    "endeavour": "Ford", "ranger": "Ford",
    # Mahindra
    "scorpio": "Mahindra", "bolero": "Mahindra", "thar": "Mahindra",
    "xuv": "Mahindra", "tuv": "Mahindra", "marazzo": "Mahindra",
    # Tata
    "1512": "Tata", "207 di": "Tata", "sumo": "Tata", "indica": "Tata",
    "indigo": "Tata", "manza": "Tata", "nexon": "Tata", "safari": "Tata",
    "tiago": "Tata", "tigor": "Tata", "yodha": "Tata", "zest": "Tata",
    "harrier": "Tata", "altroz": "Tata", "punch": "Tata", "winger": "Tata",
    "xenon": "Tata", "freestyle": "Tata",
    # Toyota
    "corola": "Toyota", "corolla": "Toyota", "etios": "Toyota",
    "fortuner": "Toyota", "hilux": "Toyota", "innova": "Toyota",
    "rav-4": "Toyota", "rav4": "Toyota", "avanza": "Toyota",
    "toyata": "Toyota", "hiace": "Toyota", "land cruiser": "Toyota",
    # Nissan
    "micra": "Nissan", "sunny": "Nissan", "gt-r": "Nissan",
    "kicks": "Nissan", "leaf": "Nissan", "magnite": "Nissan",
    "patrol": "Nissan",
    # Renault
    "duster": "Renault", "kiger": "Renault", "kwid": "Renault",
    "triber": "Renault", "capture": "Renault",
    # Jeep
    "compass": "Jeep", "wrangler": "Jeep", "grand cherokee": "Jeep",
    # Honda
    "amaze": "Honda", "brv": "Honda", "crv": "Honda", "city": "Honda",
    "wrv": "Honda",
    # Isuzu
    "d-max": "Isuzu", "flat deck": "Isuzu", "highlander": "Isuzu",
    "v-cross": "Isuzu",
    # BYD
    "atto 3": "BYD", "dolphin": "BYD", "e-6": "BYD",
    # Mitsubishi
    "eclipse cross": "Mitsubishi", "pajero": "Mitsubishi",
    # Skoda
    "karoq": "Skoda", "kodiaq": "Skoda", "kushaq": "Skoda",
    "rapid": "Skoda", "superb": "Skoda",
    # Volkswagen
    "polo": "Volkswagen", "tiguan": "Volkswagen", "vento": "Volkswagen",
    # Chevrolet
    "aveo": "Chevrolet", "spark": "Chevrolet", "tavera": "Chevrolet",
    # Citroen
    "c - 3": "Citroen", "ec - 3": "Citroen",
    # Datsun
    "new go": "Datsun", "redi go": "Datsun",
    # Chery / Omoda / MG / NETA / Seres / ORA
    "t11": "Chery", "230t": "Omoda", "mg 5": "MG", "mg zs": "MG",
    "ora o3": "ORA",
    # Other niche/commercial brands
    "linea": "Fiat", "kyc": "KYC", "king long": "King Long",
    "dongfeng": "Dongfeng", "foton": "Foton",
}

# Canonical casing lookup so a given value like "BMW", "bmw", or "Bmw" all
# collapse to the same category instead of splitting into duplicates.
_BRAND_CANONICAL_CASE = {b.lower(): b for b in CANONICAL_BRANDS}
_BRAND_CANONICAL_CASE.update({"kyc": "KYC", "king long": "King Long"})

# A rental of 4 hours on a city vehicle realistically covers on the order of
# tens of km, not thousands. Values above this are treated as data-entry
# errors (e.g. a stray "5500" that belongs in a different column).
MAX_KM_4HR_SANITY_CAP = 300

# Minimum plausible fuel cost per km (NPR). Anything <= 0 or absurdly high
# is treated as invalid.
FUEL_COST_MIN, FUEL_COST_MAX = 1.0, 100.0


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def infer_manufacturer(name: str, manufacturer):
    """
    Fill missing/blank manufacturer in three passes:
      1. Use the given value if present.
      2. Look for a canonical brand name already sitting inside vehicle_name
         (handles "Hyundai Creta 2018", "mahindra mahindra scorpio 2016").
      3. Fall back to the model -> brand lookup table for bare model names
         ("ALTO", "Scorpio", "WAGONR").
      4. "Unknown" if nothing matches.
    """
    if isinstance(manufacturer, str) and manufacturer.strip():
        cleaned = manufacturer.strip()
        # Prefer the canonical casing (e.g. raw "NETA"/"BYD"/"BMW" should
        # stay as acronyms, not collapse to "Neta"/"Byd"/"Bmw" via .title()).
        return _BRAND_CANONICAL_CASE.get(cleaned.lower(), cleaned.title())

    if not isinstance(name, str) or not name.strip():
        return "Unknown"

    lowered = name.lower()

    for brand in CANONICAL_BRANDS:
        if brand.lower() in lowered:
            return "Suzuki" if brand == "Maruti" else brand

    # Longest keys first so "grand i10" wins over the bare "i10".
    for key in sorted(MODEL_TO_BRAND, key=len, reverse=True):
        if key in lowered:
            return MODEL_TO_BRAND[key]

    return "Unknown"


def to_numeric(series: pd.Series) -> pd.Series:
    """Coerce to numeric, turning anything unparsable into NaN."""
    return pd.to_numeric(series, errors="coerce")


def mark_invalid_as_nan(series: pd.Series, min_valid: float = 0.0) -> pd.Series:
    """
    Treat missing, non-numeric, zero, negative (or below `min_valid`)
    values as invalid and convert them to NaN so they can be imputed.
    """
    series = to_numeric(series)
    series = series.where(series > min_valid, np.nan)
    return series


def cap_outliers_iqr(series: pd.Series, hard_cap: float = None) -> pd.Series:
    """
    Flag statistical outliers with the IQR rule AND (optionally) a hard
    domain sanity cap, then null them out for later imputation.
    """
    series = to_numeric(series)

    q1, q3 = series.quantile(0.25), series.quantile(0.75)
    iqr = q3 - q1
    lower, upper = q1 - 1.5 * iqr, q3 + 1.5 * iqr

    is_outlier = (series < lower) | (series > upper)
    if hard_cap is not None:
        is_outlier = is_outlier | (series > hard_cap)

    return series.where(~is_outlier, np.nan)


def impute_grouped(series: pd.Series, group: pd.Series) -> pd.Series:
    """
    Fill NaNs with the median for that row's manufacturer group; fall back
    to the global median for groups that have no valid values at all.
    """
    global_median = series.median()
    grouped_median = series.groupby(group).transform("median")
    filled = series.fillna(grouped_median)
    filled = filled.fillna(global_median)
    return filled


def round_up_to_multiple(value: float, multiple: int = 50) -> float:
    """ceil(value / multiple) * multiple  ->  333 -> 350, 400 -> 400."""
    if pd.isna(value):
        return np.nan
    return math.ceil(value / multiple) * multiple


# ---------------------------------------------------------------------------
# Main pipeline
# ---------------------------------------------------------------------------

def preprocess(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()

    # ---- 1. Keep the columns we actually need -----------------------------
    keep = ["vehicle_name", "manufacturer", "price_4hr", "price_8hr",
            "price_1day", "max_km_4hr", "fuel_cost_per_km"]
    df = df[[c for c in keep if c in df.columns]]

    df["vehicle_name"] = df["vehicle_name"].astype(str).str.strip()

    # ---- 2. Manufacturer: fill blanks from name lookup ---------------------
    df["manufacturer"] = [
        infer_manufacturer(n, m)
        for n, m in zip(df["vehicle_name"], df.get("manufacturer"))
    ]

    # ---- 3. Prices: zero / negative / missing / non-numeric -> impute -----
    for col in ["price_4hr", "price_8hr", "price_1day"]:
        df[col] = mark_invalid_as_nan(df[col], min_valid=0.0)
        df[col] = impute_grouped(df[col], df["manufacturer"])

    # ---- 4. max_km_4hr: outlier correction ---------------------------------
    df["max_km_4hr"] = cap_outliers_iqr(df["max_km_4hr"], hard_cap=MAX_KM_4HR_SANITY_CAP)
    df["max_km_4hr"] = impute_grouped(df["max_km_4hr"], df["manufacturer"])

    # ---- 5. fuel_cost_per_km: sanity range ---------------------------------
    if "fuel_cost_per_km" in df.columns:
        fc = to_numeric(df["fuel_cost_per_km"])
        fc = fc.where((fc >= FUEL_COST_MIN) & (fc <= FUEL_COST_MAX), np.nan)
        df["fuel_cost_per_km"] = impute_grouped(fc, df["manufacturer"])

    # ---- 6. Core derived features ------------------------------------------
    df["max_km_per_hour"] = df["max_km_4hr"] / 4
    df["price_per_hour"] = df["price_4hr"] / 4
    df["base_price_per_hour"] = df["price_per_hour"].apply(round_up_to_multiple)

    # ---- 7. Longer-rental scaling: learned discount curve, not a flat multiplier ----
    # Real listings show the *effective hourly rate* drops the longer you
    # rent (bulk discount), e.g. an 8hr block prices out to ~65-70% of the
    # 4hr hourly rate, and a full day to ~25-30% of it. We learn these
    # discount ratios from the cleaned data itself instead of hardcoding
    # 8x / 24x, so the pipeline adapts if pricing patterns shift.
    valid = df["price_4hr"] > 0
    ratio_8hr = ((df.loc[valid, "price_8hr"] / 8) / (df.loc[valid, "price_4hr"] / 4)).median()
    ratio_1day = ((df.loc[valid, "price_1day"] / 24) / (df.loc[valid, "price_4hr"] / 4)).median()

    # Guard rails in case a tiny/degenerate dataset produces a bad ratio.
    ratio_8hr = np.clip(ratio_8hr, 0.5, 0.9) if not pd.isna(ratio_8hr) else 0.7
    ratio_1day = np.clip(ratio_1day, 0.15, 0.4) if not pd.isna(ratio_1day) else 0.27

    df["scaling_8hr"] = (df["base_price_per_hour"] * 8 * ratio_8hr).round(-1)   # nearest 10
    df["scaling_1day"] = (df["base_price_per_hour"] * 24 * ratio_1day).round(-1)

    df.attrs["ratio_8hr"] = ratio_8hr
    df.attrs["ratio_1day"] = ratio_1day

    return df


def main():
    parser = argparse.ArgumentParser(description="Clean Rento vehicle pricing data.")
    parser.add_argument("--input", default="sajilo_vehicle_dataset.csv")
    parser.add_argument("--output", default="clean_vehicles.csv")
    args = parser.parse_args()

    raw = pd.read_csv(args.input)
    clean = preprocess(raw)

    clean.to_csv(args.output, index=False)

    print(f"Rows in:  {len(raw)}")
    print(f"Rows out: {len(clean)}")
    print(f"Learned scaling ratios -> 8hr: {clean.attrs['ratio_8hr']:.3f}, "
          f"1day: {clean.attrs['ratio_1day']:.3f}")
    print(f"Saved cleaned data to {args.output}")


if __name__ == "__main__":
    main()