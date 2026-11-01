# 44 — First native Control Centre increment and master-QA progress

**Date:** 28 October 2026 · **Authorization:** owner “Start”, 44-A/44-B.
**Status:** first implementation increment; master whole-OS / every-action audit **incomplete**. Not deployed or merged. No schema changes or real provider sends.

## 1. Delivered in this increment

- Native Dashboard tabs **Overview / My Day / Operations / System**. Existing `/admin` and `?tab=my-day` URLs remain; System is permission-gated.
- Period-performance block with date presets, business line, exact selected dates, application timezone and refresh time. Financial drill-downs carry the same scope into the native report. The fixed 14-day CRM chart and current operating queues are explicitly separate from period filters.
- Shared revenue-report queries for the Dashboard, report screen, monthly/breakdown totals and CSV; issued invoices exclude Draft/Void. Invoice value is **not** posted accounting revenue or profit.
- Current Operations summaries for permitted CRM, Sales, Accounts, Projects, Inbox, Automation, Marketing and Website modules. Links open existing workspaces; they are not presented as exact filtered record counts when those workspaces cannot express the filter.
- Approvals adds quote search, business-line filtering and pagination plus links to existing payment verification, publishing review and system-change review. Quote decisions still use the original permission checks/domain action; no cross-domain bulk-approval policy was introduced.
- Reports retains Funnel/Revenue and adds **Ledger & operations** sources, with per-source permission checks. This directory is not a new reporting engine.
- My Day now reports complete queue counts separately from limited previews; uncontacted/overdue/today lead groups no longer overlap. Existing ownership and task-completion policy remain authoritative.
- Dashboard avoids forbidden CRM queries/actions for restricted roles. Hub tab authorization is rechecked on rendering/hydration; direct report/System rendering checks permissions. Search shortcuts now respect CRM permissions and offer permitted native alternatives.
- Mobile navigation closes with Escape. Theme/sidebar persistence and the circled header controls were exercised. The Demo badge remains an environment marker, not a claim of live production data.
- Removed the nonfunctional Lead Ads placeholder from active navigation. **Lead Ads itself remains not implemented.**

### Deliberate limits

This is not the full proposed advanced dashboard. Universal non-CRM My Day, owner/team filtering, a custom-date picker, expanded historical/cohort analytics, scheduler cadence repair, every original-module action and production delivery proof are not delivered here. Custom dates are supported through validated URLs, not a new picker. No owner/team control is displayed while silently ignored. New-enquiry counts have no false exact-filter drill-down: the existing lead list cannot represent the arbitrary date range.

## 2. Verified findings and dispositions

| ID | Severity | Finding / evidence | Disposition |
| --- | --- | --- | --- |
|44-F01|P1 financial|The old report admitted Draft invoices with issue dates. A legacy-query reproduction returned **100000 minor units when 0 was expected**.|Fixed with a positive Issued / PartiallyPaid / Paid status set across totals, monthly series and line breakdowns. New all-status fixture expects 300000 minor units in each aggregation.|
|44-F09|P1 financial|A separate same-day fixture initially returned **0 instead of 300000** because SQLite mixed date-only/raw-query and Eloquent datetime storage excluded that date boundary. This was not the draft-inflation reproduction.|Invoice/payment date ranges now use `>= first date` and `< day after last date`; same-day and next-day exclusion fixtures pass. MySQL execution still requires its own environment.|
|44-F07|P1 access|CRM chart/activity queries and action shortcuts were not consistently gated; hydrated hub tabs did not recheck their tab permission.|Gated before querying/rendering. Accountant Dashboard query-log test asserts no leads/activities query; sales cannot hydrate the finance tab or render System/finance directly; editor search has no forbidden CRM shortcuts. This is selected-role proof, not every-role/every-action certification.|
|44-F02|P2 UX|Original Dashboard lacked selected-period/business-line performance and cross-module context.|First increment delivered; advanced analytics and broader personal work remain backlog.|
|44-F03|P2 workflow|Approvals was quotation-only.|Aggregated links to existing permitted reviews; no change to quote decision rules or financial approval policy. Accountant can open its payment review but cannot invoke quote approval.|
|44-F04|P2 workflow|My Day preview limits understated queue counts and lead groups could overlap.|Counts/disjoint grouping fixed. My Day remains `leads.view`-gated; non-CRM personal work is not silently enabled.|
|44-F05|P2 navigation|Lead Ads had no route/action.|Removed placeholder; actual integration not implemented. Navigation regression updated to expect no unimplemented placeholder.|
|44-F06|Review|Demo marker is based on local environment, not record provenance.|Retained and browser-checked. No production-readiness inference.|
|44-F08|P2 interpretation|Partial current calendar period versus previous full calendar period can mislead if hidden behind “previous”.|Current/comparison dates are displayed/exported. Existing calendar comparison semantics retained, not relabelled as elapsed-period-normalized analytics.|
|44-F10|P2 interpretation|A zero automation-failure count could imply healthy/fresh execution even without runs.|Dashboard now says “No failures recorded; check task freshness in System.” Operations discloses stale reports; cadence/runtime repairs remain future work.|
|44-F11|Review, unverified hypothesis|Sent/accepted quotation reporting uses mutable quote values/current status rather than a dedicated frozen event/cohort model.|Not reproduced as a historical discrepancy in this increment. Validate revision/status-transition fixtures before proposing a reporting policy change. Frozen quotation/PDF history was not rewritten.|

