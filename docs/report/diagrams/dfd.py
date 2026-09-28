"""Data flow diagrams (Gane-Sarson style) drawn from explicit layouts, for print-legible figures.

External entities are squares, processes are rounded orange boxes with their number, data stores are grey boxes
with an open right side and their D-number. Every flow is an arrow with a label; bidirectional flows have two heads.
Coordinates are CSS pixels; the SVG files are rasterised at 2x by headless Chrome.
Run: ml/.venv/bin/python docs/report/diagrams/dfd.py   ->  figures/diagrams/dfd_*.png
"""
import subprocess
from pathlib import Path
from html import escape

from PIL import ImageFont

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / "figures" / "diagrams"
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
FONT = 14
LINE = 17
try:
    _font = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", FONT)
except OSError:  # pragma: no cover
    _font = ImageFont.load_default()


def text_width(s):
    return _font.getlength(s)


class Diagram:
    def __init__(self, name, width, height):
        self.name, self.w, self.h = name, width, height
        self.nodes, self.parts = {}, []

    # kind: entity | process | store
    def node(self, key, kind, x, y, w, h, label):
        self.nodes[key] = (kind, x, y, w, h, label)

    def box(self, key):
        _, x, y, w, h, _ = self.nodes[key]
        return x, y, w, h

    def port(self, key, side, t=0.5):
        """Point on a side of a node: side in l, r, t, b; t = position along the side (0..1)."""
        x, y, w, h = self.box(key)
        return {"l": (x, y + h * t), "r": (x + w, y + h * t), "t": (x + w * t, y), "b": (x + w * t, y + h)}[side]

    def flow(self, a, b, label="", both=False, via=None, lpos=0.5, loff=(0, 0), at=None):
        """a, b: (node, side, t). via: intermediate points for elbows. at: absolute label centre."""
        p = [self.port(*a)] + (via or []) + [self.port(*b)]
        self.parts.append(("flow", p, label, both, lpos, loff, at))

    def svg(self):
        o = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.w}" height="{self.h}" viewBox="0 0 {self.w} {self.h}" '
             f'font-family="Arial, Helvetica, sans-serif" font-size="{FONT}">',
             '<defs><marker id="a" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">'
             '<path d="M0,0 L10,5 L0,10 z" fill="#4f5362"/></marker></defs>',
             f'<rect width="{self.w}" height="{self.h}" fill="white"/>']
        labels = []
        for _, pts, label, both, lpos, loff, at in self.parts:
            d = "M" + " L".join(f"{x:.1f},{y:.1f}" for x, y in pts)
            o.append(f'<path d="{d}" fill="none" stroke="#4f5362" stroke-width="1.4" marker-end="url(#a)"'
                     + (' marker-start="url(#a)"' if both else "") + "/>")
            if label:
                # label at lpos along the total length of the polyline
                segs = list(zip(pts, pts[1:]))
                lens = [((x2 - x1) ** 2 + (y2 - y1) ** 2) ** 0.5 for (x1, y1), (x2, y2) in segs]
                target, acc = sum(lens) * lpos, 0
                for ((x1, y1), (x2, y2)), ln in zip(segs, lens):
                    if acc + ln >= target:
                        f = (target - acc) / ln if ln else 0
                        cx, cy = x1 + (x2 - x1) * f + loff[0], y1 + (y2 - y1) * f + loff[1]
                        break
                    acc += ln
                labels.append(at + (label,) if at else (cx, cy, label))
        for kind, x, y, w, h, label in self.nodes.values():
            if kind == "entity":
                o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="white" stroke="#101114" stroke-width="1.6"/>')
            elif kind == "process":
                o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="14" fill="#fff0eb" stroke="#d23f1c" stroke-width="1.4"/>')
            else:  # store: grey box open on the right, with a compartment for the D-number
                o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#f1f2f4" stroke="none"/>')
                o.append(f'<path d="M{x + w},{y} L{x},{y} L{x},{y + h} L{x + w},{y + h}" fill="none" stroke="#4f5362" stroke-width="1.4"/>')
                o.append(f'<path d="M{x + 38},{y} L{x + 38},{y + h}" stroke="#4f5362" stroke-width="1.2"/>')
            lines = label.split("\n")
            if kind == "store":
                num, rest = lines[0].split(" ", 1)
                lines = [rest] + lines[1:]
                o.append(f'<text x="{x + 19}" y="{y + h / 2 + 5}" text-anchor="middle" font-weight="bold">{escape(num)}</text>')
                tx, anchor = x + 46, "start"
            else:
                tx, anchor = x + w / 2, "middle"
            top = y + h / 2 - (len(lines) - 1) * LINE / 2 + 5
            for i, ln in enumerate(lines):
                weight = ' font-weight="bold"' if (kind == "entity" or (kind == "process" and i == 0)) else ""
                o.append(f'<text x="{tx}" y="{top + i * LINE:.1f}" text-anchor="{anchor}"{weight}>{escape(ln)}</text>')
        for cx, cy, label in labels:
            lines = label.split("\n")
            wmax = max(text_width(s) for s in lines) + 8
            hh = len(lines) * (LINE - 1) + 4
            o.append(f'<rect x="{cx - wmax / 2:.1f}" y="{cy - hh / 2:.1f}" width="{wmax:.1f}" height="{hh}" fill="white" opacity="0.92"/>')
            for i, ln in enumerate(lines):
                yy = cy - (len(lines) - 1) * (LINE - 1) / 2 + 5 + i * (LINE - 1)
                o.append(f'<text x="{cx:.1f}" y="{yy:.1f}" text-anchor="middle" font-size="{FONT - 1}" fill="#30333d">{escape(ln)}</text>')
        o.append("</svg>")
        return "\n".join(o)

    def render(self):
        src = HERE / "dfd_svg" / f"{self.name}.svg"
        src.parent.mkdir(exist_ok=True)
        src.write_text(self.svg())
        png = OUT / f"{self.name}.png"
        subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=2",
                        f"--window-size={self.w},{self.h}", f"--screenshot={png}", src.as_uri()],
                       check=True, capture_output=True)
        print(f"{self.name}: {self.w}x{self.h} -> {png.relative_to(HERE.parent.parent.parent)}")


