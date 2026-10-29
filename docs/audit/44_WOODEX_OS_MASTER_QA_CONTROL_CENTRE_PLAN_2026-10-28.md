# 44 — Woodex OS master QA and Control Centre redevelopment plan

**Date:** 28 October 2026
**Decision:** approved by the owner’s “Start” for 44-A/44-B. Execution is in progress; this is not authorization for a whole-system rewrite.
**Execution:** see [first increment and QA report](44_CONTROL_CENTRE_IMPLEMENTATION_QA_2026-10-28.md). Master every-action coverage remains incomplete.
**Follow-up:** [45 transactional/ledger audit](45_TRANSACTIONAL_LEDGER_AUDIT_2026-10-28.md) reproduced Payments/GL defects; owner approved 45-B with “Start”. [46 repair/QA](46_PAYMENT_LEDGER_REPAIR_QA_2026-10-28.md) records F01–F04 repairs and read-only reconciliation. [47 CRM/communications redesign](47_CRM_COMMUNICATIONS_REDESIGN_2026-10-29.md) subsequently delivered the approved native Leads/Alerts/Inbox/Activity/Live Chat increment. F05 definitions, production reconciliation and whole-OS sign-off remain open.
**Scope:** every owner-listed navigation entry, its tabs, secondary screens, links, actions, APIs, PHP/domain services, database effects, permissions, jobs and external dependencies.

## Execution checklist

- [x] Record owner approval and keep native Laravel/Livewire + locked v1 architecture.
- [x] Persist native route/component/schema discovery and all 48 navigation dispositions.
- [x] Reproduce financial/status/date defects and implement the scoped first Control Centre increment.
- [x] Exercise native filters, selected-role guards, header/mobile controls and navigation landings.
- [x] Persist evidence, metric definitions, initial findings and remaining limitations.
- [ ] Complete every-action workflow/API/database attestation beyond the first increment.
- [ ] Finish all-role, ledger/scale/recovery, real-provider and MySQL/deployment-host verification.
- [ ] Present later domain repair findings for scoped approval before unrelated rewrites.

## 1. Executive recommendation

Keep the native Laravel/Livewire OS and the locked v1 website. Upgrade the existing **Dashboard + My Day / Approvals / Reports** into a coordinated Control Centre over existing domain services. Do not introduce a second CRM, accounting engine, automation runtime or JavaScript admin.

Start with read-only discovery and reproduced findings; implement the approved first Overview increment against verified findings and agreed metric definitions. Continue the whole-system action audit without treating landing coverage as completion. Fix subsequent modules in risk order, with a report after each slice. No schema change, new financial approval policy, destructive operation, external campaign/send, deployment or merge is authorized by this planning document.

**Recommended decision:** approve the master audit and the first Control Centre slice described here. Findings in other modules become a prioritized repair backlog, not automatic permission to redevelop every module at once.

## 2. Evidence and honest audit status

### Planning baseline (before execution)

- Current native admin route definitions and navigation configuration.
- `DashboardHub`, `Dashboard`, `MyDay`, `ReportsHub`, `Sales/Approvals` and the shared hub implementation.
- `ControlPanel`, `RevenueCollectionsReport`, `ReportRange`, invoice statuses and manual-invoice creation.
- Dashboard/header/sidebar views and the live-chat header component.
- Existing overview/report/approval tests and earlier audit/reuse plans32/35/43.

**Fresh executable baseline:** `ControlViewTest`, `ReportsKitTest`, `ApprovalEditSafetyTest`: **50 passed,214 assertions,16.44s**. These tests ran in the local native test runtime, not against production data.

The prior quotation release's full-suite result is historical evidence, not proof that every page/function requested in this new audit has been checked. No new full-system browser sweep, database reconciliation or live third-party delivery was completed in this planning pass. The owner-provided `arena.site` hostname was not externally browser-verified here; native-runtime and deployment-host verification must be recorded separately.

### Initial findings — pre-implementation snapshot; dispositions in the execution report

