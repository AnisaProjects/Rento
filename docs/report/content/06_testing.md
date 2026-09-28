# CHAPTER 6: TESTING AND EVALUATION

## 6.1 Introduction

This chapter reports how Rento was tested and how well its intelligent components perform. Section 6.2 presents the test strategy and environment. Sections 6.3 to 6.5 report the unit, integration and system tests. Sections 6.6 and 6.7 evaluate the smart pricing engine and the recommendation engine, Section 6.8 reports the performance measurements, and Section 6.9 the accessibility, compatibility and usability checks. Section 6.10 relates the requirements of Chapter 3 to the evidence that they are met, and Section 6.11 summarises the chapter. The machine-learning experiments, which evaluate models for a future learned pricer, were reported in Section 5.9. All results in this chapter were produced by scripts in the project repository, and the raw results are stored with the report.

## 6.2 Test Strategy and Environment

Testing was carried out at four levels, summarised in Table 6.1. Unit tests check individual functions in isolation; integration tests send real HTTP requests to the running site and check the database afterwards; system tests exercise complete workflows in a real browser; and evaluation scripts measure the quality of the pricing and recommendation engines and the performance of the site. Tests were run after every phase, and the automated suites were run again for this report on 28 September 2026.

Table: Table 6.1 Test plan {widths=1.2,1.55,3.0}
| Level | Tools | Scope |
|---|---|---|
| Unit | PHPUnit 12.5 (30 tests); pytest 9.1 (46 tests) | Helper functions, pricing and booking totals, security functions, trip parser; pricing rules and forecast computation |
| Integration | PHPUnit with an HTTP client (24 tests) | JSON API, booking flow with stored price and invoice, protection of internal files, form tokens |
| System | Headless Google Chrome scripts and manual testing | End-to-end booking and enquiry, link crawl, JavaScript errors, layout overflow, keyboard use, axe accessibility checks |
| Evaluation | Python and Node.js scripts | 480 forecast rows and quotes, 12 recommendation scenarios, response times, page weight |

All tests ran on the development machine described in Table 3.6, against the local MAMP server (Apache 2.4.62, PHP 8.3.14, MySQL 8.0.40) with the real Rento database. Tests that need an account used a temporary user with a random password, which was deleted, together with its bookings, at the end of the test; after the runs for this report, the database again contained its four real bookings and no test users.

## 6.3 Unit Testing

Table 6.2 summarises the unit tests. All 76 unit tests passed.

Table: Table 6.2 Unit test results {widths=1.7,2.85,0.6,0.6}
| Suite | What is tested | Tests | Result |
|---|---|---|---|
| HelpersTest (PHP) | Rental days counted inclusively (same day, month end, leap day, invalid and empty dates); SQL version of the rule; rupee formatting; clean URLs | 11 | Passed |
| PricingTest (PHP) | Booking total uses the stored quoted price; old bookings without a quote; rounding of package surcharges; market categories; readable reasons | 5 | Passed |
| SecurityTest (PHP) | bcrypt verification; MD5 hashes accepted once and upgraded; CSRF token must match; scripts removed from content HTML; encryption of secrets | 5 | Passed |
| TripParserTest (PHP) | Budget amounts; named and inferred car types; family context and default budget; empty input | 9 | Passed |
| test_pricing_properties (Python) | Caps and rounding for every day at several base prices, occupancy levels and request counts; random stacking of factors; travel days never combined with the festival surcharge; strongest festival counted once; season separate; lead-time boundaries at 0, 2, 3, 44 and 45 days; weekend days; demand factors only when switched on; readable reasons | 33 | Passed |
| PricingRulesTest (Python) | Plain weekday at base price; weekend; cap; discount floor; festival and season; travel days; demand gate; booked days | 8 | Passed |
| test_batch_forecast (Python) | One row per car and day; rows capped, rounded and explained; demand gate; booked days unavailable; manual override wins and keeps the rule reasons | 5 | Passed |

The Python property tests are the strongest evidence for the correctness of the pricer. The test that stacks random combinations of factors confirms that no combination can produce a price outside the −15% to +25% range, and the boundary tests confirm that the last-minute factor applies up to two days ahead and the early-booking factor from 45 days, with no factor in between.

## 6.4 Integration Testing

