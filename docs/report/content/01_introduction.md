# CHAPTER 1: INTRODUCTION

## 1.1 Background of the Study

Vehicle rental is a practical necessity in Nepal. Much of the country is reached only by road, public transport between cities is limited, and domestic travel peaks sharply around the national festivals of Dashain and Tihar, when large numbers of people travel from Kathmandu to their home districts. Visitors, families and businesses therefore rely on hired cars, jeeps and vans for trips to destinations such as Pokhara, Chitwan, Lumbini and Mustang, as well as for airport transfers and daily use within the Kathmandu valley.

A market survey carried out for this project in September 2026 (documented in the project repository) shows how the local rental market currently operates. The largest online marketplace, Sajilo Rental, lists more than a thousand vehicles, almost all rented with a driver, at prices that are nearly flat by vehicle category: about Rs 8,000 per day for hatchbacks, sedans and the Mahindra Scorpio, and about Rs 10,000 for vans (Sajilo Rental, n.d.). Only one operator surveyed, Self Drive Nepal, published a self-drive rate card, with rates of about Rs 5,000 per day for hatchbacks and sedans and Rs 7,000 to Rs 10,000 for sport utility vehicles (Self Drive Nepal, 2025). Most other operators handle bookings through enquiry forms, telephone calls and messaging applications such as WhatsApp and Viber, and few publish fixed prices for tours or packages. Where seasonal surcharges exist, they are usually stated informally; one operator, for example, notes that prices rise by 10–15% in the October–November peak season (Nepal Vehicle Hiring, 2026).

In other industries with fixed capacity and perishable inventory, such as airlines, hotels and international car rental, prices have long been set dynamically according to expected demand. This practice, known as revenue management or yield management, aims to sell the right capacity to the right customer at the right price (Weatherford & Bodily, 1992; Talluri & van Ryzin, 2004). Car rental companies were among its early adopters: National Car Rental reported substantial revenue gains after introducing a demand-based pricing and fleet system (Geraghty & Johnson, 1997), and Hertz applied similar techniques to rental rates and fleet allocation (Carroll & Grimes, 1995). More recently, online platforms have combined such pricing with machine-learning models of demand, as in the pricing tips offered to Airbnb hosts (Ye et al., 2018).

A second development is the use of recommender systems to help customers choose among many options. Recommender systems filter a catalogue according to a user's stated needs or past behaviour and present the most suitable items with an explanation (Ricci et al., 2015). For car rental, the relevant needs are practical: the number of travellers, the budget, the type of road and the purpose of the trip. A customer planning a family trip to Pokhara, for instance, needs a comfortable car with at least five seats, whereas a trip on the rough mountain roads of Mustang calls for a rugged four-wheel-drive vehicle.

This project, Rento, brings these ideas to the Nepali rental context. Rento is a web-based car rental platform for self-drive cars, cars with a driver and tour packages, with all prices in Nepalese rupees. It sets a price for every car on every day from explainable demand rules, shows customers why a price is higher or lower, and recommends vehicles for a trip described in plain language. The system was developed from an existing open-source PHP car-rental template, which was rebuilt almost entirely during the project; Chapter 3 describes this starting point and the work carried out.

## 1.2 Problem Statement

Car rental businesses in Nepal usually charge a fixed daily price that does not reflect changes in demand. The same car costs the same during Dashain and Tihar, on weekends and for last-minute bookings as on a quiet weekday, even though demand in these periods is much higher. Businesses either lose revenue at peak times or apply informal surcharges that customers cannot anticipate. Customers, in turn, find prices unclear, because the published price often excludes fuel, driver costs and peak-season charges that are only explained over the phone.

Customers also find it difficult to choose the right vehicle for their trip. Rental websites typically offer a list of cars with basic filters, but they do not help a customer decide whether a compact car is suitable for a family of five, or whether a particular sport utility vehicle can handle the road to Mustang. The choice is left to the customer or to a telephone conversation with the operator.

Finally, bookings are largely handled manually over telephone and social media. This makes availability uncertain, prevents customers from seeing and comparing prices in advance, and leaves no reliable record of the price that was agreed.

The problem addressed by this project is therefore the lack of an online rental platform for Nepal that sets demand-aware prices transparently, recommends suitable vehicles for a given trip, and records bookings at a price calculated and stored by the system. Rento addresses this with an online booking platform that combines explainable, AI-based pricing with trip-based vehicle recommendation.

## 1.3 Objectives

### 1.3.1 General Objective

The general objective of this project is to design, develop and evaluate a secure web-based car rental platform for Nepal that prices vehicles dynamically according to expected demand, explains every price to the customer, and recommends suitable vehicles for a trip.

### 1.3.2 Specific Objectives

The specific objectives are:

