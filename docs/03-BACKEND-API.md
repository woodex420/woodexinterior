# Woodex OS — backend, admin and API reference

**Version:** 2.1.0 · **Stack:** Node 22 · Express 4 · EJS · `node:sqlite` · `multer`
**Surfaces:** public site `/` · admin `/admin` · JSON API `/api/v1`

## 1. Architecture

```
browser ──► Express (src/app.js)
              ├── /            site router      → EJS shell + block partials (public HTML)
              ├── /admin       admin router     → session cookie + CSRF, EJS screens
              ├── /api/v1      api router       → session cookie or Bearer token, JSON
              ├── /uploads     uploaded media   → static, immutable cache
              └── /assets      design system    → ../woodex/assets (shared with v1)

data/woodex-os.sqlite (WAL)          data/uploads/
        │                                 │
        └── src/lib/content.js  ◄── admin, API and the static exporter all use the
                                   same model functions, so rules never diverge.
```

There is no ORM, no migration framework and no external service. `src/schema.sql` is
executed on every boot (`CREATE TABLE IF NOT EXISTS`), so the database is created from
scratch by the seeder and can be inspected with any SQLite client.

**Why SQLite:** the site has one writer (the studio), a few thousand rows and a Hostinger
target. A single file is trivially backed up and keeps the deployment to one Node process.

## 2. Data model

| Table | Purpose | Notable columns |
| --- | --- | --- |
| `users` | Admin accounts | `email` (unique), `password_hash` (scrypt), `role` (`owner`/`editor`/`viewer`), `status` |
| `sessions` | Server-side sessions | `id` (random), `csrf`, `expires_at`, `ip`, `user_agent` |
| `api_tokens` | Bearer tokens | `token_hash` (SHA-256 of `wox_…`), `scopes`, `revoked_at`, `last_used_at` |
| `pages` | Any page, including legal/system pages | `slug`, `page_type` (`home`/`page`/`legal`/`system`), `template`, `status`, `blocks` (JSON), per-page SEO fields, `noindex` |
| `page_revisions` | Snapshot taken before each update | `snapshot` (JSON), `created_by`, `note` |
| `projects` | Portfolio case studies | `category`, `location`, `year`, `area`, `body`, `gallery` (JSON), `facts` (JSON), `featured` |
| `insights` | Journal articles | `category`, `reading_time`, `excerpt`, `body`, `published_at` |
| `services` | Service offering | `number`, `summary`, `body`, `deliverables` (JSON) |
| `media` | Uploaded files | `filename` (randomised), `original_name`, `mime`, `size`, `alt`, `caption` |
| `enquiries` | Contact-form leads | `name`, `email`, `phone`, `location`, `service`, `timeline`, `message`, `status`, `notes`, `source`, `ip` |
| `nav_items` | Navigation | `label`, `href`, `area`, `sort_order`, `visible` |
| `settings` | Key/value site config | Site identity, contact details, social links, SEO defaults, theme tokens, form behaviour |
| `activity_log` | Audit trail | `user_email`, `action`, `entity`, `entity_id`, `ip`, `meta` |
| `export_runs` | Static export history | `target`, `pages`, `files`, `status`, timestamps |

Page blocks are validated and normalised in `src/lib/blocks.js`; the supported types are
`hero`, `pageHero`, `richText`, `imageText`, `serviceCards`, `projectGrid`, `journalGrid`,
`processSteps`, `accordion`, `faq`, `cardGrid`, `cta`, `stats`, `contactForm`.
HTML inside blocks, project bodies, insight bodies and service bodies is sanitised on
write; the renderer additionally relies on EJS escaping for every scalar.

## 3. Authentication and authorisation

- **Passwords:** scrypt with per-password salt (`scrypt$N$r$p$salt$hash`), verified with
  `timingSafeEqual`. Minimum 10 characters, rejected if it contains the email or is a
  known-weak string.
