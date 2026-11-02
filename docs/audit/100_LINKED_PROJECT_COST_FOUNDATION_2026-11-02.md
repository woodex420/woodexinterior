# Increment 100 — linked supplier/project cost foundation

2 November 2026 · active branch `arena/7093000c-woodexinterior` · builds on `6c049895a00483ca755f8c577519c96cb58420c1` · draft PR #8

**Partial workflow implementation, not release completion. Deployment is the final task; auto-deployment stays OFF.** No production access/writes, production migrations, provider sends, merge or deployment.

## Implemented

- `RecordProjectCost` records a purchase as exactly one canonical source: a received `Bill` (unpaid, Dr cost/asset / Cr payable), or an outgoing paid `Expense` (Dr expense / Cr cash/bank/wallet). It does not manufacture a second `ProjectExpense` or a parallel ledger.
- Project and business-line context follows the bill, expense and new bill-payment journals. Existing bill payments now identify their own payment row as the journal source; historical journal rows are not rewritten.
- A stable UUID request identity with payload fingerprint handles cost retries, including attempts to change source kind, amount or project. Successful retries resolve the original source, including a subsequently voided bill. Changed payloads are rejected. Failed transactions leave neither the source nor a reserved request behind.
- Optional UUID payment keys deduplicate full/partial payment retries. A payment clears Accounts payable only; it does not recognize the purchase cost again. Amount, method, reference and explicitly supplied date must match a replay. New payment dialogs hold a server-locked request key. Existing callers may omit the optional argument.
- `SaveBill` centralizes the existing bill screen's draft/received writes. It rechecks draft status under a bill-row lock, preserves a draft's project when the existing screen does not supply that field, validates bill-line account type, and refuses integer overflow. `ReceiveBill` posts an existing draft atomically. A failed or stale post no longer resets another successful posting to draft outside the transaction.
- The general bill/expense paths share a supplier-reference check, taking the same vendor lock and using locking reads for duplicate checks (important with MySQL repeatable-read snapshots). Same vendor plus nonblank reference cannot be entered again as a bill/outgoing expense. A different vendor may use the same reference. References without vendor/reference identity cannot be inferred or retrospectively reconciled.
- Current active financial-management authority is checked in the new writers and the existing expense/bill posting/payment/void actions. Linking a project also requires the existing Project view policy. A previously linked liability may still be settled after its project is soft-deleted; new costs cannot be attached to a deleted project.

## Why one additive migration is needed

`2026_11_02_180000_link_project_cost_sources.php` adds:

1. Nullable, indexed `project_id` foreign keys on `bills` and `expenses`.
2. Nullable unique `bill_payments.idempotency_key`.
3. `project_cost_requests`: UUID request key, project ID, SHA-256 payload fingerprint and nullable unique source pointers to Bill/Expense, plus timestamps. **No amounts or balances** are duplicated here.

Existing rows remain unlinked. Foreign keys restrict physical deletion of linked history. The application writes one source pointer atomically; an incomplete reservation is never committed by the action. No guessed vendor/project matches, financial backfill, historical reposting, quote/invoice/PDF rewrite or import.

## Verification

- **120 account/project tests / 623 assertions** (before the final isolated upgrade-fixture refactor); **41 new cases / 200 assertions** passed again after that refactor, strict native PHP 8.5.12 / Laravel / synthetic SQLite, passed. Includes **41 new cases** for source choice, cost/payment retries, cross-model duplicates, permissions, stale drafts, exact amounts, overflow, project archival, rollback, reversal and historical preservation.
- Upgrade regression applies/removes only this migration against independent in-memory SQLite schema fixtures populated with pre-link bill/item/expense/payment/journal columns, preserving exact fixture rows and checking the new foreign keys. DDL runs on a separate connection so it cannot implicitly commit MySQL RefreshDatabase transactions. This is not an actual installed-schema MySQL upgrade rehearsal. It caught a SQLite index-removal ordering bug; migration rollback order was fixed and the regression passes.
- Disposable synthetic preview migration succeeded; an immediate second ordinary `artisan migrate` returned **Nothing to migrate**. No `--force`, production Database Update or installed-owner database was used.
- Full-app Larastan: **0 errors**; the two subsequently hardened posting/save actions were checked again: **0 errors**. Pint passed on the affected source/tests/migration. Source/release contracts: **92 passed**.
- Real Chromium: **6 new bill-screen checks** (login, exact-decimal create/post, partial payment, final payment, mobile form, no uncaught JS errors), plus rerun **24 application checks + 4 local cross-site iframe checks**. Browser ledger assertions confirm one cost posting, two payment-source journals, complete payment UUIDs and exact settlement of 50,025 minor units. The isolated synthetic database was restored to its pre-browser fixture afterward. This is not actual Arena-frame or MySQL concurrency certification.
- **Final exact-source full native suite: 1,898 passed /10,710 assertions /213.04s, exit0**, strict warnings/skips flags, no test skips or vendor/TestCase changes. All1,190 application/test/resource/build/migration files in scope were verified byte-identical between canonical source and isolated runtime. New exact-candidate hosted CI remains a separate gate; do not inherit acceptance from the preceding commit. Previous hosted run **41500249255** is confirmed **SUCCESS** for **6c04989**, not for this increment.

## Deliberately unfinished / next

1. Reuse existing Vendors/Bills/Expenses in the Projects-adjacent Vendors & costs hub, project selector/filter and project-local form. These new screens are **not implemented in this increment**. Include visible domain/stale-bill validation feedback and field-name mapping. Existing bill edit/payment prefills still use legacy float formatting; replace them with exact Money formatting and large-value UI regressions in that slice.
2. Project summaries must distinguish linked source costs, supplier outstanding, payments and legacy `project_expenses`. Existing legacy project costs already credit AP; they must not be automatically turned into bills, treated as settled, or entered again. No financial reconciliation is inferred from this source work.
3. Add project/customer billed/received/outstanding context without conflating supplier AP with customer AR or recoverable supplier tax with net cost. Whole-document project linking only; mixed-project line allocations are not implemented.
4. Real MySQL concurrent cost/payment writers, migration/upgrade/recovery acceptance, candidate hosted CI and reviewed exact-source PR remain gates. Row locks/replay tests on SQLite are not proof of MySQL concurrency behavior.
5. Continue Chat, Website, Assistant, archive content comparison, actual-install read-only inventory, private consistent backup/verified isolated restore and staging acceptance. Deferred workbook/import, Sheets, Drive/GitHub backup integrations and provider AI stay deferred.

No feature-wide completion, historical reconciliation, live verification, merge or deployment is claimed.
