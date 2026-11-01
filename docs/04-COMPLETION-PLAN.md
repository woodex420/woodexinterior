# Woodex OS — completion plan and pending tasks

**Date:** 26 October 2026 · **Baseline:** v2.1.0 in `woodex-os/` (89 tests passing)

## 1. Where the project actually stands

| Workstream | Before | Now |
| --- | --- | --- |
| Website (v1 static) | 18 pages, 52 crawl errors, demo-only contact form, one shared meta description | 0 crawl errors, unique metadata, canonical/OG/JSON-LD, hardened `.htaccess`; the OS is the new source of truth |
| CMS / content group | nothing editable, no collections, no media library | pages + blocks, projects, insights, services, media, navigation, settings |
| Backend admin | nothing | 16 screens, roles, CSRF, revisions, audit log, health checks |
| Backend API | nothing | public reads, token-authenticated admin CRUD, errors, pagination |
| Enquiries | form collected nothing | stored, validated, visible in an inbox, CSV export |
| SEO | partial, duplicated | per-page metadata, structured data, sitemap/robots/RSS from the database |
| Deployment | manual HTML upload | `npm run export` → 24 routes / 43 files for Apache |
| Quality | none | 89 tests + a SEO/a11y crawler with a non-zero exit code on errors |

## 2. Plan to finish (in order)

### Phase 1 — Go live safely (1–2 days)

| # | Task | Owner | Done when |
| --- | --- | --- | --- |
| 1.1 | Set production `WOODEX_ADMIN_EMAIL` / `WOODEX_ADMIN_PASSWORD`, reseed, enable `WOODEX_SECURE_COOKIES=1`, HTTPS | backend | Login works over HTTPS with a `Secure` cookie |
| 1.2 | Host the OS on a Node 22 host with a persistent disk (`data/`), or run it on the VPS that also serves the static export | ops | `/healthz` and `/admin` reachable on a real domain |
| 1.3 | Deploy `dist/` to `public_html`; confirm the exported `.htaccess` is active (404 page, HTTPS redirect, CSP header) | ops | `curl -I` shows CSP; unknown URL returns the branded 404 |
| 1.4 | Change the export form action to the OS host (`POST https://cms.<domain>/contact`) or keep the site non-static for `/contact` | backend | A test enquiry arrives in the inbox from the live site |
| 1.5 | Backups: nightly copy of `data/woodex-os.sqlite` + `data/uploads/`, 14-day retention | ops | A restore has been performed once, on purpose |
| 1.6 | Legal review of the privacy text (currently studio-written) and the enquiry consent sentence | content | Signed-off copy in `/admin/pages/privacy` |

### Phase 2 — Close the launch gaps (3–5 days)

| # | Task | Complexity | Notes |
| --- | --- | --- | --- |
| 2.1 | **Email notifications** (owner alert + visitor auto-reply) | Medium | Provider adapter (Resend/Postmark/SMTP), `email_outbox` table with attempts/backoff, templates, admin “last 20 emails” panel, test mode that never sends. |
| 2.2 | **Image pipeline** | Medium | On upload: store original + AVIF/WebP at 480/960/1440, generate `srcset` in templates, keep `alt` mandatory. Measure LCP before/after (target < 2.5 s on 4G). |
| 2.3 | **Redirect manager** | Low/Medium | `redirects` table, 301 middleware before the site router, `.htaccess`/export support, automatic suggestion when a page slug changes. |
| 2.4 | **CI** | Low | GitHub Actions: `npm test`, `npm run export`, `qa-site` crawl, fail on errors. |
| 2.5 | **Visual regression** | Medium | Playwright screenshots at 375/768/1440 for the 8 main templates, compared on PRs. |
| 2.6 | **Block editor browser test** | Low | Playwright: add, reorder, delete, save, reload, verify order in the DB. |

### Phase 3 — Finish the content group (1 week, mostly content work)

| # | Task | Complexity |
| --- | --- | --- |
| 3.1 | Replace concept projects with real case studies (or keep the honest “concept” label and add real work as it completes) | Content |
| 3.2 | Testimonials, team members and client logos: new tables, admin screens, two blocks | Medium |
| 3.3 | Reusable FAQ library and a blog taxonomy (categories, authors, related posts) | Medium |
| 3.4 | Signed preview links so clients can review a draft without an account | Low |
| 3.5 | SEO operations: Search Console verification, IndexNow/sitemap ping, a content-health dashboard with “what to fix next” | Low |

