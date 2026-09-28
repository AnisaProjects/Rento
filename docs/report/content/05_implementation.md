# CHAPTER 5: IMPLEMENTATION

## 5.1 Introduction

This chapter describes how the design presented in Chapter 4 was implemented. Section 5.2 introduces the development environment and the structure of the code. Section 5.3 describes the implementation of the database. Sections 5.4 and 5.5 present the customer and administration modules, and Section 5.6 the JSON application programming interface. Sections 5.7 to 5.10 explain the intelligent components: the smart pricing engine, the forecast pipeline, the machine-learning experiments and the recommendation engine. Section 5.11 describes the security measures, Section 5.12 the remaining quality features, and Section 5.13 summarises the chapter. The code excerpts in this chapter are taken from the source code, shortened where marked with an ellipsis.

## 5.2 Development Environment and Code Structure

The system was developed and tested on the local server package MAMP, which provides Apache 2.4.62, PHP 8.3.14 and MySQL 8.0.40, with the Python components in a separate Python 3.12.14 virtual environment. The tools and their versions are listed in Table 3.6. The code is organised in the folders shown in Table 5.1; the public pages remain in the root folder with their original file names, so that existing links continued to work while the pages were rebuilt.

Table: Table 5.1 Structure of the source code {widths=1.4,3.2,1.15}
| Folder | Contents | Lines |
|---|---|---|
| Root folder | Public pages, such as the home page, car listing, car page, Find My Car, packages, My Bookings and invoice | 2,788 |
| includes | Shared PHP modules: security, configuration, pricing, price insights, recommendation engine, trip parser, mailer, SEO, layout and components | 3,649 |
| api | JSON endpoints for price quotes, price calendars and recommendations, with shared set-up | 278 |
| admin | Administration pages and their shared layout, forms and upload handling | 4,769 |
| assets | Design tokens and stylesheets (CSS) and scripts for the price calendar, trip search and theme (JavaScript) | 6,923 CSS, 643 JS |
| ml | Python pricer, forecast pipeline and machine-learning experiments | 1,508 |
| database | 22 additive migrations and their undo scripts | 563 |
| tests | PHPUnit and pytest test suites and evaluation scripts | 1,035 |

> Line counts exclude third-party libraries (Bootstrap, jQuery, DataTables, Chart.js, Quill, PHPMailer) and minified files.

Configuration values that differ between computers, such as the database user and password and the application key used for encryption, are read from environment variables or from a local configuration file that is excluded from version control. No password is stored in the repository.

## 5.3 Database Implementation

The database was extended through 22 numbered migration scripts, summarised in Table 5.2. Every migration is additive: it creates a table or adds columns and never renames or removes existing ones, and a backup of the database was made outside the web folder before each migration was applied. Where a migration changed data, such as the market-based prices of migration 017, an undo script was written as well.

Table: Table 5.2 Database migrations {widths=0.8,4.95}
| Migrations | Changes |
|---|---|
| 001–004 | Password reset tokens and rate-limit records, starter FAQ content, reset tokens for administrators |
| 005–007 | Editable site content, active and inactive vehicles, 17 researched vehicles and 5 brands (added as inactive) |
| 008–010 | Festival calendar with 40 dated entries, packages with categories, photos and enquiries, driver-only vehicles |
| 011–016 | Pricing settings, price forecast and forecast runs, stored booking price, festival travel days, manual price overrides, festival pricing for packages |
| 017–018 | Market-based prices, corrected specifications and descriptions of the active cars, FAQ text on smart pricing |
| 019–022 | Rugged flag, search engine descriptions, email settings, draft versions of content pages |

The application connects to MySQL through PHP Data Objects (PDO) with a dedicated database user, rento, which has only the privileges that the application needs on the rento database; the administrative root account is used only to apply migrations. Every query that contains a value from a request is a prepared statement with bound parameters.

## 5.4 Customer Modules

### 5.4.1 Car Listing and Car Page

