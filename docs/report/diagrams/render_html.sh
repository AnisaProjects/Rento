#!/bin/zsh
# Renders HTML-drawn figures (currently conceptual_framework.html) to docs/report/figures/diagrams/<name>.png at 2x.
here=${0:A:h}
for f in ${@:-$here/*.html}; do
  n=${f:t:r}
  "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu --hide-scrollbars \
    --force-device-scale-factor=2 --window-size=1200,552 --screenshot="$here/../figures/diagrams/$n.png" "file://${f:A}" 2>/dev/null
  echo "$n.html -> docs/report/figures/diagrams/$n.png"
done
