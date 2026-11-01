# B04 started — quotation and milestone safety foundations

**17 October 2026 PKT. Status: first local B04 slice implemented and tested; B04 remains unchecked.** The owner requested an updated/restarted App Connections preview on port 8080 and the next recommended work. This is a local preview refresh, not a Hostinger deployment or business acceptance certificate.

> Subsequent delivery: [2025 leads, core coordinated locking and the Accounts plan](18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md) records the next changes. The remaining-gate list below is historical to this first slice; real MySQL/related-writer and delivery-recovery acceptance still remains open.

## Implemented in this slice

- **SendQuote:** reloads and locks the quotation before status, expiry, completeness and approval-figure checks. Uses fresh relations for the snapshot, reloads the active sender and applies the existing send policy. An old revision cannot send a newer draft. A stale duplicate send is refused with the existing “Revise” instruction, not treated as another revision or another email.
- **AcceptQuote:** locks and reloads the quotation before checking revision/status/expiry and writing acceptance, client conversion, Won lead, project and advance invoice. The contact conversion also locks its row. Duplicate acceptance is refused without another acceptance event, project or advance invoice.
- **Version-bound public acceptance:** the form submits the displayed revision; the controller requires it and the action compares it under lock. An already-open R0 form cannot accept R1 after a resend. Previously open forms without this field must reload. Existing signed page URLs continue showing the latest sent version.
- **Project and advance helpers:** lock the owning quotation, require accepted state and repeat existing-record checks inside the transaction. Archived projects/invoices require review rather than silently recreating or restoring financial history. An advance with a configured acceptance milestone requires its actual project milestone; it no longer creates an unbound invoice when that relationship is absent.
- **Milestone invoices:** lock in quote → project → project-milestone order, reload the active actor and apply the existing project update policy. Repeated calls return the same invoice. Fresh amounts/relations are used; the `issue: true` escape hatch is refused. These invoices remain **manual drafts** (D-88), with no client delivery. Existing automatic **advance** draft/issue settings are unchanged.
- **Number suffixes:** advance and later milestone invoices share `InvoiceNumbering`, called while holding the project lock. It takes the highest matching historical suffix, including soft-deleted invoices, rather than counting rows. Gaps such as an archived `-9` lead to `-10`, not reused/colliding earlier suffixes. Existing invoice-number uniqueness remains in place.
- **Business constraint:** migration `2026_10_17_030000_unique_invoice_project_milestone` adds a nullable unique index on `invoices.project_milestone_id`, including archived records. Multiple unrelated manual invoices with a null milestone remain possible. Duplicate historical milestone invoices stop the migration for Accounts review; the migration never deletes or consolidates records.
- **Document and transaction safety:** failed PDF writes throw and roll back the corresponding database transaction. An already-frozen advance PDF is not regenerated from changed contact/settings data. Missing, partial or hash-mismatched frozen documents require reviewed repair instead of silently replacing the first document. Quote mail and staff quote notifications are queued **after the outermost commit**; the database queue/cron remains the deployment model.

## Evidence

| Check | Result |
|---|---|
| New `QuotationSafetyTest` regressions | **34 passed, 134 assertions**; `/tmp/b04-regressions-final.log` |
| Complete PHP suite after formatting | **656 passed, 4,202 assertions, one skipped**, 178.99 seconds; `/tmp/b04-full-tests-final.log` |
| Larastan level 6 | **No errors**; `/tmp/b04-phpstan-final.log` |
| Pint | **15 PHP files pass** explicit `--test`; `/tmp/b04-pint-final-test.log` |
| Front-end build | Passed; existing large ApexCharts chunk warning remains; `/tmp/b04-build.log` |
| Refreshed authenticated App Connections | HTTP 200; 1440/390/360px without horizontal overflow; zero page JavaScript errors |
| Cross-site iframe acceptance | **Both third-party-cookie-blocked and no-cookie scenarios passed**; sign-in/navigation, hub search, MCP setup and Google dialog; `/tmp/b04-iframe-check.log` |

