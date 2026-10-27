# 35 — Pending-tasks refresh: v2.1 Pro source vs Woodex OS (26 Oct 2026)

## 27 October Furniture scope update

The owner approved the full Furniture quotation/invoice redevelopment and additive storage. It is now implemented
and verified in preview: dedicated green/grey sheet, product/specification, charges/advance, brand selection,
reusable templates, matching live invoice, WFI full-conversion workflow and verified receipts/accounting. This
supersedes the older Furniture-only gaps below; it does not mark all Interior T/U work complete. See
[37_FURNITURE_DOCUMENTS_2026-10-27.md](37_FURNITURE_DOCUMENTS_2026-10-27.md).
**W stays paused; Sheets append and off-site backup remain deferred.**

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
| Quotation templates + PDF | `admin-print17.js` (3 layouts, 3 palettes, blocks) + 9 starter templates | **Step 48 correction:** native source-derived Classic BOQ / Single / Project renderer, DM Sans and source shell, persistent current-input preview; canonical 16-template importer remains | **Three layouts delivered**, replacing the earlier partial alignment claim. Cards/chips/advance-% remain T; full heading/spec row-kind editor and pixel-identical html2pdf pagination are not claimed. See audit 36 |
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
2. ~~**Slice INT — Integrations redevelopment**~~ — **Done 27 Oct (Step 47).** Owner chose the unified grid + signed webhooks and approved two tables. Delivered scope and remaining connector work below.
3. ~~**Slice W — Estimations hub**~~ — **Implemented27 Oct after “start next”.** Native Estimates / Rates / Estimator Enquiries, all eight audit fixes, no schema changes; current evidence in report39.
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


## INT delivery update — 27 Oct 2026 (Step 47)

Owner chose **full_grid_webhooks**, approving the two endpoint/delivery tables, deferring
Sheets append and Drive/GitHub off-site backups. The live-preview correction (Step 46)
interposed; INT resumed afterwards on the owner's explicit selection.

Shipped: one searchable/filterable 14-card grid (Communication / Google / Social / Data /
Automation), provider Details, preserved Google OAuth, Telegram pairing/diagnostics,
WhatsApp tests/templates, SMTP, legacy import and MCP-key controls. The duplicated strips
and all documentation-only roadmap cards are gone; working WhatsApp is no longer marked
Planned. Gmail/Drive share one Workspace authorisation and their Details explicitly say
that removing it removes access for both. A saved credential without a successful check
reads Needs check, not Connected. MCP keys being issued also do not prove a client connected.

Facebook Page is a real Page ID + token identity check, encrypted and provider-proved before
storage, with live retest/removal. **It does not publish posts**: Meta identity verification
is not publishing permission or a social publishing transport. That remains separate work.

"Add any app" now creates signed outgoing HTTPS webhooks for lead.created, quote.sent,
invoice.paid. The two approved tables hold encrypted signing secrets and delivery history.
Jobs queue after commit on database/webhooks; a separate every-minute worker drains that
queue (three attempts) without delaying staff alerts. Paused/deleted endpoints cannot
receive queued requests. Stable delivery IDs let receivers deduplicate retries; signing
uses HMAC-SHA256 of the exact body. Only the owner can create, pause, rotate or remove;
integration managers can inspect and send tests. The last 30 delivery outcomes update in
the endpoint panel; integration logs contain metadata, never payloads or signing secrets.
The public-address guard resolves and pins DNS for each arbitrary outbound request and
refuses private/reserved destinations, redirects and proxies (also applied to Custom API).

**Evidence boundary:** Chromium proved the grid and endpoint panel on port 33143. HTTP-fake
integration tests proved provider responses, queueing, HMAC and retry outcomes. No owner
Facebook token or real Zapier/Make endpoint was supplied, so no external account or webhook
receiver is claimed connected. Sheets append/off-site backups are deferred, not shipped.

**Next recommended:** W — native Estimations hub. T remainder and U remain pending as
listed above; U still needs the owner's invoice sample PDFs.


## Owner-interposed quotation correction — Step 48 (27 Oct)

