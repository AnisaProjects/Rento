# CHAPTER 2: LITERATURE REVIEW

## 2.1 Introduction

This chapter reviews the literature and existing systems that inform the design of Rento. The review is organised around the three intelligent functions of the system. The first is dynamic pricing, which is studied mainly under the heading of revenue management, together with research on how customers perceive the fairness of variable prices. The second is demand forecasting, which provides the evidence on which demand-based prices can be set and which motivates the machine-learning experiments of this project. The third is recommender systems, which help customers choose a suitable item from a catalogue. The chapter then examines the rental platforms currently operating in Nepal, using the market survey carried out for this project, and compares them with Rento.

Section 2.2 reviews related works in each of these areas and summarises them in Table 2.1 and Table 2.2. Section 2.3 presents the theoretical framework of the study: the rule-based pricing model, the ensemble learning methods and error metrics used in the experiments, and the weighted scoring model and precision measures used for recommendation. Section 2.4 identifies the research gap that Rento addresses, and Section 2.5 summarises the chapter.

## 2.2 Review of Related Works

### 2.2.1 Dynamic Pricing and Revenue Management

Revenue management, also called yield management, is the practice of controlling the price and availability of a fixed, perishable capacity in order to maximise revenue. Kimes (1989) identified the conditions under which it is most useful: capacity is relatively fixed, inventory perishes if it is not sold by a given date, demand fluctuates and can be forecast, customers can be segmented, and the marginal cost of a sale is low compared with the cost of adding capacity. A rental car meets all of these conditions. A day on which a car stands idle cannot be sold later, the size of the fleet cannot be changed quickly, and demand rises and falls with weekends, holidays and seasons.

Weatherford and Bodily (1992) proposed a taxonomy of what they called perishable-asset revenue management, which brings together overbooking, capacity allocation and pricing, and noted that the same principles apply to airlines, hotels and car rental. Talluri and van Ryzin (2004) later provided a comprehensive treatment of the theory and practice of the field. They distinguish quantity-based revenue management, in which the firm decides how much capacity to offer at each fixed price, from price-based revenue management, in which the price itself is changed over time in response to demand. Rento follows the second approach, because it changes the daily price of each car rather than the number of cars offered.

The theoretical basis of price-based revenue management was established by Gallego and van Ryzin (1994), who showed how the optimal price of a limited inventory depends on the remaining stock and the time left to sell it when demand is stochastic. Their model shows why prices should normally rise as the remaining capacity shrinks close to the sale date, and why a small number of fixed price levels can perform nearly as well as a continuously changing price. Den Boer (2015) reviewed the later literature on dynamic pricing and learning, in which the seller does not know the demand function in advance and must learn it from observed sales while setting prices. This problem is directly relevant to a new platform such as Rento, which has almost no sales history from which to estimate demand.

Car rental has a long history in this field. Carroll and Grimes (1995) described how Hertz developed its rate and fleet management over several decades, from simple rules towards systems that forecast demand and plan fleet movement between locations. Geraghty and Johnson (1997) reported that the revenue management system introduced at National Car Rental, which combined demand forecasting, pricing and capacity control, made a significant contribution to the company's recovery in the 1990s. Oliveira, Carravilla and Oliveira (2017) reviewed the literature on fleet and revenue management in car rental. They noted that car rental differs from airline and hotel revenue management because rental durations vary, cars can be moved between locations and the fleet can be adjusted over the medium term, and they proposed an integrated framework that links pricing with fleet decisions. These studies show that demand-based pricing is established practice among large car rental companies, but they concern firms with extensive booking histories and dedicated analytics teams, which a small Nepali operator does not have.

Online marketplaces have more recently applied machine learning to pricing. Ye et al. (2018) described the model used by Airbnb to suggest nightly prices to its hosts. The model estimates the probability that a listing will be booked on each night at different prices and combines this with a customised regression that suggests a price for every future date. The approach depends on a very large volume of booking data, and the suggested price is shown to the host, who can accept or override it. Rento adopts a similar division of responsibility: the system suggests and explains prices, while the administrator can apply or override them.

