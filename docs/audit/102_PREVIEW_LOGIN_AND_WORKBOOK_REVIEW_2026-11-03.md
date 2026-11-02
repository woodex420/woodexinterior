# 102 — Preview sign-in, workbook review and next-slice decisions

Date: 3 November 2026, Asia/Karachi. Source changes are not a deployment. The master queue is [`../../ox-pending.md`](../../ox-pending.md).

## Authority and boundaries

Owner confirmed: affected login is the Arena **synthetic preview**; inspect both workbooks and recommend mappings **before importing**; Assistant diagnostics may recommend fixes but writes require owner approval. Deployment remains the final task and automatic deployment stays OFF. Exposed provider/bot credentials were not used, persisted or reproduced; rotation is required before configuration/testing.

No workbook data, customer/bank information, raw extracts, credentials or workbook access URLs belong in this public repository. Downloads, inspection scripts and row-level review evidence are in an ignored, permission-restricted private cache. They were not copied into the preview application database. The cache is disposable, not a durable business backup.

## Sign-in result and change

A real browser successfully authenticated the existing active synthetic owner, reached `/admin`, and retained the session after reload. An intentionally incorrect password returned to `/admin/login`, displayed the existing credentials error and allowed retry. A grey/charcoal button is not evidence that it is disabled. Production credentials are not copied into the synthetic database, so the production email shown in the owner's screenshot is not the account to use here; this is not evidence of a production authentication defect.

Added explicit, opt-in synthetic preview guidance and an optional preview email hint/default. It requires **all** of: `WOODEX_SYNTHETIC_PREVIEW=true`, local/testing environment, SQLite default connection. `WOODEX_PREVIEW_LOGIN_EMAIL` is optional and HTML-escaped. Defaults are OFF. Even an enabled flag cannot label production/staging or a MySQL runtime as the synthetic preview. No password is configured/rendered by this feature; no bypass, registration, account reset, active-user override, throttle relaxation, CSRF/session change or production password alteration.

Only the ignored synthetic runtime enables these options. Password-reset mail there remains non-sending. Tests cover opt-in/off, production/staging exclusion, non-SQLite exclusion, guest protection, rejected password, inactive user, normal successful authentication, preserved input behavior and accessible error rendering. Browser redirect/error/retry/session behavior is tested through actual HTTP, not inferred from a GET 200.

## Workbook inspection coverage — 17 tabs

Drive metadata identified workbook A as a native Sheet named **Lead 2026**, not a uniformly 2025 dataset. It was exported as XLSX so all tabs could be inspected. Workbook B is a stored **IncomevsExpense old data .xlsx**, downloaded as its original bytes. Both were opened read-only with formulas retained, plus a second cached-value view. No formulas/macros/external links were executed. Neither workbook contains Excel external-link relationships. Spreadsheet cached totals were not treated as reconciled balances.

The following are **candidate/structure counts**, not approved import counts or certified transaction totals. All cells were scanned structurally; financial classification and reconciliation against installed records are still pending.

| Workbook | Tab | Review finding / recommended treatment |
|---|---|---|
| A | `Jan ` | 29 contact-bearing rows: 22 lead dates in 2025, 7 undated; 2 missing primary phones and 1 requiring phone review. Preserve originals. |
| A | `Feb` | No contact-bearing monthly rows; template/summary content is not lead data. |
| A | `Mar` | 16 contact-bearing rows dated 2026; two additional contact columns shift the schema; separate right-hand target block contains 6 entries. |
| A | `April` | 16 contact-bearing rows dated 2026. |
| A | `May` | 16 contact-bearing rows dated 2026. |
| A | `Target clients` | 42 candidates, 3 secondary-contact rows, 5 missing primary phones. Prospect list, not automatically won leads/customers. |
| A | `Client Data base DB` | 26 candidates; 30 further serial-only rows are template filler, not customers. 1 missing primary phone. |
| A | `Team ` | Repeated single-column reference/list values (20 distinct nonblank values), not permission to create staff or assign roles. Exclude automatic user creation. |
| A | `VendorSup` | 87 supplier/contact candidates. Private account-detail cells require separate restricted handling; do not put them into notes/public reports. |
| A | `expenses` | 10 dated 2026 detail entries; outside a 2025-only review/import scope. Keep quarantined pending year decision. |
| B | `Dashboard` | Summary formulas and several side-by-side blocks, including cash movements. Reconciliation reference, never a second booking source. |
| B | `GL Jan` | 164 numeric amount-bearing detail candidates; 7 lack native transaction dates. Dated entries span January–March 2025, despite tab name. |
| B | ` GL Fab` | 86 numeric amount-bearing detail candidates; dates span February–March 2025. Preserve the actual date, not the tab month. |
| B | `GL Mar` | 145 numeric amount-bearing detail candidates; 1 lacks native transaction date, 30 lack payer; 1 repeated serial group and 3 identical-detail groups need review, not automatic deletion. |
| B | `COA` | Mixed category/project/reference lists. Propose explicit mappings to existing account/project IDs; never replace the installed chart blindly. |
| B | `Contact ` | 86 supplier/contact candidates; compare against A's supplier list before creating any vendors. |
| B | `Fab Mass` | Expense and settlement side blocks with totals. Supporting detail/reference until overlap with the GL is resolved; do not import both representations as fresh expenses. |

