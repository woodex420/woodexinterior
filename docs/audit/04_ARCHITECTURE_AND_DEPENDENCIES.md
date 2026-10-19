# 04 · Architecture and dependencies

Read from the checkout at `b817322…`; versions read from the runtime's installed package list and the
committed `package-lock.json`. This document describes **what exists** and ends with proposed
structure/decisions — nothing here has been implemented.

---

## 1. Stack

| Layer | Technology | Version observed |
|---|---|---|
| Language | PHP | 8.4.25 (require `^8.4`) |
| Framework | Laravel | 13.37.0 (require `^13.36`) |
| UI | Livewire + Alpine + Blade | Livewire 4.4.7, Alpine 3.17.4, 158 Blade views |
| Styling | Tailwind CSS 4 + Preline, design tokens in `resources/css/tokens.css` | 4.3.3 |
| Build | Vite | 8.3.4 |
| Database | SQLite in sandbox/tests; MySQL planned for production | 95 tables, 64 migrations |
| Auth | Laravel Fortify + Google OAuth; 2FA enforced by middleware | fortify 1.41 |
| Permissions | spatie/laravel-permission + 8 policies | 8.3.2 |
| Media | spatie/laravel-medialibrary + a private disk outside the web root | 11.23.10 |
| Backups | spatie/laravel-backup | 10.3.3 |
| PDF | dompdf | 3.1.2 |
| Tests | Pest 5, pest-livewire, Larastan 3, Pint 1.32 | 122 files, 1,189 passing |

No JavaScript SPA framework, no Node server, no separate API service. Hostinger shared hosting is the
deployment target, which explains several constraints (no Node on the host, assets built in CI,
queue via cron).

---

## 2. Repository structure (as it stands)

```
/
├── platform/                  Laravel application
│   ├── app/
│   │   ├── Domain/            10 business domains (Accounts, Automation, Bot, Conversations,
│   │   │                      Crm, Marketing, Projects, Sales, System, Website)
│   │   │   └── <Domain>/{Actions,Enums,Jobs,Models,Policies,Services,Support}
│   │   ├── Livewire/         89 components, screen-per-component
│   │   ├── Integrations/     Google, Mcp, Telegram, Turnstile, WhatsApp
│   │   ├── Http/             22 controllers, 7 middleware
│   │   ├── Settings/         spatie/laravel-settings classes
│   │   └── Support/          AdminNavigation, Phone, Money, Install…
│   ├── database/             64 migrations, factories, seeders (incl. generated template data)
│   ├── resources/            158 Blade views, CSS, JS
│   ├── routes/               web, admin, api, console, install
│   ├── public/               front controller + 80 exported v1 pages + media assets + build
│   └── tests/                122 files (Feature + Unit + Arch)
├── docs/                     36 tracked markdown files: plans, PRD, schema, deploy, decisions, prior audit
│   └── audit/                ← this audit (00–08) + raw evidence
├── woodex/                   40 files: an earlier static design reference (not served)
├── tools/
│   ├── sandbox/              preview server + browser/route checks
│   ├── website/              audit.php, compare.php (v1↔CMS parity gate)
│   └── deploy/               Hostinger Composer hook
├── .htaccess                 serves only platform/public
├── composer.json             Hostinger deploy manifest (installs into platform/vendor)
└── README.md, PROJECT_STATUS.md
```

**Structural observations**

- The domain layout is consistent: model + action + service + policy per business area, with Livewire
  screens as the delivery layer. This is the strongest part of the codebase and should be preserved.
- There are **two copies of the frontend**: `woodex/` (early design, 40 files) and
  `platform/public/*` (the approved export, 80 URLs). Only the second is served.
- `docs/` is large (41 files) and partly stale (see 03 Q-04). The audit documents are being added
  under `docs/audit/` to follow the existing convention rather than creating a second documentation
  root; the master prompt's file names are preserved.
- `tools/` mixes a development sandbox (php-wasm + headless Chromium) with production helpers. It is
  not shipped to the web root, but it is in the deployed repository.

---

## 3. Request lifecycle

### 3.1 Public website (static-first, CMS fallback)

```
Browser
  → Apache (.htaccess): /assets, /media, /build are let through; everything else → platform/public
      → a real file exists at the path?   → serve it (the 80 exported v1 pages)
      → otherwise                         → Laravel front controller
            → routes/web.php matches?     → route (portals, sitemap, media, theme CSS, webhooks)
            → Route::fallback → SiteRouter
                  → published CMS page?   → PageRenderer (Blade sections)
                  → 301 map?              → redirect
                  → 404
```

`?preview=1` bypasses the static file only for a signed-in editor with `website.edit`; the preview
response drops `X-Frame-Options` and sets `frame-ancestors` for the admin domain.

**Consequence:** the same URL can have two sources. Today the file always wins (W-01/W-03 in `03`).
The conversion plan (`docs/13`) turns this into a migration mechanism: delete a group's files and the
CMS page starts answering. That plan is approved in writing but **not started**.

### 3.2 Admin