Dynamic pricing also raises questions of fairness. Kahneman, Knetsch and Thaler (1986) found that customers regard price increases as unfair when they appear to exploit a rise in demand rather than to reflect a rise in cost. Xia, Monroe and Cox (2004) developed a framework in which perceived fairness depends on comparisons with past prices, with prices paid by other customers and with prices charged by competitors, and on whether the reasons for a price difference are understood. Haws and Bearden (2006) showed experimentally that customers judge price differences between buyers more harshly than differences over time, and that satisfaction falls when a customer learns that another customer paid less. These findings suggest that variable prices are better accepted when they are limited, predictable and explained. Rento therefore caps every adjustment, shows the price of each future day in advance and gives a plain reason, such as a weekend or a Dashain travel day, for every change.

### 2.2.2 Demand Forecasting

Pricing according to demand requires an estimate of future demand. Hyndman and Athanasopoulos (2021) present the main forecasting methods for time series, from simple benchmarks to exponential smoothing and regression models. They stress that every forecasting method should be compared with simple benchmarks. One such benchmark is the seasonal naïve method, which predicts that a value will equal the value observed at the same point of the previous season, for example the same hour one week earlier. A model that cannot outperform such a benchmark adds little value. The experiments in this project follow this advice by comparing every model with a seasonal or median baseline.

Tree-based ensemble methods are widely used for demand prediction from tabular data. Breiman (2001) introduced the random forest, which averages the predictions of many decision trees, each trained on a bootstrap sample of the data and a random subset of the features, and showed that it reduces the variance of single trees while remaining robust to noise. Friedman (2001) developed gradient boosting, which builds an additive model by fitting each new tree to the errors of the model built so far. Chen and Guestrin (2016) described XGBoost, a scalable implementation of gradient boosting that became popular in data science competitions. Grinsztajn, Oyallon and Varoquaux (2022) compared tree-based models with deep neural networks on a range of tabular datasets and found that tree-based models still performed better on typical medium-sized tabular data, partly because they are less sensitive to uninformative features. This supports the choice of random forest and gradient boosting as the main candidates for Rento's learned pricer.

Makridakis, Spiliotis and Assimakopoulos (2018) compared statistical and machine-learning forecasting methods on a large set of monthly time series and found that the machine-learning methods were often less accurate than simple statistical methods, while also being more costly to compute. They argued that machine-learning methods should be evaluated carefully against statistical benchmarks before being adopted. Their findings are consistent with the experience of this project, in which the more complex models gained only modestly over the seasonal baseline on hourly demand, as reported in Chapter 5.

The choice of evaluation method also matters. Bergmeir and Benítez (2012) studied cross-validation for time series and noted that standard random cross-validation can give optimistic results when observations are dependent in time, whereas a blocked or time-ordered split preserves the order of the data. The experiments in this project therefore use a chronological train and test split for the demand data. For error measurement, Hyndman and Koehler (2006) reviewed the common measures of forecast accuracy and discussed their weaknesses, including the instability of percentage errors when actual values are close to zero. Willmott and Matsuura (2005) argued that the mean absolute error is a more natural measure of average error than the root mean squared error, whereas Chai and Draxler (2014) argued that the root mean squared error is more appropriate when errors are expected to follow a normal distribution and large errors should be penalised more heavily. Because the two measures answer different questions, this project reports both, together with the coefficient of determination.

### 2.2.3 Recommender Systems

Recommender systems suggest items that are likely to be useful to a particular user. Ricci, Rokach and Shapira (2015) describe the main families of recommender systems: collaborative filtering, which recommends items that similar users have liked; content-based filtering, which recommends items whose attributes match the user's preferences; knowledge-based systems, which reason explicitly about how item attributes meet the user's stated requirements; and hybrid systems that combine these approaches. Burke (2002) surveyed hybrid recommender systems and noted that knowledge-based methods are especially suitable when items are purchased rarely and each purchase depends on specific requirements, as in the choice of a car or a house, because there is little rating history to learn from.

Collaborative filtering has been very successful where large amounts of interaction data are available. Koren, Bell and Volinsky (2009) showed how matrix factorisation models, which represent users and items by latent factors learned from ratings, achieved leading accuracy in the Netflix Prize competition. However, collaborative methods suffer from the cold-start problem: they cannot recommend well for new users or new items that have no ratings. Schein, Popescul, Ungar and Pennock (2002) examined this problem and showed that methods which use item content perform better when interaction data are missing. A small rental platform with a fleet of eight active cars and a handful of bookings is an extreme cold-start case, so a collaborative approach is not feasible for Rento.

