# Woodex OS — Project Status

**Updated:** 2 November 2026 · **Active branch:** `arena/7093000c-woodexinterior` · **Native synthetic preview:** port 8080

## Current execution record — revisions 98 / 99

**Owner ordering:** finish remaining pending/paused work first; deployment is the final task. Candidate native CI [41495382678](https://github.com/woodex420/woodexinterior/actions/runs/41495382678) is now **successful** at `1640506`; no merge/deployment follows automatically.

[Consolidated master plan / resume instructions](docs/audit/98_MASTER_COMPLETION_PLAN_2026-11-02.md) · [30-item task register](docs/audit/98_COMPLETION_REGISTER_2026-11-02.csv).

- Master baseline `3fcaff8e0f78aaaa2e6e2aed2011541fd4cff944` includes merged release 97; its native GitHub run passed **1,818 / 10,348 assertions**. This does not certify newer edits or production deployment.
- **Full real Laravel admin + public preview is running**, not a static mock: verified native PHP 8.5.12, genuine Composer 2.9.1, existing lock, fresh isolated synthetic SQLite and 103 migrations. Providers reject outgoing Laravel HTTP; mail is array-only; no worker/scheduler. No production data restored.
- **Two source fixes with regression tests:** full-range expense/income totals beyond the 300-row display cap; exact decimal project costs through `Money`, not float. No schema, lock, frontend, document-history or production changes.
- **Current local evidence:** 196 native tests / 786 assertions; 92 source contracts; Larastan 0 for changed components; Pint pass; 24 real-browser checks plus 4 local cross-site iframe/session/navigation checks. Full candidate remote CI for `1640506` is successful; whole-action/PDF QA and subsequent-candidate checks remain separate gates.
- Drive inventory/hashes refreshed; **archive contents comparison remains unfinished**. No automatic archive overwrite or import. Workbook reconciliation/import is deferred.
- **Owner-only Telegram source enforcement implemented:** pairing, webhook/command/callback authority, notification/quiet-hour/queued delivery, fresh role/link checks, profile/integration controls. Staff in-app notifications/email fallback retained; legacy links preserved without authority. **Full local native:1,857 pass/10,510 assertions;37 current browser checks; static/style/build/source contracts pass.** [Increment 99](docs/audit/99_OWNER_ONLY_TELEGRAM_2026-11-02.md) records current QA; new-candidate hosted CI remains separate.
- **Owner choices confirmed:** both unpaid bills and paid costs with linked payments/single accounting source; previously deferred workbook/Sheets/backup/AI extras stay deferred. Deployment is last.
- **Not complete:** ledger cross-model/recovery acceptance, linked Vendors/Projects workflow, live owner Telegram receipt, Website Chat/UI/SEO/media/speed refinements, Assistant diagnostics, full candidate tests, isolated staging and verified restore. See the register for exact gaps/dependencies rather than restarting merged work.
- Candidate source is not merged/deployed. Production installed SHA/schema and backup restore remain unverified. **Auto-deployment stays OFF.** GitHub/active branch work; no new branch/master prompt/Continue or repeated SSH setup is needed.

---

## Historical records (retained; current status is above)

**Updated:** 29 October 2026 · **Branch:** `arena/8be318a2-woodexinterior` · **Preview:** port 33143 (local demo)

> **Current request — revision49: Website-first audit and four-phase plan; implementation approval pending.**
> All seven requested Website pages reviewed in source and Chromium; v2.1 ported Media/Theme/Speed source
> compared. Baseline **120 tests /845 assertions**; all seven pages HTTP200, no page JS errors.
> Confirmed gaps: unheaded navigation, Media parity, missing live Theme/Settings preview, misleading
> configured/verified labels, oversized staff chat and Speed mobile overflow/low-contrast cache pill.
> Public chat is **off locally**; production state unverified. Owner requests charcoal instead of navy.
> [Master plan49](docs/audit/49_WEBSITE_OS_FOUR_PHASE_MASTER_PLAN_2026-10-29.md) ·
> [Seven-page QA49](docs/audit/49_WEBSITE_PAGE_QA_2026-10-29.md).
> No application fixes, schema, cleanup/deletion, production sends, deploy or merge in this audit.
>
> **Current delivery — revision48-A: native theme and website-chat capture upgrade delivered.**
> Light/Night/System, accurate environment note outside the top bar, early SQL-backed CRM capture,
> Leads → Web Chat, closed-thread reopening and draft-safe targeted alerts reuse existing modules.
> Long emails no longer overflow intake state; public service text cannot invoke staff mention commands.
> **Full:1722 passed /1 skipped /9199 assertions** before the final three safety guards;
> **final affected:218/884**,26 new cases,PHPStan0,Pint8,Vite,7 theme unit tests and37 native browser checks pass.
> Main preview has0 conversations/0 QA48 contacts; synthetic browser data stayed in a separate QA copy.
> Local Telegram token/secret are absent; production/mobile delivery remain unverified. No schema,
> provider connections/sends, historical backfill, deploy or merge. Social/provider phases remain gated.
> [Implementation48-A and evidence](docs/audit/48A_NATIVE_THEME_WEB_CHAT_UPGRADE_2026-10-29.md) ·
> [Phased master plan48](docs/audit/48_THEME_OMNICHANNEL_MASTER_PLAN_2026-10-29.md).
>
> **Previous implementation — revision47: approved CRM and communications redesign delivered.**
> Advanced Leads workspace/detail/activity, Alerts dropdown/centre, Inbox and staff/visitor Live Chat
> reuse the native Laravel/Livewire modules. Real archive/search/filter/assignment/history controls;
> fresh access/channel guards; retained visitor drafts/retry; toasts no longer block the composer.
> **Full:1699 passed /1 skipped /9109 assertions**; final UI/finance157/547; new24/82; PHPStan0,
> Pint10, Vite and20 desktop/mobile Chromium checks pass. Tests mutate only a separate QA copy.
> Revision46 financial repairs remain unchanged. **F05 definitions, production reconciliation and
> whole-OS every-action certification remain open.** No schema, provider-send policy, production
> corrections, deployment or merge. [Report47 and screenshots](docs/audit/47_CRM_COMMUNICATIONS_REDESIGN_2026-10-29.md) ·
> [Financial repair46](docs/audit/46_PAYMENT_LEDGER_REPAIR_QA_2026-10-28.md) ·
> [Master audit44](docs/audit/44_WOODEX_OS_MASTER_QA_CONTROL_CENTRE_PLAN_2026-10-28.md).

> **Historical revision42 — approved Interior integration and Furniture polish delivered.** Owner reviewed the
> preview then said **“good update now”**, also requesting Furniture logo/header/cards/footer and bottom
> terms/signatory controls. New quotation-only Interior2 / Furniture4 renderers are implemented. Native
>12-row Interior and5-row Furniture references each fit1 A4; commercial/long documents paginate safely.
> Final gate: **1,620 passed /1 skipped /8,704 assertions /461.13s**, focused74/434, PHPStan0, Pint11,
> Vite/native desktop/390px/PDF/restricted-cookie framing pass. Existing invoices, old renderers and frozen
> history are preserved. No schema or production deployment. [Report42](docs/audit/42_INTERIOR_IMPLEMENTATION_FURNITURE_POLISH_2026-10-27.md).
> [Interior screenshot](docs/audit/evidence/42_interior_quotation.png) · [Furniture screenshot](docs/audit/evidence/42_furniture_quotation.png).
> Existing editable drafts opt in via **Apply current design**; no silent historical upgrade.
>
> Previous Furniture revision3 release at34f73c4 remains available for frozen history;
> [report40](docs/audit/40_FURNITURE_HTML_REFERENCE_AUDIT_2026-10-27.md) records its older gate. Current
> verification is report42 above. Preview33143 remains a fresh demo, not restored owner data.
>
> Previous Slice W remains delivered: native Estimates / Rates / Estimator Enquiries and all eight audited
> workflow fixes; [report39](docs/audit/39_ESTIMATIONS_HUB_AUDIT_PLAN_2026-10-27.md).

**One line:** revision49 Website-first audit/plan delivered, Phase1 approval pending; revision48-A remains the implemented baseline. Social/provider work follows the new owner ordering and remains gated. F05, production reconciliation and whole-OS certification remain open.

> The detailed findings, evidence and ordered plan are in **[`docs/audit/31_DEEP_QA_2026-10-21.md`](docs/audit/31_DEEP_QA_2026-10-21.md)**. It remains the historical baseline; reports44–48-A above govern the current increment and outstanding financial findings.

| Quick facts | |
|---|---|
| Stack | Laravel 13 · Livewire 4 · Tailwind 4 · SQLite (preview) → MySQL (production plan) |
| Tests | Revision48-A full: **1722 passed /1 skipped /9199 assertions** before final guards; final affected218/884, new26 cases, browser37, theme unit7. Financial F05 remains deferred. |
| Frontend | v1 export (142 pages) is the only public site; the page builder writes into `woodex/` |
| Scheduler | 25 jobs registered (SLA, reminders, automations, social publish, digests, backups) |
| Environment | Preview remains `APP_ENV=local`; an accurate **Local preview** note is in the user menu. It does not infer record ownership or production status. |

## Earlier delivery plan: 26 October master plan

`docs/audit/32_MASTER_PLAN_2026-10-26.md` sets the forward order. **Slices I–M are shipped** (below); the last
planned slice, Search Console over MCP (N), is blocked on the Google key. Telegram has native configuration,
pairing, webhook, command and test paths; current production configuration, destination and mobile receipt
remain unverified (see revision48). The earlier token-only blocker is not a current diagnosis. Plan 34 followed (Classic BOQ, builder live
preview), and Step 44 imported the real v2.1 Pro template source and verified the document
design against it. The current queue — with the v2.1-vs-OS gap report and the new
integrations slice — is **[`docs/audit/35_PENDING_TASKS_REFRESH_2026-10-26.md`](docs/audit/35_PENDING_TASKS_REFRESH_2026-10-26.md)**;
the owner picks the next slice from it.

## Latest delivery — Interior reference integration + Furniture polish (27 October)

- Owner-approved Interior Classic BOQ revision2: source navy/ochre layout, W identity, local fonts,
  native section summary/BOQ/net totals, existing commercial blocks and historical-safe routing.
- Furniture revision4: improved green W lockup/header and paired client/project cards; contact strip
  above terms, centred editable signers, always-available bottom show/hide controls and automatic
  editable-percentage payment sentence. Existing50% default and all pricing/accounting stay unchanged.
- Actual native reference PDFs: Interior12 rows and Furniture5 rows each1 A4; Interior commercial2 pages,
 55-work stress5 pages; Furniture45-product stress8 pages, all text retained and in bounds.
- Final full **1,620 passed /1 skipped /8,704 assertions**, focused74/434; static/build/browser/PDF and
  nested cookie-restricted checks pass. Invoices/old revisions/frozen PDFs preserved; no schema/deploy.
- [Native Interior screenshot](docs/audit/evidence/42_interior_quotation.png) ·
  [native Furniture screenshot](docs/audit/evidence/42_furniture_quotation.png) ·
  [report42](docs/audit/42_INTERIOR_IMPLEMENTATION_FURNITURE_POLISH_2026-10-27.md).
- Existing editable drafts: **Apply current design**. Owner screenshot review next; broader T/U remains separate.

## Previous delivery — Furniture quotation HTML-reference layout (27 October)

- New quotation-only revision3 adopts the attached A4 header/client strip, green alternate-row table,
  right-aligned totals/remainder, structured terms and slim footer; existing native fields and logo retained.
- **50% editable default and native tax/transport calculations remain unchanged.** Example native total
  Rs661,050 → Rs330,525 requested advance + Rs330,525 remainder, on1 A4 page. No fixed75%/13% rules imported.
- Local embedded Inter fonts; no CDN dependencies or public-v1 change. Invoices retain their DM Sans/v2
  renderer, including conversions from new quotation metadata. Revision1/2 templates remain unchanged.
- Existing editable drafts upgrade only through **Apply current design**; sent history/stored PDFs remain
  frozen.7 new permanent regression cases plus42 focused document/live-preview cases pass.
- Actual normal/reference/small/invoice PDFs are1 page;45-row long-description pagination test is9 pages,
  all products/bounds/page numbers checked. Desktop/390px/focus/optional-controls and nested-cookie proof pass.
- Final full gate: **1,608 passed /1 skipped /8,615 assertions**. The skip is the live MySQL probe; no MySQL
  production-runtime claim. No new schema or production deployment. Next: owner review before broader T.
- Visual deliverable: [native demo PDF](docs/audit/evidence/40_furniture_quotation.pdf);
  [verification](docs/audit/evidence/40_furniture_release_results.md).

## Previous delivery — native Estimations hub (27 October)

- One Sales hub: **Estimates / Rates / Estimator Enquiries**, search/filter/pagination and scoped actions;
  website-only rate editors do not gain CRM access. Legacy Website estimator URL redirects to Rates.
- Shared publisher: explicit publication, named published-price backups/load-as-draft, drift indicator,
  native revision conflicts, cache lock, file/DB compensation and unchanged public catalogue.
- Frozen saved prices and read-only converted history; explicit reprice, review invalidation, strict
  quantities, labelled/responsive add-ons, unified retry-safe Interior/Furniture conversion.
- Real browser publish250→275 retained the saved Rs2,500 quotation rather than Rs2,750 current preview;
  selected add-on live pricing and390px fit passed. Authentic public JS/native PHP matched18 base cases.
- Active33143 and isolated33144 pass third-party-cookie-blocked/no-cookie iframe checks. Actual generated
  Furniture1-page and Interior2-page PDFs parsed/rasterised/visually inspected. Existing renderers unchanged.
- No new schema, destructive data work or production deployment. Live MySQL is not validated here.
- **Recommended next:** audit Slice T's remaining quotation-template cards/chips; request its own decision
  before code/schema work. U remains sample-blocked. Evidence: [39 release results](docs/audit/evidence/39_estimations_release_results.md).

## Previous delivery — Furniture A4 polish (27 October)

The owner-approved second Furniture pass is complete in the existing Sales module. New Furniture quotations
and invoices use the reference-like A4 letterhead: logo/company left, document metadata right, repeated print
header/footer and grey/green thank-you band. The table now says **Product / Description**, without Amount in words.

- Wider summary; optional zero discount/tax and combined **Rent / Transport**. Applied charges never disappear.
- **50% editable new default**, with matching automatic Terms and conditions wording:
  **Rs47,500 → Rs23,750 requested / Rs23,750 remaining**. Existing explicit 75% templates/documents stay 75%.
- Per-document optional sections; editable centred signatories copied from settings, with individual removal or whole-section hiding.
- New quotes use exact paisa, not a rounding row. Legacy transport charges remain preserved and disclosed.
- Requested advance remains separate from actual verified payments/outstanding; native WFI full-invoice conversion,
  issue/frozen snapshot, ledger and receipts remain intact. No new schema in this polish.
- Versioned renderer preserves old sent/issued documents. Existing drafts adopt via **Apply current design**.
- **Final gate:** 1,577 passed / 1 skipped / 8,418 assertions; PHPStan 0; Pint16 files; build successful.
- Isolated Chromium: live fields, percentage/terms, optional sections/signatories, 390px, conversion/issue,
  Interior regression, zero JS errors. Both cookie-restricted iframe modes pass on33144 **and33143**.
- Real PDFs: quotation/invoice/Rs47,500 example **one page each**; 45-row long-description stress **eight pages**,
  with repeated headers/footers, full text and embedded fonts checked; raster proofs inspected.
- Full evidence/boundaries: [audit38](docs/audit/38_FURNITURE_A4_POLISH_2026-10-27.md).
  Initial Furniture schema/workflow delivery remains documented in [audit37](docs/audit/37_FURNITURE_DOCUMENTS_2026-10-27.md).

**W remains paused for the owner's Furniture design acceptance.** Preview33143 is updated, the isolated QA server
is stopped, and no owner's draft was changed or stale database restored. No production deployment/MySQL runtime
validation is claimed. Public frontend v1 remains locked.

## Previous correction — Step 48 (27 October)

The owner paused Slice W and approved the quotation correction after a source comparison.
The existing quotation page now has an **always-shown desktop side preview**, updating from
**unsaved inputs while typing**, independently of save-on-blur. The source-derived v2.1
**Classic BOQ / Single page / Project** layouts and **Classic / Minimal / Premium** colour
designs are selectable above it; DM Sans, header/boxes/tables/totals/footer replace the OS
quotation shell. Both interior and furniture use the v2.1 palettes (no legacy green override).
Saved PDFs share the renderer; sent versions remain frozen. No schema or public-v1 changes.
Browser proof covered both business lines, all layouts, mobile width and cookie-restricted
Arena framing. See **[`docs/audit/36_QUOTATION_WORKBENCH_2026-10-27.md`](docs/audit/36_QUOTATION_WORKBENCH_2026-10-27.md)**
for the source comparison, proof and explicit pagination/row-kind boundaries. **W remains paused
until the owner reviews this correction.** Earlier Slice S/R/Step 44/46 descriptions below are
historical and do not supersede this correction.

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
| ~~46~~ | ~~Live preview rebuilt, v2.1-style~~ | **Done 26 Oct** — quotation (interior + furniture) and invoice builders preview the document itself as you edit (the same HTML the PDF prints from), replacing the Chrome-blocked framed PDF; PDFs now frame only inside the admin |
| ~~INT~~ | ~~Integrations redevelopment~~ | **Done 27 Oct (Step 47)** — one 14-card grid, shared Details, real Facebook Page identity check, no roadmap cards, signed outgoing webhooks with queue/retries/history; external live proof still needs owner credentials |
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
