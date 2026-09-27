# Rento market research (Nepal)

Collected 2026-09-27 for Rento's fleet, pricing and packages. Reuse it for the Phase 5
"below market" badge and when prices are reviewed. Every price is in Nepalese rupees
(Rs) and was read from the page cited, on the date shown. Rento's own suggestions are
marked as suggestions or *(inferred)*. No competitor descriptions or photos were copied.

What was added to the database from this research (all **inactive**, hidden from the
website until activated in admin):
- Brands: Hyundai, Tata, BYD, Deepal, Mahindra (Suzuki models use the existing "Maruti" brand).
- 17 vehicles: `database/migrations/007_market_vehicles.sql`. (The Toyota Fortuner row was
  skipped because the fleet already has an active Toyota Fortuner.)
- 16 packages: `database/migrations/009_packages.sql`.

## Benchmarks for the "below market" badge (Phase 5)

Daily price per vehicle category. "With driver" rates come from the Sajilo data and the
with-driver operators below; "self-drive" is Self Drive Nepal, the only self-drive rate
card found. Fuel is excluded unless stated.

| Category | Self-drive (Rs/day) | With driver (Rs/day) | Rento suggested self-drive (Rs/day) |
|---|---|---|---|
| Hatchback | 5,000 (valley) / 6,000 (outside) | 8,000 (fuel extra) | 4,000–5,500 (EV 5,500–7,500) |
| Sedan | 5,000 / 6,000 | 5,000–8,000 (all-inclusive) · 8,000 (fuel extra) | 5,000–5,200 |
| SUV, manual | 7,000 / 8,000 | 8,500 (fuel extra) | 6,500–7,000 |
| SUV, automatic / EV | 10,000 / 12,000 | 10,000 (EV, charging incl.) | 7,000–8,500 |
| 7-seat SUV / MUV (Scorpio, Innova, Fortuner) | 10,000 (Scorpio) | 8,000–12,000 | 8,500–11,000 |
| Pickup | – | 8,000 (fuel extra) | 9,000 |
| Van (Hiace) | – | 7,500–12,000 | 9,500 (probably driver-only, see licence note) |

A car can be called "below market" when its self-drive price is under the self-drive
benchmark for its category (or, where none exists, at least 25% under the with-driver rate).

## Part 1: Vehicles and daily prices

### V1. Sources

| # | URL | What it provided | Accessed |
|---|-----|------------------|----------|
| S1 | local file `python/sajilo_vehicle_dataset.csv` (mtime 2026-08-27 08:33; `scraper.py` mtime 2026-08-27 07:16) | Sajilo Rental scrape: 1,640 listings, vehicle IDs 1–1770 | 2026-09-27 |
| S2 | https://sajilorental.com/vehicle/1 | Listing detail for an Alto: 4hr Rs 4,500 (≤55 km), 8hr Rs 5,500 (≤210 km), 1 day Rs 8,000 (≤210 km), extra km Rs 0, fuel estimate Rs 21.70/km, a named driver included, "daily-basis rentals require you to bear fuel cost", payment 10% within 24h + 40% before trip, Rs 5 lakh/person trip insurance | 2026-09-27 |
| S3 | https://sajilorental.com/ | Marketplace home page: "3,500+ verified vehicles", categories include EV hatchback/sedan/SUV, free cancellation up to 24h before. Self-drive is not mentioned. | 2026-09-27 |
| S4 | https://selfdrivenepal.com/news/car-rental-price-in-nepal-for-self-drive (article dated 2025-12-15) | Self-drive rate card by category, inside/outside valley. Fuel, tolls, parking and charging not included. Security deposit Rs 10,00,000, 3-year licence, 2 signed cheques | 2026-09-27 |
| S5 | https://selfdrivenepal.com/news/jeep-scorpio-rental-in-kathmandu-nepal-self-drive-and-rental-with-driver | Scorpio self-drive "from Rs 10,000/day" in Kathmandu. With driver "from Rs 8,000" outside the valley. It also shows "Rs 2,000" with driver inside Kathmandu, which is probably a typo or an hourly figure. | 2026-09-27 |
| S6 | https://rentinnepal.com/electric-car-rental | EV rental with driver: EV car Rs 8,000/day, EV SUV Rs 10,000, EV van Rs 15,000. Hourly rates Rs 1,200/1,500/2,500 (4h minimum). Daily = 8h, 120 km max, 3-day minimum. Charging included. 13% VAT if requested, 25% night surcharge, customer pays driver food and lodging on overnight trips | 2026-09-27 |
| S7 | https://vehiclehiringnepal.com/how-much-does-it-cost-to-hire-a-car-in-nepal/ (dated 2026-09-09) | All-inclusive with driver and fuel: sedan Rs 5,000–8,000, premium SUV (Fortuner, Tucson) Rs 8,000–12,000, 4WD jeep Rs 8,000–28,000+, Hiace Rs 7,500–12,000, Coaster Rs 15,000–22,000. Hourly: car Rs 600 (5h min), jeep Rs 800, Hiace Rs 850. Mentions driver bhatta of Rs 1,500–2,500/night at other operators | 2026-09-27 |
| S8 | https://www.kathmanducarservices.com/car-rental-prices-in-kathmandu/ (dated 2026-09-26) | Per-destination fares with driver, including fuel, parking and tolls. Car examples: airport transfer Rs 1,500, Bhaktapur Rs 3,000, Kirtipur Rs 4,000, Dakshinkali/Chapagaun Rs 5,000. Hiace/Jeep examples: Rs 2,500–10,200 | 2026-09-27 |
| S9 | https://nepalvehiclerental.com/price/ | **Unreachable.** The page returned only a bot-verification wall. | 2026-09-27 |
| S10 | https://highwayrental.luxury/ | **Unreachable.** The SSL certificate had expired. | 2026-09-27 |
| S11 | https://sparkcar.org/blog/ev-%20rental-price-in-kathmandu-nepal | Fetched, but the page had no price content (only a shell) | 2026-09-27 |
| S12 | https://www.en.meroauto.com/nepals-five-most-imported-electric-suvs-of-2026/ (2026-09-09) | Most-imported EV SUVs, 1 Jan–15 Aug 2026 | 2026-09-27 |
| S13 | https://www.en.meroauto.com/ev-surge-byd-dominates-as-nepal-sees-record-2746-electric-cars-imported-ahead-of-budget/ (2026-06-04) | May 2026 EV imports by brand (customs data) | 2026-09-27 |
| S14 | https://bestsellingcarsblog.com/2025/12/nepal-fiscal-year-2081-82-bevs-account-for-73-of-sales/ | FY 2081/82: BEVs are 73% of the market; top EV brands and models | 2026-09-27 |
| S15 | https://techmandu.com/electric-car-market-share-in-nepal/ (2024-05-28) | 2023 EV imports by brand (6,762 units) | 2026-09-27 |
| S16 | https://thulobazaar.com.np/en/blog/top-10-popular-cars-nepal-2026 (2025-12-29) | Popularity list for 2026. It is not sales data and cites no source. | 2026-09-27 |
| S17 | https://meromoto.com/blog/nepal-car-sales-q2-2025 | Fetched, but the article body did not render, so I have no data from it | 2026-09-27 |