### Important reconciliation findings

- The March, April and May lead tabs share **16 identical date/company/person/primary-phone combinations across all three**. This may be copied monthly work, not 48 separate opportunities. Do not automatically merge or create three leads per contact. Monthly primary phones also repeat in 15 groups (44 rows); shared contact identity alone does not prove duplicate opportunity identity.
- The two supplier directories share **85 identical normalized full directory rows**, with 2 A-only and 1 B-only row. Differences must be reviewed privately; matching names alone is insufficient.
- All three GL tabs have blank/non-numeric/zero debit-credit fields on the counted detail rows. A positive `Amount`, `Advance`, `Remaining`, a person in `Pay By`, or the `Account` label is insufficient to prove paid expense versus unpaid supplier bill, reimbursement, transfer, personal drawing or income. No entry was posted from that inference.
- 4 lead summary formula cells have no cached value. No cached spreadsheet error cells were detected in the inspected export/original. Absence of errors does not establish freshness or completeness.
- These files do not demonstrate complete January–December 2025 coverage. The accounting detail tabs cover January–March; later dashboard blocks are not proof of complete later-month ledgers. Ask for the remaining months or confirmation that these files are the entire available source.

## Proposed mapping — approval/dry-run first

1. **Provenance:** preserve workbook fingerprint, tab, original row coordinate, raw values, normalized values and review reason in private staging. Identity uses source-coordinate/fingerprint, not reused serial numbers. Never write provenance containing sensitive values into public logs.
2. **Contacts/companies:** company, person, designation, location and validated phone/email map to existing CRM entities. Preserve raw phone alongside normalization in staging; country-code assumptions, malformed numbers, missing identity and conflicting contacts require review. Secondary contacts stay separate people, not concatenated phone strings. Existing database matching must happen before upserts.
3. **Leads:** the spreadsheet `Status` column actually holds Furniture/Interior/Project business-line labels. `Action` contains pipeline/follow-up signals; `Quotation=Done` does not mean a won/paid invoice. `Lead Source=Client/New Lead` describes relationship, not a proven acquisition channel. Explicit dictionaries map these fields; do not silently map ambiguous values. `Assassin to` is an assignment hint requiring an active authorized staff-ID match, not permission to provision users. Follow-ups/dates become proposed tasks only after timezone/year/ownership review; no historical reminders are sent.
4. **Suppliers:** reconcile both directories and existing vendors; preserve contacts. Bank/account details require an approved private destination and access policy, not general notes or screenshots.
5. **Accounts/project costs:** explicit account/category, project, counterparty, direction, settlement status, payer account and source-document mappings are required. Use existing canonical bill OR paid-expense/payment actions with single-source linkage. No duplicate ProjectExpense booking, automatic historical mapping, inferred payment, frozen-document edits or opening-balance replacement. Personal/transfer/reimbursement rows are a separate review queue.
6. **Dry-run before any write:** private row-level proposed actions, duplicate/conflict/skip reasons, exact decimal conversions, date/year boundaries, mapped identifiers and reconciled totals. Re-run produces zero unexplained changes. Import authorization is a separate owner decision after this report; staging test, verified backup/restore and no-provider-send safeguards precede any production operation.

## Selective Preline / SEO / Assistant plan

Reviewed public Preline MCP setup, dashboard demo layout and animated-icon references. Local manifests already use **Preline 5, Tailwind 4, Alpine 3 and Livewire 4**. No second framework or plugin bundle is needed. MCP is a read-only catalog service requiring its own bearer authorization; it is not connected here, and an OpenRouter key does not authorize it. The referenced dashboard shell is marked Pro and its exact source is access-gated. Do not claim licensed source integration from catalog descriptions or copy gated assets. Use current/free components unless licensed source is legitimately supplied.

