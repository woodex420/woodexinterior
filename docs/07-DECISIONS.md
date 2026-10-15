# Woodex OS: Decision Record (ADR log)

**Date:** 15 October 2026 · **Status:** Accepted unless marked *Pending*
**Read with:** `00-DEEP-ANALYSIS.md` (why) → `01-MASTER-PLAN.md` (how/when) → `02-MASTER-PRD.md` (what)

Each decision below is binding for every human or AI contributor. To change a decision, add a new entry
that supersedes it. Do not edit an old entry silently.

Legend: **U** = decided by the owner (answers to the analysis questions) · **T** = technical decision
derived from U-decisions + hosting constraints · *Pending* = needs owner confirmation.

---

## A. Owner decisions (from the analysis questionnaire)

| ID | Decision | Source |
|---|---|---|
| **D-01** | **Admin = custom TailAdmin shell + Livewire 4 + Preline v5 components.** Not Filament. Every screen is purpose-built for Woodex sales work. | U · Q1 |
| **D-02** | **Website = keep all 142 live URLs and their content**, redesigned with Preline blocks in this repository's **"Studio" look** (Nohemi + Inter, navy `#0F1E36` / beige `#FCF2E8`). | U · Q2 |
| **D-03** | **Website customisation = design-token theme builder + block page builder** (choose blocks, fill fields, reorder; no free drag-and-drop canvas). Every page stays on-brand and fast. | U · Q3 |
| **D-04** | **WhatsApp = official WhatsApp Business Cloud API (Meta)**: two-way inbox, approved templates, automated follow-ups, quote/invoice delivery. Click-to-chat links stay as a fallback on the website. | U · Q4 |
| **D-05** | **Social = Facebook + Instagram full** (unified inbox: Messenger, IG DMs, comments; Lead Ads → CRM; post scheduler + content calendar) **plus LinkedIn** (company page posting, comments where the API allows, Lead Gen forms when access is approved). | U · Q5 |
| **D-06** | **Rollout = sales system first, with migration of the old v2.1 data.** Order: CRM → Quotations → Invoices → WhatsApp/Telegram, on an **admin subdomain**; then website + theme builder; then social. | U · Q6 |

## B. Technical decisions