P1 findings above describe the original defect and scoped retest, not a claim that no other P1 exists. Unknown/unexamined actions remain visible in the coverage inventory.

## 3. Metric/source contract

| Metric | Definition / scope | Source / drill-down |
| --- | --- | --- |
|New enquiries|Creation datetime inside selected period, optional business line, `visibleTo(actor)`|`PeriodPerformance` → native Lead query. Not clickable because current list lacks that exact range filter.|
|Accepted quotation value|Current Accepted quotations with `accepted_at` in period; optional line. Not recognized accounting revenue.|Shared `RevenueCollectionsReport::totals`; scope-preserving Revenue link. Historical/frozen interpretation remains review item F11.|
|Issued invoice value|Gross invoice total for Issued/PartiallyPaid/Paid, `issue_date` in half-open date interval, optional line.|Same report service for screen, Dashboard, CSV, monthly series and breakdowns. Draft/Void excluded.|
|Verified collections, All lines|Verified payment receipt amounts by `received_on`, including unallocated cash.|Shared collections query. Not inferred from allocation totals.|
|Allocated collections, one line|Verified payment allocations to invoices attributed to that line; receipt date defines period; unallocated cash excluded.|Shared joined query; method counts use distinct payment IDs to avoid multiplying one receipt by its allocations.|
|Outstanding today|Existing issued/part-paid invoice total minus paid total, optionally line-scoped; independent of selected period.|`outstandingMinor`; stock, not selected-period revenue or a reconstructed historical balance. Full ledger/credit reconciliation remains 44-D.|
|Live operating queues|Existing native permissions/ownership and current statuses; not scoped by the period/business-line controls.|Original `ControlPanel`, `OperationsSummary`, owning workspaces and System health collector.|

Exact current/previous dates are in report UI/CSV. App timezone is shown on Dashboard. A refresh timestamp is the time of the query/render, not downstream delivery freshness. Ledger reports remain the source for posted income, expenses, debits/credits and accounting profit.

## 4. Coverage and executable evidence

### Discovery is not functional proof

- **48 planned navigation entries** tracked: **47 working navigation destinations** plus **1 not-implemented Lead Ads entry** removed from active navigation.
- **188 native routes** (134 admin / 54 other routes), **110 Livewire components**, **826 declared public methods** and **105 SQLite tables** inventoried, including column/index/foreign-key metadata. No business-row data exported.
- The method inventory includes lifecycle/helper methods, not only clickable actions; inherited methods, controller/API actions and legacy website PHP require deeper action mapping. The 54 non-admin native routes are inventoried, not automatically tested. These counts are not the complete system action denominator.
- Read-only SQLite structural check: `integrity_check = ok`, **0 foreign-key violations** in the local demo copy. Query-only connection; not financial reconciliation or production/MySQL proof.
- Read-only HTTP smoke: **87 page destinations passed**, including secondary routes/redirects. HTTP 200 is landing/smoke evidence only.
- Chromium: **47/47 discovered sidebar landings passed**, 13 Control Centre/shell checks plus final all-landings/no-JS-error gates. Covered date/line changes, truthful receipt wording, scope-preserving report link, Operations/reviews, approval empty state, report sources, System, theme persistence, desktop sidebar persistence and 390px layout/mobile Escape.
- Isolated copied-demo header browser: **9 checks passed**, including real website-chat open/close, notifications open/Escape, Ctrl+K focus, real search empty state/Escape, environment badge and no JavaScript errors. Chat opening can mark a selected thread read, so this ran against a separate copied SQLite database, not the primary preview DB. No replies, sends or mark-all-read actions were performed.

### Release checks