- **Sessions:** random 32-byte id in `woodex_sid` — `HttpOnly`, `SameSite=Lax`,
  `Secure` in production, 12-hour expiry, deleted on logout and on expiry.
- **CSRF:** every session carries a token. Admin forms send it as `name="_csrf"`; API
  writes send `x-csrf-token`. Bearer-token requests do not need it (no ambient cookie).
  The public contact form uses a double-submit cookie (`woodex_form`) so anonymous
  visitors can post without a session. Changing your own password rotates the session
  token and revokes every other session for that account.
- **Recovery:** `npm run set-password -- --email <address>` sets a password from the
  server (and revokes sessions); `npm run set-password -- --list` lists accounts. This is
  the only path that bypasses the UI, and it writes to the audit log.
- **Roles and capabilities:**

| Capability | owner | editor | viewer |
| --- | --- | --- | --- |
| `content:read` | ✓ | ✓ | ✓ |
| `content:write` | ✓ | ✓ | — |
| `media:write` | ✓ | ✓ | — |
| `enquiries:read` | ✓ | ✓ | ✓ |
| `enquiries:write` | ✓ | ✓ | — |
| `settings:write` | ✓ | — | — |
| `users:manage` | ✓ | — | — |
| `tokens:manage` | ✓ | — | — |
| `audit:read` | ✓ | — | — |
| `system:export` | ✓ | — | — |

Capabilities are enforced server-side per route (`authorize()` in the API, `guard()` in
the admin). The last remaining owner cannot be demoted or deleted (409).

## 4. API conventions

- Base path: `/api/v1`. JSON in/out; `content-type: application/json`.
- Single resources: `{ "data": { … } }`. Collections: `{ "data": [ … ], "meta": { … } }`.
- Pagination: `?page=1&per_page=20` (max 100) →
  `meta: { page, per_page, total, total_pages, has_more }`.
- Errors: `{ "error": { "code", "message", "details"? } }`.

| Status | `code` | When |
| --- | --- | --- |
| 400 | `bad_request` | Malformed JSON body |
| 401 | `unauthorized` / `invalid_credentials` | No/invalid session or token; wrong password |
| 403 | `forbidden` / `csrf_failed` | Role lacks the capability; missing/incorrect CSRF token |
| 404 | `not_found` | Unknown record, unpublished slug, unknown endpoint |
| 409 | `conflict` | Duplicate slug, deleting a system page, removing the last owner |
| 413 | `upload_failed` | Upload exceeds `WOODEX_MAX_UPLOAD` |
| 422 | `validation_failed` / `upload_failed` | Field errors in `details`, wrong MIME type |
| 429 | `rate_limited` | Login or enquiry limiter tripped (`Retry-After` set) |
| 500 | `internal_error` | Unhandled error (logged, never leaks a stack trace) |

### 4.1 Public endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/health` | Liveness + version |
| `GET` | `/settings` | Public subset of settings (name, contact, social, theme tokens) |
| `GET` | `/pages` | Published pages (summary fields) |
| `GET` | `/pages/:slug` | One published page + blocks |
| `GET` | `/projects` | Published projects, `?category=`, paginated |
| `GET` | `/projects/:slug` | One published project |
| `GET` | `/insights` | Published insights, paginated |
| `GET` | `/insights/:slug` | One published insight |
| `GET` | `/services` | Published services |
| `GET` | `/services/:slug` | One published service |
| `GET` | `/sitemap` | Sitemap entries (URLs, priority, changefreq) |
| `POST` | `/enquiries` | Create an enquiry (validated, honeypot, rate limited) |
| `POST` | `/session` | Sign in → sets cookie, returns user, capabilities and CSRF token |
| `GET` | `/session` | Current session/user |
| `DELETE` | `/session` | Sign out (needs CSRF when cookie-authenticated) |

Drafts never appear here: `status = 'published'` is enforced in the model layer.

### 4.2 Admin endpoints

Require a session cookie (with CSRF for writes) or a Bearer token with the right scope.

