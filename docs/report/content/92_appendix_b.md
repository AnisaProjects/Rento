# APPENDIX B: USER MANUAL

This appendix explains how customers and administrators use Rento. A longer administrator guide is kept in the repository as docs/admin-guide.md.

## B.1 Customers

### B.1.1 Finding a Car

1. Open the home page and choose a car type, brand or budget in the search form, or open Cars in the top menu.
2. On the car listing, narrow the results with the filters for type, brand, fuel and maximum price, and sort them by price. Each card shows the lowest price of the next 30 days ("from Rs X/day") and badges such as "Below market".
3. To get suggestions for a trip, open Find My Car and describe the trip in your own words, for example "Family trip to Pokhara under Rs 8,000, 5 people". The criteria that Rento understood appear as labels. Add dates to see only cars that are free and the price for your dates. Each suggested car shows its match percentage, the reasons and any cautions.

![Figure B.1 Car listing with filters and "from" prices](screenshots/03_listing_desktop.png){width=5.2}

### B.1.2 Booking a Car

1. Open a car page. The price calendar shows the price of each day for the coming months; cheaper days, higher-priced days and booked days are marked. Point to or select a day to see why its price is higher or lower.
2. Log in, or register if you do not have an account. Choose the pickup and return dates; the total and the reasons appear below the dates.
3. Add a message if needed, for example a pickup time, and select Book now. The request appears in My Bookings as "new" with the price you were shown, which is the price you will pay.
4. When the administrator confirms the booking, its status changes to "confirmed". Open My Bookings and select Invoice to see or print the invoice with the price of each day.
5. Cars marked "With driver" and tour or transfer packages are requested with an enquiry form instead; the operator replies with a quote.

![Figure B.2 My Bookings with the status and stored price of each booking](screenshots/09_my_bookings_desktop.png){width=5.2}

### B.1.3 Account

Use Profile to update your details and Update Password to change your password. If you forget your password, select Forgot password in the login window; a link valid for 30 minutes is sent to your email address. The theme button in the header switches between the system setting, light mode and dark mode.

## B.2 Administrators

### B.2.1 Daily Tasks

1. Open the administration area at /admin/ and sign in. The dashboard shows the revenue of confirmed bookings, active and pending bookings, fleet use and new enquiries.
2. Open Bookings, then New, to review requests. Open a booking to see the customer, the dates and the stored price of each day, and confirm or cancel it. A booking cannot be confirmed if it overlaps a confirmed booking of the same car. Confirming or cancelling recomputes the prices automatically.
3. Open Package enquiries and Contact Us Queries to answer customers, and mark each one as contacted.

![Figure B.3 Administration dashboard](screenshots/20_admin_dashboard.png){width=5.2}

### B.2.2 Fleet and Content

1. Under Vehicles, add or edit a car: its title, brand, type, seats, fuel, base price per day, photographs and the flags Active, Driver only and Rugged. Inactive cars are hidden from the website. Next to the base price, a smart price suggestion shows the 60-day average with its main reasons.
2. Under Packages, manage packages and their categories, photographs and prices; a package is shown on the website only when it is active.
3. Under Site content and Manage Pages, change the text and images of the public site and the content pages. Legal pages are edited as drafts and can be published only when no item marked "[TO CONFIRM]" remains.

### B.2.3 Pricing

1. Under Festivals, keep the festival calendar up to date: for each festival, enter the dates, the demand level, an optional travel window before it with its own percentage, and the source of the dates. Check the dates of the next Bikram Sambat year when the government publishes its holiday list.
2. Under Smart Pricing, adjust the percentage of each factor, the maximum discount and surcharge and the rounding. The model health panel shows which pricer is active and how many confirmed bookings exist compared with the thresholds. Select Recompute prices after major changes; the prices are also recomputed every night and after every relevant change.
3. Under AI Insights, compare each car's base price with its forecast average. Select Apply to use the suggested base price, or set a manual price for chosen dates, for example for a special event. Manual prices can be removed at any time.

![Figure B.4 Festival calendar in the administration area](screenshots/23_admin_festivals.png){width=5.2}

### B.2.4 Settings and Checks

Under Email, enter the SMTP details of an email provider and send a test email; until this is done, no emails are sent. Under Contact Info, enter the real address, telephone number and email of the business. Under System check, review the production settings before the site is opened to customers.