The integration tests in Table 6.3 run against the live local site. The booking test logs in as the temporary user, requests a booking, and then checks in the database and on the pages that the stored price equals the sum of the forecast day prices, that the invoice shows the same total, and that the revenue on the administrator's dashboard changes by exactly that amount. The security tests request 14 internal files, among them the configuration files, a migration, the Python pricer, a saved model, the scraped dataset, the log, the decisions document, the Composer files, the Git folder, the PHPUnit configuration and the test bootstrap, and check that each one is refused. All 24 integration tests, with 184 assertions, passed.

Table: Table 6.3 Integration test results {widths=1.5,3.05,0.6,0.6}
| Suite | What is tested | Tests | Result |
|---|---|---|---|
| ApiTest | Quote total equals the sum of the forecast days; bad input rejected with 400; only GET allowed (405); calendar covers the rest of the month; recommendations ranked with reasons; rate limit answers 429 | 6 | Passed |
| BookingFlowTest | Stored price, invoice and dashboard revenue agree; booking without a form token rejected; invoice visible only to its owner | 3 | Passed |
| SecurityHttpTest | 14 internal files return 403 or 404; login without a form token does not log in | 15 | Passed |

During the development of these tests, the security test found a real problem: the PHPUnit configuration file and the test bootstrap were served by the web server. Both were blocked with web server rules, the production check was extended to report them, and the test passed afterwards.

## 6.5 System Testing

System tests were carried out at the end of Phase 6 with scripts that drive a real browser, and manually. Table 6.4 lists the test cases and their results.

Table: Table 6.4 System test cases {widths=0.55,1.75,2.25,0.55}
| ID | Test case | Expected and actual result | Result |
|---|---|---|---|
| TC01 | Customer logs in, selects dates on the price calendar and books | The quote of Rs 25,450 equals the sum of the day prices; the booking confirmation shows the same total | Pass |
| TC02 | Customer opens the invoice | The invoice shows the stored total of Rs 25,450 and the price of each day | Pass |
| TC03 | Administrator confirms the booking | The status changes to confirmed for the customer; revenue on the dashboard rises by exactly Rs 25,450 | Pass |
| TC04 | Visitor sends a package enquiry | A thank-you message is shown and announced; the administrator sees the enquiry and marks it as contacted | Pass |
| TC05 | Booking and login without a valid form token | The request is rejected and nothing is stored (automated, Table 6.3) | Pass |
| TC06 | Customer opens another customer's invoice | Access refused (automated) | Pass |
| TC07 | Link crawl of 25 public and 228 administration pages | No broken links; the sitemap lists 25 URLs, all returning status 200 | Pass |
| TC08 | 23 public and 41 administration pages at 1366 and 390 pixels, in light and dark mode | No JavaScript errors, no failed requests and no horizontal overflow | Pass |
| TC09 | PHP error log after the tests | No errors; only the expected notice that dates beyond the forecast use the base price | Pass |
| TC10 | Price calendar and booking with the keyboard only | Every function reachable; arrows move by day and week; focus always visible | Pass |
| TC11 | Recompute prices from the Smart Pricing page | The run queued from the page (27 September 2026, 13:42) completed with 480 rows for 8 cars | Pass |
| TC12 | Find My Car with "Mountain roads to Mustang, 5 people" | The rugged Toyota Fortuner is ranked first with reasons; non-rugged SUVs carry a caution | Pass |

After the end-to-end tests, all test data were deleted and the prices recomputed, and the dashboard revenue returned to its previous value of Rs 57,000.

## 6.6 Evaluation of the Smart Pricing Engine

The pricing evaluation script read the whole forecast of 27 September 2026, 480 rows for 8 cars and 60 days, and recalculated every row independently from the settings, the festivals and the base prices. Its results are shown in Table 6.5. No row was outside the caps, no row was incorrectly rounded, and every stored price agreed with an independent calculation. In 48 rows, the sum of the factors exceeded the +25% cap, with a highest uncapped sum of +27%, and all 48 were capped and marked as such. Because fewer than 50 bookings were confirmed, the occupancy and demand factors were correctly off in every row. In addition, six quotes from the JSON API for two cars and three date ranges, including a Dashain weekend, were compared with the sum of the forecast days, and all six matched.

