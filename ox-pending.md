# ox-pending — Woodex OS master work queue

**Updated:** 3 November 2026 (Asia/Karachi). **This is the current master list**, including earlier holds and new requests. Detailed machine-readable gates remain in [the completion register](docs/audit/98_COMPLETION_REGISTER_2026-11-02.csv); [revision 102](docs/audit/102_PREVIEW_LOGIN_AND_WORKBOOK_REVIEW_2026-11-03.md) records workbook/owner decisions; revisions103/104 below record implemented brand/login/SEO/diagnostic slices and their separate evidence. Earlier reports are historical evidence, not current release acceptance.

## Active revision108 — preview-origin repair and single-year demo

[Implementation and evidence](docs/audit/108_PREVIEW_NAVIGATION_AND_SINGLE_YEAR_DEMO_2026-11-03.md).

- Owner confirmed March/April/May are copies of the **same16 test enquiries**, and approved simpler one-year test data. No more48-distinct-opportunity assumption. Actual workbooks remain unchanged; financial reference-only/no historical import/dry-run hold remains.
- **Implemented:** private-launcher relative-origin navigation/redirect/Livewire effects, signed snapshots preserved; XHR demo context for uploads without weakening CSRF. Controlled alternate-origin browser15checks PASS. The actual screenshot is E2B infrastructure403, not Fortify; actual owner acceptance after Arena preview refresh is still required. Never request or embed traffic-access tokens.
- **Working demo:**16 fictional2026 leads imported through real browser mapping/date review/dry-run/confirmation. Fresh re-upload is recognized as already imported. Owner-only single-year CSV export implemented, UTC New-Year boundary/formula safety/archive exclusion covered. Original pages retained; no financial records created.
- **Drive:** new private text-safe one-year demo Sheet created and exported back; all17 records including headings match app export field-for-field. Drive CSV re-import also recognizes the same16 rows. Optional private preview link is labelled manual snapshot; no automatic/two-way sync or original-workbook overwrite. App OAuth connection is separate from Arena's connected Drive; live scheduled writes still need destination/scopes/conflict policy and owner approval.
- Local focused native15tests/138assertions,92source+62UI/context contracts, Pint7 and preview Vite PASS. Full local PHPStan blocked by the multicall-runtime re-exec; not a pass. Exact-source hosted results are tracked in PR8.36 parent gates retained; deployment FINAL/auto-deploy OFF; no production write, merge, deployment or repo-privacy change.

## Previous revision107 — leads/contacts review, not import

[Review, proposed column mapping and implementation](docs/audit/107_LEADS_CONTACTS_SOURCE_REVIEW_2026-11-03.md).

- Fresh read-only lead-workbook review confirms **22 dated2025 /48 dated2026 /7 undated monthly candidates**, plus42 Target-client and26 client-database reference candidates. Team/vendor/expense tabs and the financial workbook are excluded. No source rows applied; accounts/payments remain reference-only.
- The48 monthly2026 occurrences form16 repeated content groups across March/April/May;13 groups have usable matching primary phones.33 cross-tab phone groups need identity review. These are flags, not proof of distinct opportunities or authority to merge/delete. Keep7 undated rows held; contact-only/reference dates need separate semantics.
- **Implemented:** owner-only source-date inventory with strict calendar/year/future counts and no importer/database write; stale counts cleared on mapping/year changes. Dry-run results retain original CSV record positions. Per-run canonical cleanup no longer sweeps another review's temporary files.
- **106 hosted CI failed one test**, despite its earlier local SQLite pass: absent private preview path short-circuited the expected mock call.107 resolves the actual path first without relaxing the launcher guard. Fresh hosted MySQL run41575009030 at application-source `e05a50c` **PASS:2033 tests/11282 assertions**. All other106 native groups had passed per check annotations.
- Local107: native date-helper17checks and foreign-root guard checks PASS; syntax/Pint5/source contracts92/UI helpers61 PASS. Added3 unit+2 feature cases. Fresh hosted native application tests pass; actual owner/browser and local static/build acceptance remain separate because the disposable106 runtime/dependencies did not persist. Do not reuse prior local success as new-source certification.
- **Next owner decisions:** are March/April/May copies of the same16 enquiries or separate opportunities; which tab is authoritative? Keep Target/client-database lists reference-only or later approve a contact-only workflow? No inferred apply, ledger import, production write, deployment, provider send or repository-privacy conversion. All36 parent gates retained; deployment FINAL/auto-deploy OFF.