The car listing accepts the filters keyword, type, brand, fuel, maximum price, sort order and page as query parameters. Each value is accepted only if it belongs to a list of allowed values, and the budget filter and the price sort use the "from" price of each car, which is the lowest price of an available day in the next 30 days taken from the forecast. Each car card also shows badges based on the data, such as "Below market" when the car's 30-day average is below the published self-drive rate for its category.

The car page shows the photographs, specifications and description of the car and a booking panel with the price calendar. The calendar is drawn by a small JavaScript module that requests the prices of a month from the price-calendar endpoint and colours each day according to whether it is cheaper or more expensive than usual or already booked. When the customer selects the dates, the module requests a quote for the rental and shows the total, the reasons and, where applicable, how much the customer would save by moving the rental to cheaper days. The calendar can be operated with the keyboard, and changes of price are announced to screen readers.

### 5.4.2 Booking, My Bookings and Invoice

A booking request is handled by the car page itself. After checking the form token, the login, the dates and the availability of the car, the server calculates the quote and stores it with the booking, as shown in Figure 5.1. Any price sent by the browser is ignored, so that a customer cannot change the price by modifying the form.

```Figure 5.1 Code excerpt: the booking is stored with the price calculated on the server (vehical-details.php)
$quote = public_quote($dbh, $car, $fromdate, $todate);
[$quotedPrice, $pricingDetails] = booking_price_record($quote);
...
$sql = "INSERT INTO  tblbooking(BookingNumber,userEmail,VehicleId,FromDate,ToDate,message,Status,quoted_price,pricing_details,priced_at)
        VALUES(:bookingno,:useremail,:vhid,:fromdate,:todate,:message,:status,:quoted,:details,NOW())";
...
forecast_request_recompute($dbh, 'new booking #' . $bookingno);
```

My Bookings lists the customer's bookings with their status and stored price, and the invoice page, which can be opened only by the owner of the booking, shows the stored total and the price of each day with its reasons, in a layout designed for printing. Figure 5.2 shows the invoice of a demonstration booking.

![Figure 5.2 Printable invoice showing the stored price and the price of each day](screenshots/10_invoice_desktop.png){width=5.2}

### 5.4.3 Find My Car and Packages

Find My Car accepts a trip description in plain language with optional dates, budget and number of travellers. The same trip parser exists in JavaScript, which shows the understood criteria as labels while the customer types, and in PHP, which is used by the server; the recommendation engine behind it is described in Section 5.10. The packages pages list active tour and transfer packages with their itinerary, inclusions, exclusions and prices, and send enquiries to the administrator. Cars that are available only with a driver are also booked through an enquiry, because their price depends on the itinerary. At the time of writing, all 16 packages were inactive and the packages section was therefore hidden on the public site.

## 5.5 Administration Modules

The administration area has its own login and session, separate from the customer session. Its dashboard shows key figures calculated from the database, such as the revenue of confirmed bookings, the active rentals, the pending booking requests, the use of the active fleet and the new users of the month. The bookings pages share one list with a status filter and a detail page from which a booking is confirmed or cancelled; confirmation is refused if the booking would overlap a confirmed booking of the same car. The vehicle form manages all attributes of a car, including the active, driver-only and rugged flags and up to five photographs, and shows a smart price suggestion next to the base price.

The festivals page lists the festival calendar with the dates, demand level, travel window and the source of each entry, and marks the dates that are not yet official. The Smart Pricing page lets the administrator change every pricing setting within safe limits, recompute the forecast, and see the health of the pricing model, as shown in Figure 5.3: which pricer is active, how many confirmed bookings exist compared with the thresholds for the occupancy and demand factors and for the learned model, and how much of the fleet the forecast covers.

![Figure 5.3 Smart Pricing page with the model health and a preview of the next days](figures/ui_admin_model_health.png){width=5.4}

AI Insights, shown in Figure 5.4, lists for each car its base price, its average forecast price over 30 and 60 days, its lowest and highest days and the main reasons, together with a suggested base price. The administrator can apply the suggestion or set a manual price for a range of dates; both actions trigger a recomputation. The page states explicitly that the suggestions come from the pricing rules and not from a trained model.

