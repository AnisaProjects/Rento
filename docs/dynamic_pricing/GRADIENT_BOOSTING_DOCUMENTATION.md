# Gradient Boosting Classifier Documentation
## Dynamic Pricing Demand Prediction Model

---

## Table of Contents

1. [Overview](#overview)
2. [What is Gradient Boosting?](#what-is-gradient-boosting)
3. [Implementation in This Project](#implementation-in-this-project)
4. [Model Architecture](#model-architecture)
5. [Feature Engineering](#feature-engineering)
6. [Training Process](#training-process)
7. [Inference Process](#inference-process)
8. [How Gradient Boosting Works](#how-gradient-boosting-works)
9. [Hyperparameters Explained](#hyperparameters-explained)
10. [Advantages for Car Rental Pricing](#advantages-for-car-rental-pricing)
11. [Model Performance & Evaluation](#model-performance--evaluation)
12. [Tuning & Optimization](#tuning--optimization)
13. [Code Examples](#code-examples)
14. [Troubleshooting](#troubleshooting)

---

## Overview

The **Gradient Boosting Classifier** is the core machine learning algorithm used in this car rental system to predict short-term demand for vehicles. The model predicts whether a specific vehicle will experience high demand during a given rental period, which then drives dynamic pricing adjustments (surge pricing or discounts).

**Key Purpose**: Binary classification problem
- **Class 0**: Low demand (apply discount)
- **Class 1**: High demand (apply surge pricing)

**Location**: `carrental/ml/pricing_model.py` (training) and `carrental/ml/predict_price.py` (inference)

---

## What is Gradient Boosting?

Gradient Boosting is an ensemble machine learning technique that builds a strong predictive model by combining multiple weak learners (typically decision trees) in a sequential manner. Unlike Random Forest (which builds trees in parallel), Gradient Boosting builds trees one at a time, where each new tree corrects the errors made by the previous trees.

### Core Concept

1. **Start with a simple model** (often just the mean for regression or log-odds for classification)
2. **Calculate residuals/errors** from the current model
3. **Fit a new weak learner** (decision tree) to predict these errors
4. **Add the new tree** to the ensemble with a small learning rate
5. **Repeat** until stopping criteria are met

The "gradient" refers to the fact that the algorithm uses gradient descent optimization to minimize a loss function (typically log-loss for classification).

---

## Implementation in This Project

### Model Configuration

The Gradient Boosting Classifier is configured in `pricing_model.py` with the following parameters:

```python
model = GradientBoostingClassifier(
    n_estimators=400,          # Maximum number of trees
    learning_rate=0.05,        # Shrinkage factor (how much each tree contributes)
    max_depth=3,               # Maximum depth of each tree
    validation_fraction=0.1,   # Fraction of data for early stopping validation
    n_iter_no_change=5,        # Stop if no improvement for 5 iterations
    random_state=42,           # Reproducibility seed
)
```

### Model Pipeline

The model is wrapped in a scikit-learn `Pipeline` that includes:

1. **Preprocessing Stage** (`ColumnTransformer`):
   - **Numeric Features**: Median imputation → StandardScaler
   - **Categorical Features**: Most frequent imputation → OneHotEncoder

2. **Model Stage**: GradientBoostingClassifier

This ensures consistent preprocessing during both training and inference.

---

## Model Architecture

### Complete Pipeline Structure

```
Input Data (Raw Features)
    ↓
ColumnTransformer
    ├─ Numeric Pipeline
    │   ├─ SimpleImputer (median)
    │   └─ StandardScaler
    └─ Categorical Pipeline
        ├─ SimpleImputer (most_frequent)
        └─ OneHotEncoder
    ↓
Preprocessed Features (Normalized & Encoded)
    ↓
GradientBoostingClassifier
    ├─ Tree 1 (weak learner)
    ├─ Tree 2 (corrects Tree 1's errors)
    ├─ Tree 3 (corrects Tree 2's errors)
    ├─ ...
    └─ Tree N (up to 400, or until early stopping)
    ↓
Ensemble Prediction (Probability)
    ↓
Price Adjustment Logic
    ↓
Final Recommended Price
```

---

## Feature Engineering

### Input Features

The model uses **12 features** divided into two categories:

#### Numeric Features (9 features)
1. **PricePerDay**: Base daily rental price
2. **ModelYear**: Vehicle manufacturing year
3. **SeatingCapacity**: Number of seats
4. **lead_time_days**: Days between booking date and rental start date
5. **rental_days**: Duration of rental period
6. **recent_vehicle_bookings_30d**: Count of bookings for this vehicle in the last 30 days
7. **booking_month**: Month of rental start (1-12)
8. **booking_dow**: Day of week (0=Monday, 6=Sunday)
9. **is_weekend_pickup**: Binary flag (1 if Saturday/Sunday, 0 otherwise)

#### Categorical Features (3 features)
1. **FuelType**: Vehicle fuel type (e.g., "Petrol", "Diesel", "Electric")
2. **BrandName**: Vehicle brand (e.g., "Toyota", "Honda")
3. **season_bucket**: Seasonal category (monsoon, autumn, festive, winter, spring, summer)

### Feature Engineering Logic

**Temporal Features** (from `pricing_utils.py`):
- `booking_month`: Extracted from `start_date`
- `booking_dow`: Day of week (0-6)
- `is_weekend_pickup`: Boolean derived from `booking_dow`
- `season_bucket`: Mapped from month using predefined buckets
- `rental_days`: Calculated as `(end_date - start_date).days`

**Demand Features**:
- `recent_vehicle_bookings_30d`: Rolling 30-day window count per vehicle
- `lead_time_days`: Booking advance notice period

**Vehicle Attributes**:
- Directly from database: `PricePerDay`, `ModelYear`, `SeatingCapacity`, `FuelType`, `BrandName`

---

## Training Process

### Step-by-Step Training Workflow

1. **Data Collection**
   ```python
   # Fetches from database:
   # - Historical bookings (tblbooking)
   # - Vehicle details (tblvehicles)
   # - Brand information (tblbrands)
   ```

2. **Data Normalization**
   - Convert date strings to datetime objects
   - Calculate derived features (lead_time, rental_days, etc.)
   - Compute rolling 30-day booking counts per vehicle
   - Label historical bookings as `demand_label = 1` (high demand)

3. **Synthetic Data Generation**
   - Creates negative examples (low demand) to balance the dataset
   - For each vehicle, generates future date windows with:
     - Zero recent bookings
     - `demand_label = 0` (low demand)
   - Default: 3 synthetic rows per vehicle (configurable via `--negative-samples`)

4. **Feature Assembly**
   - Concatenates real bookings + synthetic low-demand rows
   - Handles missing values:
     - Numeric: Median imputation
     - Categorical: "unknown" or most frequent value

5. **Model Training**
   ```python
   pipeline.fit(X, y)  # X = features, y = demand_label (0 or 1)
   ```

6. **Evaluation**
   - Generates classification report (precision, recall, F1-score)
   - Prints metrics to console

7. **Model Persistence**
   - Saves complete pipeline to `ml/models/demand_model.pkl`
   - Includes metadata (features, training date, pricing config)
   - Exports JSON summary for audit trail

### Training Command

```bash
python ml/pricing_model.py \
    --db-host=localhost \
    --db-user=root \
    --db-pass=secret \
    --db-name=carrental \
    --negative-samples=3 \
    --minimum-rows=20
```

---

## Inference Process

### Real-Time Prediction Flow

1. **Input**: Vehicle ID, start date, end date, base price (optional)

2. **Feature Construction**:
   - Load vehicle details from database
   - Calculate recent bookings (30-day window)
   - Compute temporal features (month, day of week, season, etc.)
   - Build feature row matching training format

3. **Model Prediction**:
   ```python
   probabilities = pipeline.predict_proba(feature_row)
   probability = probabilities[0][1]  # Probability of high demand (class 1)
   ```

4. **Price Adjustment**:
   - Maps probability to price multiplier using `PriceAdjustment` logic:
     - **High demand** (probability ≥ 0.7): Apply surge (up to 1.35x)
     - **Low demand** (probability ≤ 0.35): Apply discount (down to 0.8x)
     - **Neutral** (0.35 < probability < 0.7): Base price (1.0x)

5. **Output**: JSON with recommended price, multiplier, probability, and metadata

### Inference Command

```bash
python ml/predict_price.py \
    --vehicle-id=3 \
    --start-date=2025-12-01 \
    --end-date=2025-12-05 \
    --base-price=50.0
```

---

## How Gradient Boosting Works

### Mathematical Foundation

Gradient Boosting minimizes a loss function using gradient descent. For binary classification, it typically uses **log-loss** (logistic loss):

```
L(y, F(x)) = -y * log(σ(F(x))) - (1-y) * log(1 - σ(F(x)))
```

Where:
- `y` = true label (0 or 1)
- `F(x)` = current ensemble prediction (log-odds)
- `σ(F(x))` = sigmoid function = probability

### Algorithm Steps

1. **Initialize**:
   ```
   F₀(x) = log(odds of positive class) = log(p / (1-p))
   ```

2. **For m = 1 to M (number of trees)**:
   a. **Compute pseudo-residuals** (negative gradient):
      ```
      rᵢₘ = -[∂L(yᵢ, F(xᵢ)) / ∂F(xᵢ)] for each sample i
      ```
   
   b. **Fit a regression tree** `hₘ(x)` to predict residuals
   
   c. **Find optimal step size** `γₘ` (via line search)
   
   d. **Update ensemble**:
      ```
      Fₘ(x) = Fₘ₋₁(x) + learning_rate * γₘ * hₘ(x)
      ```

3. **Final prediction**:
   ```
   P(y=1|x) = σ(Fₘ(x)) = 1 / (1 + exp(-Fₘ(x)))
   ```

### Why Sequential Learning Works

Each new tree focuses on samples that previous trees misclassified. The algorithm:
- **Learns from mistakes**: Each tree corrects errors of previous trees
- **Reduces bias**: Gradually improves prediction accuracy
- **Prevents overfitting**: Learning rate (0.05) and early stopping control complexity

---

## Hyperparameters Explained

### `n_estimators=400`
- **What**: Maximum number of trees (weak learners) in the ensemble
- **Impact**: More trees = better fit, but risk of overfitting
- **Why 400**: Balance between performance and training time; early stopping may stop before reaching 400

### `learning_rate=0.05`
- **What**: Shrinkage factor controlling how much each tree contributes
- **Impact**: Lower rate = more trees needed, but better generalization
- **Why 0.05**: Conservative rate prevents overfitting; each tree makes small corrections

### `max_depth=3`
- **What**: Maximum depth of each decision tree
- **Impact**: Deeper trees = more complex patterns, but risk of overfitting
- **Why 3**: Shallow trees are "weak learners" that work well in boosting; prevents individual trees from dominating

### `validation_fraction=0.1`
- **What**: Fraction of training data held out for early stopping validation
- **Impact**: Monitors overfitting during training
- **Why 0.1**: 10% provides reliable validation signal without wasting too much training data

### `n_iter_no_change=5`
- **What**: Number of iterations without improvement before stopping
- **Impact**: Prevents unnecessary training when model has converged
- **Why 5**: Allows for minor fluctuations while stopping when truly stuck

### `random_state=42`
- **What**: Seed for random number generator
- **Impact**: Ensures reproducible results across runs
- **Why 42**: Arbitrary but consistent seed for debugging and comparison

---

## Advantages for Car Rental Pricing

### 1. **Handles Non-Linear Relationships**
- Vehicle demand depends on complex interactions (season × brand × price)
- Gradient Boosting captures these without manual feature engineering

### 2. **Robust to Small Datasets**
- Car rental businesses may have limited historical data
- Shallow trees + early stopping prevent overfitting on small samples

### 3. **Feature Importance**
- Model provides feature importance scores
- Helps identify which factors drive demand (e.g., season, lead time, brand)

### 4. **Handles Mixed Data Types**
- Works seamlessly with numeric (price, year) and categorical (fuel type, brand) features
- Preprocessing pipeline handles encoding automatically

### 5. **Interpretable Predictions**
- Outputs probability (0-1) that can be mapped to business rules
- More transparent than black-box models

### 6. **Fast Inference**
- Once trained, predictions are fast (milliseconds)
- Suitable for real-time pricing on web pages

---

## Model Performance & Evaluation

### Evaluation Metrics

The training script outputs a **classification report** with:
- **Precision**: Of predicted high-demand, how many were actually high-demand?
- **Recall**: Of actual high-demand, how many were correctly predicted?
- **F1-Score**: Harmonic mean of precision and recall
- **Support**: Number of samples in each class

### Typical Output

```
              precision    recall  f1-score   support

           0       0.XX      0.XX      0.XX        XXX
           1       0.XX      0.XX      0.XX        XXX

    accuracy                           0.XX        XXX
   macro avg       0.XX      0.XX      0.XX        XXX
weighted avg       0.XX      0.XX      0.XX        XXX
```

### Model Artifacts

After training, the following files are created:

1. **`ml/models/demand_model.pkl`**: Serialized pipeline (model + preprocessor)
2. **`ml/models/demand_model.pkl.json`**: Metadata (training date, feature list, pricing config)
3. **`ml/data_cache/training_snapshot.parquet`**: Training data snapshot for audit/debugging

---

## Tuning & Optimization

### When to Retrain

- **Regular schedule**: Weekly or monthly to capture seasonal changes
- **After major events**: New vehicle additions, pricing changes, marketing campaigns
- **Performance degradation**: If predictions become less accurate

### Hyperparameter Tuning

To improve model performance, consider:

1. **Grid Search**:
   ```python
   from sklearn.model_selection import GridSearchCV
   
   param_grid = {
       'model__n_estimators': [200, 400, 600],
       'model__learning_rate': [0.01, 0.05, 0.1],
       'model__max_depth': [2, 3, 4]
   }
   ```

2. **Cross-Validation**: Use `cross_val_score` to evaluate different configurations

3. **Feature Engineering**: Add new features (holidays, weather, events)

### Common Adjustments

- **Increase `n_estimators`**: If model underfits (low accuracy)
- **Decrease `learning_rate`**: If model overfits (high training accuracy, low validation)
- **Adjust `max_depth`**: Deeper for complex patterns, shallower for simpler data

---

## Code Examples

### Training the Model

```python
from pricing_model import main
import sys

# Set command-line arguments
sys.argv = [
    'pricing_model.py',
    '--db-host=localhost',
    '--db-user=root',
    '--db-pass=secret',
    '--db-name=carrental',
    '--negative-samples=3'
]

main()
```

### Making Predictions

```python
import joblib
import pandas as pd
from pricing_utils import enrich_temporal_columns

# Load model
artifact = joblib.load('ml/models/demand_model.pkl')
pipeline = artifact['pipeline']
features = artifact['features']

# Prepare feature row
feature_row = pd.DataFrame([{
    'PricePerDay': 50.0,
    'ModelYear': 2020,
    'SeatingCapacity': 5,
    'lead_time_days': 7,
    'rental_days': 3,
    'recent_vehicle_bookings_30d': 5,
    'booking_month': 12,
    'booking_dow': 5,
    'is_weekend_pickup': 1,
    'FuelType': 'Petrol',
    'BrandName': 'Toyota',
    'season_bucket': 'festive'
}])

# Predict
input_cols = features['numeric'] + features['categorical']
probabilities = pipeline.predict_proba(feature_row[input_cols])
high_demand_prob = probabilities[0][1]

print(f"High demand probability: {high_demand_prob:.2%}")
```

### Extracting Feature Importance

```python
import joblib
import pandas as pd

# Load model
artifact = joblib.load('ml/models/demand_model.pkl')
pipeline = artifact['pipeline']
model = pipeline.named_steps['model']

# Get feature names (after preprocessing)
feature_names = pipeline.named_steps['features'].get_feature_names_out()

# Get importance scores
importance = pd.DataFrame({
    'feature': feature_names,
    'importance': model.feature_importances_
}).sort_values('importance', ascending=False)

print(importance)
```

---

## Troubleshooting

### Common Issues

1. **"Not enough rows to train"**
   - **Cause**: Insufficient historical bookings
   - **Solution**: Lower `--minimum-rows` or wait for more data

2. **Model predictions always 0.5**
   - **Cause**: Model not learning (underfitting)
   - **Solution**: Increase `n_estimators` or `learning_rate`, check feature quality

3. **Overfitting (high training accuracy, low validation)**
   - **Cause**: Model too complex or too many trees
   - **Solution**: Decrease `learning_rate`, increase `n_iter_no_change`, reduce `max_depth`

4. **"Model not found" during inference**
   - **Cause**: Model file missing or wrong path
   - **Solution**: Run training script first, check `--model-path` argument

5. **Feature mismatch errors**
   - **Cause**: Training and inference use different features
   - **Solution**: Ensure `predict_price.py` uses same feature engineering as `pricing_model.py`

### Debugging Tips

- **Check training snapshot**: Review `ml/data_cache/training_snapshot.parquet` to inspect training data
- **Validate features**: Print feature row before prediction to ensure correct format
- **Monitor probabilities**: Log predictions to identify patterns or anomalies
- **Compare with baseline**: Test against simple heuristics (e.g., weekend = high demand)

---

## References

- **Scikit-learn Documentation**: [GradientBoostingClassifier](https://scikit-learn.org/stable/modules/generated/sklearn.ensemble.GradientBoostingClassifier.html)
- **Original Paper**: Friedman, J. H. (2001). "Greedy Function Approximation: A Gradient Boosting Machine"
- **Project Files**:
  - Training: `carrental/ml/pricing_model.py`
  - Inference: `carrental/ml/predict_price.py`
  - Utilities: `carrental/ml/pricing_utils.py`

---

## Version History

- **v1.0** (Initial): Gradient Boosting implementation with 12 features, early stopping, and dynamic pricing integration

---

**Last Updated**: 2025-01-XX  
**Maintained By**: Development Team  
**Contact**: See project README for support

