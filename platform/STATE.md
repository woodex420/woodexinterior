# Woodex OS: build state

_Update at the end of every task (docs/03 Part A §7)._

## Resume here (project memory, saved 16 Oct 2026 PKT, after the setup-code fix)
- **Where we are:** P1 CRM is complete, P2 Quotations part 1 (the quote builder) is done, and the Hostinger Git deploy
  works. **The owner deployed to the main domain `woodex.com.pk`** (his choice; not the subdomain) and reached the web
  installer: PHP 8.4.26, all 12 extensions, private data folder `woodex-os/woodex.com.pk`, build present.
- **Setup code fix (D-59):** the owner could not find `~/woodex-os/<site>/setup-code.txt` (hPanel's File Manager opens
  in `public_html`; the home folder only shows under "Access all files of hosting"). The installer now also writes
  **`public_html/WOODEX-SETUP-CODE.txt`** (same code, never served, deleted after installing) and names it first.
  Owner: Deploy again in hPanel → Git, reload `/install`, open that file in File Manager, paste the code.
- **After installing:** add the cron line the installer shows (hPanel → Advanced → Cron Jobs); after each later deploy
  sign in → System → Database update when asked.
- **In progress (uncommitted until finished):** P2 part 2a — approvals (QUO-007), send + frozen versions (QUO-008/013),
  PDF (QUO-014), signed client page + view tracking (QUO-009/010), accept / request changes (QUO-011 first part).
  Tests, Larastan and Pint pass. Left: PDF polish (header rule through the address, "Page 1 of 0", numeric headers,
  "Lahore, Lahore", whole-rupee milestones), browser check, docs (D-60+), commit.
- **Next after that:** the owner asked to update and deploy the **public website** (questions first), then continue
  P2 part 2b (project + advance invoice with P3, reminders/expiry QUO-012, options QUO-019, v2.1 quote import).
  - **CI:** this GitHub connection cannot write `.github/workflows`, so the owner adds the two files from
    `docs/ci/` (2 minutes in the GitHub website).
  - The owner has not yet answered P-01…P-09; P-06 (rotate the leaked credentials) blocks go-live.
- **Code:** everything is on branch `arena/8be318a2-woodexinterior` and pushed. `master` is still the starting point
  (d77016b); merge through a pull request when the owner approves.
- **Rules to follow:** docs/07 (D-01…D-59) and platform/AGENTS.md. The plan and specs are in docs/00–08.
- **Preview (AI sandbox only):** after a VM reset run `sh tools/sandbox/setup.sh` (~3–4 min). It rebuilds PHP 8.4,
  `vendor/`, the front-end build and the demo database, including the demo quotations. Then start
  `PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs` from `/tmp/woodex-run`. The checks are `smoke.sh`,
  `assets-check.sh`, `browser-check.mjs`, `iframe-check.mjs` and `installer-check.mjs` (tools/sandbox/README.md).
- **Preview data:** demo seed with 26 leads, 5 users and 4 quotations (WI-10100…WI-10102, WF-10050). Owner login:
  `owner@woodex.test` / `Woodex-Preview-2026`; the sign-in page also has a one-click demo button. The preview
  database lives in `/tmp` and is rebuilt from the seeders after a VM reset, so anything typed into the preview is
  temporary. Real data arrives with the v2.1 import on staging (docs/08).

## Hostinger deploy fix (16 Oct 2026, D-56…D-58)
- **What happened (15 Oct, deploy of 3155343):** hPanel's Git deploy copied the repository into the **main domain's**
  `public_html` (its default root directory). `woodex.com.pk` answered 403 (no index file at the repository root),
  the Composer step was skipped (no root `composer.json`), there was no CSS (`public/build` was gitignored) and the
  subdomain still showed Hostinger's default page. Hostinger's Git deploy copies files and runs only Composer.