## Previous revision 106 — direct synthetic admin and repaired workflows

[Implementation, proof and remaining gaps](docs/audit/106_DIRECT_SYNTHETIC_ADMIN_AND_REPAIRS_2026-11-03.md).

- **Actual105 sign-in acceptance failed.** Transparent welcome now pauses sign-in and offers the isolated, explicitly labelled direct synthetic admin. Default-OFF private launcher only; production login/2FA/CSRF unchanged. Owner acceptance of the real Arena browser remains open.
- **Accounts reduced to three front doors**, six ledger tools inside tabs, all original routes retained. No tables/history removed.
- **Reproduced iframe form blockage**: vendor Save and public Contact Send enquiry each previously sent0POSTs. Explicit actions now send real POSTs. Synthetic enquiry→lead/contact/in-app notification verified; vendor invalid/edit/Enter and linked project-cost save verified. Production enquiry/delivery not certified.
- **Search repaired:** authorised screens including Redirects/retained accounting tools, existing scoped CRM/quote results plus bounded project/vendor/invoice/payment-reference results. Mobile modal/close tested; not an all-database recovery/search promise.
- **Lead import duplicate-heading review implemented**, position/collision-safe labels, manual mapping+confirmation, source revalidation, original dates. Google Sheets CSV guidance only, not live sync. **No historical import applied.** Earlier financial reference-only hold remains until explicitly resolved with mapping/backup/reconciliation.
- **Missing sequence defaults can be restored by the owner, paused**, without overwriting edits or enrolling/sending. No automatic production seeding. Assistant gains enquiry diagnostics (original9+4); webhook status distinguishes configuration/delivery, endpoint creation uses explicit click.
- **Chat source upgraded:** bounded60→600 windows plus keyset older/newer/latest on both native surfaces. Mandatory650-message browser/draft checks pass; broader lifecycle/channel/whole-design gates remain partial.
- **Acceptance:** full native **2,028 tests /11,277 assertions PASS239.58s**, full application PHPStan0, Pint/build clean,61 Node checks,16 cookie-stripped browser checks plus constrained-frame save/contact/Accounts/mobile checks. Private synthetic runtime only; **production unchanged, not all backlog complete**.
- Owner's archive is source-only, not a SQL/storage backup. Missing2025 production records were not recovered/imported. Live provider/Telegram/two-way-channel work remains gated. Repository privacy change requested **after completion** is queued, not performed. Deployment **FINAL** / auto-deploy **OFF** / all36 parent gates retained.

## Previous revision 105 — preview sign-in quick fix; actual acceptance failed


[Implementation and boundaries](docs/audit/105_PROXY_SAFE_SIGN_IN_QUICK_FIX_2026-11-03.md). Owner screenshots disproved actual-proxy acceptance of revision104; it is not just a stale-design report.

- Relative same-origin login POST + Fortify JSON response with safe relative intended navigation; no following backend HTTP/internal-host redirects. Endpoint validation before sending credentials. Native responses, CSRF, active/password checks, throttling and original 2FA retained.
- Explicit default Glass appearance, persisted Solid contrast alternative; no silent opaque preference fallback for the requested glass mode.
- **1,988 tests /11,115 assertions PASS241.59s**, scoped PHPStan0/Pint3/build;57 source/helper contracts;**30 browser checks PASS/exit0**, including controlled HTTPS→HTTP/internal-host proxy sign-in/session reload and refusing foreign form endpoints without a POST. **Actual Arena browser retry still required.**
- Parent104 hosted MySQL41526580232 is now API-confirmed SUCCESS; not105/local-engine acceptance.
- **Production remains unchanged.** No hosting/runtime access or release certified; no blanket deployment of this pending branch, merge, migration, env replacement or data change. Deployment FINAL/auto-deployOFF/private backup+verified restore/staging gates remain. All36 master gates retained.

