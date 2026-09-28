# CHAPTER 4: SYSTEM DESIGN

## 4.1 Introduction

This chapter presents the design of Rento. It follows the structured and object-oriented notations commonly used to document information systems: a use case diagram for the functions offered to each user, data flow diagrams for the movement of data between processes and stores, entity relationship diagrams for the database, a class and module diagram for the structure of the code, sequence diagrams for the interactions behind the main functions, activity diagrams for the two most important workflows, and the design of the user interface. The diagrams use the Unified Modeling Language (UML) notation (Object Management Group, 2017) and the Gane and Sarson notation for data flow diagrams (Gane & Sarson, 1979). All diagrams describe the system as it was implemented; the names of tables, files and functions are those used in the source code.

## 4.2 Use Case Diagram

The use case diagram in Figure 4.1 shows the functions that Rento offers to its actors. A guest can browse and filter cars, view a car page with its price calendar, use Find My Car, register or log in, send an enquiry about a package or a car rented with a driver, and read the content pages. A registered customer can do everything a guest can do, which is shown by the generalisation between the two actors, and can also request bookings and view their bookings and invoices. The administrator manages bookings, the fleet, packages, content, festivals and pricing settings, and uses AI Insights to apply suggested prices or set manual overrides. Two external systems also interact with Rento: the operating system scheduler, which starts the nightly recomputation of the price forecast, and the mail server, which delivers notification emails when email is configured.

![Figure 4.1 Use case diagram of Rento](figures/diagrams/use_case.png){width=5.2}

The «include» relationships show functions that are always performed as part of another function. Requesting a booking always includes the price calendar of the car, because the dates are chosen on it, and a notification email; confirming or cancelling a booking includes a notification email; and changing festivals or pricing settings includes a recomputation of the forecast. Table 4.1 describes the most important use case, requesting a booking, in more detail.

Table: Table 4.1 Use case description: request a booking {widths=1.35,4.4}
| Item | Description |
|---|---|
| Actor | Registered customer |
| Precondition | The customer is logged in and the car is active and not driver-only |
| Main flow | 1. The customer opens a car page and selects the pickup and return dates on the price calendar. 2. The system shows the price of each day, the reasons for each adjustment and the total. 3. The customer adds an optional message and sends the request. 4. The system checks the form token, the dates and the availability of the car. 5. The system calculates the price on the server from the forecast and stores the booking with the quoted price and the breakdown by day. 6. The system requests a forecast recomputation, sends a confirmation email if email is configured, and shows the booking in My Bookings. |
| Alternative flows | Invalid form token: the request is rejected. Invalid dates or car already booked: an error is shown and the form is kept. Customer not logged in: the login form is shown first. |
| Postcondition | A new booking with status "new" and a stored price exists; the administrator can confirm or cancel it |

## 4.3 Data Flow Diagrams

Data flow diagrams describe how data moves through a system without showing the order of events (Gane & Sarson, 1979). In the diagrams that follow, squares are external entities, rounded boxes are processes with their number, and open-ended boxes are data stores with their identifier; stores marked with an asterisk are repeated in the same diagram to avoid crossing lines. Table 4.2 lists the data stores and the tables they contain.

Table: Table 4.2 Data stores used in the data flow diagrams {widths=0.6,2.25,2.9}
| Store | Tables | Contents |
|---|---|---|
| D1 | tblusers, admin | Customer and administrator accounts |
| D2 | tblvehicles, tblbrands | Fleet: vehicles with their attributes, base prices and flags, and brands |
| D3 | tblbooking | Bookings with dates, status, quoted price and per-day details |
| D4 | tbl_price_forecast, tbl_forecast_runs, tbl_price_overrides | 60-day price forecast, forecast run log and manual price overrides |
| D5 | tblfestivals, tblsettings | Festival calendar and changed pricing settings |
| D6 | tblpackages, tblpackagecategories, tblpackageimages, tblpackageenquiries | Packages, their categories and photos, and enquiries |
| D7 | tblpasswordresets, tblratelimits | Password reset tokens and rate-limit records |
| D8 | tblpages, tblsitecontent, tblmailsettings | Content pages, editable site text and email settings |

### 4.3.1 Context Diagram