# ---------------------------------------------------------------- Level 0 (context diagram)
d = Diagram("dfd_level0", 860, 400)
d.node("C", "entity", 20, 40, 150, 70, "Customer /\nguest")
d.node("A", "entity", 20, 290, 150, 70, "Administrator")
d.node("P", "process", 345, 150, 170, 110, "0\nRento car rental\nsystem")
d.node("S", "entity", 690, 40, 150, 70, "Scheduler\n(nightly 02:30)")
d.node("M", "entity", 690, 290, 150, 70, "SMTP mail\nserver")
d.flow(("C", "r", 0.28), ("P", "t", 0.26), "search criteria, trip description,\nbooking request, enquiry, account", via=[(389, 60)], at=(262, 36))
d.flow(("P", "l", 0.27), ("C", "b", 0.5), "car lists, day prices and reasons,\nrecommendations, status, invoice", via=[(95, 180)], at=(222, 202))
d.flow(("A", "t", 0.5), ("P", "l", 0.73), "fleet, festivals, settings,\nbooking decisions, overrides", via=[(95, 230)], at=(222, 252))
d.flow(("P", "b", 0.26), ("A", "r", 0.71), "dashboard figures, AI Insights,\nmodel health", via=[(389, 340)], at=(270, 364))
d.flow(("S", "l", 0.5), ("P", "t", 0.74), "nightly recompute trigger", via=[(471, 75)], at=(582, 60))
d.flow(("P", "r", 0.82), ("M", "l", 0.5), "reset links, booking\nand enquiry emails", via=[(610, 240), (610, 325)], at=(616, 206))
d.render()

# ---------------------------------------------------------------- Level 1, part A: customer processes
d = Diagram("dfd_level1a_customer", 860, 790)
d.node("C", "entity", 20, 40, 130, 600, "Customer /\nguest")
d.node("A", "entity", 20, 690, 130, 56, "Administrator")
X, W, H = 330, 190, 70
for key, top, label in [("P1", 55, "1.0\nAuthenticate and\nmanage account"), ("P2", 185, "2.0\nBrowse and search"),
                        ("P3", 315, "3.0\nRecommend cars"), ("P4", 445, "4.0\nQuote prices"), ("P5", 575, "5.0\nBook and invoice")]:
    d.node(key, "process", X, top, W, H, label)
SX, SW = 640, 190
d.node("D1", "store", SX, 44, SW, 36, "D1 tblusers, admin")
d.node("D7", "store", SX, 100, SW, 36, "D7 resets, rate limits")
d.node("D2", "store", SX, 202, SW, 36, "D2 vehicles, brands")
d.node("D4", "store", SX, 332, SW, 36, "D4 tbl_price_forecast")
d.node("D3", "store", SX, 527, SW, 36, "D3 tblbooking")
d.node("PX", "process", SX, 660, SW, 90, "8.0 Forecast prices,\n9.0 Send email\n(Figure part B)")
def cflow(p, top, inl, outl=None):
    d.flow(("C", "r", (top + 21 - 40) / 600), (p, "l", 0.3), inl, at=(240, top + 7))
    if outl:
        d.flow((p, "l", 0.7), ("C", "r", (top + 49 - 40) / 600), outl, at=(240, top + 63))