| Resource | Endpoints |
| --- | --- |
| Pages | `GET /admin/pages`, `POST /admin/pages`, `GET/PUT/DELETE /admin/pages/:id`, `GET /admin/pages/:id/revisions`, `POST /admin/pages/:id/revisions/:revisionId/restore` |
| Projects | `GET/POST /admin/projects`, `PUT/DELETE /admin/projects/:id` |
| Insights | `GET/POST /admin/insights`, `PUT/DELETE /admin/insights/:id` |
| Services | `GET/POST /admin/services`, `PUT/DELETE /admin/services/:id` |
| Media | `GET /admin/media`, `POST /admin/media` (multipart, field `file`), `PATCH/DELETE /admin/media/:id` |
| Enquiries | `GET /admin/enquiries`, `GET /admin/enquiries/export.csv`, `PATCH/DELETE /admin/enquiries/:id` |
| Settings | `PUT /admin/settings` |
| Users | `GET/POST /admin/users`, `PATCH/DELETE /admin/users/:id` |
| Tokens | `GET/POST /admin/tokens`, `DELETE /admin/tokens/:id` |
| System | `GET /admin/dashboard`, `GET /admin/activity`, `GET /admin/health`, `POST /admin/export` |

**Token scopes:** `content:read`, `content:write`, `enquiries:read`. A token is shown once
(`wox_` + 48 hex) and stored as SHA-256; revoking sets `revoked_at` and takes effect on the
next request.

### 4.3 Examples

```bash
# read published content
curl -s localhost:4321/api/v1/pages/about | jq '.data.title'

# sign in (cookie jar) and read the CSRF token
curl -s -c jar.txt -X POST localhost:4321/api/v1/session \
  -H 'content-type: application/json' \
  -d '{"email":"admin@example.com","password":"…"}' | jq -r '.data.csrf' > csrf.txt

# create a page
curl -s -b jar.txt -X POST localhost:4321/api/v1/admin/pages \
  -H "x-csrf-token: $(cat csrf.txt)" -H 'content-type: application/json' \
  -d '{"title":"Careers","slug":"careers","status":"draft","blocks":[{"type":"richText","html":"<p>Soon.</p>"}]}'

# token-authenticated read
curl -s -H "authorization: Bearer wox_…" localhost:4321/api/v1/admin/pages

# submit an enquiry
curl -s -X POST localhost:4321/api/v1/enquiries -H 'content-type: application/json' \
  -d '{"name":"Ayesha","email":"a@example.com","service":"residential","message":"We need help with our living room."}'
```

## 5. Admin workspace (HTML)

| Route | Screen | Capability |
| --- | --- | --- |
| `/admin/login` | Sign in (rate limited, open-redirect safe) | — |
| `/admin` | Dashboard: counts, drafts, recent enquiries, content health | `content:read` |
| `/admin/pages`, `/admin/pages/new`, `/admin/pages/:id` | List, create, edit blocks + SEO + revisions | `content:write` for writes |
| `/admin/projects`, `/admin/projects/new`, `/admin/projects/:id` | Portfolio | `content:write` |
| `/admin/insights`, `/admin/insights/new`, `/admin/insights/:id` | Journal | `content:write` |
| `/admin/services`, `/admin/services/new`, `/admin/services/:id` | Services | `content:write` |
| `/admin/media` | Upload, alt/caption editing, delete | `media:write` |
| `/admin/enquiries`, `/admin/enquiries/:id`, `/admin/enquiries/export.csv` | Inbox, status, notes, CSV | `enquiries:read` |
| `/admin/navigation` | Add/edit/reorder/hide nav items | `content:write` |
| `/admin/settings` | Identity, contact, social, SEO, theme, forms | `settings:write` |
| `/admin/users` | Create/edit/disable/reset/delete users | `users:manage` |
| `/admin/tokens` | Issue and revoke API tokens | `tokens:manage` |
| `/admin/activity` | Audit log | `audit:read` |
| `/admin/health` | Database, uploads, SEO and content checks, export button | `content:read` |
| `/admin/account` | Own profile (name, email) and password change | any signed-in user |
| `/admin/export` (POST) | Write the static export to `system.export_dir` | `system:export` |

