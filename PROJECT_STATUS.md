# Woodex OS — Project Status

**Updated:** 26 October 2026 · **Branch:** `arena/8be318a2-woodexinterior` · **Preview:** port 33143 (`owner@woodex.test` / `Woodex-Preview-2026`)

**One line:** the OS is real and database-backed; the gap QA found was the path *between* screens — an enquiry could become a lead but never an estimate, and the Overview showed counts you could not open. Both paths are now closed and tested. Everything else that looks empty is either honestly empty (no data yet) or blocked on a key the owner must approve.

> The detailed findings, evidence and ordered plan are in **[`docs/audit/31_DEEP_QA_2026-10-21.md`](docs/audit/31_DEEP_QA_2026-10-21.md)**. That file is the current authority; older status claims in this repo are superseded.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · SQLite (preview) → MySQL (production plan) |
| Tests | **1,501 passed · 1 skipped · 8,060 assertions** · Pint clean on touched files · PHPStan 0 errors |
| Frontend | v1 export (142 pages) is the only public site; the page builder writes into `woodex/` |
| Scheduler | 25 jobs registered (SLA, reminders, automations, social publish, digests, backups) |
| Environment | Preview runs `APP_ENV=local`; the header shows a **Demo data** pill so seeded records are never mistaken for clients |

## Next: the 26 October master plan

`docs/audit/32_MASTER_PLAN_2026-10-26.md` sets the forward order. **Slices I–M are shipped** (below); the last
planned slice, Search Console over MCP (N), is blocked on the Google key. Telegram is built end to end — it waits
only on the real bot token in the server `.env`. Plan 34 followed (Classic BOQ, builder live
preview), and Step 44 imported the real v2.1 Pro template source and verified the document
design against it. The current queue — with the v2.1-vs-OS gap report and the new
integrations slice — is **[`docs/audit/35_PENDING_TASKS_REFRESH_2026-10-26.md`](docs/audit/35_PENDING_TASKS_REFRESH_2026-10-26.md)**;
the owner picks the next slice from it.

## What is live today (verified this pass)

* **CRM** — leads, pipeline, tracking board, contacts, companies, tasks, visits, assignment rules, Telegram alerts.
* **Sales** — rate-book estimate builder, **enquiry → estimate → quotation draft** chain, invoices that raise themselves per reached milestone, the 7/14/30 overdue ladder, payments actions, approvals, PDFs.
* **Accounts** — chart of accounts, periods, double-entry journals, automatic posting from invoices and receipts.
* **Inbox** — WhatsApp webhook, templates, canned replies, media, website chat widget.
* **Automation** — six sequences, per-minute runner, quiet hours, toggles, last-run health.
* **Marketing** — social composer, approval walk, scheduled publisher (publishing waits on Meta/LinkedIn approval).
* **Website** — 142 file-backed pages, builder, media library, SEO audit, redirects, theme tokens, site health.
* **System** — users & roles, integrations with real connectors + MCP server, audit log, health, change requests, alert rules.
* **Projects** — projects, board, client portal, handover flow (no rows yet because no quotation has been accepted).

## What changed on 21 October (Slice A)

1. **Estimate builder opens from a real enquiry** (`?lead=…`), prefilled with service, size and client, and the estimate is linked to the lead and shown on both screens.
2. **Estimations empty state is actionable** — "Start from an enquiry" / "New estimate", with the enquiry reference on each row.
3. **Eight workflow hubs got their own browser titles** (Leads, Contacts, Tasks & Visits, Quotations, Invoices, Payments, Documents, Reports).
4. **Demo data is labelled** in every admin header on local installs.
5. **6 regression tests**; full suite green.

**Slice B (same day).** Client Portal and Handovers stopped being `P4b` badges and became real screens (signed portal links; handover progress), Chat Bot opens its live switches on Settings, the duplicate Alert Rules is gone, the eleven System entries became four pages with tabs, and the rate book moved to Sales as **Estimator rates**. 12 more tests; suite **1,296 passed / 1 skipped**.