Content-based methods avoid this limitation by matching item attributes with the user's profile. Lops, de Gemmis and Semeraro (2011) reviewed the state of the art in content-based recommendation and identified its advantages: it does not depend on other users, it can recommend new items immediately and its recommendations can be explained by pointing to the matching attributes. Its main weakness is that its quality depends on how well the attributes describe the items. In Rento, the relevant attributes of a car, such as its type, seats, fuel, price and suitability for rough roads, are recorded in the database, and the customer's requirements are taken from a plain-language description of the trip. The approach therefore combines content-based matching with knowledge-based rules about which vehicle types suit city, highway and mountain roads.

Explanation is an important quality of a recommender system. Tintarev and Masthoff (2015) identified the purposes that explanations serve, including transparency, trust, persuasiveness and helping users to make good decisions, and noted that an explanation should reflect the real reasoning of the system. Rento shows a match percentage and a list of reasons, such as "7 seats for your 6 travellers" or "Rugged, off-road capable: built for mountain and rough roads", derived directly from the criteria that contributed to the score.

For evaluation, Herlocker, Konstan, Terveen and Riedl (2004) reviewed the measures used for recommender systems and distinguished accuracy of predicted ratings from the quality of the ranked list shown to users. For a system that shows a short list of items, measures such as precision among the top-ranked items are appropriate, because the user will usually consider only the first few suggestions. Rento's recommendation engine is evaluated in this way in Chapter 6, using precision at three and the rate at which the first suggestion is relevant.

### 2.2.4 Existing Rental Platforms

A market survey of Nepali car rental websites was carried out for this project on 27 September 2026. Its findings are recorded in the project repository and summarised here.

Sajilo Rental is the largest online marketplace found. It lists vehicles from many owners, all of which are rented with a driver, and offers instant booking with online payment through eSewa, Khalti and cards (Sajilo Rental, n.d.). Its daily prices are nearly flat within each vehicle category, at about Rs 8,000 for hatchbacks, sedans and the Mahindra Scorpio and about Rs 10,000 for vans, and the customer pays for fuel separately. No change of price with the date, the season or demand was observed.

Self Drive Nepal was the only operator found that published a self-drive rate card (Self Drive Nepal, 2025). Its prices vary by vehicle category and by whether the car stays inside the Kathmandu valley, ranging from about Rs 5,000 per day for hatchbacks and sedans to Rs 10,000 for automatic, luxury and electric sport utility vehicles, with fuel, tolls and parking excluded and a large security deposit required.

Rent in Nepal offers electric cars and vans with a driver at fixed daily rates, with fixed prices for popular routes and a 25% surcharge for night travel (Rent In Nepal, n.d.). Nepal Vehicle Hiring publishes all-inclusive price ranges with a driver and states that prices rise by 10–15% in the October–November peak season (Nepal Vehicle Hiring, 2026). Kathmandu Car Services lists fares by destination, with surcharges for hill and mountain roads and discounts for long-term hire (Kathmandu Car Services, 2026). Most of these operators take bookings through enquiry forms, telephone and messaging applications such as WhatsApp and Viber, and few publish fixed prices for tours and packages.

Among global systems, the car rental companies Hertz and National Car Rental used demand-based pricing supported by forecasting systems (Carroll & Grimes, 1995; Geraghty & Johnson, 1997), and Airbnb suggests a price for each night to its hosts from a learned model (Ye et al., 2018). These systems price dynamically but do so with large volumes of data that a new Nepali platform does not have. Finally, the open-source "Car Rental Portal" template on which Rento was built provides vehicle listing, search, booking requests at a fixed price per day, testimonials and an administration panel [INSERT template citation]. It has no dynamic pricing, recommendation, invoice or package features.

Table 2.1 summarises the main related works reviewed in this section, and Table 2.2 compares the features of the surveyed platforms with those of Rento.