- **Fix:**
  - Root `.htaccess` → everything to `platform/public`; nothing else is web-reachable.
  - Root `composer.json` → same `require` as `platform/composer.json`, `vendor-dir: platform/vendor` (works with or
    without Composer scripts); `tools/deploy/after-composer.php` clears stale caches.
  - `platform/public/build` committed (rebuild + commit with every CSS/JS change).
  - `.env` + `storage/` in `~/woodex-os/<site>/` when `platform/.env` is absent (`App\Support\Install\SiteState`).
  - Web installer `/install` (setup code from the data folder, two steps, then closed) and System → Database update
    after deploys (`EnsureDatabaseIsCurrent`, `woodex:update`). Health shows database, data folder and build checks.
  - `public/index.php` explains a wrong PHP version or missing packages instead of a blank 500.
  - Seeders only add missing rows (`firstOrCreate`): re-running never undoes the owner's rates/templates/stages.
  - Production forces https links when `APP_URL` is https (CDN-safe); `TRUSTED_PROXIES` for a CDN.
- **Verified:** Pest 256 passed, 1155 assertions (1 skipped in the sandbox only: MySQL needs sockets); Larastan clean; Pint clean; lint;
  smoke (22 pages), browser-check, iframe-check; `installer-check.mjs` on a simulated `~/domains/<site>/public_html`:
  fresh site → installer → wrong code refused → install → `/install` 404 → owner signs in → a new migration sends the
  owner to Database update → Apply → admin works.
- **Not verifiable here:** the real Hostinger Composer step (which command, PHP version, memory) and LiteSpeed's
  `.htaccess` handling. The first deploy log will tell; the preflight page and docs/06 §2.7/§10 cover the failures.

## Current phase: P2 Quotations, part 1 done → part 2 next

### P2 part 1 delivered: quote builder (16 Oct 2026)
- **Quotations list** (QUO-016) at Sales → Quotations: status chips with counts (incl. "Needs follow-up": sent or
  viewed, no reply for 2 days), search by number, title, client, company or phone; line, owner and sort filters;
  value, owner and age columns.
- **New quotation** (QUO-003/006): choose the client (search, or start from an enquiry or contact page), link the open
  enquiry, the line and a template card (16 templates + "Blank"). The number is given on creation: WI-10100+
  interior/project, WF-10050+ furniture. The draft starts with valid-until = today + 15 days and the template's
  terms (or the default execution/design terms), scope, notes and milestone plan.
- **Quote builder** (QUO-003/004/005/015/020) at `/admin/quotes/{id}`:
  - Sections (rename, note, reorder, delete) and lines: description, qty with 3 decimals, unit, rate, line discount
    as % or Rs, optional line, internal cost.
  - Every field saves on blur, and the server recalculates line totals, section subtotals, discount, other
    charges, tax, rounding, total and margin (`QuoteEditor` → `RecalculateQuote`, D-54).
  - Enter in qty/rate/discount adds a line below and focuses it. Lines and sections drag and drop (SortableJS) or
    use the up/down actions.
  - The rate book picker (search + category) copies the item into the quote.
  - Side panel: client, title, validity, quote discount (% or Rs), other charges (label + amount), tax %, payment
    milestones (Execution 50/40/10, Design 75/25 or Custom; amounts always add up to the total), scope of work
    (bullets), terms, client notes and internal notes.
  - A sticky totals bar warns about lines without a quantity or rate and about milestones that are not 100%.
  - Duplicate, Save as template (new or update) and delete draft (recycle bin). Read-only for viewers and for
    quotes that are no longer drafts.
- **Rate book** (QUO-001): list with search, category and hidden/visible filters. Managers (`quotes.catalog`, D-53)
  add, edit and hide items (rate, internal cost, finish, line).
- **Quote templates** (QUO-002): cards with type, line, plan, sections, lines and usage count; "View lines",
  "Use" and edit details (name, description, type, line, plan, scope, terms, notes, hide).
- **Around the CRM:**
  - The lead page has a Quotations card with a "New quotation" link. The contact page and company page list quotes.
  - Global search finds quotation numbers and titles; the recycle bin has a Quotations tab.
  - Quote events appear on the lead and contact timelines.
