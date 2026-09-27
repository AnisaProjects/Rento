# Rento documentation

| Document | For | What it covers |
|---|---|---|
| [admin-guide.md](admin-guide.md) | Whoever runs the site | Day-to-day admin: bookings, vehicles, packages and enquiries, smart pricing, AI Insights, festivals, content and drafts, email, system check |
| [deployment.md](deployment.md) | Whoever moves the site to hosting | Server requirements, secrets, database users, php.ini, cron, permissions, backups, production check, launch checklist |
| [decisions.md](decisions.md) | Owner / developers | Every decision made on the owner's behalf (what, why, how to undo), newest first |
| [../README.md](../README.md) | Developers | Local setup, project structure, how smart pricing and Find My Car work |
| [../ml/README.md](../ml/README.md) | Developers | The Python pricing forecast: files, setup, tests, how to change the rules |
| [legal/terms-draft.html](legal/terms-draft.html), [legal/privacy-draft.html](legal/privacy-draft.html) | Owner, lawyer | **Drafts** of the Terms and Privacy Policy (also in admin → Manage Pages). Fill in every `[TO CONFIRM]` and have them reviewed before publishing |
| [market-research.md](market-research.md) | Owner | Nepal rental market, 2026-09-27: competitor prices, fleet and package suggestions, sources |
| [market-benchmarks.json](market-benchmarks.json) | The site | Self-drive market rates used for the "Below market" badge |
| [festival-calendar-sources.md](festival-calendar-sources.md) | Owner | Where the festival dates come from and how reliable each is (BS 2083 official, BS 2084 provisional) |
| [archive/](archive/) | History | Outdated notes about the first pricing prototype |

Database changes are in `../database/migrations/` (applied in order), with undo scripts for data
migrations in `../database/undo/`.
