# Machine-learning evaluation results

Produced by `ml/train_evaluate.py` (Python 3.12.14, scikit-learn 1.9.1, pandas 3.0.6,
torch 2.14.0), random seed 42. Raw numbers: `ml_demand.json`, `ml_demand_daily.json`, `ml_price.json`. Charts: `../figures/ml_*.png`.

**Context.** The live site prices with the rule-based pricer (`ml/pricing_rules.py`), because Rento has only
4 real bookings (2 confirmed). These offline experiments use external data to show how a learned
model would perform once enough bookings exist. Neither dataset is Rento booking data.

## Experiment 1: hourly vehicle-rental demand (external dataset)

18,247 labelled hourly rows. Chronological split: train 2018-08-18 to
2020-08-12 (14,597 rows), test 2020-08-12 to
2021-02-28 (3,650 rows). The features are calendar and festival
features only (known in advance), because Rento prices up to 60 days ahead. Mean demand is
74.0 per hour (sd 41.7).

**Test set**

| Model | MAE | RMSE | R² |
|---|---|---|---|
| Seasonal baseline | 30.95 | 39.60 | 0.228 |
| Linear Regression | 31.39 | 40.22 | 0.203 |
| Random Forest | 30.57 | 38.48 | 0.271 |
| Gradient Boosting | 30.46 | 38.09 | 0.285 |

**5-fold time-series cross-validation (training period)**

| Model | MAE (mean ± sd) | RMSE (mean ± sd) | R² (mean ± sd) |
|---|---|---|---|
| Linear Regression | 66.2 ± 65.9 | 74.7 ± 65.2 | -7.106 ± 14.011 |
| Random Forest | 29.5 ± 1.7 | 36.8 ± 3.7 | 0.124 ± 0.123 |
| Gradient Boosting | 30.0 ± 2.4 | 37.5 ± 4.0 | 0.070 ± 0.262 |

Best: **Gradient Boosting**. Top features: day_of_year, hour, hour_cos, dow_sin, day_of_week.

## Experiment 1b: the same demand summed per day

654 days with at least 20 of 24 hours recorded. Train 2018-09-28 to
2020-09-09 (523 days), test 2020-09-11 to
2021-02-28 (131 days). Mean 1662 rentals per day.

| Model | MAE | RMSE | R² |
|---|---|---|---|
| Weekday baseline | 267.45 | 430.65 | 0.114 |
| Linear Regression | 307.72 | 407.01 | 0.209 |
| Random Forest | 251.25 | 327.28 | 0.489 |
| Gradient Boosting | 269.61 | 347.29 | 0.424 |

**5-fold time-series cross-validation**

| Model | MAE (mean ± sd) | RMSE (mean ± sd) | R² (mean ± sd) |
|---|---|---|---|
| Linear Regression | 1,110.6 ± 1,486.0 | 1,445.7 ± 1,880.0 | -29.602 ± 54.179 |
| Random Forest | 245.8 ± 38.9 | 354.1 ± 99.9 | 0.152 ± 0.132 |
| Gradient Boosting | 244.7 ± 42.6 | 352.5 ± 91.1 | 0.143 ± 0.187 |

Best: **Random Forest**.

## Experiment 2: day price from scraped Sajilo Rental listings

1,640 listings; 192 vehicle models; 32 manufacturers. The target
`scaling_1day` is the prototype's derived day price, not the listed day price: the 4-hour hourly rate
rounded up × 24 × the median ratio of the listed day and 4-hour hourly rates (`python/preprocessing.py`).
It has only 7 distinct values (Rs 7,360 to 12,800).
The split is the existing PyTorch model's 70/15/15 split (the same 246 test rows:
True). 15 test rows are vehicle models not seen in training.

**Held-out test set**

| Model | MAE | RMSE | R² |
|---|---|---|---|
| Median baseline | 1,412.68 | 2,075.44 | -0.312 |
| Linear Regression | 226.82 | 561.80 | 0.904 |
| Random Forest | 100.60 | 251.31 | 0.981 |
| Gradient Boosting | 198.59 | 313.95 | 0.970 |
| PyTorch MLP (existing) | 134.07 | 347.03 | 0.963 |

The existing PyTorch MLP on all three of its targets: base_price_per_hour R² 0.963, scaling_8hr R² 0.963, scaling_1day R² 0.963.

**Unseen vehicle models: 5-fold GroupKFold by vehicle model**

| Model | MAE (mean ± sd) | RMSE (mean ± sd) | R² (mean ± sd) |
|---|---|---|---|
| Linear Regression | 1,101.2 ± 295.4 | 1,312.2 ± 233.3 | -1.053 ± 1.162 |
| Random Forest | 577.0 ± 321.4 | 808.4 ± 374.0 | 0.246 ± 0.426 |
| Gradient Boosting | 527.0 ± 176.6 | 709.0 ± 233.3 | 0.461 ± 0.212 |
| Median baseline | 1,563.9 ± 1,230.3 | 1,766.7 ± 1,160.5 | -5.316 ± 9.618 |

Top features (Gradient Boosting): fuel_cost_per_km, max_km_per_hour, manufacturer_Mahindra, vehicle_name_Tata 1512 Supper Deluxe, manufacturer_Tata.