The context diagram in Figure 4.2 shows Rento as a single process together with its four external entities. Customers send search criteria, trip descriptions, booking requests, enquiries and account details, and receive car lists, day prices with their reasons, recommendations, booking status and invoices. The administrator sends fleet, festival and pricing data, booking decisions and price overrides, and receives dashboard figures, AI Insights and the health of the pricing model. The scheduler sends the nightly recomputation trigger, and the system sends emails through the mail server.

![Figure 4.2 Context diagram (level 0 data flow diagram)](figures/diagrams/dfd_level0.png){width=5.7}

### 4.3.2 Level 1 Data Flow Diagrams

The level 1 diagram divides the system into nine processes. Because a single diagram with all nine processes would be difficult to read, it is shown in two parts. Figure 4.3 shows the processes used by customers: authentication and account management (1.0), browsing and searching (2.0), recommendation (3.0), price quotation (4.0) and booking and invoicing (5.0). The recommendation process reads the fleet, the forecast prices and the customer's rental history, and the quotation process reads the forecast prices and the existing bookings, so that the customer always sees prices and availability taken from the same data that the server uses when the booking is stored.

![Figure 4.3 Level 1 data flow diagram, part A: customer processes](figures/diagrams/dfd_level1a_customer.png){width=5.7}

Figure 4.4 shows the administrative and background processes: packages and enquiries (6.0), administration of the fleet, content, festivals and pricing (7.0), the price forecast (8.0) and email (9.0). The forecast process is triggered by the administrator, by any change to bookings, festivals, settings, base prices or overrides, and by the nightly scheduler. It reads the fleet, the bookings and the festival and pricing settings, and writes the 60-day prices with their reasons. The administrator reads the forecast and the health of the pricing model through process 7.0, in line with the rule that data stores are accessed only through processes.

![Figure 4.4 Level 1 data flow diagram, part B: administration and background processes](figures/diagrams/dfd_level1b_admin.png){width=5.7}

### 4.3.3 Level 2 Data Flow Diagrams

Figure 4.5 decomposes the booking process (5.0). A request passes through a check of the session and the form token (5.1), a validation of the dates, counted inclusively (5.2), and a check for overlapping bookings of the same car (5.3). The price is then calculated on the server from the forecast (5.4), so that any price sent by the browser is ignored, and the booking is stored with the quoted price and the breakdown by day (5.5). When the administrator confirms or cancels the booking (5.6), the system checks again that it does not clash with a confirmed booking. The invoice (5.7) shows the stored price rather than recalculating it, so the price the customer agreed to does not change later.

![Figure 4.5 Level 2 data flow diagram of process 5.0 (book and invoice)](figures/diagrams/dfd_level2_booking.png){width=5.5}

Figure 4.6 decomposes the forecast process (8.0). A trigger first queues a run (8.1). PHP then starts the Python pricer, passing the database settings and the pricing settings in environment variables rather than on the command line (8.2). The pricer loads the active cars, festivals, bookings and overrides (8.3), prices every car for every day of the next 60 days (8.4) and applies any manual overrides (8.5). It then replaces the whole forecast in a single database transaction, so that the website always reads either the old or the new forecast and never a partial one (8.6), and records the status of the run (8.7).

![Figure 4.6 Level 2 data flow diagram of process 8.0 (forecast prices)](figures/diagrams/dfd_level2_forecast.png){width=5.3}

## 4.4 Database Design

### 4.4.1 Entity Relationship Diagrams

The database contains 23 tables with 200 columns. Ten tables come from the original template and 13 were added during the project through 22 additive migrations, which created new tables or added columns without renaming or removing existing ones. Because the diagram of all 23 tables would be too large for one page, the database is shown in two entity relationship diagrams. Figure 4.7 shows the core of the system: users, bookings, vehicles and brands, together with the pricing tables. Figure 4.8 shows accounts, password resets, packages and their enquiries, and lists the seven tables that have no relationships. For readability, each entity shows its keys and its most important columns; the number of further columns is given at the bottom of each entity, and the complete list of columns is provided in the data dictionary in the appendices.

![Figure 4.7 Entity relationship diagram, part 1: bookings, fleet and smart pricing](figures/diagrams/erd_core.png){width=5.7}