| ID | Priority | Evidence / finding | Required next check |
| --- | --- | --- | --- |
|44-F01|P1|Revenue `months()`, `totals()` and `byLine()` exclude only Void invoice status. Manual invoices can be Draft with an `issue_date`, so the queries admit drafts into “Invoiced”. Source-confirmed inclusion; not yet a fresh isolated runtime reproduction.|Create draft/issued/part-paid/paid/void fixtures; reconcile all report totals, monthly series, breakdowns, CSV and ledger. Keep draft value separately labelled, never issued revenue.|
|44-F02|P2|Dashboard currently supplies six shared control cards, attention items, a fixed14-day lead chart, pipeline and setup checklist. It lacks the unified date/business-line/team control scope and cross-domain executive coverage requested.|Inventory existing filters and query contracts; define actionable metrics before changing layout.|
|44-F03|P2|Approvals is currently quotation-only: pending/recent queries explicitly filter `approvable_type=quote`, and its route requires quote-approval permission.|Map other existing review queues and their exact permissions; do not pretend payment verification or system changes already use this page.|
|44-F04|P2|My Day currently handles owned leads, tasks, visits and an unassigned queue; it is gated through CRM lead access.|Verify non-sales staff roles and propose a personal work queue without granting CRM/financial visibility merely to access their tasks.|
|44-F05|P2|`AdminNavigation` contains Lead Ads with no route and a P7 badge.|Record as disabled/not implemented. Recommend removing it from active navigation or keeping it in an explicitly labelled roadmap, not claiming it works. Real Lead Ads delivery is separate scope.|
|44-F06|Review|The circled Demo data element is a non-interactive badge shown for `APP_ENV=local`; this signals environment, not a database-level proof that every record is demo.|Keep an honest Preview/Demo environment indicator. Do not erase it to make sample records appear production-ready. Confirm intended wording with the design.|
|44-F07|Review|Dashboard action links are rendered without per-button permission checks, while shared cards are permission-gated. Some chart/activity/checklist queries are not individually guarded by the corresponding module permission.|Run restricted-role tests, including permission revocation/stale Livewire state; confirm forbidden actions and any data exposure before classifying a runtime defect.|
|44-F08|Review|Report comparison code/comment promises and calendar-period handling need review for a partial current month versus a full previous month.|Show exact comparison dates and distinguish comparable elapsed periods from previous full calendar periods.|

P1 = potential material financial/security correctness problem; P2 = significant workflow/usability gap. A source finding is not labelled browser-reproduced until there is executable evidence.

## 3. Proposed Overview architecture

Retain the three Overview destinations and existing bookmarked URLs. Do not add another group of competing dashboards.

### A. Dashboard — operating Control Centre

Suggested tabs:

1. **Overview:** executive status, key exceptions and meaningful trends.
2. **My Day:** personal prioritized work, reusing the existing queue and completion actions.
3. **Operations:** grouped CRM, Sales, Accounts, Projects, Inbox, Automation and Marketing summaries with drill-downs to the native modules.
4. **System:** permission-gated operational health, connections, queues, scheduler, backups and security readiness.

Preserve `/admin`, `/admin?tab=my-day`; validate tab/back-button/deep-link behavior. Newly added tabs require explicit definitions rather than silently substituting another component.

**Shared scope:** date preset/custom range, business line (All / Interior / Furniture / Project where applicable), and owner/team only where the actor is permitted. Each card states whether it is a period flow or a current stock. Unsupported filters must be disabled/explained, not silently ignored. Existing records without a business-line attribution must be disclosed rather than reassigned.

**Executive metric candidates, subject to data validation:**

- CRM: new enquiries, first-response SLA breaches, overdue follow-ups, conversion and unassigned leads.
- Sales: sent/accepted quotations, quote value awaiting decision, issued invoice value and verified collections.
- Accounts: current receivables/overdue balance, payables, posted income/expenses and period-close exceptions.
- Projects: active/overdue projects, upcoming commitments and pending handovers; no invented percentage completion.
- Inbox: unread conversations, unassigned conversations and response backlog.
- Automation: latest failed executions, failed jobs, missed expected runs and retry status.
- Marketing: attributable leads/conversions and real publishing failures; spend/ROI only when a verified source exists.
- System: connection health, scheduler heartbeat, job backlog, backup age and access/security alerts.

Show a compact selection of key indicators, not forty cards. The remaining detail belongs in Operations/Reports. Every clickable metric must open the exact filtered source records. Never query or reveal forbidden module totals and merely hide them with CSS.

### B. My Day — work, not another analytics page

- Preserve existing task completion, follow-up/visit context and ownership rules.
- Prioritize Overdue / Today / Upcoming; clearly label limited previews and link to the full queue.
- Include other existing personal assignments only when their current models and permissions support it.
- Team views require an explicit capability; a team manager's queue must not become every employee's default.
- Do not mark a task complete, send a message or change an approval merely by visiting the dashboard.