cflow("P1", 55, "credentials")
cflow("P2", 185, "filters", "car cards")
cflow("P3", 315, "trip description", "ranked cars, reasons")
cflow("P4", 445, "car, dates", "day prices, reasons")
cflow("P5", 575, "booking request", "status, invoice")
d.flow(("A", "r", 0.5), ("P5", "b", 0.5), "confirm / cancel", via=[(425, 718)], at=(290, 732))
d.flow(("P4", "b", 0.5), ("P5", "t", 0.5), "server quote", at=(475, 545))
d.flow(("P1", "r", 0.2), ("D1", "l", 0.5), "", both=True)
d.flow(("P1", "r", 0.75), ("D7", "l", 0.5), "", both=True)
d.flow(("D2", "l", 0.5), ("P2", "r", 0.5), "fleet", at=(580, 212))
d.flow(("D2", "l", 0.85), ("P3", "r", 0.25), "", )
d.flow(("D4", "l", 0.15), ("P2", "r", 0.85), "'from' prices", at=(566, 290))
d.flow(("D4", "l", 0.5), ("P3", "r", 0.55), "prices", at=(600, 339))
d.flow(("D4", "l", 0.85), ("P4", "r", 0.25), "day prices", at=(596, 432))
d.flow(("D3", "l", 0.15), ("P3", "r", 0.85), "rental history", at=(570, 400))
d.flow(("D3", "l", 0.5), ("P4", "r", 0.75), "availability", at=(586, 520))
d.flow(("P5", "r", 0.3), ("D3", "l", 0.85), "booking", both=True, at=(578, 576))
d.flow(("P5", "r", 0.75), ("PX", "l", 0.3), "recompute request,\nbooking emails", via=[(575, 627.5), (575, 687)], at=(575, 655))
d.flow(("P1", "t", 0.7), ("PX", "r", 0.5), "reset link", via=[(463, 20), (848, 20), (848, 705)], at=(700, 20))
d.render()