```
/admin/*  → [auth, active, db-current, two-factor]
           → route-level permission (`Authorize:…`) on 53 of 107 routes
           → Livewire component mount(): authorize()/policy (144 calls)
           → Domain action (transaction) → model → activity log
```

Two-factor is enforced for every admin route by `EnsureTwoFactorIsEnabled`; `db-current` blocks the
admin after a deploy with unapplied migrations until the owner runs the update screen (D-58).

### 3.3 Client portals

Signed, expiring URLs (`signed:relative`) for quotes, invoices and projects; a six-digit code page at
`/portal`; no client passwords (D-90). Throttled 5–10/min on the write paths.

---

## 4. Domain responsibilities (verified by directory contents)

| Domain | Responsibility | Key entities |
|---|---|---|
| Crm | Leads, contacts, companies, tasks, visits, assignment | `leads`, `contacts`, `companies`, `activities`, `assignment_rules` |
| Sales | Estimates, quotes, invoices, payments, documents, rate book | `estimates`, `quotes`, `quote_events`, `invoices`, `invoice_items`, `payments`, `payment_allocations` |
| Accounts | Chart of accounts, journals, periods, payment methods | `chart_of_accounts`, `journal_entries`, `journal_lines`, `accounting_periods`, `payment_method_accounts` |
| Projects | Projects, milestones, updates, handover | `projects`, `project_milestones`, `project_updates`, `project_handover_items`, `project_expenses` |
| Conversations | Inbox, messages, media, portal codes | `conversations`, `messages`, `portal_access_codes` |
| Bot | Rule-based chat bot, knowledge, usage | `bot_knowledge`, `bot_usages` |
| Automation | Automations, sequences, step runs | `automations`, `automation_steps`, `automation_enrolments`, `automation_step_runs` |
| Marketing | Social posts, scheduling | `social_posts` |
| Website | Pages, blocks, versions, templates, media, menus, redirects, SEO, theme | `pages`, `page_blocks`, `page_versions`, `page_templates`, `navigation_items`, `redirects`, `pagespeed_runs` |
| System | Audit, health, change requests, updates, imports | `activity_log`, `change_requests`, `legacy_refs`, `number_sequences` |

**95 tables** in the sandbox database; 64 migrations apply in order with no gaps (`migrate:status`).
The schema document is `docs/04-DATABASE-SCHEMA.md`; this phase did not re-verify it column-by-column
(Phase 1 deliverable `07`).

---

## 5. Authentication, authorization and tenancy

- **Users/roles:** 8 roles — owner (44 permissions), admin (41), sales_manager (27), sales (16),
  accountant (12), designer_pm (11), support (6), editor (3). 44 permissions total.
- **Policies:** Lead, Contact, Company, Quote, Invoice, Payment, Project, Conversation — registered in
  `AppServiceProvider`.
- **Route middleware:** 53 of 107 admin routes use `Authorize:<permission>`; the rest rely on
  component `mount()` authorization. **This split must be proven per route** (S-01).
- **Tenancy:** single-studio system. There is no tenant column or tenant middleware. The brief's
  "one website/tenant cannot alter another" is therefore *not applicable* today; if multi-site is
  ever in scope it is an architectural change, not a bug.
- **2FA:** middleware-enforced; recovery codes through Fortify. Lockout/recovery path scheduled for
  Phase 1.

---

## 6. Integrations