Table: Table 2.1 Summary of related works
| Work | Area | Approach | Main finding | Relevance to Rento |
|---|---|---|---|---|
| Kimes (1989) | Revenue management | Conceptual analysis | Yield management suits fixed, perishable capacity with variable, forecastable demand | Car rental meets these conditions |
| Gallego and van Ryzin (1994) | Dynamic pricing | Stochastic optimisation model | Optimal price depends on remaining stock and time; few price levels are nearly optimal | Justifies bounded, stepwise price changes |
| Geraghty and Johnson (1997) | Car rental | Industry case study | Forecasting and pricing system raised revenue at National Car Rental | Evidence that demand pricing works in car rental |
| Oliveira et al. (2017) | Car rental | Literature review and framework | Pricing and fleet decisions should be integrated | Positions Rento's pricing within car rental research |
| Ye et al. (2018) | Accommodation marketplace | Booking-probability and regression model | Learned daily price suggestions for hosts at scale | Suggest-and-override design; needs large data |
| Xia et al. (2004); Haws and Bearden (2006) | Price fairness | Framework; experiments | Unexplained or inconsistent prices are seen as unfair | Caps, advance calendar and reasons for each price |
| Breiman (2001); Friedman (2001) | Machine learning | Random forest; gradient boosting | Tree ensembles reduce error of single models | Models compared in the ML experiments |
| Grinsztajn et al. (2022) | Machine learning | Benchmark on tabular data | Tree models outperform deep networks on typical tabular data | Supports tree ensembles over the MLP |
| Makridakis et al. (2018) | Forecasting | Empirical comparison | ML methods often fail to beat statistical benchmarks | Every model is compared with a baseline |
| Schein et al. (2002) | Recommender systems | Cold-start methods | Content information helps when ratings are missing | Rento has almost no interaction data |
| Lops et al. (2011) | Recommender systems | Survey of content-based methods | Content-based systems are independent of other users and explainable | Basis of the recommendation engine |
| Tintarev and Masthoff (2015) | Recommender systems | Survey of explanations | Explanations build transparency and trust | Match percentage and reasons |

Table: Table 2.2 Feature comparison of existing platforms and Rento {widths=0.98,0.655,0.655,0.655,0.655,0.86,0.655,0.655}
| Feature | Sajilo Rental | Self Drive Nepal | Rent in Nepal | Nepal Vehicle Hiring | Kathmandu Car Services | Base template | Rento |
|---|---|---|---|---|---|---|---|
| Online booking | Yes, instant | – | Enquiry form, phone, chat apps | Form, chat apps | Yes | Booking request | Booking request |
| Published daily prices | Yes | Yes | Partly | Price ranges | Price ranges | Yes | Yes, for each day |
| Self-drive | No | Yes | No | No | No | Not specified | Yes |
| With driver | Yes | Yes | Yes | Yes | Yes | No | Yes |
| Prices change with date or demand | Not observed | Not observed | Fixed night surcharge | Informal peak surcharge | Road surcharges, long-hire discount | No | Yes, seven rules, capped |
| Reason shown for each price | No | No | No | No | No | No | Yes |
| Suggests cars for a trip | No | No | No | No | No | No | Yes |
| Tour or transfer packages | Hourly and day hire | – | Yes | Yes | Yes | No | Yes, by enquiry |
| Online payment | Yes | – | – | No | – | No | No |
| Stored quote and invoice | – | – | – | – | – | No | Yes |

> Sources: market survey of 27 September 2026 (see Section 2.2.4 and Appendix F). A dash (–) means that the feature was not described on the pages reviewed; "chat apps" refers to WhatsApp or Viber; "Not observed" means that the published prices showed no such variation.

## 2.3 Theoretical Framework

The theoretical framework of the study, shown in Figure 2.1, links the theories reviewed above with the inputs, components and outcomes of Rento. Revenue management and price fairness inform the rule-based smart pricer and its 60-day forecast; demand forecasting informs the offline machine-learning models; and content- and knowledge-based recommendation informs the recommendation engine. The following subsections present the formal models used in each component.

![Figure 2.1 Conceptual framework of the study](figures/diagrams/conceptual_framework.png){width=5.7}

### 2.3.1 Rule-Based Dynamic Pricing

A rule-based pricing model sets the price as a base price adjusted by a set of explicit factors, each triggered by a known condition. Unlike a learned model, it needs no historical sales data, and every adjustment can be stated to the customer, which addresses the fairness concerns discussed in Section 2.2.1. Its weakness is that the size of each adjustment is chosen by judgement rather than estimated from data.