- **Visibility** (D-53): managers, admins and accounts see every quote. Sales and designers see their own quotes and
  those of their leads.
- **Found by the real-browser check:** Livewire 4's `wire:model.blur` never sends a request (D-55). The builder uses
  `wire:model.live.blur`, and `browser-check.mjs` now edits a quantity, checks the server total and puts it back.

### P2 part 1 verification (16 Oct 2026)
- Pest: 212 tests, 968 assertions, all passing. The 40 new tests include the 42-line Renovation template totalled
  to the paisa against an independent BigDecimal sum, plus discounts, other charges, tax, rounding, milestones,
  moves, locks, duplicate, templates, visibility and the Livewire builder.
- Pint clean · PHP lint clean (269 files) · Larastan level 6: no errors.
- `browser-check.mjs` passes, including the quote builder edit/restore and the phone width; `iframe-check.mjs`
  passes; the smoke test checks 21 admin pages.

### P2 part 2 plan (next)
1. **Approvals (QUO-007):** discount above the role limit (`discount_limits_bp`) or margin below `min_margin_bp` →
   pending approval; the owner or manager approves in the admin (and later on Telegram, TGM-002). Approvals menu.
2. **Send + versions (QUO-008/013):** an immutable `quote_versions` snapshot and PDF hash on each send; a copyable
   signed link plus email (WhatsApp in P4). Editing a sent quote creates revision R1, R2…; staff see the
   differences.
3. **PDF (QUO-014):** A4 Studio letterhead with dompdf: client block, scope, sections, totals, amount in words,
   milestones, terms, bank details and signatories, page numbers.
4. **Client page (QUO-009/010):** signed, expiring link with a Studio-branded view, PDF download, Accept (name +
   checkbox), Request changes and Ask on WhatsApp. View tracking with first-view and "hot" alerts.
5. **Accept automation (QUO-011):** lead → Won, contact → client, a project record and the advance invoice draft (P3
   tables) in one transaction, then the notifications.
6. **P2 part 3:** validity reminders and expiry (QUO-012), options A/B (QUO-019) and the v2.1 quotes/templates
   import.

### Preview sign-in fix (16 Oct 2026)
- **Test 1 (00:18 PKT):** the owner signed in as `admin@woodex.com.pk`, which does not exist in the demo data, and
  landed on the public holding page with no error. **Cause:** the preview proxy drops the `Cookie` request header.
  The browser keeps the cookies (beacon `cookieKept=1`), but every request reached Laravel without them and started
  a new session. Even the right password would have bounced back to the sign-in form.
- **Fixes:**
  - A failed sign-in returns to its form with the error (D-51).
  - The preview server keeps each browser's session (cookie memory, D-52).
  - The sign-in page has a demo-account box with a one-click sign-in (preview only).
- **Test 2 (00:43 PKT):** sign-in worked (the session was signed in), but a double click on "Sign in" sent a second
  POST. That request arrived already signed in, and Laravel's "already signed in" redirect goes to the route named
  `home`, which is the public website. Opening the sign-in page again went there too.
  **Fixed:** signed-in users on guest pages go to `admin.dashboard` (`redirectUsersTo`); the auth forms submit
  only once per page (D-51, tested).
- **Test 3 (00:47 PKT):** the owner was in the admin and browsed 10 screens without errors.
- **New check `tools/sandbox/iframe-check.mjs`** reproduces the Arena setup: a cross-site iframe, a proxy without
  Referer, third-party cookies blocked, and a run that drops every cookie. Before each fix it showed exactly what the
  owner saw. Now both runs pass:
  - a wrong password shows the error;
  - a double click sends one request and opens the dashboard;
  - the sign-in page leads back to the admin;
  - `wire:navigate` and a full reload stay signed in.
- The request log shows cookie names and a short browser tag (`ua=Windows/Chrome153`). The beacon reports
  `cookieKept`, `framed` and `storageAccess`.