- **UI / W01–W04:** retain existing shell/routes/permissions and shared semantic tokens; neutral charcoal, warm neutral surfaces, clear metric hierarchy, consistent card density/borders/radii and existing outline icons. Adopt one proven pattern at a time (sidebar active/expanded/mobile states; filtered financial table; communication panel; settings/status card). No blue default, dummy counters, fake charts, copied demo organizations or wholesale JavaScript replacement. Keyboard/focus, Livewire reinitialization, mobile 390px and dark-mode checks are acceptance gates. Animated SVG/CSS is optional, small and reduced-motion aware; never looping decoration in dense ledger tables.
- **Seven Website tools stay together:** Media, SEO, SEO agent, Speed, Site health, Theme, Website Settings. Retain their heading, routes and permission distinctions, plus Pages/Builder/Find & replace/Redirects and Blog/Portfolio/Forms behavior.
- **SEO / W03:** audit frontend v1 and existing 142-URL contract first: titles/descriptions/canonical/robots, social metadata, sitemap/indexability, heading structure, internal links, image alternative text and structured-data validity. Establish measurable baseline and preview proposed fixes. Missing pages second; no inferred publish, redirect/removal or unsafe overwriting of CMS/files. Browser rendering and form/PDF regression remain required.
- **Assistant / S01–S02:** preserve all nine permission-scoped, cited deterministic queries. Add bounded read-only diagnostics with explicit observed/configured/untested/failed statuses; no arbitrary URL fetch, shell/SQL, secret/log/APP_KEY dump or self-granted permissions. Connectivity tests must distinguish local configuration from actual authorized provider reachability. Proposed fixes show scope, diff/action, risk and verification; server-side owner approval, expiry, actor revalidation, idempotence and audit trail before a narrowly allowlisted write. Diagnose + approved fixes is not permission for autonomous deployment or ledger rewrites.
- **Provider AI:** rotation, model/provider choice, spend limits, retention/data boundary and failure behavior remain unresolved. Recommended initial boundary is redacted operational diagnostics only, no raw customer/bank/ledger content. Keep keyless deterministic answers usable when the provider is disabled/unreachable. No billable provider call was made.
- **Telegram / T02:** reuse existing owner-only integration and pairing. After a rotated token is configured privately and owner chat pairing/scoped-send approval is verified, authorize one non-sensitive test report. Test token identity, pairing/authorization, queue outcome and actual owner receipt separately; token presence or an API success is not proof the owner received/read it. No test message or receipt claim yet.

## Verification and status

- Prior exact source `4cd99c6`: GitHub native run **41508608055 SUCCESS**, confirmed by `gh run view`; this does not certify subsequent edits or imply deployment.
- New login tests: **10 tests / 42 assertions PASS**, strict warning/skipped flags; Pint 3 files PASS; scoped Larastan **0 errors**.
- New real-browser login diagnosis verifies banner, rejected credentials/redirect/error, retry, successful owner dashboard and reload persistence. Four local cross-site iframe checks also pass again (login, partitioned cookie, navigation, reload). This is not direct certification of the owner's actual Arena browser.
- Final exact-workspace serial native suite: **1,933 tests / 10,872 assertions PASS**, strict warning/skipped flags, exit0, **213.70 seconds**. Frontend build PASS (existing large-chunk advisory remains); **92 source contracts PASS**. Real-browser checks rerun: **24 base application + 6 login-flow + 4 local cross-site iframe checks PASS**. The earlier 9 project-cost workflow checks remain evidence for101; they were not rerun as part of102. No migration/dependency/lock change, real-data import, provider send, production write, merge or deployment.

### Validation harness notes

The first new non-SQLite guard test temporarily changed the default connection without restoring it, affecting RefreshDatabase teardown; the test now restores configuration in `finally`. No application or vendor behavior was changed. HTTP error-bag assertions were separated into rejected-request/session assertions and Laravel's `withViewErrors` rendering assertion: the test session uses JSON serialization, while real-browser HTTP verifies the complete redirect/error/retry flow. Final serial native acceptance is green without overlapping test processes or weakening authentication. Build runs from the canonical platform dependency tree; the private runtime intentionally does not contain a duplicate frontend `node_modules`.

This sandbox restricts outbound hosts. A later Telegram/OpenRouter reachability/receipt test also needs an approved network-capable runtime; rotated secrets or UI configuration alone do not prove connectivity. No live test was attempted with exposed credentials.
