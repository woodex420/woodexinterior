# Woodex OS — Project Status & Quick Start

**Updated:** 17 Oct 2026 (late) · **Local tip:** `46b5bc3` · **Status: P8.2 Payments & cash complete — sales feed the ledger; Telegram /collect and /statement live.**

One line: CRM + quotations + invoicing + payments + inbox + Telegram + website CMS + double-entry ledger core are live and gated; invoices and receipts now post to the general ledger automatically, dues have a screen with consent-gated reminders, and money can be collected from the phone. Remaining: two-brand documents/estimation, tracking boards, automation agents, MySQL migration, handover.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · Preline · SQLite (sandbox) → MySQL (production, planned) |
| Tests | **~920 passing**, 3 env-conditional skips, 0 failures · 87 files (sharded gate) |
| Quality | Pint **607 files clean** · Larastan **0 errors** · CI workflow ready in `docs/ci/` |
| Progress log | [`platform/STATE.md`](platform/STATE.md) (chronological) · plan [`docs/26-P8-MASTER-PLAN.md`](docs/26-P8-MASTER-PLAN.md) |
| Sandbox preview | port 8080 · login `owner@woodex.test` / `Wave1-Preview!` (demo data seeded) |

---

## 1. What is DONE

### Business modules (all shipped behind the full test gate)
| Module | Highlights |
|---|---|
| **CRM (P1)** | Leads, pipeline Kanban, contacts, companies, tasks, site visits, assignment rules, recycle bin, global search, client portal for lead status/reviews |
| **Quotations (P2)** | Estimate → quote builder, approval flow, revisions + revision-bound acceptance, follow-ups + chase command, frozen PDF (dompdf), quote templates, rate book, description library, document designer, **item pictures for furniture quotes** (P8.0 wave 1) |
| **Invoicing & Payments (P3)** | Invoices, milestone invoicing, payments with proof upload + locked verification, payment integrity (A0), receivables, reports kit (funnel, revenue & collections, CSV export) |
| **Inbox (P4)** | Conversations, WhatsApp connect + template manager, canned replies with variables |
| **System (P5)** | Telegram **15-command centre** + inline action buttons, health collector, audit-log viewer, change-requests board, optional owner 2FA, settings, users & roles |
| **Website CMS** | Pages/sections with drag-reorder, media, menus, SEO + redirects, theme builder + design tokens, **v2.1 legacy import with URL parity (142 URLs)**, website export/import |
| **Accounts ERP — batch C (P8.1)** | Chart of accounts (starter PKR set, ~31 accounts, business-line dimensions), monthly periods (open/close), **immutable balanced journal entries with two-way linked reversals**, integer-paisa amounts, JE-{year}-{seq} numbering, idempotent posting (`unique(source_type, source_id)`), OPEN-period rule |
| **Payments & cash (P8.2)** ⬅ newest | Issued invoices + verified receipts **auto-post to the GL** (receivable/revenue by business line, tax to taxes payable; receipts debit the method's mapped cash/bank account) · method→account mapping screen (Accounts → Payment methods) · **Due Payments screen** (aging buckets, client exposure, consent-gated WhatsApp reminders) · **Telegram `/collect`** (confirm → verified + receipted + booked) and **`/statement`** |

### Safety & security (A0 law, enforced everywhere)
- Nothing auto-posts money; nothing messages a client without consent; no agent/automation enabled without an explicit owner on-switch.
- Telegram secret-header + consent hardening, payment/receipt integrity, WhatsApp batching/recovery, quotation idempotency + stale send/accept guards, owner-only 2FA enrolment.

### Tooling & quality
- Sharded test gate (per-file wasm PHP, memory-safe), Pint, Larastan — all green at `bc587df`.
- CI: `docs/ci/platform-ci.yml` (tests + style + build) and `docs/ci/deploy-hostinger.yml` — ready to activate on GitHub.
- Sandbox rig: PHP-8.4-wasm tooling snapshotted in `~/tools/` (outside the repo) with a full rebuild README.

---

## 2. What is REMAINING

### Roadmap (docs/26 — each batch ships behind the full gate + owner sign-off)
| Batch | Scope |
|---|---|
| **P8.3 Documents & templates** ← next | Two brand profiles, quote/invoice/estimation templates, per-brand descriptions, rate book with pictures, **estimator for 4 service lines → one-click convert to quotation** |
| **P8.4 Sales tracking** | Quotation tracking board (follow-ups, stale alerts), invoice tracking board (issued → paid → overdue), client statements |
| **P8.5 Automation & agents** | New-client alert + ask-for-number flow, **web chat widget now** (WhatsApp Cloud API replies once the business number is approved), monthly CRM report, automation control dashboard; rule-based only — LLM layer optional later |
| **P8.6 Runtime, portal & handover** | **MySQL migration** (production only, after GL stabilises, before 2025 financial staging), projects board, client portal, handover pack |
| **P9 tail** | Security review, load pass, restore drill, Urdu templates, training, final v2.1 sync |
| Deferred | V2-2 part 2 website builder polish (inline editing, section thumbnails, campaign free-canvas) |

### Owner actions (blocked on you, not on code)
1. **Deploy `bc587df` to production** (Hostinger git deploy — see Quick Start §C) and verify the admin + accounts screens.
2. Activate CI: add `docs/ci/platform-ci.yml` (or `/tmp/gha/gate.yml`) as `.github/workflows/` via the GitHub web UI.
3. Import the Jan-2025 lead CSV in production (already imported in sandbox; 8 contacts held for review).
4. 2025 financial files (invoices, receipts, supplier bills, expenses, bank statements, balances) — needed before 2025 financial staging.
5. WhatsApp business-number approval (unblocks full Cloud API client replies).
6. P5 exit-day checklist items.

---

## 3. Quick start

### A. Sandbox preview (already running in this workspace)
Preview process on port **8080** → login `owner@woodex.test` / `Wave1-Preview!`
Seeded staff: manager / sales1 / sales2 / accounts (passwords in the session notes).
Accounts screens: **Admin → Accounts** → Chart of Accounts · Journals · Periods · Payment methods. Money: **Admin → Sales** → Payments · Due Payments.

### B. Local development (native PHP)
```bash
cd platform
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # SQLite by default
npm install && npm run build        # assets
php artisan serve                   # http://127.0.0.1:8000
vendor/bin/pest                     # full suite
vendor/bin/pint && vendor/bin/phpstan analyse --memory-limit=1G
```

### C. Production deploy (Hostinger, no SSH)
1. hPanel → website owning the target folder → **Advanced → Git** → this repo → root = the site folder (test: `interior.woodex.com.pk`, not `public_html`) → **Deploy**.
2. Open the site → the installer walks through database + owner account.
3. After deploys with new migrations: login → one-click **Database update** (System → Updates).
Full runbook: [`docs/06-DEPLOYMENT-HOSTINGER.md`](docs/06-DEPLOYMENT-HOSTINGER.md).

### D. Test gate (wasm sandbox — file-by-file, memory-safe)
```bash
bash ~/tools/shard-gate.sh        # in the runtime app; backs up DB first
```
Never run the whole suite in one process inside the runtime copy (a test reconfigures the DB connection mid-suite and a later `migrate:fresh` wipes the preview DB). Runtime `tests/TestCase.php` must keep `public $mockConsoleOutput = false;` (wasm rig only — never commit).

---

## 4. Documentation index
| Need | Read |
|---|---|
| What to build next, in order | [`docs/26-P8-MASTER-PLAN.md`](docs/26-P8-MASTER-PLAN.md) |
| Every pending task with IDs | [`docs/16-MASTER-PENDING-LIST.md`](docs/16-MASTER-PENDING-LIST.md) |
| Full history of what shipped | [`platform/STATE.md`](platform/STATE.md) |
| Requirements per feature | [`docs/02-MASTER-PRD.md`](docs/02-MASTER-PRD.md) |
| Database design | [`docs/04-DATABASE-SCHEMA.md`](docs/04-DATABASE-SCHEMA.md) |
| Owner decisions D-01…D-116 | [`docs/07-DECISIONS.md`](docs/07-DECISIONS.md) |
| Deployment / integrations | [`docs/06`](docs/06-DEPLOYMENT-HOSTINGER.md) · [`Telegram`](docs/TELEGRAM-SECURITY-REPAIR.md) · [`Google OAuth`](docs/GOOGLE-OAUTH-SETUP.md) · [`MCP`](docs/INTEGRATIONS-MCP-PLAN.md) |
| CI | [`docs/ci/README.md`](docs/ci/README.md) |