In Rento, the price of a car on day *d* is calculated from its base daily price *b* and the percentages *f* of the factors that apply to that day, as shown in Equation 2.1.

$$ p_d = R_{s}\left( b \left( 1 + \min\left( \max\left( \sum_{i \in A_d} f_i,\ -\delta^{-} \right),\ \delta^{+} \right) \right) \right) $$ (2.1)

where *A* is the set of factors active on day *d*, δ⁻ and δ⁺ are the maximum discount and surcharge, and *R* rounds the result to the nearest multiple of *s* rupees. The factors are additive, so a Friday that is also a festival travel day receives the sum of both surcharges. Only the strongest festival factor applies on any day, and the last-minute and early-booking factors are mutually exclusive. With the settings used in this project, the caps are 15% and 25% and prices are rounded to Rs 50. The cap reflects the finding of Gallego and van Ryzin (1994) that a limited range of prices can capture most of the benefit of dynamic pricing, and it keeps the variation predictable for customers. The factors and their values are described in Chapter 5.

### 2.3.2 Ensemble Learning Methods

A regression model estimates a target value *y*, such as hourly demand or a daily rental price, from a vector of features *x*. The ensemble methods used in this project combine many decision trees.

Gradient boosting (Friedman, 2001) builds the model in *M* stages. It starts from a constant and, at each stage *m*, fits a regression tree to the negative gradient of the loss function *L*, called the pseudo-residual, as shown in Equation 2.2.

$$ r_{im} = -\left[ \dfrac{\partial L(y_i, F(x_i))}{\partial F(x_i)} \right]_{F = F_{m-1}} $$ (2.2)

The new tree is then added to the model with a learning rate ν between 0 and 1, which shrinks the contribution of each tree and reduces overfitting, as shown in Equation 2.3.

$$ F_m(x) = F_{m-1}(x) + \nu\, h_m(x) $$ (2.3)

For the squared-error loss, the pseudo-residual is simply the difference between the actual value and the current prediction, so each tree learns to correct the errors of the trees before it.

A random forest (Breiman, 2001) instead trains *B* trees independently, each on a bootstrap sample of the training data with a random subset of features considered at each split, and averages their predictions, as shown in Equation 2.4.

$$ \hat{y}(x) = \dfrac{1}{B} \sum_{b=1}^{B} T_b(x) $$ (2.4)

Averaging many decorrelated trees reduces the variance of the prediction. Boosting mainly reduces bias, whereas a random forest mainly reduces variance; which works better depends on the size and noise of the dataset, so both are evaluated in this project.

### 2.3.3 Regression Evaluation Metrics

For a test set of *n* observations with actual values *y* and predictions *ŷ*, the mean absolute error (MAE) measures the average size of the errors in the units of the target, as shown in Equation 2.5.

$$ \mathrm{MAE} = \dfrac{1}{n} \sum_{i=1}^{n} \left| y_i - \hat{y}_i \right| $$ (2.5)

The root mean squared error (RMSE) squares the errors before averaging, so it gives more weight to large errors, as shown in Equation 2.6.

$$ \mathrm{RMSE} = \sqrt{ \dfrac{1}{n} \sum_{i=1}^{n} \left( y_i - \hat{y}_i \right)^2 } $$ (2.6)

The coefficient of determination (R²) compares the squared error of the model with that of a model that always predicts the mean of the actual values, as shown in Equation 2.7.

$$ R^2 = 1 - \dfrac{ \sum_{i=1}^{n} \left( y_i - \hat{y}_i \right)^2 }{ \sum_{i=1}^{n} \left( y_i - \bar{y} \right)^2 } $$ (2.7)

An R² of 1 indicates perfect prediction and an R² of 0 indicates no improvement on the mean; a negative value means that the model is worse than predicting the mean. Lower MAE and RMSE and higher R² indicate a better model. Because error on a single split may depend on how the data happened to be divided, the experiments also use cross-validation, with folds that respect time order for demand data (Bergmeir & Benítez, 2012) and folds grouped by car model for price data, so that the models are tested on cars they have not seen.

### 2.3.4 Content-Based Recommendation Scoring