Table: Table 6.5 Checks of the 60-day forecast (27 September 2026) {widths=4.0,1.75}
| Check | Result |
|---|---|
| Rows (cars × days) | 480 (8 × 60) |
| Rows outside the −15% / +25% limits | 0 |
| Rows not rounded to Rs 50 | 0 |
| Rows whose price differs from an independent calculation | 0 |
| Rows in which the factors exceed the cap (all capped and marked) | 48 |
| Highest sum of factors before the cap | +27% |
| Range of multipliers | 1.00 to 1.25 |
| API quotes equal to the sum of the forecast days | 6 of 6 |

Table 6.6 shows the breakdown of the price of the Toyota Fortuner on typical days. It confirms that the engine behaves as specified: prices rise at weekends, before and during Dashain and Tihar, and at the last minute, the early-booking discount cancels the season surcharge in November, and a Dashain travel day on a Friday reaches the cap. The price never fell below the base price in this period, because the only discount, for early booking, applied only to days that were also in the trekking season.

Table: Table 6.6 Price of the Toyota Fortuner (base Rs 10,000) on typical days {widths=1.5,0.85,2.35,0.5,0.55}
| Case | Date | Factors | Sum | Price (Rs) |
|---|---|---|---|---|
| Normal day | Wed 30 Sep | None | 0% | 10,000 |
| Last minute | Sun 27 Sep | Last minute +5% | +5% | 10,500 |
| Weekend | Fri 2 Oct | Weekend +8%, season +4% | +12% | 11,200 |
| Dashain travel day | Fri 9 Oct | Weekend +8%, travel days +15%, season +4% | +27%, capped at +25% | 12,500 |
| Dashain main day | Sun 18 Oct | Dashain +12%, season +4% | +16% | 11,600 |
| Tihar travel day | Thu 5 Nov | Travel days +15%, season +4% | +19% | 11,900 |
| Tihar main day | Sun 8 Nov | Tihar +12%, season +4% | +16% | 11,600 |
| Early booking | Thu 12 Nov | Season +4%, early booking −4% | 0% | 10,000 |

This evaluation shows that the engine applies its rules correctly and consistently. It does not show that the chosen percentages are optimal for revenue, which can only be measured with real bookings over a season; this limitation is discussed in Chapter 7.

## 6.7 Evaluation of the Recommendation Engine

The recommendation engine was evaluated with twelve trip scenarios, written to cover families, mountain roads, city driving, business, large groups, luxury, budgets, luggage, sports cars and electric cars. For each scenario, the set of relevant cars among the eight active cars was decided in advance from the car attributes, and the evaluation script called the recommendation endpoint and computed the precision at three, the normalised precision at three and the hit rate at one, as defined in Section 2.3.4. It also checked every returned car against the rules of the engine: enough seats, a price within the budget plus 10%, no inactive or driver-only car among the self-drive matches, scores in descending order, no claim that the customer asked for a type that was only inferred, and no claim about a fuel the car does not use. The results are shown in Table 6.7.

Table: Table 6.7 Results of the recommendation scenarios {widths=0.45,1.7,2.1,0.5,0.5,0.52}
| ID | Trip | First three results (match %) | P@3 | nP@3 | Hit@1 |
|---|---|---|---|---|---|
| R01 | Family trip to Pokhara under Rs 8,000 | Nissan Sunny (97), Nissan Kicks (82), Vitara Brezza (81) | 1.00 | 1.00 | Yes |
| R02 | Mountain roads to Mustang, 5 people | Toyota Fortuner (96), Nissan Kicks (79), Vitara Brezza (78) | 1.00 | 1.00 | Yes |
| R03 | Daily city commute in Kathmandu, cheap | Wagon R (93), Nissan Sunny (70) | 0.67 | 1.00 | Yes |
| R04 | Comfortable sedan for a business meeting | Nissan Sunny (96), BMW 5 Series (94), Nissan Kicks (60) | 0.67 | 1.00 | Yes |
| R05 | Trip to Chitwan for 7 people | Toyota Fortuner (96) | 0.33 | 1.00 | Yes |
| R06 | Luxury car for a wedding | Nissan Sunny (96), BMW 5 Series (95), Nissan Kicks (79) | 0.33 | 0.33 | No |
| R07 | SUV under Rs 7,500 | Nissan Kicks (83), Vitara Brezza (82) | 0.67 | 1.00 | Yes |
| R08 | Road trip with lots of luggage | Toyota Fortuner (95), Nissan Kicks (77), Vitara Brezza (76) | 1.00 | 1.00 | Yes |
| R09 | Sports car for a weekend drive | Nissan GT-R (92) | 0.33 | 1.00 | Yes |
| R10 | Off-road trip to a village in the hills for 2 | Toyota Fortuner (94), Nissan Kicks (72), Vitara Brezza (70) | 0.33 | 1.00 | Yes |
| R11 | Cheap hatchback for two in the city | Wagon R (94), Nissan Sunny (64) | 0.33 | 1.00 | Yes |
| R12 | Electric car for the city | Wagon R (71), Nissan Sunny (44), BMW 5 Series (42) | – | – | – |
| | Mean of 11 scored scenarios | | 0.61 | 0.94 | 0.91 |