Search results also pointed to international aggregators (Skyscanner, KAYAK) quoting self-drive cars in Kathmandu from about INR 3,600–4,600/day. I did not fetch those pages, so I have not used their figures.

---

### V2. Sajilo dataset summary (S1)

- **When it was scraped:** file mtime is **2026-08-27** (scraper written the same morning, `CURRENT_YEAR = 2026`).
- **Size:** 1,640 valid listings from vehicle IDs 1–1770, with 189 distinct normalised `vehicle_name` values. Sajilo is a **marketplace**: many owners list the same model, which is why names repeat (for example, 314 Scorpio listings).
- **Data quality:**
  - `vehicle_type` holds scraped navigation text and is unusable.
  - `fuel_type` is always the string "cost".
  - `manufacturer` is empty for 613 rows (37%).
  - `manufacture_year` is present for only 48 rows (2008–2024).
  - `extra_km_price` is always 0.
  - `max_km_4hr` sometimes holds a price (5,500 in 225 rows), so the km columns are shifted for some listings.
  - 59 rows have price 0 ("on request").
- **Manufacturer counts (where filled):** Mahindra 368, Tata 229, Toyota 156, Suzuki 96, Nissan 48, Hyundai 19, Honda 16, NETA 13, Datsun 11, VW 11, Chevrolet 10, BMW 10, Isuzu 9, BYD 7.
- **price_1day** (excluding 0s): n=1,581, min Rs 6,500, median Rs 8,000, mean Rs 8,489, max Rs 18,000. The distribution is highly standardised: Rs 8,000 ×1,004, Rs 8,500 ×224, Rs 10,000 ×163, Rs 12,000 ×110, Rs 6,500 ×76.
- **Km allowance per day:** 180–210 km (200 ×706, 190 ×446, 210 ×413). Per 8h: 100–210 km. Per 4h: 50–100 km.
- **Fuel estimate** shown to the customer: Rs 21.7/km for most listings, Rs 19.73 for some, Rs 36.17 for large vehicles, about Rs 1.3–2.2/km for EVs.

### Top models by listing count (price_1day, Rs)

| Model (as listed) | Listings | Min | Median | Max |
|---|---|---|---|---|
| Mahindra Scorpio | 314 (+6 variants) | 8,000 | 8,000 | 8,000 |
| Toyota New Hiace | 118 | 10,000 | 10,000 | 10,000 |
| Tata 1512 Super Deluxe (bus) | 104 | 12,000 | 12,000 | 12,000 |
| "Maruti Suzuki" (unspecified) | 82 | 0 | 6,500 | 8,500 |
| Mahindra Bolero Pickup | 42 | 0 | 8,000 | 8,000 |
| Suzuki Brezza | 41 | 8,500 | 8,500 | 8,500 |
| Suzuki Dzire | 40 | 8,000 | 8,000 | 8,000 |
| Hyundai Santro | 40 | 8,000 | 8,000 | 8,000 |
| Suzuki Alto | 38 | 8,000 | 8,000 | 8,000 |
| Suzuki Swift | 32 | 8,000 | 8,000 | 8,000 |
| Suzuki Eeco Cargo | 26 | 0 | 6,500 | 6,500 |
| Ford EcoSport | 23 | 8,500 | 8,500 | 8,500 |
| Skoda Rapid | 21 | 8,000 | 8,000 | 8,500 |
| Ford Figo | 20 | 8,000 | 8,000 | 8,000 |
| Nissan Magnite | 20 | 8,000 | 8,500 | 8,500 |
| Tata Nexon | 20 | 8,000 | 8,500 | 8,500 |
| Hyundai i10 / Grand i10 | 19 / 16 | 8,000 | 8,000 | 8,000 |
| Suzuki Celerio | 18 | 8,000 | 8,000 | 8,000 |
| Suzuki WagonR | 17 (+2) | 8,000 | 8,000 | 8,000 |
| Nissan Kicks | 14 | 8,000 | 8,500 | 8,500 |
| Tata Nexon EV | 13 (+1) | 8,000 | 8,000 | 8,000 |
| Tata Safari | 13 | 0 | 0 | 10,000 |
| Suzuki Baleno | 13 (+1) | 8,000 | 8,000 | 8,000 |
| Tata Sumo | 12 (+1) | 8,000 | 10,000 | 10,000 |
| Tata Tiago EV | 12 | 8,000 | 8,000 | 8,000 |
| Neta V | 12 | 8,000 | 8,000 | 8,000 |
| Toyota Land Cruiser | 12 | 10,000 | 10,000 | 10,000 |
| BYD Atto 3 | 10 | 8,000 | 8,000 | 8,000 |
| MG ZS EV | 9 (+1) | 8,000 | 8,000 | 8,500 |
| Hyundai Creta (all years) | 25 | 8,500 | 8,500 | 8,500 |
| BYD Dolphin | 7 | 8,000 | 8,000 | 8,000 |
| Kia Seltos | 6 | 8,500 | 8,500 | 8,500 |
| Kia Sonet | 6 | 8,500 | 8,500 | 8,500 |
| Toyota Hilux | 6 | 8,000 | 8,000 | 8,000 |
| Toyota Innova | 2 | 8,000 | 8,000 | 8,000 |
| Toyota Fortuner | 1 | 8,500 | 8,500 | 8,500 |

