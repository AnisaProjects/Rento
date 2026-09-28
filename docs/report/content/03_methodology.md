# CHAPTER 3: METHODOLOGY

## 3.1 Introduction

This chapter describes how Rento was developed. Section 3.2 explains the development methodology, the starting point of the project, the use of an artificial-intelligence assistant and the project timeline recorded in the version control history. Section 3.3 describes the methods used to collect data and requirements. Section 3.4 presents the software requirements specification, Section 3.5 lists the tools and technologies used, and Section 3.6 reports the feasibility study. Section 3.7 describes the overall architecture of the system, and Section 3.8 summarises the chapter.

## 3.2 Development Methodology

### 3.2.1 Choice of Methodology

The project followed a phased, iterative and incremental development model. In incremental development, a system is built as a series of versions, each of which adds functionality to the previous one, and the specification, development and validation activities are interleaved rather than carried out once in strict sequence (Sommerville, 2016). Larman and Basili (2003) traced the long history of iterative and incremental development and noted that it reduces risk by producing working software early and by allowing requirements to be refined through feedback.

This model was chosen for three reasons. First, the project started from existing software rather than from a blank page, so the work consisted of a sequence of improvements to a system that had to keep working after each step. A linear waterfall model, in which all requirements are fixed before any design or coding, would have suited this situation poorly. Second, some requirements could only be settled after earlier work had been completed. For example, the prices used by the pricing engine depended on the market survey, and the festival pricing depended on the festival calendar research. Third, the intelligent components carried the greatest technical uncertainty, so they were built only after the platform had been secured and stabilised, which allowed them to be tested on a reliable base.

The work was divided into eight phases, shown in Figure 3.1 and summarised in Table 3.1. Each phase had a defined goal and followed the same cycle: the phase was planned and its scope agreed; the database was backed up and a working branch created; the changes were built and committed in small steps; automated and manual tests were run; and a phase report was reviewed by the student before the next phase began. Faults found in later phases were handled as follow-up fixes rather than by reopening earlier phases.

![Figure 3.1 Phased iterative development methodology](figures/diagrams/methodology.png){width=5.7}

Table: Table 3.1 Development phases and their main outputs
| Phase | Goal | Main outputs |
|---|---|---|
| 0 Stabilise and secure | Make the inherited system safe and working | Password hashing, CSRF tokens, secure password reset, protected uploads, rate limiting, separate admin and customer sessions, bug fixes |
| 1 Foundation | Prepare a maintainable code base | Shared layout files, Bootstrap 5.3 and Font Awesome 6, prices in rupees, removal of unused code and images, clean URLs |
| 2 Public redesign | Rebuild the customer-facing site | New design system, home page, car listing with filters, car page, Find My Car, account area, WCAG AA contrast |
| 3 Admin redesign and content | Rebuild administration and add content | New admin area, editable site content, active and inactive vehicles, market research data, festival calendar, packages and enquiries |
| 4 AI engine | Build the intelligent back end | Rule-based pricer, 60-day forecast, JSON API, stored booking prices, rebuilt recommendation engine |
| 5 AI in the interface | Show the intelligence to users | Price calendar, card prices and badges, invoice, AI Insights, price overrides, festival travel days |
| 6 Polish | Prepare for real use | Rugged flag, accessibility, search engine optimisation, dark mode, email, legal drafts, deployment guide, production check |
| 7 Evaluation and documentation | Measure and document the system | Machine-learning experiments, test suites, performance and page-weight measurements, diagrams, screenshots, this report |

### 3.2.2 Starting Point of the Project

Rento was not developed from nothing. Its starting point, recorded as the first commit of the project repository ("baseline before refactor", commit 9250954), consisted of two parts.

The first part was an open-source PHP car rental template named "Car Rental Portal" [INSERT template citation]. The template provided a small car rental website with vehicle listing, brand search, booking requests at a fixed price per day, testimonials, contact and newsletter forms, a set of content pages and an administration panel for vehicles, brands, bookings and users. It used ten database tables, Bootstrap 3 and PHP pages that mixed database queries with HTML.

