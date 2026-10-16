# 15 · P5 → P6 implementation list

_The complete, ordered list for the next two phases, written to be worked top to bottom. Owner's direction,
17 Oct: start P5 and P6 (the P4 remainder stays on docs/14 and resumes alongside)._

| | Window (1 dev) | Dev hours | Owner time |
|---|---|---|---|
| **P5 Telegram command centre** | 18 Jan → 22 Jan | 40 | 1 h: link Telegram accounts |
| **P6 Website + builders** | 25 Jan → 19 Feb | 150 | 10 h: content review, photos, approvals |

**P5 exit:** the owner completes a full day of approvals from Telegram.
**P6 exit:** parity test 142/142 green · CWV targets on staging · Release 3: website cut-over.

---

## What exists today (verified in code, 17 Oct)

Working: linking (`/start CODE`, one chat per link) · `/help` `/idea` `/issue` · alerts (new lead, SLA breach,
payment events, quote events) with quiet hours · cron heartbeat · backups + monitor · Health screen (queue, failed
jobs, disk, integrations) · `Approval` model with `RequestQuoteApproval`/`DecideQuoteApproval` ·
`VerifyPayment`/`RejectPayment` · `ChangeRequest` model fed by `/idea` `/issue` · scheduler for SLA, reminders,
quote follow-ups, backups.

Missing: the 13 business commands · callback-query handling (inline buttons) · digests · the change-requests
board · SYS-008's DB size, last-backup line, scheduled-task list with last run · alert-rules builder · command
rate limits · button expiry.

---

## P5 — the steps, in order

### S1 · Command framework (foundation, ~0.5 d)

Move command handling out of the webhook controller into a registry of one-class-per-command handlers under
`app/Domain/System/Telegram/`: each declares `name`, `description`, `permission` (or null for public), and returns
the reply text. The controller: verifies the secret → resolves the **linked, active user** → rate-limits the chat
(30 commands/min) → permission check → run → reply; an exception answers "something went wrong, the team was
notified" and reports it — the webhook itself never 500s (Meta would retry forever). `/help` is generated from the
registry, so it can never go stale. Callback queries (`callback_query` updates) are routed here too — S3 needs
them for buttons.

**Done when:** every command below runs through the same path; unlinked chats get one clear message; a staff
member without the permission gets "ask an admin", not a crash.

### S2 · The morning view — read commands (~1.5 d)

| Command | Shows | Permission |
|---|---|---|
| `/today` | visits today, SLA-overdue leads, tasks due, payments awaiting verification, approvals pending | `leads.view` |
| `/leads [new\|mine\|unassigned]` | latest 8 leads: reference, name, service, score, owner | `leads.view` |
| `/lead <ref\|id\|phone>` | one lead: stage, owner, score, budget, phone, WhatsApp, last activity, open tasks | `leads.view` |
| `/find <text>` | leads by name / phone / company | `leads.view` |
| `/quotes [pending\|viewed]` | recent quotes with amount and status | `quotes.view` |
| `/quote <no>` | one quote: amount, status, sent/viewed, valid until | `quotes.view` |
| `/due` | invoices due and overdue with balance | `invoices.view` |
| `/visits [today\|tomorrow]` | visits with time, lead and area | `leads.view` |
| `/report [day\|week\|month]` | leads, quotes sent/accepted, invoiced, collected | `reports.view` |
| `/health` | heartbeat, queue, failed jobs, disk, last backup, integrations | `system.health` |

**Done when:** each command's reply is covered by a test that fakes the webhook POST and reads the reply sent to
the fake gateway — the owner's phone is not the test bench.

### S3 · Action commands and inline buttons (~1 d)

`/approvals` and `/approve <id>` (quote discount/low-margin approvals via `DecideQuoteApproval`) · `/assign
<lead> <user>` (`AssignLead`) · `/note <lead> <text>` (an activity note) · `/mute <hours>` (quiet this chat).
Buttons on the alerts that already exist: new lead → *Assign to me · Call · WhatsApp · Open*; payment proof →
*Verify · Reject*. Every action runs with the linked user's permissions and is recorded ("via Telegram") on the
lead/quote/invoice timeline. Buttons carry an expiry (24 h) and refuse politely after it. Void/refund/role
changes are **never** offered here (TGM-005).

**Done when:** the P5 exit test passes: an approval raised in the admin is approved from Telegram, and the
timeline says who did it and from where.

### S4 · Digests (TGM-003, ~0.5 d)

`woodex:digest` scheduled: daily 09:00 (today's visits, due follow-ups, yesterday's leads/quotes/payments,
cash collected) → every linked chat; weekly Monday 09:00 (funnel, win rate, top sources, receivables); monthly
1st 09:00. Quiet hours apply to the daily digest.

