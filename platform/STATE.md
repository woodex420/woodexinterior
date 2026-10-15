# Woodex OS: build state

_Update at the end of every task (docs/03 Part A §7)._

## Current phase: P0 Foundation (in progress)

### Done (this branch)
- Laravel 13.11 skeleton in `platform/`, PHP 8.4, Livewire 4 (class mode, no emoji), Fortify, spatie permission /
  activitylog / medialibrary / settings / backup, dompdf, brick/math. Pest 5, Larastan, Pint.
- Vite 8 + Tailwind 4.3 + Preline 5 build; Woodex "Studio" tokens; TailAdmin-style admin shell with the full
  navigation hierarchy (later-phase screens shown disabled with their phase badge).
- Auth: login, password reset, TOTP 2FA (Profile → Security), 2FA enforcement for owner/admin/accountant
  (`ENFORCE_2FA`), deactivated users blocked, last-login tracking.
- Roles and permissions per PRD §7 (owner, admin, sales_manager, sales, designer_pm, accountant, editor, support).
- Settings: Business (NAP, hours), Sales (terms, discount limits, lost reasons, budget bands, signatories), Notifications.
- Database: system, CRM, sales (quotes, versions, events), projects and billing tables (docs/04, P0–P3 scope).
- Support: Money (paisa, lakh formatting), AmountInWords (lakh/crore), Phone (+92 E.164), Numbering (row lock),
  BusinessHours (SLA due times).
- Domain: CaptureLead (dedupe by phone/email, merge window, attribution, SLA, auto-assign), AssignLead,
  MoveLeadToStage (lost reason, won rule), QuoteCalculator, MilestonePlan (50/40/10, 75/25).
- Public API `POST /api/public/leads` with throttle, honeypot, minimum fill time, Turnstile and the live-site bridge key.
- Alerts: LeadCaptured → job after response → database + Telegram notification.
- Telegram webhook: account linking (`/start CODE`), `/help`, `/idea`, `/issue` → change requests.
- Admin screens: Dashboard (KPIs, 14-day chart, funnel, needs attention, go-live checklist, recent activity),
  Leads list (search by phone in any format, filters, quick add), Pipeline board (drag and drop, lost reason modal,
  won rule), Security & Telegram, System health.
- Seeders: roles, 8 pipeline stages, numbering (WI-10100, WF-10050, RC-YYYY-0001, L-YYYY-000001), rate book
  (22 items + decor library + 6 estimator services), **16 quote templates** from the real Woodex BOQs.
- CI: workflows ready in `docs/ci/` (Pest + Pint blocking, Larastan report-only, Vite build; manual Hostinger
  deploy). **Not yet active**: the Arena GitHub connection cannot write `.github/workflows/` (see docs/ci/README.md).

### Verification (15 Oct 2026)
- Ran on real **PHP 8.4.25** with the actual dependency versions resolved for this composer.json (Laravel 13.36,
  Livewire 4.4.7, Fortify 1.41, Pest 5.3.2, spatie/permission 8.3, settings 3.9, activitylog 5.1 … 166 packages).
- `migrate --seed` on SQLite: all migrations, settings migrations and seeders pass.
- **Pest: 60 tests, 187 assertions, all passing** (unit, feature, architecture).
- **Pint: clean (172 files).**
- Larastan level 5: 17 findings, all from model `casts()` not being picked up (dates/enums typed as string).
  Runtime behaviour is covered by tests. Investigate in P1 before making Larastan blocking.
- Live run: login → dashboard → leads → pipeline → health → security all render; `POST /api/public/leads`
  returns 201 and the lead appears in the admin search by phone typed in local format.
- Bug found and fixed by this run: spatie/laravel-settings parses `@var` docblocks on settings properties and
  rejects generics (`array<string, int>`). Settings classes now carry plain comments instead.

### Next (to finish P0, then P1)
1. Activate CI (docs/ci/README.md), then commit the `composer.lock` printed by the first run.
2. Fix Larastan cast detection, make Larastan blocking, then raise it to level 6.
3. Staging on Hostinger: subdomain, PHP 8.4, cron, `woodex:install`, Telegram webhook (docs/06).
4. P1: contacts/companies screens, lead drawer + timeline, tasks, site visits + calendar, assignment rules UI,
   SLA checker command + escalations, global search (Ctrl/⌘ K), v2.1 importer (CRM part).

### Assumptions
- WhatsApp/phone +92 322 4000768, email woodexinterior.pk@gmail.com, hours Mon–Sat 10:00–19:00 (P-01…P-03).
- Asia/Karachi stored as application time zone (no DST in Pakistan).
- Free Preline blocks only until a Pro licence is confirmed (P-04).

### Known issues / risks
- Admin JS bundle is ~390 KB (88 KB gzip) because the full Preline bundle is imported; switch to per-plugin
  imports (dropdown, overlay, collapse, tabs, tooltip) in P1.
- Nohemi font commercial licence to be confirmed before the public website launch (P-09).
- Credentials exposed in the old public repository must be rotated before go-live (P-06).