The dataset also has a long tail of 1–5 listings each, including KYC/Foton EV vans (Rs 10,000), Sutlej 35-seat bus (Rs 18,000), Tata Punch, Isuzu D-Max/V-Cross, Omoda, Dongfeng Nammi, Seres 3 and BYD e6.

**How Sajilo prices (S2, S3):**
- Every listing is **with driver**; the detail page names the driver.
- For daily rentals **the customer pays fuel**.
- About 200 km/day is included, and the extra-km charge is shown as Rs 0.
- No self-drive option is advertised.
- Pricing is almost flat by category, not by model. Rs 8,000 covers any hatchback, sedan or Scorpio; Rs 8,500 covers compact SUVs; Rs 10,000 covers Hiace, Land Cruiser and Sumo.

---

### V3. Competitor table

| Company (URL) | Basis | Fuel incl.? | Km limit | Brands/models | Daily price (Rs) | Extras | Accessed |
|---|---|---|---|---|---|---|---|
| Sajilo Rental (sajilorental.com) | With driver | No, customer pays fuel (about Rs 21.7/km estimate) | 180–210 km/day | Scorpio, Hiace, Swift, Dzire, Alto, Brezza, Creta, Nexon/Nexon EV, Atto 3, Dolphin, Neta V, Land Cruiser, buses | Hatch/sedan/Scorpio 8,000; compact SUV 8,500; Hiace/Land Cruiser 10,000; bus 12,000–18,000; 4hr from 4,500; 8hr 5,500–8,000 | 50% advance; late-return fee | 2026-09-27 |
| Self Drive Nepal (selfdrivenepal.com) | **Self-drive** (with-driver also offered) | No; fuel, tolls, parking and charging extra | Not stated | Hatch: Freestyle, i10, Santro, Celerio. Sedan: Ciaz, Amaze, Vento. SUV: Brezza, Kicks, Magnite, Kushaq, Duster, Creta, DFSK Glory 580. Auto SUV: Seltos, Kushaq, MG ZS. EV: Atto 3, Nexon EV, Dolphin, Seres 3, Niro EV, Creta EV. Scorpio | Hatch/sedan 5,000 in valley (6,000 outside); manual SUV 7,000 (8,000); auto/luxury SUV 10,000 (12,000); EV SUV 10,000 (12,000); Scorpio self-drive from 10,000; Scorpio with driver from 8,000 outside valley | Deposit Rs 10 lakh; 3-yr licence; 2 cheques | 2026-09-27 |
| Rent in Nepal (rentinnepal.com), EV page | With driver | Yes, charging included | 8 h and 120 km/day; 3-day minimum | Neta V, Nissan Leaf, Tata Tiago EV, Deepal, Omoda, MG, BYD Atto 3, Hyundai Kona EV, EV vans 7–16 seats | EV car 8,000; EV SUV 10,000; EV van 15,000; hourly 1,200/1,500/2,500 (4h min) | +13% VAT on request; +25% night (after 8pm / before 5am); driver food and lodging paid by customer on overnight trips | 2026-09-27 |
| Nepal Vehicle Hiring (vehiclehiringnepal.com) | With driver | Yes, all-inclusive (fuel, parking, tolls) | Not stated (itinerary-based) | Sedan, Fortuner, Tucson, 4WD jeep, Hiace, Coaster, bus | Sedan 5,000–8,000; premium SUV 8,000–12,000; 4WD 8,000–28,000+; Hiace 7,500–12,000; Coaster 15,000–22,000 | Says it has no extras; notes other operators charge driver bhatta Rs 1,500–2,500/night | 2026-09-27 |
| Kathmandu Car Services (kathmanducarservices.com) | With driver | Yes (fuel, parking, tolls) | Per destination | Car, Hiace/Jeep, Coaster, Sutlej bus | Per-trip fares, not per day: car 1,500 (airport) to 5,000 (Dakshinkali); Hiace/Jeep 2,500–10,200 | Suggests asking for a fixed quote | 2026-09-27 |
| nepalvehiclerental.com | Unreachable (bot wall) | – | – | – | – | – | 2026-09-27 |
| highwayrental.luxury | Unreachable (expired SSL certificate) | – | – | – | – | – | 2026-09-27 |

---

### V4. Popular in Nepal (2025–2026)

- **EVs dominate new sales.** Battery EVs were **73% of the Nepali car market in FY 2081/82** (mid-Apr 2024 to mid-Apr 2025). [S14]
  - Top EV brands: Tata, BYD, MG, Dongfeng, Changan Deepal.
  - Top EV models: Tata Xpres-T (about 1,300 units, taxi), BYD Dolphin, MG S5 EV, BYD Atto 3, Tata Punch EV.
- **2023 EV imports** (6,762 units): Tata 38% (2,557), BYD 32% (2,177), MG 17% (1,127), Neta 4%, Seres 3%. [S15]
- **May 2026 EV imports** (2,746 units, customs data): BYD 50.8% (1,396: Sealion 7, Atto 1, Atto 2, Atto 3), Tata 10.4% (286), Deepal 9.0% (246), Dongfeng 7.7% (210), MG 4.9% (134). [S13]
- **Most-imported EV SUVs, 1 Jan–15 Aug 2026:** [S12]
  1. BYD Atto 2: 1,403
  2. Dongfeng Nammi Vigo: 489
  3. iCAUR V23: 462
  4. Deepal S05: 454
  5. Tata Punch EV: 392

  The market is shifting from small EV hatchbacks to compact SUVs.