### Phase 4 — Scale and harden (2–3 weeks)

| # | Task | Complexity |
| --- | --- | --- |
| 4.1 | 2FA (TOTP) for owners and self-service password reset by email | Medium |
| 4.2 | Scheduled publishing (`publish_at`) and scheduled unpublishing | Low |
| 4.3 | Webhooks/events for a future front-end, plus a JSON content snapshot endpoint | Low |
| 4.4 | Move rate limits and sessions to a shared store if more than one app instance is needed | Medium |
| 4.5 | Full-text search (`FTS5`) instead of `LIKE` | Low/Medium |
| 4.6 | Multilingual content (English/Urdu) with per-locale slugs | High |
| 4.7 | Replace raw SQLite file copies with a scheduled `.backup` and an off-site copy | Low |

## 3. Pending backend/API work (short list)

1. Email outbox + provider adapter (`B1`).
2. Redirects table and middleware (`B4`).
3. Password reset and 2FA (`B2`).
4. Media derivatives and `srcset` (`B3`).
5. `publish_at` scheduling (`B5`).
6. Webhooks (`B6`).
7. Backup/restore script and a documented drill (`B9`).
8. CI pipeline (`B10`).
9. API versioning policy: keep `/v1` additive; deprecate with a response header.
10. OpenAPI document generated from the routes (nice-to-have for external integrations).

## 4. Complex fixes (need design decisions, not just code)

| Fix | Why it is complex | Decision needed |
| --- | --- | --- |
| **Static site + real form** | A pure export cannot receive POSTs. Either the OS is hosted (leads stored in SQLite) or the form posts to a hosted endpoint/third-party. | Will Woodex run a Node host at all, or should forms go to a form service? |
| **Image pipeline** | Needs a native dependency (sharp), a storage layout for derivatives and a migration for existing media; the exporter must copy derivatives too. | Accept a native dep, or pre-process in a separate step? |
| **Email deliverability** | Requires a sending domain, SPF/DKIM/DMARC, bounce handling and unsubscribe semantics, not just an API key. | Which domain sends (`@woodexinterior.pk`?) and who owns DNS? |
| **Multilingual content** | Touches schema (per-locale rows or JSON columns), routing, hreflang, sitemap and the admin UI. | Is Urdu required for launch, or later? |
| **Real content** | Legal accuracy for privacy, authenticity of projects/testimonials, photography rights. | Who signs off the copy and the photo licences? |

## 5. Launch checklist (copy into the ops runbook)

- [ ] Production credentials set; seeder run once; demo accounts absent
- [ ] `WOODEX_SECURE_COOKIES=1`, HTTPS enforced, HSTS enabled after confirming every subdomain
- [ ] `/admin` reachable but not indexed (`robots.txt` + noindex)
- [ ] `npm test` green; `npm run qa:site` 0 errors on the live URL
- [ ] Export uploaded; `/`, `/about`, `/portfolio/<slug>`, `/insights/<slug>`, `/contact`, a 404 and `sitemap.xml` spot-checked on the live domain
- [ ] Test enquiry submitted from the live form and seen in `/admin/enquiries`
- [ ] Backup taken and a restore rehearsed
- [ ] Google Search Console verified; sitemap submitted
- [ ] Old URLs → new URLs redirect table reviewed (after the export replaces v1)
- [ ] Owner account kept in a password manager; editor accounts created for the team
- [ ] `woodex/` (v1) either removed or explicitly kept as a fallback; one source of truth documented

## 6. Definition of done for “Woodex OS complete”

1. Every visible string on the site is editable in `/admin` (done, except legal review).
2. A lead reaches a human by email without opening the dashboard (pending 2.1).
3. Mobile pages stay under 1 MB with LCP < 2.5 s on 4G (pending 2.2).
4. Every URL has one canonical, unique metadata and valid structured data (done).
5. Every change is reversible: revisions (done), redirects (pending), backups (pending).
6. Deploys are gated by tests and the QA crawl (tests/crawl done, CI pending 2.4).
7. Two people can work at once without stepping on each other (done: roles, audit log,
   revisions) and nobody can lock the studio out (done: last-owner guard; 2FA pending).
