# 35 — Pending-tasks refresh: v2.1 Pro source vs Woodex OS (26 Oct 2026)

## What was asked

Complete the `import_and_verify` slice (use the already-developed v2.1 quotation/invoice
template source — do not invent a parallel design), then refresh the whole pending-task
list with a full report of what the OS is still missing next to the v2.1 Pro source, and
add an integrations-redevelopment slice (TailAdmin integrations page as the UX reference;
the owner notes v2.1 already has integrations set in the Drive source). Ask one question
to choose the next slice; do not build further without the answer.

`import_and_verify` itself is **done** — see Step 44 in `16_MASTER_TODO.md`. This file is
the gap report + the refreshed queue.

## What was checked (method)

- The owner's Drive source zip (`Woodex-Live-v2.1-Pro-Production.zip`) was re-downloaded
  and its `admin/*.js` + `api/*.php` sources extracted (105 files). The production-data
  folders (`_private/`, `_database/`, DB seed JSON) were **not opened**.
- Every module's header comment was inventoried; the integrations trio
  (`admin-integ.js`, `admin-conn.js`, `api/conn-lib.php`) was read in depth.
- The OS side was checked against the shipped slices (Steps 40–44) and the current
  `Integrations` Livewire component + OAuth controller in the repo.
- TailAdmin reference fetched: a provider card grid — name, one-line benefit, and
  **Connect / Details / Remove** actions per card.

## Gap report

| Area | v2.1 Pro source | Woodex OS today | Verdict |
|---|---|---|---|
| Quotation templates + PDF (Classic BOQ) | `admin-print17.js` (3 palettes, blocks, BOQ) + 9 starter templates | Same palettes/labels/blocks; 16 starter templates in one canonical file, importable from the UI (**Step 44**) | **Aligned.** Remaining: template cards/chips UI (Slice T), single/project layouts not ported (not requested) |
| Invoices | `admin-p18` Create invoice with PO #, project, delivery, payment condition + schedule | Items/Unit column, boxes, payments-received + Paid stamp, signatures (**Step 44**); issued PDFs are frozen bytes (v2.1 re-renders live) | **Near.** The 5 extra fields are Slice U (schema ask with the slice); frozen-PDF semantics stay — receipts prove later payments |
| Leads & CRM | `admin-sales17` sheet-style tracker, lead side drawer, sheet import; pipeline v2 | Leads index, drawer/show, pipeline, CSV importer service + console command | **Done — Slice V (Step 45):** import screen over the existing importer, drawer tabs, month/line filters |
| Estimator | `admin-p18e` rate book + formula + preview; forms | Estimator rates admin + submissions → leads, separate from Sales estimates | **Slice W**: one native Estimations hub (owner: native, not a v2.1 port) |
| Inbox / WhatsApp | live chat, WhatsApp hub, campaigns, auto flows, offers, client-update notifications | Inbox with real runtime, automation sequences, templates | Comparable; live WhatsApp proof is blocked on the **owner's tokens** (unchanged) |
| Social / Marketing | `admin-social` planner (FB Page + Instagram) | Social planner with attribution (Step 43-era) | Comparable; blocked on **Meta/LinkedIn approval** (unchanged). Remainder = Slice X |
| Content / builder / media / SEO | content collections, page builder, media, Yoast-style SEO, speed, redirects | v1 file-based site (locked), builder writes v1 files, media library, SEO tooling | Comparable by design (v1 is the only frontend). PageSpeed scores wait on the **owner's Google key** |
| Telegram | `admin-tg` bot + team alerts + link account | Slice L shipped (QR pairing, health ladder) | Comparable; blocked on the **owner's real bot token** (unchanged) |
| Google sign-in / data | `admin-p8` + service-account GSC/GA | Google OAuth shipped; GSC over MCP = plan-32 Slice N | Blocked on the **owner's Google key** (unchanged) |
| AI | assistant centre, Train AI, AI report (unanswered questions → training), SEO agent proposals | Assistant drawer (Slice M, cited answers), AI writer settings | **Small real gap:** the unanswered-questions report + SEO proposals — park under Slice X, do not fake them before keys exist |
| Sheets / Excel | enquiries appended to a Google Sheet; .xlsx downloads | .xlsx exports exist (Step 40); no Sheets append | **Gap:** Sheets append folds into INT (real runtime or nothing) |
| Booking calendar | `admin-booking` month/week/list + approvals | not verified in this pass | **To verify** in its own slice before any claim; not in the approved queue |
| **Integrations** | 9 built-in tiles + **23-connector gallery** (account id + masked secret, test/delete) + real Gmail send, GitHub backup, Drive backup via Apps Script, Zapier/Make webhooks | Integrations page with per-provider `configured` + last-OK/last-error health, an OAuth start/callback controller, some real runtimes (Telegram, WhatsApp, Turnstile, mail) and at least one placeholder card (LinkedIn "P7") | **The named gap → new Slice INT** (design below). OS is ahead on health states, behind on breadth and the gallery UX |

