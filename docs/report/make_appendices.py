"""Generates the data-driven appendices of the report from the repository itself:

  content/91_appendix_a.md   Appendix A: repository, branches and the full commit history (git log)
  content/94_appendix_d.md   Appendix D: data dictionary of all tables (diagrams/schema.json)
  content/95_appendix_e.md   Appendix E: samples of the two datasets (python/*.csv)
  content/97_appendix_g.md   Appendix G: source code listings (copied from the files)

Run before build_report.py:  ml/.venv/bin/python docs/report/make_appendices.py
The hand-written appendices (B, C, F) are content/92_*.md, 93_*.md and 96_*.md.
"""
import csv
import json
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
CONTENT = HERE / "content"


def cell(text):
    return str(text).replace("|", "/").replace("`", "'").replace("\n", " ").strip()


def git(*args):
    return subprocess.run(["git", *args], capture_output=True, text=True, check=True, cwd=ROOT).stdout.strip()


# ---------------------------------------------------------------- Appendix A
def appendix_a():
    head = git("rev-parse", "--short", "HEAD")
    branch = git("rev-parse", "--abbrev-ref", "HEAD")
    branches = [b.strip().lstrip("* ") for b in git("branch", "--format=%(refname:short)").splitlines()]
    log = git("log", "--reverse", "--no-merges", "--format=%h\t%ad\t%s", "--date=format:%Y-%m-%d %H:%M", "HEAD").splitlines()
    merges = git("rev-list", "--merges", "--count", "HEAD")
    total = git("rev-list", "--count", "HEAD")
    phases = json.loads((HERE / "results" / "gantt.json").read_text())["phases"]
    out = ["# APPENDIX A: PROJECT REPOSITORY AND VERSION HISTORY", "",
           "## A.1 Repository", "",
           "The source code, database migrations, tests, evaluation scripts and this report are kept in a Git repository:", "",
           "- Repository: [INSERT GitHub link]",
           "- Main branch: main; integration branch: dev; feature branches: " + ", ".join(sorted(b for b in branches if b.startswith("feature/"))),
           f"- History listed below: up to commit {head} on branch {branch}, {total} commits in total, of which {merges} are merge commits",
           "",
           "The history has not been rewritten or back-dated. Every commit made with the help of the AI assistant carries a "
           "\"Co-Authored-By\" line, except the automatic merge commit 8bd13fa, as stated in Section 3.2.3.", "",
           "## A.2 Commits by Phase", "",
           "Table: Table A.1 Commits by development phase (from the Git history) {widths=2.35,1.35,1.35,0.8}",
           "| Phase | First commit | Last commit | Commits |", "|---|---|---|---|"]
    for p in phases:
        out.append(f"| {cell(p['phase'])} | {p['first']} ({p['start'][:16].replace('T', ' ')}) | {p['last']} ({p['end'][:16].replace('T', ' ')}) | {p['commits']} |")
    out += ["", "## A.3 Commit History", "",
            f"Table A.2 lists every commit except merge commits, oldest first, with its date and time in Nepal time and its subject line.", "",
            "Table: Table A.2 Commit history (non-merge commits) {widths=0.65,1.15,3.95}",
            "| Commit | Date | Subject |", "|---|---|---|"]
    for line in log:
        h, d, s = line.split("\t", 2)
        out.append(f"| {h} | {d} | {cell(s)} |")
    (CONTENT / "91_appendix_a.md").write_text("\n".join(out) + "\n")
    return len(log)


# ---------------------------------------------------------------- Appendix D
ORIGIN = {"admin", "tblbooking", "tblbrands", "tblcontactusinfo", "tblcontactusquery", "tblpages", "tblsubscribers",
          "tbltestimonial", "tblusers", "tblvehicles"}


def appendix_d():
    schema = json.loads((HERE / "diagrams" / "schema.json").read_text())
    tables = schema["tables"]
    out = ["# APPENDIX D: DATA DICTIONARY", "",
           f"This appendix lists all {len(tables)} tables of the rento database with their {sum(len(c) for c in tables.values())} columns, "
           "as exported from the live schema. Key: PRI primary key, UNI unique, MUL indexed. The database has no foreign key "
           "constraints; the logical relationships are shown in Figures 4.7 and 4.8. Tables marked \"template\" come from the "
           "original template (some with added columns); the others were added by the migrations of Table 5.2.", ""]
    n = 0
    for t, cols in tables.items():
        n += 1
        origin = "template" if t in ORIGIN else "added"
        out += [f"Table: Table D.{n} {t} ({origin}, {len(cols)} columns) {{widths=1.6,1.35,0.55,0.55,1.7}}",
                "| Column | Type | Null | Key | Default / extra |", "|---|---|---|---|---|"]
        for c in cols:
            extra = " ".join(x for x in [c["default"], c["extra"].replace("DEFAULT_GENERATED", "").strip()] if x)
            out.append(f"| {cell(c['column'])} | {cell(c['type'])} | {'Yes' if c['nullable'] else 'No'} | {c['key']} | {cell(extra)} |")
        out.append("")
    (CONTENT / "94_appendix_d.md").write_text("\n".join(out) + "\n")
    return n