The second part was an earlier machine-learning prototype developed by the student in August 2026, before the repository was created. According to the file timestamps, the scraper and the scraped Sajilo Rental dataset date from 27 August 2026 and the price model files from 29 August 2026. The prototype comprised a web scraper for public vehicle listings, data preprocessing scripts, a PyTorch neural network for price prediction, a gradient boosting demand model, and PHP pages that attempted to use these models for dynamic pricing and car recommendation. At the start of Phase 0, the dynamic pricing integration always fell back to the base price because the model call failed, and the recommendation results page ended with a fatal error; both faults were corrected in Phase 0 before the components were later rebuilt.

The inherited code also had serious security weaknesses. Passwords were stored as MD5 hashes, which are unsuitable for password storage; no form was protected against cross-site request forgery; and the password reset form allowed anyone who knew an account's email address and mobile number to set a new password. These weaknesses correspond to categories of the OWASP Top 10, notably broken access control, cryptographic failures and identification and authentication failures (OWASP Foundation, 2021), and they were the reason why Phase 0 was dedicated to security.

Table 3.2 compares the baseline with the system at the end of the project. The comparison is based on the code at commit 9250954 and on measurements reported in Chapter 6. It shows that the template served as a starting structure, but that the security layer, the user interfaces, most of the database, and all of the pricing, forecasting, recommendation, testing and documentation work were rebuilt or newly added during the project.

Table: Table 3.2 Comparison of the baseline template and the final system {widths=1.25,2.2,2.3}
| Aspect | Baseline (template and prototype) | Rento at the end of the project |
|---|---|---|
| Front-end libraries | Bootstrap 3.3.7 (public site) and 3.3.6 (admin), Font Awesome 4.4.0, jQuery 1.10.2 | Bootstrap 5.3.8, Font Awesome 6.7.2, jQuery 3.7.1, own design tokens, dark mode |
| Currency | US dollar sign ($) | Nepalese rupees (Rs) throughout |
| Password storage | MD5 hash | PHP password_hash (bcrypt); old MD5 hashes are upgraded at the next login |
| Password reset | New password set by anyone who knows the email address and mobile number | Time-limited, single-use reset link, emailed to the account when email is configured |
| Form protection | No CSRF protection; admin actions by GET links | CSRF token on every POST form; admin actions by POST only |
| Login protection | No rate limiting; one shared session | Rate-limited login and reset; separate admin and customer sessions |
| URLs | File names with .php | Clean URLs and readable car URLs; old URLs redirected |
| Pricing | Fixed price per day; prototype model not working | Seven-factor rule-based pricer, 60-day forecast, price calendar, stored quote and invoice |
| Recommendation | Prototype page with a fatal error that also overwrote vehicle data | Read-only weighted scoring engine with match percentage, reasons and cautions |
| Administration | Bootstrap 3 admin theme with basic lists | New admin area with dashboard, Smart Pricing, AI Insights, festivals, packages, content and system check |
| Database | 10 tables | 23 tables (13 added by 22 additive migrations) |
| PHP files (excluding libraries) | 56 | 122 |
| Home page weight (desktop) | 40 requests, 4,090 KB | 23 requests, 831 KB |
| Automated tests | None | 54 PHPUnit tests and 46 pytest tests |

### 3.2.3 Use of Artificial-Intelligence Assistance

An artificial-intelligence assistant, Claude (Anthropic), was used throughout the development and documentation of the project as a coding and documentation assistant. The assistant was used to inspect the inherited code, propose changes, write and refactor source code, database migrations, automated tests, evaluation scripts and diagrams, run tests and measurements on the local server, and draft and edit parts of this report.

The student directed this work. The student defined the goals and scope of every phase and set rules that the assistant had to follow, including that database changes must be additive only and preceded by a backup, that existing URLs, file names and form field names must be kept unless a change was explicitly approved, that each logical change must be committed separately, that no credential may be changed without the student's permission, and that no numbers, features or references may be invented in the documentation. At the end of each phase the assistant produced a report listing every changed file, the test results and the open issues, and the student reviewed and tested the result before approving the next phase. The student is responsible for the system and for the content of this report.

