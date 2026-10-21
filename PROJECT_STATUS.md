# Woodex OS — Project Status

**Updated:** 21 October 2026 · **Branch:** `arena/8be318a2-woodexinterior` · **Preview:** port 33143 (`owner@woodex.test` / `Woodex-Preview-2026`)

**One line:** the OS is real and database-backed; the gap QA found was the path *between* screens — an enquiry could become a lead but never an estimate. That path is now closed and tested. Everything else that looks empty is either honestly empty (no data yet) or blocked on a key the owner must approve.

> The detailed findings, evidence and ordered plan are in **[`docs/audit/31_DEEP_QA_2026-10-21.md`](docs/audit/31_DEEP_QA_2026-10-21.md)**. That file is the current authority; older status claims in this repo are superseded.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · SQLite (preview) → MySQL (production plan) |
| Tests | **1,284 passed · 1 skipped · 6,976 assertions** · Pint clean · PHPStan 0 errors |
| Frontend | v1 export (142 pages) is the only public site; the page builder writes into `woodex/` |
| Scheduler | 25 jobs registered (SLA, reminders, automations, social publish, digests, backups) |
| Environment | Preview runs `APP_ENV=local`; the header shows a **Demo data** pill so seeded records are never mistaken for clients |

## What is live today (verified this pass)

* **CRM** — leads, pipeline, tracking board, contacts, companies, tasks, visits, assignment rules, Telegram alerts.
* **Sales** — rate-book estimate builder, **enquiry → estimate → quotation draft** chain (new), invoices/payments actions, approvals, PDFs.
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

## What is next (agreed order, from the audit)

| Slice | Work | Why it matters |
|---|---|---|
| **B** | Sidebar truth: enable Client Portal / Handovers / Chat Bot, remove the duplicate Alert Rules, fold System's 11 entries into 4 pages with inner buttons, rename Content → "Website estimator (rates)" | The sidebar still calls shipped features "P4/P4b/P5" and splits one page group across two sections |
| **C** | One pricing path: a quotation must always come from an estimate record | Estimate history is currently optional |
| **D** | Overview as a control view: six live KPIs + "needs attention" with one-click actions; reports as filters on the same queries | The owner asked for control, not report prose |
| **E** | Invoice automation: milestone/recurring invoices and an overdue reminder ladder, visible in Automation | "Invoice automation" has no live runtime today |
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
