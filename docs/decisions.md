# Rento decisions log

Decisions made on the owner's behalf, with the reason and how to undo each one.
Newest section first. Prices are in Nepalese rupees per day.

## 2026-09-27: Phase 5 follow-ups (data update, listing prices, FAQ)

**Backup taken before any change:** `~/Rento-backups/rento-20260927-174141-before-phase5-followups.sql`
(full dump of the `rento` database). To restore everything to that moment:
`/Applications/MAMP/Library/bin/mysql57/bin/mysql -uroot -p -P8889 -h127.0.0.1 rento < ~/Rento-backups/rento-20260927-174141-before-phase5-followups.sql`
(this also removes any bookings made after the backup, so prefer the undo scripts below).

### D1. Base prices of the active cars

**What.** Each active car's base price is now the self-drive market rate from
`docs/market-research.md` (Self Drive Nepal rate card, inside Kathmandu valley, fuel excluded; the only
published self-drive source). The rule: the median self-drive daily price for the same model; if there is
none, the median for the same type and segment; rounded to the nearest Rs 50. With one self-drive source,
the median is that source's rate.

| Car | Old | New | Basis |
|---|---|---|---|
| Maruti Suzuki Wagon R | 500 | 5,000 | No model rate; hatchback rate |
| BMW 5 Series | 1,000 | 10,000 | No luxury-sedan rate; nearest segment is "automatic/luxury SUV" (see D2) |
| Audi Q8 | 3,000 | 10,000 | Automatic/luxury SUV rate |
| Nissan Kicks | 800 | 7,000 | Model named in the manual-SUV rate |
| Nissan GT-R | 2,000 | 10,000 | No sports-car rate; nearest segment is "automatic/luxury SUV" (see D2) |
| Nissan Sunny 2020 | 400 | 5,000 | No model rate; sedan rate |
| Toyota Fortuner | 3,000 | 10,000 | No model rate; 7-seat SUV rate (Scorpio) = automatic/luxury SUV rate |
| Maruti Suzuki Vitara Brezza | 600 | 7,000 | Model named in the manual-SUV rate |

**Why.** You asked for market-based prices; the old prices were 80–95% below the market, which also made
every car "Below market".
**Undo.** Run `database/undo/017_phase5_data_update_undo.sql`, or change a single price in
admin → Vehicles → Edit. Then recompute prices (admin → Smart Pricing → Recompute prices).

### D2. Luxury sedans and sports cars

**What.** The research has no self-drive rate for a luxury sedan (BMW 5 Series) or a sports car (GT-R).
Both use Rs 10,000, the highest published self-drive rate ("automatic/luxury SUV"), because it is the
only luxury segment with data.
**Why.** A luxury car should not be priced like a Suzuki Ciaz (Rs 5,000). This is a judgement, not a
measured rate. With-driver wedding rates for BMW/Audi/Mercedes are Rs 25,000–40,000, so Rs 10,000 is
conservative.
**Undo.** Change the price in admin → Vehicles → Edit.

### D3. "Below market" comparisons

**What.** In `includes/price-insights.php` (`market_category`):
- SUVs, crossovers and MUVs from luxury brands (Audi, BMW, Mercedes, Lexus, Land Rover, Porsche,
  Jaguar, Volvo) are compared with a new "automatic and luxury SUVs" rate (Rs 10,000; added to
  `docs/market-benchmarks.json` from the same rate card).
- Luxury-brand sedans and coupes have no comparable rate, so they never get the badge.
- 7-seat MUVs (e.g. Innova Crysta) are compared with the 7-seat rate (the research groups Scorpio, Innova
  and Fortuner together). Before, the Audi Q8 was compared with the Rs 7,000 manual-SUV rate and MUVs
  had no comparison.

**Why.** The badge must only appear when it is really true.
**Undo.** Revert the commit "Data update: market-based prices…" for `includes/price-insights.php` and
`docs/market-benchmarks.json`.

**Result after the recompute:** no active car shows "Below market". Every active car is at or above its
market rate, and festival-season surcharges push the 30-day averages above the base.
"Best value this week" shows on the Nissan Sunny (the cheaper of the two sedans). The two Rs 7,000 SUVs
tie, so neither gets it. Inactive cars priced below the market rate (e.g. Alto K10 Rs 4,000 against Rs 5,000)
will show "Below market" when activated, which is true.

### D4. Car types, seats and specs

**What.** Migration `017_phase5_data_update.sql`:

| Car | Change | Why |
|---|---|---|
| Nissan Kicks | Sedan → SUV | It is a compact SUV |
| Nissan GT-R | Sedan → Coupe; 5 → 4 seats | It is a 2+2 sports coupe |
| Nissan Sunny 2020 | Hatchback → Sedan; CNG → Petrol; year 2018 → 2020 | It is a sedan; no factory CNG version is sold in Nepal; the title says 2020 |
| Maruti Suzuki Vitara Brezza | Hatchback → SUV | It is a compact SUV |
| Toyota Fortuner | 5 → 7 seats | It is a seven-seat SUV (its own description said so) |
| Audi Q8 | Year 2017 → 2018 | The Q8 went on sale in 2018; 2018 is the earliest possible year |
| Kicks, Fortuner, Brezza, Sunny | Standard features switched on (AC, power steering, ABS, airbags, central locking where missing) | E.g. the Fortuner was listed without air conditioning or ABS |
| Wagon R | Leather seats switched off | It has fabric seats |

