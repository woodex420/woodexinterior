# 14 · Pending tasks — P4, P5 and the v2 frontend

_One list, current as of 17 Oct 2026. Status is checked against the code, not against the plan documents._
_Done work is listed only where something pending depends on it._

Legend — **DONE** shipped and tested · **PART** partly there · **TODO** not started · **BLOCKED** waiting on the
owner or on Meta.

---

## 1 · Recommended start order

| # | Start | Why first |
|---|---|---|
| 1 | ~~P4 · WAP-001 connect + WAP-005 template manager~~ **BUILT (17 Oct)** — pending only the owner entering live credentials | The screens exist: Settings → WhatsApp (credentials, test connection, webhook URL), Integrations → WhatsApp Templates (sync, submit, map). What is left needs the owner's Meta access, not code. |
| 2 | **P4 · CNV-004 canned replies** | Next code to write. Small, and it is what makes the inbox usable all day instead of typing the same four answers. |
| 3 | **P4 · AUT-001/002/005/006 follow-up sequences** | The engine that turns today's one-off quotation chase into the studio's whole follow-up rhythm. |
| 4 | **P4 · CNV-006 real-time broadcasting** | The inbox currently polls; this removes the wait. |
| 5 | **P5 · Telegram command centre** | One week, and it is how the owner actually runs the business from a phone. |
| 6 | **P4 · BOT-001…010 the assistant** | The largest item; start it after the inbox is comfortable. |
| 7 | **v2 frontend · V2-1 tokens → V2-2 builder → …** | Longest runway; the conversion (docs/13) cannot start before V2-2. |

---

## 2 · P4 — Conversations

### 2.1 Done (part 1, shipped)

Inbox with assignment and internal notes (CNV-001/002, CNV-003 **without @mentions**) · 24-hour window awareness
with the template fallback (CNV-005) · polling so a thread updates without a reload (CNV-006 partial) · receive
text, image, document, audio, video, location, contacts, buttons, reactions (WAP-003) · send free-form and
templates, with delivery/read/failed statuses (WAP-004) · signed webhook with idempotent processing (WAP-002) ·
opt-out keywords (WAP-006) · **connected from the dashboard (WAP-001, this week)**: credentials saved in
Settings → WhatsApp, encrypted at rest, .env as fallback, a test that sends a real message · **the template
manager (WAP-005, this week)**: templates synced from Meta with status and rejection reason, new templates
submitted with per-variable examples, and each moment (quotation ready, visit reminder, …) mapped to an approved
template — the quotation link now sends whatever is mapped, not a name from the code · **quotations sent on
WhatsApp (this week)** · **a client's "ok" noticed without accepting the price (this week)**.

### 2.2 Pending