**Slice E (26 October).** Invoices now raise themselves. `woodex:invoice-milestones` bills every milestone an accepted quotation has reached — hourly, once per milestone, with the same number, tax, PDF and ledger posting as the advance invoice — and `woodex:invoice-reminders` runs the 7/14/30-day ladder, one rung per invoice, messaging the client on WhatsApp only with consent and telling the studio why when it cannot. Both are on the Automation screen with their cadence and switches. 10 more tests; suite **1,332 passed · 1 skipped · 7,165 assertions**.

**Slice S — builder live preview, per-quotation PDF design (26 October).** The quote builder now has a **Live preview** drawer showing the real PDF as you edit, and each quotation carries its **own PDF theme and block order** (move blocks up/down, hide them, reset) without touching the brand defaults in Document design. New printing fields — **Type**, **Site** and an **intro line** above the summary — plus "as printed" client details that override the contact for one document only. 6 more tests; suite **1,478 passed · 1 skipped · 7,918 assertions**. Live proof: the demo draft restyled to Premium with Terms before the BOQ, rendered and checked, then restored.

**Slice R — quotation & invoice design match, Classic BOQ (26 October).** Both client documents now follow the v2.1 Pro layout the owner shared as PDFs: a **Summary of cost** first page (total area and per-sft rates where a section is fully sft-priced, dashes otherwise), amount-in-words box + TOTAL pill, the detailed BOQ as one table per section with 1.1-style numbering, bank details with branch code beside a **Mobile wallets** box (JazzCash/Easypaisa), an accepted-by-client signature block, and the contact footer. Summary of cost is a normal PDF block — move or hide it in Document design like any other; designs saved earlier gain it automatically. 4 more tests; suite **1,472 passed · 1 skipped · 7,893 assertions**. Proof renders of the demo quotation and a manual invoice were inspected page-by-page against the owner's PDFs (fake account numbers; preview restored afterwards).

**Slice P — manual invoices, tracking filters, invoice export (26 October).** "New invoice" is a real flow now: client, lines, discount and tax, saved as a draft or issued in the same act — numbered in its own **MI-2026-####** series and frozen + booked exactly like milestone invoices (Dr receivable / Cr revenue / Cr tax). The shared issue step also fixes a latent gap: draft advance invoices could never be issued before; they can now. Invoice tracking filters by **year** and **Interior/Furniture**, and both the board and the list export CSV of what the filters select. 22 more tests; suite **1,469 passed · 1 skipped · 7,855 assertions**. Live proof on 33143: MI-2026-0001 issued at Rs 36,750 with an exact journal and frozen PDF, draft MI-2026-0002 issued through the new button, preview restored to baseline afterwards.

**Slice O — the PDF design system (26 October).** Quotation and invoice PDFs are now restyled per brand without code: three themes (Classic, Minimal, Premium) and blocks — Scope, Items/BOQ, Totals & words, Payment schedule, Terms, Notes, Payment details, Signatures (invoice: Particulars, Totals & words, Pay to, Notes, Terms) — that reorder and hide from Document design, frozen into each new document so sent PDFs never change; Items and Totals always print. Quote templates travel as `.woodex-template.json` files (export/import, create-only), and the builder has a Signed-by picker. 15 more tests; suite **1,447 passed · 1 skipped · 7,780 assertions**. Live proof on 33143: Premium + signatures-first on draft WI-10101 produced a real 46.6 KB PDF in the new order, untouched WI-10102 kept the classic look, and everything was restored afterwards.

**Slice M — the assistant drawer and marketing attribution (26 October).** A drawer on every admin page answers nine fixed questions (overdue, due this week, collected this month, bills to pay, follow-ups, new enquiries, quotes waiting, overdue tasks, milestones due) from the **same queries their screens use**, citing the records — keyless and deterministic, refusing anything outside the whitelist or the asker's permissions by name, because there is no AI key and invented numbers are worse than none. Marketing became a hub with **Attribution**: source → enquiry → quoted → won value, cohort-based, with ad spend honestly absent until Meta is approved. 11 more tests; suite **1,432 passed · 1 skipped · 7,712 assertions**. Live proof: the assistant's overdue answer matched the tracking page rupee for rupee (Rs 243,750), flipped to *Nothing is overdue* after real collection, and attribution showed *Phone Call 6 · 1 won · Rs 325,000*.

