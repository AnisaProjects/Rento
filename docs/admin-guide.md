# Rento admin guide

How to run the website day to day. Open the admin at **/admin/** (on this Mac:
`http://localhost:8888/Rento/admin/`) and sign in. Every page is in the menu on the left; on a phone,
the menu opens from the ☰ button.

A few things work the same everywhere:

- **Actions that change something ask first.** Delete, cancel and publish open a confirmation box.
- **Messages** appear at the bottom right after you save. Error messages stay until you close them.
- **The ◐ button** at the top switches the colour theme (System → Light → Dark).
- **Tables** can be searched and sorted. Click a column heading to sort.

---

## 1. Dashboard

Figures for today:
- **Total revenue** (confirmed bookings, at the price the customer was quoted)
- active and pending bookings
- fleet utilisation, new users, new package enquiries and unread contact messages

There are also charts of bookings per month and by status. Click a figure to open its list.

From February 2027, the dashboard also reminds you to check the BS 2084 festival dates (see
[Festivals](#7-festivals)).

## 2. Bookings

**Bookings → Manage / New / Confirmed / Canceled.**

- A customer's booking arrives as **New**, a request. The customer sees the price for each day and the
  total before sending. That total is saved with the booking and is the price they pay.
- Open a booking to see the customer, dates, the saved price day by day, and a timeline.
- **Confirm** or **Cancel** it at the top. Rento won't let you confirm two bookings for the same car on
  overlapping dates.
- When email is set up (see [Email](#11-email)), the customer is emailed when the request arrives and
  when you confirm it.
- Customers print an **invoice** from "My bookings" once a booking exists.

Confirming or cancelling updates the prices automatically, because availability changed.

## 3. Vehicles and brands

**Vehicles → Post a Vehicle / Manage Vehicles.**

- **Visibility:**
  - **Active**: shown on the website. Inactive cars are hidden everywhere and can't be booked.
  - **Driver only**: the car is shown "With driver"; customers send a request instead of booking self-drive
    (for example the 14-seat Hiace).
  - **Rugged / off-road capable**: only for cars built for mountain tracks (Fortuner, Scorpio, Hilux). Find
    My Car puts them first for mountain and rough-road trips, and the car page shows "Off-road capable".
- **Basic info:** title, brand, overview (what the car is good for), and an optional **Search description**
  (what Google shows). Leave it empty and one is written from the car's type, seats and price.
- **Price per day** is the car's *usual* daily price. The price on each day is worked out from it (see
  [Smart pricing](#5-smart-pricing)). Under the field, **Smart price suggestion** shows the 60-day average
  with its main reasons. **Use it** copies it in.
- **Photos:** drag and drop, or choose, up to five. Image 1 is the cover. Photos are shrunk before upload
  and a WebP copy is made.
- **Delete** is only possible for a car without bookings. Otherwise make it inactive.

**Brands → Create / Manage:** a brand name and an optional logo. A brand with cars can't be deleted.

## 4. Packages and enquiries

**Packages → Manage Packages / Categories / Enquiries.**

- **A package** has a title, category, summary (for the card), description, duration, route, a price for
  each vehicle type ("from" is the lowest), a day-by-day itinerary, inclusions, exclusions, a cover
  photo and gallery photos, and an optional search description.
- **Festival pricing** (off by default) adds the festival and travel-day surcharges on those dates. The
  package page then lists the festival dates with their prices. Dates not yet on the official list
  say "(dates to be confirmed)".
- **All packages start inactive.** Make one active when its photos and prices are ready; the Packages
  menu item appears on the website as soon as one is active.
- **Enquiries** come from package pages and driver-only cars:
  - Open one to see the details and add notes.
  - Move it along: **Mark as contacted → Confirm** (or **Cancel**, or **Reopen as new**).
  - If the customer gave an email and email is set up, they get a "request received" email.

## 5. Smart pricing

**Smart Pricing** sets how prices change from the car's usual price. Each day's price is the usual price,
plus or minus:

| Factor | Default | Notes |
|---|---|---|
| Weekend pickup | +8% | Friday and Saturday (you can change the days) |
| Festival, high / medium demand | +12% / +5% | Dates come from the Festivals page |
| Festival travel days | +15% | The days before a festival; each festival can set its own |
| Travel season | +4% | Trekking seasons and similar |
| Last minute / early booking | +5% / −4% | Up to 2 days ahead / 45+ days ahead |
| Occupancy and recent demand | off | They switch on by themselves after **50 confirmed bookings** |

- **Limits:** a price never goes more than **15% below** or **25% above** the usual price, whatever the
  factors add up to. Prices are rounded to Rs 50.
- **Model health** shows which pricer is active (rule-based now; a machine-learning model unlocks at 200
  confirmed bookings), the booking counts, the last recompute, coverage and any data warnings.
- **Recompute prices** runs the calculation now. It also runs every night (at 02:30 on this Mac) and after
  bookings, festival changes and price changes. The **14-day preview** shows one car's next two weeks with
  the reasons.
- Customers see the price on each day in the **price calendar** on the car page, and cards show
  "from Rs X/day" (the lowest price in the next 30 days).

## 6. AI Insights

For each car:
- the usual price, the 30- and 60-day averages, the highest and lowest day, and the main reasons prices
  move
- a **suggested usual price**
- a 60-day chart of price and demand

Actions:
- **Apply suggestion** sets the car's usual price to the suggestion.
- **Override** sets a fixed price for a date range, with a note (for example a promotion). The newest
  override wins. **Remove manual price** takes it away.

These suggestions are rule-based until there are enough bookings for the model.

## 7. Festivals

**Festivals & Travel Peaks** lists the dates that change prices: festivals, school holidays and trekking
seasons.

- **Each row has:**
  - dates and demand (high or medium)
  - optional **travel days** before the festival (with their own surcharge)
  - how the dates were **checked**, and a source link
- **Checked:** "Official (government list)" is best. Anything else shows **Provisional**.
- **BS 2084** starts on 14 April 2027. Its dates are provisional until the government publishes the
  official BS 2084 holiday list, usually early in 2027. When it is out:
  - check each 2084 row, correct the dates and set "Checked" to Official;
  - from 1 February 2027 the dashboard reminds you until that's done.
- Saving or deleting a festival recomputes prices.

## 8. Website content

**Site content → Home page / About page / Other pages / Footer & social / Site & SEO.**
- Every text and photo on those pages can be changed. Empty fields fall back to the default text.
- **Site & SEO:** the site name, logo, page titles and descriptions for search engines, the **Site address**
  (the real https address, needed before launch), the **Share image** shown when a page is shared, and an
  optional X handle.

**Manage Pages:** About Us, FAQs, Terms and Conditions, Privacy Policy.
- **Save page** publishes straight away. **Save as draft** keeps the change aside.
- A page with a draft shows **Draft waiting for review**, with a preview, **Edit draft**, **Publish draft**
  and **Discard draft**.
- A draft that still contains `[TO CONFIRM: …]` can't be published. Fill those in first.
- **Terms and Privacy drafts** are waiting now. Read them, fill in every `[TO CONFIRM]`, have them
  checked (ideally by a lawyer), then publish. The pages live today are template text.
- **FAQs:** each question is a *Heading 3*; the text under it is the answer. The FAQs page turns them into
  an accordion and tells Google about them.

## 9. People and messages

- **Registered Users:** customer accounts. A user with bookings can't be deleted.
- **Testimonials:** new ones are hidden until you **Approve and show** them. **Hide from website** takes one
  down.
- **Contact Us Queries:** messages from the contact form. **Reply by email** opens your mail program;
  **Mark as read** when done.
- **Subscribers:** newsletter sign-ups. **Remove subscriber** if someone asks to unsubscribe.

## 10. Contact info

The address, email and phone shown on the website and given to Google as the business details. It still
holds template data ("J&K Block, Laxmi Nagar"); put in Rento's real details.

## 11. Email

**Email** turns on emails through your provider's SMTP server:
- password reset links, for customers and for admins who saved an **Account email** (Change password page)
- "booking request received" and "booking confirmed"
- "request received" for enquiries

To set it up:
1. Enter the server, port, encryption, username and password, and the **From** address.
2. Save.
3. **Send test email**, and check the inbox and spam folder.

The password is stored encrypted and never shown again. With the server empty, email is off; on this Mac,
reset links are then shown on screen instead. "Recent sends" lists the last emails (addresses shortened)
and any errors.

## 12. System check and your account

- **System check** lists what is unsafe or missing before launch, such as development mode, https, email
  and template text, with what to do. "Also test protected files" checks that internal files can't be
  opened from the web. The same check runs on a server with `php tools/check-production.php`.
- **Change password:** your password, and your **Account email** for reset links. Changing it asks for your
  current password.

---

## What runs by itself

- **Prices** recompute every night and after bookings, festivals, price changes and overrides.
- **Occupancy and demand pricing** switch on at 50 confirmed bookings.
- **The sitemap** (`/sitemap.xml`) always lists the active cars and packages.
- **Security records** of failed logins are removed after about a day.

## If something looks wrong

| You see | Try |
|---|---|
| A car shows its usual price on far-ahead dates | Normal: the forecast covers 60 days. Recompute if it has not run today |
| "Some active cars have no forecast yet" | Smart Pricing → Recompute prices |
| A reset link was not received | Admin → Email → Send test email; check "Recent sends" for errors |
| A new car doesn't appear | Is it **Active**, and does its brand exist? Inactive cars are hidden everywhere |
| Packages menu missing on the website | No package is active yet |