| ID | What | Status | Notes / dependencies |
|---|---|---|---|
| **WAP-001** | ~~Connect WhatsApp from the dashboard~~ | **DONE** | Settings → WhatsApp holds the credentials encrypted (D-115); Test connection asks Meta about the number and can send one real message; the Integrations card shows the live status. Waiting on the owner's live credentials only. |
| **WAP-005** | ~~Template manager: sync, submit, status, map to events~~ | **DONE** | Automation → WhatsApp Templates. Sync mirrors Meta (paging, rejection reasons, removals marked not deleted); submit validates and sends examples; mapping is one approved template per moment, and the quotation send uses it. |
| **CNV-004** | Canned replies with variables (`{first_name}`, `{studio_hours}`, `{quote_link}`), EN/UR, `/` shortcut in the composer | **TODO** | Small; needs a table and a picker. |
| **CNV-006** | Real-time: new message, typing and status pushed (Laravel broadcasting), keeping the 5 s/30 s poll as fallback | **PART** | Polling works today. Broadcasting needs a driver that works on Hostinger (Pusher/Ably key, or self-hosted Soketi) — **owner decision on the provider**. |
| **CNV-007** | Conversation SLA: first reply and next reply targets, with escalation to Telegram | **TODO** | Mirrors the lead SLA that already exists. |
| **CNV-008** | Send media back: images, PDFs, voice notes, location | **PART** | Receiving works; sending needs media upload to the Graph API (not yet in the gateway). |
| **CNV-009** | AI assist in the inbox: suggested reply, summarise, translate EN↔UR/Roman Urdu, edited by staff before sending | **TODO** | Depends on BOT-002's provider abstraction. |
| **WAP-007** | Broadcasts to segments with opt-in, scheduling, throttling, per-recipient status and a cost estimate before sending | **TODO** | Needs WAP-005 and WAP-008. Owner decision: do we send marketing broadcasts at all? |
| **WAP-008** | Usage and cost meter: monthly counts by category × rate table, threshold alerts to Telegram | **TODO** | Settings work + a scheduled daily rollup. |
| **WAP-010** | Quality monitoring: number quality rating and messaging limit on the Integrations page, alert on downgrade | **TODO** | Small once WAP-001's page exists. |
| **AUT-001** | Follow-up sequences engine: ordered steps (wait → send template/email → create task → change stage), enrolled by trigger or by hand, exits when the client replies or the lead is won/lost | **TODO** | The biggest single piece of P4 after the bot. |
| **AUT-002** | Built-in sequences, editable: lead not reached · quote not viewed · quote viewed not accepted · invoice reminders · post-visit · lost-lead nurture · review request | **PART** | **Quotation follow-ups shipped this week** (chase after N days, warn before lapse, lapse when stale) as a focused command. The rest waits for AUT-001. |
| **AUT-005** | Execution log per enrolment (step, time, result, error) with pause/resume/cancel | **TODO** | Comes with AUT-001. |
| **AUT-006** | Safe limits: max 2 messages per contact per day, nothing between 21:00 and 09:00 unless the client wrote first | **TODO** | Must exist before any sequence can send. |
| **BOT-001** | Website chat widget: vanilla JS, Preline-styled, < 35 KB gzipped, lazy-loaded, greeting by page context, quick replies | **TODO** | The bridge already answers `/api/chat.php` with "off", so the widget can appear without touching the 142 files. |
| **BOT-002** | Bot engine with provider abstraction (Claude default; OpenAI/Gemini), system prompt from docs/03, editable per section in Settings | **TODO** | **Needs an API key and a budget decision from the owner.** |
| **BOT-003** | Knowledge base built from the CMS (services, areas, FAQs, process, hours, policies, portfolio) + manual Q&A, retrieved with MySQL FULLTEXT | **TODO** | Needs V2 content to be good; can start on the imported pages. |
| **BOT-004** | Qualification: collect service, property type, location, area, budget, timeline, name, WhatsApp; create the lead through CaptureLead (source: website chat) | **TODO** | CaptureLead exists; this is the conversation that fills it. |
| **BOT-005** | Booking from the chat: offer the next slots from Settings calendar rules and confirm | **TODO** | Depends on booking existing at all — currently switched off. |
| **BOT-006** | Hand-off rules: asks for a human, complaint, price negotiation, low confidence, too many turns, returning client — with a Telegram alert | **TODO** | |
| **BOT-007** | Guardrails: never invent prices beyond the published ranges, never promise dates or discounts, never share another client's data, answer in the client's language | **TODO** | **Non-negotiable before the bot talks to a real client.** |
| **BOT-008** | Cost control: daily token cap, per-conversation turn limit, model per channel, and a "a human will reply" mode plus alert when the cap is hit | **TODO** | Must exist before launch, not after. |
| **BOT-009** | Bot analytics: conversations, containment, hand-off reasons, leads created, top unanswered questions | **TODO** | |
| **BOT-010** | Staff "bot mode" switch per channel and schedule (bot only outside studio hours) | **TODO** | |

---

## 3 · P5 — Telegram command centre (≈ 1 week)

