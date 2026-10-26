# Woodex OS — Project Status

**Updated:** 26 October 2026 · **Branch:** `arena/8be318a2-woodexinterior` · **Preview:** port 33143 (`owner@woodex.test` / `Woodex-Preview-2026`)

**One line:** the OS is real and database-backed; the gap QA found was the path *between* screens — an enquiry could become a lead but never an estimate, and the Overview showed counts you could not open. Both paths are now closed and tested. Everything else that looks empty is either honestly empty (no data yet) or blocked on a key the owner must approve.

> The detailed findings, evidence and ordered plan are in **[`docs/audit/31_DEEP_QA_2026-10-21.md`](docs/audit/31_DEEP_QA_2026-10-21.md)**. That file is the current authority; older status claims in this repo are superseded.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · SQLite (preview) → MySQL (production plan) |
| Tests | **1,403 passed · 1 skipped · 7,576 assertions** · Pint clean · PHPStan 0 errors |
| Frontend | v1 export (142 pages) is the only public site; the page builder writes into `woodex/` |
| Scheduler | 25 jobs registered (SLA, reminders, automations, social publish, digests, backups) |
| Environment | Preview runs `APP_ENV=local`; the header shows a **Demo data** pill so seeded records are never mistaken for clients |

## Next: the 26 October master plan

`docs/audit/32_MASTER_PLAN_2026-10-26.md` sets the forward order. **Slice I is shipped** (below); next is **Slice L**
— Telegram QR pairing and a connection-health card that names the failing step — then the contact ladder (J),
proposals + forecast (K), the assistant drawer (M), and Search Console over MCP (N, blocked on the Google key).

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
| **L** | Telegram pairing | QR pairing + connection health card — next |
| **F** | Marketing live proof once Meta/LinkedIn approve the app | Composer + publisher are real; only the platform key is missing |
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
