# Woodex Master System — Deep Analysis Report

**Date:** 15 October 2026 · **Author:** Arena agent · **Branch:** `arena/8be318a2-woodexinterior`
**Status:** Analysis complete → questions asked → Master Plan / PRD / System Prompt follow.

> The reference repository (`marketingwoodex-cloud/-marketingwoodex`) was cloned **read-only** into a
> throw-away sandbox folder (`/tmp/ref`) for analysis. Nothing was pushed, changed or opened there.
> No file, credential or secret from it was copied into this repository. The original static site in
> `woodex/` in this repository is also left untouched — all new work lives in new folders.

---

## 1. What was analysed

| Source | Version analysed | Purpose |
|---|---|---|
| `woodex420/woodexinterior` (this repo) | `master @ d77016b` | Current static studio site (deploy target "GitHub → Hostinger") |
| `marketingwoodex-cloud/-marketingwoodex` — `main` | `aa96524` (P25.1 release, 15 Oct 2026) | Latest state of the existing Woodex platform |
| same — `arena/8a776c65-marketingwoodex` (the branch you linked) | `720d817` | v2.1 admin with "Preline Ocean" styling, 25-table DDL, sales/print tooling |
| same — release `p25` | P25.1 notes | What is shipped, what is still unproven |
| `marketingwoodex-cloud/preline` (fork of Preline UI) | `v5.0.0` (Aug 2026) | Website + app UI component system |
| `preline.co/blocks` | 972 blocks (free + premium) | Page-section library to copy per page |
| `TailAdmin/tailadmin-laravel` | `v1.1.2` (Laravel 12, Tailwind 4.1, Alpine 3) | Admin dashboard shell |
| Upstream versions (GitHub releases, Oct 2026) | Laravel **13.36**, Livewire **4.4**, Tailwind **4.3**, Preline **5.0**, Filament 5.10, dompdf 3.1, spatie/permission 8.3, medialibrary 11.23, backup 10.3 | Stack baseline |

---

## 2. This repository today (`woodex/` — static site)

**What it is:** 18 hand-built HTML pages (Home, About, Services, Portfolio + 6 project pages, Insights +
3 articles, Contact, Privacy, Style guide, 404), one `theme.css` (27 KB) and one `site.js` (6 KB).

**Strengths worth keeping**

- A genuinely premium visual language ("Studio" look): **Nohemi** display + **Inter** body, ink `#111`,
  navy `#0F1E36`, warm grey `#F5F5F3`, beige `#FCF2E8`, line `#DEDEDB`, wood focus `#A87442`, pill buttons
  with rotating arrow, 4-column grid lines over photographic hero, oversized hero word.
- Design tokens already centralised in `:root` — they translate 1:1 into Tailwind v4 `@theme` tokens.
- Accessible by default: skip link, `aria-current`, keyboard menu, reduced motion choices, honest copy.

**Gaps / problems found**

| # | Finding | Impact |
|---|---|---|
| S1 | Contact form is a **demo** — `formEndpoint` empty, it only downloads a `.txt` brief | **Zero leads captured** from this site |
| S2 | Portfolio is "concept collection" with Unsplash stock photos | No proof of real work → low conversion |
| S3 | URLs are `/about.html` style; the live site uses `/about/` folders (142 URLs) | Deploying this repo over the live domain would **404 the whole SEO footprint** |
| S4 | `.htaccess` has `Options +Indexes` and `<FilesMatch ".*"> Allow from all` | Directory listing on; anything uploaded is public |
| S5 | `.htaccess` strips trailing slashes; the live site's canonical URLs end in `/` | Redirect loops / duplicate URLs if merged naïvely |
| S6 | No CMS, no CRM, no analytics, no WhatsApp/chat, no schema.org data | Not a sales system — a brochure |
| S7 | Contact data differs between sources (see §6) | Inconsistent NAP hurts local SEO and trust |

---

## 3. The existing platform (`-marketingwoodex`)

### 3.1 History in one paragraph

