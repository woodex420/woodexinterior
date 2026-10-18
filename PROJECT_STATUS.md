# Woodex OS — Project Status & Quick Start

**Updated:** 18 Oct 2026 · **Status: frontend-v1 wave under way (owner directive: port the marketing repo's frontend v1 into Woodex OS — no second system). Click-to-edit, the v26 mega menu, the v26 hero slider on home, the structured header/footer editor, page templates and the shared FAQ/testimonial/logo library landed. Next: SEO agent, team manager. Gate 1100 green. Backlog marathon continues after.**

One line: CRM + quotations + invoicing + payments + inbox + Telegram + website CMS + double-entry ledger core are live and gated; invoices and receipts now post to the general ledger automatically, dues have a screen with consent-gated reminders, and money can be collected from the phone. Remaining: two-brand documents/estimation, tracking boards, automation agents, MySQL migration, handover.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · Preline · SQLite (sandbox) → MySQL (production, planned) |
| Tests | **1022 passing** · 96 files (sharded gate) · Pint clean · Larastan 0 |
| Quality | Pint **607 files clean** · Larastan **0 errors** · CI workflow ready in `docs/ci/` |
| Progress log | [`platform/STATE.md`](platform/STATE.md) (chronological) · plan [`docs/27-P10-MASTER-PLAN.md`](docs/27-P10-MASTER-PLAN.md) |
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
| **Website assistant (BOT-002/003/006/007/008/010)** ⬅ newest | The chat widget's brain, shipped keyless: with no provider key every question gets an honest "a human will reply" and the team is pinged — engine, knowledge base (CMS sync + manual Q&A), code-enforced guardrails (no invented prices, dates, discounts or client data), token caps, per-thread limits and evenings-only mode are all live. Add a key in Settings → Bot and it starts thinking (Claude/OpenAI/Gemini) |
| **Follow-up sequences (AUT-001/002/005/006)** | The engine behind the studio's follow-up rhythm: ordered steps (wait → mapped WhatsApp template → task → stage move) run by a per-minute heartbeat, enrolled by real triggers (new lead, quotation sent/first viewed, invoice, visit, lead lost). Six built-in sequences, seeded editable. Nothing automated sends between 21:00–09:00 unless the client wrote first, max 2 automated messages per client per day — held sends wait visibly for the morning. Every step of every enrolment is logged; pause/resume/cancel from Automation → Sequences |
| **Media sending (CNV-008)** | Photographs, PDFs, voice notes and clips go out through WhatsApp with a caption, under the same consent + 24-hour rules as text, with the studio's own copy kept forever. The thread now renders media both directions — inbound photos were stored but invisible until now. Served through an authorized private route |
| **Inbox quick wins (P4: CNV-003b/007, WAP-008/010)** | @mentions in internal notes notify the teammate in-app and on Telegram. WhatsApp conversations carry a reply SLA (30 min first reply, 15 min after that — both configurable) that escalates like the lead SLA. A daily WhatsApp health check watches Meta's quality rating and the month's cost against a budget you set — alerting the day a number is downgraded or the budget is crossed. Media sending (CNV-008) is the one piece left for a later wave |
| **Telegram digests + quiet-hours queue (P5 S4+S8)** | Every linked staff member gets their day in one message at 09:00 (visits, tasks, follow-ups due, yesterday's leads/quotes/money-in, approvals) and the week that just ended every Monday — each line only for roles that may see it. Muted chats no longer lose alerts: everything queues (max 20) and delivers the moment the mute ends; critical notifications may bypass the mute. Switches under System → Automations |
| **Live website chat runtime (P10.2)** | Every admin page now carries a header bell: unread badge + green "visitor active" dot for website chats, 10s polling, toast with an Open-chat button when a visitor texts (never auto-opens), and a drawer to read + reply inline — claiming the thread marks it read for the whole studio. Optional two-tone ding (studio-wide switch in Settings → Notifications, default off) |
| **Sidebar workflow hubs + lead tracking (P10.1)** | One page per workflow, tabs inside: Quotations (All · Tracking · Templates), Invoices, Payments (All · Dues · Receivables), Documents (Descriptions · Design · Rate Book), Leads (List · Pipeline · **Tracking**), Contacts, Tasks & Visits, Dashboard (Overview · My Day), Reports — 49 sidebar entries became 36, every old URL 301s to its tab. The new lead tracking board groups open leads by follow-up state (Uncontacted · Due today · Overdue · Stale · Snoozed) with log-follow-up/open/WhatsApp actions and passive alerts only. Groups collapse per browser; Inbox and Approvals carry live count badges |
| **Dormant SEO/social agents (P8.5 part 5)** | Two read-only weekly digests for the owner, both OFF by default until switched on from the Automations screen (A0): the SEO health check lists pages missing meta descriptions, overlong titles and broken redirects; the social agent lists the week's handovers, fresh pages and wins as post ideas — it never posts and never names a client. The dashboard shows them as "Dormant — off by default" with a Switch-on button |
| **Monthly CRM report (P8.5 part 4)** | On the 1st at 09:00 the owner gets the month that just ended in one message — enquiries, quotations, wins, money in, outstanding, website chats, top source, and the month before for comparison — by Telegram with email fallback, using the Reports screens' own queries so the numbers always agree. Every report is archived under Automations with a "Build last month now" button; pausing the automation row stops the monthly send |
| **Web chat widget (P8.5 part 3)** | The studio's own chat bubble on every public page (no WhatsApp number needed): a rule-based intake asks name → number-or-email → what's needed, creates the lead, and fires the owner+manager alert from part 2; the team then replies from the Conversations inbox ("Website chat" channel) and the widget shows it live. Off by default — one switch in Website Settings; the static v1 pages get it through the site.js loader, CMS pages through the layout. Typing a number in chat is never treated as WhatsApp consent, and a visitor who gives an email instead is asked for their number exactly once |
| **New-enquiry alerts + ask-for-number (P8.5 part 2)** | Every enquiry instantly alerts the lead's owner AND the sales managers — Telegram with one-tap assign and WhatsApp buttons, in-app, and email for staff without a Telegram link. An enquiry that arrives with an email but no number gets exactly one branded reply asking for a WhatsApp number (a service response, never marketing) plus a follow-up task; both flows pause independently from the Automations screen |
| **Automation control dashboard (P8.5 part 1)** | Every automation on one screen — on/off per flow with attribution, cadence, and last-run health read from the scheduler's own record. Fail-safe by design: the catalog is code, a missing switch means enabled, and the schedule entries consult the switch before every run. Business automations toggleable; platform jobs (queue, heartbeat, backups, webhook recovery) shown read-only |
| **Sales tracking boards + statements (P8.4)** | Event-driven **quote tracking board** (six groups mirroring the auto-chaser: follow-up due / awaiting reply / stale after 3 chases / expiring / won / lost) with a Log-chase action that writes the same follow_up event the chaser counts · **invoice tracking board** (overdue / due this week / current / partially paid / recently settled) with **inline one-act Collect** (records + verifies + receipts + books the ledger entry) and consent-gated Remind · **client statements** — branded letterhead PDF per contact with period filter, running balance, outstanding + aging, downloadable from the contact page and the board |
| **Estimates module (P8.3)** | Persistent versioned estimates for all 6 estimator services — one server-side pricing engine (chosen finish + honest essential→premium band + rate book lines + explicit contingency), frozen rate snapshots, EST-{year}-{seq} numbers, deliberate review act, **idempotent one-click conversion to a quote draft** linked both ways (Sales → Estimations) |
| **Payments & cash (P8.2)** | Issued invoices + verified receipts **auto-post to the GL** (receivable/revenue by business line, tax to taxes payable; receipts debit the method's mapped cash/bank account) · method→account mapping screen (Accounts → Payment methods) · **Due Payments screen** (aging buckets, client exposure, consent-gated WhatsApp reminders) · **Telegram `/collect`** (confirm → verified + receipted + booked) and **`/statement`** |

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
| **P8.5** | ✅ COMPLETE — control dashboard, new-enquiry alerts + ask-for-number, web chat widget, monthly CRM report, dormant SEO/social agents. Every flow ships behind the owner's switches (A0); rule-based only, LLM layer optional later. |
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
Accounts screens: **Admin → Accounts** → Chart of Accounts · Journals · Periods · Payment methods. Sales: **Admin → Sales** → Payments · Due Payments · **Estimations**.

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