> R12 is not scored because the fleet has no electric car; the check confirmed that no result claimed to be electric.

The mean precision at three was 0.61, which is low mainly because several scenarios have only one or two relevant cars in a fleet of eight, so that even a perfect list cannot reach 1. The normalised precision at three, which allows for this, was 0.94, and the first result was relevant in 10 of the 11 scored scenarios. All 12 scenarios passed the rule checks, and the median response time was 4 milliseconds.

The one clear failure was R06, a luxury car for a wedding. The parser read "luxury" as a budget of Rs 15,000 and as a wish for comfort, and inferred a sedan, but the engine has no criterion for luxury as such. As a result, the Nissan Sunny, an ordinary sedan, scored slightly higher than the BMW 5 Series because it is cheaper, and the Audi Q8 and Nissan GT-R did not appear in the first three. The scenario shows a limitation of keyword-based scoring: a word can carry a meaning, here prestige, that none of the criteria represents. Adding a luxury or segment criterion is a simple improvement for future work. R12 shows the opposite behaviour, which is desirable: when the requested fuel is not available, the engine still suggests suitable city cars with lower match scores and does not claim that they are electric.

## 6.8 Performance Evaluation

Response times were measured with 30 requests to each page and endpoint, with the client and the server on the same machine. The results in Table 6.8 show median times between 1.9 and 5.2 milliseconds and 95th-percentile times below 9 milliseconds. These times exclude network latency and are therefore lower than a user would experience on a hosted server, but they show that the server-side work is small, which is the result of precomputing the prices: no page or API call runs the pricing rules.

Table: Table 6.8 Server response times (30 requests each, local server) {widths=2.8,0.95,0.95,1.05}
| Request | Median (ms) | 95th percentile (ms) | Size (bytes) |
|---|---|---|---|
| API: price quote (3 days) | 2.0 | 4.2 | 1,268 |
| API: price calendar (1 month) | 3.1 | 5.0 | 5,088 |
| API: recommendation (trip text) | 2.6 | 3.4 | 1,761 |
| Home page | 5.2 | 8.9 | 48,310 |
| Car listing | 3.8 | 5.6 | 35,910 |
| Car page (Toyota Fortuner) | 4.2 | 6.0 | 39,235 |
| Find My Car | 1.9 | 4.4 | 24,492 |

Page weight was measured in headless Chrome with the cache disabled, after scrolling each page so that all lazily loaded images were fetched, and the same script had been used on the original template pages before the redesign. Table 6.9 and Figure 6.1 show that the redesigned pages transfer between 33% and 80% less data and make fewer requests. The largest saving, on the home page, came from removing unused libraries and images and serving photographs as WebP in sizes suited to the screen. On a phone-sized screen of 390 pixels, the home page transfers 606 KB and the car page 552 KB.

Table: Table 6.9 Page weight of the original template and of Rento (desktop, 1366 pixels) {widths=1.35,1.2,1.2,1.2,0.8}
| Page | Template: requests, KB | Rento: requests, KB | Rento mobile: requests, KB | Change in KB |
|---|---|---|---|---|
| Home | 40, 4,090 | 23, 831 | 21, 606 | −79.7% |
| Car listing | 38, 2,386 | 22, 540 | 22, 540 | −77.4% |
| Car page | 35, 1,171 | 29, 781 | 26, 552 | −33.3% |
| Find My Car | 28, 898 | 14, 387 | 14, 387 | −56.9% |
| FAQs | 29, 885 | 13, 389 | 13, 389 | −56.0% |
| Contact | 30, 919 | 14, 380 | 14, 380 | −58.7% |

![Figure 6.1 Data transferred per page by the original template and by Rento (desktop)](figures/page_weight.png){width=5.7}

## 6.9 Accessibility, Compatibility and Usability