The use of the assistant is recorded openly in the version control history: every commit carries a "Co-Authored-By" line naming the assistant, except for one automatic merge commit (8bd13fa) created without the line; the history was not rewritten to add it. The assistant also explains the pace of development shown in Section 3.2.4. This use of AI tools is in line with the policy of Lincoln University College: [INSERT statement on the college's AI policy].

### 3.2.4 Version Control and Project Timeline

The project was managed with Git. The main branch holds the released state of the project, a dev branch collects completed work, and each larger piece of work in Phase 7 was developed on its own feature branch (for example feature/tests, feature/diagrams and feature/report) and merged into dev when complete. All commits were made locally and pushed to the remote repository by the student. The history has not been rewritten or back-dated.

Table 3.3 lists the activities of the project with their dates, and Figure 3.2 shows the recorded development phases as a Gantt chart generated directly from the commit times. The Git history begins with the baseline commit on 26 September 2026 and, up to the completion of Chapter 2 of this report (commit 9151b7b), contains 102 commits excluding merge commits. The rebuild of the system recorded in the repository therefore took place over about 27 hours on 26 and 27 September 2026. This short period reflects the use of the AI assistant described in Section 3.2.3; it does not include the earlier prototype work, which was not under version control, or the time spent on planning and reviewing between commits. The bars in Figure 3.2 run from the first to the last commit of each phase and show when work was committed rather than the total effort spent.

Table: Table 3.3 Project timeline
| Activity | Dates | Evidence |
|---|---|---|
| Project proposal and supervisor approval | [INSERT dates] | [INSERT] |
| Machine-learning prototype: scraping, preprocessing, price and demand models | 27–29 August 2026 | File timestamps of the prototype files |
| Phase 0 and Phase 1 | 26 September 2026 | 34 commits, 20:24–22:24 |
| Phase 2 to Phase 6 | 27 September 2026 | 59 commits, 07:43–20:59 |
| Phase 7: evaluation, tests, diagrams and screenshots | 27 September 2026 | 6 commits, 21:18–22:32 |
| Phase 7: writing of the report and slides | From 27 September 2026 to [INSERT submission date] | Commits on feature/report |
| Submission and presentation | [INSERT dates] | – |

![Figure 3.2 Gantt chart of the recorded development phases, generated from the Git commit history](figures/diagrams/gantt.png){width=5.7}

## 3.3 Data and Requirements Collection

The requirements and data for the project were collected by the following methods.

- **Analysis of the existing system.** The inherited template and prototype were reviewed page by page and file by file to identify faults, security weaknesses and missing features. The findings formed the requirements of Phase 0 and Phase 1.
- **Market survey of existing platforms.** The websites of Nepali rental operators were reviewed on 27 September 2026 to record their services, prices, inclusions and booking methods, as summarised in Section 2.2.4. The survey was used to set the base prices of the active fleet at the published self-drive rates of Self Drive Nepal (2025), to choose the vehicles and packages offered, and to define the comparison benchmarks shown on the car pages.
- **Festival calendar research.** The dates of the main Nepali festivals and public holidays for BS 2083 were taken from the official list published by the Ministry of Home Affairs (Ministry of Home Affairs, n.d.) and cross-checked with at least one other calendar. Because the official list for BS 2084 had not yet been published, the BS 2084 dates were taken from independent calendars and marked as provisional. Forty festival entries were recorded in the database.
- **Price dataset.** For the price experiment, 1,640 public vehicle listings were collected from the Sajilo Rental website with the scraper developed in the August prototype. The scraper collected only vehicle attributes and listed prices, such as the vehicle model, type, fuel, year and daily price, and waited one second between requests. No personal data were collected.
- **Demand dataset.** Because Rento has almost no booking history, the demand models were trained on an external hourly rental demand dataset, [INSERT demand dataset name and URL] ([INSERT citation]). The dataset contains 18,247 hourly observations and is used only to compare forecasting methods; it is not Rento booking data.
- **Literature and documentation.** Published research, reviewed in Chapter 2, and the documentation of the technologies used informed the design of the pricing, forecasting and recommendation components.

## 3.4 Software Requirements Specification

The requirements were specified following the general structure recommended in IEEE Std 830-1998 (IEEE, 1998), which distinguishes functional requirements, describing what the system must do, from non-functional requirements, describing the qualities with which it must do it.

### 3.4.1 Users of the System

The system has three classes of users. Guests can browse the fleet, view prices, use Find My Car, read content pages and send enquiries. Registered customers can additionally request bookings, view their bookings and invoices, and post testimonials. Administrators manage the fleet, bookings, packages, festivals, pricing settings, content and system settings through a separate administration area with its own login.

### 3.4.2 Functional Requirements

The functional requirements of the system are listed in Table 3.4.

Table: Table 3.4 Functional requirements {widths=0.55,1.2,4.0}
| ID | Requirement | Description |
|---|---|---|
| FR1 | Accounts | Customers can register, log in, log out, update their profile and change their password, and reset a forgotten password through a time-limited link. Administrators have a separate login. |
| FR2 | Car search | Users can browse active cars and filter them by keyword, type, brand, fuel and maximum price, sort them and page through the results. |
| FR3 | Car page | Each car has a page with its images, specifications, description and a price calendar that shows the price of each of the next 60 days with its reasons. |
| FR4 | Price quote | For chosen dates, the system shows the total price, the price of each day, the reasons for each adjustment and whether the car is available. |
| FR5 | Booking request | Logged-in customers can request a booking for valid dates. The server calculates the price and stores it with the booking, together with the breakdown by day. |
| FR6 | My Bookings and invoice | Customers can see the status of their bookings and print an invoice that shows the stored price. |
| FR7 | Find My Car | Users can describe a trip in plain language, with optional dates, budget and number of travellers, and receive a ranked list of suitable cars with a match percentage, reasons and cautions. |
| FR8 | Packages and services | Users can view tour, transfer and other packages and send an enquiry; cars available only with a driver are booked through an enquiry. |
| FR9 | Content and contact | The site provides About, FAQs, Terms and Privacy pages, testimonials, a contact form and a newsletter subscription. |
| FR10 | Fleet and booking management | Administrators can add and edit vehicles and brands, set vehicles active or inactive, mark rugged and driver-only vehicles, manage images, and confirm or cancel bookings. |
| FR11 | Content management | Administrators can edit content pages, draft legal pages and change the text and images of the public site. |
| FR12 | Festival calendar | Administrators can add and edit festivals with their dates, demand level and travel days. |
| FR13 | Smart pricing settings | Administrators can set the percentage of each pricing factor, the maximum discount and surcharge and the rounding, recompute the forecast and view the health of the pricing model. |
| FR14 | AI Insights | Administrators can see price suggestions and a 60-day price chart for each car, apply suggestions and set manual price overrides. |
| FR15 | Price forecast | The system computes and stores the price of every active car for the next 60 days every night and after every relevant change. |
| FR16 | Application programming interface | The system provides JSON endpoints for price quotes, price calendars and recommendations. |
| FR17 | Notifications | When email is configured, the system sends password reset links and confirmations of bookings and enquiries. |
| FR18 | Dashboard and system check | Administrators see summary statistics and a check of the production settings. |

### 3.4.3 Non-Functional Requirements

The non-functional requirements are listed in Table 3.5. They were grouped using the product quality characteristics of ISO/IEC 25010 (International Organization for Standardization, 2011), and the method used to verify each requirement is given; the results of the verification are reported in Chapter 6.

Table: Table 3.5 Non-functional requirements {widths=0.6,1.15,2.9,1.1}
| ID | Quality | Requirement | Verification |
|---|---|---|---|
| NFR1 | Security | Passwords are hashed with a modern algorithm; every state-changing form is protected against CSRF; all database queries use prepared statements; output is escaped; login and reset attempts are rate-limited; uploads cannot execute scripts; the application uses a least-privilege database user with secrets kept outside the repository. | Unit and HTTP security tests |
| NFR2 | Correctness of prices | Prices are always calculated on the server, stay within the configured caps, are rounded as configured, and the price stored with a booking is the price shown on the invoice. | Pricing checks and integration tests |
| NFR3 | Explainability | Every price adjustment and every recommendation is shown with a human-readable reason; the system never claims that a customer asked for something they did not ask for. | Recommendation scenarios |
| NFR4 | Performance | Pages and API calls respond quickly on the local server, and pages transfer less data than the original template. | Timing and page-weight measurements |
| NFR5 | Usability and accessibility | The interface meets WCAG 2 level AA colour contrast (World Wide Web Consortium, 2023), can be used with the keyboard, and respects the reduced-motion setting. | Automated accessibility checks and manual testing |
| NFR6 | Compatibility | The site works on desktop and mobile screens from 390 pixels wide, in light and dark mode. | Screenshots and manual testing |
| NFR7 | Reliability and data integrity | Database changes are additive and preceded by a backup; the forecast can be recomputed at any time; the site keeps showing base prices if the forecast is unavailable. | Migration review and tests |
| NFR8 | Maintainability | Shared layout and logic files, one commit per change, documentation for developers and administrators, and automated test suites. | Code review |
| NFR9 | Portability | The system runs on a standard Apache, PHP and MySQL stack, with configuration through environment variables and a deployment guide. | Production check |
| NFR10 | Privacy | Only the personal data needed for accounts and bookings are stored, and draft privacy and terms pages are prepared for review. | Review of the database and pages |

## 3.5 Tools and Technologies

Table 3.6 lists the tools and technologies used in the project, with the versions installed in the development environment.

Table: Table 3.6 Tools and technologies {widths=1.2,1.75,2.8}
| Category | Tool and version | Purpose |
|---|---|---|
| Local server | MAMP with Apache 2.4.62, PHP 8.3.14, MySQL 8.0.40 | Web server, server-side language and database |
| Back end | PHP Data Objects (PDO), Composer 2.9.2, PHPMailer 6.12 | Database access with prepared statements, dependency management, email |
| Front end | HTML5, CSS3, JavaScript, Bootstrap 5.3.8, Font Awesome 6.7.2, jQuery 3.7.1 | User interface, layout and icons |
| Admin interface | DataTables 2.3.8, Chart.js 4.5.1, Quill 2.0.3 | Tables, charts and the content editor |
| Intelligence | Python 3.12.14, pandas 3.0.6, NumPy 2.5.3, scikit-learn 1.9.1 (Pedregosa et al., 2011), PyMySQL 1.2.3 | Pricing rules, forecast pipeline and machine-learning models |
| Development only | PyTorch 2.14.0, matplotlib 3.11.2, pytest 9.1.1, PHPUnit 12.5.36 | Neural network experiment, charts and automated tests |
| Documentation | Mermaid, headless Google Chrome 153, Node.js 25.2.1, python-docx 1.2.0, Microsoft Word 16.113 | Diagrams, screenshots, measurements and this report |
| Version control | Git 2.52.0, GitHub | Source control and remote repository ([INSERT GitHub link]) |
| Development assistance | Claude (Anthropic); code editor [INSERT editor used] | Coding and documentation assistant, as described in Section 3.2.3 |
| Hardware | Apple MacBook Air with M3 processor and 16 GB memory, macOS 26.5 | Development and testing machine |

## 3.6 Feasibility Study

### 3.6.1 Technical Feasibility

The project was technically feasible with widely used, well-documented technologies. PHP and MySQL run on almost every shared hosting service, and the Python components need only a standard Python installation with a few libraries. The heaviest computation in normal operation, the 60-day forecast for every active car, is a rule-based calculation that completes quickly on an ordinary laptop, so no special hardware is required. The machine-learning experiments were run offline on the same machine. The main technical risk, the lack of booking data for training a learned pricer, was addressed by designing a rule-based pricer that works without data and by keeping the learned models switched off until enough bookings exist.

### 3.6.2 Operational Feasibility

The system is designed to be operated by a small business without technical staff. Administrators manage the fleet, festivals, prices and content through web forms, and the forecast is recomputed automatically every night and after every change. Every automatic price is explained and can be overridden, so the operator stays in control of prices. For customers, the booking process uses familiar web forms, and the prices and reasons are shown before a booking is requested. The main operational task that remains manual is checking the festival dates each year when the government publishes the official holiday list.

### 3.6.3 Economic Feasibility

All software used to build and run the system is free and open-source, including PHP, MySQL, Apache, Python and its libraries, Bootstrap and the other front-end libraries. The development used an existing laptop, so no hardware was purchased. Microsoft Word was used only to produce this report [INSERT licence, e.g. student licence]. Running the system publicly would require web hosting and a domain name, at a cost of [INSERT estimated annual hosting and domain cost]. Because the system can run on ordinary shared hosting, these costs are small compared with the potential benefit of online bookings and demand-based pricing for a rental business.

### 3.6.4 Schedule Feasibility

The project was completed within the time available for the final year project. The phased approach allowed a working system to exist at the end of every phase, so that the project could have been stopped at any point with a usable result. As explained in Section 3.2.4, the use of an AI assistant shortened the implementation considerably, which left time for evaluation, testing and documentation.

### 3.6.5 Legal and Ethical Feasibility

The system stores only the personal data needed for accounts and bookings: names, email addresses, telephone numbers, the optional profile fields inherited from the template (date of birth and address) and booking details. Draft Terms and Privacy pages were prepared with reference to the Individual Privacy Act, 2075 (2018) and the Consumer Protection Act, 2075 (2018) of Nepal, and they are kept as drafts until they are reviewed, because they must be checked by a qualified person before publication. The template on which the system is based is open-source and is credited in this report and in the project documentation. The price data used in the experiment were public listing prices collected at a slow rate, without personal data, and used only for research. Dynamic pricing raises questions of fairness, as discussed in Section 2.2.1; the system addresses them by capping every adjustment, showing prices in advance and explaining every change. The use of an AI assistant is disclosed in the Declaration of Authenticity, in Section 3.2.3 and in the commit history.

## 3.7 System Architecture

Rento follows a layered architecture, shown in Figure 3.3. The presentation layer consists of the public pages and the administration area, rendered in the browser with Bootstrap and a small amount of JavaScript for the price calendar, the trip search and the colour theme. The application layer runs on Apache and PHP: web server rules provide clean URLs and block access to internal folders, shared PHP modules implement security, pricing, price insights, recommendation, search engine optimisation and email, and a JSON application programming interface serves price quotes, price calendars and recommendations to the pages.

The intelligence layer is written in Python. The live pricer applies the pricing rules to every active car for the next 60 days and stores the results in the database. It is started by PHP after every relevant change and by the operating system scheduler every night. The machine-learning experiments run offline on external datasets and are not part of the live system. The data layer is a MySQL database with 23 tables, accessed by PHP through prepared statements and by Python through PyMySQL, using a database user with only the privileges the application needs.

![Figure 3.3 System architecture of Rento](figures/diagrams/architecture.png){width=5.7}

This separation keeps the web pages fast, because they read prices that have already been calculated rather than running the pricing model on every request, and it allows the pricing engine to be replaced by a learned model in the future without changing the pages or the API. The detailed design of the system is presented in Chapter 4.

## 3.8 Summary

This chapter described the methodology used to develop Rento. The system was developed in eight phases following an iterative and incremental model, starting from an open-source car rental template and an earlier machine-learning prototype, both of which were largely rebuilt. An AI assistant was used as a coding and documentation assistant under the direction of the student, and its use is recorded in the commit history, which shows that the recorded rebuild took place over about 27 hours in September 2026. Requirements were collected through analysis of the existing system, a market survey, festival calendar research and external datasets, and were specified as eighteen functional and ten non-functional requirements. The project was found to be technically, operationally, economically, legally and ethically feasible. Finally, the layered architecture of the system was presented. Chapter 4 describes the design of the system in detail.
