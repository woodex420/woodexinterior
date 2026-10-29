# Woodex OS v2.1

A self-contained content platform for the Woodex Interior website: content model,
admin workspace, REST API and a static exporter for Apache/Hostinger.

- **Runtime:** Node.js ≥ 22.5 (uses the built-in `node:sqlite`)
- **Stack:** Express 4, EJS, `multer` — three dependencies, no build step, no external
  database, no CDN, no third-party JavaScript on the public site.
- **Surfaces:** public site (`/`), admin (`/admin`), JSON API (`/api/v1`).

## Install and run

```bash
npm install
cp .env.example .env          # optional, but set your own admin credentials
npm run seed                  # create data/woodex-os.sqlite from content/seed/*.json
npm start                     # http://localhost:4321
```

### Sign in

| Surface | URL |
| --- | --- |
| Public site | `http://localhost:4321/` |
| Admin | `http://localhost:4321/admin` |
| API | `http://localhost:4321/api/v1/health` |

The seeded owner account (change it before going live):

```
email:    admin@woodexinterior.pk
password: WoodexStudio2026!
```

Lost the password? `npm run set-password -- --email <email>` prints a fresh one;
`npm run set-password -- --list` shows the accounts. Owners can also reset anyone
from **Users**, and everyone can change their own password at **My account**.

Seeding is idempotent: it inserts missing content and keeps your edits. `npm run seed --
--reset` clears the content tables first (accounts and settings survive).

### Environment

| Variable | Default | Notes |
| --- | --- | --- |
| `PORT` | `4321` | |
| `HOST` | `0.0.0.0` | |
| `WOODEX_DB` | `data/woodex-os.sqlite` | Keep it on persistent storage. |
| `WOODEX_BASE_URL` | `http://localhost:4321` | Used for absolute URLs in emails/exports. |
| `WOODEX_ASSETS_DIR` | `../woodex/assets` | The shared design system. |
| `WOODEX_UPLOAD_DIR` | `data/uploads` | Media uploads. |
| `WOODEX_SECURE_COOKIES` | `1` in production | Requires HTTPS. |
| `WOODEX_TRUST_PROXY` | on | Set `0` when not behind a proxy. |
| `WOODEX_MAX_UPLOAD` | `5242880` | Bytes per upload. |
| `WOODEX_LOGIN_MAX` | `8` | Failed logins per window per IP (raise it to ~200 when running the admin check repeatedly). |
| `WOODEX_ENQUIRY_MAX` | `5` | Enquiries per window per IP. |
| `WOODEX_RATE_WINDOW_MS` | `600000` | Window for both limiters. |
| `WOODEX_ADMIN_EMAIL` / `WOODEX_ADMIN_PASSWORD` | — | Used by the seeder only. |

## Admin

`/admin/login` → sign in with an owner, editor or viewer account.

| Group | Screens |
| --- | --- |
| Website | Dashboard · Pages · Navigation · Media library · Settings |
| Content | Projects · Insights · Services · Enquiries |
| System | Users · API tokens · Activity log · Health & QA · Export · My account |

- **Pages** carry a title, slug, template, hero, ordered content blocks (hero, page hero,
  rich text, image + text, cards, service cards, project grid, journal grid, process
  steps, accordion, FAQ, stats, CTA, contact form), SEO fields and revisions with restore.
  The block editor (`src/public/admin/blocks-editor.js`) writes the same JSON the API uses.
- **Roles:** owner (everything), editor (content, media, enquiries), viewer (read-only).
  Capabilities are checked server-side on every route; the sidebar hides what a role
  cannot use.
- **Settings** covers identity, contact details, opening hours, social links, SEO
  defaults, theme tokens, form behaviour and the export target.
- **Health** flags missing SEO fields, images without alt text, draft content and a
  writable-folder check before you publish.

## API