### S5 · Health completion (SYS-008, ~0.5 d)

Add to the Health screen **and** `/health`: DB size, last backup (from spatie/laravel-backup's log), scheduled
tasks with their last run (a `scheduled_task_runs` table written by the heartbeat), mail test button, PHP/Laravel
versions. One collector feeds both the screen and the command — never two truths.

### S6 · Change-requests board (SYS-010, ~0.5 d)

System → Change Requests: list `/idea` `/issue` entries with status (new → accepted → planned → done/rejected),
phase link, requester; status change notifies the requester on Telegram. `CR-0001` reference already exists.

### S7 · Alert rules builder (AUT-003, ~1 d)

Settings → Notifications: event → condition → recipients → channels → quiet-hours bypass → priority. Defaults
match the current notification matrix; the catalogue is TGM-002's list.

### S8 · Hardening (TGM-007/008, ~0.5 d)

Rate limits per chat (done in S1, tested here) · button expiry (S3) · unlink on user deactivation · non-critical
alerts batched during quiet hours, critical always delivered (partly exists — finish it).

**Waiting on the owner (1 h):** each staff member links their Telegram from their profile. Nothing else blocks P5.

---

## P6 — the steps, in order

P6 was re-planned as the v2 frontend (docs/12, D-105…D-110). The spine is V2-1…V2-6; this list adds the PRD rows
docs/12 leaves open. **Nothing starts before the owner picks the theme path** (docs/12 §6: Preline/Tailwind
theme pack vs v1's own design — the one open decision that gates V2-1).

| Step | What | Covers | Estimate |
|---|---|---|---|
| **V2-1** | Design tokens + theme builder: colour/font/radius/spacing tokens, contrast checker, versioned `/theme/{hash}.css`, rollback; **no build step on the server** | CMS-006/007 | 2 w |
| **V2-2** | Section library (the 15 existing templates first) reading tokens only + page builder: add/reorder (drag)/duplicate/hide, side panel, device preview, autosave, publish | CMS-001/003 (+ CMS-004 versions) | 2 w |
| **V2-3** | Header/footer/global blocks · collections (services, areas, insights, projects, testimonials, FAQs) · media (WebP + responsive + usage tracking) · menus completion · editor roles + HTML sanitising | CMS-002/008/009/010/014/015 | 1.5 w |
| **V2-4** | SEO: per-page panel checks (one H1, lengths, alt text, duplicates), FAQ schema from the FAQ block, redirects + 404 log (exists — verify), sitemap ping/IndexNow, `robots` for AI crawlers, `llms.txt` | CMS-005/011, docs/12 §5.4 | 1 w |
| **V2-5** | The conversion itself, group by group per docs/13 — **the parity test is the gate** (same DOM + computed styles at 375/768/1440, every page, not a sample) · performance (WEB-011: critical CSS, WebP/AVIF, preloaded fonts, < 60 KB JS) · accessibility (WEB-012) · sticky mobile CTA (WEB-004) | WEB-001/004/011/012, docs/13 | 3 w |
| **V2-6** | AI SEO assistant (suggest, human approves) · Sveltia bridge (deferred — decision open) | docs/12 §5.4/§7 | 1 w |

**Carried inside V2-5, not separate work:** forms on v2 pages (WEB-005 — the API and Turnstile already serve the
live v1 site) · estimator (WEB-006 — live on v1, phone-before-price per D-113) · booking (WEB-007 — exists,
paused until slots are real) · chat widget (WEB-008/BOT-001 — builds on the P4 bot decision) · real project
photographs (WEB-009 — blocked on P-12).

**After cut-over:** GA4/GSC cards in Reports (RPT-007, needs P-15 IDs), Urdu RTL landing pages (WEB-014, P9).

**Waiting on the owner (10 h total):** theme path decision (gates V2-1) · Nohemi licence confirmed (P-09) · real
project photos (P-12) · GA4/GTM/Clarity IDs (P-15) · Search Console access (P-13) · content review and page
approvals during V2-5 · the approval named in docs/13 §6 for each group switch.

---

## Sequence

1. **P5 S1–S3** (framework → reads → actions) — the owner can then run a day from Telegram.
2. **P5 S4–S8** — digests, health, board, rules, hardening.
3. **V2-1** starts as soon as the theme path is decided; it does not depend on P5 finishing.
4. **V2-2 → V2-3 → V2-4**, then **V2-5** group by group (docs/13), then **V2-6**.
5. P4 remainder (docs/14 §2.2: canned replies, sequences, broadcasting, bot) interleaves where the owner
   directs; the quotation follow-ups already shipped were its first piece.
