"""
Builds the ERD sources (PlantUML, puml/erd_*.puml) and schema.json from the live schema export.
  schema.tsv   information_schema.COLUMNS (table, column, type, nullable, key, default, extra)
  indexes.tsv  secondary indexes
Refresh the exports (MySQL admin account), then run:  python3 docs/report/diagrams/make_erd.py
The database has NO foreign-key constraints: the relationships below are LOGICAL (enforced in the PHP
code), and the diagrams label them that way.
"""
import csv
import json
from collections import OrderedDict
from pathlib import Path

HERE = Path(__file__).resolve().parent
cols = OrderedDict()
for t, c, typ, null, key, default, extra in csv.reader(open(HERE / "schema.tsv"), delimiter="\t"):
    cols.setdefault(t, []).append({"column": c, "type": typ, "nullable": null == "YES", "key": key, "default": default, "extra": extra})
indexes = [dict(zip(["table", "index", "columns", "non_unique"], r)) for r in csv.reader(open(HERE / "indexes.tsv"), delimiter="\t")]

# (child table, child column, parent table, parent column, cardinality in Mermaid, label) - all logical
RELS = [
    ("tblvehicles", "VehiclesBrand", "tblbrands", "id", "}o--||", "brand of"),
    ("tblbooking", "VehicleId", "tblvehicles", "id", "}o--||", "books"),
    ("tblbooking", "userEmail", "tblusers", "EmailId", "}o--||", "made by"),
    ("tbl_price_forecast", "VehicleId", "tblvehicles", "id", "}o--||", "prices"),
    ("tbl_price_forecast", "RunId", "tbl_forecast_runs", "id", "}o--||", "written by"),
    ("tbl_price_overrides", "VehicleId", "tblvehicles", "id", "}o--||", "overrides"),
    ("tbltestimonial", "UserEmail", "tblusers", "EmailId", "}o--||", "written by"),
    ("tblpasswordresets", "UserId", "tblusers", "id", "}o--o|", "resets (AccountType=user)"),
    ("tblpasswordresets", "UserId", "admin", "id", "}o--o|", "resets (AccountType=admin)"),
    ("tblpackages", "CategoryId", "tblpackagecategories", "id", "}o--o|", "in category"),
    ("tblpackageimages", "PackageId", "tblpackages", "id", "}o--||", "photo of"),
    ("tblpackageenquiries", "PackageId", "tblpackages", "id", "}o--o|", "about package"),
    ("tblpackageenquiries", "VehicleId", "tblvehicles", "id", "}o--o|", "about driver-only car"),
]
CORE = ["tblusers", "tblbooking", "tblvehicles", "tblbrands", "tbl_price_forecast", "tbl_forecast_runs", "tbl_price_overrides", "tblfestivals", "tblsettings"]
SUPPORT = ["admin", "tblpasswordresets", "tblratelimits", "tblpackages", "tblpackagecategories", "tblpackageimages", "tblpackageenquiries",
           "tbltestimonial", "tblusers", "tblpages", "tblsitecontent", "tblmailsettings", "tblcontactusinfo", "tblcontactusquery", "tblsubscribers"]
FEATURE_COLS = {"AirConditioner", "PowerDoorLocks", "AntiLockBrakingSystem", "BrakeAssist", "PowerSteering", "DriverAirbag",
                "PassengerAirbag", "PowerWindows", "CDPlayer", "CentralLocking", "CrashSensor", "LeatherSeats"}


def mtype(t):
    base = t.split("(")[0].replace(" unsigned", "")
    return {"int": "int", "tinyint": "tinyint", "varchar": "varchar", "char": "char", "longtext": "text", "text": "text", "mediumtext": "text",
            "decimal": "decimal", "date": "date", "datetime": "datetime", "timestamp": "timestamp", "json": "json"}.get(base, base)


def entity(t, compact):
    lines = [f"    {t} {{"]
    fk = {c for (ct, c, *_rest) in RELS if ct == t}
    for c in cols[t]:
        if compact and c["column"] in FEATURE_COLS:
            continue
        keys = ",".join(k for k in (["PK"] if c["key"] == "PRI" else []) + (["FK"] if c["column"] in fk else []) + (["UK"] if c["key"] == "UNI" else []))
        lines.append(f'        {mtype(c["type"])} {c["column"]}{" " + keys if keys else ""}')
    if compact and t == "tblvehicles":
        lines.append('        tinyint feature_flags "12 feature columns (AirConditioner ... LeatherSeats)"')
    lines.append("    }")
    return "\n".join(lines)