- **Popular petrol and diesel cars** (popularity list, not sales data) [S16]: Suzuki Swift, Toyota Hilux, Honda WR-V, Hyundai Creta, Suzuki Dzire, Toyota Fortuner, BYD Atto 3, Kia Sonet, Renault Kwid, Mahindra Scorpio Classic.
- **What the rental market actually runs** (S1 listing counts): Mahindra Scorpio by far, then Toyota Hiace, Suzuki (Swift, Dzire, Alto, Brezza, WagonR, Celerio, Baleno), Hyundai (Santro, i10, Creta), Tata (Nexon, Tiago EV, Nexon EV), Bolero pickup, and Nissan (Magnite, Kicks). EVs in rental fleets: Nexon EV, Tiago EV, Neta V, Atto 3, MG ZS EV, Dolphin.

I found no official NADA model-level ICE sales table that was accessible. The ICE ranking above is based on S16 and on the rental listing counts.

---

### V5. Suggested fleet table (Rento self-drive)

**How I set the prices.** The with-driver market sits at about Rs 8,000–8,500 for cars and SUVs and about Rs 10,000 for vans (S1, S6, S7). The only self-drive benchmark, Self Drive Nepal (S4), charges:
- Rs 5,000 for hatchbacks and sedans
- Rs 7,000 for manual SUVs
- Rs 10,000 for automatic, luxury or EV SUVs
- Rs 10,000 for the Scorpio

Rento's suggested prices sit **at or slightly below the self-drive benchmark** and **roughly 25–40% below the with-driver market**, because there is no driver cost. They exclude fuel and charging and include about 200 km/day, matching the Sajilo norm. EVs are priced a little above the equivalent ICE car because they are newer and automatic and the customer saves on fuel. They are still below Self Drive Nepal's Rs 10,000.

| # | Model | Brand | Type | Seats | Fuel | Typical year | Competitor range/day (Rs) and basis | Suggested Rento self-drive (Rs/day) | Sources |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Alto K10 | Suzuki | Hatchback | 5 | Petrol | 2021–2024 | 8,000 with driver, fuel extra (Sajilo) | 4,000 | S1, S2 |
| 2 | Swift | Suzuki | Hatchback | 5 | Petrol | 2022–2025 | 8,000 with driver, fuel extra; 5,000 self-drive hatch, fuel extra | 4,800 | S1, S4, S16 |
| 3 | Grand i10 Nios | Hyundai | Hatchback | 5 | Petrol | 2021–2024 | 8,000 with driver; 5,000 self-drive (i10) | 4,500 | S1, S4 |
| 4 | Tiago EV | Tata | Hatchback | 5 | Electric | 2023–2025 | 8,000 with driver (Sajilo, fuel extra; Rent in Nepal, charging incl., 120 km) | 5,500 | S1, S6 |
| 5 | Dolphin | BYD | Hatchback | 5 | Electric | 2024–2025 | 8,000 with driver; 10,000 self-drive EV | 7,500 | S1, S4, S14 |
| 6 | Dzire | Suzuki | Sedan | 5 | Petrol | 2021–2025 | 8,000 with driver; 5,000 self-drive sedan; 5,000–8,000 all-inclusive with driver | 5,000 | S1, S4, S7, S16 |
| 7 | Ciaz | Suzuki | Sedan | 5 | Petrol | 2019–2023 | 8,000 with driver; 5,000 self-drive (named model) | 5,200 | S1, S4 |
| 8 | Brezza | Suzuki | SUV | 5 | Petrol | 2022–2025 | 8,500 with driver; 7,000 self-drive manual SUV | 6,500 | S1, S4 |
| 9 | Creta | Hyundai | SUV | 5 | Petrol | 2021–2025 | 8,500 with driver; 7,000 self-drive manual SUV | 7,000 | S1, S4, S16 |
| 10 | Punch EV | Tata | Crossover | 5 | Electric | 2024–2026 | 8,000–8,500 with driver (Punch); 10,000 self-drive EV | 7,000 | S1, S4, S12, S14 |
| 11 | Atto 3 | BYD | SUV | 5 | Electric | 2023–2025 | 8,000 with driver; 10,000 with driver incl. charging; 10,000 self-drive | 8,500 | S1, S4, S6, S14 |
| 12 | Atto 2 | BYD | SUV | 5 | Electric | 2025–2026 | about 10,000 self-drive EV SUV, category rate (no model-specific price found) | 8,000 | S4, S12, S13 |
| 13 | Deepal S05 | Deepal (Changan) | SUV | 5 | Electric | 2025–2026 | 10,000 with driver incl. charging (Deepal listed); 10,000 self-drive EV category | 8,500 | S4, S6, S12, S13 |
| 14 | Scorpio / Scorpio-N | Mahindra | SUV | 7 | Diesel | 2020–2025 | 8,000 with driver, fuel extra (314 listings); self-drive from 10,000 | 8,500 | S1, S5, S16 |
| 15 | Innova Crysta | Toyota | MUV | 7 | Diesel | 2019–2023 | 8,000 with driver (Sajilo, 2 listings) | 9,000 | S1 |
| 16 | Fortuner | Toyota | SUV | 7 | Diesel | 2019–2023 | 8,500 with driver (Sajilo); 8,000–12,000 all-inclusive premium SUV | 11,000 | S1, S7, S16 |
| 17 | Hilux | Toyota | Pickup | 5 | Diesel | 2019–2024 | 8,000 with driver (Sajilo) | 9,000 | S1, S16 |
| 18 | Hiace (Commuter) | Toyota | Van | 14 | Diesel | 2018–2023 | 10,000 with driver (118 listings); 7,500–12,000 all-inclusive | 9,500 | S1, S7 |

The Hiace (14 seats) will need a heavy or public-vehicle licence category for self-drive in Nepal, so Rento may need to offer it only with a driver. A cheaper pickup alternative is the Mahindra Bolero Pickup at Rs 8,000 with driver on Sajilo (42 listings); a Rento self-drive price of about Rs 6,500 would fit.

---

### V6. With-driver findings

