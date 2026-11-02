# ox-pending — Woodex OS master work queue

**Updated:** 3 November 2026 (Asia/Karachi). **This is the current master list**, including earlier holds and new requests. Detailed machine-readable gates remain in [the completion register](docs/audit/98_COMPLETION_REGISTER_2026-11-02.csv); [revision 102](docs/audit/102_PREVIEW_LOGIN_AND_WORKBOOK_REVIEW_2026-11-03.md) records the latest decisions, workbook findings and proposed UI/Assistant work. Earlier reports are historical evidence, not current release acceptance.

## Current priorities and decisions

1. **Login first:** verified synthetic owner sign-in/error/retry/session in a real browser; preview-only identity guidance added. Owner confirmation in the actual Arena browser remains outstanding. Production authentication/accounts unchanged.
2. **2025 workbook review before import:** all 17 tabs inspected structurally and proposed mapping prepared. Mixed 2025/2026 dates, copied lead rows, overlapping supplier lists and uncertain account classifications require decisions. No import approved or performed.
3. **Next actionable product slices:** frontend v1 SEO baseline and selective charcoal sidebar/card/table refinements; compact Website Chat/history/lifecycle; permission-safe Assistant diagnostics/proposed fixes. Preserve existing routes, permissions, seven Website tools and nine cited Assistant queries. Exact implementation and browser acceptance remain pending—not completed by this plan.
4. **Secrets/provider work:** exposed credentials must be revoked/rotated privately; do not paste replacements here. Provider model/budget/data policy and one-message owner Telegram test scope must be resolved before any call/send. Telegram remains owner-only.
5. **Deployment FINAL:** finish remaining gates, private installed audit, consistent backup/verified restore and isolated staging first. Auto-deploy OFF. Frozen finances immutable. No blanket approval for production writes, migration, import, publish/redirect or deployment.

**Current102 validation:** full serial native **1,933 tests / 10,872 assertions PASS**; scoped static0, Pint3, frontend build and92 source contracts PASS;24 base browser +6 login-flow +4 local iframe checks PASS. Prior4cd99c6 hosted CI41508608055 succeeded; newer hosted CI/review remains separate.

### Status vocabulary

Implemented ≠ tested ≠ merged ≠ deployed ≠ live-verified. `pending` below does not mean existing modules are absent. Baseline release97 is merged; revisions98–102 are separate candidate work. Project-cost hub/forms/summaries from101 are implemented and must **not** be rebuilt.

## Consolidated register

| ID | Priority | Work | Current implementation | Remaining acceptance / decision |
|---|---|---|---|---|
| B00 | P0 | Already merged release 97 | implemented | Do not reimplement 63 forms/estimator/CTA/theme/native CI cleanup. |
| A01 | P0 | Native isolated full-app preview | native isolated preview refreshed with increment101 and synthetic project-cost demo | Actual Arena proxy/browser still needs user-visible validation; runtime cache is disposable. |
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
| T02 | P1 | Telegram lifecycle and truthful delivery | source lifecycle guards implemented; live acceptance unverified | Owner requests later bot report. Rotated token, owner pairing/chat, explicit one-message scope and receipt verification required; exposed token never used. |
| C01 | P1 | Dedicated Website Chat compact workspace | existing native chat; presentation gap | Reuse existing production application DB; compact auto-opening area with paginated full history, not a second chat database. |
| C02 | P1 | Chat draft/isolation/lifecycle/mobile QA | existing partial | Verify drafts, reopen, archive, paging beyond 20, visitor isolation, permission changes and mobile interaction. |
| C03 | P1 | Channel action and receipt truthfulness | partial | Website reply, Open WhatsApp and official API send must be distinct; do not display fake delivered labels. |
| W01 | P1 | Charcoal shared UI and seven Website tools | existing native UI; selective requested gaps | Preserve heading/seven tools/SEO tabs; selective neutral-charcoal reuse, no wholesale old JS replacement. |
| W02 | P1 | Media/theme/speed/accessibility acceptance | existing pages | Verify saves/live previews, real media behavior, performance measurements, keyboard/mobile and cache-state truthfulness. |
| W03 | P1 | Frontend v1 SEO then missing pages | existing v1 and forms baseline | Preserve 142-URL contract and Blog/Portfolio/file-writing; no inferred publish/redirect/removal. |
| I01 | P1 | Real integration actions and signed webhooks | existing partial | Real Connect/Details/Test/Remove, Facebook Page and signed lead-created/quote-sent/invoice-paid contracts; no fake cards. |
| S01 | P1 | Permission-safe Assistant diagnostics | existing nine scoped queries | Owner approved diagnostics + proposed fixes; writes require explicit owner approval. Preserve nine scoped cited queries; test redaction/visibility and distinguish configured from reachable. |
| S02 | P2 | Provider-based Assistant integration | provider integration planning reopened; no live provider configured or tested | Exposed credentials must be rotated privately. Model/provider, hard spend limits, retention and data boundary require decision. No provider sends or autonomous writes/deployment. |
| Q01 | P0 | Versioned migration necessity and upgrade tests | increment100 additive link/identity migration implemented | Require schema justification, fresh/upgrade/idempotence/recovery tests; never arbitrary production Database Update. |
| Q02 | P0 | Exact candidate full native CI and review | active ref explicitly allowed | Revalidate subsequent exact SHA after102. Hosted CI is testing only, not MySQL concurrency/production/deployment approval. |
| Q03 | P1 | Whole-action browser and frozen PDF regression | partial browser acceptance | Real save/edit/navigation/mobile and both brands/PDF rendering/frozen hashes; HTTP success alone is insufficient. |
| R01 | P0 | Isolated staging release rehearsal | not established | Separate runtime/DB/storage/provider isolation plus actual upgrade/restore/rollback and representative workflow acceptance. |
| R02 | P0 | Separately approved manual production release | held | Exact reviewed tested source; preserve hosting ref/path; explicit release approval and post-release identity/smoke. Auto-deploy stays OFF. |
| D01 | P2 | Sheets append and Drive/GitHub backup integrations | deferred; workbook review now tracked separately asD02/D03 | Sheets append and Drive/GitHub backup integrations remain deferred. No automatic import or backup integration inferred from workbook review. |
| A06 | P0 | Preview sign-in identity and actual browser diagnosis | opt-in local/testing SQLite guidance and preview email hint; auth remains unchanged | Use synthetic account, never production password. No bypass/account reset. Confirm user can open dashboard in Arena. |
| D02 | P1 | Two workbooks all-tab review and proposed mappings | read-only structural inspection of all17 tabs; private row analysis; mapping recommendations prepared | Resolve 2025 vs2026 scope and completeness. Review mappings privately; candidate rows are not approved records. No dataset or bank/customer fields in public repo. |
| D03 | P1 | Historical data reconciliation and possible import dry-run | held after review; no importer run | Separate owner approval after row-level duplicate reconciliation and account/direction/payment/project mapping; frozen history immutable; staging and verified backup/restore before production writes. |
| W04 | P1 | Selective Preline theme/sidebar/card/icon refinements | catalog/MCP/license and local stack preflight completed; UI implementation pending | Existing Preline5/Tailwind4/Alpine/Livewire4; charcoal semantic tokens; seven Website tools preserved. Use current/free source; Pro source requires license. No MCP credential configured; reduced-motion/focus/mobile/dark-mode tests. |

**34 tracked gates.** Counts are not a completion percentage. Merge/deployment/live-verification columns and test evidence are preserved in the CSV. The canonical queue is this file plus that register; do not fork another competing pending list.
