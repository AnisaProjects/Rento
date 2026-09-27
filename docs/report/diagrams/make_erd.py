"""
Builds the ERD sources (Mermaid) and schema.json from the live schema export.
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


def erd(tables, title, compact=True):
    out = ["---", f"title: \"{title}\"", "---", "erDiagram"]
    out += [entity(t, compact) for t in tables]
    for ct, cc, pt, pc, card, label in RELS:
        if ct in tables and pt in tables:
            left, right = card.split("--")
            # Mermaid: PARENT ||--o{ CHILD : "label"
            rev = {"}o": "o{", "||": "||", "o|": "|o"}
            out.append(f'    {pt} {rev.get(right[::-1], right[::-1])}..{rev.get(left, left)} {ct} : "{label} (logical, {ct}.{cc})"')
    return "\n".join(out) + "\n"


(HERE / "erd_core.mmd").write_text(erd(CORE, "Rento ERD (1/2): bookings, fleet and smart pricing. Dotted lines = logical relationships (no FK constraints)"))
(HERE / "erd_support.mmd").write_text(erd(SUPPORT, "Rento ERD (2/2): accounts, security, packages and content. Dotted lines = logical relationships"))
json.dump({"tables": cols, "indexes": indexes, "relationships": [dict(zip(["child", "child_column", "parent", "parent_column", "cardinality", "label"], r)) for r in RELS],
           "note": "No foreign-key constraints exist in the database; relationships are logical (enforced in PHP)."},
          open(HERE / "schema.json", "w"), indent=1)
print(len(cols), "tables,", sum(len(v) for v in cols.values()), "columns,", len(RELS), "logical relationships")
