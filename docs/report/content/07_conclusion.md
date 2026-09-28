# CHAPTER 7: CONCLUSION AND FUTURE WORK

## 7.1 Introduction

This chapter concludes the report. Section 7.2 summarises the work carried out, and Section 7.3 assesses it against the objectives set in Chapter 1. Section 7.4 states the conclusions of the study, Section 7.5 discusses its limitations, Section 7.6 proposes future enhancements, and Section 7.7 closes with final remarks.

## 7.2 Summary of the Work

The project set out to address a problem observed in the Nepali car rental market: prices that are flat or adjusted informally regardless of demand, little help for customers in choosing a vehicle for a trip, and bookings arranged mainly by telephone and messaging applications. The literature review showed that demand-based pricing is well established in car rental, airlines and hotels, that variable prices are better accepted when they are bounded and explained, and that content- and knowledge-based recommendation suits situations with little interaction data. No Nepali platform surveyed combined these ideas.

Rento was developed in eight phases from an open-source PHP car rental template and an earlier machine-learning prototype, with the help of an AI coding assistant and under the direction of the student. The template was first secured and stabilised, and its public site, administration area and most of its database were then rebuilt. The intelligent components were added on this base: a rule-based smart pricer with seven types of factors and a −15% to +25% cap; a forecast pipeline that keeps a price with its reasons for every active car for the next 60 days; a price calendar, stored quotes and invoices that make these prices visible and binding; a recommendation engine that interprets a plain-language trip description and ranks cars with explained scores; and offline experiments that compared machine-learning models for demand and price prediction on external data. The system was tested with 100 automated tests, twelve system test cases, an evaluation of all 480 forecast prices and twelve recommendation scenarios, and measurements of performance, page weight and accessibility.

## 7.3 Achievement of the Objectives

Table 7.1 assesses the project against the seven specific objectives of Section 1.3.2.

Table: Table 7.1 Achievement of the specific objectives {widths=2.2,0.8,2.75}
| Objective | Status | Evidence |
|---|---|---|
| 1. Responsive web application with PHP, MySQL and a JSON API, covering listing, booking, invoices, packages and administration | Achieved | Chapters 4 and 5; 18 functional requirements traced to tests in Table 6.10; layouts tested at 1366 and 390 pixels |
| 2. Smart pricing engine with demand factors, configurable limits and a reason for every adjustment | Achieved | Section 5.7; all 480 forecast prices within the caps and equal to an independent calculation (Table 6.5); readable reasons tested |
| 3. Forecast pipeline for 60 days, recomputed nightly and after every relevant change | Achieved | Section 5.8; nightly job and change triggers; single-transaction replacement; test_batch_forecast suite |
| 4. Recommendation engine for a natural-language trip description, with match scores and reasons | Achieved | Section 5.10; normalised precision at three of 0.94 and hit rate of 0.91 on eleven scored scenarios; all rule checks passed |
| 5. Training and evaluation of machine-learning models against baselines | Achieved, on external data | Section 5.9; tree ensembles best in all tasks; learned pricer prepared but locked until about 200 bookings exist |
| 6. Recognised web security practices | Achieved | Table 5.11; security unit and HTTP tests passed; internal files refused |
| 7. Unit, integration and system testing and evaluation of performance, accessibility and responsiveness | Largely achieved | Chapter 6; no user acceptance testing and no testing in browsers other than Chrome |

The general objective, to design, develop and evaluate a secure web-based car rental platform for Nepal that prices vehicles dynamically, explains every price and recommends suitable vehicles, has therefore been met, with the qualification that the dynamic pricing is rule-based rather than learned, for the reasons given in Section 7.4.

## 7.4 Conclusions

Four conclusions can be drawn from the study.

First, demand-based pricing can be introduced by a small rental business from the first day, without historical booking data, if it is based on explicit rules. The Nepali festival calendar, weekends, travel seasons and booking lead time provide enough known signals to set prices that follow the expected pattern of demand, as the 60-day forecast of the Toyota Fortuner shows, with prices rising from Rs 10,000 on an ordinary weekday to the capped Rs 12,500 on a Dashain travel day.

Second, transparency and control can be built into dynamic pricing. Every price in Rento is shown in advance with its reasons, limited by a cap, stored with the booking and reproduced unchanged on the invoice, and every automatic price can be overridden by the operator. These properties respond directly to the findings on price fairness reviewed in Chapter 2, and the evaluation confirmed that the system keeps them for every car and every day of the forecast.

