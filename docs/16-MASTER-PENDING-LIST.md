# 16 · Master pending list — P1 → P7

_The one list both agents run from. Owner of every item is named; status is checked against the code, not
against the plan documents. Detail lives in docs/14 (P4/P5 overview) and docs/15 (P5/P6 steps) — this file is
the master index and the team plan._

**Lanes:** **A** = the automation agent (separate workspace) · **W** = this workspace · **O** = the owner, no
code · **A+W** = handshake. Working rule: an agent works only inside its lane's directories, so two agents can
run at once without collisions.

---

## 1 · Where the other agent's work slots in

The automation agent is producing, in their own workspace:

1. **Operating-system audit** — P1–P3 requirement coverage, integrations, existing automation, gaps, evidence.
2. **Master agent architecture** — Telegram control, approval rules, specialist agents, customer funnel,
   dashboards, hosting comparison.
3. **75-package completion backlog** — priorities, owners, dependencies, acceptance criteria.

None of it is in this repository yet (checked 17 Oct: no commits, branches or files). When it lands, this
happens to it — that is the "check his work" protocol, agreed in advance:

- **Coverage against code:** every claim of "built" must point at a file or a green test in this repo; claims
  that don't match the code get corrected in this list, not argued.
- **No plan-of-record conflicts:** decisions go in docs/07 (next D-number) — architecture that contradicts a
  recorded decision (D-105…D-115) is flagged to the owner, not silently adopted.
- **Gate parity:** their code arrives with Pest + Larastan level 6 + Pint green, like everything here.
- **Merge order:** their branch rebases onto the shared line; one phase of work per review.

Their lane (agreed scope): `app/Domain/Automation/` (new), the bot engine and knowledge base, alert rules,
digests' content, and their three documents. W's lane: everything in `app/Domain/System/Telegram/`,
`app/Domain/Conversations/`, sales/CRM screens, reports, and the whole website v2. Where a piece of their
architecture wants the Telegram command layer (S3 actions, buttons), the interface is the `TelegramCommandBus`
built in `3fc70c7` — they extend it, they do not fork it.

---

## 2 · P0/P1 — Foundation & CRM: what is genuinely left

The CRM, capture, importer, SLA and reminders are live and tested. Remaining, verified against code:

| ID | Task | Lane | Blocks / notes |
|---|---|---|---|
| SYS-007 | **Audit-log viewer**: `audit.view` permission exists, no screen uses it. Filters (user, model, action, date) + export | W | Compliance; small |
| SYS-009b | **Backups off-site**: `backup:run` is scheduled but no off-site destination is configured (no `config/backup.php`); Google Drive/S3 target + alert on failure | W + O | Restore drill is P9; needs the owner's Drive/S3 account |
| TUR | **Turnstile live keys** — spam protection shows "off" until the owner creates the account and pastes keys | O | Public forms run without it today |
| WEB-007b | **Booking slots configured** — the booking bridge answers "paused"; slots per area/day and real availability are a Settings job + owner content | W + O | BOT-005 depends on it |
| P1 polish | Returning-client enquiry alert (TGM-002 row) exists only in the matrix; confirm or build | W | Check with the alert-rules work (S7) |

## 3 · P2 Quotations — closed, one owner item

All three owner-ordered steps shipped (`926ec75`, `f353412`, `7062a5a`). Live WhatsApp sending waits on
credentials, which the owner has marked **no-go for now** (17 Oct) — nothing code-side is blocked by this; the
quotation link still sends by the configured fallback template once a number is connected.

| ID | Task | Lane |
|---|---|---|
| O | Meta credentials + template approval when the studio is ready (docs/14 §6) | O |

## 4 · P3 Invoices & payments — the reports are the gap

Invoices, payments, verification, projects-lite: shipped and tested. The **report screens are placeholders**
(verified: `AdminNavigation` points `Receivables`, `Sales Funnel`, `Revenue & Collections` at `null`):

| ID | Task | Lane | Notes |
|---|---|---|---|
| RPT-002 | **Sales Funnel**: stage conversion by period/line/source/owner, drop-off reasons | W | Data exists (`leads`, `stages`, `lost_reason`) |
| RPT-005 | **Revenue & Collections**: quoted/accepted/invoiced/collected per month, by line and bank, receivables aging | W | `reports.finance` permission already exists |
| INV-005b | **Receivables screen**: the aging table + follow-up entry point | W | Same data as RPT-005 aging — build once, use twice |
| RPT-009 | Date-range presets, comparison, CSV export, print layout — shared by all three | W | Build into the reports kit |

## 5 · P4 Conversations — remainder (docs/14 §2.2 is the detail)

Done this week: WhatsApp connection + template manager (WAP-001/005), quotation delivery/hint/follow-ups.
Remaining, split for two agents:

| ID | Task | Lane |
|---|---|---|
| CNV-004 | Canned replies with variables, EN/UR, `/` shortcut | W |
| CNV-003b | @mentions in internal notes | W |
| CNV-006 | Real-time broadcasting (needs the owner's Pusher/Ably decision) + poll fallback | W + O |
| CNV-007/008 | Conversation SLA; media sending out | W |
| CNV-009 | AI assist in the inbox (suggest/summarise/translate) | A (needs BOT-002) |
| WAP-007/008/010 | Broadcasts, cost meter, quality monitoring | W (008/010) · A if tied to their funnel |
| AUT-001/002/005/006 | **Follow-up sequences engine + built-ins + limits** | A |
| BOT-001…010 | **Chat widget, bot engine, knowledge base, qualification, hand-off, guardrails, cost caps** | A |

## 6 · P5 Telegram command centre — S1/S2 done, S3–S8 remain (docs/15)

| Step | Task | Lane |
|---|---|---|
| S3 | Action commands + inline buttons (`/approvals` `/approve` `/assign` `/note`; Verify/Reject, Assign-to-me) | W — **the interface A's architecture must use** |
| S4 | Digests daily/weekly/monthly (scheduler + copy) | W (delivery) · A (content may come from their funnel plan) |
| S5 | Health completion: DB size, last backup, scheduled-task last runs | W |
| S6 | Change-requests board (System → Change Requests) | W |
| S7 | Alert-rules builder (AUT-003) | A + W (rules engine A, Settings screen W) |
| S8 | Hardening: button expiry, unlink on deactivation, quiet-hours batching | W |

## 7 · P6 Website + builders — V2-1…V2-6 (docs/12, docs/15)

| Phase | Task | Lane |
|---|---|---|
| V2-1 | Design tokens + theme builder | W — **un-gated: the owner chose v1's own design as the token source (D-116, 17 Oct)** |
| V2-2 | Section library + page builder (drag, preview, publish) | W |
| V2-3 | Global blocks, collections, media, menus completion | W |
| V2-4 | SEO layer + auditor + AI crawler rules + `llms.txt` | W |
| V2-5 | The conversion, group by group (docs/13) + performance + accessibility | W |
| V2-6 | AI SEO assistant + Sveltia bridge (deferred decision) | W |
| — | Owner inputs: theme path, Nohemi licence (P-09), real photos (P-12), GA4/GTM/Clarity IDs (P-15), Search Console (P-13), content review 10 h | O |

## 8 · P7 Social — not started, owner-gated

Meta connect, Messenger/IG in the inbox, Lead Ads → CRM, composer/scheduler, LinkedIn. **Owner must start the
Meta app review and LinkedIn product access now** (weeks to approve) even though the work lands later.

## 9 · P8/P9 — tail, listed so nothing is lost

Projects board + client portal, handover (P8); security review, load pass, restore drill, Urdu templates,
training, final v2.1 sync (P9). No action now.

---

## 10 · The run — both lanes, in waves

**Wave 1 (now):**
- **W:** P5 S3 (approvals from Telegram — the P5 exit test) → S5 → S6.
- **A:** their three documents; sequences engine (AUT-001/002/006) against the shipped follow-up command.
- **O:** theme-path decision (gates V2-1); Meta/LinkedIn approval requests for P7; Turnstile keys.

**Wave 2:**
- **W:** P3 reports kit (funnel, revenue, receivables, CSV) → audit viewer → CNV-004 canned replies.
- **A:** BOT-002 provider abstraction → knowledge base → qualification; alert-rules engine.
- Handshake: A's architecture review against this list + docs/07.

**Wave 3:**
- **W:** V2-1 tokens (if the theme decision is in) → V2-2 builder; S7 Settings screen for alert rules.
- **A:** bot hand-off rules, cost caps, widget wiring to the inbox; digests content.

**Wave 4:**
- **W:** V2-3 → V2-4 → V2-5 conversion group by group (docs/13 gates) → V2-6.
- **A:** CNV-009 assist, WAP-007 broadcasts if the owner wants marketing.
- **O:** content review per conversion group; Go/No-Go for the website cut-over (Release 3).

Nothing in Wave 2+ blocks on the WhatsApp credentials; every automated message simply keeps the configured
fallback until the studio connects the number.

---

## 11 · Standing questions for the owner — ANSWERED 17 Oct

1. **WhatsApp live connection: the owner will connect it soon.** Steps unchanged: Settings → WhatsApp (paste
   the Meta credentials, Test connection) → Automation → WhatsApp Templates (Sync, map *quotation ready*).
   No code waits on it.
2. **Team split: agreed.** Lane boundaries in §1 stand.
3. **Theme path: v1's own design, extracted as tokens (D-116).** V2-1 is un-gated; the pack path stays for
   campaign looks later.