![Figure 5.4 AI Insights with the suggested price for each car](figures/ui_admin_ai_insights.png){width=5.4}

> The screenshots in this report were taken with one temporary demonstration booking in the database, which was deleted afterwards; the booking counts shown in Figures 5.3 and 5.4 therefore include it.

The remaining administration pages manage brands, packages and their categories and enquiries, registered users, testimonials, contact queries, subscribers, the content pages with a draft workflow, the editable text and images of the public site, the contact information, the email settings and a system check of the production settings.

## 5.6 JSON Application Programming Interface

The three JSON endpoints in Table 5.3 serve the price calendar, the booking panel and the recommendation feature. They share a set-up file that accepts only GET requests, applies a rate limit per IP address, returns errors as JSON with the correct HTTP status code, and never exposes PHP or database error messages; unexpected errors are written to the server log only. The endpoints do not use the session, so they can be called without logging in, but they are read-only.

Table: Table 5.3 JSON API endpoints {widths=1.35,2.5,1.9}
| Endpoint | Parameters | Response |
|---|---|---|
| api/price-quote.php | car, from, to (dates, from today, at most 90 days) | Total, price and reasons of each day, availability; rate limit 120 requests in 15 minutes |
| api/price-calendar.php | car, month (YYYY-MM) | Price and reasons of each remaining day of the month, with days beyond the forecast marked as estimates; 120 requests in 15 minutes |
| api/recommend.php | trip, and optionally budget, type, seats, fuel, from, to | Ranked self-drive cars with match percentage, reasons and cautions, and matching driver-only cars; 60 requests in 15 minutes |

## 5.7 Smart Pricing Engine

The smart pricing engine implements the capped rule-based model of Equation 2.1. The factors, their default values and the conditions under which they apply are given in Table 5.4. All values are settings that the administrator can change on the Smart Pricing page.

Table: Table 5.4 Pricing factors and their default values {widths=1.55,0.75,3.45}
| Factor | Default | Condition |
|---|---|---|
| Weekend | +8% | The day is a weekend day: Friday or Saturday in Nepal |
| Festival, high demand | +12% | The day falls within a festival marked as high demand, such as Dashain, Tihar or Chhath |
| Festival, medium demand | +5% | The day falls within a festival marked as medium demand, such as a single-day holiday |
| Festival travel days | +15% | The day falls within the travel window before a festival; each festival can set its own percentage |
| Travel season | +4% | The day falls within a season, such as the autumn trekking season |
| Last minute | +5% | The day is at most 2 days ahead |
| Early booking | −4% | The day is at least 45 days ahead |
| High occupancy | +6% | At least 60% of the car's next 30 days are booked (only after 50 confirmed bookings) |
| Low occupancy | −3% | At most 10% of the car's next 30 days are booked (only after 50 confirmed bookings) |
| Recent demand | +5% | At least 3 booking requests for the car in the last 30 days (only after 50 confirmed bookings) |
| Limits | −15% / +25% | The sum of the factors is capped at these values; prices are rounded to Rs 50 |

Only the strongest festival factor applies on a day, so that a travel day, a festival and an overlapping holiday are not added together; the travel season is counted separately. The occupancy and demand factors are switched off until 50 confirmed bookings exist, because with almost no bookings every car would otherwise appear to be in a quiet period and would be discounted every day. Figure 5.5 shows the core of the function that prices one car for one day.