# ---------------------------------------------------------------- Level 1, part B: administration and background processes
# Stores marked * are repeated to avoid crossing lines (standard DFD practice).
d = Diagram("dfd_level1b_admin", 860, 720)
d.node("C", "entity", 20, 20, 130, 56, "Customer /\nguest")
d.node("A", "entity", 20, 190, 130, 140, "Administrator")
d.node("S", "entity", 20, 360, 130, 60, "Scheduler")
d.node("M", "entity", 20, 625, 130, 60, "SMTP server")
X, W = 330, 190
d.node("P6", "process", X, 20, W, 76, "6.0\nHandle packages\nand enquiries")
d.node("P7", "process", X, 160, W, 120, "7.0\nAdminister fleet,\ncontent, festivals,\npricing")
d.node("P8", "process", X, 360, W, 60, "8.0\nForecast prices")
d.node("P5", "process", X, 500, W, 56, "5.0\nBook and invoice")
d.node("P9", "process", X, 625, W, 60, "9.0\nSend email")
SX, SW = 640, 190
d.node("D6", "store", SX, 30, SW, 36, "D6 packages, enquiries")
d.node("D8", "store", SX, 110, SW, 36, "D8 pages, content, mail")
d.node("D5", "store", SX, 170, SW, 36, "D5 festivals, settings")
d.node("D2", "store", SX, 230, SW, 36, "D2 vehicles, brands")
d.node("D4", "store", SX, 290, SW, 36, "D4 forecast, overrides")
d.node("D5b", "store", SX, 345, SW, 30, "D5 festivals, settings *")
d.node("D2b", "store", SX, 385, SW, 30, "D2 vehicles *")
d.node("D3", "store", SX, 425, SW, 30, "D3 tblbooking")
d.node("D4b", "store", SX, 470, SW, 30, "D4 tbl_price_forecast *")
d.flow(("C", "r", 0.5), ("P6", "l", 0.37), "enquiry", at=(240, 34))
d.flow(("A", "t", 0.5), ("P6", "b", 0.2), "status, notes", via=[(85, 125), (368, 125)], at=(226, 125))
d.flow(("A", "r", 0.3), ("P7", "l", 0.6), "fleet, packages,\ncontent, festivals,\nsettings, overrides", at=(240, 198))
d.flow(("P7", "l", 0.85), ("A", "r", 0.514), "AI Insights,\nmodel health", at=(240, 285))
d.flow(("S", "r", 0.5), ("P8", "l", 0.5), "nightly trigger", at=(240, 374))
d.flow(("P6", "r", 0.37), ("D6", "l", 0.5), "", both=True)
d.flow(("P7", "r", 0.04), ("D6", "b", 0.2), "packages", at=(590, 100))
d.flow(("P7", "r", 0.25), ("D8", "l", 0.5), "content", at=(582, 150))
d.flow(("P7", "r", 0.45), ("D5", "l", 0.5), "festivals, settings", at=(575, 196))
d.flow(("P7", "r", 0.65), ("D2", "l", 0.5), "fleet", at=(585, 245))
d.flow(("P7", "r", 0.88), ("D4", "l", 0.5), "overrides / forecast", both=True, at=(575, 292))
d.flow(("P7", "b", 0.5), ("P8", "t", 0.5), "recompute request", at=(490, 322))
d.flow(("P5", "t", 0.5), ("P8", "b", 0.5), "recompute", at=(470, 460))
d.flow(("D5b", "l", 0.5), ("P8", "r", 0.1), "")
d.flow(("D2b", "l", 0.5), ("P8", "r", 0.5), "")
d.flow(("D3", "l", 0.5), ("P8", "r", 0.8), "inputs", at=(585, 420))
d.flow(("P8", "r", 0.95), ("D4b", "l", 0.5), "60-day prices", at=(565, 494))
d.flow(("P5", "b", 0.5), ("P9", "t", 0.5), "booking emails", at=(485, 590))
d.flow(("P6", "t", 0.8), ("P9", "r", 0.75), "enquiry email", via=[(482, 8), (850, 8), (850, 670)], at=(700, 8))
d.flow(("D8", "r", 0.5), ("P9", "r", 0.35), "SMTP settings", via=[(836, 128), (836, 646)], at=(760, 646))
d.flow(("P9", "l", 0.5), ("M", "r", 0.5), "emails", at=(240, 640))
d.render()

# ---------------------------------------------------------------- Level 2: process 5.0 (booking)
d = Diagram("dfd_level2_booking", 860, 720)
d.node("C", "entity", 20, 30, 150, 60, "Customer")
d.node("A", "entity", 20, 505, 150, 60, "Administrator")
d.node("C2", "entity", 20, 625, 150, 60, "Customer *")
X, W, H = 300, 220, 60
for key, top, label in [("P51", 30, "5.1\nCheck session and CSRF"), ("P52", 120, "5.2\nValidate dates"), ("P53", 210, "5.3\nCheck overlapping bookings"),
                        ("P54", 300, "5.4\nServer-side quote"), ("P55", 390, "5.5\nStore booking with\nquoted_price"),
                        ("P56", 505, "5.6\nConfirm or cancel\n(clash check)"), ("P57", 625, "5.7\nMy Bookings and invoice")]:
    d.node(key, "process", X, top, W, H if key not in ("P55", "P56") else 66, label)