## The refreshed queue (owner picks one to start)

1. ~~**Slice V — Leads redevelopment**~~ — **Done 26 Oct (Step 45).** Owner-only
   `/leads/import` screen over the tested `LeadCsvImporter` (column matching → dry run →
   apply), drawer tabs (Details / Log activity / Timeline), month + business-line tabs.
   Shipped without a schema change; see `16_MASTER_TODO.md` Step 45.
2. **Slice INT — Integrations redevelopment** *(new, owner 26 Oct)*. Design below.
3. **Slice W — Estimations hub**. Merge Website-studio rates + Sales estimates into one
   native hub. No new tables expected; verify at design time.
4. **Slice T remainder — quotation types & template cards.** Data half superseded by
   Step 44's importer; remaining = card/chip UI + advance-% surfaces. Small schema ask
   (template category) approved with the slice.
5. **Slice U — invoice fields.** PO # / project / delivery / payment-condition columns
   + furniture invoice design & tracking. **Needs the owner's furniture + interior
   invoice sample PDFs first** (unchanged blocker).
6. **Slice X — Marketing remainder** (social planner gaps, AI unanswered-questions
   report) once platform keys land.

## Slice INT design (integrations redevelopment)

Goal: one Connections page that looks like the TailAdmin reference, behaves like v2.1's
connector gallery, and keeps the OS's honest health states.

- **Card grid, grouped** (Communication / Google / Social / Data / Automation): each card
  shows provider, one-line benefit, live status chip (`Connected · last OK …` /
  `Error: …` / `Not connected`), and actions **Connect · Details · Test · Remove**.
- **Connect flows are real or absent.** OAuth providers use the existing
  `ConnectorOAuthController`; token providers use v2.1's proven shape — account id +
  write-only secret (only last 4 shown back), a **Test** button that makes a real API
  call and records the result, and `last_ok`/`last_error` health from actual traffic
  (the OS already stores these).
- **Custom webhooks** ("Add any app"): named outgoing webhook with event picker (lead
  created, quote sent, invoice paid) → covers Zapier/Make without pretending to be
  either. Signed payloads, retry log, delivery history on the Details view.
- **Day-one real runtimes only:** Telegram, WhatsApp, mail/SMTP, Turnstile, Google
  (sign-in + GSC when the key lands), Facebook Page posting (token already the owner's
  to supply), Google Sheets append (service account), backups to Drive/GitHub
  (v2.1-proven mechanisms, ported — not the in-browser JS itself).
- **Placeholders die:** every card that cannot do something real on day one is removed
  from the grid (LinkedIn "P7" first). No documentation-only cards, no "coming soon".
  Where a provider needs an owner key, the card says exactly which key and where to get
  it (v2.1's per-connector help text is the model).

## Deliberately not carried over

- v2.1's in-browser JS admin as code — workflows and data only (standing rule).
- The `single`/`project` PDF layouts and head/spec line hierarchy (not requested; the
  Classic BOQ is the matched document).
- Any v2.1 production data (`_private/`, `_database/` — never opened, never imported).

## Owner-side blockers (unchanged)

Rotated secrets · GitHub `workflows` permission · AI/PageSpeed/OAuth keys · Meta/LinkedIn
approval · real Telegram token in server `.env` · Google key (N) · FB Page ID + token
(Q/X proof) · **furniture + interior invoice sample PDFs (Slice U)** · bank/wallet live
data entry in admin.
