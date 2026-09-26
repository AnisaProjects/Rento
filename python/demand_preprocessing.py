"""
demand_preprocessing.py
------------------------
Feature engineering for the Rento demand-forecasting pipeline.

Input : a demand CSV with `date`, `hour` (and `demand` for training data).
Output: the same rows with engineered date/time features added, ready for
        train.py. No target-derived (leaky) features are created here —
        every feature is knowable in advance from the calendar alone, so
        the exact same function is safe to run on train, validation, and
        future/unseen data.

Usage:
    python demand_preprocessing.py --input train_E1GspfA.csv --output train_features.csv
    python demand_preprocessing.py --input test_6QvDdzb.csv  --output test_features.csv
"""

import argparse
import numpy as np
import pandas as pd

# ---------------------------------------------------------------------------
# Config: Nepal / Kathmandu festival calendar
# ---------------------------------------------------------------------------
#
# These are lunar/lunisolar festivals, so their Gregorian dates shift every
# year and CANNOT be derived from a formula — they must be looked up.
# Dates below are sourced from published Nepali holiday calendars
# (main observance day only; see WINDOW_DAYS below for how the surrounding
# multi-day festival period is handled).
#
# TO EXTEND: add a new year's date here. If a date is missing for a year
# present in your data, that festival is simply not flagged for that year
# (it will NOT crash) — so keep this table up to date as new data comes in.

FESTIVAL_MAIN_DAY = {
    "nepali_new_year": {  # Baisakh 1 — solar calendar, mid-April each year
        2018: "2018-04-14", 2019: "2019-04-14", 2020: "2020-04-13",
        2021: "2021-04-14", 2022: "2022-04-14",
    },
    "holi": {  # Fagu Purnima (hill/Kathmandu observance)
        2018: "2018-03-01", 2019: "2019-03-20", 2020: "2020-03-09",
        2021: "2021-03-28", 2022: "2022-03-17",
    },
    "teej": {  # Haritalika Teej
        2018: "2018-09-12", 2019: "2019-09-02", 2020: "2020-08-21",
        2021: "2021-09-09", 2022: "2022-08-30",
    },
    "dashain": {  # Vijaya Dashami — the main tika day of the 15-day Dashain festival
        2018: "2018-10-19", 2019: "2019-10-08", 2020: "2020-10-25",
        2021: "2021-10-15", 2022: "2022-10-05",
    },
    "tihar": {  # Laxmi Puja — the main day of the 5-day Tihar festival
        2018: "2018-11-07", 2019: "2019-10-27", 2020: "2020-11-15",
        2021: "2021-11-04", 2022: "2022-10-24",
    },
}

# Dashain and Tihar are multi-day festivals where demand is elevated across
# the whole period, not just the single "main day" above. Window is
# (days_before_main_day, days_after_main_day).
FESTIVAL_WINDOW_DAYS = {
    "nepali_new_year": (0, 0),
    "holi": (0, 1),       # hill-region day + terai-region day
    "teej": (0, 0),
    "dashain": (9, 5),    # Ghatasthapana (~9 days before) -> Kojagrat Purnima (~5 days after)
    "tihar": (2, 2),      # Kaag Tihar -> Bhai Tika
}


def build_festival_calendar() -> set:
    """
    Expand FESTIVAL_MAIN_DAY + FESTIVAL_WINDOW_DAYS into a flat set of every
    individual calendar date (as pandas.Timestamp) that counts as a festival
    day. Computed once and reused for fast lookups.
    """
    festival_dates = set()

    for festival, year_map in FESTIVAL_MAIN_DAY.items():
        before, after = FESTIVAL_WINDOW_DAYS.get(festival, (0, 0))

        for _, date_str in year_map.items():
            main_day = pd.Timestamp(date_str)
            window = pd.date_range(
                main_day - pd.Timedelta(days=before),
                main_day + pd.Timedelta(days=after),
                freq="D",
            )
            festival_dates.update(window)

    return festival_dates


FESTIVAL_CALENDAR = build_festival_calendar()


# ---------------------------------------------------------------------------
# Feature engineering
# ---------------------------------------------------------------------------

def add_datetime_features(df: pd.DataFrame, date_col: str = "date", hour_col: str = "hour") -> pd.DataFrame:
    """
    Add calendar/time features derived purely from `date` and `hour`.
    Every feature here is known ahead of time for any date — none of them
    use `demand` or any other target-derived statistic, so this function is
    leakage-safe to apply identically to train, validation, and future data.
    """
    df = df.copy()
    df[date_col] = pd.to_datetime(df[date_col])

    # ---- Core requested features -------------------------------------------
    df["day_of_week"] = df[date_col].dt.dayofweek  # Mon=0 ... Sun=6
    df["hour"] = df[hour_col].astype(int)

    # Nepal's weekend is Saturday only in the traditional sense, but the
    # task spec calls for Sat+Sun as weekend, so: Sat=5, Sun=6.
    df["is_weekend"] = df["day_of_week"].isin([5, 6]).astype(int)

    df["is_festival"] = df[date_col].isin(FESTIVAL_CALENDAR).astype(int)

    # ---- Additional seasonal/date features ---------------------------------
    df["month"] = df[date_col].dt.month
    df["day_of_month"] = df[date_col].dt.day
    df["day_of_year"] = df[date_col].dt.dayofyear
    df["week_of_year"] = df[date_col].dt.isocalendar().week.astype(int)
    df["quarter"] = df[date_col].dt.quarter
    df["is_month_start"] = df[date_col].dt.is_month_start.astype(int)
    df["is_month_end"] = df[date_col].dt.is_month_end.astype(int)

    # Nepal's broad climate/tourism seasons — useful because vehicle rental
    # demand tracks tourist season & weather, not just "month number".
    #   winter: Dec-Feb | spring: Mar-May | monsoon: Jun-Sep | autumn: Oct-Nov
    def season_of(month: int) -> str:
        if month in (12, 1, 2):
            return "winter"
        if month in (3, 4, 5):
            return "spring"
        if month in (6, 7, 8, 9):
            return "monsoon"
        return "autumn"

    df["season"] = df["month"].apply(season_of)

    # Cyclical encodings so the model sees hour 23 and hour 0 as adjacent
    # (a raw integer hour/day-of-week wrongly implies 23 is "far" from 0).
    df["hour_sin"] = np.sin(2 * np.pi * df["hour"] / 24)
    df["hour_cos"] = np.cos(2 * np.pi * df["hour"] / 24)
    df["dow_sin"] = np.sin(2 * np.pi * df["day_of_week"] / 7)
    df["dow_cos"] = np.cos(2 * np.pi * df["day_of_week"] / 7)
    df["month_sin"] = np.sin(2 * np.pi * df["month"] / 12)
    df["month_cos"] = np.cos(2 * np.pi * df["month"] / 12)

    return df


def main():
    parser = argparse.ArgumentParser(description="Engineer date/time features for Rento demand data.")
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--date-col", default="date")
    parser.add_argument("--hour-col", default="hour")
    args = parser.parse_args()

    raw = pd.read_csv(args.input)
    featured = add_datetime_features(raw, date_col=args.date_col, hour_col=args.hour_col)
    featured.to_csv(args.output, index=False)

    print(f"Rows: {len(featured)}")
    print(f"Festival rows flagged: {int(featured['is_festival'].sum())} "
          f"({featured['is_festival'].mean():.1%})")
    print(f"Weekend rows: {int(featured['is_weekend'].sum())} "
          f"({featured['is_weekend'].mean():.1%})")
    print(f"Saved features to {args.output}")


if __name__ == "__main__":
    main()