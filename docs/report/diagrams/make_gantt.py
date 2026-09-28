"""Gantt chart of the recorded development work, built from the real Git history.

Each phase is the range of non-merge commits between its first and last commit (inclusive), as
listed in PHASES. Bars run from the first to the last commit time of the phase, so they show when
work was committed, not every hour spent. Output: figures/diagrams/gantt.png and results/gantt.json.
Run: ml/.venv/bin/python docs/report/diagrams/make_gantt.py
"""
import json
import subprocess
from datetime import datetime, timedelta
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.dates as mdates

HERE = Path(__file__).resolve().parent
REPORT = HERE.parent
PHASES = [  # (label, first commit, last commit)
    ("Phase 0: stabilise and secure", "278f52a", "1b226cf"),
    ("Phase 1: foundation", "08b18e8", "6a7a044"),
    ("Phase 2: public redesign", "3d3c673", "4daa61d"),
    ("Phase 3: admin redesign", "b604802", "3d24921"),
    ("Phase 3: content, festivals, packages", "5e8b6c6", "b2019de"),
    ("Phase 4: AI engine", "c428671", "20a3c36"),
    ("Phase 5: AI in the interface", "d8c736f", "93da222"),
    ("Phase 6: polish", "8ef7f0f", "200ce12"),
    ("Phase 7: evaluation, tests, diagrams", "a16a20d", "13e2cd5"),
    ("Phase 7: report (to Chapter 2)", "fe77255", "9151b7b"),
]

def log():
    out = subprocess.run(["git", "log", "--no-merges", "--reverse", "--format=%h %aI", "HEAD"],
                         capture_output=True, text=True, check=True, cwd=REPORT).stdout.split("\n")
    return [(h, datetime.fromisoformat(t)) for h, t in (line.split() for line in out if line)]

def main():
    commits = log()
    index = {h: i for i, (h, _) in enumerate(commits)}
    head = subprocess.run(["git", "rev-parse", "--short", "HEAD"], capture_output=True, text=True, cwd=REPORT).stdout.strip()
    rows = []
    for label, first, last in PHASES:
        a = index[first]
        b = len(commits) - 1 if last == "HEAD" else index[last]
        span = commits[a:b + 1]
        rows.append({"phase": label, "first": span[0][0], "last": span[-1][0], "start": span[0][1].isoformat(),
                     "end": span[-1][1].isoformat(), "commits": len(span)})
    (REPORT / "results").mkdir(exist_ok=True)
    (REPORT / "results" / "gantt.json").write_text(json.dumps({"head": head, "phases": rows}, indent=2))

    plt.rcParams.update({"font.family": "Arial", "font.size": 9})
    fig, ax = plt.subplots(figsize=(6.6, 4.0), dpi=250)
    for i, r in enumerate(rows):
        s, e = datetime.fromisoformat(r["start"]), datetime.fromisoformat(r["end"])
        e = max(e, s + timedelta(minutes=12))  # keep very short phases visible
        ax.barh(i, mdates.date2num(e) - mdates.date2num(s), left=mdates.date2num(s), height=0.55,
                color="#ff5a36" if "report" not in r["phase"] else "#ffb49f", edgecolor="#d23f1c")
        ax.text(mdates.date2num(e) + 0.01, i, f'{r["commits"]} commits', va="center", fontsize=7.5, color="#4f5362")
    ax.set_yticks(range(len(rows)), [r["phase"] for r in rows])
    ax.invert_yaxis()
    tz = commits[0][1].tzinfo  # the commits' own offset (Nepal time, +05:45)
    ax.xaxis.set_major_locator(mdates.HourLocator(byhour=range(0, 24, 6), tz=tz))
    ax.xaxis.set_major_formatter(mdates.DateFormatter("%d %b\n%H:%M", tz=tz))
    ax.grid(axis="x", color="#e5e6ea", linewidth=0.6)
    ax.set_axisbelow(True)
    for sp in ("top", "right"):
        ax.spines[sp].set_visible(False)
    ax.set_xlabel("Commit time, Nepal time (UTC+05:45), September 2026")
    fig.tight_layout()
    out = REPORT / "figures" / "diagrams" / "gantt.png"
    fig.savefig(out, facecolor="white")
    print(json.dumps(rows, indent=1))
    print("->", out.relative_to(REPORT.parent.parent))

if __name__ == "__main__":
    main()