**Slice L — Telegram that attaches (26 October).** The complaint was real: the Security & Telegram page had **no QR at all** (a text link and a code to retype) and one generic sentence for every failure. It now shows a **server-rendered QR** of `https://t.me/<bot>?start=<code>`, a copyable link, a live expiry countdown and the *waiting → linked as @handle → last message received* states without a reload, plus a **connection-health ladder** that names the exact failing step — token missing, secret missing, bot unreachable, token/link mismatch, webhook not registered (with Telegram's own delivery error), no update in 24 h, bot blocked by the user. 8 more tests; suite **1,421 passed · 1 skipped · 7,665 assertions**. Live proof: the issued QR **decoded by an independent decoder** to the exact deep link, a webhook `/start` flipped the page to *Linked @qa_owner*, and with no token the card opens on *TELEGRAM_BOT_TOKEN is not set* — still the one owner-side blocker.

**Slices J + K — the contact ladder and the proposal pipeline (26 October).** Five windows on records the OS already kept, no schema change: **Clients**, **Customers** (who actually bought, with won value read from accepted quotations) and **Visitors** (website enquiries — form, estimator, booking, chat — not yet converted; the OS records enquiries, not page views, and the screen says so) as tabs on the Contacts hub; **Proposals** (quotations still in negotiation, with value in play) and the **Pipeline** (every quotation by stage with a weighted forecast Σ value × stage weight) as tabs on the Quotations hub. `/admin/clients`, `/admin/customers`, `/admin/visitors`, `/admin/proposals` and `/admin/sales-pipeline` answer with a 301 to the right tab; the sidebar stays at 48. 10 more tests; suite **1,413 passed · 1 skipped · 7,624 assertions**. Live proof: WI-10103 (Rs 3,25,000) sent, accepted, invoiced (Rs 2,43,750) and paid — the five screens agreed with the records, then every proof row was removed. "Estimates Received" (supplier quotations) has no data model and stays a future schema slice rather than a pretend screen.

**Slice I — money out (26 October).** The half of the ledger that did not exist. Accounts now has **received invoices** (vendor bills with lines, tax, paid status and a proper posting: Dr the cost accounts + Dr input tax 1210 / Cr accounts payable 2010), **part payments** against them, **void with a journal reversal**, a **supplier register**, **payables aging**, **one-off expenses and other income** that post as they are saved, **recurring overheads** posted daily at 09:10 by `woodex:expense-recurring`, **project costs that finally reach the books** (and a project page that says *posted*), and a **profit & loss** built from posted lines. 20 more tests; suite **1,403 passed · 1 skipped · 7,576 assertions**. Live proof: bill Rs 1,15,000 posted, Rs 45,000 paid leaving Rs 70,000, rent schedule advanced to 26 Nov, project cost Rs 1,50,000 posted, P&L profit Rs 10,000, trial balance balanced at Rs 6,60,000 both sides.

**Slice H, part 2 — the client's money and a trial balance (26 October).** The two findings the audit recorded next. The client record now carries a **Money** card — Awaiting decision, Invoiced, Paid with the last payment date, and Outstanding, summed from the real invoices and payments (voided invoices excluded) — followed by the last ten invoices, each linking to its invoice screen; the card is inside `invoices.view`. Accounts gained **`/admin/accounts/trial-balance`**: every posted journal line in the range summed per account with debit, credit, net and entry count, totals and the difference, month/quarter/year/all presets, and a *"Balanced."* / *"Out of balance by …"* banner that refuses to celebrate an empty range. 11 more tests; suite **1,376 passed · 1 skipped · 7,461 assertions**. Recorded, not changed: the `accountant` role has no `accounts.view`, so it gets 403 on the trial balance.

**Slice H — CRM / Sales / Accounts deep QA, part 1 (26 October).** The audit found that an invoice had no screen at all: `/admin/invoices` was a read-only table with a PDF link, `/admin/invoices/{id}` was a 404, and `InvoicePolicy::void()` with the `invoices.void` permission had no caller anywhere. It also found that no screen could enter a bank account — the dashboard told the owner to use "Invoices → Bank accounts", a route that did not exist — so every invoice printed no payment details. Both are now real: `/admin/invoices/{invoice}` shows the frozen lines, totals, payments, ledger entry and client link, and collects a payment in place; the owner can void with a reason and the journal is **reversed**, never deleted, with a refusal when money has been recorded. `/admin/invoices?tab=bank` adds, edits, defaults and deactivates bank and wallet accounts. 15 more tests; suite **1,363 passed · 1 skipped · 7,418 assertions**.

**Slice G — hygiene (26 October).** The page builder stopped asking a font CDN for glyphs: all 26 families in its typography picker (87 woff2 faces) ship in `public/assets/fonts/` with one local `public/builder/fonts.css`, in both the picker and the theme export — a saved page now loads no external host. The three System hubs gained `can-any` on their routes, so they open only for someone who may use one of their tabs (support and accountant: 403). The 33 historical screenshots were re-encoded PNG → JPEG (13 MB → 5.6 MB) with every reference repointed, and `docs/README.md` now names the right current set. 7 more tests; suite **1,348 passed · 1 skipped · 7,354 assertions**.

**Slice E2 — recurring invoices (26 October).** A retainer now bills itself. The owner approved one new table: `recurring_invoices` holds the client, the standing line, the frequency (monthly / quarterly / yearly), tax, due days and the next run; `woodex:invoice-recurring` runs daily at 09:20 behind the Automation switch and raises one invoice per period under its own `RI-2026-0001` series — same frozen PDF and ledger posting as every other invoice — then advances the schedule. Paused and ended schedules bill nothing, a schedule that missed periods catches up with one invoice rather than a backlog, and `/admin/invoices?tab=recurring` creates, pauses, resumes, bills immediately and deletes. The freeze step moved into `Sales\Services\InvoiceFreezer`, shared with the milestone raiser. 9 more tests; suite **1,341 passed · 1 skipped · 7,210 assertions**. Live on 33143: a QA retainer produced `RI-2026-0001` (Rs 25,000 + 15% = Rs 28,750), a second run produced nothing, QA rows deleted.

**Slice D (26 October).** The Overview is a control view. One `ControlPanel` answers the six numbers the studio runs on — enquiries today, follow-ups overdue, quotations awaiting approval, money due (and how much of it is late), jobs running, automations failing — and every tile links to the list that filters to the same rows (`/admin/leads?due=overdue`, `?created=today`, `/admin/approvals`, `/admin/payments?tab=receivables`, `/admin/projects?status=active`). The "needs attention" list's button *is* the action, the five most overdue enquiries keep their WhatsApp and call links, the Reports page carries the same six numbers above its tabs with a new **Today** preset, and the tracking board's column is URL state. 15 more tests; suite **1,322 passed · 1 skipped · 7,116 assertions**.

**Slice C (21 October).** One pricing path. An estimator enquiry now writes its draft estimate the moment it arrives (listener on `LeadCaptured`), the quotation started from an enquiry converts that draft and links both sides (`quotes.estimate_id` ↔ `estimates.quote_id`), and `estimates:backfill` catches up enquiries captured before this slice. The Estimations screen is the real pipeline, not an empty room; the duplicate hub heading is gone. 11 more tests; suite **1,307 passed · 1 skipped · 7,044 assertions**.

## What is next (agreed order, from the audit)

| Slice | Work | Why it matters |
|---|---|---|
| ~~B~~ | ~~Sidebar truth~~ | **Done 21 Oct** — Client portal, Handovers and Chat Bot are live pages, System is 4 pages with tabs, Alert Rules de-duplicated, Estimator rates sits under Sales |
| ~~C~~ | ~~One pricing path~~ | **Done 21 Oct** — estimator enquiries write their draft estimate at capture; every new quotation carries an estimate; backfill command for older enquiries |
| ~~D~~ | ~~Overview as a control view~~ | **Done 26 Oct** — six tiles from one `ControlPanel`, each linked to its filtered list; needs-attention actions; Reports strip + Today preset; `?due=overdue` / `?created=today` on Leads |
| ~~E~~ | ~~Invoice automation~~ | **Done 26 Oct** — milestone invoices raised hourly for reached milestones, 7/14/30 overdue ladder with consent-gated WhatsApp and an audit record per rung; **E2** recurring retainers raise `RI-…` invoices daily at 09:20 from `/admin/invoices?tab=recurring` |
| ~~H~~ | ~~CRM / Sales / Accounts deep QA~~ | **Done 26 Oct** — invoice screen, in-place payment, void with ledger reversal, bank accounts, client money card, trial balance |
| ~~I~~ | ~~Money out~~ | **Done 26 Oct** — vendors, received invoices + payments + void, expenses, other income, recurring overheads, project-cost posting, P&L, AP aging |
| ~~J~~ | ~~Contact ladder~~ | **Done 26 Oct** — Clients, Customers and Visitors tabs on the Contacts hub, reference URLs 301 to them |
| ~~K~~ | ~~Sales proposals + pipeline~~ | **Done 26 Oct** — Proposals (in-negotiation quotes + value in play) and the Pipeline with a weighted forecast |
| ~~L~~ | ~~Telegram pairing~~ | **Done 26 Oct** — server QR pairing + connection health ladder; waiting only on the real bot token in the server `.env` |
| ~~M~~ | ~~Assistant + attribution~~ | **Done 26 Oct** — drawer on every page (nine cited, read-only questions) and source → won-value attribution |
| **F** | Marketing live proof once Meta/LinkedIn approve the app | Composer + publisher are real; only the platform key is missing |
| ~~R~~ | ~~Classic BOQ design match~~ | **Done 26 Oct** — summary-first BOQ, frozen sent PDFs, zero-money dashes |
| ~~S~~ | ~~Builder live preview + per-quote blocks~~ | **Done 26 Oct** — preview drawer with the real PDF, per-quotation theme/blocks, Type/Site/intro, printed-client overrides |
| ~~44~~ | ~~v2.1 template source import + design verify~~ | **Done 26 Oct** — the owner's own 16 starter templates import from the UI in one click; quote/invoice PDFs verified block-by-block against `admin-print17.js` (palettes, boxes, payments + stamp, signatures) |
| ~~V~~ | ~~Leads redevelopment (sheet import screen, drawer tabs)~~ | **Done 26 Oct** — owner-only `/leads/import` (column matching → privacy-safe dry run → idempotent apply over the existing importer), drawer Details/Log activity/Timeline tabs with quick chips that never erase a planned next action, business-line tabs + month filter on the list |
| **INT** | Integrations redevelopment (TailAdmin-style cards, real connect/test, custom webhooks; placeholders removed) | New 26 Oct — design in plan 35 |
| **W · T · U · X** | Estimations hub · template cards · invoice fields (needs the owner's sample invoice PDFs) · marketing remainder | Queue + blockers in plan 35 |
| ~~G~~ | ~~Hygiene~~ | **Done 26 Oct** — builder fonts self-hosted (26 families, 87 faces), System hubs gated by `can-any`, evidence 13 MB → 5.6 MB with references repointed, `docs/README.md` corrected |

## Owner-side blockers (unchanged)

Rotated secrets · GitHub `workflows` permission (one CI run) · AI-model key · PageSpeed key · Google OAuth client + second redirect URI · Meta/LinkedIn app approval.

## Known environment notes

* Clearing compiled views while the preview server is running returns 500s until it restarts — restart, don't retry.
* The page builder's canvas intentionally blocks page scripts; its two console messages are by design and do not affect rendering.
* Google Fonts cannot be reached from this sandbox; production is unaffected.

## Where the history lives

* `docs/audit/31_DEEP_QA_2026-10-21.md` — this QA pass (authority).
* `docs/audit/16_MASTER_TODO.md` — append-only build log.
* `docs/07-DECISIONS.md` — owner decisions.
* `docs/README.md` — which docs are current and which are historical.
