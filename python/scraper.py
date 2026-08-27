# ============================================================
# SAJILO RENTAL VEHICLE SCRAPER
# ============================================================
#
# Collects:
#   vehicle_id
#   vehicle_name
#   vehicle_type
#   manufacturer
#   manufacture_year
#   vehicle_age
#   fuel_type
#   price_4hr
#   price_8hr
#   price_1day
#   max_km_4hr
#   max_km_8hr
#   max_km_1day
#   extra_km_price
#   fuel_cost_per_km
#   vehicle_url
#
# Output:
#   sajilo_vehicle_dataset.csv
#
# ============================================================

import requests
from bs4 import BeautifulSoup

import pandas as pd
import re
import time

from tqdm import tqdm
from urllib.parse import urljoin


# ============================================================
# 1. CONFIGURATION
# ============================================================

BASE_URL = "https://sajilorental.com"

OUTPUT_FILE = "sajilo_vehicle_dataset.csv"

# Current year
CURRENT_YEAR = 2026

# Delay between requests
REQUEST_DELAY = 1.0

# Vehicle IDs to test/crawl.
#
# Sajilo vehicle URLs currently look like:
#
# https://sajilorental.com/vehicle/1330
#
# We start with a range and later remove invalid pages.
#
START_ID = 1
END_ID = 1800


# ============================================================
# 2. SESSION
# ============================================================

session = requests.Session()

session.headers.update({
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/147.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "en-US,en;q=0.9",
})


# ============================================================
# 3. GET PAGE
# ============================================================

def get_page(url):

    try:

        response = session.get(
            url,
            timeout=20
        )

        if response.status_code != 200:
            return None

        return response.text

    except requests.RequestException as e:

        print(
            f"Request failed: {url}"
        )

        return None


# ============================================================
# 4. CLEAN TEXT
# ============================================================

def clean_text(text):

    if text is None:
        return None

    text = text.replace("\xa0", " ")

    text = re.sub(
        r"\s+",
        " ",
        text
    )

    return text.strip()


# ============================================================
# 5. EXTRACT NUMBER
# ============================================================

def extract_number(text):

    if not text:
        return None

    match = re.search(
        r"[\d,]+(?:\.\d+)?",
        text
    )

    if not match:
        return None

    value = match.group(
        0
    ).replace(
        ",",
        ""
    )

    try:
        return float(value)

    except ValueError:
        return None


# ============================================================
# 6. EXTRACT YEAR
# ============================================================

def extract_year(text):

    if not text:
        return None

    # Look for years from 1980–2026
    years = re.findall(
        r"\b(19[8-9]\d|20[0-2]\d)\b",
        text
    )

    if not years:
        return None

    # Prefer a plausible manufacture year
    for year in years:

        year = int(year)

        if 1980 <= year <= CURRENT_YEAR:
            return year

    return None


# ============================================================
# 7. EXTRACT PRICING INFORMATION
# ============================================================

def extract_pricing(text):

    data = {

        "price_4hr": None,
        "price_8hr": None,
        "price_1day": None,

        "max_km_4hr": None,
        "max_km_8hr": None,
        "max_km_1day": None,

        "extra_km_price": None,

        "fuel_cost_per_km": None
    }

    # --------------------------------------------------------
    # 4 HR
    # --------------------------------------------------------

    pattern_4hr = re.search(
        r"4\s*hr.*?"
        r"Rs\.?\s*([\d,]+)",
        text,
        re.IGNORECASE
    )

    if pattern_4hr:

        data["price_4hr"] = extract_number(
            pattern_4hr.group(1)
        )

    # Extract km associated with 4hr
    km_4hr = re.search(
        r"4\s*hr\s*"
        r"\(.*?(\d[\d,]*)\s*km.*?\)",
        text,
        re.IGNORECASE
    )

    if km_4hr:

        data["max_km_4hr"] = extract_number(
            km_4hr.group(1)
        )


    # --------------------------------------------------------
    # 8 HR
    # --------------------------------------------------------

    pattern_8hr = re.search(
        r"8\s*hr.*?"
        r"Rs\.?\s*([\d,]+)",
        text,
        re.IGNORECASE
    )

    if pattern_8hr:

        data["price_8hr"] = extract_number(
            pattern_8hr.group(1)
        )

    km_8hr = re.search(
        r"8\s*hr\s*"
        r"\(.*?(\d[\d,]*)\s*km.*?\)",
        text,
        re.IGNORECASE
    )

    if km_8hr:

        data["max_km_8hr"] = extract_number(
            km_8hr.group(1)
        )


    # --------------------------------------------------------
    # 1 DAY
    # --------------------------------------------------------

    pattern_1day = re.search(
        r"1\s*day.*?"
        r"Rs\.?\s*([\d,]+)",
        text,
        re.IGNORECASE
    )

    if pattern_1day:

        data["price_1day"] = extract_number(
            pattern_1day.group(1)
        )

    km_1day = re.search(
        r"1\s*day\s*"
        r"\(.*?(\d[\d,]*)\s*km.*?\)",
        text,
        re.IGNORECASE
    )

    if km_1day:

        data["max_km_1day"] = extract_number(
            km_1day.group(1)
        )


    # --------------------------------------------------------
    # EXTRA KM
    # --------------------------------------------------------

    extra_km = re.search(
        r"Long route\s*/\s*extra km.*?"
        r"Rs\.?\s*([\d,]+(?:\.\d+)?)",
        text,
        re.IGNORECASE
    )

    if extra_km:

        data["extra_km_price"] = extract_number(
            extra_km.group(1)
        )


    # --------------------------------------------------------
    # FUEL
    # --------------------------------------------------------

    fuel = re.search(
        r"Fuel\s*\(est\.\s*per\s*km\).*?"
        r"Rs\.?\s*([\d,]+(?:\.\d+)?)",
        text,
        re.IGNORECASE
    )

    if fuel:

        data["fuel_cost_per_km"] = extract_number(
            fuel.group(1)
        )


    return data


