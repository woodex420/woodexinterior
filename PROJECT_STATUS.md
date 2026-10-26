# Woodex OS — Project Status

**Updated:** 26 October 2026 · **Branch:** `arena/8be318a2-woodexinterior` · **Preview:** port 33143 (`owner@woodex.test` / `Woodex-Preview-2026`)

**One line:** the OS is real and database-backed; the gap QA found was the path *between* screens — an enquiry could become a lead but never an estimate, and the Overview showed counts you could not open. Both paths are now closed and tested. Everything else that looks empty is either honestly empty (no data yet) or blocked on a key the owner must approve.

> The detailed findings, evidence and ordered plan are in **[`docs/audit/31_DEEP_QA_2026-10-21.md`](docs/audit/31_DEEP_QA_2026-10-21.md)**. That file is the current authority; older status claims in this repo are superseded.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · SQLite (preview) → MySQL (production plan) |
| Tests | **1,332 passed · 1 skipped · 7,165 assertions** · Pint clean · PHPStan 0 errors |
| Frontend | v1 export (142 pages) is the only public site; the page builder writes into `woodex/` |
| Scheduler | 25 jobs registered (SLA, reminders, automations, social publish, digests, backups) |
| Environment | Preview runs `APP_ENV=local`; the header shows a **Demo data** pill so seeded records are never mistaken for clients |

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

**Slice D (26 October).** The Overview is a control view. One `ControlPanel` answers the six numbers the studio runs on — enquiries today, follow-ups overdue, quotations awaiting approval, money due (and how much of it is late), jobs running, automations failing — and every tile links to the list that filters to the same rows (`/admin/leads?due=overdue`, `?created=today`, `/admin/approvals`, `/admin/payments?tab=receivables`, `/admin/projects?status=active`). The "needs attention" list's button *is* the action, the five most overdue enquiries keep their WhatsApp and call links, the Reports page carries the same six numbers above its tabs with a new **Today** preset, and the tracking board's column is URL state. 15 more tests; suite **1,322 passed · 1 skipped · 7,116 assertions**.

**Slice C (21 October).** One pricing path. An estimator enquiry now writes its draft estimate the moment it arrives (listener on `LeadCaptured`), the quotation started from an enquiry converts that draft and links both sides (`quotes.estimate_id` ↔ `estimates.quote_id`), and `estimates:backfill` catches up enquiries captured before this slice. The Estimations screen is the real pipeline, not an empty room; the duplicate hub heading is gone. 11 more tests; suite **1,307 passed · 1 skipped · 7,044 assertions**.

## What is next (agreed order, from the audit)

| Slice | Work | Why it matters |
|---|---|---|
| ~~B~~ | ~~Sidebar truth~~ | **Done 21 Oct** — Client portal, Handovers and Chat Bot are live pages, System is 4 pages with tabs, Alert Rules de-duplicated, Estimator rates sits under Sales |
| ~~C~~ | ~~One pricing path~~ | **Done 21 Oct** — estimator enquiries write their draft estimate at capture; every new quotation carries an estimate; backfill command for older enquiries |
| ~~D~~ | ~~Overview as a control view~~ | **Done 26 Oct** — six tiles from one `ControlPanel`, each linked to its filtered list; needs-attention actions; Reports strip + Today preset; `?due=overdue` / `?created=today` on Leads |
| ~~E~~ | ~~Invoice automation~~ | **Done 26 Oct** — milestone invoices raised hourly for reached milestones, 7/14/30 overdue ladder with consent-gated WhatsApp and an audit record per rung; recurring invoices wait on a schema decision |
| **F** | Marketing live proof once Meta/LinkedIn approve the app | Composer + publisher are real; only the platform key is missing |
| **G** | Hygiene: self-host fonts, trim historical screenshots, docs authority index | Small, safe, keeps the repo honest |

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