- **Yes, competitors mostly price with driver.**
  - All 1,640 Sajilo listings include a named driver.
  - Rent in Nepal (EV), Nepal Vehicle Hiring and Kathmandu Car Services are with-driver only.
  - Only Self Drive Nepal publishes self-drive rates, and it also offers drivers.
  - Self-drive with public prices is a thin niche, which is an opening for Rento.
- **What competitors charge extra for:**
  - **Fuel:** Sajilo daily rentals make the customer bear fuel, with an estimate of about Rs 21.7/km for cars and about Rs 36/km for large vehicles. Self Drive Nepal excludes fuel, tolls, parking and charging. Nepal Vehicle Hiring and Kathmandu Car Services include fuel, and Rent in Nepal includes charging.
  - **Km limits:** Sajilo includes about 180–210 km/day (50–100 km on 4h), and extra km shows as Rs 0. Rent in Nepal caps a day at 8 h and 120 km.
  - **Driver food and stay (overnight):** Rent in Nepal has customers prepay the driver's accommodation and food. Nepal Vehicle Hiring says other operators charge driver bhatta of Rs 1,500–2,500/night. A search-result snippet (not a fetched page) cited Tourist Vehicle Association overnight charges of about Rs 5,000/night for a Scorpio and Rs 6,000 for a Hiace, but I did not verify it.
  - **Night surcharge:** Rent in Nepal adds 25% for after 8pm or before 5am. Sajilo charges extra for late return.
  - **VAT:** Rent in Nepal adds 13% if a VAT bill is requested.
  - **Deposits and advances:** Sajilo takes 10% + 40% in advance. Self Drive Nepal (self-drive) takes a Rs 10 lakh security deposit plus cheques and a 3-year licence.

---

## Part 2: Packages and services

### P1. Sources

| # | Company | URL | Accessed | Notes |
|---|---|---|---|---|
| S1 | Sajilo Rental | https://sajilorental.com | 2026-09-27 | Marketplace homepage (vehicle types, 77 cities, carpool, host program) |
| S2 | Sajilo Rental | https://sajilorental.com/search?travel_from=kathmandu | 2026-09-27 | 4 hr / 8 hr / 1 day price ranges |
| S3 | Sajilo Rental | https://sajilorental.com/terms-of-service | 2026-09-27 | Payment and cancellation terms |
| S4 | Rent In Nepal | https://rentinnepal.com/kathmandu-to-pokhara-car-rentals | 2026-09-27 | KTM–Pokhara prices by vehicle |
| S5 | Rent In Nepal | https://rentinnepal.com/kathmandu-airport-transfer | 2026-09-27 | Airport transfer rates (2 hr) |
| S6 | Rent In Nepal | https://rentinnepal.com/religious-tour and /religious-tour/lumbini-tour | 2026-09-27 | Lumbini 3-day itinerary, incl./excl. |
| S7 | Rent In Nepal | https://rentinnepal.com/city-tour and /city-tour/kathmandu-to-nagarkot-tour | 2026-09-27 | Nagarkot tour incl./excl. |
| S8 | Rent In Nepal | https://rentinnepal.com/pokhara-to-muktinath-jeep-fare | 2026-09-27 | Muktinath jeep 2D/3D prices |
| S9 | Kathmandu Car Services | https://www.kathmanducarservices.com/car-rental-in-nepal-price/ | 2026-09-27 | Full price list (daily, airport, routes, wedding, surcharges) |
| S10 | Nepal Vehicle Hiring Pvt Ltd | https://vehiclehiringnepal.com/how-much-does-it-cost-to-hire-a-car-in-nepal/ | 2026-09-27 | Hourly, routes, wedding, monthly |
| S11 | Nepal Vehicle Hiring Pvt Ltd | https://vehiclehiringnepal.com/kathmandu-to-chitwan-transfer/ | 2026-09-27 | KTM–Chitwan by vehicle |
| S12 | Yatra Nepal | https://www.yatranepal.com/transportation-vehicle-booking/tourist-vehicle-renting-in-kathmandu | 2026-09-27 | Tariff table: airport, hourly, sightseeing, surcharges |
| S13 | Tourist Vehicle Service | https://www.touristvehicleservice.com/wedding-car-rental-in-kathmandu-cost/ | 2026-09-27 | Wedding car prices by model |

Could not access: nepalvehiclerental.com/price (bot-check wall). kathmanducarservices.com Gautam Buddha airport page (fetch failed because of a session limit). Sajilo Rental shows no tour, airport or wedding packages on its public pages. It is a per-vehicle marketplace only.

### P2. Competitor services table