Regression cases cover stale models, changed approval figures and relations, inactive/revoked actors, duplicate effects, old public acceptance forms, archived reservations, suffix gaps, the database uniqueness boundary, migration refusal on duplicates, failed PDF storage and a failed version insertion preserving the previous frozen PDF. Real QueueManager/database-connector tests exercise both outer commit and outer rollback, not just QueueFake flags. The existing advance-tax test now sets tax before the sent snapshot and accepts the quotation before requesting its advance; it no longer invoices an unaccepted, subsequently altered sent quotation.

Tests use isolated in-memory SQLite and fake providers/storage except the explicit local database-queue tests. The one existing sandbox installer/driver skip remains. These are **not MySQL concurrency, real provider connectivity, cron delivery or production acceptance proofs**. The broad homepage/browser suite was not certified green; its previously documented unrelated expectations are not addressed by this slice.

## Port 8080 refresh and preservation

- Synced current application code and rebuilt assets while retaining the runtime `.env`, database, uploads/storage and dependencies. No credential edits, data reset, fresh migration or demo reseed.
- Made SQLite backups outside Git before the refresh and again before the final migration/cache check.
- Applied the pending WhatsApp recovery migration during the first stopped-server refresh. At the final refresh, `migrate --force` reported **nothing pending**; the B04 unique index was already present and was verified directly. Do not infer from that final no-op that this session deployed anything to Hostinger.
- Compared all **66 existing business/settings/session tables** immediately before and after the final migration/cache refresh: every table's rows were unchanged (migration bookkeeping excluded). Aggregate business counts also matched the initial backup. No database backup was restored over the owner's ongoing preview work.
- Refreshed configuration and precompiled views, then restarted the listener on `0.0.0.0:8080`. The final preview serves the updated App Connections and assets, including the earlier B08 work.
- UI setup-dialog checks did not connect providers, register a webhook, generate an MCP key, accept a quotation or issue an invoice.

## Explicit remaining B04/B21 gates — next work

1. **All competing writers must share the lock protocol.** Quote editor/recalculation, request/decide approval, reopen/revise, view/change-request transitions and related lead/milestone writers still require a coordinated fresh-state/lock-order audit. A lock in SendQuote/AcceptQuote alone does not prove race safety against a writer that validates before waiting on its update. In particular, approval decisions must verify the original requested figures under lock, not approve a changed proposal implicitly.
2. Expand approval binding beyond the existing financial fingerprint to the exact intended revision, recipient and relevant content; test changed recipient/content and delayed/stale approval decisions. Do not present the existing fingerprint as a complete authorization token.
3. Run simultaneous MySQL/MariaDB acceptance/send/advance/later-milestone/number allocation, approval/edit and archival races, including deadlock handling. SQLite cannot prove the row-lock guarantees. The current tests establish stale-state behavior, rollback and uniqueness, not multi-worker liveness.
4. A successful filesystem write followed by database rollback can leave an **unreferenced PDF**. Committed frozen PDFs are protected in the tested paths, but a reviewed orphan-cleanup/recovery strategy is still needed. Do not delete files merely to make a retry pass.
5. After-commit queue admission is **not a durable outbox**. A crash/queue outage after business commit can leave delivery absent or uncertain. Inspect committed records and delivery jobs; never resend the business operation to recreate an invoice/project. Outbox/delivery reconciliation belongs to B30 and related finance work.
6. Complete Accounts/owner UAT, broader own/team permissions and B14/B25 issue/send/bank controls. The legacy project update policy is deliberately reused, not represented as a newly completed field/record permission matrix.

## Later production rollout

Publishing requires a **new coding session**; this session's PR is closed/merged. Preserve all earlier local Google/MCP/Telegram/A0 work. Take independent DB/files backups, rehearse restore, stop competing writes for migration, inspect historical milestone duplicates and run forward migrations without deleting financial history. Verify the unique index, cron/database queue, frozen documents and role denials on the actual hosting environment. Refresh/reload older acceptance pages. Keep Telegram financial/approval writes disabled, owner-only optional 2FA and existing confirmed challenges unchanged. No Hostinger push, deployment, provider registration or production migration was performed here.
