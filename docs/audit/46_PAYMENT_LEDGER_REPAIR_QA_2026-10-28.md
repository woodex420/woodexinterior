# 46 — Approved payment/ledger integrity repairs

**Date:** 28 October 2026 · **Approval:** owner “Start” for [45-B §4](45_TRANSACTIONAL_LEDGER_AUDIT_2026-10-28.md#4-recommended-next-repair--45-b-approval-required).
**Scope:** F01–F04 only. Native Laravel/Livewire; no schema, permission, accounting-policy or historical-data correction.

## 1. Delivered

| Finding | Repair | Verification |
| --- | --- | --- |
|45-F01: staff Record payment omitted its journal|`RecordPayment::handle` now records payment, allocations, receipt and ledger in one enclosing transaction, reusing the existing posting services.|Both Interior/Furniture native forms reconcile invoice and GL receivable; allocation/unallocated-credit split; mapping failure and outer rollback regressions.|
|45-F02: staff recording bypassed closed periods|Posting now reaches the existing accounting-period guard. The form displays posting errors and retains input instead of failing with a generic server error.|Closed-period form commits no payment, allocation, receipt or journal; actual Chromium submit also checked against a separate QA database.|
|45-F03: posting accepted pending money|Posting reloads and locks the current payment, requires Verified **before** looking up a source journal, and calculates from fresh data.|Pending, rejected, refunded and stale Verified objects rejected; stale amount refreshed; verified retry returns the same journal even after period closure.|
|45-F04: failed issue left a private PDF|Register rollback deletion before writing a **new** invoice PDF. Existing valid frozen PDFs return unchanged; an unexplained existing target is refused and preserved for reviewed repair.|Issue failure and nested/outer rollback remove new files; frozen byte hashes and pre-existing orphan bytes remain unchanged. Existing acceptance/storage-failure regression retained.|

Existing `payments.record` / `payments.verify` permissions and immediate verification of staff-recorded cash are unchanged. A record-only staff test confirms that ledger access or verification permission is not newly required. No accountant access was broadened. Existing Collect/Verify action flows remain intact and are included in the regression gates.

## 2. Read-only reconciliation

Operator CLI, from the platform application:

```sh
php artisan accounts:reconcile-payments
```

Returns aggregate PKR totals for Verified payments missing a journal whose source is `payment` plus that payment ID. Includes archived Verified rows deliberately. Prints no customer, bank or payment identifiers and performs no posting/backfill. Tests inspect SELECT-only queries and unchanged historical fixture/journal counts.

A matching source journal is **not** proof of correct amount, account, reversal or period. Exit 0 means the query succeeded, not financial sign-off. The local demo run returned zero Verified payments / zero missing journals; this is an empty local-demo check, **not production or historical reconciliation**. Missing rows require separately approved operator review, particularly in closed periods.

## 3. QA gates

| Gate | Result |
| --- | --- |
|New `PaymentPostingRepairTest`|**20 passed /83 assertions /7.15s**.|
|Focused Payments / PaymentIntegrity / GL / transactional audit / repair selection|**75 passed /388 assertions /35.30s**, before the final two dedicated cases were added.|
|QuotationSafety + final repair suite|**54 passed /219 assertions /24.54s**.|
|Full Pest regression|**1675 passed /1 skipped /9027 assertions /329.10s**, exit0.|
|Original revision45 red-contract replay|**5 repaired contracts passed; F05 alone still fails /12 assertions /4.71s**. Separate deliberate probe run, exit1, not a normal-suite result.|
|PHPStan|**0 errors**.|
|Pint / syntax / diff checks|Final evidence recorded in `evidence/46/46-verification.txt`.|
|Actual Chromium payment submit|**5 checks passed**, no page JavaScript errors; separate copied local QA DB, mail array, no production/provider writes.|

The first full run found one storage-test double that did not permit the new `exists` / rollback `delete` calls. Its expectations were updated, retaining the original assertion that failed storage rolls back acceptance, project, invoice and audit writes. That affected group then passed 54/219. The production guard was not removed or weakened to satisfy the mock.

### Browser observations — actual form clicks plus database inspection

- Fresh native-issued QA invoice: invoice/GL receivable **10000 minor units**.
- Record **5000 minor units** cash through Payments: **one payment, one payment journal**, invoice/GL receivable both **5000**.
- Close that period in the guarded QA copy and attempt another payment: clear closed-period error, amount still **50.00**, no second payment/journal or balance change.
- This browser mutation used `/tmp/woodex46/qa/app` on port33144, **not the main demo database on33143**. Both-brand coverage is in native Livewire tests; Chromium mutation covered Interior on desktop, not every device/role/action.

## 4. Evidence and boundaries

- [`evidence/46/46-verification.txt`](evidence/46/46-verification.txt): commands, exact outcomes and isolation notes.
- [Full Pest output](evidence/46/46-full-suite.txt) · [Dedicated test output](evidence/46/46-dedicated-tests.txt) · [Original-contract replay](evidence/46/46-original-contract-replay.txt).
- [`evidence/46/46-browser-results.json`](evidence/46/46-browser-results.json): native DB counts/balances after actual browser actions.
- [Successful payment screenshot](evidence/46/46-payment-recorded.png) · [Closed-period screenshot](evidence/46/46-payment-closed-period.png).
- Normal regressions: `platform/tests/Feature/Accounts/PaymentPostingRepairTest.php`; revised storage mock in `QuotationSafetyTest.php`.
- Reproducible browser driver and guarded QA-only fixture: `tools/sandbox/payment-ledger-{check.mjs,fixture.php}`. Requires the PHP/browser sandbox tooling and separately copied database; never use owner/production data.

**Still excluded/open:** F05 historical quoted/send/revision/cohort semantics; automatic backfills or closed-period corrections; production reconciliation; schema/destructive changes; real provider sends; deployment and merge. Revision43 quotation layouts/frozen history, revision44 financial definitions, locked v1/admin assets and existing routes are unchanged.

SQLite and the sandbox PHP runtime do not prove MySQL row-lock/concurrency behaviour, crash/power-loss recovery, realistic scale, owner-host parity or live provider delivery. The existing MySQL/network skip is not a pass. Whole-OS every-action/all-role coverage remains incomplete. Green regressions do not certify historical books.
