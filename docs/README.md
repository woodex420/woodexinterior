# Woodex OS: Documentation index

The complete plan for building the Woodex sales, communication and website system. Read the documents in
this order:

| # | Document | What it answers |
|---|---|---|
| 00 | [Deep analysis](00-DEEP-ANALYSIS.md) | What exists today (this repo + the old marketingwoodex platform), what is broken, what must be preserved (142 URLs, business rules) |
| 07 | [Decisions](07-DECISIONS.md) | The owner's answers + technical decisions (D-01…D-30) + pending confirmations (P-01…P-09) |
| 01 | [Master plan](01-MASTER-PLAN.md) | Framework, architecture, admin navigation, core flows, integrations, phases P0–P9, effort, running costs, risks |
| 02 | [Master PRD](02-MASTER-PRD.md) | Every requirement with an ID, priority, phase and acceptance criteria; permissions; notifications |
| 03 | [Master system prompt](03-MASTER-SYSTEM-PROMPT.md) | Rules for AI agents/developers, phase cards, chat bot prompt, Telegram and WhatsApp specs |
| 04 | [Database schema](04-DATABASE-SCHEMA.md) | Tables, columns, conventions, v2.1 → new import mapping |
| 05 | [Design system](05-DESIGN-SYSTEM.md) | Studio tokens, Tailwind/Preline/TailAdmin mapping, components, block library per page |
| 06 | [Deployment on Hostinger](06-DEPLOYMENT-HOSTINGER.md) | Server layout, `.env`, cron, updates, webhooks, go-live checklist |
| ci | [GitHub Actions workflows](ci/README.md) | Ready-to-activate CI (tests, style, build) and one-click Hostinger deploy |

**Code:** the Laravel application lives in [`../platform`](../platform). Progress is tracked in
[`../platform/STATE.md`](../platform/STATE.md). The original static studio site in [`../woodex`](../woodex) is
kept untouched until the website cut-over (phase P6).
