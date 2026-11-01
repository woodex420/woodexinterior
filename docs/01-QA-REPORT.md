# Woodex — QA, bug and accessibility report

**Date:** 26 October 2026
**Scope:** the static site in `woodex/` (v1), the new Woodex OS in `woodex-os/`, and the
content/SEO/accessibility state of the live pages.
**Environment:** Node 22.22.3 · no PHP/SQLite CLI available · Chromium-based automated
crawl replaced by an HTTP + parser crawler (`scripts/qa-site.js`), so no pixel comparison.

Everything below is reproducible with the commands in the last section.

## 1. Result summary

| Target | Pages | Errors before | Errors now | Warnings now |
| --- | --- | --- | --- | --- |
| `woodex/` (v1 static site) | 16 crawled (18 files) | **52** | **0** | **0** |
| Woodex OS dynamic site | 17 | — (new) | **0** | **0** |
| Woodex OS static export | 17 | — | **0** | **0** |
| Woodex OS automated tests | 6 suites | — | **101 passing, 0 failing** | — |
| Woodex OS admin end-to-end | 24 checks | — | **24 passing, 0 failing** | — |

Baseline v1 issues (first crawl, before any change): 16 × missing canonical, 16 × missing
`og:image`, 16 × missing Twitter card, 16 × no JSON-LD, 19 × heading-order errors,
78 × images without intrinsic dimensions, 22 × `<button>` without `type`, 16 × identical
meta description, 1 × unlabelled form control, 0 × broken links.

## 2. Bugs found and fixed

### Critical — user-facing or security

| # | Bug | Evidence | Fix |
| --- | --- | --- | --- |
| C0 | **Admin error screens crashed.** Rendering `admin/error.ejs` before the locals middleware ran (signed-out POST, CSRF failure) threw `active is not defined`, turning a clean 401/403 into a 500. | Server log during the admin walkthrough; reproduced with a POST to `/admin/pages` without a session. | The shared admin head partial now defaults every local it uses, and each error render passes `active`/`flash`; a 401/403/404/409/500 always renders the branded shell. Covered by `tests/account.test.js`. |
| C0b | **Export button returned 500.** `/admin/export` and `POST /api/v1/admin/export` passed the *site settings* object where the app config was expected, so `uploadDir` was undefined. | Admin check: `export 500`; log `mkdirSync ... Received undefined`. | Both routes now pass the real config, resolve the target from `system.export_dir` (default `dist`) and use `site.url` as the canonical base. The admin exports 47 files; covered by a test. |