## Previous revision 104 — single transparent card and implemented SEO/Assistant extensions

[Report and exact boundaries](docs/audit/104_SINGLE_GLASS_CARD_SEO_AND_MYSQL_CHECKPOINT_2026-11-03.md) · [Single-card desktop](docs/audit/evidence/104/login-desktop.png) · [Mobile](docs/audit/evidence/104/login-mobile.png).

- **MySQL checked first; local engine still BLOCKED, not fixed:** reviewed the requested GitHub topic and genuine8.4.6 release metadata. Fresh download redirects to an unreachable `release-assets.githubusercontent.com`; SSL failure/0 bytes. No emulator, obsolete5.x binary or production connection substituted. A trusted supported Linux archive/access is needed before local installation/version/driver/concurrency checks.
- **Hosted MySQL, separate evidence:** parent source `c793425` run41523310965 is **SUCCESS**. That is not a live local MySQL preview or acceptance for the new104 source. Full hosted logs could not be downloaded from the results host; no guessed assertion count.
- **Implemented/tested now:** one centred **28%-opaque charcoal glass** welcome card, white text, photo background, flat demo credential section (no second opaque card), actual reduced-transparency fallback and preview-site link. Existing vector identity/auth/prefill isolation preserved.
- **SEO implemented extension:** category/count/search/review filters, atomic clear/empty state, direct audit/category links, permission revocation check and selected-audit-only execution. No public-page edit or inferred publish/redirect/noindex removal.
- **Assistant implemented extension:** third permission-safe diagnostic for website metadata counts/recommendations with direct category citations; **12 deterministic questions = original nine + three diagnostics**. No external AI/provider request or executable fix.
- **Current local acceptance:** **1,969 tests /11,070 assertions PASS224.87s**; focused28/151; PHPStan0/Pint6/build;92 release +55 UI/auth source checks; **28 browser checks PASS with final exit0**. This implements concrete queue slices—not all36 gates.

## Previous revision 103 — vector brand, glass login and fresh test workspace

[Implementation, acceptance and limitations](docs/audit/103_VECTOR_BRAND_GLASS_LOGIN_AND_DIAGNOSTICS_2026-11-03.md) · [Desktop](docs/audit/evidence/103/login-desktop.png) · [Mobile](docs/audit/evidence/103/login-mobile.png).

- **Implemented and locally tested:** approved filled W + gold-dot vector across shared Woodex OS/Interior UI, all 142 public pages, builder/404/Studio and favicons. Historical/frozen PDFs, saved snapshots, custom logos and production records unchanged.
- **Implemented and locally tested:** photo-background glass login; visible/showable pre-filled **synthetic** credentials behind default-OFF local/testing/isolated-SQLite gates. Still normal CSRF/Fortify/session sign-in, never automatic login or a password bypass. Explicit click/Enter fixes the locally reproduced sandbox-without-forms failure.
- **Provisioned:** fresh private synthetic SQLite DB, all104 existing migrations/reference seeds, three role-specific staff accounts; mail `array`, database queue **without worker**. Prior preview DB/private environment copy retained. No financial/customer import. Not a production/chat DB or local MySQL substitute.
- **Implemented first slices, not the whole backlog:** separate read-only canonical/social/JSON-LD SEO audit, two permission-gated Assistant diagnostics alongside the existing nine queries, client-side sidebar filter and charcoal stat refinements. No executable fix, provider request, publish or indexing-rule change.
- **Local acceptance:** full serial native **1,964 tests /11,035 assertions PASS (225.41s)**; PHPStan0; Pint12; build;92 release +55 UI/auth source checks; **27 real-browser checks PASS**. Its hosted MySQL run41523310965 later succeeded atc793425; actual owner Arena/proxy confirmation remains separate; do not reuse102 evidence as103 certification. Browser-specific 2FA/intended/timeout/rapid-duplicate scenarios remain unverified here.

