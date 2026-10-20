# 18 · Wave 0 baseline — freeze, inventory and safety

_Working paper for Wave 0 of the owner's master plan (`docs/audit/17_OWNER_MASTER_PLAN_2026-10-20.md`,
§“Wave 0 — Freeze, Inventory and Safety Baseline”). It answers one question: **what exactly is being
migrated, where does each piece go, and what must be true before real rows move.**

Everything here is **Verified** against the code at `175b7b4` and the v2.1 source clone
(`marketingwoodex-cloud`, SHA `63868e0`), except where a line says otherwise. Nothing here is a
schema change; the only code touched is configuration plus one rehearsal fixture._

---

## 1. The numbers that frame the migration

| | Woodex OS (target) | v2.1 (source) |
|---|---|---|
| Screens / admin modules | **90** Livewire components, 25 controllers | **60** admin JS modules (+ `seo-analyzer.js`) |
| Database | 65 migrations, one hand-written schema per domain | **44** distinct `wx_*` tables — 20 in the v2.0 dump, 24 more across 16 migrations |
| API | 14 public/API routes, one studio seam | **35** route files under `api/v2/routes/` |
| Website screens | 23 target, **4 live** (builder, client logos, blog, portfolio) | 21 Website modules in `admin-v21/` |
| Settings | `spatie/laravel-settings` groups (`BusinessSettings`, `SalesSettings`, `BotSettings`, …) | `wx_settings` key/value + **4** JSON stores + `_private/*.json` secrets |
| Lockfile | **none** — supply-chain gap Q-01 | n/a |

These counts are the size of the job, not an estimate of effort: 23 Website screens are already
scoped screen-by-screen in `16_MASTER_TODO.md` §16.8, and the CRM imports already run.

---

## 2. Where every v2.1 table goes

Three destinations, and nothing is left undefined.

### 2.1 Imported today by `woodex:import-v21` (rehearsed, not yet run on real rows)

| v2.1 table | OS destination | Notes |
|---|---|---|
| `wx_users` | `users` + roles | Roles `admin`/`editor`; unknown roles fall back to `support` with a warning. Passwords are **never** copied |
| `wx_clients` | `contacts` (client type) | Matched by phone/email, `became_client_at` from `created_at` |
| `wx_leads` | `leads` | Stage vocabulary `new, contacted, visit, quote, hold, won, lost` maps to `PipelineStage`; unknown stage → New + warning |
| `wx_lead_notes` | `activities` | `sys=1` notes become system entries; a note whose lead was not imported is skipped and reported |
| `wx_bookings` | `bookings` | A booking without an enquiry gets a lead (`V21-B000xx`); an impossible time is skipped, never guessed |

### 2.2 Imported with their module (nothing to do in Wave 0 beyond confirming the map)

| v2.1 tables | OS owner | When |
|---|---|---|
| `wx_templates` | Quote templates | P2 (Sales) |
| `wx_quotes` | Quotations | P2 |
| `wx_invoices`, `wx_invoice_payments` | Accounts | P3 |
| `wx_chats`, `wx_chat_msgs` | Inbox / conversations | P4 |
| `wx_projects` | Projects | P8 |
| `wx_pages`, `wx_blocks`, `wx_page_versions`, `wx_content`, `wx_content_tpl`, `wx_media_alt`, `wx_media_trash`, `wx_redirects`, `wx_r404`, `wx_chrome_versions`, `wx_placements`, `wx_placement_sets` | Website (pages, blocks, media, redirects, menus) | **Already imported** — the OS holds 143 pages (48 insights, 60 service, 12 city, 6 project), 50 page templates, 103 media rows and the homepage blocks. Menus, redirects and saved sections are empty in the sandbox, so their v2.1 source rows still have to be identified before they are claimed |
| `wx_settings` | `BusinessSettings` / `WebsiteSettings` | Wave 1 (canonical settings) |
| `wx_roles`, `wx_role_groups`, `wx_user_perms`, `wx_action_groups`, `wx_action_roles`, `wx_action_shared`, `wx_approvals` | OS permissions (spatie) + approvals | P5 (System) |

### 2.3 Never imported

| v2.1 table | Why |
|---|---|
| `wx_secrets` | Secrets never leave the old server. Rotate, do not copy (`docs/08 §1`) |
| `wx_sessions`, `wx_login_attempts`, `wx_throttle` | Runtime state, not data |
| `wx_notify_log`, `wx_tg_map`, `wx_tg_seen`, `wx_wa_seen` | Delivery bookkeeping; recreating it would cause duplicate notifications |
| `wx_activity` | Superseded by the OS activity log |
| `wx_ai_events`, `wx_ai_unans` | AI telemetry tied to keys being retired |
| `wx_migrations` | Foreign migration bookkeeping |

