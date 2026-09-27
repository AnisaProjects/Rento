# Rento decisions log

Decisions made on the owner's behalf, with the reason and how to undo each one.
Newest section first. Prices are in Nepalese rupees per day.

## 2026-09-27: Phase 6 (polish)

**Backup taken before any change:** `~/Rento-backups/rento-20260927-180218-before-phase6.sql`.

### P1. `rento` MySQL user

**What.**
- Created `rento`@`localhost` and `rento`@`127.0.0.1`, with SELECT, INSERT, UPDATE and DELETE on the
  `rento` database only. The auth plugin is `mysql_native_password`, the same as root; PyMySQL would
  need an extra package for MySQL 8's default.
- The password is random (28 characters). It is stored only in `includes/config.local.php`, which is
  owner-readable (600), git-ignored and in `includes/`, which Apache already blocks.
- `includes/config.php` now reads settings from `RENTO_*` environment variables first, then
  `config.local.php`, then (in dev mode only) the MAMP defaults.
- Tested: the web site, the PHP CLI (launchd job) and the Python pricer connect as `rento`.
- Migrations (schema changes) still need an admin user such as root, because the app user can't
  ALTER tables. Root's password was not changed.

**Why.** The app shouldn't run as root.
**Undo.** Delete `includes/config.local.php` (PHP falls back to the MAMP root defaults in dev mode).
To remove the user: `DROP USER 'rento'@'localhost', 'rento'@'127.0.0.1';`.
**To see or change the password:** open `includes/config.local.php`. To change it:
`ALTER USER 'rento'@'localhost' IDENTIFIED BY '…'`, the same for `127.0.0.1`, then update the file.

### P2. Nightly price recompute (launchd)

**What.** Installed `tools/com.rento.price-forecast.plist` in `~/Library/LaunchAgents` and loaded it.
It runs every night at 02:30. A test run (`launchctl kickstart`) exited with code 0 and wrote this to
`logs/forecast-launchd.log`:
`2026-09-27 18:03:57 forecast (nightly): done - 8 cars x 60 days from 2026-09-27. …`
**Undo.** `launchctl bootout gui/$(id -u)/com.rento.price-forecast`, then delete the plist from
`~/Library/LaunchAgents`.

### P3. BS 2084 festival dates are "provisional"

**What.**
- A festival counts as provisional until its "Checked" value is "Official (government list)". Seasonal
  guidance rows never do.
- Admin → Festivals shows a "Provisional" badge on upcoming provisional rows, and a note about the BS 2084
  dates (20 festivals from 14 Apr 2027).
- From **1 Feb 2027**, while any BS 2084 date is still provisional, the dashboard shows a reminder
  (`FESTIVAL_BS2084_REVIEW_FROM` in `includes/festivals.php`).
- Package pages with festival pricing mark those windows "(dates to be confirmed)".

**Why.** The official BS 2084 holiday list isn't out yet, and prices must not rely on unchecked dates
without saying so.
**Undo.** Revert the commit "Items 4-5: rento DB user, nightly job, BS 2084 reminder".

### P4. End-to-end booking check (then deleted)

A temporary customer booked the Nissan Kicks for 20–22 Oct 2026 (3 days, Dashain). The figures agreed:
- **Price shown before booking:** Rs 24,300 (3 × Rs 8,100).
- **Stored `quoted_price`:** Rs 24,300.
- **Invoice:** base Rs 21,000 + Rs 3,300 = Rs 24,300.
- **Admin booking details:** Rs 24,300.
- **Admin "Total revenue" after confirming:** rose from Rs 57,000 to Rs 81,300 (+Rs 24,300).

The customer and booking were then deleted. Revenue is back at Rs 57,000, and prices were recomputed.

### P5. Rugged / off-road flag

**What.**
- Migration 019 adds `tblvehicles.Rugged` (default 0) and a "Rugged / off-road capable" switch in
  admin → Vehicles (form field `rugged`). It is set on the Toyota Fortuner, and on the inactive Mahindra
  Scorpio and Toyota Hilux: ladder-frame cars with high ground clearance and 4x4 versions.
