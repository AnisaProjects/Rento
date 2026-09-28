# APPENDIX A: PROJECT REPOSITORY AND VERSION HISTORY

## A.1 Repository

The source code, database migrations, tests, evaluation scripts and this report are kept in a Git repository:

- Repository: [INSERT GitHub link]
- Main branch: main; integration branch: dev; feature branches: feature/diagrams, feature/evaluation, feature/ml-evaluation, feature/report, feature/screenshots, feature/tests, feature/upload-noexec
- History listed below: up to commit 78780c3 on branch feature/report, 116 commits in total, of which 6 are merge commits

The history has not been rewritten or back-dated. Every commit made with the help of the AI assistant carries a "Co-Authored-By" line, except the automatic merge commit 8bd13fa, as stated in Section 3.2.3.

## A.2 Commits by Phase

Table: Table A.1 Commits by development phase (from the Git history) {widths=2.35,1.35,1.35,0.8}
| Phase | First commit | Last commit | Commits |
|---|---|---|---|
| Phase 0: stabilise and secure | 278f52a (2026-09-26 20:24) | 1b226cf (2026-09-26 21:03) | 21 |
| Phase 1: foundation | 08b18e8 (2026-09-26 21:10) | 6a7a044 (2026-09-26 22:24) | 13 |
| Phase 2: public redesign | 3d3c673 (2026-09-27 07:43) | 4daa61d (2026-09-27 09:17) | 12 |
| Phase 3: admin redesign | b604802 (2026-09-27 09:24) | 3d24921 (2026-09-27 09:54) | 8 |
| Phase 3: content, festivals, packages | 5e8b6c6 (2026-09-27 12:49) | b2019de (2026-09-27 13:12) | 6 |
| Phase 4: AI engine | c428671 (2026-09-27 13:32) | 20a3c36 (2026-09-27 13:52) | 9 |
| Phase 5: AI in the interface | d8c736f (2026-09-27 14:35) | 93da222 (2026-09-27 17:53) | 13 |
| Phase 6: polish | 8ef7f0f (2026-09-27 18:07) | 200ce12 (2026-09-27 20:59) | 11 |
| Phase 7: evaluation, tests, diagrams | a16a20d (2026-09-27 21:18) | 13e2cd5 (2026-09-27 22:32) | 6 |
| Phase 7: report (to Chapter 2) | fe77255 (2026-09-27 22:49) | 9151b7b (2026-09-27 23:13) | 2 |

## A.3 Commit History

Table A.2 lists every commit except merge commits, oldest first, with its date and time in Nepal time and its subject line.

