# 17 · P9 master plan — complete the operating system

_Status: draft for owner sign-off, 17 October 2026. Supersedes the "waves" in docs/16 §10 as the forward plan;
docs/16 stays the per-ID index until its rows are folded in here (tracked as P9-000)._

**What P9 is.** P1–P8 built a CRM, quotations, invoices, payments, projects, a CMS, a Telegram command layer and
reports. P9 turns that into a complete **interior + furniture operating system**: double-entry accounting, a
document/estimation template system, description and rate libraries, an agent layer that can actually run the
business from Telegram and WhatsApp, and a verification path that is honest about MySQL.

**How to read this file.** §1 is the verified inventory. §2 is the recovery problem. §3 is the gap analysis.
§4–§8 are the designs. §9 is the phase cards with acceptance criteria. §10 is the full pending register P1–P9.
§11 is sequencing, §12 verification, §13 risks, §14 what the owner must supply. Every "planned" item is
planned only — nothing in this document claims code that does not exist.

---

## 1 · Verified inventory (code, not recaps)

Checked at `arena/a2a9b311-woodexinterior` HEAD `6084…`/`2884841` with the preview running.

| Area | What exists | Counts |
|---|---|---|
| CRM | leads, contacts, companies, pipeline, tasks, visits, SLA, scoring, assignment rules, saved views, merge | 13 actions |
| Sales | quote builder, templates (execution/design/scope), rate book (`CatalogItem`), approvals, frozen versions, client portal, WhatsApp fallback | 13 models |
| Billing | invoices (milestone/advance/credit-note kinds), items, payments, allocations, receipts, bank accounts | 4 tables |
| Projects | projects, milestones, updates, handover items, portal access codes, review requests | shipped |
| Conversations | inbox, WhatsApp Cloud API gateway, templates, canned replies, consent/STOP | 4 actions |
| Telegram | command bus, callback bus (24 h expiry), S3 actions, health, change requests | 66 files |
| Website | 142 legacy URLs as CMS pages, blocks, media, menus, redirects, theme tokens, section library, page builder | 142 pages |
| Reports | sales funnel, revenue & collections, receivables | 3 screens |
| System | audit log, recycle bin, health, installer, DB update, scheduled-task runs, integrations | shipped |
| Tests | 50 feature files, 499 declared cases | last recorded gate 569 / 3,856 |
| **Accounting** | **none** — 0 references to journal, ledger, chart of accounts, expense, fiscal period, trial balance | **gap** |
| **Document design** | hard-coded Blade letterheads (`pdf/quote`, `pdf/invoice`, `pdf/receipt`), theme tokens for the website only | **gap** |
| **Description library** | quote item `description` text only; no reusable spec library | **gap** |
| **Estimation** | website estimator → draft quote (`CreateQuoteFromEstimate`), no BOQ templates or saved estimates | **partial** |
| **Agents** | no agent framework, no tool registry, no runs/cost ledger | **gap** |
| **Live chat** | legacy `chat.php` bridge only | **gap** |

---

## 2 · The recovery problem (blocking for four workstreams)

The 17 Oct session (App Connections, MCP bridge, Google sign-in, Description Library, Document Design, payment
and WhatsApp hardening, January-2025 import, `docs/20`, 744 tests) is **not on GitHub**. PR #3 stops at
16 Oct 22:12 UTC. The only copy is `woodexinterior-all-work-2026-10-17.zip` (42 MB, SHA-256 `d85094…07d`) in
Google Drive, unreachable from this sandbox.

Rules for recovery, so it cannot corrupt the line:

1. Extract to `/tmp/recovered`, verify the manifest hashes.
2. Diff the tree against the export's own base commit — **do not copy blind**; it may sit on a different tip.
3. Bring it in as **one commit per workstream** on `arena/a2a9b311-woodexinterior` (or a child branch), never as
   a squash of everything: App Connections / MCP, Description Library, Document Design, imports.
