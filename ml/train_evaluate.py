"""
Train and evaluate machine-learning models for the FYP report (docs/report/).

Two offline experiments. They do not change the live site: Rento prices with the rule-based pricer
(ml/pricing_rules.py) because it has only a handful of real bookings. These experiments show how a
learned pricer would perform once there is enough data.

  Experiment 1 - hourly demand regression (external data, not Rento bookings)
      data   python/train_features.csv: 18,247 labelled hourly rows (2018-08-18 .. 2021-02-28), built by
             python/demand_preprocessing.py from a public hourly vehicle-rental demand dataset, with
             calendar and Nepali festival features. The unlabelled python/test_features.csv is not used.
      target demand (rentals per hour)
      split  chronological: the last 20% of the timeline is the test set; 5-fold TimeSeriesSplit
             cross-validation on the training part
      models seasonal baseline (mean per weekday x hour), Linear Regression, Random Forest,
             Gradient Boosting

  Experiment 1b - the same demand summed per day (Rento prices per day): days with >= 20 recorded hours,
             chronological 80/20 split, 5-fold TimeSeriesSplit; weekday-mean baseline

  Experiment 2 - daily price regression (publicly listed prices, collected by scraping)
      data   python/clean_vehicles.csv: 1,640 Sajilo Rental listings, cleaned by python/preprocessing.py
      target scaling_1day (the day price the existing PyTorch model predicts), also base_price_per_hour
             and scaling_8hr for the MLP
      split  the existing PyTorch model's own 70/15/15 split (random_state 42), so every model is scored
             on the same 246 held-out rows (python/pricing_test_split.csv); plus 5-fold GroupKFold by
             vehicle model, to measure how well prices are predicted for models never seen in training
      models median baseline, Linear Regression, Random Forest, Gradient Boosting, and the existing
             PyTorch MLP (python/pricing_model.pt, loaded as saved, not retrained)

Usage (from the project root):
    ml/.venv/bin/pip install -r ml/requirements-dev.txt
    ml/.venv/bin/python ml/train_evaluate.py [--experiment demand|price|all]
Output: docs/report/figures/*.png and docs/report/results/ml_*.json
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import time
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402
from sklearn.compose import ColumnTransformer  # noqa: E402
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor  # noqa: E402
from sklearn.linear_model import LinearRegression  # noqa: E402
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score  # noqa: E402
from sklearn.model_selection import GroupKFold, TimeSeriesSplit, train_test_split  # noqa: E402
from sklearn.pipeline import Pipeline  # noqa: E402
from sklearn.preprocessing import OneHotEncoder, StandardScaler  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
PY = ROOT / "python"
FIG = ROOT / "docs" / "report" / "figures"
RES = ROOT / "docs" / "report" / "results"
SEED = 42
ORANGE, INK, GREY, BLUE = "#ff5a36", "#101114", "#9497a6", "#2f7fd1"


def metrics(y_true, y_pred) -> dict:
    return {"MAE": float(mean_absolute_error(y_true, y_pred)),
            "RMSE": float(np.sqrt(mean_squared_error(y_true, y_pred))),
            "R2": float(r2_score(y_true, y_pred))}


def style():
    plt.rcParams.update({"font.family": "DejaVu Sans", "font.size": 10, "axes.spines.top": False,
                         "axes.spines.right": False, "axes.titleweight": "bold", "figure.dpi": 150})


def bar_compare(results: dict, title: str, path: Path, unit: str):
    names = list(results)
    fig, axes = plt.subplots(1, 3, figsize=(11, 3.6))
    for ax, key in zip(axes, ["MAE", "RMSE", "R2"]):
        vals = [results[n][key] for n in names]
        colours = [ORANGE if v == (max(vals) if key == "R2" else min(vals)) else GREY for v in vals]
        ax.bar(range(len(names)), vals, color=colours)
        ax.set_xticks(range(len(names)), names, rotation=25, ha="right")
        ax.set_title(key + ("" if key == "R2" else f" ({unit})"))
        for i, v in enumerate(vals):
            ax.text(i, v, f"{v:.2f}" if key == "R2" else f"{v:,.1f}", ha="center", va="bottom", fontsize=8)
    fig.suptitle(title, fontweight="bold")
    fig.tight_layout()
    fig.savefig(path)
    plt.close(fig)


# ---------------------------------------------------------------- Experiment 1: demand
DEMAND_NUMERIC = ["hour", "day_of_week", "is_weekend", "is_festival", "month", "day_of_month", "day_of_year",
                  "week_of_year", "quarter", "is_month_start", "is_month_end",
                  "hour_sin", "hour_cos", "dow_sin", "dow_cos", "month_sin", "month_cos"]
DEMAND_CATEGORICAL = ["season"]


def demand_models():
    pre_lin = ColumnTransformer([("num", StandardScaler(), DEMAND_NUMERIC),
                                 ("cat", OneHotEncoder(handle_unknown="ignore"), DEMAND_CATEGORICAL)])
    pre_tree = ColumnTransformer([("num", "passthrough", DEMAND_NUMERIC),
                                  ("cat", OneHotEncoder(handle_unknown="ignore"), DEMAND_CATEGORICAL)])
    return {
        "Linear Regression": Pipeline([("pre", pre_lin), ("m", LinearRegression())]),
        "Random Forest": Pipeline([("pre", pre_tree), ("m", RandomForestRegressor(
            n_estimators=300, min_samples_leaf=3, random_state=SEED, n_jobs=-1))]),
        "Gradient Boosting": Pipeline([("pre", pre_tree), ("m", GradientBoostingRegressor(
            n_estimators=400, learning_rate=0.05, max_depth=4, subsample=0.9, random_state=SEED))]),
    }


def seasonal_baseline(train: pd.DataFrame, test: pd.DataFrame) -> np.ndarray:
    table = train.groupby(["day_of_week", "hour"])["demand"].mean()
    return test.apply(lambda r: table.get((r["day_of_week"], r["hour"]), train["demand"].mean()), axis=1).to_numpy()


def experiment_demand() -> dict:
    df = pd.read_csv(PY / "train_features.csv", parse_dates=["date"]).sort_values(["date", "hour"]).reset_index(drop=True)
    cut = int(len(df) * 0.8)
    train, test = df.iloc[:cut], df.iloc[cut:]
    X_tr, y_tr, X_te, y_te = train[DEMAND_NUMERIC + DEMAND_CATEGORICAL], train["demand"], test[DEMAND_NUMERIC + DEMAND_CATEGORICAL], test["demand"]
    out = {"dataset": {"rows": len(df), "train_rows": len(train), "test_rows": len(test),
                       "train_period": [str(train["date"].min().date()), str(train["date"].max().date())],
                       "test_period": [str(test["date"].min().date()), str(test["date"].max().date())],
                       "features": DEMAND_NUMERIC + DEMAND_CATEGORICAL, "target": "demand",
                       "target_stats": {k: float(v) for k, v in df["demand"].describe().items()},
                       "festival_hours": int(df["is_festival"].sum())},
           "test": {}, "cv": {}, "fit_seconds": {}}
    out["test"]["Seasonal baseline"] = metrics(y_te, seasonal_baseline(train, test))
    preds = {}
    tscv = TimeSeriesSplit(n_splits=5)
    for name, model in demand_models().items():
        folds = []
        for tr_idx, va_idx in tscv.split(X_tr):
            m = demand_models()[name]
            m.fit(X_tr.iloc[tr_idx], y_tr.iloc[tr_idx])
            folds.append(metrics(y_tr.iloc[va_idx], m.predict(X_tr.iloc[va_idx])))
        out["cv"][name] = {k: {"mean": float(np.mean([f[k] for f in folds])), "std": float(np.std([f[k] for f in folds]))} for k in folds[0]}
        t0 = time.perf_counter()
        model.fit(X_tr, y_tr)
        out["fit_seconds"][name] = round(time.perf_counter() - t0, 2)
        preds[name] = model.predict(X_te)
        out["test"][name] = metrics(y_te, preds[name])
        if name == "Gradient Boosting":
            gb = model
    best = min((n for n in out["test"] if n != "Seasonal baseline"), key=lambda n: out["test"][n]["RMSE"])
    out["best_model"] = best

    # Figures
    bar_compare(out["test"], "Experiment 1: hourly demand, chronological test set", FIG / "ml_demand_model_comparison.png", "rentals/h")
    week = test.iloc[: 24 * 14]
    fig, ax = plt.subplots(figsize=(11, 3.6))
    ts = week["date"] + pd.to_timedelta(week["hour"], unit="h")
    ax.plot(ts, week["demand"], color=INK, lw=1.2, label="Actual")
    ax.plot(ts, preds[best][: len(week)], color=ORANGE, lw=1.2, label=f"Predicted ({best})")
    ax.set_title("Actual vs predicted hourly demand, first two weeks of the test period")
    ax.set_ylabel("rentals per hour")
    ax.legend(frameon=False)
    fig.tight_layout(); fig.savefig(FIG / "ml_demand_actual_vs_predicted.png"); plt.close(fig)
    fig, ax = plt.subplots(figsize=(4.8, 4.4))
    ax.scatter(y_te, preds[best], s=4, alpha=0.3, color=ORANGE)
    lim = [0, max(y_te.max(), preds[best].max())]
    ax.plot(lim, lim, color=INK, lw=1)
    ax.set_xlabel("actual demand"); ax.set_ylabel("predicted demand"); ax.set_title(f"{best}: test set")
    fig.tight_layout(); fig.savefig(FIG / "ml_demand_scatter.png"); plt.close(fig)
    names = gb.named_steps["pre"].get_feature_names_out()
    imp = pd.Series(gb.named_steps["m"].feature_importances_, index=[n.split("__", 1)[1] for n in names]).sort_values()
    out["feature_importance_gb"] = {k: float(v) for k, v in imp.sort_values(ascending=False).items()}
    fig, ax = plt.subplots(figsize=(6, 4.6))
    imp.tail(12).plot.barh(ax=ax, color=ORANGE)
    ax.set_title("Gradient Boosting feature importance (top 12)")
    fig.tight_layout(); fig.savefig(FIG / "ml_demand_feature_importance.png"); plt.close(fig)
    fig, ax = plt.subplots(figsize=(6.4, 3.6))
    cvn = list(out["cv"])
    ax.bar(cvn, [out["cv"][n]["RMSE"]["mean"] for n in cvn], yerr=[out["cv"][n]["RMSE"]["std"] for n in cvn],
           color=[ORANGE if n == best else GREY for n in cvn], capsize=4)
    ax.set_title("5-fold time-series cross-validation: RMSE (mean ± sd)"); ax.set_ylabel("rentals/h")
    fig.tight_layout(); fig.savefig(FIG / "ml_demand_cv.png"); plt.close(fig)
    return out


DAILY_NUMERIC = ["day_of_week", "is_weekend", "is_festival", "month", "day_of_month", "day_of_year",
                 "week_of_year", "quarter", "is_month_start", "is_month_end", "dow_sin", "dow_cos", "month_sin", "month_cos"]


def experiment_demand_daily() -> dict:
    """Experiment 1b: the same data summed per day, which is the granularity Rento prices at."""
    df = pd.read_csv(PY / "train_features.csv", parse_dates=["date"])
    hours = df.groupby("date")["hour"].count()
    daily = df.groupby("date").agg({**{c: "first" for c in DAILY_NUMERIC + ["season"]}, "is_festival": "max", "demand": "sum"})
    daily = daily[hours.reindex(daily.index) >= 20].reset_index()  # days with (almost) all hours recorded
    daily = daily.sort_values("date").reset_index(drop=True)
    cut = int(len(daily) * 0.8)
    train, test = daily.iloc[:cut], daily.iloc[cut:]
    cols = DAILY_NUMERIC + ["season"]
    pre_lin = ColumnTransformer([("num", StandardScaler(), DAILY_NUMERIC), ("cat", OneHotEncoder(handle_unknown="ignore"), ["season"])])
    pre_tree = ColumnTransformer([("num", "passthrough", DAILY_NUMERIC), ("cat", OneHotEncoder(handle_unknown="ignore"), ["season"])])
    models = {
        "Linear Regression": lambda: Pipeline([("pre", pre_lin), ("m", LinearRegression())]),
        "Random Forest": lambda: Pipeline([("pre", pre_tree), ("m", RandomForestRegressor(n_estimators=400, min_samples_leaf=3, random_state=SEED, n_jobs=-1))]),
        "Gradient Boosting": lambda: Pipeline([("pre", pre_tree), ("m", GradientBoostingRegressor(n_estimators=300, learning_rate=0.03, max_depth=3, subsample=0.9, random_state=SEED))]),
    }
    table = train.groupby("day_of_week")["demand"].mean()
    out = {"dataset": {"days": len(daily), "train_days": len(train), "test_days": len(test),
                       "train_period": [str(train["date"].min().date()), str(train["date"].max().date())],
                       "test_period": [str(test["date"].min().date()), str(test["date"].max().date())],
                       "rule": "days with at least 20 of 24 hours recorded; demand summed per day; festival = any festival hour",
                       "target_stats": {k: float(v) for k, v in daily["demand"].describe().items()}},
           "test": {"Weekday baseline": metrics(test["demand"], test["day_of_week"].map(table).to_numpy())}, "cv": {}}
    preds = {}
    for name, make in models.items():
        folds = []
        for tr, va in TimeSeriesSplit(n_splits=5).split(train):
            m = make().fit(train.iloc[tr][cols], train.iloc[tr]["demand"])
            folds.append(metrics(train.iloc[va]["demand"], m.predict(train.iloc[va][cols])))
        out["cv"][name] = {k: {"mean": float(np.mean([f[k] for f in folds])), "std": float(np.std([f[k] for f in folds]))} for k in folds[0]}
        m = make().fit(train[cols], train["demand"])
        preds[name] = m.predict(test[cols])
        out["test"][name] = metrics(test["demand"], preds[name])
    best = min(preds, key=lambda n: out["test"][n]["RMSE"])
    out["best_model"] = best
    bar_compare(out["test"], "Experiment 1b: daily demand, chronological test set", FIG / "ml_demand_daily_model_comparison.png", "rentals/day")
    fig, ax = plt.subplots(figsize=(11, 3.6))
    # Missing days (fewer than 20 recorded hours) break the lines instead of being bridged.
    full = pd.date_range(test["date"].min(), test["date"].max(), freq="D")
    act = test.set_index("date")["demand"].reindex(full)
    pred = pd.Series(preds[best], index=test["date"]).reindex(full)
    ax.plot(full, act, color=INK, lw=1.2, label="Actual")
    ax.plot(full, pred, color=ORANGE, lw=1.2, label=f"Predicted ({best})")
    fest = test[test["is_festival"] == 1]
    ax.scatter(fest["date"], fest["demand"], s=12, color=BLUE, zorder=3, label="Festival day")
    ax.set_title("Actual vs predicted daily demand, test period"); ax.set_ylabel("rentals per day"); ax.legend(frameon=False)
    fig.tight_layout(); fig.savefig(FIG / "ml_demand_daily_actual_vs_predicted.png"); plt.close(fig)
    return out


# ---------------------------------------------------------------- Experiment 2: price
PRICE_TARGETS = ["base_price_per_hour", "scaling_8hr", "scaling_1day"]
PRICE_NUMERIC = ["max_km_per_hour", "fuel_cost_per_km"]
PRICE_CATEGORICAL = ["vehicle_name", "manufacturer"]


def price_models():
    def pre(scale):
        return ColumnTransformer([("num", StandardScaler() if scale else "passthrough", PRICE_NUMERIC),
                                  ("cat", OneHotEncoder(handle_unknown="ignore", min_frequency=2), PRICE_CATEGORICAL)])
    return {
        "Linear Regression": Pipeline([("pre", pre(True)), ("m", LinearRegression())]),
        "Random Forest": Pipeline([("pre", pre(False)), ("m", RandomForestRegressor(
            n_estimators=300, min_samples_leaf=2, random_state=SEED, n_jobs=-1))]),
        "Gradient Boosting": Pipeline([("pre", pre(False)), ("m", GradientBoostingRegressor(
            n_estimators=300, learning_rate=0.05, max_depth=3, random_state=SEED))]),
    }


def load_mlp():
    spec = importlib.util.spec_from_file_location("rento_mlp_test", PY / "test.py")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    model, ckpt = mod.load_checkpoint(str(PY / "pricing_model.pt"))
    return mod, model, ckpt


def experiment_price() -> dict:
    df = pd.read_csv(PY / "clean_vehicles.csv")
    train, temp = train_test_split(df, test_size=0.30, random_state=SEED)  # the MLP's own split (python/train.py)
    val, test = train_test_split(temp, test_size=0.50, random_state=SEED)
    saved_test = pd.read_csv(PY / "pricing_test_split.csv")
    same_split = len(saved_test) == len(test) and np.allclose(saved_test[PRICE_TARGETS].to_numpy(), test[PRICE_TARGETS].to_numpy())
    target = "scaling_1day"
    X = PRICE_NUMERIC + PRICE_CATEGORICAL
    out = {"dataset": {"rows": len(df), "train_rows": len(train), "validation_rows": len(val), "test_rows": len(test),
                       "split_matches_saved_mlp_test_split": bool(same_split), "features": X, "target": target,
                       "distinct_vehicle_models": int(df["vehicle_name"].nunique()),
                       "distinct_manufacturers": int(df["manufacturer"].nunique()),
                       "distinct_target_values": int(df[target].nunique()),
                       "target_stats": {k: float(v) for k, v in df[target].describe().items()},
                       "unseen_models_in_test": int((~test["vehicle_name"].isin(train["vehicle_name"])).sum())},
           "test": {}, "mlp_all_targets": {}, "group_cv": {}}
    out["test"]["Median baseline"] = metrics(test[target], np.full(len(test), train[target].median()))
    preds = {}
    for name, model in price_models().items():
        model.fit(train[X], train[target])
        preds[name] = model.predict(test[X])
        out["test"][name] = metrics(test[target], preds[name])
    # Existing PyTorch MLP, loaded as saved.
    import torch
    mod, mlp, ckpt = load_mlp()
    veh = torch.tensor(mod.encode_column(test["vehicle_name"], ckpt["vehicle_encoder"]), dtype=torch.long)
    mfr = torch.tensor(mod.encode_column(test["manufacturer"], ckpt["mfr_encoder"]), dtype=torch.long)
    num = torch.tensor(mod.apply_standard_scaler(test[ckpt["numeric_cols"]].to_numpy(dtype=float), ckpt["numeric_scaler"]), dtype=torch.float32)
    with torch.no_grad():
        mlp_pred = mod.inverse_standard_scaler(mlp(veh, mfr, num).numpy(), ckpt["target_scaler"])
    cols = list(ckpt["target_cols"])
    preds["PyTorch MLP (existing)"] = mlp_pred[:, cols.index(target)]
    out["test"]["PyTorch MLP (existing)"] = metrics(test[target], preds["PyTorch MLP (existing)"])
    for i, c in enumerate(cols):
        out["mlp_all_targets"][c] = metrics(test[c], mlp_pred[:, i])
    # Unseen vehicle models: 5-fold GroupKFold by vehicle_name (scikit-learn models).
    gkf = GroupKFold(n_splits=5)
    for name in price_models():
        folds = []
        for tr, va in gkf.split(df, groups=df["vehicle_name"]):
            m = price_models()[name]
            m.fit(df.iloc[tr][X], df.iloc[tr][target])
            folds.append(metrics(df.iloc[va][target], m.predict(df.iloc[va][X])))
        out["group_cv"][name] = {k: {"mean": float(np.mean([f[k] for f in folds])), "std": float(np.std([f[k] for f in folds]))} for k in folds[0]}
    folds = []
    for tr, va in gkf.split(df, groups=df["vehicle_name"]):
        folds.append(metrics(df.iloc[va][target], np.full(len(va), df.iloc[tr][target].median())))
    out["group_cv"]["Median baseline"] = {k: {"mean": float(np.mean([f[k] for f in folds])), "std": float(np.std([f[k] for f in folds]))} for k in folds[0]}
    best = min((n for n in out["test"] if n != "Median baseline"), key=lambda n: out["test"][n]["RMSE"])
    out["best_model"] = best

    bar_compare(out["test"], "Experiment 2: day price (Rs), held-out test set (246 listings)", FIG / "ml_price_model_comparison.png", "Rs")
    fig, ax = plt.subplots(figsize=(4.8, 4.4))
    jitter = np.random.default_rng(SEED).normal(0, 40, len(test))
    ax.scatter(test[target] + jitter, preds[best], s=10, alpha=0.5, color=ORANGE)
    lim = [test[target].min() - 500, test[target].max() + 500]
    ax.plot(lim, lim, color=INK, lw=1)
    ax.set_xlabel("actual day price (Rs, jittered)"); ax.set_ylabel("predicted (Rs)"); ax.set_title(f"{best}: test set")
    fig.tight_layout(); fig.savefig(FIG / "ml_price_actual_vs_predicted.png"); plt.close(fig)
    gbm = price_models()["Gradient Boosting"].fit(train[X], train[target])
    names = gbm.named_steps["pre"].get_feature_names_out()
    imp = pd.Series(gbm.named_steps["m"].feature_importances_, index=[n.split("__", 1)[1] for n in names]).sort_values()
    out["feature_importance_gb"] = {k: float(v) for k, v in imp.sort_values(ascending=False).head(15).items()}
    fig, ax = plt.subplots(figsize=(6, 4.6))
    imp.tail(12).plot.barh(ax=ax, color=ORANGE)
    ax.set_title("Gradient Boosting feature importance, day price (top 12)")
    fig.tight_layout(); fig.savefig(FIG / "ml_price_feature_importance.png"); plt.close(fig)
    fig, ax = plt.subplots(figsize=(6.4, 3.6))
    gn = list(out["group_cv"])
    ax.bar(gn, [out["group_cv"][n]["MAE"]["mean"] for n in gn], yerr=[out["group_cv"][n]["MAE"]["std"] for n in gn],
           color=[ORANGE if n == min(gn, key=lambda k: out["group_cv"][k]["MAE"]["mean"]) else GREY for n in gn], capsize=4)
    ax.set_title("Unseen vehicle models (GroupKFold): MAE (mean ± sd)"); ax.set_ylabel("Rs")
    plt.setp(ax.get_xticklabels(), rotation=15, ha="right")
    fig.tight_layout(); fig.savefig(FIG / "ml_price_group_cv.png"); plt.close(fig)
    return out


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--experiment", choices=["demand", "price", "all"], default="all")
    a = p.parse_args()
    FIG.mkdir(parents=True, exist_ok=True)
    RES.mkdir(parents=True, exist_ok=True)
    style()
    for name, fn in [("demand", experiment_demand), ("demand_daily", experiment_demand_daily), ("price", experiment_price)]:
        if a.experiment in (name.split("_")[0], "all"):
            t0 = time.perf_counter()
            res = fn()
            res["seconds"] = round(time.perf_counter() - t0, 1)
            res["versions"] = {"numpy": np.__version__, "pandas": pd.__version__, "scikit-learn": __import__("sklearn").__version__}
            (RES / f"ml_{name}.json").write_text(json.dumps(res, indent=2))
            print(f"== {name} ({res['seconds']} s)")
            for m, v in res["test"].items():
                print(f"  {m:24s} MAE {v['MAE']:10.2f}  RMSE {v['RMSE']:10.2f}  R2 {v['R2']:.3f}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