| Company | Service | Route / stops | Duration | Price (Rs) & vehicle | Included | Excluded | Booking |
|---|---|---|---|---|---|---|---|
| Sajilo Rental (S2) | Hourly / day hire | Pickup from 14 locations (KTM, Pokhara, Chitwan…) | 4 hr / 8 hr / 1 day | 4 hr 4,500–7,800. 8 hr 5,500–9,360. 1 day 8,000–10,400 (range across listed vehicles) | AC, GPS, Bluetooth listed as vehicle features. VAT included "where applicable" (S3) | Renter responsible for traffic, parking and toll fines (S3). Fuel/driver terms not stated | App, website, phone +977 9741816117. Pay full or partial before dispatch via eSewa, Khalti, card or cash. Free cancellation up to 12 hr before (S3). Homepage says 24 hr (S1), which is inconsistent |
| Sajilo Rental (S1) | Carpool; host-your-vehicle | 77 cities | n/a | not shown | "Insurance coverage", 24/7 support | n/a | Online / app |
| Rent In Nepal (S4) | KTM → Pokhara one-way | Kalanki–Thankot–Malekhu–Muglin–Dumre–Damauli–Pokhara (~200 km) | 5–7 hr | Car 17,220. Van 25,830. Hiace/Jeep 30,135. Coaster 43,050. Bus 47,355 | Not itemised on page | Not itemised | Phone 015929580, WhatsApp, quote form |
| Rent In Nepal (S5) | KTM airport transfer | TIA ↔ city | 2 hr block | Car 1,200. Van 1,800. Hiace 2,100. Coaster 3,000. Bus 3,300 | Driver, insurance, baggage help, flight monitoring | Beyond 2 hr: not stated | Website, phone, WhatsApp. 20–50% advance for some vehicles/groups |
| Rent In Nepal (S6) | Lumbini pilgrimage | D1 KTM–Lumbini (Maya Devi Temple, Ashoka Pillar). D2 monasteries, museum, Peace Pagoda. D3 Tilaurakot, Kudan, Gotihawa, Niglihawa → KTM | 3 days | Price not listed. Vehicles: EV car (4), Jeep (7), EV van (11), Hiace (15), mini bus (25) | Private vehicle and driver, airport pickup/drop (KTM/Bhairahawa), fuel, parking, driver allowance, govt taxes | Accommodation, meals, entry fees, guide, insurance, tips. Page also lists "driver food/lodging" as excluded, which contradicts "driver allowance" included | Phone, WhatsApp, inquiry form |
| Rent In Nepal (S7) | Nagarkot sunrise | Hotel → Nagarkot viewpoint → breakfast → pine-forest walk → return | Day trip (~2 hr each way) | "Cars from NPR 4,000" | AC vehicle, driver, fuel, tolls, parking, hotel pickup/drop, water | Meals, entry fees, personal expenses, insurance, accommodation | Phone, WhatsApp, online form |
| Rent In Nepal (S6/S7) | Other listed tours (no prices) | Pathibhara Devi jeep, Kalinchowk jeep, KTM–Pokhara scenic drive, Pokhara city tour | n/a | not listed | Pickup & drop, driver, AC | n/a | Quote form |
| Rent In Nepal (S8) | Pokhara → Muktinath private jeep | Pokhara–Beni–Jomsom–Kagbeni–Muktinath (~197 km, 7–9 hr) | 2D/1N; 3D/2N | Jeep 32,000 (2D). Jeep 39,000 (3D) | Jeep, fuel, parking, driver, driver salary, driver accommodation (and meals) | ACAP permit (foreigners Rs 2,000 per page; Nepalis exempt), passenger food and lodging | WhatsApp +977 9768963977, phone, quote form |
| Kathmandu Car Services (S9) | Daily with driver | Valley / outside valley | per day | Economy sedan 3,500–4,500. Mid sedan 4,500–6,000. Compact SUV 5,500–7,000 (in) / 7,000–9,000 (out). Std SUV 8,000–11,000 (in) / 11,000–14,000 (out). Scorpio 10,000–13,000. Hiace 12,000–16,000 | Per-km fuel option Rs 45–80/km | Driver bhatta Rs 1,500–2,500/night (Rs 3,000+ remote). 13% VAT (varies). Hill surcharge +500–1,000/day. Mountain +500–3,000/day. One-way drop +2,000–4,000 | Direct booking on site, free cancellation |
| Kathmandu Car Services (S9) | Airport transfer (one-way) | TIA | one-way | Econ sedan 1,500–2,000. SUV 2,500–3,500. Luxury 4,000–6,000. Minivan 4,500–6,500 | n/a | n/a | same |
| Kathmandu Car Services (S9) | Route one-way | KTM–Pokhara. KTM–Chitwan. KTM–Lumbini | 4–7 hr | Pokhara: sedan 12,000–14,000, SUV 18,000–22,000, Hiace 22,000–25,000. Chitwan: sedan 14,500–16,000, SUV 18,000–22,000. Lumbini: sedan 11,000–14,000, SUV 15,000–19,000 | n/a | Chitwan in-park safari +3,000–5,000 | same |
| Kathmandu Car Services (S9) | Valley sightseeing | KTM valley | 4 hr/40 km. 8 hr/80 km | Half day 2,500–4,000. Full day 4,500–7,500 | n/a | n/a | same |
| Kathmandu Car Services (S9) | Muktinath / Upper Mustang | via Jomsom | per day | Muktinath 20,000–28,000/day. Lo Manthang 25,000–35,000/day | n/a | Upper Mustang RAP permit (~Rs 57,500 / 10 days). Self-drive not allowed | same |
| Kathmandu Car Services (S9) | Wedding / event | Valley | per day / event | BMW/Audi/Mercedes 25,000–40,000/day. Vintage decorated 15,000–25,000/event. White SUV convoy on request | n/a | n/a | same |
| Kathmandu Car Services (S9) | Long-term | n/a | 7 / 14 / 30 days | Discounts 10–15%, 15–20%, 20–30% off daily | n/a | n/a | same |
| Nepal Vehicle Hiring (S10) | Hourly | Valley | 5 hr minimum | Sedan 600/hr (min 3,000). Jeep 800/hr (min 4,000). Hiace 850/hr (min 4,250) | "All-inclusive": vehicle, driver, fuel, parking, tolls | n/a | WhatsApp/Viber +977 9851013196, "Plan a Trip" form |
| Nepal Vehicle Hiring (S10) | Airport transfer | TIA (15–20 km) | one-way | Car 1,500–3,000. Jeep 2,500–4,000. Hiace 3,500–5,500 | same | n/a | same |
| Nepal Vehicle Hiring (S10) | Sightseeing / day trips | KTM full/half day. Nagarkot sunrise. Bhaktapur 4–5 hr | day | KTM full day car 6,000–9,000. Half day 4,000–6,000. Nagarkot car 5,000–8,000. Bhaktapur car 5,000–8,000 | same | n/a | same |
| Nepal Vehicle Hiring (S10) | Pokhara–Jomsom/Mustang | 200 km | one-way (jeep) | 28,000–40,000 | same | n/a | same |
| Nepal Vehicle Hiring (S10) | Wedding hire | Valley | daily | 8,000–20,000 (decorated) | n/a | n/a | same |
| Nepal Vehicle Hiring (S10) | Monthly / corporate | n/a | 25–30 working days | Sedan 80,000–120,000. Jeep 120,000–180,000 | n/a | n/a | same. Peak season (Oct–Nov) +10–15% |
| Nepal Vehicle Hiring (S11) | KTM → Chitwan | 150–170 km | 4–5 hr | Sedan 12,000–15,000 (RT 20,000–26,000). SUV 14,000–18,000 (RT 24,000–31,000). Jeep 15,000–19,000. Hiace 20,000–30,000 | Hotel pickup, AC, driver, fuel, tolls, all driver expenses | n/a | Book 2–3 days ahead (5–7 days in peak season) |
| Yatra Nepal (S12) | Airport arrival/departure | TIA | 2 hr | Car 1,500. Van 2,000. Hiace/Jeep 2,500. Coaster 3,000. Bus 4,000. Mountain-flight transfer: car 2,500 | Vehicle, driver, AC | "Any kind of taxes" not included | Phone / email only |
| Yatra Nepal (S12) | Hourly disposal | Valley | 4 hr minimum | Car 600/hr. Van 900/hr. Hiace/Jeep 1,050/hr | same | Overtime hourly. 50% night surcharge after 8 PM. +25% gravel/hill roads. Overnight charge car 2,500 | same. 25% cancellation penalty |
| Yatra Nepal (S12) | Sightseeing | Half day 3–4 hr (Bhaktapur/Changunarayan, Nagarkot, Godavari). Full day 8 hr | half/full day | Half day 2,500–7,000 (Nagarkot 4,000–11,000). Full day 4,000–11,000 (range car → bus) | same | same | same |
| Yatra Nepal (S12) | Multi-day round trip | Pokhara–Palpa–Tansen–Lumbini–Chitwan | 4–5 days | 36,000–99,000 (car → bus) | same | same | same |
| Tourist Vehicle Service (S13) | Wedding car | Valley | per function (hours not stated) | Corolla/Accent 9,000. Dzire 13,000. Swift/Baleno 13,000–14,000. Skoda Rapid 14,000. Slavia 15,000. Brezza 14,000. Creta/MG ZS EV/BYD Atto 3 20,000–20,500. Vintage Mercedes 22,000. VW Beetle 25,000. Prado 50,000 | Driver. Decoration from Rs 4,000 extra | Overtime/fuel not stated | Phone, WhatsApp/Viber 9851003394 |