### C. Approvals — decisions with their original domain safeguards

- Retain quotation approval rules, margin/discount context, reasons, current revision checks and audit history.
- Add search, age/urgency, requester, business-line filters, clear pending/recent states and source-document links.
- Surface other existing review work through a permission-aware aggregator where appropriate. Keep actual decisions in their existing domain handlers.
- A “Review” item is not necessarily a new formal approval type. New invoice/expense/journal approval policies require a separate business-rule decision.
- No generic bulk Approve button that bypasses quote revision checks, payment verification, accounting periods or system change controls.
- Recheck authority and current state on every write; protect duplicate clicks, concurrent decisions and stale forms.

### D. Reports — trusted analysis and reconciliation

Preserve `?tab=funnel` and `?tab=revenue`. Extend only after metric contracts are settled:

- Existing Sales funnel: source/owner/business-line conversion, SLA and aging; clearly distinguish lead cohorts from events in a date period.
- Existing Revenue & collections: sent, accepted, issued and verified collected values; label current outstanding independently of the selected historical period.
- Accounts: link/reuse native trial balance, P&L, payables and income/expenses. Do not equate invoice gross value or a customer deposit with posted accounting revenue.
- Projects: schedules, overdue work and commercial position using actual stored relations; no invented profitability without attributable costs.
- Growth/operations: real marketing attribution, inbox workload and automation outcomes, subject to permissions/source availability.

Screen, charts, CSV/XLSX and drill-downs must share definitions, filters and integer-minor-unit calculations. Show timezone, comparison dates, data freshness and unavailable sources. Targets, forecasts and pipeline-weighted values must be labelled estimates and remain outside actual accounting totals.

## 4. Complete owner-listed audit inventory

**Status:** Pending execution unless an explicit source finding above applies. These rows inventory scope; they are not green QA claims. Each destination includes every discovered child/detail/create/edit screen and tab, not just its landing page.