# ---------------------------------------------------------------- Appendix E
def appendix_e():
    out = ["# APPENDIX E: DATASET SAMPLES", "",
           "This appendix shows the first rows of the two datasets used in the machine-learning experiments of Section 5.9. "
           "Neither dataset contains Rento bookings or personal data.", "",
           "## E.1 Hourly Demand Dataset", "",
           "Source: [INSERT demand dataset name and URL], prepared by python/demand_preprocessing.py into python/train_features.csv "
           "(18,247 labelled rows). A selection of the 20 columns is shown; the cyclical encodings (hour_sin, hour_cos, dow_sin, "
           "dow_cos, month_sin, month_cos) and month, week_of_year, quarter and month-start/end flags are omitted.", ""]
    cols = ["date", "hour", "demand", "day_of_week", "is_weekend", "is_festival", "day_of_year", "season"]
    with open(ROOT / "python" / "train_features.csv", newline="") as f:
        rows = [r for _, r in zip(range(8), csv.DictReader(f))]
    out += ["Table: Table E.1 First rows of the hourly demand dataset {widths=1.0,0.5,0.65,0.8,0.75,0.75,0.75,0.8}", "| " + " | ".join(c.replace("_", " ") for c in cols) + " |", "|" + "---|" * len(cols)]
    out += ["| " + " | ".join(cell(r[c]) for c in cols) + " |" for r in rows]
    out += ["", "## E.2 Sajilo Rental Price Dataset", "",
            "Source: public listings of Sajilo Rental (n.d.), collected with python/scraper.py in August 2026 and cleaned by "
            "python/preprocessing.py into python/clean_vehicles.csv (1,640 rows). The target of Experiment 2, scaling_1day, is the "
            "day price derived by the preprocessing from the four-hour price, as explained in Section 5.9.1.", ""]
    cols = ["vehicle_name", "manufacturer", "price_4hr", "price_8hr", "price_1day", "max_km_4hr", "fuel_cost_per_km", "base_price_per_hour", "scaling_1day"]
    with open(ROOT / "python" / "clean_vehicles.csv", newline="") as f:
        rows = [r for _, r in zip(range(8), csv.DictReader(f))]
    out += ["Table: Table E.2 First rows of the cleaned price dataset {widths=1.05,0.8,0.55,0.55,0.55,0.55,0.55,0.6,0.55}",
            "| " + " | ".join(c.replace("_", " ") for c in cols) + " |", "|" + "---|" * len(cols)]
    out += ["| " + " | ".join(cell(r[c].removesuffix(".0")) for c in cols) + " |" for r in rows]
    (CONTENT / "95_appendix_e.md").write_text("\n".join(out) + "\n")


# ---------------------------------------------------------------- Appendix G
LISTINGS = [
    ("ml/pricing_rules.py", "The rule-based pricer: the settings, festival and car context types and the function that prices one car for one day."),
    ("api/price-quote.php", "The price-quote endpoint of the JSON API."),
]


def appendix_g():
    out = ["# APPENDIX G: SOURCE CODE LISTINGS", "",
           "This appendix reproduces two central source files in full, unchanged from the repository. The rest of the code, including "
           "the forecast pipeline, the recommendation engine and the security module, is available in the repository listed in Appendix A.", ""]
    for n, (path, what) in enumerate(LISTINGS, 1):
        code = (ROOT / path).read_text().rstrip("\n").splitlines()
        out += [f"## G.{n} {path}", "", what, "", f"```Figure G.{n} Listing of {path} ({len(code)} lines)"]
        out += code
        out += ["```", ""]
    (CONTENT / "97_appendix_g.md").write_text("\n".join(out) + "\n")


if __name__ == "__main__":
    commits = appendix_a()
    tables = appendix_d()
    appendix_e()
    appendix_g()
    print(f"Appendix A: {commits} commits; D: {tables} tables; E and G written")