```Figure 5.5 Code excerpt: pricing one car for one day (ml/pricing_rules.py)
def price_for_day(day, today, car, festivals, s):
    reasons = []
    weekend_days = {int(d) for d in str(s["pricing_weekend_days"]).split(",") if str(d).strip()}
    if day.isoweekday() in weekend_days and s["pricing_weekend_pct"]:
        reasons.append({"label": "Weekend", "pct": _pct(s["pricing_weekend_pct"])})
    # The strongest festival that day; a travel day uses the travel surcharge instead
    ...
    days_ahead = (day - today).days
    if days_ahead <= s["pricing_lastminute_days"] and s["pricing_lastminute_pct"]:
        reasons.append({"label": "Last-minute", "pct": _pct(s["pricing_lastminute_pct"])})
    elif days_ahead >= s["pricing_early_days"] and s["pricing_early_pct"]:
        reasons.append({"label": "Early booking", "pct": _pct(s["pricing_early_pct"])})
    ...
    total = sum(r["pct"] for r in reasons)
    low, high = -float(s["pricing_max_discount"]), float(s["pricing_max_surcharge"])
    capped = total < low or total > high
    total = max(low, min(high, total))
    multiplier = round(1 + total / 100, 4)
    step = max(1, int(s["pricing_round_to"]))
    price = int(round(car.base_price * multiplier / step) * step)
```

The effect of the rules is illustrated with the Toyota Fortuner, whose base price is Rs 10,000 per day, in the forecast computed on 27 September 2026. On an ordinary weekday such as Wednesday 30 September no factor applies and the price is Rs 10,000. On Friday 2 October the weekend (+8%) and the autumn trekking season (+4%) apply, giving Rs 11,200. On Friday 9 October, a travel day before Dashain, the weekend (+8%), the travel days (+15%) and the season (+4%) add up to +27%, which is capped at +25%, giving Rs 12,500. Figure 5.6 shows the whole 60-day forecast of the car, with the Dashain, Tihar and Chhath periods highlighted.

![Figure 5.6 Smart price of the Toyota Fortuner for the next 60 days (forecast of 27 September 2026)](figures/pricing_60_days.png){width=5.7}

## 5.8 Forecast Pipeline

The forecast pipeline computes the price of every active car for each of the next 60 days and stores the results in the forecast table, so that pages and API calls only read prepared prices. A recomputation is requested whenever something that affects prices changes: a booking is created, confirmed or cancelled; a festival, a pricing setting or a vehicle is saved, added, activated, deactivated or deleted; or a suggested or manual price is applied or removed. In addition, a launchd job on the server starts the pipeline every night at 02:30, so that the lead-time factors move forward each day.

Each request inserts a row in the forecast runs table and starts the Python batch forecast in the background through PHP's proc_open function. The database and pricing settings are passed in environment variables rather than as command-line arguments, where they could be seen by other users of the server. The batch forecast first takes a named MySQL lock, so that only one recomputation runs at a time; a second one waits up to 300 seconds and, if the first is still running, leaves its request to be picked up by it. The pipeline then marks all queued runs as running, computes the forecast and replaces the table in one transaction, as shown in Figure 5.7, so that the website sees either the complete old forecast or the complete new one.

```Figure 5.7 Code excerpt: replacing the forecast in one transaction (ml/batch_forecast.py)
cur.execute("SELECT GET_LOCK(%s, %s) AS got", [LOCK_NAME, LOCK_WAIT_SECONDS])
if not cur.fetchone()["got"]:
    print("Another recompute is still running; this one will be picked up by it.", file=sys.stderr)
    return 0
run_ids = claim_runs(cur)
...
rows, cars = compute(cur, settings, today)
# Replace the whole forecast in one transaction: readers see the old or the new one.
cur.execute("DELETE FROM tbl_price_forecast")
cur.executemany("INSERT INTO tbl_price_forecast (VehicleId, ForecastDate, BasePrice, Multiplier, Price, Reasons, IsAvailable, RunId)"
                " VALUES (%s, %s, %s, %s, %s, %s, %s, " + str(int(run_ids[-1])) + ")", rows)
...
finish_runs(cur, run_ids, "done", cars, len(rows), ...)
conn.commit()
```

With the eight active cars, a run writes 480 rows. If the forecast is missing for a day, for example beyond the 60-day period, the pricing module uses the base price for that day and writes a note to the server log, so that the site continues to work even if the Python component is unavailable. A forecast row computed for an older base price is ignored for the same reason.