| ID | Decision | Why |
|---|---|---|
| **D-07** | **One Laravel 13 application** (modular monolith). Repository layout: `platform/` (the app), `docs/` (plans), `woodex/` (current static site, untouched until the website cut-over in P6). | One codebase, one deploy, one database. Fits shared hosting. |
| **D-08** | **PHP 8.4** in production and CI. Hostinger hPanel offers PHP 8.2–8.5 (8.3 default), so select 8.4 per site. | Laravel 13 needs ≥ 8.3. Current Spatie/Pest releases need 8.4. |
| **D-09** | **Livewire components are class-based** (`app/Livewire/**` + `resources/views/livewire/**`). Emoji filenames are switched off (`make_command.type=class`, `emoji=false`). | ASCII-only paths are safe for Hostinger File Manager, zip and FTP. Classes are easier to test and give AI agents a predictable pattern. |
| **D-10** | **Front-end build = Vite 8 + Tailwind CSS 4.3 + laravel-vite-plugin 3** (Laravel 13 skeleton defaults) **+ Preline 5**. Assets are built locally or in GitHub Actions; `public/build` is shipped. **No Node.js on the server.** | Hostinger shared hosting has no Node runtime. |
| **D-11** | **Do not load Alpine twice.** Admin pages get Alpine from Livewire 4 (bundled). Public pages do not load Livewire; they load a small Alpine bundle of their own. | Livewire 4 docs warn that a second copy of Alpine breaks initialisation. |
| **D-12** | **Preline JS is re-initialised after Livewire DOM updates** (`HSStaticMethods.autoInit()` on `livewire:navigated` and after morphs). | Without this, Preline dropdowns, overlays and tabs die after `wire:navigate`. |
| **D-13** | **Public interactivity uses stateless JSON endpoints + Alpine** (lead forms, estimator, booking, chat widget), not Livewire. Throttle + honeypot + Cloudflare Turnstile. | Public pages are full-page cached (LiteSpeed). Livewire/CSRF does not survive page caching. |
| **D-14** | **Money = integer minor units (paisa) in `BIGINT`**, currency PKR. Quantities = `DECIMAL(14,3)`. All totals are computed **server-side** (`brick/math` for multiply/round). The browser never decides a total. | The old system calculated money in browser JavaScript and stored it in JSON blobs. That made reporting impossible and was easy to tamper with. |
| **D-15** | **Sent documents are immutable.** Sending a quotation creates a `quote_versions` snapshot (JSON + PDF hash). Edits after sending create a new revision (`WI-10123 R1`). | Audit trail. The client always sees exactly what was sent. |
| **D-16** | **Numbering:** Interior and Project jobs use **WI-10100+** (quotes; the invoice reuses the quote number + milestone suffix, e.g. `WI-10123-1`). Furniture uses its own counter, **WF-10050+**. Receipts use `RC-2026-0001`. Counters live in `number_sequences` with a row lock. Prefixes and start values can be changed in Settings. | Carries the existing Woodex practice forward. |
| **D-17** | **Queue = `database` driver drained by cron** every minute: `schedule:run`, which runs `queue:work --stop-when-empty --max-time=50`. No daemons. | Shared hosting cannot keep a worker running. |
| **D-18** | **Real-time = Laravel Broadcasting with a hosted Pusher-protocol service** (Pusher Channels or Ably free tier). If no key is configured, the UI falls back to `wire:poll.visible`. | Reverb needs a long-running process, which shared hosting cannot run. The fallback means the system never depends on the paid tier. |
| **D-19** | **PDF = dompdf** (pure PHP) with print-specific Blade templates. Urdu PDFs are a risk: test early, and keep mPDF as the fallback. | No headless Chrome on shared hosting. |
| **D-20** | **Auth = Laravel Fortify** (headless) with TailAdmin-styled screens. **TOTP 2FA is required** for owner, admin and accountant. | Money and personal data live in the system. |
| **D-21** | **Permissions = spatie/laravel-permission.** Roles: `owner`, `admin`, `sales_manager`, `sales`, `designer_pm`, `accountant`, `editor`, `support`. | Covers the roles found in v2.1, plus the PM and accountant roles it was missing. |
| **D-22** | **Settings = spatie/laravel-settings** (typed groups). Third-party secrets are stored **encrypted** (`encrypted` settings) or in `.env`, **never in Git**. | The old system kept 28 JSON side-files in `_private/`, and its public repo leaked credentials. |
| **D-23** | **Integrations sit behind interfaces** (`WhatsAppGateway`, `TelegramGateway`, `MetaGraph`, `LinkedInGateway`, `AiProvider`, `RealtimeBroadcaster`). Each one is switched on by entering keys in Settings and has a **"Test connection"** button. | Lets each integration be swapped or switched off without code changes. Tests use fakes. |
| **D-24** | **AI provider is abstracted.** Default: Anthropic Claude (the reference project's choice); OpenAI or Gemini optional. Each provider has a daily cost cap and per-conversation turn limits. | Keeps costs under control and avoids lock-in. |
| **D-25** | **Data migration = one-way importer** `php artisan woodex:import-v21` reading a read-only `legacy` DB connection. It supports dry-run, idempotent re-runs, row counts, money checksums and a CSV of warnings. Imported users must **set new passwords**. | Old credentials were exposed publicly. Re-runnable until cut-over day. |
| **D-26** | **SEO continuity:** all 142 URLs answer 200 with a trailing slash; non-slash requests 301 to the slash version. A CI "URL parity" test fails the build if any URL is missing. | Protects the existing Google rankings. |
| **D-27** | **Licences:** Preline free blocks (MIT + Fair Use: attribution in the footer credits page and in `docs/`), and TailAdmin (MIT). **Preline Pro blocks are used only if a Pro licence is bought** (*Pending*). | Legal safety. |
| **D-28** | **Quality gates:** Pest 5 tests, Larastan (level 5 in P0, level 6 from P1) and Pint on every push via GitHub Actions on PHP 8.4. Tests are blocking from day one; Pint and Larastan become blocking after the P0 clean-up pass. A phase is not "done" until CI is green. | No PHP runtime is guaranteed on the owner's machine. CI is the referee. |
| **D-29** | **Domains:** new app first on **`admin.woodex.com.pk`** (staff, P0–P5). The public website moves to the apex domain at the P6 cut-over. A **staging** subdomain mirrors production. | Matches D-06. The live site keeps working untouched until P6. |
| **D-30** | **Time zone `Asia/Karachi`, locale `en`; client-facing messages in English + Urdu.** Phone numbers are stored in E.164 (`+923…`); local formats are normalised on input. | Pakistan-first business. Phone is the identity key for dedupe. |

## C. Pending owner confirmations (defaults in force until confirmed)

| ID | Item | Default used now | Alternatives seen |
|---|---|---|---|
| **P-01** | Main WhatsApp/phone number | **+92 322 4000768** (live site, this repo) | +92 322 4200168 (reference plan D6) |
| **P-02** | Public email | **woodexinterior.pk@gmail.com** | info@woodex.com.pk |
| **P-03** | Studio hours | **Mon–Sat 10:00–19:00** | 10:00–19:30 |
| **P-04** | Preline Pro licence | **Free blocks only** | Buy Pro for the full 740 premium blocks |
| **P-05** | Hostinger plan + SSH access | Business/Cloud plan with SSH, cron, MySQL assumed | Must be confirmed before P0 deploy |
| **P-06** | Credential rotation done? | **Not done** (blocking for go-live) | Rotate every key that appeared in the old public repo |
| **P-07** | WhatsApp number strategy | Use the **same number** via Meta's Business App coexistence *if available for the number*; else a **new API number** | Migrate the existing number fully to the API |
| **P-08** | Bank / wallet details for documents | Entered by owner in Settings (never in code) | — |
| **P-09** | Nohemi font licence (Pangram Pangram) for commercial web use | Used now (already on the studio site); confirm before website launch | Swap heading font in the theme builder (no code change) |

All P-items are Settings values (or a one-time setup step), so changing them later needs no code.