After INT the owner paused W, selected source review first, then approved Start. Existing
quotation editor rebuilt with persistent desktop side preview, no-save current-input render,
three v2.1 layouts/palettes and source-derived document shell. Native Laravel workflow reused;
no schema change. Both business lines and cookie-restricted Arena frame verified; full gate
**1,547 passed / 1 skipped / 8,257 assertions**. Sent PDFs stay frozen; legacy snapshots keep
legacy rendering. Details and boundaries: `36_QUOTATION_WORKBENCH_2026-10-27.md`.
**Next: owner reviews quotations before any decision to start W.**


## Owner-interposed Furniture delivery and A4 polish (27 Oct)

Initial Furniture native quotations/full WFI invoices shipped, followed by the explicitly approved screenshot-led
A4 polish. New Furniture uses logo/company left, metadata right, repeated header/footer, Product / Description,
wide optional summary, combined Rent / Transport, editable centred signatories and **50%** matching terms/advance.
Old sent/issued renderers and explicit saved percentages remain frozen; drafts opt in through Apply current design.
No new schema in the polish; native issue/ledger/verified-payment semantics retained. Final gate **1,577 passed /
1 skipped / 8,418 assertions**; active33143 and isolated33144 iframe/browser/PDF evidence in
`38_FURNITURE_A4_POLISH_2026-10-27.md`. **W is still paused. Next: owner accepts this Furniture design.**


## Slice W audit → approved implementation (27 Oct)

Owner accepted Furniture, approved W's audit/plan with “Sure start”, then approved its implementation with
**“start next”**. Delivered native Estimates / Rates / Estimator Enquiries; one sidebar and authorised legacy
Rates redirect; scoped records/actions and website-only Rates access;20-row pagination/search; shared
publisher with drift display, explicit publish, named backups/load-as-draft, native revision conflicts and
file/DB compensation. All eight reproduced defects now have permanent passing regressions.

Final exact-tree full Pest gate: **1,601 passed /1 skipped /8,565 assertions /438.16s**.
PHPStan0, Pint30, Vite, native desktop/390px workflows,18 actual public-v1/native base parity cases, active33143
restricted-cookie framing and Interior/Furniture generated-PDF inspection pass. No schema changes,
production deployment, public-v1 redesign or Furniture-renderer change. Preview remains fresh demo data.
See [report39](39_ESTIMATIONS_HUB_AUDIT_PLAN_2026-10-27.md) and its release evidence for limits/reproduction.

**Current recommendation:** audit **T remainder** (quotation-template cards/chips) next, reusing source first;
no T implementation/schema work until its own decision. U still requires invoice samples; X and deferred
connector/off-site-backup work remain separate. Earlier “W paused/next” notes above are historical and are
superseded by this section.

## Owner priority update — attached Furniture HTML (27 Oct)

Owner approved the recommended **design-only** Furniture quotation update with **“start”**. Revision3 is
implemented with native Product/Description, editable50%, native tax/transport totals, current-data preview
and PDF. New/explicitly-upgraded quotations adopt it; revision1/2 history, invoice renderer and Interior
are preserved. No schema or sample-data import. Seven new regression cases and real desktop/390px/nested
cookie-restricted preview proof cover the boundary. The five-row example fits1 A4 page at the native
Rs661,050 total and matching Rs330,525 advance/remainder;45-row stress paginates over9 pages without loss.
Final release gate: **1,608 passed /1 skipped /8,615 assertions /418.28s**; PHPStan/formatting/build pass. See [report40](40_FURNITURE_HTML_REFERENCE_AUDIT_2026-10-27.md).

**Superseded next step:** owner subsequently supplied an Interior quotation HTML/screenshot and asked for
that update next. Owner then directed **preview first, update only after approval**. A standalone
**Interior Classic BOQ** visual preview is ready on33145 with a1-page A4 sample PDF; desktop/390px,
zoom/print/download/framing and PDF bounds/font checks pass. Native-calculator-compatible total is
**Rs1,488,750**, correcting both conflicting reference aggregates. This Chromium prototype is not the
native Laravel/dompdf renderer. **Visual approval is required before application integration.** No
application/schema/database changes; Furniture/invoices/alternative layouts/frozen history unchanged.
See [report41](41_INTERIOR_HTML_REFERENCE_AUDIT_2026-10-27.md). Broader T/U work remains separate.