A content- and knowledge-based recommender can be expressed as a weighted scoring model. For a trip query *q*, a set of criteria *K*(*q*) applies, such as the number of seats, the budget, the type of road and the preferred fuel. Each criterion *k* has a weight *w* and gives a car *c* a partial score *s* between 0 and 1, which measures how well the car meets that criterion. The match score of the car is the share of the applicable weights that it earns, as shown in Equation 2.8.

$$ S(c, q) = \dfrac{ \sum_{k \in K(q)} w_k\, s_k(c, q) }{ \sum_{k \in K(q)} w_k } $$ (2.8)

Because only criteria that apply to the trip are counted, a car scores 100% only when it fully meets every requirement the customer has expressed, and the score of a vague query is not diluted by criteria that the customer did not mention. The partial scores also provide the reasons shown to the customer, in line with the principle that an explanation should reflect the real reasoning of the system (Tintarev & Masthoff, 2015). The criteria and weights used by Rento are presented in Chapter 5.

The quality of the ranked list is measured by precision at *k*, the proportion of the top *k* recommended cars that belong to the set *G* of cars judged relevant for the trip (Herlocker et al., 2004). When fewer than *k* cars are relevant, even a perfect ranking cannot reach a precision of 1, so a normalised precision is also used, which divides by the smaller of *k* and the number of relevant cars, as shown in Equation 2.9.

$$ P@k = \dfrac{ \left| R_k \cap G \right| }{ k }, \qquad nP@k = \dfrac{ \left| R_k \cap G \right| }{ \min(k, |G|) } $$ (2.9)

where *R* is the set of the top *k* cars returned. A related measure, the hit rate at one, records whether the first recommended car is relevant.

## 2.4 Research Gap

The review shows that demand-based pricing is well established in the international car rental, airline and hotel industries (Geraghty & Johnson, 1997; Talluri & van Ryzin, 2004) and that online platforms such as Airbnb use learned models to suggest prices (Ye et al., 2018). These approaches, however, rely on large amounts of booking data and dedicated systems. The research on dynamic pricing and learning (den Boer, 2015) and on cold-start recommendation (Schein et al., 2002) shows that a new platform with little data needs a different approach, but it gives little practical guidance on how a small operator in a developing market can introduce demand-based pricing from the first day.

In the Nepali rental market, the survey found no platform that changes prices systematically by date or demand. Prices are either flat by vehicle category or subject to informal surcharges, such as a peak-season increase stated only as a range, and none of the platforms reviewed explains the price of a particular day or recommends vehicles for a described trip. Festival demand around Dashain and Tihar, which drives much of the variation in rental demand in Nepal, is not reflected in any published price. Research on price fairness (Xia et al., 2004) suggests that variable prices would need to be transparent and bounded to be accepted in such a market.

The gap addressed by this project is therefore the absence of a rental platform for Nepal that combines transparent, bounded, day-by-day dynamic pricing based on the Nepali festival calendar and booking conditions with explainable, trip-based vehicle recommendation, and that is designed to operate without historical data while preparing for a learned pricer once data become available. Rento addresses this gap with a rule-based pricer that works from the first day, a content-based recommendation engine that needs no rating history, and offline machine-learning experiments that establish which models should replace the rules when enough bookings exist.

## 2.5 Summary

This chapter reviewed the literature on revenue management and dynamic pricing, price fairness, demand forecasting and recommender systems, together with the rental platforms operating in Nepal. Revenue management research establishes that car rental is well suited to demand-based pricing, and fairness research indicates that such prices should be bounded and explained. Forecasting research supports tree-based ensemble models for tabular demand data while warning that they must be compared with simple baselines and evaluated with time-aware methods. Recommender systems research shows that content- and knowledge-based methods are appropriate when interaction data are scarce, and that explanations increase transparency and trust. The survey of Nepali platforms found flat or informally adjusted prices, booking by enquiry, and no price explanation or trip-based recommendation.

The theoretical framework defined the capped, rule-based pricing model, the gradient boosting and random forest methods, the MAE, RMSE and R² metrics, and the weighted scoring model and precision measures used for recommendation. The research gap identified is the lack of a transparent, data-independent dynamic pricing and recommendation platform for the Nepali rental market. Chapter 3 describes the methodology used to develop Rento in response to this gap.