# ============================================================
# 8. EXTRACT VEHICLE DETAILS
# ============================================================

def extract_vehicle(
    html,
    vehicle_id
):

    soup = BeautifulSoup(
        html,
        "html.parser"
    )

    # --------------------------------------------------------
    # Complete page text
    # --------------------------------------------------------

    text = clean_text(
        soup.get_text(
            " ",
            strip=True
        )
    )


    # --------------------------------------------------------
    # VEHICLE NAME
    # --------------------------------------------------------

    vehicle_name = None

    # Try H1
    h1 = soup.find("h1")

    if h1:

        vehicle_name = clean_text(
            h1.get_text(
                " ",
                strip=True
            )
        )

    # Fallback: title
    if not vehicle_name:

        title = soup.find(
            "title"
        )

        if title:

            vehicle_name = clean_text(
                title.get_text(
                    " ",
                    strip=True
                )
            )

            # Remove common suffix
            vehicle_name = re.sub(
                r"\s*[—|-]\s*Sajilo Rental.*$",
                "",
                vehicle_name,
                flags=re.IGNORECASE
            )


    # --------------------------------------------------------
    # VEHICLE TYPE
    # --------------------------------------------------------

    vehicle_type = None

    type_patterns = [

        r"Vehicle Type\s*:?\s*([A-Za-z0-9 ()/-]+)",

        r"vehicle type\s*([A-Za-z0-9 ()/-]+)"
    ]

    for pattern in type_patterns:

        match = re.search(
            pattern,
            text,
            re.IGNORECASE
        )

        if match:

            vehicle_type = clean_text(
                match.group(1)
            )

            break


    # --------------------------------------------------------
    # MANUFACTURE YEAR
    # --------------------------------------------------------

    manufacture_year = None

    year_patterns = [

        r"Manufacture\s*Year\s*:?\s*(20\d{2})",

        r"Manufacturing\s*Year\s*:?\s*(20\d{2})",

        r"Model\s*Year\s*:?\s*(20\d{2})",

        r"Year\s*:?\s*(20\d{2})"
    ]

    for pattern in year_patterns:

        match = re.search(
            pattern,
            text,
            re.IGNORECASE
        )

        if match:

            manufacture_year = int(
                match.group(1)
            )

            break


    # --------------------------------------------------------
    # FALLBACK YEAR
    # --------------------------------------------------------

    if manufacture_year is None:

        # Sometimes the year appears in the
        # vehicle title.
        manufacture_year = extract_year(
            vehicle_name
        )


    # --------------------------------------------------------
    # VEHICLE AGE
    # --------------------------------------------------------

    vehicle_age = None

    if manufacture_year:

        vehicle_age = (
            CURRENT_YEAR -
            manufacture_year
        )


    # --------------------------------------------------------
    # FUEL TYPE
    # --------------------------------------------------------

    fuel_type = None

    fuel_patterns = [

        r"Fuel\s*Type\s*:?\s*([A-Za-z]+)",

        r"Fuel\s*:?\s*([A-Za-z]+)"
    ]

    for pattern in fuel_patterns:

        match = re.search(
            pattern,
            text,
            re.IGNORECASE
        )

        if match:

            fuel_type = clean_text(
                match.group(1)
            )

            break


    # --------------------------------------------------------
    # MANUFACTURER
    # --------------------------------------------------------

    manufacturer = None

    manufacturers = [

        "Honda",
        "Tata",
        "Ford",
        "Hyundai",
        "Mahindra",
        "Skoda",
        "Renault",
        "Nissan",
        "Toyota",
        "Suzuki",
        "KIA",
        "Datsun",
        "ISUZU",
        "Jeep",
        "MITSUBISHI",
        "Morris Garages",
        "Chevrolet",
        "Fiat",
        "BYD",
        "NETA",
        "GWM",
        "Seres",
        "Citroen",
        "BMW",
        "Omoda",
        "Donfeng",
        "Leapmotor",
        "Volkswagen",
        "GAC",
        "Chery",
        "Foton"
    ]

    if vehicle_name:

        for manufacturer_name in manufacturers:

            if manufacturer_name.lower() in vehicle_name.lower():

                manufacturer = manufacturer_name

                break


    # --------------------------------------------------------
    # PRICING
    # --------------------------------------------------------

    pricing = extract_pricing(
        text
    )


    # --------------------------------------------------------
    # URL
    # --------------------------------------------------------

    vehicle_url = (
        f"{BASE_URL}/vehicle/{vehicle_id}"
    )


    # --------------------------------------------------------
    # RESULT
    # --------------------------------------------------------

    result = {

        "vehicle_id": vehicle_id,

        "vehicle_name": vehicle_name,

        "vehicle_type": vehicle_type,

        "manufacturer": manufacturer,

        "manufacture_year": manufacture_year,

        "vehicle_age": vehicle_age,

        "fuel_type": fuel_type,

        "price_4hr": pricing["price_4hr"],

        "price_8hr": pricing["price_8hr"],

        "price_1day": pricing["price_1day"],

        "max_km_4hr": pricing["max_km_4hr"],

        "max_km_8hr": pricing["max_km_8hr"],

        "max_km_1day": pricing["max_km_1day"],

        "extra_km_price": pricing["extra_km_price"],

        "fuel_cost_per_km": pricing["fuel_cost_per_km"],

        "vehicle_url": vehicle_url
    }


    return result