Twenty-five release phases in ~3 weeks (P15 → P25.1) moved through **four architectures**: static site +
Bootstrap admin → Vercel + Supabase + Netlify functions → Hostinger PHP + MySQL ("v2.1", vanilla JS,
60+ IIFE modules, ~10.5k lines, **365 API actions**) → a React 18 + TypeScript console ("v2.5") over a new
`api/v2` PHP layer that bridges **163 of 365** actions (43%). The repository still contains two public-site
generations (`frontend-v1/` = live 150 pages, root = newer "v26" pages) and many plan documents that
supersede each other.

### 3.2 What genuinely exists and works (v2.1 on Hostinger)

Leads/enquiries with notes and follow-ups · pipeline · bookings (site visits) · client master + "360" view
and merge · quotations (A4, multi-page, templates, PDF, share link, status) · invoices with payments and
tracker · transactions · projects with milestones/photos/billing · WhatsApp Cloud API (send, webhook,
campaigns, flows, opt-out) · Telegram bot · website chat with AI-first + human takeover · AI training (Q&A,
tone, hours, daily cap) · client update notifications (7 events, EN + Urdu) · page builder v3 + section
library + header/footer global edit · redirects + 404 monitor · blog with AI writer · estimator · forms
with Turnstile/honeypot/rate-limit · media library with WebP · SEO manager, speed, site health · users &
roles (owner/admin/sales/editor/support), 2FA, sessions · backups · MCP endpoint for AI agents.

### 3.3 Why it should not simply be continued

| # | Finding (from its own audits + this review) | Consequence |
|---|---|---|
| R1 | **Four admin generations** coexisted; P25.1 still has Pipeline and Quotation builder only as *planned* routes in v2.5 | The two most important sales screens are not finished in the newest UI |
| R2 | Money documents (`wx_quotes`, `wx_invoices`, `wx_projects`, `wx_templates`) are **LONGTEXT JSON blobs** | Cannot query, index, report or validate money |
| R3 | **No foreign keys** in 20 tables; `wx_leads` has 32 loose columns | Integrity lives only in code |
| R4 | 28 JSON side-stores in `_private/` hold settings, secrets and state; non-atomic writes | Corruption class of bugs ("Admin is not set up yet") |
| R5 | Public repository with **credentials published in 151 files / 36 locations**; audit verdict "BLOCK" (8 Critical, 28 High) | **Every credential that ever appeared there must be rotated** before anything new goes live |
| R6 | Migrations `000–014` "have never run against MySQL"; the real publishing gate "has never been executed" | v2.5 is unproven in production |
| R7 | Browser-side money maths, HMAC tokens in `sessionStorage` (XSS-readable) in v2.1 | Security + correctness risk |
| R8 | No build step, 67 unversioned script tags, load order is the contract | Every change risks a regression |

**Conclusion:** the *business knowledge* in the old platform is excellent and must be preserved; the
*code base* is not a safe foundation. A clean rebuild on a mainstream framework, with a one-way data
importer from the v2.1 MySQL tables, is the lowest-risk path to a "complete" system.

### 3.4 Business rules worth carrying forward (extracted from the reference code)

| Domain | Rule / data |
|---|---|
| Pipeline stages | `new → contacted → site visit → quote sent → hold → won / lost` |
| Business lines | **Interior**, **Furniture**, **Project** (turnkey) |
| Lead types | new · returning client · referral |
| Next actions | call · WhatsApp · site visit · meeting · email (each with outcome) |
| Priority | low · normal · high |
| Client types | individual · company · developer/builder · architect/consultant |
| Document numbers | **WI-10100+** (Woodex Interior; quotes and invoices share the series) · **WF-10050+** (Woodex Furniture, own counter) |
| Payment terms | Execution: **50% advance / 40% on civil+wood completion / 10% handover** · Design: **75% advance / 25% on approval** · validity **15 days** |
| Payment routing | Interior → *Woodex Interior* bank account · Furniture → *Woodex Furniture* bank account · JazzCash / Easypaisa wallets (exact numbers stay in Settings, never in code) |
| Signatories | Director + Marketing Manager blocks on quotations |
| Quote templates | Renovation BOQ (10 sections: civil, flooring, ceiling, paint, wood, glass, bath fittings, curtains, electrical, plumbing), Civil Work, Interior Designing + scope-of-work templates per service |
| Estimator | 6 services × 3 finishes (Essential / Standard / Premium) per sq ft or per view + 22-item line catalog |
| Client notifications | enquiry received · quotation sent · work started · handover · booking confirmed · booking reminder · project progress (with photos) — EN + Urdu |
| Project stages | planning → design → procurement → execution → finishing → handover → completed |
| Roles | owner · admin · sales · editor · support (+ proposed: designer/PM, accountant) |

