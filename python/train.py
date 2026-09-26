"""
train.py
--------
Trains a PyTorch regression model that predicts a vehicle's pricing —
base_price_per_hour, scaling_8hr, scaling_1day — from vehicle
characteristics (vehicle_name, manufacturer, max_km_per_hour,
fuel_cost_per_km).

IMPORTANT — why price_4hr / price_per_hour are NOT input features:
base_price_per_hour is literally derived from price_4hr (ceil(price_4hr/4/50)*50),
so using it as an input would let the model "cheat" by just re-deriving the
answer instead of learning the actual pricing pattern from vehicle
characteristics. Only true vehicle attributes are used as inputs.

Categorical encoding:
    vehicle_name and manufacturer are label-encoded to integer indices and
    fed through learned nn.Embedding layers (rather than one-hot), which
    scales well with vehicle_name's high cardinality and lets the model
    discover similarity between vehicles automatically.

Pipeline:
    clean_vehicles.csv -> train/val/test split -> train MLP -> evaluate ->
    save model + encoders + scalers (pricing_model.pt) + held-out test set
    (pricing_test_split.csv) for test.py to independently verify.

Usage:
    python train.py --input clean_vehicles.csv --output pricing_model.pt
"""

import argparse
import json

import numpy as np
import pandas as pd
import torch
import torch.nn as nn
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split

TARGET_COLS = ["base_price_per_hour", "scaling_8hr", "scaling_1day"]
NUMERIC_COLS = ["max_km_per_hour", "fuel_cost_per_km"]
UNK_TOKEN = "<UNK>"  # reserved index 0, used for categories unseen at train time

torch.manual_seed(42)
np.random.seed(42)


# ---------------------------------------------------------------------------
# Encoding helpers
# ---------------------------------------------------------------------------

def build_label_encoder(values: pd.Series) -> dict:
    """category string -> integer index, with index 0 reserved for unknown."""
    uniques = sorted(values.astype(str).unique())
    encoder = {UNK_TOKEN: 0}
    encoder.update({v: i + 1 for i, v in enumerate(uniques)})
    return encoder


def encode_column(values: pd.Series, encoder: dict) -> np.ndarray:
    return values.astype(str).map(lambda v: encoder.get(v, encoder[UNK_TOKEN])).to_numpy()


def fit_standard_scaler(values: np.ndarray) -> dict:
    mean = values.mean(axis=0)
    std = values.std(axis=0)
    std[std == 0] = 1.0  # avoid divide-by-zero for constant columns
    return {"mean": mean.tolist(), "std": std.tolist()}


def apply_standard_scaler(values: np.ndarray, scaler: dict) -> np.ndarray:
    mean = np.array(scaler["mean"])
    std = np.array(scaler["std"])
    return (values - mean) / std


def inverse_standard_scaler(values: np.ndarray, scaler: dict) -> np.ndarray:
    mean = np.array(scaler["mean"])
    std = np.array(scaler["std"])
    return values * std + mean


# ---------------------------------------------------------------------------
# Model
# ---------------------------------------------------------------------------

class PricingModel(nn.Module):
    """Embeddings for vehicle_name & manufacturer + numeric features -> MLP -> 3 price targets."""

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


# ---------------------------------------------------------------------------
# Evaluation
# ---------------------------------------------------------------------------