Table: Table A.2 Commit history (non-merge commits) {widths=0.65,1.15,3.95}
| Commit | Date | Subject |
|---|---|---|
| 9250954 | 2026-09-26 20:24 | baseline before refactor |
| 278f52a | 2026-09-26 20:24 | Remove unused Vercel config (site runs locally on MAMP only) |
| 654a1c1 | 2026-09-26 20:31 | Centralise DB config and hide DB errors from pages |
| ac5d72f | 2026-09-26 20:31 | Harden sessions and require CSRF tokens on every POST form |
| 9ef6cc1 | 2026-09-26 20:34 | Replace MD5 passwords with password_hash and regenerate session on login |
| daaa5f4 | 2026-09-26 20:36 | Replace forgot-password with a time-limited token reset flow |
| 882b810 | 2026-09-26 20:46 | Convert admin action links to CSRF-protected POST forms |
| 83c2f6e | 2026-09-26 20:48 | Lock down admin vehicle image uploads |
| c812285 | 2026-09-26 20:48 | Block web access to internal folders and data files |
| b6e897f | 2026-09-26 20:49 | Escape user-controlled output and sanitise CMS page HTML |
| 04b24be | 2026-09-26 20:50 | Require login and valid dates before saving a booking |
| 42d66d6 | 2026-09-26 20:51 | Fix page.php loading two files that don't exist |
| 2e538ca | 2026-09-26 20:51 | Fix footer Contact Us link pointing to an empty CMS page |
| b9a37c9 | 2026-09-26 20:51 | Add charset and mobile viewport meta tags to every public page |
| de342cb | 2026-09-26 20:51 | Fix JS error on every admin dashboard load |
| f64a3e6 | 2026-09-26 20:52 | Fix car-listing brand/fuel filter always showing 0 listings |
| 34f73f6 | 2026-09-26 20:52 | Fix fatal error on the AI recommendation results page |
| 13b7902 | 2026-09-26 20:53 | Fix dynamic pricing so the model actually runs (it always fell back) |
| 57b1fe1 | 2026-09-26 21:00 | Add PRICING_ENABLED switch (off) so pages show the base price |
| 7a17f30 | 2026-09-26 21:01 | Give admin and customer logins separate sessions |
| aebae46 | 2026-09-26 21:02 | Rate-limit failed logins, reset requests and email checks |
| 1b226cf | 2026-09-26 21:03 | Remove stale training snapshot and stop tracking ml/data_cache |
| 08b18e8 | 2026-09-26 21:10 | Add Bootstrap 5.3.8, Font Awesome 6.7.2 and jQuery 3.7.1 (local copies) |
| d0bce5a | 2026-09-26 21:37 | Move public pages to shared layout includes, Bootstrap 5.3 and Font Awesome 6 |
| 1931a34 | 2026-09-26 21:39 | Remove unused front-end libraries and template leftovers |
| ba372d1 | 2026-09-26 21:46 | Delete dead CSS from style.css (9,086 -> 4,603 lines, 149 KB -> 80 KB) |
| 1f29baf | 2026-09-26 21:48 | Delete 75 unused images from assets/images (6 MB) |
| 713ecad | 2026-09-26 21:55 | Serve car photos as WebP with lazy loading, size attributes and real alt text |
| 3fe462f | 2026-09-26 21:56 | Show all public prices in rupees through format_price() |
| 65f10e6 | 2026-09-26 21:57 | Use one flash-message component for success/error notices |
| a1cb78f | 2026-09-26 22:00 | Log all PHP errors in dev mode and fix the notices on the public pages |
| 5902a83 | 2026-09-26 22:02 | Add gzip rules for when mod_deflate is enabled in MAMP |
| 5567c48 | 2026-09-26 22:12 | Give About Us, FAQs, Terms and Privacy their own page files |
| 88b631e | 2026-09-26 22:17 | Add a branded 404 page for missing URLs, cars and pages |
| 6a7a044 | 2026-09-26 22:24 | Clean URLs without .php, and readable car URLs |
| 3d3c673 | 2026-09-27 07:43 | Phase 2: design system, new header/footer/modals and home page |
| a6eaa53 | 2026-09-27 07:50 | Phase 2: rebuild car listing with real filters; search pages redirect to it |
| 7bad3dd | 2026-09-27 07:55 | Phase 2: redesign the car page, with bookings via toasts |
| ca7ba9e | 2026-09-27 07:56 | Phase 2: redesign Find My Car (recommendations) |
| dccbb95 | 2026-09-27 08:03 | Phase 2: redesign contact, About, FAQs, Terms and Privacy |
| 4e11bd9 | 2026-09-27 08:06 | Phase 2: redesign the account area |
| cf590a5 | 2026-09-27 08:19 | Phase 2: redesign reset-password and 404; drop the old template CSS; WCAG AA contrast |
| 149d27d | 2026-09-27 08:53 | Home: hero carousel, testimonial and brand sliders; white text on buttons |
| a08eaa4 | 2026-09-27 08:56 | FAQs as a searchable accordion; About page with image sections |
| f66a899 | 2026-09-27 09:05 | Find My Car and car listing: describe-your-trip smart search |
| e24028f | 2026-09-27 09:13 | Remove the unused old logo images and the unused r-btn--ghost style |
| 4daa61d | 2026-09-27 09:17 | Stop Find My Car from overwriting every vehicle's CarType |
| b604802 | 2026-09-27 09:24 | Admin: new shell (sidebar, top bar, breadcrumbs) and a dashboard with real data |
| 4fae714 | 2026-09-27 09:28 | Admin tables: vehicles, brands, users, testimonials, queries, subscribers |
| 53e7862 | 2026-09-27 09:36 | Admin vehicle form and image manager |
| 4ceb24e | 2026-09-27 09:38 | Manage Pages: Quill 2 editor instead of nicEdit |
| 05dc046 | 2026-09-27 09:41 | Admin bookings: one shared list with a status filter, and a new booking detail page |
| c75f45a | 2026-09-27 09:44 | Admin login, forgot password (token reset), change password and contact info |
| 1363c42 | 2026-09-27 09:45 | Remove the old admin theme (Bootstrap 3, Font Awesome 4, old DataTables, Chart.js 1) |
| 3d24921 | 2026-09-27 09:54 | Manage Pages: accessible name for Quill's text style picker |
| 5e8b6c6 | 2026-09-27 12:49 | Site content: every hardcoded public text and image editable from admin |
| c1ab02d | 2026-09-27 12:50 | Vehicles: active/inactive setting (inactive = hidden from the website and Find My Car) |
| 520bad6 | 2026-09-27 12:54 | Market research: new brands and 17 suggested vehicles, all inactive |
| 74899de | 2026-09-27 12:54 | Festival calendar: table, 40 researched dates and an admin page |
| 3345f23 | 2026-09-27 13:05 | Packages & services: public pages, request-booking enquiries and admin management |
| b2019de | 2026-09-27 13:12 | Admin: festival notes in their own column; long clipped text can't widen phone layouts |
| c428671 | 2026-09-27 13:32 | Follow-ups: enquiry notes, driver-only vehicles, POST logout |
| 39b5020 | 2026-09-27 13:35 | One rental-day rule (inclusive) everywhere; pricing settings in admin |
| 2af7914 | 2026-09-27 13:38 | Python: dedicated Python 3.12 venv, pinned requirements, DB settings from the environment |
| 8fe7cf0 | 2026-09-27 13:43 | Smart pricing: rule-based forecast (60 days x active cars), admin recompute, nightly job |
| 7db3445 | 2026-09-27 13:43 | Track logs/.htaccess (blocks the logs folder from the web); log files stay ignored |
| 662fd03 | 2026-09-27 13:44 | JSON API: price-quote and price-calendar |
| 8c89c92 | 2026-09-27 13:46 | Bookings store their price: quoted_price + pricing_details, set on the server |
| 51ae67d | 2026-09-27 13:50 | Recommendation engine rebuilt: real weighted scores and reasons, no DB writes |
| 20a3c36 | 2026-09-27 13:52 | Smart pricing switched on: live price on the car page through the API |
| d8c736f | 2026-09-27 14:35 | Recommendations: finer, honest scores |
| 03df0df | 2026-09-27 14:37 | Pricing: travel days before festivals; occupancy/demand off until 50 confirmed bookings |
| 3c68fbc | 2026-09-27 14:38 | Customer invoice: printable invoice / booking summary from My Bookings |
| efb84af | 2026-09-27 14:41 | Car cards: 'from' price from the forecast and data-backed badges |
| 459be0f | 2026-09-27 14:45 | Car page: price calendar with day prices, colours and a real savings hint |
| 0e56769 | 2026-09-27 14:48 | Admin AI Insights: suggestions per car, Apply, manual price overrides, 60-day chart |
| 5812d50 | 2026-09-27 14:49 | Smart Pricing: model health |
| 375db5d | 2026-09-27 14:50 | Vehicle form: smart price suggestion next to the base price |
| fb0be4c | 2026-09-27 14:52 | Packages: optional festival and travel-day pricing (default off) |
| ac8da48 | 2026-09-27 17:36 | Packages page: empty-state heading in order (h2 after h1) |
| 8280685 | 2026-09-27 17:49 | Data update: market-based prices, corrected specs, full descriptions |
| 2a6f377 | 2026-09-27 17:52 | Listing and budgets: 'from' price for filter and sort, budgets sized for market prices |
| 93da222 | 2026-09-27 17:53 | FAQ: explain smart pricing instead of 'one daily price' |
| 8ef7f0f | 2026-09-27 18:07 | Items 4-5: rento DB user, nightly job, BS 2084 reminder |
| 806c54f | 2026-09-27 18:08 | Rugged / off-road flag used for mountain and rough-road recommendations |
| dd91648 | 2026-09-27 19:03 | Accessibility: focus rings, skip link, calendar keys, announcements, reduced motion |
| bece38a | 2026-09-27 19:13 | SEO: canonical URLs, Open Graph / X cards, JSON-LD, sitemap.xml and robots.txt |
| ae8eab1 | 2026-09-27 19:17 | Motion: skeleton loaders for AI results and prices, staggered reveal |
| 810722c | 2026-09-27 19:43 | Dark mode: system-following theme toggle for the site and admin |
| c5b54a8 | 2026-09-27 19:57 | Email: optional SMTP (PHPMailer) for reset links, booking and enquiry confirmations |
| 0703205 | 2026-09-27 20:03 | Legal: Nepal Terms and Privacy saved as drafts; draft workflow in Manage Pages |
| fed57aa | 2026-09-27 20:07 | Production readiness: deployment checklist and production settings check |
| c978529 | 2026-09-27 20:11 | Docs: README, admin guide, docs index; ml/README rewritten; old pricing notes archived |
| 200ce12 | 2026-09-27 20:59 | Final test fixes: editor heading order and dark colours, inline messages announced, toast region |
| a16a20d | 2026-09-27 21:18 | Security: never execute scripts from the site-image upload folder |
| fcb71c7 | 2026-09-27 21:26 | ML evaluation: train and compare models on demand and price data (offline) |
| 619cc26 | 2026-09-27 21:29 | Evaluation: recommendation scenarios (precision@3, hit@1, rule checks) and pricing checks |
| fb3e3fa | 2026-09-27 22:22 | Tests: PHPUnit unit + integration suites, pytest for pricing and forecast, performance and page weight |
| ce2d9c6 | 2026-09-27 22:28 | docs: UML/DFD/ERD diagrams (Mermaid sources + rendered PNGs) |
| 13e2cd5 | 2026-09-27 22:32 | docs: report screenshots (desktop, mobile, dark, admin) and README schema, ML, tests, credits |
| fe77255 | 2026-09-27 22:49 | docs: FYP report preliminary pages and Chapter 1 (Introduction) |
| 9151b7b | 2026-09-27 23:13 | docs: report Chapter 2 (Literature Review) |
| 05c7564 | 2026-09-28 08:32 | docs: report Chapter 3 (Methodology) |
| 915cfa0 | 2026-09-28 08:59 | docs: report Chapter 4 (System Design) |
| a7d0866 | 2026-09-28 08:59 | docs: track appendix items promised in Chapters 2 and 4 |
| 73aeee4 | 2026-09-28 09:00 | docs: state the one merge commit without the co-author line |
| f6f5bb6 | 2026-09-28 09:22 | docs: report Chapter 5 (Implementation) |
| 9767fec | 2026-09-28 09:34 | docs: report Chapter 6 (Testing and Evaluation) |
| c977af4 | 2026-09-28 09:40 | docs: report Chapter 7 (Conclusion and Future Work) |
| 78780c3 | 2026-09-28 09:43 | docs: describe the price target honestly as a derived day price |