4. Re-run the full gate after each; a workstream that fails gets fixed before the next lands.
5. If an area is recovered, this plan **adopts** it and only fills gaps — it does not rebuild it.

If the export is never recovered, P9-4 (description library) and P9-5 (document design) are rebuilt from §7 and
§6 — roughly 8 extra focus days. That is the cost of the missing handover, not a design choice.

---

## 3 · Gap analysis by pillar

### 3.1 Sales → ERP
Today a quotation becomes a project and an advance invoice. There is no expense, no supplier, no purchase side,
no recurring cost, no ledger. "ERP" here means: **every rupee in and out is a journal entry**, and every screen
(invoice, payment, expense, bill) is a *document* that posts one.

### 3.2 Accounting (the largest gap)
Nothing exists. Needs: chart of accounts, fiscal periods with a lock, balanced journal entries with reversals,
posting rules for every existing document, opening balances, suppliers and bills, expenses and recurring
expenses, and the six reports an owner actually reads (trial balance, P&L, balance sheet, cash flow, AR/AP
aging, tax summary).

### 3.3 Documents and templates
Letterheads are Blade files. Changing the logo, accent, header text or footer line means a deploy. There is no
estimate document, no furniture variant beyond a name in settings, no item images or dimensions on a quote, and
the PDF opens in a new tab instead of the admin window.

### 3.4 Descriptions and rates
Rate book exists and is copy-on-use (correct: changing a rate never re-prices an old quote). But descriptions
are free text — no library by section and brand, no variables, no copy into the builder.

### 3.5 Estimation / BOQ
One website estimator that opens a draft quote. No saved estimates, no tier options (essential/standard/premium)
as documents, no BOQ with dimensions, no estimate → quotation with sections.

### 3.6 Automation, agents, Telegram, WhatsApp
Telegram has reads and four actions. No scheduled digests, no alert-rule builder, no agent runtime, no cost
caps, no outbound campaign logic. WhatsApp is single-event; no batching/dedupe, no bot state, no handoff.
The website has no live chat.

### 3.7 Platform
No CI in the repo (workflows exist only as templates in `docs/ci`), no MySQL anywhere, no docker-compose, no
concurrency tests. The preview runs php-wasm + SQLite.

---

## 4 · Accounting design (P9-A)

**Principles.** Double entry only through one service (`PostJournalEntry`). Integer paisa. Every posting is
idempotent per document event. Periods lock; a locked period accepts only reversals in the next open period.
Nothing in the codebase writes `journal_lines` outside the service.

### 4.1 Tables

```
accounts               id, code (unique), name, type(asset|liability|equity|income|expense),
                       subtype(bank|cash|ar|ap|tax|revenue|cogs|opex|equity|fixed_asset),
                       parent_id, is_system, is_active, currency, opening_minor, opening_on, position
accounting_periods     id, name, starts_on, ends_on, status(open|closed), closed_at, closed_by
                       unique(starts_on, ends_on); no overlaps (validated in service)
journal_entries        id, number (JE-YYYY-####), period_id, date, memo, status(draft|posted|reversed),
                       source_type, source_id, source_event, reversal_of_id, posted_at, posted_by, created_by
                       unique(source_type, source_id, source_event)  ← idempotency
journal_lines          id, entry_id, account_id, debit_minor, credit_minor, contact_id, project_id,
                       business_line, tax_code, memo, position
                       index(account_id, entry_id); service asserts Σdebit = Σcredit > 0
tax_codes              id, name, rate_bp, account_id, kind(output|input|exempt), is_active
```

### 4.2 Default chart (Pakistan, small studio)