- It is *not* set on the Audi Q8. Its quattro all-wheel drive is for grip on the road, and its low tyres
  and body aren't meant for mountain tracks. The Kicks, Brezza and Creta are road-going compact SUVs.
- On mountain, Mustang, off-road and rough-road trips, road suitability now weighs 25 instead of 15:
  - Rugged cars score full marks for it.
  - Every other car is capped at 0.6 and gets the caution "Not a rugged 4x4: fine on rough patches,
    less suited to mountain tracks".
- Car pages show an "Off-road capable" chip, and the API returns `rugged`.

**Result** ("Mountain roads to Mustang, 5 people, SUV"): Fortuner 97%, Kicks 83%, Brezza 82%, Q8 82%
(before: Kicks 96%, Fortuner 96%, Brezza 95%, Q8 95%).
**Undo.** Switch the flag off per car in admin. The column itself stays (additive rule).

### P6. Accessibility beyond axe

**How it was tested.**
- **Keyboard:** a script presses real Tab keys through every public, customer and admin page at 1366px
  and 390px. It flags focus stops with no name, no visible focus style, invisible focused elements and
  focus traps.
- **Widgets:** 29 keyboard scenarios cover the skip link, mobile menu, login modal, hero slideshow, FAQ
  accordion, price calendar, Find My Car, booking date fields and a failed login.
- **axe:** run on every page at both widths.