| Integration | Entry points | Credentials (names only) | State |
|---|---|---|---|
| WhatsApp Cloud API | send/receive, templates, webhook | `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_API_VERSION` | Built, signature-verified, credential-gated |
| Telegram | staff bot, digests, approvals, alerts | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET` | Built, hardened webhook |
| Google | staff OAuth sign-in, Search Console/PSI hooks | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `GOOGLE_SIGN_IN_ENABLED` | Built, key-gated |
| MCP bridge | read-only CRM API for agents | `mcp_access_tokens` table, `AuthenticateMcpBridge` | Built, token-gated |
| Turnstile | public form protection | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Built, key-gated |
| Social (Meta/LinkedIn) | planner, scheduler | provider tokens in settings | Composer built; **external publishing not implemented/approved** |
| AI provider | bot, SEO proposals, captions | key + daily cap (settings) | Dormant until key |

The Integrations screen states honestly that a provider connect flow is "not implemented in this
release" — no fake "Connected" state (I-01).

---

## 7. Background work, storage, deployment

| Concern | Implementation | Observation |
|---|---|---|
| Queue | `database` default; scheduled `queue:work --stop-when-empty --max-time=50` every minute | Works on shared hosting; no supervisor |
| Scheduler | 24 entries (heartbeat, SLA, reminders, digests, automation, WhatsApp health, backups) | Must be driven by the host cron |
| Jobs | 4 job classes | — |
| Backups | `backup:run --only-db` daily 02:30, full weekly, clean 03:30, monitor 04:00 | **Off-site destination not configured** (SYS-009b); restore drill documented, not executed here |
| Uploads | private disk `storage/app/private`, served by `/media/{id}/{name}` | Survives deploys because it is outside the web root |
| Assets | `npm run build` → `public/build` committed (hashed) | Host has no Node, so CI/host must build |
| Deployment | Hostinger Git deploy; root `.htaccess`; root composer manifest → `platform/vendor`; installer for first run | Workflow template exists in `docs/ci/deploy-hostinger.yml`, **not activated** |
| CI | `docs/ci/platform-ci.yml` | **Not active** (no `.github/workflows`) |

### Environment variable groups (names only, 54 total)

`APP_*` (name/env/key/debug/url/timezone/locale…), `ADMIN_DOMAIN`, `ADMIN_PATH`, `PUBLIC_SITE_URL`,
`ENFORCE_2FA`, `SECURITY_FRAME_OPTIONS`, `OWNER_*`, `BCRYPT_ROUNDS`, `LOG_*`,
`DB_CONNECTION`, `DB_TIMEZONE`, `LEGACY_DB_*`, `SESSION_*`, `BROADCAST_CONNECTION`,
`FILESYSTEM_DISK`, `QUEUE_CONNECTION`, `CACHE_STORE`, `MAIL_*`, `PUBLIC_LEADS_ALLOWED_ORIGINS`,
`PUBLIC_LEADS_BRIDGE_KEY`, `TURNSTILE_*`, `TELEGRAM_*`, `WHATSAPP_*`, `VITE_APP_NAME`,
`GOOGLE_*`.

No values were read. `.env` is git-ignored in this repository (verified); note the separate
`woodex420/woodex` repository does commit one (see 01 §6).

---

## 8. Dependencies — direct only

**Composer (12 runtime + 10 dev).** All permissive licences (MIT/BSD/Apache-2.0); no paid or
source-available dependency. Maintenance risk is low: Laravel, Livewire, the four Spatie packages and
Pest are actively maintained; `barryvdh/laravel-dompdf` and `brick/math` are stable.

**npm (5 runtime + 4 dev):** `alpinejs`, `@floating-ui/dom`, `apexcharts`, `preline`, `sortablejs`;
dev: `vite`, `@tailwindcss/vite`, `tailwindcss`, `laravel-vite-plugin`.

**Licence review for a builder replacement.** If Phase 8 considers a canvas editor, the candidates
named in the brief have these licences (verified from their public repositories; **no code copied**):

| Candidate | Licence | Fit note |
|---|---|---|
| Puck | MIT | React-first; would add a React island to a Livewire/Alpine admin |
| Craft.js | MIT | React, page-builder primitives, no UI |
| GrapesJS | BSD-3-Clause | Framework-agnostic, heavy, own document model; would duplicate the section model |

The existing Livewire editor has no licence cost and already satisfies the studio's typed-section
workflow. A replacement is an architecture decision for Phase 8, not a Phase 0 recommendation.

---

## 9. Architectural risks

| ID | Risk | Why it matters | Mitigation proposed |
|---|---|---|---|
| R-01 | Two sources of truth for 80 public URLs | Publishing in the CMS does not change the live page; editors can be misled | Owner decision on conversion order (docs/13) + a visible "served by static file" badge in the builder |
| R-02 | No `composer.lock` | A deploy can silently change dependency versions | Commit lock; CI `composer install --locked` |
| R-03 | CI inactive | The green suite is local only; regressions can merge | Activate the two existing workflow templates |
| R-04 | Legacy v1 static files are 23 MB of assets and 80 HTML pages inside the app's `public/` | Deploy size, and the app's web root mixes generated and legacy content | Keep until conversion; move to `public/legacy/` only as part of a group switch |
| R-05 | Demo data is empty for sales/projects/inbox | UI behaviour of those modules is untested end-to-end | Seed a small E2E fixture set |
| R-06 | Docs drift (test counts, Pint claim) | Future audits inherit false confidence | Generate status from CI, not by hand |
| R-07 | Admin on a separate domain (`admin.`), public on apex | Preview framing, cookie scope, CORS — already handled but fragile | Keep the existing CSP `frame-ancestors` tests in CI |
| R-08 | Shared hosting, no queue worker, no Node | Background work and asset build depend on cron/CI | Already designed for; document as a hard constraint in every phase plan |

---

## 10. Proposed structure for the approved main branch (recommendation, not implemented)

The current layout is sound; the proposal keeps it and fixes drift:

1. **Keep** `platform/` as the only application root and `app/Domain/*` as the business boundary.
2. **Retire** `woodex/` from the deployed repository into `docs/reference/frontend-v0/` (history is
   enough; it is not served and confuses "which frontend is master").
3. **Add** `docs/audit/` (this set) and a single `docs/audit/README.md` index.
4. **Add** `composer.lock` at `platform/` (and keep the root manifest's `require` identical).
5. **Activate** `.github/workflows/platform-ci.yml` (lint + static analysis + pest + build) and
   `.github/workflows/deploy-hostinger.yml` (manual dispatch), both already written as templates.
6. **Keep** `tools/sandbox/` out of production deploy by excluding it in the Hostinger deploy step
   (it is developer tooling, not runtime).
7. **Name** the legacy export explicitly: `platform/public` is "v1 export (serving)" until group 8 of
   `docs/13` switches; add a `public/README.md` stating that and the switch mechanism.

None of the above has been applied. Items 4–5 touch build/deploy and therefore wait for approval.