| Code | Account | Type |
|---|---|---|
| 1010 / 1020 | Cash in hand / Bank — Meezan current | asset |
| 1100 / 1110 | Accounts receivable / Retention receivable | asset |
| 1200 / 1210 | Inventory — furniture / Work in progress | asset |
| 1500 | Furniture & equipment | asset |
| 2000 / 2010 | Accounts payable — suppliers / Accrued expenses | liability |
| 2100 / 2110 | Sales tax payable / Withholding tax payable | liability |
| 2200 | Client advances (unearned) | liability |
| 3000 / 3100 | Owner's equity / Opening balance equity | equity |
| 4000 / 4010 / 4020 | Interior revenue / Furniture revenue / Design fees | income |
| 5000 / 5010 | Cost of materials / Subcontractors | expense |
| 6000–6500 | Salaries, rent, utilities, marketing, transport, software | expense |

### 4.3 Posting rules (one row per document event)

| Event | Debit | Credit |
|---|---|---|
| Invoice issued (`kind=milestone/advance`) | AR 1100 (total) | Revenue 4000/4010 by line, Tax 2100 |
| Credit note issued | Revenue, Tax | AR |
| Invoice voided | reverse the original entry (new entry, `reversal_of_id`) | |
| Payment verified | Bank/cash (by `bank_account_id`) | AR |
| Payment rejected/rolled back | reverse | |
| Client advance received before invoice | Bank | Client advances 2200; invoice then consumes 2200 |
| Supplier bill approved | Expense/COGS + input tax | AP 2000 |
| Supplier payment | AP | Bank |
| Expense (paid at entry) | Expense + input tax | Bank/cash |
| Recurring expense run | Expense | AP (accrual) or Bank (if auto-paid) |
| Opening balance import | per account | Opening equity 3100 |

Rounding: the existing `rounding_minor` on quotes stays a revenue adjustment line; the journal must balance to
the paisa, so the service posts the rounding to 4900 "Rounding differences" when needed.

### 4.4 Reports (P9-C)
Trial balance, P&L (month + YTD, comparison), balance sheet, cash-flow (direct from bank/cash lines), AR aging
(reuse `ReceivablesReport`), AP aging, expense by category/brand, tax summary (output − input), and a
"books health" card: unposted documents, entries without source, period overlaps.

### 4.5 Migration from 2025 data
1. Load the chart + opening balances as of the cut-over date (one balanced opening journal).
2. Import invoices, receipts, bills, expenses from the owner's files — each becomes a document **and** a posting.
3. Reconcile: per-bank closing balance = statement; AR aging = client statements; AP aging = supplier ledgers.
4. Freeze: closing journal, close periods, mark the import batch `reconciled_at`.
An import batch table (`accounting_imports`) records file name, hash, rows, and reconciliation result, so a
rerun is idempotent like `V21Importer`.

---

## 5 · Sales ERP completion (P9-B)

- **Estimates as documents** (not just website leads): `estimates`, `estimate_items`, `estimate_options`,
  `estimate_templates`; tiers essential/standard/premium; area unit marla/kanal/sft with conversion table.
- **Estimate → quotation** with sections and milestones; keeps the estimator rate snapshot.
- **Manual invoice builder** (requested twice): invoice from scratch with sections/items, tax per line, due
  dates, bank block; milestone/advance invoices keep their paths.
- **Credit notes** with reason and posting.
- **Recurring revenue** (AMC/maintenance): schedule → draft invoice → approval.
- **Purchase side**: suppliers, bills, AP payments (P9-A tables).
- **Rate book v2**: versioned rates, effective dates, cost + margin, import CSV, per-brand catalog, and a
  "rate changed" audit line. Quotes keep their copied rate (already true).
- **Sales targets & commission**: monthly target per salesperson, commission on collected (not invoiced) —
  needs an owner rule before building.

---

## 6 · Document & template system (P9-D)

**One renderer, many designs.** `DocumentDesign` (key: `quote.interior`, `quote.furniture`, `invoice.interior`,
`invoice.furniture`, `estimate`, `receipt`, `description`) with a versioned JSON config; a Blade layout
(`pdf/document.blade.php`) renders any of them. Designs are **snapshotted by id + version + hash** into every
sent document, so a reprint in 2027 looks exactly like the copy the client signed.

Config surface (all editable in admin, no deploy):