1. To develop a responsive web application for customers and administrators with a PHP backend, a MySQL database and a JSON application programming interface (API), covering vehicle listing, booking, invoices, packages and administration.
2. To design and implement a smart pricing engine that adjusts each car's daily price for weekends, festivals and festival travel days, travel seasons, booking lead time, occupancy and recent demand, within configurable limits, and that gives a human-readable reason for every adjustment.
3. To build a forecast pipeline that computes and stores the price of every active car for the next 60 days, recomputed automatically every night and after every relevant change.
4. To implement a recommendation engine that interprets a natural-language trip description and ranks the available cars with a match score and reasons.
5. To train and evaluate machine-learning models for demand and price prediction, and to compare them with baselines using standard regression metrics, in preparation for a learned pricer.
6. To apply recognised web security practices, including password hashing, protection against cross-site request forgery (CSRF) and injection, rate limiting and server-side price calculation.
7. To test the system through unit, integration and system tests, and to evaluate its performance, accessibility and responsiveness on desktop and mobile screens.

## 1.4 Scope of the Study

The scope of the study covers the features, users and environment summarised in Table 1.1.

Table: Table 1.1 Scope of the project
| Area | In scope | Out of scope |
|---|---|---|
| Users | Guests, registered customers and one or more administrators | Vehicle owners listing their own cars (marketplace model) |
| Services | Self-drive cars, cars rented only with a driver, and tour or transfer packages in Nepal | Other countries; buses and goods transport |
| Customer features | Search and filters, car page with a price calendar, trip-based recommendation, booking request, My Bookings and printable invoice, package enquiries, testimonials, FAQs, contact | Online payment, mobile application, live chat assistant |
| Administration | Bookings, vehicles, brands, packages and enquiries, festivals, smart-pricing settings, AI Insights, content, email and system checks | Accounting, payroll and fleet maintenance |
| Intelligence | Rule-based dynamic pricing, 60-day forecast, content-based recommendation, offline evaluation of machine-learning models | A learned pricer in live use (planned once enough bookings exist) |
| Prices and data | Nepalese rupees; festival calendar for BS 2083 and provisional BS 2084 | Currency conversion; automatic import of official holiday lists |
| Deployment | Local server (MAMP: Apache, MySQL and PHP on macOS) with a deployment guide for real hosting | Public cloud deployment |

## 1.5 Significance of the Study

For customers, Rento makes rental prices transparent. The price of every day is shown in advance with its reasons, such as a weekend or a Dashain travel day, and the total quoted when a booking is requested is stored and appears unchanged on the invoice. The recommendation feature helps customers choose a suitable vehicle without needing detailed knowledge of the fleet.

For rental businesses, the system shows how demand-based pricing can be introduced in a controlled and explainable way. Prices follow the festival calendar and booking patterns automatically, but the adjustments are limited by a configurable maximum discount and surcharge, and the administrator can review suggestions, apply them or set manual prices. Online booking requests with stored prices reduce the manual work of telephone bookings and the risk of disputes over the agreed price.

Academically, the project applies revenue-management and recommender-system concepts to a small business in a developing market, where historical booking data is scarce. It demonstrates a practical approach to this "cold start": a transparent rule-based pricer is used from the first day, while machine-learning models are trained and evaluated on external data so that they can replace or complement the rules once enough bookings exist.

## 1.6 Limitations

The study has the following limitations:

- **Limited booking data.** The system holds only four real bookings, two of them confirmed. The machine-learning models could not be trained on Rento's own data and were evaluated on external datasets; the occupancy and demand pricing factors remain switched off until 50 confirmed bookings exist, and the learned pricer until about 200.
- **External datasets.** The demand dataset is an external hourly rental dataset and not Nepali booking data, and the price dataset consists of publicly listed prices collected by scraping one marketplace, from which the prototype derived the day price used as the target. Results from these datasets indicate how the models behave but cannot be transferred directly to Rento.
- **Market benchmarks.** Only one published self-drive rate card was found, so the market price used for comparison is based on a single source.
- **Festival calendar.** Nepali festivals follow the lunar calendar and move every year. The dates must be checked each year; the BS 2084 dates in the system are provisional until the government publishes its official holiday list.
- **Deployment.** The system runs on a local server only. A deployment guide and an automated production check were prepared, but the system has not been deployed publicly.
- **Features not implemented.** There is no online payment gateway, mobile application or conversational AI assistant.
- **Template origin and AI assistance.** The system was built on an open-source template, and an AI coding assistant was used during development, as stated in the Declaration of Authenticity and in Chapter 3.

## 1.7 Organization of the Report

This report is organised into seven chapters. Chapter 1 has introduced the background, problem, objectives, scope, significance and limitations of the project. Chapter 2 reviews related work on dynamic pricing, demand forecasting, recommender systems and existing rental platforms, and identifies the research gap. Chapter 3 describes the development methodology, the software requirements specification, the tools and technologies, the feasibility study and the system architecture. Chapter 4 presents the system design through use case, data flow, entity relationship, class, sequence and activity diagrams, together with the user interface design. Chapter 5 explains the implementation of the modules, the application programming interface, the database and the algorithms, including the pricing engine, the forecast pipeline, the machine-learning experiments and the recommendation engine. Chapter 6 reports the test plan, test cases and results of unit, integration and system testing, and the performance evaluation. Chapter 7 summarises the work, draws conclusions and suggests future enhancements. The references and appendices follow.

