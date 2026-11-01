# Woodex — gap report: what exists, what is missing, what is not in Woodex OS yet

**Date:** 26 October 2026
**Question this answers:** “Woodex OS basically has nothing — the backend admin/API, the
frontend website v1, the website CMS/content group are not complete. Show me exactly
what is missing, what is not updated into the Woodex OS, which backend/API work is
still pending and which fixes are complex.”

## 1. The four repositories, as they actually are

| Repository | What is in it (verified) | Verdict |
| --- | --- | --- |
| `woodex420/woodexinterior` (this repo) | 18 hand-written HTML pages, `theme.css` (one 12-line design system), `site.js` (~6 kB of carousel/menu/filter code), 9 JPEGs, three OTF + one WOFF2 font, `.htaccess`, sitemap, robots, privacy text and a style guide. No build step, no CMS, no backend, no API, no forms endpoint. | v1 website: complete as a **static brochure**, not as a managed site. |
| `woodex420/woodex-os` | A README containing the words “complate master plan”. Nothing else. | Empty placeholder. **Now implemented inside this repository** as `woodex-os/`. |
| `woodex420/woodex-2030` | A README only. Two `arena/*` branches, no application code on the default branch. | Idea/notes only. |
| `woodex420/agent.woodex` | Next 16 / React 19 / Sanity 5 / Tailwind 4 marketing site with a `/studio` Sanity path. `SETUP-CMS.md` lists the Sanity project/dataset/tokens, revalidation secret, Supabase, HubSpot and Resend variables that must be supplied. `TODO.md` still lists JSON-LD per page, unique metadata, portfolio chips, sticky mobile CTA and 3D pricing. | A different stack, **not configured** (needs an external Sanity project + keys), and its own TODO says the SEO work is unfinished. |
| `woodex420/woodex` | A 57 MB legacy monorepo: storefront, MPA, ecommerce, an admin dashboard, Supabase migrations, dozens of status/report markdown files. Commits a root `.env` (Supabase URL, anon key, Stripe publishable key) and `DEMO_CREDENTIALS.md` with a plaintext demo admin password. | Legacy; not the website's CMS. Security cleanup required (see §5). |

## 2. What Woodex OS v2.1 now covers

| Area | State | Where |
| --- | --- | --- |
| Content model | Pages (blocks), projects, insights, services, media, enquiries, nav, settings | `src/schema.sql`, `src/lib/content.js` |
| Admin UI | 16 screens in three groups: **Website** (dashboard, pages, navigation, media, settings), **Content** (projects, insights, services, enquiries), **System** (users, tokens, activity, health, export) | `src/views/admin/`, `src/routes/admin.js` |
| Block editor | 14 block types with add / reorder / delete and schema-driven fields | `src/public/admin/blocks-editor.js`, `src/lib/blocks.js` |
| Auth & roles | scrypt passwords, SQLite sessions, CSRF, owner/editor/viewer capabilities | `src/lib/auth.js` |
| REST API | Public reads + token-authenticated admin CRUD, pagination, consistent errors | `src/routes/api.js` |
| Enquiries | Real form endpoint, validation, honeypot, rate limit, inbox with status/notes, CSV | `src/routes/site.js`, admin |
| SEO | Unique metadata, canonical, OG/Twitter, JSON-LD, sitemap, robots, RSS, per-page `noindex` | `src/lib/seo.js`, `src/lib/content.js` |
| Media | Upload validation, random filenames, alt text, delete-with-file | `src/lib/media.js` |
| Static export | Published-only HTML + assets + hardened Apache config | `src/lib/exporter.js` |
| Quality gates | 89 tests + SEO/a11y/HTML crawler | `tests/`, `scripts/qa-site.js` |

## 3. What is still **not** in Woodex OS

These are real gaps, not polish. They are ordered by impact.

### 3.1 Backend / API work still pending

| # | Gap | Consequence | Complexity |
| --- | --- | --- | --- |
| B1 | **No outbound email.** `enquiry.auto_reply` / `enquiry.notify_email` settings exist but nothing sends. | Leads are only visible in the admin; no acknowledgement to the visitor. | Medium — needs a provider (Resend/Postmark/SMTP), a queue table with retries, templates and an unsubscribe trail. |
| B2 | **No self-service password reset / 2FA.** Owners can reset another user's password in `/admin/users`. | Locked-out owner needs server access; owner accounts are single-factor. | Medium. |
| B3 | **No media image processing.** Uploads are stored byte-for-byte; no thumbnail, crop, AVIF/WebP conversion or `srcset`. | Editors must pre-optimise images by hand; page weight stays high. | Medium — `sharp` or a build-time step, plus derived-file naming. |
| B4 | **No redirect manager.** Slugs can be edited, and old URLs then 404. | SEO losses on every rename. | Low/Medium — a `redirects` table + 301 middleware + export rule. |
| B5 | **No content scheduling.** A page is published or draft, not “publish at 09:00”. | Manual publishing at odd hours. | Low — `publish_at` + a filter in queries. |
| B6 | **No webhooks / events.** Nothing tells a future frontend or automation that content changed. | A Next.js front-end would need polling. | Low. |
| B7 | **No audit-log UI filtering or export**, and no per-record “who changed this” diff (revisions store snapshots without a compare view). | Investigations are manual. | Low. |
| B8 | **Rate limiting and sessions are single-process.** | Multiple app instances share nothing. | Low/Medium. |
| B9 | **No backups / restore tooling** beyond copying the SQLite file. | Data loss risk on the host. | Low — a `npm run backup` script + retention policy. |
| B10 | **No CI.** Tests and the QA crawl exist but nothing runs them on push. | Regressions reach production silently. | Low — GitHub Actions (2 jobs). |