| Group | Fields |
|---|---|
| Identity | brand name (interior/furniture), legal name, logo (media), NTN, tagline |
| Header | layout (left logo / centred / banner), address lines, doc title wording, accent rule |
| Colours | primary, accent, table header, muted, watermark |
| Footer | legal line, bank block, terms, page numbering, signature blocks |
| Table | show unit, show dimensions, show item images, section band colour, optional-items style |
| Content | default terms, scope block, milestone table, notes |
| Output | page size (A4/A5), margin preset, font stack (DejaVu only — dompdf) |

Item-level: optional `image` (media id), `dimensions` (W×H×D mm), `finish`, `material`, `item_code`,
`lead_time`. Quote lines keep a copy of those values at send time.

**Preview in-window** (P9-D2): a modal with `<iframe src="…/pdf?embed=1">` inside the admin; `?embed=1` sets
`Content-Disposition: inline` and hides admin chrome. No new tab, no download surprise.

---

## 7 · Description library & rate book (P9-E)

```
spec_sections   id, name, business_line, position          (Kitchen, Wardrobe, Flooring, Lighting, …)
spec_brands     id, name, logo_media_id, note             (Blum, Hettich, Merino, local…)
spec_items      id, section_id, brand_id, business_line, title, body_html,
                variables json (size, finish, material), unit, rate_minor, tags, is_active
```

- Insert into a quote line (copies HTML, never links live) or into the rate book as a new `CatalogItem`.
- Variables filled at insert: `{area}`, `{finish}`, `{brand}`, `{size}`.
- Search by section/brand/text; filters per business line; usage count.
- Prints as the item description block in the document (sanitised, dompdf-safe HTML subset).

---

## 8 · Automation, agents, Telegram, WhatsApp (P9-F/G)

### 8.1 Agent runtime (one framework, no vendor lock)
```
agents          key, name, role, model, system_prompt, tools json, limits json, is_active
agent_runs      id, agent_key, trigger(manual|schedule|event|telegram|whatsapp), input, output,
                status, tokens_in, tokens_out, cost_minor, started/finished, error
agent_tool_calls id, run_id, tool, args, result, ok, duration_ms, approval_id
agent_approvals id, run_id, action, payload, status(pending|approved|rejected), decided_by, decided_at
agent_policies  key, value json  (spend cap, quiet hours, allowed tools, approvals required)
```

**Rules:** tools are the existing Domain Actions. Read tools run freely; write tools require an approval row
unless the policy allows auto. Every call is audited and cost-capped (monthly, per agent). The bot never
touches money without `payments.verify` + owner approval.

Agents planned: **Master** (router/intent), **Sales** (follow-ups, quote drafts), **CRM** (dedupe, assignment,
SLA), **SEO/Content** (page briefs, meta drafts — drafts only, publishing stays human), **Finance** (chase
receivables, expense capture), **Support/Live** (WhatsApp + web chat). Each is a prompt + tool list + policy, not
a separate codebase.

### 8.2 Telegram master control
Existing: `/today` `/approvals` `/approve` `/assign` `/note` `/idea` `/health` + inline buttons.
Add: `/report sales|finance|aging`, `/balance`, `/expense <amount> <category>`, `/bill`, `/quote <id> send`
(approval-gated), `/agent status|pause|resume`, `/digest on|off`, `/rule <name>`, `/lock <doc>`.
Plus **digests** (S4: daily 08:00, weekly Mon, monthly 1st), **alert-rules builder** (S7: event → condition →
channel → throttle), **quiet-hours batching** and **unlink on user deactivation** (S8).

### 8.3 WhatsApp + web live chat
- Inbound webhook: batch, dedupe by `wa_message_id`, idempotent receive, bounded retry, signature check.
- Bot state machine: `new → qualifying → quoted → follow_up → human → closed`, intents from keywords + agent
  tool calls; handoff to a staff member pauses the bot for that contact.