Accessibility was tested with the axe-core engine (Deque Systems, n.d.), which checks pages against WCAG rules, together with scripts that operate every page with the keyboard. At the end of Phase 6, axe reported no violations, including colour contrast, on 24 public pages, 42 administration pages and 8 customer pages, in light and dark mode, at widths of 1366 and 390 pixels. The keyboard script pressed the Tab key through every page at both widths and found no focus stop without a name, no invisible focus and no keyboard trap, and 29 keyboard scenarios for the interactive widgets, such as the mobile menu, the login dialog, the slideshow, the FAQ accordion, the price calendar and Find My Car, all passed. Several problems were found and fixed during this testing, including focus rings with a contrast of only about 1.7:1, a skip link that scrolled without moving the focus, and price calendar days whose "cheaper" or "higher" status was shown only by colour.

Compatibility was tested in Google Chrome at desktop and phone widths in light and dark mode, as reported in test case TC08, and the screenshots in Chapters 4 and 5 show the layouts at both widths. Other browsers, such as Safari, Firefox and Microsoft Edge, and real mobile devices were not tested systematically.

The usability of the system was assessed by the developer through the end-to-end scenarios and the recommendation scenarios. No user acceptance test with customers or rental operators was carried out within the project. [INSERT results of user acceptance testing, if carried out before submission.]

## 6.10 Requirements Traceability

Table 6.10 relates each requirement of Section 3.4 to the tests that provide evidence that it is met.

Table: Table 6.10 Requirements and test evidence {widths=0.85,2.2,2.7}
| Requirement | Summary | Evidence |
|---|---|---|
| FR1 | Accounts and password reset | SecurityTest; SecurityHttpTest (login token); TC01 |
| FR2 | Car search and filters | TC07, TC08; sequence of Figure 4.11 tested manually |
| FR3, FR4 | Car page, price calendar and quote | ApiTest (quote, calendar); Table 6.5 (6 of 6 quotes); TC01, TC10 |
| FR5, FR6 | Booking with stored price, My Bookings and invoice | BookingFlowTest; TC01–TC03, TC05, TC06 |
| FR7 | Find My Car | ApiTest (recommend); TripParserTest; Table 6.7; TC12 |
| FR8, FR9 | Packages, enquiries and content | TC04, TC07 |
| FR10–FR14 | Administration, festivals, pricing settings, AI Insights | TC03, TC07, TC08, TC11 |
| FR15 | 60-day forecast | test_batch_forecast; Table 6.5; TC11 |
| FR16 | JSON API | ApiTest; Table 6.8 |
| FR17 | Notifications | Not tested end to end: email is off until SMTP is configured; encryption of the SMTP password tested in SecurityTest |
| FR18 | Dashboard and system check | BookingFlowTest (revenue); TC03, TC07 |
| NFR1 | Security | SecurityTest, SecurityHttpTest, BookingFlowTest; Table 5.11 |
| NFR2 | Correctness of prices | Property tests; Tables 6.5 and 6.6; BookingFlowTest |
| NFR3 | Explainability | Readable-reason tests; rule checks of Table 6.7 |
| NFR4 | Performance | Tables 6.8 and 6.9 |
| NFR5, NFR6 | Accessibility and compatibility | Section 6.9; TC08, TC10 |
| NFR7 | Reliability and data integrity | Additive migrations with backups; single-transaction forecast; base-price fallback (TC09) |
| NFR8–NFR10 | Maintainability, portability, privacy | Automated suites, documentation and production check; deployment not tested on real hosting |

## 6.11 Summary

This chapter reported the testing and evaluation of Rento. All 76 unit tests and all 24 integration tests passed, and the twelve system test cases passed in a real browser; the tests found and helped to fix real problems, such as exposed test files and accessibility defects. The evaluation of the pricing engine confirmed that all 480 forecast prices respected the caps and rounding and agreed with an independent calculation, and that API quotes equalled the sum of the day prices. The recommendation engine reached a normalised precision at three of 0.94 and a hit rate of 0.91 on eleven scored scenarios, with one clear failure for a luxury request. The server responded in a few milliseconds, the pages transfer up to 80% less data than the original template, and no accessibility violations were found. The main gaps are the lack of user acceptance testing, of testing in other browsers and on real devices, of end-to-end testing of email, and of an evaluation of the revenue effect of the pricing rules. Chapter 7 draws the conclusions of the project.