- **Verification:**
  - Pest: 172 tests, 726 assertions, all passing.
  - Pint clean.
  - `browser-check.mjs` passes; `iframe-check.mjs` passes both runs; smoke test: 17 admin pages return 200.

### Preview fix (16 Oct 2026, after P1 part 2)
- **Holding page unstyled in the owner's browser (reported twice):** the preview emitted absolute asset URLs on a
  guessed preview host; they failed in the real browser while in-sandbox curl passed. All output URLs are now
  root-relative (preview server rewrite + `ASSET_URL=/`), so pages work under any origin (D-36). Confirmed from the
  owner's own browser: CSS and fonts load from the preview origin and JavaScript runs (preview beacon: Alpine started).
- **Lead form invisible without JS:** the whole form sat in `<template x-if>`; it is now plain HTML enhanced by
  Alpine, `x-cloak` added to site.css, submit disabled until Alpine starts (D-49).
- **Dashboard chart drawn twice and overflowing its card; y-axis 0,1,1,2,2,3,3:** init race fixed, charts re-fit to
  their box, whole-number ticks (D-48).
- **Admin content slid in on every page load / `wire:navigate`:** sidebar width is now a CSS variable set before
  first paint (D-48).
- Preview server: gzip/brotli (livewire.js 609 → 121 KB, ApexCharts 567 → 135 KB), request log with start / done /
  ABORTED lines and proxy headers.
- Sandbox tooling moved into the repo (`tools/sandbox/`, D-50) including a real headless-Chromium check.

### P1 part 2 delivered (this branch)
- **Fix: public holding page without styling** (giant logo): the sandbox preview server was rebuilt so asset URLs
  always use the public https origin (no mixed content), static files are served with correct MIME types, and inline
  SVGs (logo, icons) now carry width/height fallbacks so no page can explode while CSS loads (D-36).
- **Pipeline exit test** (CRM-004/005): dragging a card to *Site visit* without a booked visit opens the booking
  modal; cancelling puts the card back; booking moves it (also when dragged back from a later stage).
- **Visit booking everywhere** via one concern (`BooksSiteVisits`) with a **double-booking warning** (same team member,
  overlapping time → "Book anyway").
- **Visit calendar** (CRM-008): week columns and month grid with status colours, previous/next/today, day → week.
- **Staff reminders** (CRM-007/008): `woodex:reminders` every minute → task reminders at `remind_at` (30 min before
  due) and site-visit reminders 24 h and 2 h before, in-app + Telegram, each sent once (D-40).
- **Repeating tasks**: daily, working days (Mon–Sat), weekly, monthly; completing creates the next one (D-40).
- **Companies** (CRM-002): list (search by name/phone/email/NTN, type, sort), company page (details, people with
  link/unlink, enquiries, P2/P3 placeholders), edit, delete; companies in global search.
- **Assignment rules** (CRM-009) at Settings → Lead assignment: ordered rules by line, service, city/area and source;
  role or chosen people; round robin or fewest open leads; workload cap; "working hours only" (after-hours rule
  below it); fallback = least-loaded sales person. **Reassign with a reason** on the lead page (D-37).
- **SLA** (CRM-010, AUT-004): response target per source, **holidays** in Settings → Business, second escalation to
  the final roles (default owner) after the escalation delay (D-38).
- **Lead scoring rules in Settings** (CRM-011): Settings → Lead rules (budget, size, line, timeline, lead type,
  services, cities, per-source points, hot threshold) (D-39).
- **List tools** on Leads (GLB-003): sort, 25/50/100 per page, column chooser, saved views per person (with default
  view), CSV export (`leads.export`, formula-injection safe), bulk assign / move stage / delete (D-43).
- **Notification centre** (GLB-008): header bell with unread count, mark read, deep links, 30 s polling while visible,
  and a full Notifications page (D-45).