| ID | Group / page | Path | Key workflow checks |
| --- | --- | --- | --- |
|01|Overview / Dashboard|`/admin`|metrics, filters, chart data, drill-downs, refresh, roles|
|02|Overview / Approvals|`/admin/approvals`|request, approve/reject, reason, stale revision, history|
|03|Overview / Reports|`/admin/reports`|funnel/revenue tabs, dates, comparisons, exports, reconciliation|
|04|CRM / Leads|`/admin/leads`|create/import, assignment, stages, SLA, drawer, duplicates, archive|
|05|CRM / Contacts|`/admin/contacts`|create/update, linked records, search, duplicate/merge safeguards|
|06|CRM / Tasks & Visits|`/admin/tasks`|tabs, assignees, dates, completion, reschedule, access|
|07|Sales / Estimations|`/admin/estimations`|estimates/rates/enquiries, formulas, versions, conversion|
|08|Sales / Quotations|`/admin/quotes`|both brands, calculator, templates, approvals, revisions, PDF, share|
|09|Sales / Invoices|`/admin/invoices`|draft/issue/void, source conversion, frozen PDF, journal effects|
|10|Sales / Payments|`/admin/payments`|record/verify, allocation, partials, receipts, receivables|
|11|Sales / Documents|`/admin/sales/documents`|all tabs, brand settings, blocks, catalog/rates, persistence|
|12|Accounts / Chart of Accounts|`/admin/accounts/chart`|classification, codes, protected/in-use accounts|
|13|Accounts / Journals|`/admin/accounts/journals`|balanced postings, dates, sources, reversal, duplicates|
|14|Accounts / Trial balance|`/admin/accounts/trial-balance`|debits=credits, dates, drill-down, export|
|15|Accounts / Profit & loss|`/admin/accounts/profit-loss`|posted income/expense classification, date scope, ledger tie-out|
|16|Accounts / Payables|`/admin/accounts/payables`|vendor balances, settlement, reversal, aging|
|17|Accounts / Income & expenses|`/admin/accounts/expenses`|create/post, categorization, attachments, period controls|
|18|Accounts / Periods|`/admin/accounts/periods`|open/close, backdating, authorized reopen, audit trail|
|19|Accounts / Payment Methods|`/admin/accounts/methods`|active methods, mappings, private bank data access|
|20|Projects / Projects|`/admin/projects`|create, stages, ownership, linked sales, archive|
|21|Projects / Board|`/admin/projects/board`|drag/drop persistence, invalid transitions, role restrictions|
|22|Projects / Client portal|`/admin/projects/portal`|signed access, privacy, revocation, client downloads|
|23|Projects / Handovers|`/admin/projects/handovers`|checklists, incomplete work, signoff, attachments|
|24|Inbox / Conversations|`/admin/inbox`|receive/reply, assignment, read state, attachments, channel errors|
|25|Inbox / Chat Bot|`/admin/chat-bot`|settings redirect, persistence, responses, unavailable-provider states|
|26|Automation / Automations|`/admin/automation`|enabled/paused, triggers, real run results, failures|
|27|Automation / Sequences|`/admin/automation/sequences`|enrolment, due steps, stop/unsubscribe, retry/idempotency|
|28|Automation / WhatsApp Templates|`/admin/integrations/whatsapp-templates`|provider status, variables, locale, approved-template rules|
|29|Marketing / Marketing|`/admin/marketing`|planner/attribution tabs, drafts, scheduling, real delivery/error|
|30|Marketing / Lead Ads|No route: P7 placeholder|source-confirmed disabled; not a working integration|
|31|Pages & builder / All pages|`/admin/website/pages`|file parity, create/open, safe paths, public page result|
|32|Pages & builder / Page builder|`/admin/website/builder`|full-document navigation, edits/save, assets, actual v1 output|
|33|Pages & builder / Find & replace|`/admin/website/find-replace`|preview, bounded apply, escaping, recovery, file effects|
|34|Pages & builder / Redirects|`/admin/website/redirects`|loops, conflicts, target validity, export, preserved URLs|
|35|Content / Blog|`/admin/website/blog`|create/edit/publish, actual files, slug, metadata|
|36|Content / Portfolio|`/admin/website/portfolio`|items/images, publish, actual file/card output|
|37|Content / Forms|`/admin/website/forms`|submit/validate/spam, leads, duplicates, notifications|
|38|Content / Media|`/admin/website/media`|upload/MIME/size, authorization, paths, usage, deletion|
|39|Content / SEO|`/admin/website/seo-overview`|metadata, canonical, sitemap, actual rendered page checks|
|40|Content / SEO agent|`/admin/website/seo`|proposals, approval/apply, safety, provider failures|
|41|Content / Speed|`/admin/website/speed`|real measurement, cache/freshness, missing API-key state|
|42|Content / Site health|`/admin/website/health`|real links/assets/errors, scan limits, no false green|
|43|Content / Theme|`/admin/website/theme`|tokens, save/reload, responsive output, v1 preservation|
|44|Content / Website Settings|`/admin/website/settings`|validation, roles, SEO/contact output, secret masking|
|45|System / Settings|`/admin/settings`|all tabs, validation, persistence, role restrictions|
|46|System / Access & team|`/admin/system/access`|roles, users, 2FA, direct URL/actions, access revocation|
|47|System / Connections|`/admin/system/connections`|real connect/test/remove, OAuth, encrypted secrets, dependencies|
|48|System / Platform|`/admin/system/platform`|health/audit/changes/backups/privacy tabs, updates, recovery|

Also explicitly test `/admin?tab=my-day`, `/admin/reports?tab=revenue`, legacy redirects, notifications/deep links, global search results, signed client URLs, attachments, exports and secondary routes discovered during execution.

### Header and shared shell — included from the owner's screenshot

- Sidebar toggle: collapse/expand, mobile overlay, keyboard, focus, persistence and route changes.
- Global search: Ctrl/Cmd+K, results permissions, empty/error state, correct destination.
- Demo/environment marker: accurate wording and no false implication that sample metrics are live business facts.
- Theme switch: light/dark persistence, readable tables/charts/forms, no effect on white printed PDF output.
- Chat bubble: actual website chat, unread/visitor state, drawer open/close, reply permission, no duplicate alerts after navigation.
- Notification bell: unread state, mark read, deep links, authorization, stale targets.
- Assistant: authorized source records, honest unsupported questions/provider failures, no invented facts.
- Profile/security/logout: keyboard, menu positioning, session invalidation and mobile behavior.

The red circles identify audit targets; they are not interpreted as approval to delete those controls.

## 5. QA method and acceptance gates

For each page/action record: route/tab; actor/role; dataset/fixture; action; expected versus observed UI/API/database result; evidence; severity; owner; status; retest. Use **Pass / Fail / Blocked / Not implemented / Not tested**. A200 response alone is not a functional pass.

### Frontend

