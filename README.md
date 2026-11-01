# Woodex Interior

The Woodex Interior website and its content platform.

| Path | What it is | Status |
| --- | --- | --- |
| `woodex/` | The hand-built static site (18 HTML pages, `theme.css`, `site.js`, images). No build step; uploads straight to Hostinger. | Live-ready. Hardened and de-duplicated on 2026-10-26. |
| `woodex-os/` | **Woodex OS v2.1** — the CMS, admin, REST API and static exporter that now owns the site content. Express 4 + EJS + `node:sqlite`, no external services. | Complete and tested (101 tests + 24 admin checks). |
| `docs/` | QA report, gap analysis, backend/API reference and the completion plan. | — |

## Quick start

```bash
cd woodex-os
npm install
npm run seed          # creates data/woodex-os.sqlite from content/seed/*.json
npm start             # http://localhost:4321  ·  /admin  ·  /api/v1
```

Sign in at `/admin` with the seeded owner account:

```
admin@woodexinterior.pk  /  WoodexStudio2026!
```

The seeder takes the account from `WOODEX_ADMIN_EMAIL` / `WOODEX_ADMIN_PASSWORD`, or
prints a random password once. **Change it before deploying** — either at
**My account** in the admin or with `npm run set-password -- --email <address>`.
Never reuse the demo credentials that appear in old notes.

```bash
npm test              # 101 tests: public site, API, admin, security, export
npm run admin:check   # 24 end-to-end checks driven through the real admin forms
npm run export        # renders the published content to woodex-os/dist for Apache
npm run qa:site       # crawls the live site and fails on SEO/a11y/HTML regressions
```

## What Woodex OS adds

- **Real content editing** — pages, projects, insights, services, media, navigation and
  site settings, all backed by SQLite with page revisions and restore.
- **Real enquiries** — the contact form posts to the server, validates per field, drops
  honeypot spam, rate limits, stores the lead and gives the studio a working inbox
  with statuses, notes and CSV export. The v1 form was a demo that sent nothing.
- **A REST API** — token-authenticated reads plus full admin CRUD, paginated, with a
  consistent error envelope.
- **Per-page SEO** — unique titles/descriptions, canonical URLs, Open Graph/Twitter
  cards, JSON-LD, sitemap, robots and RSS generated from the database.
- **Static export** — the same renderer writes `dist/` for shared hosting, so the live
  site stays static and fast while the CMS stays private.

## Deployment in one paragraph

Run `npm run export` and upload `dist/` to `public_html` (the generated `.htaccess`
adds HTTPS, compression, caching, CSP and a 404 page). Run the OS itself on any Node 22
host with a persistent disk for `data/`; put it behind HTTPS and set
`WOODEX_SECURE_COOKIES=1`. Details in [`docs/03-BACKEND-API.md`](docs/03-BACKEND-API.md)
and [`docs/04-COMPLETION-PLAN.md`](docs/04-COMPLETION-PLAN.md).

## Documentation

- [`docs/01-QA-REPORT.md`](docs/01-QA-REPORT.md) — what was found, fixed and still open.
- [`docs/02-GAP-REPORT.md`](docs/02-GAP-REPORT.md) — repo-by-repo inventory and the
  remaining gaps, including what is still not in Woodex OS.
- [`docs/03-BACKEND-API.md`](docs/03-BACKEND-API.md) — architecture, data model, API.
- [`docs/04-COMPLETION-PLAN.md`](docs/04-COMPLETION-PLAN.md) — phases, pending tasks,
  complex fixes and the launch checklist.
- [`woodex-os/README.md`](woodex-os/README.md) — application-level setup and operations.
