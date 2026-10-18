# 14 · Pending tasks — P4, P5 and the v2 frontend

_One list, current as of 17 Oct 2026. Status is checked against the code, not against the plan documents.
**The master index across all phases P1–P7, with owners per item and the two-agent run order, is
docs/16-MASTER-PENDING-LIST.md** — this file stays as the P4/P5 detail._
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

Inbox with assignment and internal notes (CNV-001/002, CNV-003 **with @mentions since 18 Oct**) · 24-hour window awareness
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
| **CNV-004** | ~~Canned replies with variables, EN/UR, `/` shortcut in the composer~~ | **DONE 17 Oct** | `canned_replies` table + `CannedReply` model (variables `{first_name}` `{studio_name}` `{studio_hours}` `{quote_link}`, quote link = latest sent quotation's portal link); `/` picker in the inbox composer fills them per client; Settings → Canned replies manages them (EN/Roman Urdu, seeded with 7). |
| **CNV-006** | Real-time: new message, typing and status pushed (Laravel broadcasting), keeping the 5 s/30 s poll as fallback | **PART** | Polling works today. Broadcasting needs a driver that works on Hostinger (Pusher/Ably key, or self-hosted Soketi) — **owner decision on the provider**. |
| **CNV-007** | Conversation SLA: first reply and next reply targets, with escalation to Telegram | **DONE 18 Oct** | Reply clock on WhatsApp conversations: 30 min first reply, 15 min after that (Settings → Sales), armed by inbound, cleared by any staff reply; `woodex:sla-check` flags breaches and escalates. Website chats excluded on purpose — the lead SLA + live-chat bell already cover them. |
| **CNV-008** | Send media back: images, PDFs, voice notes, location | **DONE 18 Oct** | Upload + send through the gateway, caption doubles as the composer text, studio keeps its own copy, thread renders media both ways (`ConversationMediaTest`). Not in: in-browser voice recording (attach an audio file instead), location sending (needs a picker), web-chat media (widget is text-only v1). |
| **CNV-009** | AI assist in the inbox: suggested reply, summarise, translate EN↔UR/Roman Urdu, edited by staff before sending | **TODO** | Depends on BOT-002's provider abstraction. |
| **WAP-007** | Broadcasts to segments with opt-in, scheduling, throttling, per-recipient status and a cost estimate before sending | **TODO** | Needs WAP-005 and WAP-008. Owner decision: do we send marketing broadcasts at all? |
| **WAP-008** | Usage and cost meter: monthly counts by category × rate table, threshold alerts to Telegram | **DONE 18 Oct** | Rates + monthly budget in Settings → WhatsApp; daily `woodex:whatsapp-health` counts successfully sent messages (failed, notes, web chats excluded) and alerts once per month when the estimate crosses the budget. |
| **WAP-010** | Quality monitoring: number quality rating and messaging limit on the Integrations page, alert on downgrade | **DONE 18 Oct** | Daily quality check in `woodex:whatsapp-health` stores the rating, alerts the day it drops; shown on the Integrations card. Messaging-limit tier waits for a real Meta API surface (not exposed on the phone-number node). |
| **AUT-001** | Follow-up sequences engine: ordered steps (wait → send template/email → create task → change stage), enrolled by trigger or by hand, exits when the client replies or the lead is won/lost | **DONE 18 Oct** | `woodex:automation-run` heartbeat, atomic claims, bounded passes, exit conditions (reply, won, lost — nurture excepted). Email steps not built (WhatsApp templates + tasks cover the studio's actual rhythm); manual enrolment = the screen, later. |
| **AUT-002** | Built-in sequences, editable: lead not reached · quote not viewed · quote viewed not accepted · invoice reminders · post-visit · lost-lead nurture · review request | **DONE 18 Oct** | Six seeded editable (AutomationSequenceSeeder). Review request deliberately stays its own command (RequestClientReviewsCommand). The older quotation-chase command still runs for lapsed quotations. |
| **AUT-005** | Execution log per enrolment (step, time, result, error) with pause/resume/cancel | **DONE 18 Oct** | `automation_step_runs` per step + pause/resume/cancel on the screen. |
| **AUT-006** | Safe limits: max 2 messages per contact per day, nothing between 21:00 and 09:00 unless the client wrote first | **DONE 18 Oct** | AutomatedSendGuard: quiet hours with a 60-minute reply grace, daily cap across all sequences (Settings → Sales). Held sends wait for 09:05 visibly. |
| **BOT-001** | Website chat widget: vanilla JS, Preline-styled, < 35 KB gzipped, lazy-loaded, greeting by page context, quick replies | **DONE (P8.5, 16 Oct)** | Shipped by the P8.5 web-chat wave: `/chat/widget.js`, token thread, rule-based intake. |
| **BOT-002** | Bot engine with provider abstraction (Claude default; OpenAI/Gemini), system prompt from docs/03, editable per section in Settings | **DONE 18 Oct (keyless)** | Engine + 4 providers live; key encrypted in Settings → Bot. **Thinking waits on the owner's API key + budget** — until then the keyless mode answers honestly and hands over. |
| **BOT-003** | Knowledge base built from the CMS (services, areas, FAQs, process, hours, policies, portfolio) + manual Q&A, retrieved with MySQL FULLTEXT | **DONE 18 Oct** | `bot_knowledge` + one-click CMS sync + manual Q&A in Settings → Bot. Retrieval is term-scoring (not FULLTEXT) — deliberately simple, documented; upgrade to FULLTEXT/vectors when the corpus earns it. |
| **BOT-004** | Qualification: collect service, property type, location, area, budget, timeline, name, WhatsApp; create the lead through CaptureLead (source: website chat) | **DONE (P8.5, 16 Oct)** | The rule-based intake already collects name → number → service and creates the lead. |
| **BOT-005** | Booking from the chat: offer the next slots from Settings calendar rules and confirm | **TODO** | Depends on booking existing at all — currently switched off. |
| **BOT-006** | Hand-off rules: asks for a human, complaint, price negotiation, low confidence, too many turns, returning client — with a Telegram alert | **DONE 18 Oct** | Hand-off on: no key, model failure, guardrail trip, turn limit, studio hours. Conversation → Pending + Telegram/database alert to the hand-off roles. Returning-client detection deferred (the intake creates a lead per thread today). |
| **BOT-007** | Guardrails: never invent prices beyond the published ranges, never promise dates or discounts, never share another client's data, answer in the client's language | **DONE 18 Oct** | Enforced in code (`BotGuardrails`), not just the prompt: timeline/discount regexes, digit-normalised price whitelist from the fed knowledge, no phone/email leakage. Violations never reach the visitor. Language rule lives in the system prompt. |
| **BOT-008** | Cost control: daily token cap, per-conversation turn limit, model per channel, and a "a human will reply" mode plus alert when the cap is hit | **DONE 18 Oct** | `bot_usages` per day/channel/provider; caps in Settings → Bot; the keyless mode IS the cap-hit answer. Model-per-channel is one channel deep (web chat). |
| **BOT-009** | Bot analytics: conversations, containment, hand-off reasons, leads created, top unanswered questions | **PART** | Today's counters live on the Bot tab (replies, tokens, hand-offs, guardrail hits). A real analytics screen waits until there is traffic worth charting. |
| **BOT-010** | Staff "bot mode" switch per channel and schedule (bot only outside studio hours) | **DONE 18 Oct** | The evenings-only switch in Settings → Bot, on the studio's own BusinessHours. |

---

## 3 · P5 — Telegram command centre (≈ 1 week)

_Implementation detail, ordered into buildable steps: docs/15 §P5. **COMPLETE 18 Oct** — S1–S8 all landed; the owner's 1-hour staff-linking task remains._

| ID | What | Status |
|---|---|---|
| **TEL-1** | Staff linking (`/link`), alerts to the right people, quiet hours | **DONE** — linking, alert routing (now rule-driven, S7) and quiet hours |
| **TEL-2** | Commands: `/today` `/leads` `/lead <ref>` `/quotes` `/due` `/visits` `/report` `/health` `/approve` `/assign` `/note` | **DONE** — all commands live through the command bus (S1/S2) |
| **TEL-3** | Morning digest and evening summary | **TODO** |
| **TEL-4** | Approvals from Telegram (quotation discounts, payments to verify) | **TODO** — the approvals screen exists; the Telegram route does not |
| **SYS-008** | Health: cron heartbeat, queue and failed jobs, disk and DB size, last backup, mail test, integrations, versions, scheduled tasks with last run | **PART** — a health screen and heartbeat exist; extend to the full list |
| **SYS-010** | Change-request board: from Telegram `/idea` `/issue` into an admin screen with status, phase and a reply to the requester | **PART** — the commands exist and store entries; there is no board to read them in |
| **AUT-003** | Alert rules: event → condition → recipients → channels → quiet hours → priority, editable in Settings | **DONE** — System → Alert Rules (S7): catalogue of 22 events, router behind every send, defaults = the shipped matrix |

Exit criterion for P5: **the owner completes a full day of approvals from Telegram.**

---

## 4 · v2 frontend — V2-1 … V2-6 (docs/12 §9)

_Implementation detail, ordered into buildable steps: docs/15 §P6._

| Phase | What | Status |
|---|---|---|
| **V2-1** | Design tokens + theme builder; all 15 section templates read tokens only | **DONE** — tokens are the live CSS's own vocabulary (D-116); `/assets/theme.css` re-skins the live v1 pages with no deploy, v2 pages link the hashed `/theme/{hash}.css` |
| **V2-2** | Section library + page builder (drag, device preview, draft → publish) | **PART** — library with categories + descriptions, saved-by-the-studio sections, drag-to-reorder (wire:sort), duplicate, device preview (desktop/tablet/mobile), shareable 24 h signed preview links shipped; remaining: campaign free-canvas mode, inline text editing |
| **V2-3** | Header/footer builder, media improvements, Pages/Menus completion | **ACTIVE (frontend v1 wave, 18 Oct)** — scope set from the marketing repo deep-search: capabilities port into Woodex OS, not a system mount (owner decision). Landed: click-to-edit (F4), nested menus + dropdowns + v26 mega panel (F1), the v26 hero slider section + home seeding (F2 — `BlockType::Slider`, Alpine carousel, `woodex:home-slider`), and the structured header/footer editor (F3 — SiteSettings header CTA/social/footer switches, footer columns from the footer menu tree). Wave F is COMPLETE (F1–F11) and P5 is COMPLETE (S1–S8; alert-rules builder landed last). Next per the marathon order: V2-2 leftovers → V2-5 → P7 → P9. Landed so far: F4 click-to-edit, F1 menus/mega, F2 slider, F3 header/footer editor, F5 page templates (starter/apply/save-back flows, 8 seeded layouts), F6 central FAQ/testimonial/logo library (Website → Content; sections pick members). v26 static design (frontend-v1 branch) is the owner's final design reference |
| **V2-4** | SEO: crawler rules, sitemap, canonicals, schema, then the auditor | **PART** — sitemap, canonicals and schema exist per page |
| **V2-5** | The conversion itself, group by group (docs/13) | **TODO** — blocked by V2-1 and V2-2 |
| **V2-6** | AI assistant for SEO + the Sveltia bridge | **TODO** — Sveltia deferred by recommendation (docs/12 §7) |

---

### 5a · Frontend-v1 wave — the full gap list (deep-search of the marketing repo, 18 Oct)

The old admin v2.1 pro (`frontend-v1/admin/`, the P17→P41 screens the owner starred) mapped item by item
against Woodex OS. Already covered, OS-native and richer: All pages (type filters incl. Blog/Portfolio/
Service/City), Page builder (drag, device preview, versions, click-to-edit), Section library + saved
sections, Redirects, Forms→CRM, Estimator, Media, SEO (per-page + sitemap), Theme Builder (V2-1 tokens,
v1 reset), plus every non-Website module (CRM, quotes, invoices, inbox, WhatsApp, users, backups).
Menus (F1) now beats the old "Header & footer" screen: the old one rewrote nav HTML on 87 files; ours is
one tree in the database, live everywhere at once.

Still missing from the old system — the wave continues after F2/F3:

| # | Piece | What the old system had | Plan |
|---|---|---|---|
| F5 | **Page templates** | Layout templates per type (article/study/city): hero style, sections on/off, order; apply to new or existing pages with a preview | Template = named section list (type + data) per PageType; "New page from template" + "Apply template" in the editor with preview; the 50 v26 section presets seed the library |
| F6 | **Shared content libraries** | FAQs, Testimonials, Client logos managed centrally, referenced by any page | Central libraries (Website → Content) + blocks pick from them; per-page manual rows stay possible |
| F7 | **Team** | Team manager: members with photo, role, bio; team section on About | `team_members` table + CRUD + a Team section block |
| F8 | **SEO agent** | Scan all pages → prioritized issues (title/desc/alt/H1/keyphrase/thin/schema) → AI proposals → review + apply | Keyless-first deterministic scanner + one-click fixes (mirrors the BOT pattern); AI proposals light up when a key exists |
| F9 | **Speed** | PageSpeed dashboard (mobile + desktop, history, top suggestions) | Internal checks always (image weight/format, page size, cache headers); Google PageSpeed API behind a settings key |
| F10 | **Site health (website)** | Broken links, missing images, orphan pages, robots sanity | One "Website health" tab on the SEO agent screen (or System Health extension) |
| F11 | **Theme presets** | 4 presets (Woodex classic, Gallery serif, Modern slate, Warm walnut) | Seed the 3 extra presets into the Theme Builder next to "back to v1" |

Order after F2/F3: F5 → F6 → F8 (+F10 folded in) → F7 → F9 → F11.

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
| **P-09** ~~Nohemi font licence~~ | No longer gates theme tokens — v1's own design (Plus Jakarta Sans) was chosen (D-116); Nohemi only if the studio ever switches to it |
| **P-11** Real Google Business Profile URL | Local SEO and rating schema |
| **P-16** Settings: email `info@woodex.com.pk`, hours 09:30–18:30 | NAP consistency everywhere |
| **New** WhatsApp templates approved in Meta (quotation first) | WAP-005, quotation sending outside the 24-hour window |
| **New** Broadcasting provider for real-time (Pusher/Ably/Soketi) | CNV-006 |
| **New** AI provider key and monthly budget | BOT-002…010 |
| **New** Which Preline/Tailwind theme pack (or v1's own design) | docs/12 §6 theme import |
| **New** Does the studio want marketing broadcasts at all? | WAP-007 |
| **New** Should publishing a page need a second person? | docs/12 §11 |
