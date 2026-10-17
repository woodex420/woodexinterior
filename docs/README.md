# Woodex OS: Documentation index

The complete plan for building the Woodex sales, communication and website system.

**Current audit and next-work entry point (17 October 2026):**

| Document | Purpose |
|---|---|
| [12 — Operating-system audit](12-OPERATING-SYSTEM-AUDIT.md) | Evidence-backed P1–P3 requirement traceability, integration/automation inventory, reproduced defects and live-verification boundaries |
| [13 — Master agent and automation plan](13-MASTER-AGENT-AUTOMATION-PLAN.md) | Approval-gated Telegram control, specialist agents, complete customer funnel, separate dashboards and Hostinger/worker comparison |
| [14 — Completion backlog](14-AUTOMATION-COMPLETION-BACKLOG.md) | 75 work packages with priorities, owners, dependencies and full acceptance gates; local A0 work started |
| [15 — First A0 implementation](15-A0-SAFETY-IMPLEMENTATION.md) | Local payment/receipt integrity and WhatsApp batching/recovery repairs, regression evidence, rollout and remaining gates |
| [16 — Telegram and consent hardening](16-TELEGRAM-CONSENT-HARDENING.md) | Next local B08 batch: sender identity, secret-header migration, rate limits, sticky opt-outs and regression evidence |
| [17 — Quotation safety foundations](17-QUOTATION-IDEMPOTENCY-START.md) | First local B04 slice: stale send/accept guards, revision-bound acceptance, milestone uniqueness/numbering, PDF/queue rollback evidence and refreshed 8080 preview; remaining approval/concurrency gates explicit |
| [18 — Basic Sales + Accounts ERP](18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md) | Owner-approved scope and phased balanced-ledger plan; actual January lead-import/core-locking delivery, MySQL gate and unimplemented accounting modules clearly separated |
| [19 — Documents + estimation implementation PRD](19-DOCUMENTS-ESTIMATION-IMPLEMENTATION-PRD.md) | Complete Interior/Furniture document/estimate/Accounts roadmap, WF-10153 design analysis, first library/design/in-window preview implementation, runtime setup limits and remaining gates |
| [Audit evidence](audit-evidence/2026-10-17-results.md) | Seven isolated behavior probes / 22 assertions; observations of defects, not repaired-feature tests |

These documents distinguish local implementation/tests from unverified deployment and business acceptance. Historical phase-completion claims and old estimates below are not current delivery certificates. The baseline audit made no feature changes; the subsequently authorized A0 batches implement local safety repairs only. These changes have not been deployed to Hostinger; the local port 8080 preview has been refreshed.

**Existing integration setup guides:** [Telegram repair](TELEGRAM-SECURITY-REPAIR.md) · [integrations and read-only MCP](INTEGRATIONS-MCP-PLAN.md) · [Google identity OAuth and staff sign-in](GOOGLE-OAUTH-SETUP.md).

Original architecture and requirement references, in reading order:

| # | Document | What it answers |
|---|---|---|
| 00 | [Deep analysis](00-DEEP-ANALYSIS.md) | What exists today (this repo + the old marketingwoodex platform), what is broken, what must be preserved (142 URLs, business rules) |
| 07 | [Decisions](07-DECISIONS.md) | The owner's answers + technical decisions (D-01…D-98) + pending confirmations (P-01…P-16) |
| 01 | [Master plan](01-MASTER-PLAN.md) | Framework, architecture, admin navigation, core flows, integrations, phases P0–P9, effort, running costs, risks |
| 02 | [Master PRD](02-MASTER-PRD.md) | Every requirement with an ID, priority, phase and acceptance criteria; permissions; notifications |
| 03 | [Master system prompt](03-MASTER-SYSTEM-PROMPT.md) | Rules for AI agents/developers, phase cards, chat bot prompt, Telegram and WhatsApp specs |
| 04 | [Database schema](04-DATABASE-SCHEMA.md) | Tables, columns, conventions, v2.1 → new import mapping |
| 05 | [Design system](05-DESIGN-SYSTEM.md) | Studio tokens, Tailwind/Preline/TailAdmin mapping, components, block library per page |
| 06 | [Deployment on Hostinger](06-DEPLOYMENT-HOSTINGER.md) | Server layout, `.env`, cron, updates, webhooks, go-live checklist |
| 08 | [v2.1 import runbook](08-V21-IMPORT-RUNBOOK.md) | Moving users, clients, leads, notes and bookings from the old system: dry run, real run, checks, value conversions |
| 09 | [Website frontend plan](09-WEBSITE-FRONTEND-PLAN.md) | The complete website: CMS architecture, the 142-URL map and 54 redirects, page types and section blueprints, the six service definitions, city/insight/project rules, SEO + AIO/GEO + E-E-A-T, tracking, the import pipeline and the delivery batches |
| ci | [GitHub Actions workflows](ci/README.md) | Ready-to-activate CI (tests, style, build) and one-click Hostinger deploy |

**Code:** the Laravel application lives in [`../platform`](../platform). Progress is tracked in
[`../platform/STATE.md`](../platform/STATE.md). The original static studio site in [`../woodex`](../woodex) is
kept untouched until the website cut-over (phase P6).