## 5.9 Machine-Learning Experiments

### 5.9.1 Purpose and Data

Because Rento has only four real bookings, two of them confirmed, the live site prices with the rules described in Section 5.7, and the learned pricer is switched off until about 200 bookings exist. The machine-learning experiments were carried out offline to determine which kind of model should replace or complement the rules at that point. They were implemented in a single script, ml/train_evaluate.py, which uses a fixed random seed of 42 so that the results can be reproduced, and which saves all results and charts for this report. Two external datasets were used; neither contains Rento bookings.

The first dataset is an hourly vehicle rental demand dataset, [INSERT demand dataset name and URL] ([INSERT citation]). The August prototype had prepared it into 18,247 labelled hourly rows from 18 August 2018 to 28 February 2021, with calendar features and features for Nepali festivals. Only features that are known in advance were used, such as the hour, the day of the week, the month, the day of the year, whether the day is a weekend or a festival, and cyclical encodings of the hour, weekday and month, because Rento prices up to 60 days ahead. The second dataset consists of 1,640 public listings collected from the Sajilo Rental website in August 2026, covering 192 vehicle models of 32 manufacturers, with the listed prices for four hours, eight hours and one day, the distance limits and the estimated fuel cost per kilometre. The prototype's preprocessing did not use the listed day price directly as the target. Instead, it derived a day price for each listing from its four-hour price: the hourly rate was rounded up, multiplied by 24 and multiplied by the median ratio between the listed day and four-hour hourly rates in the data. This derived day price, which the prototype's neural network was trained to predict, was kept as the target so that all models could be compared with the saved network on the same task.

### 5.9.2 Models and Evaluation Method

Four models were compared with a simple baseline in each experiment, as listed in Table 5.5. The baselines follow the advice of Hyndman and Athanasopoulos (2021) to compare every method with a simple benchmark: the mean demand for the same weekday and hour, the mean daily demand for the same weekday, and the median price.

Table: Table 5.5 Models used in the experiments {widths=1.5,4.25}
| Model | Configuration |
|---|---|
| Baseline | Demand: mean of the training data for the same weekday and hour (hourly) or weekday (daily). Price: median of the training prices |
| Linear Regression | scikit-learn, with standardised numeric features and one-hot encoded categories |
| Random Forest | 300 or 400 trees, minimum 2 or 3 samples per leaf |
| Gradient Boosting | 300 or 400 trees, learning rate 0.03 or 0.05, maximum depth 3 or 4, subsample 0.9 for demand |
| Multilayer perceptron | The PyTorch network trained in the August prototype, loaded as saved and not retrained (price experiment only) |

The demand data were split chronologically, with the last 20% of the timeline as the test set, and the models were also evaluated with 5-fold time-series cross-validation on the training period (Bergmeir & Benítez, 2012). The price data were split with the same 70/15/15 split and random state as the prototype's neural network, so that all models were scored on the same 246 test listings. Because a random split places listings of the same vehicle model in both the training and the test set, the price models were also evaluated with 5-fold cross-validation grouped by vehicle model, which measures how well prices are predicted for models that were never seen in training. All models were compared with the MAE, RMSE and R² metrics defined in Section 2.3.3.

### 5.9.3 Results: Demand

Table 5.6 shows the results for hourly demand. The mean demand was 74.0 rentals per hour. Gradient Boosting achieved the best results on the test set, but it improved on the seasonal baseline only slightly, from an R² of 0.228 to 0.285, and Linear Regression performed worse than the baseline. In the time-series cross-validation, Linear Regression was very unstable, with a mean R² of −7.1, while the tree ensembles were stable but weak, with mean R² values of 0.124 for Random Forest and 0.070 for Gradient Boosting. The most important features were the day of the year and the hour.

