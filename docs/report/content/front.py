"""Preliminary pages of the FYP report (used by docs/report/build_report.py)."""

TITLE = "RENTO – AN AI-POWERED CAR RENTAL PLATFORM WITH SMART DYNAMIC PRICING AND VEHICLE RECOMMENDATION"
TITLE_CASE = "Rento – An AI-Powered Car Rental Platform with Smart Dynamic Pricing and Vehicle Recommendation"

INFO = {
    "title": TITLE,
    "student": "Anisha Ghatan",
    "lcid": "LC00017002320",
    "submission": "[INSERT MONTH, YEAR OF SUBMISSION]",
}

CERTIFICATION = (
    f"This is to certify that the Final Year Project report entitled “{TITLE_CASE}” "
    "submitted by Anisha Ghatan (LCID: LC00017002320) has been examined and is recommended for acceptance in partial "
    "fulfilment of the requirements for the degree of Bachelor of Information Technology (Honours), Faculty of "
    "Computer Science in Multimedia, Lincoln University College, Malaysia."
)

DECLARATION = [
    f"I, Anisha Ghatan (LCID: LC00017002320), hereby declare that this Final Year Project report entitled "
    f"“{TITLE_CASE}” is the result of my own project work, carried out under "
    "the supervision of Mr. Rohit Pandey, except where otherwise stated and acknowledged. All sources of information "
    "used in this report have been cited and are listed in the References.",
    "The system was developed from an open-source PHP car-rental template (“Car Rental Portal”). The template is "
    "identified and cited in Chapter 3, which also sets out which parts of the system were rebuilt or newly added "
    "during this project.",
    "I declare that an artificial-intelligence assistant, Claude (Anthropic), was used during this project as a coding "
    "and documentation assistant. It helped to write and refactor source code, automated tests and diagrams, and to "
    "draft and edit parts of this report. I directed the work, set the requirements and design decisions, reviewed, "
    "tested and verified all outputs, and I take full responsibility for the system and for the content of this report. "
    "The commits produced with this assistance are marked as co-authored in the project's Git history. This use of AI "
    "tools is in line with the policy of Lincoln University College: [INSERT statement on the college's AI policy].",
    "This report has not been submitted, in whole or in part, for any other degree or qualification at this or any "
    "other institution.",
]

ACKNOWLEDGEMENTS = [
    "I would like to express my sincere gratitude to my internal supervisor, Mr. Rohit Pandey, for his guidance, "
    "patience and constructive feedback throughout this project. His advice on scope, methodology and evaluation "
    "shaped both the system and this report.",
    "I am grateful to Dr. Suman Thapaliya, Head of Department, and to the Faculty of Computer Science in Multimedia "
    "at Lincoln University College for the knowledge, facilities and support provided during the Bachelor of "
    "Information Technology (Honours) programme.",
    "I also acknowledge the open-source communities whose work made this project possible, including the authors of "
    "the original car-rental template, PHP, MySQL, Bootstrap, scikit-learn and PyTorch, and the use of the AI assistant "
    "Claude (Anthropic) as a coding and documentation aid, as described in the Declaration of Authenticity.",
    "Finally, I thank my family and friends for their encouragement, understanding and support throughout my studies.",
]

ABBREVIATIONS = [
    ("AI", "Artificial Intelligence"), ("API", "Application Programming Interface"), ("APA", "American Psychological Association (citation style)"),
    ("BIT", "Bachelor of Information Technology"), ("BS", "Bikram Sambat (the official calendar of Nepal)"), ("CRUD", "Create, Read, Update, Delete"),
    ("CSRF", "Cross-Site Request Forgery"), ("CSS", "Cascading Style Sheets"), ("CV", "Cross-Validation"), ("DB", "Database"),
    ("DFD", "Data Flow Diagram"), ("ERD", "Entity Relationship Diagram"), ("EV", "Electric Vehicle"), ("FK", "Foreign Key"),
    ("FR", "Functional Requirement"), ("FYP", "Final Year Project"), ("GB", "Gradient Boosting"), ("HTML", "HyperText Markup Language"),
    ("HTTP(S)", "HyperText Transfer Protocol (Secure)"), ("JS", "JavaScript"), ("JSON", "JavaScript Object Notation"),
    ("KPI", "Key Performance Indicator"), ("LR", "Linear Regression"), ("MAE", "Mean Absolute Error"),
    ("MAMP", "macOS, Apache, MySQL, PHP (local server package)"), ("ML", "Machine Learning"), ("MLP", "Multilayer Perceptron"),
    ("NFR", "Non-Functional Requirement"), ("NPR", "Nepalese Rupee (shown as “Rs”)"), ("OWASP", "Open Worldwide Application Security Project"),
    ("PDO", "PHP Data Objects"), ("PK", "Primary Key"), ("R²", "Coefficient of determination"), ("RF", "Random Forest"),
    ("RMSE", "Root Mean Squared Error"), ("SEO", "Search Engine Optimisation"), ("SMTP", "Simple Mail Transfer Protocol"),
    ("SQL", "Structured Query Language"), ("SRS", "Software Requirements Specification"), ("UI / UX", "User Interface / User Experience"),
    ("UML", "Unified Modeling Language"), ("URL", "Uniform Resource Locator"), ("WCAG", "Web Content Accessibility Guidelines"),
    ("XSS", "Cross-Site Scripting"),
]