- 24-hour window rules, template-only outside it, STOP/consent enforced in one place.
- Web widget: vanilla JS + signed session, polling (3 s) with Livewire-free endpoint; staff reply from the same
  inbox; typing/presence is polling-based until the owner picks Reverb/Pusher (CNV-006).

---

## 9 · Phase cards

Estimates are **focus days** for one agent with owner decisions available, excluding waiting time.

### P9-000 · Recovery + baseline — 1–2 d — **blocking**
Do: recover the export (§2), run the full gate, record numbers, add `.github/workflows/ci.yml` + MySQL service,
`docker-compose.yml`, fix the PDF preview (in-window).
Accept: gate numbers in `STATE.md`; CI green on the branch; preview opens PDFs inside the admin.

### P9-A · Accounting foundation — 5–7 d — **started 17 Oct** (schema, chart, posting/reversal services, invoice/payment postings, admin screens, 12 tests)
Tables §4.1, chart seeder, `PostJournalEntry` + `ReverseJournalEntry`, periods with lock, posting rules for
invoice/payment/credit-note/void, opening balances, screens: Chart of accounts, Journal, Periods.
Accept: every invoice and verified payment produces a balanced entry exactly once; reversal keeps the trial
balance at zero; locked period refuses postings; 12+ tests including idempotency and imbalance.

### P9-B · Expenses, suppliers, payables — 4–5 d
Suppliers, bills, bill payments/allocations, expense categories, expenses with receipt upload, recurring
expense templates + scheduler, approvals; posting rules.
Accept: AP aging ties to the ledger; a recurring expense run is idempotent; deleting a draft posts nothing.

### P9-C · Financial reports + 2025 reconciliation — 4–6 d
Trial balance, P&L, balance sheet, cash flow, AP aging, tax summary, books-health; CSV/print; 2025 import
batch + reconciliation report.
Accept: balance sheet balances; P&L YTD ties to trial balance; bank balances match statements; import rerun
produces zero new rows.

### P9-D · Document design system — 4–5 d
Design model + versions, one renderer, six designs, logo upload, header/footer/colour/table options, item
images and dimensions, in-window preview, frozen design in snapshots, furniture variant.
Accept: changing a design never alters a sent document; both brands render correctly; visual check in the
browser suite.

### P9-E · Description library + rate book v2 — 3–4 d
Tables §7, admin CRUD with section/brand filters, insert into quote/rate book, variables, usage stats; rate
effective dates + CSV import.
Accept: copied descriptions freeze at send; rate change does not touch existing quotes (regression test).

### P9-F · Estimation & BOQ — 4–6 d
Estimates, tiers, options, templates, area units, BOQ sections with dimensions, estimate → quotation, saved
service estimates, estimate PDF on the estimate design.
Accept: website lead → estimate → quote → invoice with no re-entry; totals identical across the chain.

### P9-G · Agents + Telegram master — 6–8 d
Runtime §8.1, approval gates, cost caps, audits; Telegram commands, digests, alert rules, S8 hardening.
Accept: an agent cannot spend money or send a quote without an approval row; every run is auditable; a day of
approvals completes from the phone (P5 exit).

### P9-H · WhatsApp bot + web live chat — 5–7 d
§8.3. Accept: duplicate webhook delivery creates one message; bot hands off cleanly; staff reply from the
inbox to the web widget; STOP blocks all outbound.

### P9-I · Furniture system — 3–4 d
Catalog with images/dimensions/finishes, WF numbering, furniture letterhead, showroom/stock-lite, BOQ lines.
Accept: a furniture quote shows pictures and specs; totals and margins correct.

### P9-J · Platform hardening & release — 5–7 d
MySQL concurrency tests (numbering, allocations, webhook dedupe, period lock), load pass on the inbox,
restore drill, off-site backups, Hostinger deploy dry run, release notes.
Accept: concurrency suite green on MySQL; restore drill documented; deploy checklist executed once end to end.

**P9 total: ~41–57 focus days.** Waves in §11 land accounting and documents first because everything else
depends on their data model.

---

## 10 · Pending register P1 → P9