| Check | Result |
| --- | --- |
|Focused Overview/report/approval/tasks/sidebar/access/System regressions|**134 passed / 455 assertions**, 32.20s; includes all 11 new Control Centre cases. After the final shortcut guard, its dedicated rerun passed **11 tests / 50 assertions**.|
|Final full Pest|**1649 passed / 1 skipped / 8873 assertions**, 364.48s. The skip is the MySQL network test unsupported by PHP-WASM.|
|Full PHPStan|**0 errors** after fixing the initial nullsafe-expression warning.|
|Pint|**18 changed application/test PHP files and 2 audit helpers pass**. Whole-tree check found **2 pre-existing style violations** in unchanged `Projects/ProjectShow.php` and `Overview/ControlViewTest.php`; left untouched rather than mixing unrelated formatting.|
|Production Vite build|Passed. Generated admin CSS/manifest updated; locked v1 `site.css` and `site.js` restored byte-identical to baseline.|
|Embedded preview sessions|**Both scenarios passed / 22 assertions**: third-party cookies blocked and all server cookies stripped. Covers invalid login, double-click single POST, session/navigation/reload, native integration search, MCP setup and Google dialog. No token created or connection submitted. Initial stale selector and input-clearing harness failures corrected; application unchanged.|
|Diff / JS syntax|`git diff --check` and updated browser-driver syntax checks passed.|

### Evidence files

Under [`evidence/44/`](evidence/44/):

- `44-master-navigation-coverage.csv`: each of the 48 entries, landing result and still-required action checks.
- `44-native-route-coverage.csv`: all 188 native route definitions and honest action/landing status.
- `44-database-structure.json`: local read-only SQLite integrity/FK result, not a ledger tie-out.
- `44-component-method-inventory.csv`: 826 method entries, explicitly **not individually attested**, so baseline regression passes cannot silently become per-action certification.
- `44-native-inventory.json`: admin routes/components/schema metadata; guarded read-only scripts in `tools/sandbox/master-qa-inventory.php` and `master-qa-database-check.php`.
- `44-browser-results.json`, `44-header-results.json`: actual checks, destinations and JS errors; no credentials/business records.
- `44-dashboard-desktop.png`, `44-dashboard-mobile.png`, `44-operations-desktop.png`: local demo only, not customer data or production screenshots.
- `44-verification.txt`: final concise command outcomes, limitations and corrected test/harness failures.

Reproduction tooling: `tools/sandbox/control-centre-check.mjs` (local 33143, navigation/filter/shell checks), `control-header-check.mjs` (guarded copied QA database on 33144) and updated `iframe-check.mjs` (`PORT=33144`, cookie-restricted embedding). Browser dependencies/PHP-WASM and copied runtime live outside Git. Do not run the chat/action driver against owner/production data.

## 5. Remaining audit / repair backlog

| Priority | Work | Status / gate |
| --- | --- | --- |
|P1 audit|CRM → estimates → both quotation brands → approval → invoice issue → verified payment/allocation → project; invalid/stale/duplicate/reversal paths|Per-action end-to-end audit not complete. Existing regression coverage is separate evidence; new module redevelopment still needs a scoped decision.|
|P1 audit|Trial balance/journal traceability, payables/expenses, credit/void/reversal, closed periods, realistic query plans and concurrency|SQLite schema discovery and a clean structural/FK check are not financial reconciliation or scale proof. No index/schema change authorized.|
|P1 access audit|All roles, ownership boundaries, revoked/stale permissions, API/signed customer URLs, files, exports and sessions|Selected roles/hub paths tested here; complete matrix remains open. Search also suppresses Operations for an actor without Dashboard permission.|
|P2 workflow|Non-CRM My Day and custom-date picker; explicit owner/team scope where supported|Not delivered in this baseline; never grant CRM just to expose personal tasks.|
|P2 interpretation|Frozen-history/cohort report semantics; unattributed business-line records; allocation/ledger reconciliation|F11 is an unverified concern, not a silently corrected accounting policy.|
|P2 runtime|Inbox send/receive, sequences/jobs/idempotency/retries/cadence, marketing publish/attribution and real integration identity/delivery|Owner credentials/provider access and explicit send approval where needed. No fake Connected/Delivered outcomes.|
|P2 website/System|All v1 builder/file saves, forms/media/SEO/content, redirect export/URL parity; backup/restore/privacy/security every-action checks|Landing sweep passed, but individual mutations/recovery not certified here. Locked site assets preserved.|
|Environment|MySQL/network-dependent behavior and owner-provided `arena.site` hostname|Not independently end-to-end verified. Local browser/runtime proof does not attest that host or production.|
|Owner decision|Default accountant role lacks `accounts.view` (already documented in audit31)|Not silently broadened; existing role policy retained.|
|Not implemented|Lead Ads; blocked Telegram owner-token/linking dependencies|No placeholder success; no production external traffic.|

## 6. Handoff and next work

44-A has a persisted discovery/landing baseline and a visible method/action backlog, **not a completed every-action audit**. 44-B has a tested first native increment, **not all advanced candidates from the master plan**. Continue transactional and ledger audit in risk order; present reproduced domain findings and scoped recommendations before unrelated repairs. No deployment, merge, schema change, external sends or new financial approval policy is authorized by this report.