_Implementation detail, ordered into buildable steps: docs/15 §P5. Started 17 Oct (owner's direction: P5 → P6 next)._

| ID | What | Status |
|---|---|---|
| **TEL-1** | Staff linking (`/link`), alerts to the right people, quiet hours | **PART** — linking, alerts and quiet hours exist; the matrix is not complete |
| **TEL-2** | Commands: `/today` `/leads` `/lead <ref>` `/quotes` `/due` `/visits` `/report` `/health` `/approve` `/assign` `/note` | **PART** — only `/help` `/start` `/link` `/idea` `/issue` exist |
| **TEL-3** | Morning digest and evening summary | **TODO** |
| **TEL-4** | Approvals from Telegram (quotation discounts, payments to verify) | **TODO** — the approvals screen exists; the Telegram route does not |
| **SYS-008** | Health: cron heartbeat, queue and failed jobs, disk and DB size, last backup, mail test, integrations, versions, scheduled tasks with last run | **PART** — a health screen and heartbeat exist; extend to the full list |
| **SYS-010** | Change-request board: from Telegram `/idea` `/issue` into an admin screen with status, phase and a reply to the requester | **PART** — the commands exist and store entries; there is no board to read them in |
| **AUT-003** | Alert rules: event → condition → recipients → channels → quiet hours → priority, editable in Settings | **PART** — notification settings exist; the rule builder does not |

Exit criterion for P5: **the owner completes a full day of approvals from Telegram.**

---

## 4 · v2 frontend — V2-1 … V2-6 (docs/12 §9)

_Implementation detail, ordered into buildable steps: docs/15 §P6._

| Phase | What | Status |
|---|---|---|
| **V2-1** | Design tokens + theme builder; all 15 section templates read tokens only | **TODO** |
| **V2-2** | Section library + page builder (drag, device preview, draft → publish) | **TODO** — the editor exists without drag-and-drop |
| **V2-3** | Header/footer builder, media improvements, Pages/Menus completion | **PART** — media, menus and pages screens exist |
| **V2-4** | SEO: crawler rules, sitemap, canonicals, schema, then the auditor | **PART** — sitemap, canonicals and schema exist per page |
| **V2-5** | The conversion itself, group by group (docs/13) | **TODO** — blocked by V2-1 and V2-2 |
| **V2-6** | AI assistant for SEO + the Sveltia bridge | **TODO** — Sveltia deferred by recommendation (docs/12 §7) |

---

## 5 · Later phases, untouched (listed so nothing is lost)

**P7 Social** (Meta connect, Messenger/IG in the inbox, Lead Ads → CRM, composer and scheduler, LinkedIn) ·
**P8 Projects + client portal** (project board, photo updates, portal, handover, review request) · **P9 Hardening**
(security review, load pass, backup restore drill, Urdu templates, training, final v2.1 sync and retirement).

---

## 6 · Waiting on the owner

From docs/07 (P-items). None of these need code; all of them block something:

| Item | Blocks |
|---|---|
| **P-06** Rotate every credential that appeared in the old public repository | Go-live. Security. |
| **P-15** GA4 / GTM / Clarity IDs | Analytics, and the v1/v2 comparison (docs/13 §8) |
| **P-13** Search Console access | Watching a group after it switches |
| **P-12** Real project photographs | Projects group, "Selected work" proof |
| **P-09** Nohemi font licence confirmed | Theme tokens |
| **P-11** Real Google Business Profile URL | Local SEO and rating schema |
| **P-16** Settings: email `info@woodex.com.pk`, hours 09:30–18:30 | NAP consistency everywhere |
| **New** WhatsApp templates approved in Meta (quotation first) | WAP-005, quotation sending outside the 24-hour window |
| **New** Broadcasting provider for real-time (Pusher/Ably/Soketi) | CNV-006 |
| **New** AI provider key and monthly budget | BOT-002…010 |
| **New** Which Preline/Tailwind theme pack (or v1's own design) | docs/12 §6 theme import |
| **New** Does the studio want marketing broadcasts at all? | WAP-007 |
| **New** Should publishing a page need a second person? | docs/12 §11 |