Not changed, because the correct value is not certain: the Vitara Brezza is listed as a 2018 petrol car,
but the 2018 Brezza sold in Nepal was diesel only. Check the real car and fix either the year or the fuel
in admin.

**Undo.** `database/undo/017_phase5_data_update_undo.sql` (restores every column this migration changed).

### D5. Descriptions

**What.**
- **Active cars:** new, plain descriptions replace the old template text, which quoted Indian
  prices ("? 55.4 Lakh"), "0 kmpl" mileage, "read more about it here" links and a truncated GT-R sentence.
- **New vehicles:** all 17 have a complete description (what the car is, seats and luggage, where it is
  best), up from a single sentence.
- **Packages:** all 16 keep their existing text, followed by a new second paragraph with practical details:
  times, what the price covers and what to bring. Nothing in them promises a policy that Rento doesn't
  already state in the inclusions and exclusions.

**Why.** "Complete description, ready to activate". The old active-car texts were wrong for a Nepali site.
**Undo.** `database/undo/017_phase5_data_update_undo.sql`.

### D6. New vehicles and packages stay inactive

**What.**
- **Vehicles:** all 17 are still inactive. Their prices are the research's suggested Rento self-drive
  prices (`docs/market-research.md`, table V5), which are market-based and sourced. The one change is the
  Suzuki Brezza, Rs 6,500 → Rs 7,000, to match the active Vitara Brezza (the same model at two prices
  would look like an error).
- **Packages:** all 16 are still inactive, with the research prices (table P4).

**Still needed before activating:** real photos. All 17 vehicles and 16 packages use the placeholder
image, and I did not add photos I have no rights to.
**Undo.** Brezza price: `database/undo/017_phase5_data_update_undo.sql` or admin.

### D7. Occupancy/demand threshold

**What.** Kept at 50 confirmed bookings (`pricing_demand_min_bookings`, the default). Currently 2 of 50,
so the factors are off.
**Undo.** Admin → Smart Pricing → "Confirmed bookings needed for the occupancy and demand factors".

### D8. Listing filter and sort use the "from" price

**What.**
- **Listing:** the budget filter and "Price: low/high" sort on `car-listing` use the price the cards show
  ("from", the lowest price in the next 30 days), with the base price when there is no forecast. The list
  is now filtered and paged in PHP, which is fine for a fleet of this size (hundreds of cars would still be
  fast).
- **Home page:** the type and brand cards ("from Rs X/day") use the same price.
- **Find My Car and the smart search:** without dates, they also compare the budget with the "from"
  price. With dates, they use the price on those dates, as before.

**Why.** What customers filter by now matches what the cards show.
**Undo.** Revert the commit "Listing and budgets: 'from' price…".

### D9. Budget options sized for the new prices

**What.** The old budgets (Rs 1,000–5,000) would have hidden most cars after D1.
- Listing and home budget choices are now Rs 5,000 / 7,500 / 10,000 / 15,000 (the field name `max` is
  unchanged). An old bookmarked `max=1000` is simply ignored and shows all cars.
- The Find My Car slider now runs Rs 1,000–20,000 in Rs 500 steps, starting at Rs 10,000 (was
  Rs 200–5,000, starting at Rs 1,000).
- "Cheap" without an amount now means Rs 5,500 (was Rs 1,000) and "luxury/premium" Rs 15,000 (was
  Rs 5,000), in both `includes/trip-parser.php` and `assets/js/rento-ai.js`.
- The example prompt "Family trip to Pokhara under Rs 3,000" is now "under Rs 8,000", in the default
  text and the placeholders. It had not been edited in Site content, so no saved text was overwritten.
- Only types with an active car are offered in the listing's type filter, so Coupe (the GT-R) now
  appears there.

**Why.** Keep every search usable with market-based prices.
**Undo.** Revert the same commit; the Find My Car example can also be edited in admin → Site content.

### D10. FAQ "How is the price calculated?"

**What.** The answer said "Every car has one daily price … the number of days multiplied by the daily
price", which is no longer true. It now explains:
- each day's price is adjusted within set limits for weekends, festivals and travel days, and the season;
- the car page's price calendar shows cheaper days;
- the total shown before the request is saved with the booking and used on the invoice.

It doesn't quote the percentage limits, so it stays correct if they are changed in Smart Pricing.
Applied by `database/migrations/018_faq_smart_pricing.sql`.
Your message was cut off after "3. FAQ: reword the "one daily", so this covers only that answer.
**Why.** The FAQ must describe how prices really work.
**Undo.** `database/undo/018_faq_smart_pricing_undo.sql`, or edit it in admin → Manage Pages → FAQs.