Table: Table 5.6 Results of Experiment 1: hourly demand (test set, 3,650 hours)
| Model | MAE | RMSE | R² |
|---|---|---|---|
| Seasonal baseline | 30.95 | 39.60 | 0.228 |
| Linear Regression | 31.39 | 40.22 | 0.203 |
| Random Forest | 30.57 | 38.48 | 0.271 |
| Gradient Boosting | 30.46 | 38.09 | 0.285 |

Because Rento sets prices per day, the same demand was also summed per day, using the 654 days on which at least 20 of the 24 hours were recorded; the last 131 days formed the test set. Table 5.7 shows that the daily task was easier to predict: Random Forest explained 49% of the variance of daily demand, compared with 11% for the weekday baseline.

Table: Table 5.7 Results of Experiment 1b: daily demand (test set, 131 days)
| Model | MAE | RMSE | R² |
|---|---|---|---|
| Weekday baseline | 267.45 | 430.65 | 0.114 |
| Linear Regression | 307.72 | 407.01 | 0.209 |
| Random Forest | 251.25 | 327.28 | 0.489 |
| Gradient Boosting | 269.61 | 347.29 | 0.424 |

![Figure 5.8 Actual and predicted daily demand on the test period](figures/ml_demand_daily_actual_vs_predicted.png){width=5.7}

### 5.9.4 Results: Price

Table 5.8 shows the results for the derived day price. The target has only seven distinct values, between Rs 7,360 and Rs 12,800, because the four-hour prices on the marketplace take only a few values, in line with the nearly flat prices by category observed in Section 2.2.4. Because the target is a fixed function of the four-hour price, which is not one of the features, the task amounts to predicting a listing's price level from its vehicle model, manufacturer, distance limit and fuel cost. On the shared test set, all learned models performed far better than the median baseline, and Random Forest was the most accurate, with an MAE of Rs 100.60 and an R² of 0.981; the prototype's neural network reached an R² of 0.963. The grouped cross-validation, however, gave a much less optimistic picture. For vehicle models not seen in training, Gradient Boosting reached a mean MAE of Rs 527 and a mean R² of 0.461, Random Forest an R² of 0.246, and Linear Regression performed worse than a constant prediction. The most important feature was the estimated fuel cost per kilometre, which reflects the size of the vehicle.

Table: Table 5.8 Results of Experiment 2: day price (test set, 246 listings)
| Model | MAE (Rs) | RMSE (Rs) | R² |
|---|---|---|---|
| Median baseline | 1,412.68 | 2,075.44 | −0.312 |
| Linear Regression | 226.82 | 561.80 | 0.904 |
| Random Forest | 100.60 | 251.31 | 0.981 |
| Gradient Boosting | 198.59 | 313.95 | 0.970 |
| Multilayer perceptron (prototype) | 134.07 | 347.03 | 0.963 |

![Figure 5.9 Comparison of the price models on the test set](figures/ml_price_model_comparison.png){width=5.7}

### 5.9.5 Discussion

The experiments support three conclusions for Rento. First, tree ensembles were the most accurate models in all three tasks, which agrees with the findings of Grinsztajn et al. (2022) for tabular data; the neural network did not outperform them. Second, the gain over simple baselines was small for hourly demand and moderate for daily demand, which is consistent with the observation of Makridakis et al. (2018) that machine-learning methods often gain little over statistical benchmarks, and which supports the decision to rely on transparent rules until Rento's own data become available. Third, the high accuracy of the price models on a random split is largely due to the same vehicle models appearing in both sets, to the few distinct price levels and to a target that is derived from the four-hour price rather than set independently; for new vehicle models the error is much larger. A learned pricer for Rento will therefore need to be trained on Rento's own bookings, and it should be evaluated with time-ordered and grouped validation rather than with a random split. The pricing settings already contain a switch for a learned model and a threshold of 200 bookings, and the model health panel shows the progress towards it; the model remains locked until then.

## 5.10 Recommendation Engine

### 5.10.1 Trip Parser

The trip parser reads the trip description, which is converted to lower case, and extracts the criteria listed in Table 5.9 with keyword rules and regular expressions. A vehicle type that the customer names explicitly, such as "SUV" or "jeep", counts as requested; otherwise a type is inferred from the words that describe the trip and marked as a suggestion. The rules are kept identical in the JavaScript and PHP versions of the parser.

