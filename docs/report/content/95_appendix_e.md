# APPENDIX E: DATASET SAMPLES

This appendix shows the first rows of the two datasets used in the machine-learning experiments of Section 5.9. Neither dataset contains Rento bookings or personal data.

## E.1 Hourly Demand Dataset

Source: [INSERT demand dataset name and URL], prepared by python/demand_preprocessing.py into python/train_features.csv (18,247 labelled rows). A selection of the 20 columns is shown; the cyclical encodings (hour_sin, hour_cos, dow_sin, dow_cos, month_sin, month_cos) and month, week_of_year, quarter and month-start/end flags are omitted.

Table: Table E.1 First rows of the hourly demand dataset {widths=1.0,0.5,0.65,0.8,0.75,0.75,0.75,0.8}
| date | hour | demand | day of week | is weekend | is festival | day of year | season |
|---|---|---|---|---|---|---|---|
| 2018-08-18 | 9 | 91 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 10 | 21 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 13 | 23 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 14 | 104 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 15 | 81 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 16 | 37 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 17 | 27 | 5 | 1 | 0 | 230 | monsoon |
| 2018-08-18 | 18 | 66 | 5 | 1 | 0 | 230 | monsoon |

## E.2 Sajilo Rental Price Dataset

Source: public listings of Sajilo Rental (n.d.), collected with python/scraper.py in August 2026 and cleaned by python/preprocessing.py into python/clean_vehicles.csv (1,640 rows). The target of Experiment 2, scaling_1day, is the day price derived by the preprocessing from the four-hour price, as explained in Section 5.9.1.

Table: Table E.2 First rows of the cleaned price dataset {widths=1.05,0.8,0.55,0.55,0.55,0.55,0.55,0.6,0.55}
| vehicle name | manufacturer | price 4hr | price 8hr | price 1day | max km 4hr | fuel cost per km | base price per hour | scaling 1day |
|---|---|---|---|---|---|---|---|---|
| ALTO | Suzuki | 4500 | 5500 | 8000 | 100 | 21.7 | 1150 | 7360 |
| Sorento | Kia | 5000 | 6500 | 8000 | 80 | 21.7 | 1250 | 8000 |
| Mahindra Scorpio | Mahindra | 7500 | 8000 | 8000 | 50 | 19.73 | 1900 | 12160 |
| Toyota New Hiace | Toyota | 5000 | 10000 | 10000 | 50 | 21.7 | 1250 | 8000 |
| Maruti Suzuki | Suzuki | 5000 | 6500 | 8000 | 80 | 21.7 | 1250 | 8000 |
| Toyota Land Cruiser | Toyota | 6000 | 9000 | 10000 | 80 | 21.7 | 1500 | 9600 |
| Nissan Van | Nissan | 5500 | 7500 | 8500 | 100 | 21.7 | 1400 | 8960 |
| Aspire | Ford | 5000 | 6500 | 8000 | 80 | 21.7 | 1250 | 8000 |