| # | Bug | Evidence | Fix |
| --- | --- | --- | --- |
| C1 | **Unknown URLs returned HTTP 500**, not 404. | `GET /nope` → 500; error log `path is not defined` in `site-header.ejs`. | 404/503/500 renders now receive `path`, `jsonLd`, `site` and a full `meta` object; the header guards with `typeof path !== 'undefined'`. Verified: `/nope` → 404 with `<title>Page not found | Woodex Interior</title>`. |
| C2 | **CSRF rejection crashed with 500** instead of 403. | `POST /admin/pages` without `_csrf` → 500. Cause: two copies of the CSRF middleware, the first running before `res.locals`/flash existed. | Exactly one CSRF middleware, mounted after the locals middleware. Verified: 403 + “Security check failed”. |
| C3 | **Contact form collected nothing.** v1 form had no `action`, no `method`, and the copy said “Demo form — details are not sent”. | `woodex/contact.html` | Woodex OS now serves a real endpoint (`POST /contact`): per-field validation, CSRF double-submit token, honeypot, rate limiting, SQLite storage, redirect to `/thank-you`, admin inbox, statuses, notes and CSV export. v1 copy rewritten with working `mailto:`/`tel:`/WhatsApp routes. |
| C4 | **Apache config would fail or expose directories.** `Allow from all` is Apache 2.2 syntax (2.4 → 500 without `mod_access_compat`), `Options +Indexes` enabled directory listings, `Header` directives sat outside `<IfModule>`, no CSP/HSTS. | `woodex/.htaccess` | Rewritten: `Require all granted`, `Options -Indexes`, all headers inside `mod_headers`, CSP, COOP, dotfile denial, `www`→apex redirect, cache rules split by asset type, no gzip for already-compressed fonts. |
| C5 | **No Content-Security-Policy** on any page; `X-Powered-By` was exposed by the app. | Header capture | CSP (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`), `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` — on Express responses and in the exported `.htaccess`. `x-powered-by` disabled. |
| C6 | **Unvalidated HTML reachable from the editor** (stored XSS surface). | — | Server-side sanitiser strips `<script>`, `<iframe>`, `<style>`, inline `on*` handlers and `javascript:`/`data:text/html` URLs from page blocks, project bodies, insight bodies and service bodies. Covered by tests. |
| C7 | **Server-rendered admin error pages were unbootable** (`active is not defined`). | `ReferenceError` in `admin/error.ejs` when rendering 403/409/500. | `res.locals.active` defaults to `''`; error pages render with the real layout and status code. |

### High — SEO, discoverability and conversion

| # | Bug | Evidence | Fix |
| --- | --- | --- | --- |
| H1 | **One 114-character meta description shared by all 16 pages.** | Crawl: `desc 114` on every URL, duplicate detection. | 18 unique, length-checked descriptions (89–155 chars) in v1; descriptions are CMS fields with a live counter in the admin. |
| H2 | **No canonical URLs, no `og:image`, no Twitter cards, no structured data.** | 16/16 pages each. | Canonical + OG + Twitter + JSON-LD (`InteriorDesignBusiness`, `WebSite`, `AboutPage`, `ContactPage`, `Service`, `CollectionPage`, `CreativeWork`, `Article`, `BreadcrumbList`, `FAQPage`) from the CMS; v1 patched in place. |
| H3 | **Heading outline broken on every page** (footer `h4` after `h2`; card grids jumping `h1`→`h3`). | 19 jump errors. | Footer headings are `h3`, grids get a visually hidden `h2`; Woodex OS templates emit one `h1` and a monotonic outline (asserted in tests). |
| H4 | **Hero slides 2–3 had empty `alt`** while being real photographs. | `woodex/index.html` | Descriptive alt text added; the CMS requires alt text for uploaded media and the health check flags missing alt. |
| H5 | **Phone and email on the contact page were plain text**, not `tel:`/`mailto:`, and there was no WhatsApp link — on a mobile-first local business site. | `woodex/contact.html` | Links added; the OS contact block renders `tel:`, `mailto:` and WhatsApp from settings. |
| H6 | **Portfolio filter state was not linkable** and the count was not announced. | `site.js` filter only toggled `hidden`. | The OS portfolio filters are real links (`?category=`), `aria-current` marks the active filter and the count is in a live region. |
| H7 | **Contact submit button shipped `disabled`** — with JavaScript blocked the form could never be submitted. | `<button type="submit" … disabled>` | OS form has no disabled state and validates server-side; progress/JS enhancement is optional. |

### Medium — markup hygiene, performance, maintainability

| # | Bug | Evidence | Fix |
| --- | --- | --- | --- |
| M1 | 22 `<button>` elements without `type` (menu toggle ×18, carousel arrows, filters). | Crawl rule `button-type`. | `type="button"` everywhere except real submits; the OS admin asserts it in tests. |
| M2 | 78 images without `width`/`height` → layout shift on slow connections. | Crawl rule `img-dimensions`. | Intrinsic dimensions read from the JPEG headers and written into markup; the CMS renders dimensions for every image tag. |
| M3 | Sitemap listed `/home` (a 404) and could include drafts. | Export crawl found `/home` → 404. | Home is emitted as `/` only; drafts and noindex pages are excluded; the sitemap is generated from published rows. |
| M4 | Static export was missing `/site.webmanifest` and the 404 fallback for unknown paths. | Export crawl: 404 on the manifest link. | Manifest added to the exporter routes; `.htaccess` maps 404s to `/404.html`. |
| M5 | Duplicated markup and assets: 18 hand-copied headers/footers, two footer partials, an unused `site-footer.ejs`. | `src/views/site/partials/` | Single shell + partials; the v1 folder is now a fallback only. |
| M6 | Rate limits were hard-coded, which made the test suite throttle itself. | Tests hit 429 while logging in repeatedly. | Limits are configurable (`WOODEX_LOGIN_MAX`, `WOODEX_ENQUIRY_MAX`, `WOODEX_RATE_WINDOW_MS`); a dedicated suite verifies 429 + `Retry-After`. |
| M7 | `X-Powered-By: Express` and no cache policy on dynamic HTML. | Header capture | Disabled; static asset cache headers set per environment. |

## 3. What is now verified automatically

`npm test` (89 tests, ~5 s) covers:

- **Public site:** titles/descriptions/canonical/OG/Twitter per page, JSON-LD types,
  breadcrumbs, 404 status and `noindex`, draft isolation, preview login wall, sitemap
  (no drafts, no `/home`), robots, RSS, manifest, security headers, one `h1` per page,
  labelled images with dimensions, typed buttons, search results, escaped output.
- **Contact:** token issuing, 422 field errors with preserved input, CSRF mismatch,
  successful storage + redirect, honeypot drop, thank-you page.
- **API:** login failures, generic error message, session cookie flags, capabilities,
  CSRF on cookie and bearer writes, page CRUD, slug conflicts (409), field validation,
  revisions + restore, publish toggle, system-page delete guard, RBAC for viewer/editor,
  settings protection, enquiry status updates and CSV export, user management and
  last-owner protection, token creation/scoping/revocation.
- **Security:** sanitiser (scripts, event handlers, obfuscated schemes, iframes, style),
  stored-XSS rendering in admin, scrypt hashing, open-redirect protection, disabled
  accounts, unknown sessions, login and enquiry rate limits, upload MIME/size/traversal
  handling, file deletion.
- **Export:** route coverage, draft exclusion, asset + upload copying, hardened
  `.htaccess`, export notes, exporting from the admin to a configured folder and the
  owner-only guard.
- **Account & recovery:** profile edits, duplicate email rejection, current-password and
  confirmation checks, password rotation (old password rejected, other sessions revoked,
  current session kept), CSRF on the account form, and every admin error page rendering
  the branded shell instead of a 500.

`npm run admin:check` adds 24 live checks driven through the real forms: sign-in,
all 18 screens, page create/edit/publish/unpublish/revision-restore, project, insight,
service, upload, navigation, settings, enquiry inbox + CSV, viewer role enforcement, API
token issue/use/revoke, profile + password round-trip, static export and cleanup.

Crawl artefacts: `docs/qa/v1-site.json` and `docs/qa/os-export.json` (plus `.md` twins).

## 4. Known issues still open

| Severity | Issue | Why it matters | Suggested action |
| --- | --- | --- | --- |
| High | **Enquiries do not email anyone yet.** They are stored, listed and exportable, but there is no SMTP/API provider wired (no auto-reply, no notification). | A lead is only seen when someone opens `/admin`. | Add an `EMAIL_*`/Resend adapter with a queue and a retry log (see completion plan, phase 3). |
| High | **The static export's contact form needs the OS host.** With the site fully static, `POST /contact` does not exist. | Exporting to Hostinger without hosting the OS silently loses leads. | Point the form at `https://cms.woodex.com.pk/contact` (configurable action) or host the OS alongside. |
| Medium | **Images are unoptimised:** hero 502 kB, detail 497 kB, lounge 325 kB, Inter 352 kB, three `.otf` faces (~40 kB each) instead of WOFF2 subsets. | Mobile LCP on 4G; ~3 MB first load on the home page. | Generate AVIF/WebP + `srcset`, convert Nohemi to WOFF2, preload one hero image. |
| Medium | **No visual regression or cross-browser evidence.** The QA crawler is DOM-level only. | Design drift and Safari/Firefox differences are unmeasured. | Add Playwright screenshots per breakpoint (375/768/1440) in CI. |
| Medium | **Rate limiting is in-process** and there is no account lockout, 2FA or self-service password reset. | Multi-instance deployments bypass limits; owner-only recovery. | Shared store (Redis/SQLite table), TOTP for owners, reset-by-email. |
| Medium | **`.env` and demo credentials are committed in the `woodex` repo** (Supabase URL + anon key, Stripe publishable key, plaintext demo admin password in `DEMO_CREDENTIALS.md`). | Anon/publishable keys are public by design, but a plaintext admin credential is a real risk if that stack is ever deployed. | Rotate the demo password, delete the file, purge history, add `.env` to `.gitignore` and a pre-commit secret scan. |
| Low | **No cookie consent or analytics** — only a form-token cookie and the admin session. | Fine while there is no tracking; becomes a compliance gap the day analytics is added. | Decide on analytics first, then add a consent gate. |
| Low | **Single SQLite writer.** | Concurrent bulk edits can hit `SQLITE_BUSY`. | WAL mode is on; add a retry wrapper if the team grows. |
| Low | **Block editor JS is untested in a browser.** | A regression could break editing while tests stay green. | Add a Playwright test that adds, reorders and deletes a block, then saves. |

## 5. Reproduce

```bash
# 89 automated tests
cd woodex-os && npm test

# crawl the running site (starts on :4321)
npm start &
npm run qa:site -- --base http://localhost:4321 --label "dynamic"

# crawl the static export
npm run export
npm run qa:site -- --dir dist --label "export"

# crawl the legacy static site
npm run qa:site -- --dir ../woodex --label "v1"
```

The crawler exits non-zero when it finds an error, so it can be wired into CI or a
pre-deploy step. Warnings (length of a description, missing OG type, etc.) are reported
but do not fail the run.