Table: Table 5.9 Criteria extracted by the trip parser {widths=1.3,4.45}
| Criterion | Rule (examples) |
|---|---|
| Road type and inferred car type | Mountain and rough roads, SUV: "mountain", "hill", "off road", "Mustang", "Jomsom", "Manang", "trek". Highway, sedan: "highway", "long drive", "business", "airport", "Pokhara", "Lumbini". City, hatchback: "city", "commute", "Kathmandu", "Bhaktapur", "parking", "solo" |
| Named car type | "hatchback", "sedan", "SUV" or "jeep", "MUV", "crossover", "coupe" or "sports car", "pickup", "van" |
| Budget per day | An amount such as "under Rs 8000" or "8k"; "cheap" is read as Rs 5,500 and "luxury" as Rs 15,000 |
| Travellers | A number of people, such as "6 people" |
| Context | Family ("family", "kids", "parents"), luggage ("luggage", "bags", "airport"), business ("business", "meeting", "wedding"), group ("group", "friends") and comfort ("comfortable", "long drive", "luxury") |
| Fuel | Petrol, diesel or electric when mentioned |

### 5.10.2 Scoring

The engine first removes the cars that cannot meet the hard requirements: cars with too few seats for the travellers, or fewer than five seats for a family or group trip; cars that are booked on the chosen dates; and cars whose price is more than 10% over the budget. Each remaining car is then scored with the weighted model of Equation 2.8, using only the criteria that apply to the trip, with the weights in Table 5.10.

Table: Table 5.10 Criteria and weights of the recommendation engine {widths=1.3,0.75,3.7}
| Criterion | Weight | Partial score |
|---|---|---|
| Car type | 30 if named, 15 if inferred | 1 for the same type, a similarity between 0.2 and 0.8 for related types (for example 0.7 for a crossover when an SUV is wanted) |
| Seats | 15 | 1 with up to two spare seats, 0.85 with up to four, 0.7 otherwise |
| Budget | 20 | 1 up to 80% of the budget, falling to 0.9 at the budget, 0.4 when up to 10% over |
| Road | 15, or 25 on mountain roads | Suitability of the car type for city, highway or mountain roads; on mountain roads only cars flagged as rugged score 1 and others at most 0.6 |
| Comfort and luggage | 10 | Comfort or luggage space of the car type, for family, business, comfort or luggage trips |
| Fuel | 10 | 1 for the requested fuel, otherwise 0 |
| History | 10 | 0.7 if the customer has rented the same type before and 0.3 for the same brand |
| Age | 5 | Newer models score higher, falling to 0 for a car 12 years old |

For every criterion that contributes, the engine records a reason in plain language, and for weak points it records a caution, such as "Not a rugged 4x4: fine on rough patches, less suited to mountain tracks". Figure 5.10 shows how the budget and road criteria are scored and explained. The cars are then sorted by score and, for equal scores, by price, and matches below 40% are removed. Cars that are rented only with a driver are listed separately as suggestions to request with a driver. The engine is read-only: it never writes to the database, unlike the prototype, which overwrote the car type of every vehicle each time it was used.

```Figure 5.10 Code excerpt: scoring and explaining the budget and road criteria (includes/recommendation_engine.php)
if ($budget) {
    $share = $price / $budget;
    $add('budget', $share <= 0.8 ? 1.0 : ($share <= 1 ? 1 - 0.5 * ($share - 0.8) : 0.4));
    ...
    if ($share <= 1) $reasons[] = "$priceText, within your " . format_price($budget) . ' budget';
    else $cautions[] = "$priceText, slightly over your " . format_price($budget) . ' budget';
}
if ($road) {
    $suit = self::ROAD_SUITABILITY[$road][$kind] ?? 0.5;
    $rugged = !empty($car->Rugged) && !$sports;
    if ($road === 'mountain') {
        // Rough and mountain roads: being built for them matters more than the body type.
        $suit = $rugged ? 1.0 : min($suit, self::ROAD_NOT_RUGGED);
    }
    $add($road === 'mountain' ? 'roadMountain' : 'road', $suit);
    if ($road === 'mountain' && $rugged) $reasons[] = 'Rugged, off-road capable: built for mountain and rough roads';
    ...
}
```