### Production data and deployment

Production data remains read-only; no import, production account reset, provider send, migration, publish, merge or deployment follows from this test-environment work. Deployment stays **FINAL**, auto-deploy **OFF**. Installed identity, consistent private backup, verified restore, isolated staging, concurrency, exact-candidate review and explicit release approval remain gates.

## Current priorities and decisions

1. **Actual preview access first:**105 sign-in was disproved by the owner;106 explicitly holds sign-in and offers isolated direct synthetic admin. Controlled cookie-independent access/CSRF/browser checks pass; owner Arena acceptance remains outstanding. Production authentication/accounts unchanged.
2. **2025 workbook review before import:** all 17 tabs inspected structurally and proposed mapping prepared. Owner chose separate2025/2026 review with unchanged dates; duplicates still flagged. Historical accounts are reference-only: no ledger importer/dry-run. No import approved or performed.
3. **Next actionable product slices:** frontend v1 SEO baseline and selective charcoal sidebar/card/table refinements; compact Website Chat/history/lifecycle; permission-safe Assistant diagnostics/proposed fixes. Preserve existing routes, permissions, seven Website tools and nine cited Assistant queries. Revision103 implements and locally accepts the first SEO/diagnostic/sidebar slices; compact Chat and remaining content/whole-action acceptance are still pending.
4. **Secrets/provider work:** exposed credentials must be revoked/rotated privately; do not paste replacements here. Owner permits approved aggregate summaries and one conditional non-sensitive report to the paired owner chat. Model/budget/field policy, secure rotation, pairing and network access remain gates before any call/send. Telegram remains owner-only.
5. **Deployment FINAL:** finish remaining gates, private installed audit, consistent backup/verified restore and isolated staging first. Auto-deploy OFF. Frozen finances immutable. No blanket approval for production writes, migration, import, publish/redirect or deployment.

**Historical102 validation (not104 acceptance):** full serial native **1,933 tests / 10,872 assertions PASS**; scoped static0, Pint3, frontend build and92 source contracts PASS;24 base browser +6 login-flow +4 local iframe checks PASS. Final local run at9c20de1 passed in263.59s. Hosted MySQL41512958972 **SUCCESS** at exact9c20de1:1,933 tests/10,870 assertions. Earlier2f62965 had a SQLite-only test assumption corrected for MySQL; no app guard was weakened. This is not production/concurrency acceptance.

### Status vocabulary

Implemented ≠ tested ≠ merged ≠ deployed ≠ live-verified. `pending` below does not mean existing modules are absent. Baseline release97 is merged; revisions98–104 are separate candidate work. Project-cost hub/forms/summaries from101 are implemented and must **not** be rebuilt.

## Consolidated register