### 2.4 The four JSON stores in `api/v2/data/`

| File | Meaning | Destination | State |
|---|---|---|---|
| `chat-rules.json` | Bot intents and canned answers | `BotSettings` (rules) | Not imported — small, manual review is cheaper than a mapping |
| `connectors.json` | Third-party connector switches | `IntegrationSettings` | Not imported; every connector is re-authorised |
| `quote-starters.json` | Quote line presets | `SalesSettings` / templates | Import with P2 |
| `redirect-plan.json` | Planned redirects | `redirects` table | Compare with the imported `wx_redirects` before cut-over |

`_private/*.json` (28 side-files in v2.1) held credentials and are **out of scope by design** — the repo
carries only `db.example.json`.

---

## 3. Staging and the first dry-run report (done, on a rehearsal database)

Wave 0 item 7 asks for a dry-run report. The real report needs the real dump; **the pipeline that
produces it is now rehearsed end to end** so the real run has no surprises.

What changed:

- `config/database.php` → the `legacy` connection takes `LEGACY_DB_DRIVER` (`mysql` by default) and
  `LEGACY_DB_SQLITE_PATH`, so a restored dump can be a MySQL server **or** an SQLite file.
  Production behaviour is unchanged when the variables are absent.
- `.env.example` documents both variables.
- `platform/database/rehearsal/build-v21-rehearsal.php` builds the five tables with the v2.1 column
  shapes and the real vocabularies, and fills them with **synthetic** rows (3 users, 3 clients,
  12 leads, 20 notes, 6 bookings) that deliberately include the awkward cases: a lead with no phone
  or email, an invalid phone, a note whose lead is missing, a booking with no enquiry, a booking with
  an impossible time.

Commands (run in the sandbox, PHP 8.4):

```sh
php database/rehearsal/build-v21-rehearsal.php storage/app/private/rehearsal-v21.sqlite

LEGACY_DB_DRIVER=sqlite LEGACY_DB_SQLITE_PATH=…/rehearsal-v21.sqlite \
  php artisan woodex:import-v21 --dry-run
```

Result — `users 3/3, clients 3/3, leads 12/12, notes 20 source → 19 created + 1 skipped, bookings 6/6`,
**0 failed**, 5 warnings (exactly the five deliberate edge cases), 1.4 s, report written to
`storage/app/private/imports/<timestamp>-dry-run/` as `report.json`, `warnings.csv`, `duplicates.csv`,
and **the transaction rolled back** — the app database was unchanged afterwards.

Verbatim output: `docs/audit/evidence/wave0-import-rehearsal.txt`.

What this proves: the command path, the vocabulary maps, the warning/report machinery and the rollback
work. What it does **not** prove: anything about real volumes, duplicates, encoding or phone formats —
that is what the first real dry run is for. The rehearsal rows are fictional and are labelled as such
in the fixture.

Against the real dump the same command is:

```sh
# read-only user on the v2.1 database (docs/08 §1), never the writer account
php artisan woodex:import-v21 --dry-run
```

The report lands in `storage/app/private/imports/` and is attached to the Wave 0 sign-off.

---

## 4. Canonical business settings (inventory only; the change is Wave 1)

`app/Settings/BusinessSettings.php` is the intended single source. Today the same facts are typed in
more than one place:

| Fact | Where it appears now | Count |
|---|---|---|
| Phone `+92 322 4000768` | views (`holding`, `lead-form`, CRM empty states), `LegacySiteBridgeController`, tests | 40 occurrences, 4 non-test files |
| Address / hours / email | site footer partials, `holding`, contact page | 3 partials |
| Currency and payment terms | `SalesSettings`, quote/invoice views | 2 places |

Wave 1 moves the four non-test views onto `BusinessSettings` and leaves the tests asserting the
canonical value, so the next change of phone number is one setting, not a search.

---

## 5. Credentials to rotate (owner action; list produced here)

Nothing is exposed in this repository — scan of `platform/` for key-shaped strings is clean, `.env` is
untracked and `.env.example` carries empty values. What must be rotated is what the **old** system
still holds, because v2.1's public repo leaked its secrets (`docs/00 §7`, `docs/01` risk table, D-22).