## 5.11 Security Implementation

Security measures were implemented throughout the system, beginning in Phase 0 with the weaknesses inherited from the template. Table 5.11 relates each measure to the risk it addresses, using the categories of the OWASP Top 10 (OWASP Foundation, 2021). Their effectiveness is tested in Chapter 6.

Table: Table 5.11 Security measures {widths=1.6,4.15}
| Risk | Measure |
|---|---|
| Weak password storage | Passwords hashed with bcrypt through password_hash; MD5 hashes from the template are verified once and replaced at the next login |
| Account takeover through password reset | Random reset token, stored only as a hash, valid for 30 minutes and for one use, sent by email; the same message is shown whether or not the email is registered |
| Brute-force login | At most 5 failed attempts per IP address or email address in 15 minutes for login, password reset and email checks |
| Cross-site request forgery | A random 32-byte token in the session, checked on every POST form with a constant-time comparison; all administrative actions use POST |
| Session attacks | Strict session mode, cookies only, HttpOnly and SameSite=Lax cookies (Secure over HTTPS), a new session identifier at login, separate sessions for administrators and customers |
| SQL injection | Prepared statements with bound parameters for every query with user input; filter values checked against allowed lists |
| Cross-site scripting | All user-controlled output escaped; HTML in content pages cleaned to an allowed set of tags |
| Price manipulation | Prices always calculated on the server and stored with the booking; browser values ignored |
| Malicious uploads | Images checked by extension, detected MIME type and image decoding, limited to 5 MB, stored under random names in folders where scripts cannot run |
| Exposure of internal files | Web server rules that deny access to the includes, logs, machine-learning and test folders and to Composer, PHPUnit and hidden files; database errors hidden from pages |
| Secrets | Database password and application key outside the repository; the SMTP password stored encrypted with the application key; least-privilege database user |

## 5.12 Other Quality Features

Several further features were implemented in Phase 6 to prepare the system for real use. For accessibility, all pages have a skip link, visible focus rings, correctly ordered headings, labelled form fields and announcements for dynamic messages, and they respect the reduced-motion setting. For search engines, each page has a canonical URL, a description, Open Graph and X card tags and structured data in JSON-LD, and the site provides a sitemap and a robots file. A dark mode follows the system setting and can be switched manually on both the public site and the administration area. Email is sent through PHPMailer over SMTP for password reset links and for booking and enquiry confirmations; it remains switched off until the administrator enters the SMTP settings. Draft Terms and Privacy pages for Nepal were prepared but cannot be published while they contain items marked for confirmation. Finally, a deployment guide and a production check, which reports settings that are unsafe for production, such as development mode, a site without HTTPS, a missing application key or email that is not set up, were written for moving the system from the local server to real hosting.

## 5.13 Summary

This chapter described the implementation of Rento. The code is organised into public pages, shared PHP modules, a JSON API, the administration area and the Python pricer, and the database was extended with 13 tables through 22 additive migrations. The customer modules show a price for every day and store the server-calculated price with each booking, and the administration modules give the operator control over the fleet, festivals, prices and content. The smart pricing engine applies seven types of factors within a −15% to +25% cap, and the forecast pipeline keeps 60 days of prices up to date for every car, safely and automatically. The machine-learning experiments showed that tree ensembles are the most suitable models, but that their advantage over simple baselines depends strongly on the task and the validation method, which justifies the rule-based pricer until Rento's own data are available. The recommendation engine combines a keyword-based trip parser with weighted, explained scoring, and the security measures address the main risks of a web application. Chapter 6 describes how the system was tested and evaluated.