# ============================================================
# 9. CHECK WHETHER PAGE IS A VEHICLE PAGE
# ============================================================

def is_vehicle_page(html):

    if not html:
        return False

    text = clean_text(
        BeautifulSoup(
            html,
            "html.parser"
        ).get_text(
            " ",
            strip=True
        )
    )

    # Vehicle pages contain pricing information.
    if "Pricing information" in text:

        return True

    if "4 hr" in text and "1 day" in text:

        return True

    return False


# ============================================================
# 10. SCRAPE A SINGLE VEHICLE
# ============================================================

def scrape_vehicle(
    vehicle_id
):

    url = (
        f"{BASE_URL}/vehicle/{vehicle_id}"
    )

    html = get_page(
        url
    )

    if html is None:

        return None


    if not is_vehicle_page(html):

        return None


    vehicle = extract_vehicle(
        html,
        vehicle_id
    )

    return vehicle


# ============================================================
# 11. MAIN SCRAPER
# ============================================================

def main():

    print()
    print("=" * 60)
    print("SAJILO RENTAL SCRAPER")
    print("=" * 60)
    print()

    print(
        f"Checking vehicle IDs "
        f"{START_ID} → {END_ID}"
    )

    print(
        "This may take some time."
    )

    print()


    vehicles = []


    # --------------------------------------------------------
    # LOOP THROUGH VEHICLE IDS
    # --------------------------------------------------------

    for vehicle_id in tqdm(
        range(
            START_ID,
            END_ID + 1
        )
    ):

        vehicle = scrape_vehicle(
            vehicle_id
        )

        if vehicle:

            vehicles.append(
                vehicle
            )

            print(
                f"\nFound: "
                f"{vehicle['vehicle_id']} "
                f"- "
                f"{vehicle['vehicle_name']}"
            )

        time.sleep(
            REQUEST_DELAY
        )


    # --------------------------------------------------------
    # CHECK RESULTS
    # --------------------------------------------------------

    if not vehicles:

        print()
        print(
            "No vehicle pages were found."
        )

        return


    # --------------------------------------------------------
    # DATAFRAME
    # --------------------------------------------------------

    df = pd.DataFrame(
        vehicles
    )


    # --------------------------------------------------------
    # REMOVE DUPLICATES
    # --------------------------------------------------------

    df = df.drop_duplicates(
        subset=[
            "vehicle_id"
        ]
    )


    # --------------------------------------------------------
    # CALCULATE HOURLY RATES
    # --------------------------------------------------------

    df["price_per_hr_4hr"] = (
        df["price_4hr"] / 4
    )

    df["price_per_hr_8hr"] = (
        df["price_8hr"] / 8
    )

    df["price_per_hr_day"] = (
        df["price_1day"] / 24
    )


    # --------------------------------------------------------
    # KATHMANDU FILTER
    # --------------------------------------------------------

    # NOTE:
    #
    # We only filter here if the scraped page contains
    # location information in the future.
    #
    # Do NOT blindly filter based on vehicle name.
    #
    # For now the complete dataset is retained.


    # --------------------------------------------------------
    # SORT
    # --------------------------------------------------------

    df = df.sort_values(
        by="vehicle_id"
    )


    # --------------------------------------------------------
    # SAVE
    # --------------------------------------------------------

    df.to_csv(
        OUTPUT_FILE,
        index=False,
        encoding="utf-8-sig"
    )


    # --------------------------------------------------------
    # SUMMARY
    # --------------------------------------------------------

    print()
    print("=" * 60)
    print("SCRAPING COMPLETE")
    print("=" * 60)

    print(
        f"Vehicles found: {len(df)}"
    )

    print(
        f"Saved to: {OUTPUT_FILE}"
    )

    print()

    print(
        df.head(10)
    )

    print()

    print(
        "Columns:"
    )

    for column in df.columns:

        print(
            f"  - {column}"
        )


# ============================================================
# 12. RUN
# ============================================================

if __name__ == "__main__":

    main()