Legend: **O** owner · **W** this workspace · **A** automation agent (unrecovered) · **—** done in code.

| ID | Task | Status now | Phase |
|---|---|---|---|
| SYS-009b | Off-site backups + alert on failure | open (no destination) | P9-J |
| TUR | Turnstile live keys | owner | P9-J |
| WEB-007b | Booking slots configuration | open | P9-H |
| P1-polish | Returning-client enquiry alert | unverified | P9-G |
| CNV-003b | @mentions in notes | open | P9-G |
| CNV-006 | Real-time (owner picks Reverb/Pusher) | owner decision | P9-H |
| CNV-007/008 | Conversation SLA; media sending | open | P9-H |
| CNV-009 | AI assist in inbox | open | P9-G |
| WAP-007/008/010 | Broadcasts, cost meter, quality monitor | open | P9-H |
| P5-S4 | Digests daily/weekly/monthly | open | P9-G |
| P5-S7 | Alert-rules builder | open | P9-G |
| P5-S8 | Unlink on deactivation, quiet hours | open | P9-G |
| V2-3 | Global blocks, collections, media, menus completion | partial | P9-K |
| V2-4 | SEO layer, auditor, `llms.txt`, crawler rules | open | P9-K |
| V2-5 | Conversion group by group + performance/a11y | open | P9-K |
| V2-6 | AI SEO assistant + Sveltia bridge | deferred decision | P9-K |
| P6-owner | Nohemi licence, real photos, GA4/GTM IDs, Search Console | owner | P9-K |
| P7 | Social: Meta connect, Messenger/IG, Lead Ads, composer | not started (needs Meta app review) | P10 |
| P8 | Portal/handover polish | shipped, verify | P9-J |
| P9-A…J | This plan | planned | P9 |

**Recovered-work rows** (App Connections, MCP, Google sign-in, Description Library, Document Design, batched
WhatsApp, 2025 preview import): treated as *exists in the export, absent here*. They enter this register only
after recovery; until then they are P9-000 tasks, not "done".

Contradictions in the recap that this plan does **not** inherit: 744 tests / 4,500 assertions (repo: 499 cases,
last gate 569/3,856); "66 tables" (preview: 70); "Description Library / Document Design shipped" (404 here).

---

## 11 · Sequencing

| Wave | Days | Content | Exit test |
|---|---|---|---|
| 0 | 1–2 | P9-000 recovery, CI+MySQL, PDF preview, baseline gate | CI green; numbers recorded |
| 1 | 9–12 | P9-A accounting, P9-B expenses/payables | trial balance zero per event; AP aging ties |
| 2 | 8–11 | P9-D documents, P9-E descriptions/rates, P9-F estimates | both brands print; estimate→quote→invoice |
| 3 | 11–15 | P9-G agents/Telegram, P9-H WhatsApp/live chat, P9-I furniture | phone-only approval day; duplicate-safe bot |
| 4 | 5–7 | P9-C reports + 2025 reconciliation, P9-J hardening/release | reconciled books; MySQL suite green |

P9-C sits in wave 4 because reconciliation needs the owner's 2025 files, which are also the slowest input.
The report screens themselves can be pulled into wave 2 if the files arrive early.

---

## 12 · Verification strategy

- **Gate every phase:** Pest, Larastan level 6, Pint, `npm run build`, `smoke.sh`, `browser-check.mjs`,
  `iframe-check.mjs`. No phase closes without a browser check.
- **MySQL:** this sandbox cannot install PHP/MySQL/Docker (no docker socket, no apt network, packagist blocked).
  Real verification goes to **GitHub Actions with a MySQL 8 service** plus a **docker-compose.yml** the owner can
  run anywhere. SQLite stays the fast local loop; MySQL-specific tests are tagged and skip locally.
- **Concurrency suite** (P9-J): invoice numbering under parallel requests (`SELECT … FOR UPDATE` on
  `number_sequences`), double payment verification, duplicate webhook, period lock race.