---

## 4. Live SEO footprint that must be preserved (woodex.com.pk)

`frontend-v1/sitemap.xml` lists **142 URLs**:

| Group | Count | Examples |
|---|---|---|
| Service & utility pages | 65 | `/interior-design/`, `/fit-out/`, `/renovation/`, `/architecture/`, `/3d-visualization/`, `/kitchen-design/`, `/office-fit-out/`, `/10-marla-house-design/`, `/estimator/`, `/book-a-visit/` |
| City / area pages | 20 | 12 cities (`/lahore/`, `/karachi/`, `/islamabad/` …) + 8 Lahore areas (`/lahore/dha/`, `/lahore/gulberg/`, `/lahore/bahria-town/` …) |
| Insights | 48 + index | `/insights/house-renovation-cost-lahore-2026/` … |
| Projects | 6 + index | `/projects/courtyard-house/` … |
| Home | 1 | `/` |

**Rule for the rebuild:** every one of these URLs must answer 200 on the new Laravel site (same path, with
trailing slash) or 301 to its replacement. The router, sitemap and redirect manager are designed around
this list.

---

## 5. Resources review

### 5.1 TailAdmin Laravel (admin shell)
Laravel 12 + Blade components + **Tailwind v4 (`@theme`, no config file)** + Alpine.js + ApexCharts,
FullCalendar, Flatpickr, Swiper, jsVectorMap; dark mode via `.dark`; RTL-ready layout rules. It is a
**visual shell only** — no auth, CRUD, tables with server data, or permissions. MIT licence.
→ Use: sidebar/header/layout, dashboard cards, charts, calendar, profile and form styles. Upgrade the
skeleton to Laravel 13 and add Livewire 4.

### 5.2 Preline UI v5 (UI system + blocks)
MIT + "Preline UI Fair Use" licence (attribution required in redistributions; fine for a company site).
v5 brings a **semantic token theme** (`--background`, `--foreground`, `--primary`, `--card`, `--navbar`,
`--sidebar`, `--footer`, `--chart-1…10` …), a theme generator, vanilla JS plugins (`data-hs-*`), and
**972 blocks** (hero, headers, features, stats, testimonials, galleries, FAQ, pricing, contact, footers,
KPI cards, charts, tables, timelines, drawers, chat UI …).
→ Key insight: because Preline v5 themes are **CSS custom properties**, the Theme Builder can change
colours, fonts and radii **at runtime from the database** — no Tailwind recompile and **no Node.js on the
server**.
→ Licence note: *free* blocks are MIT; *Pro/premium* blocks need a Preline Pro licence. The plan uses free
blocks unless you confirm you own Pro.

### 5.3 Stack row you selected
"Laravel + Livewire + Tailwind v4 — very low resource use, `queue:work --stop-when-empty` from hPanel cron,
no Node.js on the server, Git push or File Manager deployment." This matches the hosting reality below.

---

## 6. Inconsistencies that need one source of truth

| Item | Source A | Source B |
|---|---|---|
| WhatsApp / phone | **+92 322 4000768** (this repo, live site — 230 hard-coded places) | **+92 322 4200168** (reference plan decision D6) |
| Email | woodexinterior.pk@gmail.com (this repo) | info@woodex.com.pk (reference company config) |
| Studio hours | Mon–Sat 10:00–19:00 (this repo) | 10:00–19:30 (reference AI training) |
| Brand look | "Studio": Nohemi + Inter, navy/beige (this repo) | "v26": Plus Jakarta Sans, navy/cream/wood (live site) |
| Admin look | TailAdmin (requested) | "Woodex Ink" dark/cyan (v2.5) · "Preline Ocean" (v2.1 branch) |

All of these become **Settings** values in the new system (single source of truth, edited from the admin).

---

## 7. Hosting constraints → design consequences (Hostinger shared, LiteSpeed)