| ID | Priority | Work | Current implementation | Remaining acceptance / decision |
|---|---|---|---|---|
| B00 | P0 | Already merged release 97 | implemented | Do not reimplement 63 forms/estimator/CTA/theme/native CI cleanup. |
| A01 | P0 | Native isolated full-app preview | native preview now uses fresh103 synthetic DB/reference seeds and three staff roles; earlier private DB retained | Actual Arena proxy/browser still needs user-visible validation; runtime cache is disposable. |
| A02 | P0 | Durable consolidated register | implemented | Keep this register current; historical reports are not current release acceptance. |
| A03 | P0 | Drive archives vs exact master | metadata inventoried | Private safe entry/content comparison; no automatic overwrite or restore. Large backup exceeds 100 MB connector cap. |
| A04 | P0 | Actual installed runtime/schema/ledger identity | unverified | Read-only actual installed SHA/engine/schema and relationship/journal/allocation inventory; Database Update is not a probe. |
| A05 | P0 | Fresh consistent private backup and isolated restore | prepared checklist only | Retain old backups; capture current DB/config/pages/storage together and restore in isolation before production writes. |
| F01 | P0 | Full-range expenses and income aggregates | fixed | Latest 300 detail rows remain bounded; totals and all account groups now cover complete date/direction range. |
| F02 | P0 | Exact decimal project expense entry | fixed | Money::ofRupees replaces binary float; posting/reversal/frozen documents unchanged. |
| F03 | P0 | Posting/reversal/idempotence/concurrency acceptance | existing actions hardened; workflow acceptance partial | Do not reimplement existing actions; verify source uniqueness/atomic allocation/reversal/closed periods and concurrent attempts on exact candidate. |
| F04 | P0 | Cross-model duplicate booking and historical integrity | shared new-write guard and linked UI; legacy source history separate with unknown settlement warning | New writes use shared vendor lock and source identity. Legacy ProjectExpense has no inferred vendor/payment link; preserve and distinguish unreconciled costs. Real MySQL concurrent writer checks pending. |
| V01 | P1 | Supplier bill vs paid expense workflow decision | linked domain foundation and project-cost hub/form/tables/summaries implemented | Whole-document project links; canonical bill/payments OR paid expense; supplier AP/customer AR/tax/legacy context separate. No historical mapping or mixed-project line allocation. Real MySQL concurrent writers, reviewed candidate and release gates remain. |
| V02 | P1 | Vendors and Costs beside Projects | implemented in101: Vendors & costs hub; project-local linked entry; Bills/Expenses/Vendors tabs | Do not rebuild this workspace. Remaining gates: MySQL concurrent writers/upgrade; installed identity; staged release and live acceptance. |
| V03 | P1 | Project financial and delivery summary | linked supplier costs/tax/AP and customer invoice/receipt/AR summary implemented101; delivery/budget acceptance remains | Preserve101 summaries. Verify budget/tasks/dates/handover end-to-end; do not claim mixed-project allocation or historical reconciliation. |
| T01 | P0 | Telegram owner-only authority | implemented in revision 99, not merged | Owner-only source/isolated acceptance completed; new exact-candidate hosted CI and later deployment/live verification remain separate. |
| T02 | P1 | Telegram lifecycle and truthful delivery | source lifecycle guards implemented; live acceptance unverified | Owner authorized one non-sensitive test report only to the paired owner chat, conditional on private rotated token, fresh owner pairing and network-capable runtime. No customer/financial data; API result and owner receipt separate. Not sent. |
| C01 | P1 | Dedicated Website Chat compact workspace | bounded shared history/windows implemented106; broader presentation acceptance partial | Reuse existing production application DB; compact auto-opening area with paginated full history, not a second chat database. |
| C02 | P1 | Chat draft/isolation/lifecycle/mobile QA | native history/draft tests and mandatory650-message browser checks on both surfaces PASS106; remainder partial | Verify drafts, reopen, archive, paging beyond 20, visitor isolation, permission changes and mobile interaction. |
| C03 | P1 | Channel action and receipt truthfulness | partial | Website reply, Open WhatsApp and official API send must be distinct; do not display fake delivered labels. |
| W01 | P1 | Charcoal shared UI and seven Website tools | existing native UI; selective requested gaps | Preserve heading/seven tools/SEO tabs; selective neutral-charcoal reuse, no wholesale old JS replacement. |
| W02 | P1 | Media/theme/speed/accessibility acceptance | existing pages | Verify saves/live previews, real media behavior, performance measurements, keyboard/mobile and cache-state truthfulness. |
| W03 | P1 | Frontend v1 SEO then missing pages | v1 preserved; read-only metadata audit103 plus filters/direct links/revocation/performance guards104 | Preserve 142-URL contract and Blog/Portfolio/file-writing; no inferred publish/redirect/removal. |
| I01 | P1 | Real integration actions and signed webhooks | existing partial | Real Connect/Details/Test/Remove, Facebook Page and signed lead-created/quote-sent/invoice-paid contracts; no fake cards. |
| S01 | P1 | Permission-safe Assistant diagnostics | nine original queries plus four permission-gated runtime/connection/website/enquiry diagnostics implemented/tested106 | Owner approved diagnostics + proposed fixes; writes require explicit owner approval. Preserve nine scoped cited queries; test redaction/visibility and distinguish configured from reachable. |
| S02 | P2 | Provider-based Assistant integration | provider integration planning reopened; no live provider configured or tested | Owner permits approved permission-checked aggregate totals, not raw customer/bank/ledger rows. Rotated secrets, model, hard spend caps, retention and explicit field policy still required before calls. Writes remain owner-approved. |
| Q01 | P0 | Versioned migration necessity and upgrade tests | increment100 additive link/identity migration implemented | Require schema justification, fresh/upgrade/idempotence/recovery tests; never arbitrary production Database Update. |
| Q02 | P0 | Exact candidate full native CI and review | active ref allowed;106 local2028/11277 PASS and PHPStan0; earlier hosted MySQL evidence separate | Revalidate exact106 candidate SHA; parent CI is not new-source certification. Hosted CI is testing only, not MySQL concurrency/production/deployment approval. |
| Q03 | P1 | Whole-action browser and frozen PDF regression | partial browser acceptance | Real save/edit/navigation/mobile and both brands/PDF rendering/frozen hashes; HTTP success alone is insufficient. |
| R01 | P0 | Isolated staging release rehearsal | not established | Separate runtime/DB/storage/provider isolation plus actual upgrade/restore/rollback and representative workflow acceptance. |
| R02 | P0 | Separately approved manual production release | held | Exact reviewed tested source; preserve hosting ref/path; explicit release approval and post-release identity/smoke. Auto-deploy stays OFF. |
| D01 | P2 | Sheets append and Drive/GitHub backup integrations | deferred; workbook review now tracked separately asD02/D03 | Sheets import/export request reopened for review106; per-sheet CSV guidance implemented, live sync/append and Drive/GitHub backup still not implemented. No automatic import inferred. |
| A06 | P0 | Preview sign-in identity and actual browser diagnosis | actual105 sign-in failed;106 paused welcome/direct synthetic launcher; normal production authentication unchanged;16 controlled cookie-stripped checks | Use synthetic account, never production password. No production bypass/account reset. Direct access only in isolated synthetic launcher. Confirm user can open dashboard in Arena. |
| D02 | P1 | Two workbooks all-tab review and proposed mappings | 17-tab review and proposed mappings complete; monthly leads separated privately into2025/2026/undated review buckets | Owner chose separate years with original dates preserved; duplicate identity/mapping review remains. No import. Monthly buckets22 confirmed2025/48 dated2026/7 undated; reference tabs need their own date semantics. |
| D03 | P1 | Historical accounts reference-only hold | owner chose reference-only; no ledger import or import dry-run | Do not start an accounting importer. Keep available historical GL/supporting data as private reference; any future import requires a new explicit decision and reconciliation/backup/staging gates. |
| W04 | P1 | Selective Preline theme/sidebar/card/icon refinements | sidebar filter/neutral stats103; single smoke-glass card and metadata review controls104; selective work only | Existing Preline5/Tailwind4/Alpine/Livewire4; charcoal semantic tokens; seven Website tools preserved. Use current/free source; Pro source requires license. No MCP credential configured; reduced-motion/focus/mobile/dark-mode tests. |
| B01 | P1 | Approved filled vector identity for OS and Interior | source/UI/public reference updates and vector/colour checks PASS103 | Actual owner visual review and final release held; frozen/custom historical documents deliberately preserved. |
| A07 | P0 | Fresh isolated test DB and staff | provisioned103: SQLite,104 existing migrations/reference seeds, three active roles, non-sending mail/no worker | Not supported local MySQL, production restore or staging release acceptance; actual Arena owner confirmation remains. |

**36 tracked gates.** Counts are not a completion percentage. Merge/deployment/live-verification columns and test evidence are preserved in the CSV. The canonical queue is this file plus that register; do not fork another competing pending list.