| # | Credential | Lives today in | Action |
|---|---|---|---|
| 1 | v2.1 MySQL user + password | `_private/db.json` on the old host | New read-only user for the import; old writer password changed at cut-over |
| 2 | SMTP user + password | old host `.env` | Rotate at the provider, then set `MAIL_*` |
| 3 | WhatsApp token, phone-number id, WABA id, app secret | `wx_settings` / old `.env` | Rotate in Meta; set `WHATSAPP_*` |
| 4 | Telegram bot token + webhook secret | `wx_settings` / old `.env` | `/revoke` in BotFather, new token, new `TELEGRAM_WEBHOOK_SECRET` |
| 5 | Turnstile site + secret key | old host `.env` | New widget keys; `TURNSTILE_*` |
| 6 | Public leads bridge key | old host `.env` | New random `PUBLIC_LEADS_BRIDGE_KEY`; the old one is dead |
| 7 | AI provider key(s) | `wx_settings`, `_private/` | Rotate, then reuse one key for content AI (`BotSettings`) with its own daily cap |
| 8 | Production `APP_KEY` and session cookie | old host | New `php artisan key:generate` on the new server only |
| 9 | Builder passcode (`X-WX-ADM`) | v2.1 builder source | Never ported; the OS session is the only gate |
| 10 | Demo owner password `Woodex-Preview-2026` | `DemoSeeder` | Preview only; the installer creates the real owner, and this seeder must not run in production |

Items 1–8 need a console I do not have (hPanel, Meta, BotFather, Cloudflare, the AI provider). I can
generate the values; only the owner can install them.

---

## 6. Lockfile and CI

- **`composer.lock` (Q-01):** cannot be generated in this sandbox — there is no `composer` binary and
  `repo.packagist.org` is not reachable. Two honest paths: the owner runs `composer update --lock` once
  and commits the file, or CI does it — `.github/workflows/platform-ci.yml` already falls back to
  `composer update` when no lock exists and prints a gzip+base64 copy of the generated lock for
  committing. The second path costs one CI run and no local setup.
- **CI:** `platform-ci.yml` runs Pest, Pint, Larastan level 6 (blocking since P1) and the Vite build on
  every push and PR under `platform/`; `deploy-hostinger.yml` is a manual, secret-gated deploy that
  backs up the database before it touches anything. Both files are written and verified against the
  local runs (287 → **276** tests after the deleted screens, Pint PASS, PHPStan 0).
- **Blocked:** the GitHub App behind this workspace is refused permission to create or update files
  under `.github/workflows/` (“without `workflows` permission”). The files are therefore untracked in
  the working copy. Either grant that permission or commit the two files manually; until then CI does
  not run on this branch.

---

## 7. Wave 0 exit checklist

| # | Item | State | Blocked on |
|---|---|---|---|
| 1 | Staging database | **Ready** — `legacy` connection accepts a MySQL copy or an SQLite dump; rehearsal ran on the latter | A real dump / read-only credentials |
| 2 | Inventory of tables, stores, screens, routes, APIs | **Done in this document** (§1, §2) | — |
| 3 | Canonical business settings | **Inventoried** (§4) | Wave 1 work |
| 4 | Rotate old credentials | **List issued** (§5) | Owner consoles (items 1–8) |
| 5 | `composer.lock` | **Unblocked by CI fallback**; not in the repo yet | One CI run, or `composer update --lock` |
| 6 | Activate CI | **Written, cannot be pushed** | GitHub `workflows` permission |
| 7 | First dry-run import report | **Pipeline rehearsed** (§3), report format proven | The real dump |

Wave 0 closes when 1, 5, 6 and 7 have real artefacts attached. Items 2 and 4 are done as documents;
item 3 finished Wave 1's inventory and is implemented there.

---

## 8. What happens the moment the dump arrives

1. Restore it to a scratch MySQL database (or export to SQLite) and point `LEGACY_DB_*` at it.
2. Run `woodex:import-v21 --dry-run`, attach `report.json` + `warnings.csv` to Wave 0.
3. Reconcile the counts against `SELECT COUNT(*)` on each of the five tables — the number in the
   report must equal the number in the source, and every skip must have a reason in `warnings.csv`.
4. Only then: run without `--dry-run` on **staging**, compare a sample of clients/leads/notes by eye,
   and take a database snapshot before and after.
5. Wave 1 (canonical settings, sidebar regroup, Website screen port) proceeds in parallel — it does not
   depend on the dump.