- **@mentions** in notes (GLB-005): `@Sara`, `@Sara Khan`, `@sara.khan` notify in-app + Telegram (D-44).
- **Attachments** (GLB-006) on leads and contacts: photos, PDF, DWG/DXF, Excel, Word up to 20 MB, private disk,
  authorised download route, WebP thumbnails for images, timeline entry (D-41).
- **Recycle bin** (GLB-010): delete lead / contact (with its enquiries) / company, restore together; owner-only
  **erase** of personal data for privacy requests (D-42).
- **Integrations hub** (SYS-005) + **integration log viewer** (GLB-018): status cards (Telegram, email, website forms,
  Turnstile, v2.1 database; WhatsApp/Meta/LinkedIn planned), test buttons, connect Telegram webhook (D-47).
- **Data import wizard** (SYS-006): connection check, choose parts, queued dry run, reports with warnings/duplicates
  downloads, real run only after a dry run in the last 24 h + typing IMPORT (D-46).

### Verification (16 Oct 2026, PHP 8.4.25 sandbox, real dependency versions)
- **Pest: 170 tests, 704 assertions, all passing** (133 from part 1 + 37 new).
- **Pint clean · PHP lint clean (246 files) · Larastan level 6: no errors · all Blade views compile.**
- Live preview: 17 admin pages + holding page return 200 as owner; CSS/JS URLs root-relative, `text/css` /
  `text/javascript`. **Real-browser check (headless Chromium) passes:** styles applied, Alpine/Livewire running, no
  failed requests or console errors, one chart inside its card (expanded and collapsed sidebar), no layout slide on
  load or `wire:navigate`, mobile drawer, lead form visible without JS and submitted with JS (reference returned).

### Next (after P1)
1. Activate CI (docs/ci/README.md; Larastan is blocking there), then commit the first `composer.lock`. Waiting for
   the owner: this GitHub connection cannot write `.github/workflows`.
2. Staging on Hostinger: subdomain, PHP 8.4, cron, `woodex:install`, Telegram webhook (docs/06); first import dry run
   from System → Data import against a copy of the v2.1 database (docs/08). Waiting for P-05.
3. P2 Quotations (docs/03 Part B): part 1 done, see above.
4. Deferred from P1 (small): CSV import of leads/contacts with mapping (CAP-009); full alert-rule builder (AUT-003,
   P5); client WhatsApp visit reminders (P4); saved views on Contacts/Companies (the concern is ready).

### P1 part 1 delivered (commit cfd0fd5)
- **Lead page** `admin/leads/{lead}` (CRM-003/006/016, GLB-004): one-tap call outcomes, WhatsApp/email/meeting/note
  logging, next action with presets (1 h, tomorrow, 3 days, next week), stage bar with lost-reason modal, owner
  reassignment, site visit booking, tasks, editable details, score badge and SLA status, full timeline.
- **First-response SLA** (CRM-010, D-31): first outbound attempt stops the timer; New → Contacted only when the client
  was reached; 3 unanswered calls in a row suggest WhatsApp; `woodex:sla-check` every minute flags breaches once and
  alerts owner + escalation roles (in-app + Telegram).
- **Lead scoring** (CRM-011) on capture: budget band, size, service, line, referral/returning, timeline; Hot ≥ 80.
- **Contacts** list + **Contact 360** (CRM-001/012/015): search by phone in any format, client/prospect filter, tags,
  duplicate detection (phone/email/name) and **merge** (moves leads, timeline, tasks, visits; fills blank fields),
  new enquiry for an existing client. Sales users see only their own contacts.
- **Tasks** (CRM-007): mine / whole team, overdue / today / upcoming / no date, complete and reopen, link to a lead
  by reference.
- **Site visits** (CRM-008): agenda (needs update / today / tomorrow / dates), map link, one-tap WhatsApp
  confirmation, confirm / reschedule / no-show / cancel, **outcome form with room measurements** → lead size, a
  "prepare quotation" task due in 48 h and a follow-up call.