- **Data reconciliation:** every imported 2025 file gets a hash, a row count and a balance check; the report is
  stored with the import batch.
- **Honesty rule:** a claim is "done" only when a test or a screenshot proves it; `STATE.md` records the commit
  and the gate numbers.

---

## 13 · Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Export never recovered | 8 duplicated days, merge conflicts | Wave 0 recovers first; adopt, don't rebuild |
| MySQL behaves differently from SQLite | numbering/duplicate bugs in production | CI on MySQL + concurrency suite before release |
| 2025 records incomplete | books cannot be reconciled | opening-balance path + reconciliation report; owner checklist §14 |
| Tax treatment unknown | wrong postings, rework | tax codes table; owner decision before P9-A posting rules freeze |
| Shared hosting limits (no websockets) | "real-time" is polling | polling first, Reverb/Pusher only if owner approves |
| Agent autonomy | wrong money actions | write tools approval-gated, spend caps, audit, kill switch |
| Scope creep across 10 phases | nothing ships | phase cards with acceptance; one wave at a time |

---

## 14 · What the owner must supply

**Accounting (blocking P9-C):** 2025 sales invoices; receipts; supplier bills; expense receipts (with category);
all bank statements Jan–Dec 2025 (every account); opening balance sheet at 1 Jan 2025; closing balances at
31 Dec 2025; existing chart of accounts if any; tax status (NTN, sales-tax registration, filer) and rates;
list of suppliers and staff advances/loans; list of projects and their billing status at cut-over.

**Product:** furniture price list with photos, dimensions, finishes; rate book CSV if one exists; the five
quote/invoice samples the studio actually sends (to match the design); brand assets (logos, fonts licence).

**Access:** Meta app review started for WhatsApp/IG; Telegram bot token (already configured?); Google account
for company identity if OAuth stays in scope; off-site backup destination (Drive/S3).

---

## 15 · Proposed decisions (to record in docs/07 after sign-off)

- **D-117** Accounting is double-entry, single base currency (PKR), integer paisa, one posting service.
- **D-118** Periods close monthly; a closed period accepts adjustments only by reversal in the next open period.
- **D-119** Documents are rendered by one versioned design system; sent documents bind the design version hash.
- **D-120** Descriptions and rates are copy-on-use; libraries never retro-price.
- **D-121** Agents act only through typed tools; write tools need approval unless the policy explicitly allows.
- **D-122** Verification of record is CI on MySQL; the sandbox is a fast loop, not the proof.
- **D-123** Estimates, quotes and invoices share one totals engine (`QuoteCalculator` family) — no parallel maths.

---

## 16 · Build log (P9, newest first)

- **17 Oct — P9-A first slice landed:** migration `2026_10_17_090000_create_accounting_tables` (`accounts`,
  `accounting_periods`, `tax_codes`, `journal_entries`, `journal_lines` with the unique document-event key);
  enums (`AccountType`, `AccountSubtype`, `PeriodStatus`, `EntryStatus`); models (`Account`, `AccountingPeriod`,
  `TaxCode`, `JournalEntry`, `JournalLine`); services `ChartOfAccounts`, `PostJournalEntry`,
  `ReverseJournalEntry`, `LedgerPoster`; 25-account default chart + 18% output/input tax codes; period lock;
  ADVANCE and milestone invoices post when issued; every receipt posts money-in; rejected payments reverse;
  screens `admin.accounting.chart|journal|periods` behind `accounting.view|manage|close`. Gate: **581 passed /
  3,902 assertions / 1 skipped**, Larastan clean, Pint clean (565 files). Preview verified: the three screens 200
  as the owner.
- **17 Oct — P9-000 groundwork:** `.github/workflows/platform-ci.yml` (SQLite + MySQL 8.4 jobs, assets),
  `docker-compose.yml` + `platform/Dockerfile` for a local MySQL runtime.

---

_Next action after sign-off: P9-000 (recovery + CI + PDF preview), then P9-A wave 1._