**What changed.**
- **Focus ring contrast.** Rings were orange at 45% opacity (about 1.7:1 on white, under the 3:1 that
  WCAG 1.4.11 asks for). They now use `--rento-focus-ring` (#c43c1a, 5.2:1). Dark sections (hero,
  page headers, footer, Find My Car, admin sidebar) use `--rento-focus-ring-on-dark` (#ff8a6b, 8.2:1).
  Form fields get a solid ring too, not only a faint glow.
- **Skip link** now moves keyboard focus to the main content. Before, it only scrolled.
- **Price calendar:**
  - It is one Tab stop. Arrows move by a day or a week, Home/End go to the start/end of the week, and
    Page Up/Down change month.
  - Each day's spoken label now says "cheaper than usual" or "higher than usual". Before, only the colour
    showed it.
- **Scroll-reveal content** becomes visible as soon as it receives keyboard focus. Before, the home
  page's type cards could be focused while still invisible.
- **Form errors.**
  - Flash messages are read out through live regions (`role=status`, or `role=alert` for errors) that
    are filled after the page loads.
  - Error toasts stay until they are closed. Before, they disappeared after 6 s.
  - Success toasts stay 8 s.
- **Admin.**
  - "Delete not possible" icons are now one focusable, named button (`aria-disabled`) with its tooltip,
    not an unnamed focusable span around a disabled button.
  - Image upload inputs have explicit names ("Image 1 (cover): choose a photo").
- **Heading order and alt text.** Empty states on My Bookings, My Testimonials and Packages use h2 after the
  page's h1. The About page's car photos no longer repeat the name shown next to them.
- **Reduced motion.** A global rule in `tokens.css` (public and admin) turns off animations, transitions and
  smooth scrolling. Script-driven smooth scrolling follows the setting too. The slideshow already didn't
  autoplay and has a pause button.
  - Checked with reduced motion emulated: no running animations, no hidden reveal elements, and the
    slideshow stays on slide 1.
  - With normal settings the effects still run.

**Result.**
- Keyboard pass: no problems on any page.
- Widget scenarios: 29/29.
- axe: no violations on any page at 1366 or 390.

**Not automated:** a real screen reader (VoiceOver / NVDA). Worth one manual pass before launch.
**Undo.** Revert the commit "Accessibility: focus rings, skip link, calendar keys, announcements…".

### P7. SEO

**What.**
- **Titles and descriptions.**
  - Every indexable page has a unique `<title>` and meta description (checked on all 25 sitemap URLs:
    0 duplicates).
  - Main pages were already editable in admin → Site content → Site & SEO. The About, FAQs, Terms and
    Privacy defaults were one generic sentence and now describe their page.
  - Cars and packages get an optional **Search description** field in their admin forms (migration 020:
    `SeoDescription`; form field `seodescription`). When it is empty, the description is built from the
    data. Cars use the card's "from" price, not the base price.
  - Type and brand lists get their own title and description ("Toyota cars", "Rent an SUV car in Nepal
    with Rento: 4 cars from Rs 7,000/day…").
- **Canonical URLs** use the clean addresses (`car/<name>-<id>`, `package/<name>-<id>`,
  `car-listing?type=SUV`).
- **Indexing.**
  - Type, brand and paged lists are indexable.
  - Keyword, trip, fuel, budget and sort variants are `noindex` and point to the plain list. Before, every
    filtered list was noindex; type and brand pages are real landing pages.
  - Account, invoice, reset and 404 pages are `noindex`. Duplicate hand-written robots tags were removed.
- **Open Graph and X cards** are on every page.
  - The default share image is `assets/images/share-default.jpg` (1200 × 630, rebuilt with
    `tools/make-share-image.php`). It can be replaced in Site & SEO → Share image.
  - Car and package pages use their own photo.
- **JSON-LD.**
  - `AutoRental` (a LocalBusiness) on home and contact, from admin → Contact info.
  - `WebSite` with a search action on home.
  - `Car` with an `Offer` (the "from" price per day in NPR; none for driver-only cars) and breadcrumbs on
    car pages.
  - `TouristTrip` with the itinerary and starting price on package pages.
  - `FAQPage` from the same FAQ content as the accordion.
- **`/sitemap.xml` and `/robots.txt`** are generated by `sitemap.php` and `robots.php`, via .htaccess. The
  sitemap lists active cars, active packages and categories, type and brand lists, and content pages.
  robots.txt blocks admin, API, internal folders and account pages.
- **New Site & SEO settings:** *Site address* (the absolute URL for canonical links, the sitemap and share
  tags; empty on MAMP = the address in use), *Share image* and *X handle*.
- The vehicle and package forms now save textarea line breaks as LF. Browsers send CRLF, which
  previously added `\r` on every admin save.

**To do before launch.**
- Set *Site address* to the real https address.
- The contact address in admin ("J&K Block, Laxmi Nagar") looks like template data (it is in Delhi). It
  is shown in the LocalBusiness data, so correct it in admin → Contact info.

**Undo.** Revert the commit "SEO: …". The migration 020 columns can stay empty.

### P8. Motion

**What.** CSS only, no animation library.
- **Skeleton loaders** (section 29 of `app.css`):
  - The price calendar shows a skeleton month while its data loads. Before, it was hidden until then.
  - The booking total shows a skeleton while a new quote loads.
  - During a smart search on the car listing, the cards turn into skeleton cards. Before, they faded
    to 45%.
  - The Find My Car "thinking" panel shows preview rows.
  - A loader only appears if loading takes over 150 ms, so fast answers don't flash.
- **Scroll reveal:** items in a row now appear one after another (60 ms apart, at most six steps).
- **Hover effects** were already on cards, buttons, type and brand tiles, and were kept.
- **Reduced motion:** every sweep, lift and reveal is off (global rule, P6). Skeletons show as still
  grey blocks.

**Undo.** Revert the commit "Motion: skeleton loaders…".

### P9. Dark mode

**What.**
- **Toggle.** A theme button in the public header and the admin top bar cycles **System → Light →
  Dark**.
  - "System" (the default) follows the device setting and updates when it changes.
  - The choice is remembered per visitor in the browser (`localStorage` key `rento-theme`), shared by
    the site and admin.
  - `assets/js/theme.js` runs in `<head>` before the page paints, so there is no flash.
  - Without JavaScript, the system setting still applies through `prefers-color-scheme`.
- **Palette** (`tokens.css`).
  - New role tokens: `--rento-bg`, `--rento-surface`, `--rento-heading`, `--rento-text-strong`,
    `--rento-inverse` / `--rento-on-inverse` (selected pills, small badges), `--rento-on-dark` (text on
    the ink sections), and status text tokens.
  - In dark mode the grey scale is inverted, so backgrounds, borders and text keep their relationships.
  - Ink sections (hero, page headers, footer, call-to-action, admin sidebar) stay dark in both themes,
    slightly darker than the page.
  - `app.css` and `admin.css` now use these tokens instead of hard-coded white and ink. Bootstrap gets
    `data-bs-theme` for its components.
- **Orange accent.**
  - Buttons keep #d23f1c with white text (4.7:1).
  - Orange text and focus rings use #ff8a6b in dark mode: 7.4:1 on cards, 8.1:1 on the page.
  - Status text on its tinted background is 8.3–8.4:1.
- **Form field borders** in both themes are now 3:1 or better (`--rento-field-border`), as WCAG 1.4.11 asks.
  Before, they were 1.3:1.
- **Text pasted into Manage Pages** with fixed colours follows the theme in dark mode. The saved content
  is unchanged.
- **Copy.** The home page reason "One daily price in rupees" (not true with smart pricing) is now "Prices in
  rupees for each day, with the full total shown before you book." It is editable in Site content.
- **Admin.** The account menu button now has a name at mobile width. Scrollable tables (Smart Pricing
  preview, AI Insights, Package categories) are keyboard-focusable named regions. These showed up when
  every admin page was retested with a fresh session: an expired session had made some earlier admin
  checks test the sign-in page.

**Result.** axe shows no violations, including colour contrast, on every public and admin page, in light and
dark, at 1366 and 390.
**Undo.** Revert the commit "Dark mode…". To force light for everyone, remove `theme.js` from
`includes/head.php` and `admin/includes/layout.php`, and delete the two dark blocks in `tokens.css`.

### P10. Email (PHPMailer, optional SMTP)

**What.**
- **Library.** PHPMailer 6.12 is installed with Composer (`composer.json` / `composer.lock` committed;
  `vendor/` git-ignored and blocked from the web). Run `composer install --no-dev` on a new server.
- **Migration 021** adds `admin.Email` and the table `tblmailsettings`. The legacy
  `admin.updationDate` zero-date default needed a session-only `sql_mode` change for the ALTER; the
  column itself is untouched.
- **Admin → Email** (new page `admin/email-settings.php`):
  - Settings: SMTP server, port, encryption, username, password, From address and name, reply-to.
  - "Send test email", plus a short log of recent sends.
  - The password is encrypted with libsodium using `APP_KEY`. The key is generated into
    `includes/config.local.php` or set with `RENTO_APP_KEY`, and is never in the database. The form never
    shows the password again.
- **Admin → Change password** has a new "Account email" form (fields `adminemail`, `emailpassword`).
  Changing it needs the current password, because reset links go there.
- **When SMTP is set up:**
  - Customer and admin password reset links are emailed and no longer shown on screen, even in dev mode.
  - "We received your booking request" is sent, with the stored price.
  - "Your booking is confirmed" is sent when an admin confirms.
  - "We received your request" is sent for package and with-driver enquiries that give an email address.
- **When it isn't set up:** dev mode shows reset links on screen as before, and nothing is sent.
- **Failures never interrupt a booking or enquiry.** The admin sees the SMTP error on a test send.
  `logs/mail.log` records sends and failures with masked addresses and never passwords.
- **Emails** are plain HTML with a text version, the Rento wordmark, a single button and a detail table.

**Tested** against a local SMTP test server: the test email, customer reset, booking received, booking
confirmed, package enquiry and admin reset all arrived with the right content and links. With the server
down, the error was shown and the booking still saved. With email off, the dev-mode link came back.
All test data, the admin test address and the SMTP settings were then removed, so **email is off until
you enter your provider's details**.

**Not included (open):** a cancellation email, and an "new booking" notice to the admin inbox.
**Undo.** Clear the SMTP server in admin → Email (email off). Revert the commit to remove the code;
migration 021 stays (additive).

### P11. Terms & Conditions and Privacy Policy (drafts)

**What.**
- **Drafts.** Nepal-specific drafts in `docs/legal/terms-draft.html` and `docs/legal/privacy-draft.html` are
  loaded as **drafts** (migration 022 adds `DraftDetail`, `DraftNote` and `DraftUpdatedAt` to `tblpages`).
  **Nothing is published.** The live Terms and Privacy pages are unchanged.
- **Admin → Manage Pages:**
  - A page with a draft shows "Draft waiting for review", with the review note, a preview, and Edit draft,
    Publish draft (with confirmation) and Discard draft.
  - The live editor also has "Save as draft".
  - **Publishing is blocked while the draft contains `[TO CONFIRM: …]`.** Terms has 27 of these, Privacy 14.
- **The Terms draft covers:** self-drive, with-driver rental, packages, booking requests and confirmation,
  smart pricing (daily prices, and the saved quote as the price), payment and the deposit, who may drive,
  documents, use of the vehicle (off-road flag, restricted areas), fuel and km, damage, accidents,
  insurance, breakdown and theft, changes and cancellations, liability (with a Consumer Protection Act
  2075 reference), the account and website, complaints, Nepal law and courts.
- **The Privacy draft covers:** what is collected (account, bookings, enquiries, contact form, newsletter,
  testimonials, documents at pickup, technical data and cookies), why, smart pricing and recommendations,
  sharing (email and hosting providers, insurers, police, Google Fonts and Maps), retention, security,
  rights under the Individual Privacy Act 2075, children and changes. It matches what the code actually
  does (for example, rate-limit records deleted after about a day, and the theme stored only in the
  browser).
- **Keyboard fix.** Tab in the page editor (Quill) no longer traps the keyboard: it leaves the editor
  instead of inserting a tab (WCAG 2.1.2).

**You must review:** every `[TO CONFIRM]` item (company, PAN/VAT, deposit, payment timing, minimum age and
licence years, insurance and excess, fuel and km rules, cancellation fees, retention periods, providers), and
have both texts checked, ideally by a lawyer in Nepal. The drafts were written by the developer, not a
lawyer.

**Important:** the **live** Terms page is copied *Yahoo! India* terms and the live Privacy page is *lorem
ipsum*. Both should be replaced soon, which is what publishing the reviewed drafts will do.
**Undo.** Admin → Manage Pages → Discard draft.

### P12. Production readiness (nothing deployed)

**What.**
- **`docs/deployment.md`** is a checklist for real hosting: requirements, copying files, moving from
  `/Rento/` to the domain root, web server rules (Apache and nginx), the two database users, configuration
  through environment variables or `config.local.php`, php.ini, Composer and the Python venv, **cron instead of
  launchd**, file permissions, email, backups, logging, and the launch checklist.
- **`tools/check-production.php [url]`** (command line) and **admin → System check** (inside the web
  server) run the same checks (`includes/production-check.php`), and never print secrets. They cover:
  - dev mode;
  - where secrets come from and their file mode;
  - a root or privileged DB user, and a weak DB password;
  - APP_KEY, SMTP and PHPMailer;
  - the admin account email;
  - the site address and https, including the actual request and proxy headers when run from admin;
  - `display_errors`, `log_errors` and `expose_php`;
  - the logs folder, the pricing venv and a recent price run;
  - template text on legal pages and the contact address;
  - over HTTP: that internal files (config, logs, vendor, migrations, Python, docs, tools, .git) are
    not served, and that http redirects to https.
- The command-line tool exits with code 1 while anything fails.

**Result on MAMP today:** 3 failed (dev mode on; Terms and Privacy show template text), 6 warnings (email
not set up, no admin email, no site address, `display_errors` and `expose_php` on in MAMP's php.ini,
template contact address), 10 passed. All of these are expected on MAMP; each has its fix in deployment.md.
**Undo.** Delete `tools/check-production.php`, `includes/production-check.php` and
`admin/system-check.php`, and remove the menu line.

### P13. Documentation

**What.**
- **New:** `README.md` (setup, structure, how smart pricing and Find My Car work, conventions),
  `docs/admin-guide.md` (plain-language guide for every admin page) and `docs/README.md` (index).
- **Rewritten:** `ml/README.md`. It described the first prototype (Python per page view, database
  credentials on the command line).
- **Archived:** `docs/dynamic_pricing/` moved to `docs/archive/dynamic_pricing/`, with an "outdated" note.
- Stale references to `includes/forecast.php` now point to `includes/pricing.php`.
- All docs are blocked from the web (`docs/.htaccess` and `.md` rule).
- A stray file `ml/ml` (956 bytes, from 31 Aug) was left alone; it isn't used.

**Undo.** `git mv docs/archive/dynamic_pricing docs/dynamic_pricing`.

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