SX, SW = 640, 200
d.node("D3", "store", SX, 222, SW, 36, "D3 tblbooking")
d.node("D4", "store", SX, 312, SW, 36, "D4 tbl_price_forecast")
d.node("F1", "process", SX, 388, SW, 30, "8.0 Forecast prices *")
d.node("E1", "process", SX, 432, SW, 30, "9.0 Send email *")
d.node("F2", "process", SX, 498, SW, 30, "8.0 Forecast prices *")
d.node("E2", "process", SX, 538, SW, 30, "9.0 Send email *")
d.node("D3b", "store", SX, 590, SW, 36, "D3 tblbooking *")
d.node("D1", "store", SX, 650, SW, 36, "D1 tblusers")
d.flow(("C", "r", 0.5), ("P51", "l", 0.5), "dates, message,\ncsrf_token", at=(235, 42))
d.flow(("P51", "b", 0.5), ("P52", "t", 0.5))
d.flow(("P52", "b", 0.5), ("P53", "t", 0.5))
d.flow(("D3", "l", 0.5), ("P53", "r", 0.5), "bookings", at=(580, 228))
d.flow(("P53", "b", 0.5), ("P54", "t", 0.5), "car is free", at=(458, 285))
d.flow(("D4", "l", 0.5), ("P54", "r", 0.5), "day prices", at=(580, 318))
d.flow(("P54", "b", 0.5), ("P55", "t", 0.5), "total, per-day prices", at=(482, 375))
d.flow(("P55", "r", 0.22), ("D3", "l", 0.8), "booking", via=[(600, 404.5), (600, 250.8)], at=(600, 290))
d.flow(("P55", "r", 0.5), ("F1", "l", 0.7), "recompute", at=(565, 412))
d.flow(("P55", "r", 0.82), ("E1", "l", 0.5), "request received", at=(578, 460))
d.flow(("A", "r", 0.5), ("P56", "l", 0.5), "decision", at=(235, 523))
d.flow(("P56", "r", 0.12), ("F2", "l", 0.5), "recompute", at=(578, 500))
d.flow(("P56", "r", 0.5), ("E2", "l", 0.5), "confirmed", at=(578, 542))
d.flow(("P56", "r", 0.88), ("D3b", "l", 0.4), "status", both=True, at=(578, 583))
d.flow(("C2", "r", 0.3), ("P57", "l", 0.3), "booking number", at=(235, 631))
d.flow(("P57", "l", 0.7), ("C2", "r", 0.7), "invoice (stored price)", at=(235, 680))
d.flow(("D3b", "l", 0.85), ("P57", "r", 0.2), "")
d.flow(("D1", "l", 0.5), ("P57", "r", 0.75), "customer details", at=(578, 685))
d.render()

# ---------------------------------------------------------------- Level 2: process 8.0 (forecast)
d = Diagram("dfd_level2_forecast", 860, 630)
d.node("T", "entity", 20, 20, 190, 70, "Trigger: admin,\nchange or nightly\nscheduler")
X, W, H = 290, 230, 58
for key, top, label in [("P81", 26, "8.1\nQueue run"), ("P82", 116, "8.2\nStart Python (settings in env)"),
                        ("P83", 206, "8.3\nLoad cars, festivals,\nbookings, overrides"), ("P84", 324, "8.4\nPrice each car-day"),
                        ("P85", 442, "8.5\nApply manual overrides"), ("P86", 552, "8.6\nReplace forecast")]:
    d.node(key, "process", X, top, W, H, label)
d.node("P87", "process", 20, 552, 190, H, "8.7\nRecord run status")
SX, SW = 620, 220
d.node("R", "store", SX, 37, SW, 36, "D4 tbl_forecast_runs")
d.node("D5", "store", SX, 127, SW, 36, "D5 festivals, settings")
d.node("D2", "store", SX, 200, SW, 36, "D2 tblvehicles")
d.node("D3", "store", SX, 250, SW, 36, "D3 tblbooking")
d.node("O", "store", SX, 453, SW, 36, "D4 tbl_price_overrides")
d.node("F", "store", SX, 563, SW, 36, "D4 tbl_price_forecast")
d.flow(("T", "r", 0.5), ("P81", "l", 0.5))
d.flow(("P81", "r", 0.5), ("R", "l", 0.5), "queued run", at=(570, 43))
d.flow(("P81", "b", 0.5), ("P82", "t", 0.5))
d.flow(("D5", "l", 0.5), ("P82", "r", 0.5), "settings", at=(570, 133))
d.flow(("P82", "b", 0.5), ("P83", "t", 0.5))
d.flow(("D5", "l", 0.9), ("P83", "r", 0.15), "festivals", at=(560, 196))
d.flow(("D2", "l", 0.5), ("P83", "r", 0.5), "cars", at=(596, 226))
d.flow(("D3", "l", 0.5), ("P83", "r", 0.85), "bookings", at=(578, 266))
d.flow(("P83", "b", 0.5), ("P84", "t", 0.5), "car context, festivals,\ndemand gate (50 confirmed)", at=(512, 295))
d.flow(("O", "l", 0.5), ("P85", "r", 0.5), "overrides", at=(570, 459))
d.flow(("P84", "b", 0.5), ("P85", "t", 0.5), "price, multiplier,\nreasons, capped", at=(482, 412))
d.flow(("P85", "b", 0.5), ("P86", "t", 0.5))
d.flow(("P86", "r", 0.5), ("F", "l", 0.5), "60-day prices", at=(570, 569))
d.flow(("P86", "l", 0.5), ("P87", "r", 0.5))
d.flow(("P87", "t", 0.5), ("R", "t", 0.5), "status, message", via=[(115, 500), (255, 500), (255, 10), (730, 10)], at=(480, 10))
d.render()