![Figure 4.8 Entity relationship diagram, part 2: accounts, security, packages and content](figures/diagrams/erd_support.png){width=5.7}

### 4.4.2 Relationships and Keys

The database defines no foreign key constraints. The template did not use them, and the migrations did not add them, because adding constraints to existing tables with inconsistent legacy data could have failed or deleted data, which the rule of additive-only changes did not allow. The 13 relationships shown in the diagrams are therefore logical relationships: they are maintained by the PHP code, which reads the referenced record before writing a dependent one (for example, a booking is stored only for an existing, active car and a logged-in customer), and every query that uses them is a prepared statement. The diagrams draw these relationships with dashed lines to make this clear. Two relationships are conditional: a password reset refers either to a customer or to an administrator, depending on its AccountType column, and an enquiry refers either to a package or to a car that is rented only with a driver.

Most tables have an auto-increment integer primary key. The forecast table uses a composite primary key of the vehicle and the date, which guarantees that there is exactly one price for each car on each day, and the settings and mail settings tables use their key names as primary keys. Secondary indexes support the most frequent queries, such as the forecast by date, the overrides by vehicle and dates, the festivals by dates, the packages by active flag and sort order, and the rate-limit records by action, email or IP address and time.

### 4.4.3 Design of the Main Tables

Table 4.3 shows the booking table. The columns quoted_price, pricing_details and priced_at were added during the project so that each booking stores the price calculated by the server at the moment of the request, together with the price and reasons for each day. The date columns FromDate and ToDate were inherited from the template as text columns; they were kept unchanged to respect the additive-only rule, and the code validates and normalises the dates before storing and comparing them.

Table: Table 4.3 Data dictionary of tblbooking {widths=1.25,1.15,0.85,2.5}
| Column | Type | Null | Description |
|---|---|---|---|
| id | int, auto-increment | No | Primary key |
| BookingNumber | bigint | Yes | Booking number shown to the customer |
| userEmail | varchar(100) | Yes | Customer (logical reference to tblusers.EmailId) |
| VehicleId | int | Yes | Car (logical reference to tblvehicles.id) |
| FromDate, ToDate | varchar(20) | Yes | Pickup and return dates (inherited text columns, validated in PHP) |
| message | varchar(255) | Yes | Optional message from the customer |
| Status | int | Yes | 0 or empty: new; 1: confirmed; 2: cancelled |
| quoted_price | int | Yes | Total price in rupees calculated by the server (added) |
| pricing_details | text | Yes | Price and reasons for each day, as JSON (added) |
| priced_at | datetime | Yes | Time at which the price was calculated (added) |
| PostingDate | timestamp | No | Time of the request (default: current time) |
| LastUpdationDate | timestamp | Yes | Time of the last change (updated automatically) |

Table 4.4 shows the forecast table, which is the central table of the smart pricing engine. It holds one row for each active car and each day of the forecast period; with eight active cars and 60 days, it contained 480 rows at the time of writing. Each row stores the base price used, the multiplier, the final price, the reasons in JSON and whether the car is available on that day, so that the website can show the price calendar and the "from" price on the car cards without calculating anything when a page is requested.

Table: Table 4.4 Data dictionary of tbl_price_forecast {widths=1.25,1.15,0.85,2.5}
| Column | Type | Null | Description |
|---|---|---|---|
| VehicleId | int | No | Car; part of the composite primary key |
| ForecastDate | date | No | Day; part of the composite primary key (indexed) |
| BasePrice | int | No | Base daily price used for the calculation; a forecast for an older base price is ignored |
| Multiplier | decimal(6,4) | No | 1 plus the capped sum of the factor percentages (default 1.0000) |
| Price | int | No | Final day price in rupees after capping, rounding and any override |
| Reasons | text | Yes | JSON list of the reasons and their percentages |
| IsAvailable | tinyint(1) | No | 1 if the car is not booked on that day |
| RunId | int | Yes | Forecast run that wrote the row (logical reference to tbl_forecast_runs.id) |
| ComputedAt | timestamp | No | Time of calculation |

The pricing settings are stored in a key and value table, tblsettings, which contains only the values that the administrator has changed; the default values, such as the maximum discount of 15% and the maximum surcharge of 25%, are defined in the code. This design allows new settings to be introduced without changing the database structure. The festival table stores, for each festival, its dates, its demand level and an optional travel window before the festival with its own percentage, together with the source of the dates and whether they have been verified.

