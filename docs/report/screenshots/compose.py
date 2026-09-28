"""Side-by-side desktop + mobile figures for the report (desktop scaled to the mobile screenshot's height).
Run: ml/.venv/bin/python docs/report/screenshots/compose.py  ->  docs/report/figures/ui_*.png
"""
from pathlib import Path
from PIL import Image, ImageDraw

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / "figures"
PAIRS = {
    "ui_home": ("01_home_desktop.png", "02_home_mobile.png"),
    "ui_car_page": ("05_car_price_calendar_desktop.png", "06_car_price_calendar_mobile.png"),
    "ui_find_my_car": ("07_find_my_car_results_desktop.png", "08_find_my_car_mobile.png"),
    "ui_dark_mode": ("13_home_dark_desktop.png", "14_car_dark_mobile.png"),
}
GAP, PAD, H = 40, 20, 1100  # output height of the mobile shot (px)

for name, (desk, mob) in PAIRS.items():
    d, m = Image.open(HERE / desk).convert("RGB"), Image.open(HERE / mob).convert("RGB")
    m = m.resize((round(m.width * H / m.height), H), Image.LANCZOS)
    dh = round(H * 0.62)  # desktop is wider than tall: give it 62% of the height, centred
    d = d.resize((round(d.width * dh / d.height), dh), Image.LANCZOS)
    out = Image.new("RGB", (PAD * 2 + d.width + GAP + m.width, PAD * 2 + H), "white")
    out.paste(d, (PAD, PAD + (H - dh) // 2))
    out.paste(m, (PAD + d.width + GAP, PAD))
    draw = ImageDraw.Draw(out)
    for x, y, im in ((PAD, PAD + (H - dh) // 2, d), (PAD + d.width + GAP, PAD, m)):
        draw.rectangle([x - 1, y - 1, x + im.width, y + im.height], outline="#9a9eab", width=2)
    out.save(OUT / f"{name}.png", optimize=True)
    print(name, out.size)

# Crops of tall admin screenshots (top part only).
# (source, left, height): the model-health capture has an empty sidebar (full-page capture), so it is cut off.
CROPS = {"ui_admin_ai_insights": ("21_admin_ai_insights.png", 0, 1250), "ui_admin_model_health": ("22_admin_smart_pricing_model_health.png", 256, 1250)}
for name, (src, left, height) in CROPS.items():
    im = Image.open(HERE / src).convert("RGB")
    im.crop((left, 0, im.width, min(height, im.height))).save(OUT / f"{name}.png", optimize=True)
    print(name, im.width, min(height, im.height))
