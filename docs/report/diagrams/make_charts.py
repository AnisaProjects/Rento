"""Charts for Chapter 6 from the measured results in docs/report/results/.
Run: ml/.venv/bin/python docs/report/diagrams/make_charts.py  ->  figures/page_weight.png
"""
import json
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

REPORT = Path(__file__).resolve().parent.parent
pages = json.loads((REPORT / "results" / "page_weight.json").read_text())["pages"]
plt.rcParams.update({"font.family": "Arial", "font.size": 9})
fig, ax = plt.subplots(figsize=(6.6, 3.2), dpi=250)
x = range(len(pages))
w = 0.38
b1 = ax.bar([i - w / 2 for i in x], [p["before_kb"] for p in pages], w, color="#9a9eab", label="Original template")
b2 = ax.bar([i + w / 2 for i in x], [p["now_kb"] for p in pages], w, color="#ff5a36", label="Rento (desktop)")
for bars in (b1, b2):
    for r in bars:
        ax.text(r.get_x() + r.get_width() / 2, r.get_height() + 40, f"{r.get_height():,.0f}", ha="center", fontsize=7)
ax.set_xticks(list(x), [p["page"] for p in pages])
ax.set_ylabel("Data transferred (KB)")
ax.legend(frameon=False)
for sp in ("top", "right"):
    ax.spines[sp].set_visible(False)
fig.tight_layout()
fig.savefig(REPORT / "figures" / "page_weight.png", facecolor="white")
print("figures/page_weight.png")