## 4.5 Class and Module Diagram

Rento is written mainly in procedural PHP, organised in modules: files in the includes and api folders that each group the functions for one concern. Only the recommendation engine is implemented as a PHP class. Figure 4.9 therefore shows the modules as classes with the stereotype «module», together with the one PHP class and the Python modules and data classes of the pricer; the database connection passed to most functions is omitted from the signatures for readability. The dependencies show that the JSON API and the recommendation engine both obtain prices only through the pricing module, which in turn starts the Python batch forecast. The pricing rules themselves are therefore implemented in a single place, the price_for_day function, and every part of the system that shows a price reads the result from the forecast table.

![Figure 4.9 Class and module diagram](figures/diagrams/class_diagram.png){width=5.7}

## 4.6 Sequence Diagrams

The sequence diagrams show the order of the messages exchanged between the user, the browser, the PHP modules, the Python pricer and the database for five important interactions.

Figure 4.10 shows the customer login. The form token is checked first, then the rate limit for the email address. The password is verified with the modern hash; if the account still has an old MD5 hash from the template, it is verified once and replaced by a new hash. On success, the session identifier is regenerated to prevent session fixation, and on failure the attempt is recorded for rate limiting.

![Figure 4.10 Sequence diagram: customer login](figures/diagrams/seq_login.png){width=5.7}

Figure 4.11 shows the search and filtering of the car listing. Every filter value is accepted only if it belongs to a list of allowed values, and the query uses bound parameters. The "from" price of each car, which is the lowest price of an available day in the next 30 days, is read from the forecast once per request.

![Figure 4.11 Sequence diagram: search and filter the car listing](figures/diagrams/seq_search.png){width=5.7}

Figure 4.12 shows the price quotation used by the price calendar. The JSON endpoint accepts only GET requests, is rate-limited to 120 requests in 15 minutes, and validates the car and the dates before asking the pricing module for a quote. The quote sums the day prices from the forecast, merges their reasons, uses the base price for any day beyond the forecast period, and checks whether the car is free.

![Figure 4.12 Sequence diagram: price quotation through the JSON API](figures/diagrams/seq_price_quote.png){width=5.7}

Figure 4.13 shows the recommendation process. The trip parser extracts the vehicle type, road type, number of travellers and budget from the description. The recommendation engine then excludes cars that cannot meet the hard requirements, scores the remaining cars with the weighted model presented in Section 2.3.4, sorts them, and removes any match below 40%.

![Figure 4.13 Sequence diagram: trip-based recommendation](figures/diagrams/seq_recommendation.png){width=5.7}

Figure 4.14 shows the complete booking interaction, from the customer's request, through the server-side quotation, storage of the booking and recomputation of the forecast, to the administrator's confirmation and the customer's invoice.

![Figure 4.14 Sequence diagram: booking request, confirmation and invoice](figures/diagrams/seq_booking.png){width=5.7}

## 4.7 Activity Diagrams

The activity diagram in Figure 4.15 shows the booking workflow across three swimlanes: the customer, the Rento system and the administrator. It makes explicit the two loops in the workflow, in which a customer who is not logged in is asked to log in and a request with invalid dates or for a car that is already booked returns to the date selection, as well as the check for clashes with confirmed bookings when the administrator confirms a booking.

![Figure 4.15 Activity diagram: booking workflow](figures/diagrams/activity_booking.png){width=4.9}

Figure 4.16 shows the workflow of the forecast recomputation. It includes the database lock that prevents two recomputations from running at the same time: a run that finds the lock taken waits up to 300 seconds, and all queued requests are then handled together. It also shows the gate that switches the occupancy and demand factors on only when at least 50 confirmed bookings exist, and the order in which the factors, the caps, the rounding and the manual overrides are applied to each car and day.

![Figure 4.16 Activity diagram: price forecast recomputation](figures/diagrams/activity_forecast.png){width=4.6}

## 4.8 User Interface Design

### 4.8.1 Design Principles