### P3. Observations

**How booking works**
- Most tour-transport operators (Rent In Nepal, Yatra Nepal, Nepal Vehicle Hiring, Tourist Vehicle Service) use a quote/inquiry form plus phone and WhatsApp/Viber. Few publish fixed package prices. Rent In Nepal hides prices on most tour pages.
- Only Sajilo Rental takes online payment (eSewa, Khalti, cards) with instant booking. Rent In Nepal asks for 20–50% advance on some bookings. Yatra Nepal charges a 25% cancellation penalty. Sajilo offers free cancellation up to 12 hr in its ToS and 24 hr on its homepage.
- Published fixed prices are a real differentiator. Showing price per vehicle type plus online advance payment (eSewa/Khalti) would put Rento ahead of most competitors.

**Common inclusions**
- Almost always included: driver, AC vehicle, hotel pickup/drop.
- "All-inclusive" operators also include fuel, tolls, parking and driver allowance (S7, S8, S10, S11).

**Common exclusions**
- Passenger meals and accommodation, monument/entry fees, guide, permits (ACAP, Upper Mustang RAP), travel insurance and tips.

**Frequent hidden extras**
- Driver bhatta Rs 1,500–2,500 per night (Rs 3,000+ remote).
- 13% VAT (varies by operator).
- Overtime per hour, 50% night surcharge after 8 PM, +25% hill/gravel roads.
- One-way drop fee Rs 2,000–4,000.
- Peak season (Oct–Nov) +10–15%.

**Price anchors**
- **Airport transfer (KTM, car):** Rs 1,200–2,000. SUV Rs 2,500–3,500. Van Rs 1,800–5,500.
- **Hourly:** Rs 600/hr car (4–5 hr minimum). Sajilo 4 hr Rs 4,500+.
- **Valley full day (8 hr, car):** Rs 4,500–9,000.
- **KTM–Pokhara one-way:** sedan Rs 12,000–17,220. SUV Rs 18,000–22,000. Hiace Rs 22,000–30,135.
- **Pokhara–Muktinath 3D jeep:** Rs 39,000, driver costs included.
- **Wedding sedan:** Rs 9,000–15,000. Luxury SUV Rs 20,000+. Decoration Rs 4,000+.
- **Monthly sedan with driver:** Rs 80,000–120,000.

### P4. Suggested Rento packages

Rento price basis:
- Multi-day tours are priced per vehicle, all-in for transport: driver, fuel, tolls, parking and the driver's food and stay.
- Tours exclude passenger hotels, meals, entry fees and permits.
- All prices are *(inferred)* from the anchors above. They sit mid-market against published all-inclusive rates.