# Columns shown in the printed ERD besides PK/FK/UK (the full list is in schema.json and the data dictionary).
KEYCOLS = {
    "tblusers": ["FullName", "EmailId", "ContactNo"],
    "tblbooking": ["BookingNumber", "FromDate", "ToDate", "Status", "quoted_price", "pricing_details"],
    "tblvehicles": ["VehiclesTitle", "PricePerDay", "FuelType", "SeatingCapacity", "CarType", "IsActive", "DriverOnly", "Rugged"],
    "tblbrands": ["BrandName"],
    "tbl_price_forecast": ["ForecastDate", "BasePrice", "Multiplier", "Price", "Reasons", "IsAvailable"],
    "tbl_forecast_runs": ["TriggerReason", "Status", "RequestedAt", "FinishedAt"],
    "tbl_price_overrides": ["FromDate", "ToDate", "Price", "Note"],
    "tblfestivals": ["FestivalName", "StartDate", "EndDate", "TravelFrom", "TravelTo", "TravelPct", "DemandLevel"],
    "tblsettings": ["SettingKey", "SettingValue"],
    "tbltestimonial": ["Testimonial", "status"],
    "admin": ["UserName", "Email"],
    "tblpasswordresets": ["AccountType", "TokenHash", "ExpiresAt", "UsedAt"],
    "tblpackages": ["Title", "Slug", "StartingPrice", "FestivalPricing", "IsActive"],
    "tblpackagecategories": ["CategoryName", "Slug"],
    "tblpackageimages": ["ImagePath"],
    "tblpackageenquiries": ["FullName", "Phone", "TravelDate", "Status"],
}
PUML_CARD = {"}o--||": ("||", "o{"), "}o--o|": ("|o", "o{")}  # (parent end, child end)


def puml_entity(t):
    fk = {c for (ct, c, *_rest) in RELS if ct == t}
    rows, shown = [], 0
    for c in cols[t]:
        name, key = c["column"], c["key"]
        tag = "PK" if key == "PRI" else ("FK" if name in fk else ("UK" if key == "UNI" else ""))
        if tag or name in KEYCOLS.get(t, []):
            rows.append((tag, name, mtype(c["type"])))
            shown += 1
    pk = [r for r in rows if r[0] == "PK"]
    rest = [r for r in rows if r[0] != "PK"]
    out = [f'entity "{t}" as {t} {{']
    out += [f'  * {n} : {ty} <<PK>>' for _, n, ty in pk]
    out.append("  --")
    out += [f'  {n} : {ty}' + (f' <<{tag}>>' if tag else "") for tag, n, ty in rest]
    hidden = len(cols[t]) - shown
    if hidden:
        out.append(f'  .. {hidden} more column{"s" if hidden > 1 else ""} ..')
    out.append("}")
    return "\n".join(out)


def puml_erd(tables, lists=None):
    out = ["@startuml", "!include _style.iuml", "allowmixing", "hide circle", "skinparam linetype ortho", "skinparam defaultFontSize 13",
           "skinparam EntityFontSize 14", "skinparam EntityAttributeFontSize 13"]
    out += [puml_entity(t) for t in tables]
    for ct, cc, pt, pc, card, label in RELS:
        if ct in tables and pt in tables:
            pe, ce = PUML_CARD[card]
            out.append(f'{pt} {pe}..{ce} {ct} : {label}')
    for name, members in (lists or {}).items():
        body = "\\n".join(f"{m} ({len(cols[m])} columns)" for m in members)
        out.append(f'rectangle "{name}\\n----\\n{body}" as {name.split()[0].lower()}_list')
    out.append("@enduml")
    return "\n".join(out) + "\n"


(HERE / "puml" / "erd_core.puml").write_text(puml_erd(
    ["tblusers", "tblbooking", "tblvehicles", "tblbrands", "tbltestimonial", "tbl_price_forecast", "tbl_forecast_runs", "tbl_price_overrides", "tblfestivals", "tblsettings"]))
(HERE / "puml" / "erd_support.puml").write_text(puml_erd(
    ["admin", "tblusers", "tblpasswordresets", "tblpackages", "tblpackagecategories", "tblpackageimages", "tblpackageenquiries", "tblvehicles"],
    {"Standalone tables (no relationships)": ["tblratelimits", "tblpages", "tblsitecontent", "tblmailsettings", "tblcontactusinfo", "tblcontactusquery", "tblsubscribers"]}))
json.dump({"tables": cols, "indexes": indexes, "relationships": [dict(zip(["child", "child_column", "parent", "parent_column", "cardinality", "label"], r)) for r in RELS],
           "note": "No foreign-key constraints exist in the database; relationships are logical (enforced in PHP)."},
          open(HERE / "schema.json", "w"), indent=1)
print(len(cols), "tables,", sum(len(v) for v in cols.values()), "columns,", len(RELS), "logical relationships")