| Constraint | Consequence in the design |
|---|---|
| No long-running processes / daemons | Queue = `database` driver, drained by cron every minute (`schedule:run` → `queue:work --stop-when-empty --max-time=50`) |
| No websockets server (no Reverb) | Real-time via **Pusher/Ably free tier** *or* Livewire polling fallback — behind Laravel Broadcasting so the choice is a config switch |
| No Node.js on the server | Tailwind/Vite built locally or in GitHub Actions; `public/build` shipped with the release; runtime theming via CSS variables |
| PHP CLI over SSH, hPanel cron, MySQL/MariaDB | Standard Laravel deploy: `composer install --no-dev`, `migrate --force`, `optimize`; `storage:link` via SSH |
| No headless Chrome | PDFs with **dompdf** (pure PHP) — table-based print templates |
| SMTP limits on shared mail | Transactional mail through Hostinger SMTP with throttling; optional Resend/Brevo API later |
| LiteSpeed cache | Full-page cache for public pages (purge on publish); admin never cached |

---

## 8. Gap analysis — requested master system vs what exists

| Requested capability | Old platform | New plan |
|---|---|---|
| Web lead → client database | ✅ (fragile, no FKs) | Unified `contacts` + `leads` + `clients` with dedupe on normalised +92 phone |
| Quotation builder + templates + tracking | ✅ v2.1 / ❌ v2.5 (planned) | Relational quote → sections → lines, revisions, signed public link, view tracking, e-accept |
| Quote → invoice → payments → tracking | ✅ (JSON blobs) | Milestone invoicing (50/40/10), payments, receipts, aging, reminders, ledger reports |
| CRM (pipeline, tasks, follow-ups, SLA) | ✅ partial | Kanban + list, tasks, SLA timers, assignment rules, lead scoring, activity timeline |
| Live chat bot + human handoff | ✅ (polling) | AI bot with knowledge base, lead capture, handoff, WhatsApp fallback, real-time |
| WhatsApp follow-up automation | ✅ Cloud API | Cloud API inbox + templates + sequences + quote/invoice delivery + opt-out |
| Telegram alerts + mobile control | ✅ basic | Alert matrix + daily digest + commands + inline actions + system health alerts |
| Social media management + social inbox | ⚠️ minimal | Meta (FB/IG) inbox, comments, Lead Ads → CRM, scheduler, calendar |
| Website customisation / theme builder | ⚠️ fragile builder | Token theme builder + block page builder (Preline blocks) + versions/rollback |
| Preline-quality UI/UX everywhere | ❌ | Preline v5 blocks on site, TailAdmin + Preline in admin, one token system |
| Real-time dashboard | ⚠️ polling | Broadcast events + KPI widgets + "needs attention" rules |

---

## 9. Recommendation (summary — detailed in the Master Plan)

1. **One Laravel 13 application** (PHP 8.4; Hostinger offers 8.2–8.5) with **Livewire 4**, **Tailwind v4**, **Preline v5** and the
   **TailAdmin** shell; MySQL 8 / MariaDB; no Node in production.
2. **Modular monolith**: `Website`, `CRM`, `Sales` (quotes/invoices/payments), `Projects`, `Conversations`
   (chat/WhatsApp/social/Telegram), `Marketing`, `CMS/Theme`, `System` — each with its own models, Livewire
   screens, policies and tests.
3. **Relational money model** (no JSON blobs for money), server-side totals, immutable sent versions.
4. **Integrations behind interfaces** (WhatsApp, Telegram, Meta, AI, payment gateways, realtime) so each can
   be enabled by pasting keys in Settings, and each has a "test connection" button.
5. **SEO continuity**: all 142 live URLs preserved; content imported; redirects managed in the admin.
6. **One-way importer** from the v2.1 MySQL tables (`wx_leads`, `wx_clients`, `wx_quotes`, `wx_invoices`,
   `wx_projects`, `wx_bookings`, `wx_chats` …) with dry-run, counts and checksums.
7. **Security first**: rotate every credential exposed in the old public repo; this repository stays
   secret-free (`.env` only on the server).

---

## 10. Questions raised by this analysis

Asked interactively right after this report: admin build approach · website base · theme-builder model ·
WhatsApp level · social-media scope · rollout/data migration. Answers are recorded in
`docs/07-DECISIONS.md` and drive the Master Plan, PRD and System Prompt.