def evaluate(model, vehicle_idx, mfr_idx, numeric, targets_scaled, target_scaler, split_name=""):
    model.eval()
    with torch.no_grad():
        preds_scaled = model(vehicle_idx, mfr_idx, numeric).numpy()

    preds = inverse_standard_scaler(preds_scaled, target_scaler)
    actuals = inverse_standard_scaler(targets_scaled.numpy(), target_scaler)

    print(f"\n--- {split_name} metrics ---")
    results = {}
    for i, col in enumerate(TARGET_COLS):
        mae = mean_absolute_error(actuals[:, i], preds[:, i])
        rmse = np.sqrt(mean_squared_error(actuals[:, i], preds[:, i]))
        r2 = r2_score(actuals[:, i], preds[:, i])
        results[col] = {"mae": mae, "rmse": rmse, "r2": r2}
        print(f"  {col:22s}  MAE={mae:8.2f}  RMSE={rmse:8.2f}  R2={r2:.3f}")

    return results


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description="Train Rento vehicle pricing regression model.")
    parser.add_argument("--input", default="clean_vehicles.csv")
    parser.add_argument("--output", default="pricing_model.pt")
    parser.add_argument("--test-split-output", default="pricing_test_split.csv")
    parser.add_argument("--epochs", type=int, default=300)
    parser.add_argument("--lr", type=float, default=1e-3)
    parser.add_argument("--batch-size", type=int, default=64)
    args = parser.parse_args()

    df = pd.read_csv(args.input)

    # ---- 1. Train / val / test split (70 / 15 / 15) ------------------------
    train_df, temp_df = train_test_split(df, test_size=0.30, random_state=42)
    val_df, test_df = train_test_split(temp_df, test_size=0.50, random_state=42)

    print(f"Train: {len(train_df)}  Val: {len(val_df)}  Test: {len(test_df)}")

    # Save the untouched test split so test.py evaluates on genuinely held-out data.
    test_df.to_csv(args.test_split_output, index=False)

    # ---- 2. Fit encoders / scalers on TRAIN ONLY (avoid leakage) ------------
    vehicle_encoder = build_label_encoder(train_df["vehicle_name"])
    mfr_encoder = build_label_encoder(train_df["manufacturer"])
    numeric_scaler = fit_standard_scaler(train_df[NUMERIC_COLS].to_numpy(dtype=float))
    target_scaler = fit_standard_scaler(train_df[TARGET_COLS].to_numpy(dtype=float))

    def to_tensors(split_df):
        veh_idx = torch.tensor(encode_column(split_df["vehicle_name"], vehicle_encoder), dtype=torch.long)
        mfr_idx = torch.tensor(encode_column(split_df["manufacturer"], mfr_encoder), dtype=torch.long)
        numeric = torch.tensor(
            apply_standard_scaler(split_df[NUMERIC_COLS].to_numpy(dtype=float), numeric_scaler),
            dtype=torch.float32,
        )
        targets = torch.tensor(
            apply_standard_scaler(split_df[TARGET_COLS].to_numpy(dtype=float), target_scaler),
            dtype=torch.float32,
        )
        return veh_idx, mfr_idx, numeric, targets

    train_veh, train_mfr, train_num, train_y = to_tensors(train_df)
    val_veh, val_mfr, val_num, val_y = to_tensors(val_df)
    test_veh, test_mfr, test_num, test_y = to_tensors(test_df)

    # ---- 3. Model / optimizer -----------------------------------------------
    model = PricingModel(
        num_vehicles=len(vehicle_encoder),
        num_manufacturers=len(mfr_encoder),
        num_numeric=len(NUMERIC_COLS),
    )
    optimizer = torch.optim.Adam(model.parameters(), lr=args.lr)
    loss_fn = nn.MSELoss()

    # ---- 4. Training loop with mini-batches + early stopping on val loss ---
    n_train = len(train_df)
    best_val_loss = float("inf")
    best_state = None
    patience, patience_counter = 30, 0

    for epoch in range(1, args.epochs + 1):
        model.train()
        perm = torch.randperm(n_train)
        epoch_loss = 0.0

        for start in range(0, n_train, args.batch_size):
            idx = perm[start:start + args.batch_size]
            optimizer.zero_grad()
            preds = model(train_veh[idx], train_mfr[idx], train_num[idx])
            loss = loss_fn(preds, train_y[idx])
            loss.backward()
            optimizer.step()
            epoch_loss += loss.item() * len(idx)

        epoch_loss /= n_train

        model.eval()
        with torch.no_grad():
            val_loss = loss_fn(model(val_veh, val_mfr, val_num), val_y).item()

        if val_loss < best_val_loss:
            best_val_loss = val_loss
            best_state = {k: v.clone() for k, v in model.state_dict().items()}
            patience_counter = 0
        else:
            patience_counter += 1

        if epoch % 20 == 0 or epoch == 1:
            print(f"Epoch {epoch:4d}  train_loss={epoch_loss:.4f}  val_loss={val_loss:.4f}")

        if patience_counter >= patience:
            print(f"Early stopping at epoch {epoch} (no val improvement for {patience} epochs).")
            break

    model.load_state_dict(best_state)

    # ---- 5. Evaluate on val + held-out test ---------------------------------
    evaluate(model, val_veh, val_mfr, val_num, val_y, target_scaler, split_name="Validation")
    evaluate(model, test_veh, test_mfr, test_num, test_y, target_scaler, split_name="Test (held-out)")

    # ---- 6. Save model + everything needed to run inference elsewhere ------
    checkpoint = {
        "model_state_dict": model.state_dict(),
        "config": {
            "num_vehicles": len(vehicle_encoder),
            "num_manufacturers": len(mfr_encoder),
            "num_numeric": len(NUMERIC_COLS),
        },
        "vehicle_encoder": vehicle_encoder,
        "mfr_encoder": mfr_encoder,
        "numeric_scaler": numeric_scaler,
        "target_scaler": target_scaler,
        "numeric_cols": NUMERIC_COLS,
        "target_cols": TARGET_COLS,
    }
    torch.save(checkpoint, args.output)

    print(f"\nSaved model + encoders/scalers to {args.output}")
    print(f"Saved held-out test split to {args.test_split_output}")


if __name__ == "__main__":
    main()