- Desktop/tablet/390px mobile, light/dark, keyboard/focus, empty/loading/error states and readable real data.
- Every visible link/button/menu/tab; pagination, filters, sort, search, validation and save/reload persistence.
- Livewire navigation versus full-document website editors, console errors, failed assets, duplicate requests and stale forms.
- PDF page ink/content and actual downloads; customer links in a fresh unauthenticated context.

### Backend / PHP / APIs

- Route/component/action authorization, ownership, CSRF, signed URLs, request validation and error contracts.
- Happy paths plus invalid input, duplicate submit, retries, concurrent/stale updates and unauthorized direct calls.
- Transactions, domain events, frozen documents, immutable posted records, audit trails and safe external failure handling.
- Native domain actions remain authoritative; dashboard controls do not bypass them.

### Database / accounting

- Inspect migrations and actual test schema; keys, relations, indexes, enum/status integrity, orphan detection, soft deletes and transaction boundaries.
- Reconcile quote calculations, issued invoices, verified payments, allocations, balances and postings; keep tax/discount/charges/rounding and previous adjustments explicit.
- Trial balance must balance; posted reports tie to journals; void/reversal/closed-period cases cannot silently rewrite history.
- Check query plans, N+1/pagination and scoped aggregation with realistic isolated fixture sizes. Do not add indexes/schema speculatively.
- Current sandbox tests use SQLite/PHP-WASM. MySQL/network-specific behavior needs a suitable native environment; do not claim MySQL runtime coverage from SQLite passes.

### Jobs / integrations

- Scheduler heartbeat and expected task cadence, queue handling, retries, idempotency, paused sequences, webhooks and delivery logs.
- Token saved is not Connected; HTTP acceptance is not confirmed downstream delivery. Distinguish provider identity, permission and actual send/publish.
- Missing owner credentials/approval = Blocked with a concrete reason, never a fake Pass. No real marketing blast, message, payment or production write during QA without approval.

### Completion gates

- Each inventoried action has a recorded outcome; blocked items remain visible in the final report.
- No unresolved critical security/data-loss issue; financial discrepancies explicitly resolved or held from release.
- Focused regressions, full suite, PHPStan, formatting, production build, browser workflow checks and route/asset checks.
- No concurrent Pest processes sharing fake-storage directories. Isolated data only; do not overwrite owner records or test financial writes against production.
- Short slice report: changed, tested, evidence, limitations, remaining issues. Overall QA report includes coverage counts and real outstanding blockers, not an unsupported “100% complete”.

## 6. Recommended delivery order

| Slice | Work | Output / gate |
| --- | --- | --- |
|44-A|Read-only master inventory and reproducible workflow audit of all48 entries, tabs and discovered actions; reproduce initial financial/role findings first|Master coverage matrix, severity backlog, metric dictionary, source-to-database map|
|44-B|Dashboard / My Day / Approvals / Reports Control Centre over existing services|Approved responsive layout, correct metric contracts, meaningful drill-downs, role safety, baseline financial query corrections with tests|
|44-C|CRM → estimates → quotes → approvals → invoices → payments → projects end-to-end|Real transaction/workflow evidence; preserve prior document improvements|
|44-D|Accounting and database integrity, payables/expenses/periods, report reconciliation and scale|Balanced/traceable ledger, protected periods, verified query improvements; schema asks separate|
|44-E|Projects, Inbox, Automation and Marketing runtime repairs|Real state transitions, channel/job evidence, explicit external blockers|
|44-F|Website files/builder/content/SEO/media/security|Saved admin changes verified in actual v1 output; URL parity/export preserved|
|44-G|System/access/connections/platform, backups and release readiness|Restricted-role proofs, genuine provider health, isolated recovery evidence where supported|
|44-H|Final cross-role/browser/regression sweep and handoff|Master QA report, evidence, remaining blockers and release recommendation; deploy only with approval|

High-severity defects take precedence over cosmetic slice order. Existing modules and approved architecture are reused first; consult previously audited v2.1 sources before proposing a duplicate implementation. Fresh parity claims require source re-verification; no private source databases or bank/customer data are imported.

## 7. Approved decision

**Owner response: “Start” — approved audit execution and the first native Control Centre slice (44-A and44-B). No repeat approval requested.**

This allows audit execution and the scoped Overview redevelopment. It does **not** approve a new framework, blanket module rewrite, schema migration, altered accounting policy, third-party production traffic or deployment. Later module findings are reported for prioritized decisions.