Every write is CSRF-checked, validated, logged to `activity_log`, and (for pages)
preceded by a revision snapshot. Validation failures re-render the form at 422 with the
submitted values and field-level messages.

## 5.1 Admin error pages

Every admin screen — including 401, 403, 404, 409 and 500 — renders the branded shell.
The shared head partial defaults `active`, `user`, `roleLabels`, `flash`, `csrf`,
`newEnquiries`, `can` and `title` when a screen is rendered before the locals middleware
runs (for example the signed-out and CSRF failures). A failure to render an error page
can therefore never cascade into a second 500.

## 6. Public site rendering

- `/` home, `/:slug` pages, `/portfolio` + `/portfolio/:slug`, `/insights` +
  `/insights/:slug`, `/services`, `/contact` (GET form, POST enquiry), `/thank-you`,
  `/search`, `/preview/:slug` (login required), `/sitemap.xml`, `/robots.txt`,
  `/feed.xml`, `/site.webmanifest`, `/healthz`.
- Every page gets: unique title/description, canonical, robots directive, Open Graph and
  Twitter tags, JSON-LD (`InteriorDesignBusiness`, `WebSite`, `AboutPage`, `ContactPage`,
  `Service`, `CollectionPage`, `CreativeWork`, `Article`, `BreadcrumbList`, `FAQPage`),
  one `h1`, a skip link, intrinsic image dimensions and `alt` text.
- `<meta name="robots" content="noindex">` is applied automatically to drafts (preview),
  search results, the thank-you page, the 404 page and any page marked `noindex`.

## 7. Static export

`npm run export` (or `POST /admin/export`) boots the app on an ephemeral port, crawls the
published routes and writes:

```
dist/
  index.html  about/index.html  …
  portfolio/<slug>/index.html  insights/<slug>/index.html
  sitemap.xml  robots.txt  feed.xml  site.webmanifest  404.html
  assets/…  uploads/…  .htaccess  EXPORT-README.txt
```

Latest run: **24 routes, 47 files**, 0 crawl errors. The target folder comes from the
`system.export_dir` setting (default `dist`, resolved relative to the database folder). Directory-style URLs rely on
`DirectoryIndex`; `.htaccess` adds HTTPS, compression, caching, CSP, `Options -Indexes`,
dotfile denial and `/404.html`. The export contains published content only.

## 8. Security controls (implemented)

- Strict-ish CSP (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`),
  `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, no `X-Powered-By`.
- Parameterised SQL everywhere (`node:sqlite` prepared statements); no string-built queries
  with user input.
- HTML sanitiser on all rich-text fields; EJS auto-escaping elsewhere; JSON-LD `<` escaped.
- Uploads: MIME + extension allowlist, size cap, random filenames, `basename` on the
  original name, served from a directory with no script execution.
- Login and enquiry rate limits, generic credential errors, disabled-account checks,
  constant-time password compare, session revocation, audit logging of auth events.
- CSRF for all cookie-authenticated writes; tokens scoped and hashed at rest.
- Error pages never expose stack traces or SQL.

## 9. Known limitations

| Area | Limitation |
| --- | --- |
| Email | No SMTP/transactional provider; enquiries are stored only. |
| Images | No resizing/derivatives; the original file is served. |
| Limits | In-process rate limiting; single-instance cache-free design. |
| Concurrency | SQLite single writer (WAL enabled, retry not implemented). |
| Search | `LIKE`-based, not full-text (`FTS5` is available in `node:sqlite` if needed). |
| Scheduling | No timed publishing; `status` is manual. |
| Webhooks | None; a Next.js front-end would poll `/api/v1/pages` or use the export. |
| Logging | `activity_log` in the database; no external log sink or alerting. |