Third, machine learning is not yet the right tool for Rento's live pricing, but it is the right next step. The experiments showed that tree ensembles are the most accurate of the models compared, but also that their advantage over simple baselines was small for hourly demand and that the apparently excellent price results depend on seeing the same vehicle models in training and testing. With four real bookings, a learned pricer could not be trained or validated on Rento's own data. Keeping the rules live, while preparing the models, the switch and the threshold for a learned pricer, is therefore a sound approach to the cold-start problem.

Fourth, a simple, explained recommendation model can be useful with a small fleet. The weighted scoring engine recommended a relevant car first in ten of eleven scored scenarios and gave reasons and cautions that reflect its actual criteria. Its one clear failure, a request for a luxury car, shows both the limit of keyword-based understanding and how easily the model can be extended.

## 7.5 Limitations

The study has the following limitations, several of which were introduced in Section 1.6 and confirmed by the testing.

- **No real demand data.** Rento has only four real bookings. The pricing percentages were chosen by judgement and from the market survey, and their effect on revenue and occupancy could not be measured. The occupancy and demand factors and the learned pricer have not yet been used live.
- **External data for the experiments.** The demand dataset is not Nepali booking data, and the price target is a day price derived by the prototype from the four-hour prices listed on one marketplace, with only seven distinct levels. The experimental results show how the models behave but cannot be transferred directly to Rento.
- **Single market benchmark.** Only one operator published self-drive prices, so the base prices and the "below market" comparison rest on a single source.
- **Keyword-based trip understanding.** The trip parser recognises a fixed set of words and place names. It does not understand other phrasings, spelling mistakes or Nepali text, and it has no concept of luxury or prestige.
- **Small evaluation sets.** The recommendation engine was evaluated on twelve scenarios and eight active cars, with relevance judged by the developer.
- **Limited user testing.** No user acceptance test with customers or rental operators was carried out, and the interface was tested only in Google Chrome, without other browsers or real mobile devices.
- **Features not tested or not implemented.** Email notifications were not tested end to end because no SMTP server was configured. There is no online payment, no mobile application and no conversational assistant.
- **Security hardening.** The site does not yet send security headers such as a Content Security Policy or protection against framing, and the database has no foreign key constraints; relationships are maintained by the application code.
- **Local deployment and provisional data.** The system runs only on a local server and has not been deployed or load-tested on real hosting. The festival dates for BS 2084 are provisional until the government publishes its official list, and the festival calendar must be updated every year.
- **Starting point and assistance.** The system was built from an open-source template, and an AI assistant was used for much of the coding and documentation, as described in Section 3.2.

## 7.6 Future Enhancements

The following enhancements are proposed, in approximate order of priority.

1. **Deployment and user testing.** Deploy the system on a hosting service with HTTPS, following the deployment guide and the production check, configure email, and carry out user acceptance testing with customers and at least one rental operator.
2. **Security headers and constraints.** Add a Content Security Policy and related headers, and, after the legacy data have been cleaned, add foreign key constraints to the main relationships.
3. **Online payment.** Integrate the payment services widely used in Nepal, such as eSewa and Khalti, to take advance payments for bookings, which the market survey identified as a differentiator.
4. **Learned pricing.** Once about 200 bookings exist, train demand and price models on Rento's own data, evaluate them with time-ordered and grouped validation against the rule-based pricer, and use them first to suggest adjustments to the rule percentages and later, if they prove better, to set prices within the same caps and with explanations.
5. **Evaluation of the pricing rules.** Record occupancy and revenue per car and period, and compare periods or cars with and without selected factors, to test whether the rules improve revenue without reducing bookings.
6. **Richer trip understanding.** Add a luxury or segment criterion, recognise more places and phrasings, including Nepali, and consider a language model to interpret trip descriptions, while keeping the explained scoring model for the ranking itself.
7. **Official calendar import.** Import the festival and holiday dates from the official list each year, and warn the administrator when the calendar for the next year is missing.
8. **Fleet and marketplace features.** Support several branches, vehicle owners who list their own cars, and features found in the market survey, such as hourly hire, one-way drop fees and long-term discounts.

## 7.7 Final Remarks

Rento shows that the ideas of revenue management and recommender systems can be adapted to a small car rental business in Nepal, where data are scarce and customers are used to flat or negotiated prices. By starting with transparent, bounded rules and explained recommendations, and by preparing the data structures, models and switches for learned pricing, the system can be useful from the first booking and become more intelligent as its data grow. The project also provided practical experience of securing and rebuilding an existing system, designing and evaluating intelligent components honestly, and documenting the work, including the use of AI assistance, in a way that others can verify.