Base path `/api/v1`. JSON in, JSON out, `{ data, meta }` for lists and
`{ error: { code, message, details? } }` for failures. Full reference:
[`../docs/03-BACKEND-API.md`](../docs/03-BACKEND-API.md).

```bash
curl -s localhost:4321/api/v1/pages | jq '.data[].slug'
curl -s -X POST localhost:4321/api/v1/session \
  -H 'content-type: application/json' \
  -d '{"email":"you@example.com","password":"…"}' -c jar.txt
curl -s -X POST localhost:4321/api/v1/admin/pages -b jar.txt \
  -H "x-csrf-token: $(jq -r .data.csrf <(curl -s -b jar.txt localhost:4321/api/v1/session))" \
  -H 'content-type: application/json' -d '{"title":"New page","slug":"new-page"}'
```

API tokens (`/admin/tokens`, `wox_` + 48 hex) authenticate with
`Authorization: Bearer …` and are limited to `content:read`, `content:write` or
`enquiries:read`. Only the SHA-256 hash is stored.

## Static export

```bash
npm run export                     # -> dist/
npm run export -- --out ../public  # custom target
npm run export -- --with-qa        # export, then crawl the result
```

The exporter boots the real Express app on an ephemeral port and crawls it, so the HTML
matches the dynamic site exactly. It writes published pages only, directory-style URLs,
`sitemap.xml`, `robots.txt`, `feed.xml`, `site.webmanifest`, a 404 page, the design
assets, uploads and a hardened `.htaccess`.

## Tests and QA

```bash
npm test                    # 101 tests across public site, API, admin, security, export
npm run admin:check         # 24 end-to-end checks through the real admin forms
npm run qa:site             # crawl http://localhost:4321
npm run qa:site -- --dir dist --label "export"
npm run qa:site -- --dir ../woodex --label "v1 site"
```

`npm run admin:check` drives the admin like a browser: it signs in, walks all 18
screens, creates/edits/publishes/restores a page, creates a project, insight,
service, upload, nav item and enquiry, checks role enforcement and token
revocation, changes and restores the password, runs the export, then removes
every `qa-` record it created. It exits non-zero on any failure.

The QA crawler checks: HTTP status (including every internal link), `<title>` and meta
description presence/length and duplicates, canonical, Open Graph/Twitter, JSON-LD
validity, `lang`/viewport, skip link, single `h1` and heading order, image `alt` and
intrinsic dimensions, button `type`, duplicate ids, form labels, inline-script XSS
smells and response security headers. It exits non-zero on any error, so it can gate a
deploy.

## Operational notes

- **Backups:** copy `data/woodex-os.sqlite` and `data/uploads/` (SQLite is a single file;
  stop the process or use `sqlite3 .backup` for a consistent copy).
- **Email:** enquiries are stored and shown in the admin; outbound notification email is
  not wired to an SMTP provider yet (see the completion plan).
- **Rate limits** are per-process in memory. If you run multiple instances, move them to
  a shared store.
- **Sessions** live in SQLite and are revoked on logout; the cookie is `HttpOnly`,
  `SameSite=Lax` and `Secure` in production.
- **Uploads** accept JPEG, PNG, WebP, AVIF and GIF up to the configured limit, are
  renamed on disk and served from `/uploads`.

## Layout

```
server.js              boot + graceful shutdown
src/app.js             app factory, config, headers, 404/500
src/schema.sql         tables and indexes
src/db.js              node:sqlite wrapper
src/lib/               auth, http, validation, content, blocks, seo, media, exporter, audit
src/routes/            site.js, admin.js, api.js
src/views/site/        55 templates: shell, partials, 14 block partials, page templates
src/views/admin/       admin screens
src/public/admin/      admin.css + blocks-editor.js
content/seed/          the current site content as JSON
scripts/               seed.js, set-password.js, admin-check.js, export-static.js,
                       qa-site.js, patch-legacy-site.js
tests/                 node:test suites and fixtures
```
