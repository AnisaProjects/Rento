# Report diagrams

All figures are rendered to `docs/report/figures/diagrams/` and are sized so that their text prints at
roughly 6–9 pt when placed at the full text width (5.7 in) of the report.

| Source | Diagrams | Render |
|---|---|---|
| `puml/*.puml` (PlantUML, shared style in `puml/_style.iuml`) | use case, class, sequence, activity, ERD | `JAVA=… PLANTUML_JAR=… ./render_puml.sh` (needs Java and the MIT build of PlantUML) |
| `make_erd.py` | writes `puml/erd_core.puml`, `puml/erd_support.puml` and `schema.json` from `schema.tsv` / `indexes.tsv` | `python3 make_erd.py`, then render the ERDs with PlantUML |
| `dfd.py` | data flow diagrams (Gane–Sarson style, explicit layout) | `ml/.venv/bin/python docs/report/diagrams/dfd.py` (uses headless Chrome) |
| `*.html` | conceptual framework, methodology, architecture (size in a comment) | `./render_html.sh` (headless Chrome) |
| `make_gantt.py` | Gantt chart from the Git history (also writes `results/gantt.json`) | `ml/.venv/bin/python docs/report/diagrams/make_gantt.py` |

The database has no foreign-key constraints; the relationships in the ERDs are logical (enforced in PHP).