| # | Package | Category | Duration | Route / itinerary | Vehicles | Price (Rs) | Inclusions | Exclusions |
|---|---|---|---|---|---|---|---|---|
| 1 | Kathmandu Airport Pickup & Drop | Airport transfer | One way, up to 2 hr | TIA ↔ any hotel/home in Kathmandu, Lalitpur or Bhaktapur | Hatchback, Sedan, SUV, Van | from 1,300. Hatchback 1,300, Sedan 1,500, SUV 2,500, Van 3,500 | Driver, fuel, airport parking, flight tracking, name-board meet | Waiting beyond 60 min after landing, extra stops |
| 2 | Pokhara & Bhairahawa Airport Transfer | Airport transfer | One way | Pokhara Intl → Lakeside/city, or Gautam Buddha Intl → Bhairahawa/Lumbini | Hatchback, Sedan, SUV, Van | from 1,500. Hatchback 1,500, Sedan 1,800, SUV 2,800, Van 3,800 | Driver, fuel, parking | Extra stops, waiting beyond 60 min |
| 3 | City Ride (Point-to-Point) | City ride | One way, up to 15 km | Any two points inside Kathmandu valley or Pokhara | Hatchback, Sedan, SUV, Van | from 1,000. Hatchback 1,000, Sedan 1,200, SUV 1,800, Van 2,500 | Driver, fuel | Parking at destination, extra km (Rs 60/km) |
| 4 | 4-Hour Car with Driver | Hourly | 4 hr / 40 km | Your own stops inside the valley | Hatchback, Sedan, SUV, Van | from 3,000. Hatchback 3,000, Sedan 3,500, SUV 5,000, Van 5,500 | Driver, fuel for 40 km, tolls | Parking, overtime Rs 600–900/hr, extra km |
| 5 | Full-Day Car with Driver (8 hr) | Hourly | 8 hr / 80 km | Your own stops inside the valley | Hatchback, Sedan, SUV, Van | from 5,000. Hatchback 5,000, Sedan 6,000, SUV 8,500, Van 10,000 | Driver, fuel for 80 km, tolls | Parking, overtime, night charge after 8 PM, driver's lunch |
| 6 | Kathmandu Valley Heritage Day | Tour | 1 day (8 hr) | D1: Pashupatinath → Boudhanath → Bhaktapur Durbar Square → Patan → Swayambhunath | Sedan, SUV, Van | from 6,500. Sedan 6,500, SUV 9,000, Van 11,000 | Driver, fuel, tolls, parking, water | Entry fees, guide, meals |
| 7 | Nagarkot Sunrise Escape | Tour | Half day (~6 hr) | D1: 4:30 AM pickup → Nagarkot viewpoint sunrise → breakfast stop → Changunarayan → hotel | Sedan, SUV, Van | from 5,500. Sedan 5,500, SUV 7,500, Van 9,000 | Driver, fuel, tolls, parking | Breakfast, entry fees |
| 8 | Manakamana Darshan Day Trip | Tour (Pilgrimage) | 1 day | D1: Kathmandu → Kurintar (cable car to Manakamana temple) → lunch by Trishuli → Kathmandu | Sedan, SUV, Van | from 11,000. Sedan 11,000, SUV 15,000, Van 18,000 | Driver, fuel, tolls, parking, driver's meal | Cable-car tickets, puja items, meals |
| 9 | Kathmandu–Pokhara Lakeside Getaway | Tour | 3 days / 2 nights | D1: Kathmandu → Bandipur tea stop → Pokhara. D2: Sarangkot sunrise, Davis Falls, Gupteshwor Cave, World Peace Pagoda, Phewa Lake. D3: Pokhara → Kathmandu | Sedan, SUV, Van | from 32,000. Sedan 32,000, SUV 44,000, Van 52,000 | Driver, fuel, tolls, parking, driver food & stay | Hotels, meals, entry fees, boating, paragliding |
| 10 | Chitwan Jungle Weekend | Tour | 3 days / 2 nights | D1: Kathmandu → Sauraha, Tharu village sunset. D2: vehicle on standby for local trips (safari with park operator). D3: Sauraha → Kathmandu | Sedan, SUV, Van | from 28,000. Sedan 28,000, SUV 36,000, Van 45,000 | Driver, fuel, tolls, parking, driver food & stay | National park fees, safari, hotel, meals |
| 11 | Bandipur Heritage Overnight | Tour | 2 days / 1 night | D1: Kathmandu → Bandipur bazaar, Tundikhel sunset. D2: Siddha Cave or Ramkot village → Kathmandu | Sedan, SUV, Van | from 18,000. Sedan 18,000, SUV 24,000, Van 28,000 | Driver, fuel, tolls, parking, driver food & stay | Hotel, meals, cave entry |
| 12 | Lumbini Peace Pilgrimage | Tour (Pilgrimage) | 3 days / 2 nights | D1: Kathmandu → Lumbini, evening at Maya Devi Temple. D2: Monastic Zone, Peace Pagoda, museum. D3: Tilaurakot → Kathmandu | Sedan, SUV, Van | from 34,000. Sedan 34,000, SUV 45,000, Van 55,000 | Driver, fuel, tolls, parking, driver food & stay | Hotels, meals, entry fees, guide |
| 13 | Janakpur Dham Pilgrimage | Tour (Pilgrimage) | 3 days / 2 nights | D1: Kathmandu → Janakpur via BP Highway. D2: Janaki Mandir, Ram Mandir, Dhanush Sagar, Vivah Mandap, evening aarati. D3: Janakpur → Kathmandu | Sedan, SUV, Van | from 30,000. Sedan 30,000, SUV 40,000, Van 48,000 | Driver, fuel, tolls, parking, driver food & stay | Hotels, meals, temple donations |
| 14 | Muktinath & Jomsom Jeep Yatra | Tour (Pilgrimage) | 3 days / 2 nights (from Pokhara) | D1: Pokhara → Beni → Tatopani → Jomsom. D2: Jomsom → Kagbeni → Muktinath temple darshan → Marpha. D3: Marpha → Pokhara | SUV (4WD jeep/Scorpio) | from 40,000. SUV 40,000 | 4WD with mountain-route driver, fuel, parking, driver food & stay | ACAP permit, hotels, meals, Kathmandu–Pokhara transfer |
| 15 | Wedding & Event Car | Event | 6 hr within valley | Home → venue → photo stops → return. Convoy on request | Sedan, SUV | from 12,000. Sedan 12,000, SUV 18,000 (luxury/vintage on request) | Chauffeur in uniform, fuel for 50 km, basic floral decoration, ribbon | Premium decoration (from Rs 4,000 extra), overtime Rs 1,500/hr, outside-valley charge |
| 16 | Corporate Monthly with Driver | Corporate | 30 days (26 working days × 10 hr) | Office commute, client visits, staff pick-up inside valley | Hatchback, Sedan, SUV, Van | from 75,000/month. Hatchback 75,000, Sedan 90,000, SUV 130,000, Van 140,000 | Dedicated driver, maintenance, insurance, replacement vehicle, monthly VAT invoice | Fuel (billed at actuals or per km), parking, tolls, driver overtime/overnight |
