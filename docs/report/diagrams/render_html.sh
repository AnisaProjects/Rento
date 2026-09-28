#!/bin/zsh
# Renders the HTML-drawn figures to docs/report/figures/diagrams/<name>.png at 2x.
# Each file states its capture size in CSS pixels in a comment: <!-- ... size: WIDTHxHEIGHT -->
#   docs/report/diagrams/render_html.sh [file.html ...]     (default: every .html here)
here=${0:A:h}
for f in ${@:-$here/*.html}; do
  n=${f:t:r}
  size=$(grep -oE 'size: [0-9]+x[0-9]+' "$f" | head -1 | sed 's/size: //; s/x/,/')
  "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu --hide-scrollbars \
    --force-device-scale-factor=2 --window-size=${size:-1200,600} --screenshot="$here/../figures/diagrams/$n.png" "file://${f:A}" 2>/dev/null
  echo "$n.html ($size) -> docs/report/figures/diagrams/$n.png"
done