### 3.2 Website (front-end) work still pending

| # | Gap | Consequence | Complexity |
| --- | --- | --- | --- |
| F1 | **Image pipeline.** Hero 502 kB, detail 497 kB, lounge 325 kB; fonts are 352 kB (Inter) + 3 × 40 kB OTF. | Mobile LCP is dominated by the hero; ~3 MB first load. | Medium — AVIF/WebP + `srcset`/`sizes`, WOFF2 subsets, one preloaded hero. |
| F2 | **The exported static site cannot receive form posts.** | Leads lost unless the OS is hosted too. | Low — point the form at the OS URL (config) or embed a hosted endpoint. |
| F3 | **No animation/JS budget.** One 6 kB script, no framework — good — but the carousel has no `prefers-reduced-motion` branch for the fade. | Accessibility preference ignored. | Low. |
| F4 | **No visual regression or device testing.** | Design drift and Safari/Firefox bugs are unmeasured. | Medium — Playwright screenshots at 375/768/1440. |
| F5 | **No 360°/3D or video** (listed as a future ambition in the old plans). | Not a launch blocker. | High. |
| F6 | **No analytics, consent banner or conversion tracking.** | No data on enquiries/abandonment; becomes a compliance gap once tracking is added. | Low/Medium (decision first). |
| F7 | **v1 folder still exists** as a second copy of the same pages. | Two sources of truth until the export replaces it. | Low — delete/redirect after go-live. |

### 3.3 CMS / “content group” work still pending

| # | Gap | Consequence | Complexity |
| --- | --- | --- | --- |
| C1 | **Content is still concept copy.** All six projects are labelled sample concepts with illustrative photography; privacy was placeholder text. | The site cannot claim real work; it must keep the honest “concept” labelling until real case studies exist. | Content work, not code. |
| C2 | **No testimonial, team, awards or client-logo content types.** | The about/project pages cannot show social proof. | Low — new tables + admin screens + blocks. |
| C3 | **No FAQ library** — FAQs exist only as a block inside a page. | Duplicated answers, no reuse across pages. | Low. |
| C4 | **No blog taxonomy** (categories/tags/authors) beyond a single category string. | Filtering and related content stay manual. | Medium. |
| C5 | **No draft preview links for external review** (preview requires an admin login). | Clients cannot review a page without an account. | Low — signed preview tokens with expiry. |
| C6 | **No content import from the old site** beyond the one-off seed script. | Future migrations are manual. | Low/Medium. |
| C7 | **No multilingual content (Urdu/English).** | Limits reach for a Lahore studio. | High. |
| C8 | **No sitemap ping / IndexNow / Search Console integration.** | Slower discovery of new pages. | Low. |

## 4. “Website group” vs “Content group” — completeness

| Requirement | v1 static site | Woodex OS |
| --- | --- | --- |
| Pages editable without code | ✗ | ✓ pages + blocks + revisions |
| Projects / insights / services as collections | ✗ (hard-coded HTML) | ✓ |
| Navigation editable | ✗ | ✓ |
| Media library with alt text | ✗ | ✓ (no image derivatives yet) |
| Contact form that stores leads | ✗ demo only | ✓ inbox + CSV |
| Per-page SEO + structured data | ✗ shared description | ✓ |
| Sitemap / robots / RSS generated | static files | ✓ generated |
| Users, roles, audit trail | ✗ | ✓ |
| API for content | ✗ | ✓ (tokens, scopes) |
| Static deployment to Hostinger | ✓ | ✓ (export) |
| Email notifications | ✗ | ✗ **pending** |
| Image optimisation pipeline | ✗ | ✗ **pending** |
| Redirects, scheduling, webhooks | ✗ | ✗ **pending** |

## 5. Cross-repo security findings (not this repo's code)

1. `woodex420/woodex` commits `.env` (306 bytes: `VITE_SUPABASE_URL`,
   `VITE_SUPABASE_ANON_KEY`, `VITE_STRIPE_PUBLISHABLE_KEY`, `VITE_CUSTOM_DOMAIN`).
   Those three are publishable by design, but the file proves the repo holds environment
   files — check for a service-role key and rotate anything sensitive.
2. The same repo ships `DEMO_CREDENTIALS.md` with a plaintext demo admin account
   (`admin@woodex-demo.com`). Delete the file, rotate the password, purge history.
3. `woodex-furniture-mpa/.env` is committed as well. Treat every key in it as disclosed.
4. `woodex-os` and `woodex-2030` contain no code — do not point DNS, demos or documents at
   them expecting an application.

## 6. What “complete” should mean for this website

A defensible definition of done for the website group:

1. The four content collections and all pages are editable in `/admin` (done).
2. Nothing on the site is hard-coded copy that the studio cannot change (done, except the
   legal review of the privacy text).
3. Leads reach a human without anyone opening a dashboard — email notification (pending).
4. Pages are ≤ 1 MB on mobile and LCP < 2.5 s on 4G (pending image pipeline).
5. Every URL has one canonical, unique metadata, valid structured data (done).
6. Every change is recoverable: revisions (done), backups (pending), redirects (pending).
7. Deploys are gated by automated tests and a QA crawl (tests + crawl exist, CI pending).