EXECUTIVE_SUMMARY = [
    "Car rental businesses in Nepal usually quote one fixed daily price, even though demand rises sharply around "
    "Dashain, Tihar, weekends and last-minute trips, and most bookings are still arranged by phone or social media. "
    "Customers also find it hard to know which vehicle suits a trip to, for example, the mountain roads of Mustang. "
    "This project developed Rento, a web-based car rental platform for Nepal with explainable AI-based pricing and "
    "trip-based vehicle recommendation.",
    "Rento was built with PHP 8.3, MySQL 8, Bootstrap 5.3 and Python 3.12, using a phased, iterative method on top of "
    "an open-source car-rental template that was almost entirely rebuilt. Its intelligent components are a rule-based "
    "smart pricer that combines seven demand factors with a −15% / +25% cap and a human-readable reason for every price; "
    "a 60-day price forecast recomputed nightly and after every relevant change; a content-based recommendation engine "
    "that scores cars against a natural-language trip description; and offline machine-learning experiments that "
    "prepare a learned pricer for the time when enough bookings exist.",
    "Evaluation showed that all 480 forecast prices respected the caps and rounding rules, and that the recommendation "
    "engine reached a normalised precision@3 of 0.94 and a hit rate of 0.91 on twelve test trips. Among the models "
    "compared, a Random Forest explained 49% of daily demand variance on an external rental dataset and 98% of price "
    "variance on 1,640 scraped Nepali listings (a day price derived from the listed four-hour price). Fifty-four PHPUnit and forty-six pytest tests passed, and page weight "
    "fell by up to 80% compared with the original template. Because Rento has only four real bookings, the live site "
    "uses the rule-based pricer; the learned models are ready to be switched on once about 200 bookings exist.",
]

BLOCKS = [
    ("heading", "Recommendation"),
    ("para", "[To be received from Resource Center]", "Centered"),
    ("break",),
    ("heading", "Certification"),
    ("para", CERTIFICATION),
    ("signature", "Mr. Rohit Pandey", "Internal Supervisor"),
    ("signature", "Prof. Dr. Govinda Prasad Acharya", "Chairperson, RDSC"),
    ("signature", "Dr. Suman Thapaliya", "Director / Department Head"),
    ("signature", "Ms. Sarala Karki", "Research Learning & Development"),
    ("break",),
    ("heading", "Declaration of Authenticity"),
    *[("para", t) for t in DECLARATION],
    ("signature", "Anisha Ghatan", "LCID: LC00017002320"),
    ("break",),
    ("heading", "Acknowledgements"),
    *[("para", t) for t in ACKNOWLEDGEMENTS],
    ("signoff", "Anisha Ghatan"),
    ("break",),
    ("heading", "Table of Contents"),
    ("toc", 'TOC \\o "1-3" \\h \\z \\u'),
    ("break",),
    ("heading", "List of Tables"),
    ("toc", 'TOC \\h \\z \\t "Table Caption,1"'),
    ("break",),
    ("heading", "List of Figures"),
    ("toc", 'TOC \\h \\z \\t "Figure Caption,1"'),
    ("break",),
    ("heading", "Abbreviations and Acronyms"),
    ("abbrev", ABBREVIATIONS),
    ("break",),
    ("heading", "Executive Summary"),
    *[("para", t, "Single") for t in EXECUTIVE_SUMMARY],
]
