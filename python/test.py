"""
test.py
-------
Loads the model + encoders/scalers saved by train.py and:
  1. Re-evaluates on the held-out test split (pricing_test_split.csv) to
     confirm the saved checkpoint reproduces train.py's reported metrics.
  2. Exposes predict_price(...) for scoring a single new/hypothetical vehicle.

Usage:
    python test.py --model pricing_model.pt --test-split pricing_test_split.csv
"""

import argparse

import numpy as np
import pandas as pd
import torch
import torch.nn as nn
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

UNK_TOKEN = "<UNK>"


# ---------------------------------------------------------------------------
# Must match train.py exactly so state_dict loads correctly.
# ---------------------------------------------------------------------------

class PricingModel(nn.Module):
    def __init__(self, num_vehicles, num_manufacturers, num_numeric,
                 veh_emb_dim=16, mfr_emb_dim=8, hidden_dims=(64, 32), num_targets=3):
        super().__init__()
        self.vehicle_emb = nn.Embedding(num_vehicles, veh_emb_dim)
        self.mfr_emb = nn.Embedding(num_manufacturers, mfr_emb_dim)

        input_dim = veh_emb_dim + mfr_emb_dim + num_numeric
        layers = []
        prev_dim = input_dim
        for h in hidden_dims:
            layers += [nn.Linear(prev_dim, h), nn.ReLU(), nn.Dropout(0.1)]
            prev_dim = h
        layers.append(nn.Linear(prev_dim, num_targets))
        self.mlp = nn.Sequential(*layers)

    def forward(self, vehicle_idx, mfr_idx, numeric):
        v = self.vehicle_emb(vehicle_idx)
        m = self.mfr_emb(mfr_idx)
        x = torch.cat([v, m, numeric], dim=1)
        return self.mlp(x)


def encode_column(values: pd.Series, encoder: dict) -> np.ndarray:
    return values.astype(str).map(lambda v: encoder.get(v, encoder[UNK_TOKEN])).to_numpy()


def apply_standard_scaler(values: np.ndarray, scaler: dict) -> np.ndarray:
    mean = np.array(scaler["mean"])
    std = np.array(scaler["std"])
    return (values - mean) / std


def inverse_standard_scaler(values: np.ndarray, scaler: dict) -> np.ndarray:
    mean = np.array(scaler["mean"])
    std = np.array(scaler["std"])
    return values * std + mean


def load_checkpoint(path: str):
    checkpoint = torch.load(path, weights_only=False)
    model = PricingModel(**checkpoint["config"])
    model.load_state_dict(checkpoint["model_state_dict"])
    model.eval()
    return model, checkpoint


def predict_price(model, checkpoint, vehicle_name: str, manufacturer: str,
                   max_km_per_hour: float, fuel_cost_per_km: float) -> dict:
    """Predict base_price_per_hour / scaling_8hr / scaling_1day for one vehicle."""
    veh_idx = torch.tensor(
        [checkpoint["vehicle_encoder"].get(vehicle_name, checkpoint["vehicle_encoder"][UNK_TOKEN])],
        dtype=torch.long,
    )
    mfr_idx = torch.tensor(
        [checkpoint["mfr_encoder"].get(manufacturer, checkpoint["mfr_encoder"][UNK_TOKEN])],
        dtype=torch.long,
    )
    numeric_raw = np.array([[max_km_per_hour, fuel_cost_per_km]])
    numeric = torch.tensor(
        apply_standard_scaler(numeric_raw, checkpoint["numeric_scaler"]), dtype=torch.float32
    )

    with torch.no_grad():
        pred_scaled = model(veh_idx, mfr_idx, numeric).numpy()

    pred = inverse_standard_scaler(pred_scaled, checkpoint["target_scaler"])[0]
    return dict(zip(checkpoint["target_cols"], pred.tolist()))


def evaluate_test_split(model, checkpoint, test_df: pd.DataFrame):
    numeric_cols = checkpoint["numeric_cols"]
    target_cols = checkpoint["target_cols"]

    veh_idx = torch.tensor(encode_column(test_df["vehicle_name"], checkpoint["vehicle_encoder"]), dtype=torch.long)
    mfr_idx = torch.tensor(encode_column(test_df["manufacturer"], checkpoint["mfr_encoder"]), dtype=torch.long)
    numeric = torch.tensor(
        apply_standard_scaler(test_df[numeric_cols].to_numpy(dtype=float), checkpoint["numeric_scaler"]),
        dtype=torch.float32,
    )

    with torch.no_grad():
        preds_scaled = model(veh_idx, mfr_idx, numeric).numpy()

    preds = inverse_standard_scaler(preds_scaled, checkpoint["target_scaler"])
    actuals = test_df[target_cols].to_numpy(dtype=float)

    print("--- Held-out test metrics (reproduced from saved checkpoint) ---")
    for i, col in enumerate(target_cols):
        mae = mean_absolute_error(actuals[:, i], preds[:, i])
        rmse = np.sqrt(mean_squared_error(actuals[:, i], preds[:, i]))
        r2 = r2_score(actuals[:, i], preds[:, i])
        print(f"  {col:22s}  MAE={mae:8.2f}  RMSE={rmse:8.2f}  R2={r2:.3f}")

    print("\nSample predictions vs actual (first 5 rows):")
    sample = pd.DataFrame(preds[:5], columns=[f"pred_{c}" for c in target_cols])
    sample_actual = test_df[target_cols].head(5).reset_index(drop=True)
    sample_actual.columns = [f"actual_{c}" for c in target_cols]
    print(pd.concat([test_df[["vehicle_name", "manufacturer"]].head(5).reset_index(drop=True),
                      sample_actual, sample], axis=1).to_string(index=False))


def main():
    parser = argparse.ArgumentParser(description="Evaluate/run the Rento pricing model.")
    parser.add_argument("--model", default="pricing_model.pt")
    parser.add_argument("--test-split", default="pricing_test_split.csv")
    args = parser.parse_args()

    model, checkpoint = load_checkpoint(args.model)

    test_df = pd.read_csv(args.test_split)
    evaluate_test_split(model, checkpoint, test_df)

    # ---- Demo: predict for a hypothetical / new vehicle --------------------
    print("\n--- Example single-vehicle prediction ---")
    example = predict_price(
        model, checkpoint,
        vehicle_name="Scorpio",
        manufacturer="Mahindra",
        max_km_per_hour=12.5,
        fuel_cost_per_km=19.73,
    )
    for k, v in example.items():
        print(f"  {k}: {v:.1f}")


if __name__ == "__main__":
    main()