- **My Day** (CRM-013): uncontacted leads first (with SLA countdown), overdue follow-ups, later today, today's visits,
  tasks due, unassigned leads for managers.
- **Global search** (GLB-002): Ctrl/⌘ K palette in the header over leads and contacts (name, phone in any format,
  email, reference), keyboard navigation, respects visibility.
- **Users & roles** (SYS-002): invite by email (set-password link), roles with descriptions, edit, deactivate
  (signs out, unlinks Telegram: TGM-007), resend invite; guards: never the last active owner, never yourself, only
  owners grant the owner role.
- **Settings** screen (SYS-001): business identity, contact, working hours/days, social links; sales rules (validity,
  invoice terms, discount limits in %, minimum margin, SLA minutes, lost reasons, budget bands, terms, signatories);
  notifications (Telegram on/off, quiet hours, digest time, escalation roles). Phones normalised to E.164.
- **v2.1 importer** (SYS-006 CRM part, D-32): `php artisan woodex:import-v21 {--dry-run} {--only=*} {--connection=legacy}`
  for users, clients, leads, notes, bookings; idempotent via `legacy_refs`; report + warnings/duplicates CSV.
  Runbook: `docs/08-V21-IMPORT-RUNBOOK.md`.
- Lead sources extended for organic/offline channels (D-33); manual lead form offers them.
- Admin JS: Preline per-plugin import (44 KB instead of ~390 KB, D-35). Navigation: My Day, Contacts, Tasks, Site
  Visits, Settings, Users & Roles enabled; lead links everywhere (list, board, dashboard, Telegram, notifications).
- Demo seeder (local only) adds calls, visits (today/tomorrow/overdue/completed with measurements) and tasks.

### Part 1 verification (15 Oct 2026)
- **Pest: 133 tests, 518 assertions, all passing** (60 from P0 + 73 new: lead page, contacts, tasks/visits/My Day,
  SLA, global search, users/settings, importer against a v2.1-shaped database, value conversions).
- **Pint: clean (210 files). PHP lint: clean.**
- **Larastan level 6: no errors** after enabling `parseModelCastsMethod` (D-34). It found two real bugs while P1 was
  built (a renamed method still called in one path; an untyped schema call).
- Live preview: all 16 admin pages render as owner (dashboard, My Day, leads, lead page, pipeline, contacts,
  contact page, tasks, site visits, settings × 3 tabs, users, health).

## P0 Foundation (done, commit ce819db)
- Laravel 13 + PHP 8.4, Livewire 4 class components, Fortify (login, reset, TOTP 2FA + enforcement), spatie
  permission/activitylog/medialibrary/settings/backup, dompdf, brick/math; Vite 8 + Tailwind 4.3 + Preline 5;
  TailAdmin-style shell with the full navigation (later screens shown disabled with their phase).
- Roles/permissions (PRD §7), settings groups, schema for system/CRM/sales/projects/billing (docs/04), support classes
  (Money, AmountInWords, Phone, Numbering, BusinessHours), CaptureLead + public lead API (throttle, honeypot,
  Turnstile, bridge key), Telegram alerts + account linking + `/idea` `/issue`, dashboard, leads list, pipeline board,
  system health; seeders incl. 16 real quote templates and the rate book.

### Assumptions
- WhatsApp/phone +92 322 4000768, email woodexinterior.pk@gmail.com, hours Mon–Sat 10:00–19:00 (P-01…P-03).
- Asia/Karachi stored as application time zone (no DST in Pakistan).
- Free Preline blocks only until a Pro licence is confirmed (P-04).

### Known issues / risks
- CI not active yet: the Arena GitHub connection cannot write `.github/workflows/` (docs/ci/README.md).
- Importer: v2.1 phone numbers that are not valid Pakistani/international numbers are kept in contact notes; the
  dry-run warnings list them. Budget text that names no amount stays in the lead details.
- Nohemi font commercial licence to be confirmed before the public website launch (P-09).
- Credentials exposed in the old public repository must be rotated before go-live (P-06).