The user interface was designed around four principles. First, prices must be clear: every price is shown in Nepalese rupees in the form "Rs 10,500/day", the price calendar shows the price of each day before the customer chooses dates, and every adjusted price is accompanied by its reason. Second, the interface must work on mobile phones, which many Nepali customers use to browse, so every page was designed and tested at widths from 390 pixels, the width of a common phone, up to desktop screens. Third, the interface must be accessible: text and interface elements meet the colour contrast requirements of WCAG 2 level AA (World Wide Web Consortium, 2023), every function can be used with the keyboard, focus is always visible, messages are announced to screen readers, and animations are reduced when the user's system asks for reduced motion. Fourth, the interface should follow familiar conventions, such as a top navigation bar, cards for cars and forms with clear labels, so that customers do not need instructions.

### 4.8.2 Design System

The visual design is defined by a small design system of CSS custom properties, or design tokens, in a single file, which the Bootstrap 5.3 components also use. Table 4.5 summarises its main elements. The same tokens are redefined for dark mode, which follows the user's system setting by default and can be switched manually.

Table: Table 4.5 Main elements of the design system {widths=1.3,2.1,2.35}
| Element | Value | Use |
|---|---|---|
| Brand colour | Orange #ff5a36 | Accents and highlights; with near-black text (6.1:1 contrast) |
| Button colour | #d23f1c with white text | Buttons (4.7:1 contrast) |
| Orange text | #c43c1a | Links and orange text on white (5.2:1 contrast) |
| Dark sections | Ink #101114 | Footer, dark sections, headings |
| Status colours | Green, amber, red and blue, each with a text colour that meets 4.5:1 on its tinted background | Success, warning, error and information messages; cheaper, higher and booked days in the price calendar |
| Typography | Plus Jakarta Sans for headings, Inter for body text | All text |
| Corner radius | 6, 10, 14 and 20 pixels, and fully rounded pills | Inputs, cards, panels, badges |
| Layout | Bootstrap 5.3 grid with its standard breakpoints, and own adjustments below 576 and 992 pixels | Responsive page layouts |

### 4.8.3 Page Design

The main pages were designed from the customer's tasks. The home page (Figure 4.17) opens with a photographic carousel and a search form for the car type, brand and budget, followed by the fleet grouped by car type, the steps of booking, the reasons to choose Rento, an invitation to use Find My Car, testimonials and brands; the packages section appears only when at least one package is active. The car page (Figure 4.18) places the photographs and description of the car on the left and a booking panel on the right, with the price calendar at the top of the panel; each day in the calendar shows its price and is coloured when it is cheaper or more expensive than usual or already booked, and selecting a day shows the reasons for its price. On a phone, the booking panel is placed below the photographs and the calendar keeps its full size.

![Figure 4.17 Home page on desktop (left) and mobile (right)](figures/ui_home.png){width=5.7}

![Figure 4.18 Car page with the price calendar on desktop (left) and mobile (right)](figures/ui_car_page.png){width=5.7}

Find My Car (Figure 4.19) accepts a trip description in plain language, shows the criteria it has understood as small labels while the customer types, and presents the recommended cars as cards with their match percentage, reasons and any cautions. Figure 4.20 shows the dark mode of the home page and the car page.

![Figure 4.19 Find My Car recommendations on desktop (left) and mobile (right)](figures/ui_find_my_car.png){width=5.7}

![Figure 4.20 Dark mode of the home page (left) and the car page on mobile (right)](figures/ui_dark_mode.png){width=5.7}

The administration area uses the same design system with a sidebar and a top bar. It is designed for efficient management rather than for promotion: tables can be searched and sorted, all actions that change data are buttons in forms protected by tokens, and the pages for smart pricing, AI Insights and festivals explain each setting and show the effect of the current prices. The administration pages are presented with the implementation in Chapter 5.

## 4.9 Summary

This chapter presented the design of Rento. The use case diagram and its description defined the functions offered to guests, customers and the administrator. The data flow diagrams showed how data moves between the nine processes and eight data stores, and decomposed the booking and forecast processes, which are the core of the system. The entity relationship diagrams and data dictionaries described the 23 tables of the database, including the logical relationships and the tables added for smart pricing. The class and module diagram, sequence diagrams and activity diagrams showed how the code is organised and how the main interactions and workflows take place. Finally, the design principles, the design system and the main pages of the user interface were presented. Chapter 5 describes how this design was implemented.
