# 45 — Transactional workflow and ledger audit

**Repair follow-up:** owner subsequently approved 45-B (“Start”). See [revision46 implementation/QA](46_PAYMENT_LEDGER_REPAIR_QA_2026-10-28.md) for F01–F04 dispositions. Findings and six red results below are the preserved pre-repair audit; F05 remains deferred.

**Date:** 28 October 2026
**Authorization:** owner “Start next” after revision44, continuing the approved master audit.
**Status:** selected workflow audit completed; **repairs not implemented**. Whole-OS every-action coverage remains incomplete.
**Release recommendation:** **hold financial-workflow sign-off** until the P1 payment discrepancies below are repaired and retested. No deployment, merge, schema change, real provider send or historical-data repair was performed.

## 1. What ran

### Fresh passing regression evidence

**454 tests passed / 2394 assertions / 189.74s**, covering:

- New `tests/Feature/Audit/TransactionalFlowAuditTest.php`.
- Existing Accounts, Projects and Sales feature-test directories.
- Approval edit safety, advance invoices, payment integrity/payments, quotations/client portal, estimate conversion, projects, leads and report-kit tests.

Dedicated final rerun: **6 passed / 69 assertions / 11.03s**; new test file passes Pint and PHP syntax checks.

The six new workflow cases use real Laravel actions, signed acceptance HTTP requests, in-memory SQLite transactions, ledger queries and actual locally rendered PDF bytes. Storage, mail and notifications are isolated/faked; stray Laravel HTTP requests are prohibited. They do **not** fake the domain actions or journal models.

| New case | Actor / path | Fresh result |
| --- | --- | --- |
|Interior chain|Sales prices 8% discount + 16% tax → different manager approves → immutable send → signed client acceptance → lead Won/client/project → issued advance → partial/final Collect receipts|Pass: invoice/subledger/GL tie, distinct receipts, idempotent Verify retry, zero final receivable and unchanged quote PDF hash.|
|Furniture chain|Same approval/acceptance chain → no automatic milestone invoice → explicit full invoice draft → owner issues → partial/final Collect|Pass: native full-billing behavior retained; invoice/subledger/GL tie, unchanged quote PDF. Furniture 75% presentation/terms policy not altered.|
|Closed-period Collect|Owner collects against issued invoice after its accounting period closes|Pass: payment, allocation, receipt file and payment journal all roll back.|
|Unpaid void|Owner voids an issued unpaid invoice with reason; retries void|Pass: linked balanced reversal clears receivable; original frozen invoice path/bytes preserved; duplicate reversal refused.|
|Paid void refusal|Owner tries to void after a partial collection|Pass: refused; invoice/receivable and original journals retained.|
|Archived project|Existing accepted project is archived; same quotation attempts to reopen a project|Pass: refused; no duplicate project created.|

The broader **454-pass** result is not proof that the payment paths below are correct. Existing coverage passed while missing these cross-screen/ledger invariants.

### Fresh deliberately failing audit contracts

**6 failed desired-contract cases / 10 assertions / 6.66s** reproduce the unresolved findings. These are genuine failures, **not passing fixes**. Source is preserved as `evidence/45/45-unresolved-contract-probes.php.txt`; it is copied into the isolated runtime for a standalone Pest run and removed afterwards, so known-red audit probes are not silently mixed into the normal regression suite. They must be promoted into normal regression tests when the repair is approved.

No browser mutation was claimed: the payment form was exercised through Livewire's real component/request test harness, not Chromium clicks. No fresh visual/full-browser certification was performed in this increment. Revision44's landing/browser evidence remains historical evidence, not proof of these actions.

## 2. Reproduced findings

### 45-F01 — P1: staff Payments form does not post its verified receipts

**Entry:** `PaymentsIndex::save` → `RecordPayment::handle` → allocation + receipt. There is no `PostPaymentToLedger` call on this path. The posting service's only production caller found is `VerifyPayment`; that action returns immediately for an already-Verified payment.

**Observed for both Interior and Furniture, in minor units:**

| Measure | Expected after payment | Observed |
| --- | --- | --- |
|Issued invoice total|10000|10000|
|Verified receipt|5000|5000; receipt issued|
|Invoice remaining balance|5000|5000|
|GL receivable remaining|5000|**10000**|
|Payment-source journal count|1|**0**|
|Journal count after `VerifyPayment` retry|1|**0**, still missing|

Thus an invoice for **Rs100**, paid **Rs50**, shows **Rs50 due** operationally while its GL still has **Rs100 receivable**. A balanced trial balance would not detect the missing receipt journal: the original invoice entry remains balanced. The Revenue/Collections report reads verified payments and can therefore disagree with the GL cash/receivable position.

**Not a permissions-policy finding:** staff-recorded cash is already immediately Verified by the existing workflow. Repair must preserve current `payments.record` / `payments.verify` policy rather than silently requiring a new approval or widening accountant ledger access.

### 45-F02 — P1: same form bypasses accounting-period closure

After posting an invoice and closing its covering accounting period, the staff Payments form still:

- returned without an exception;
- committed **1 Verified payment**, **5000 paid_minor**, and **1 receipt file**;
- created **0 payment journals**.

In contrast, the separately tested invoice **Collect** path rejects the closed-period posting and rolls payment/allocation/receipt back. F01/F02 are two effects of the same missing atomic ledger step, not independent proof of two unrelated root causes.

### 45-F03 — P2: payment posting boundary accepts unverified money

Directly calling `PostPaymentToLedger::handle` on a PendingVerification payment created **1 journal**, left the payment PendingVerification and raised **no exception**. Invoice posting already has a status gate; payment posting does not.

This is a domain invariant/defence-in-depth finding. No public HTTP endpoint or unauthorized user path to direct posting was demonstrated. The normal Verify/Collect path sets Verified first. Do not present this as a proven remote exploit.

### 45-F04 — P2: failed invoice issue leaves an orphan PDF

An owner issues a draft invoice in a closed accounting period:

- ledger posting correctly throws `JournalPostingFailed`;
- invoice rolls back to **Draft**, `pdf_path = null`, **0 journals**;
- fake private storage nevertheless grows from **0 to 1 PDF**.

`InvoiceFreezer` writes its new PDF before the outer ledger step, but unlike receipt storage it does not register rollback cleanup. This proves a private orphan artifact, not a public data leak or corrupted issued invoice. No existing frozen history was modified by this audit.

### 45-F05 — P2 / metric-definition decision: historical sent totals move with revisions

This resolves revision44's previously unverified concern **44-F11** for the **Quoted/sent-period metric**, not every accepted-value/cohort question:

1. Send a quotation in September: September quoted value = **1000000 minor units**.
2. Revise and send **2000000 minor units** in October through the native actions.
3. September's report now returns **0**; October returns **2000000**.
4. The September frozen version still holds **1000000**, and its PDF hash is unchanged.

Cause: the report aggregates the mutable quote's latest `sent_at` and total, not immutable send/version history. The frozen quotation subsystem is working; the report definition is latest-send/current-book, not stable historical send flow.

A repair requires an explicit choice about **unique quotations versus revision/send events**, and how accepted-period flows differ from cohorts. Do not sum every revision and silently inflate “quotes sent”, or redefine accounting revenue. Historical report semantics are **outside the recommended immediate repair bundle**.

## 3. Path/source map

| Surface | Native chain | Audit disposition |
| --- | --- | --- |
|Payments → Record and issue receipt|`PaymentsIndex::save` → `RecordPayment::handle` → `AllocatePayment` → `receipt`|Fresh Livewire/DB failures F01/F02.|
|Invoice detail / tracking board Collect|`CollectPayment` → `VerifyPayment` → allocate → receipt → post ledger|New action-chain reconciliation/rollback passes; exact detail/board browser clicks not repeated.|
|Client proof → account verification|`SubmitPaymentProof` → PendingVerification → `VerifyPayment`|Existing Payments/PaymentIntegrity/GL tests rerun in the 454-test selection; no real proof upload/provider delivery claimed.|
|Telegram `/collect`|Command delegates to `CollectPayment`|Source mapped only; no real bot message/token tested.|
|Ledger posting|`PostPaymentToLedger` → method mapping → `PostJournalEntry`|F03 direct-boundary failure. Existing balanced/source-idempotency/period tests rerun.|
|Draft invoice issue|`IssueInvoice` → `InvoiceFreezer` → invoice ledger posting|F04 rejected-issue artifact failure.|
|Signed acceptance/project|`AcceptQuote` → lead/contact → `OpenProjectFromQuote` → brand-specific invoice workflow|New both-brand signed HTTP chain passes; full multi-session concurrency remains untested.|
|Financial period reports|`RevenueCollectionsReport` → current quote/payment/invoice aggregates|F05 real revision/date reproduction; no report-policy rewrite.|

## 4. Recommended next repair — 45-B (approval required)

**Recommend one bounded financial-integrity bundle:**

1. Make staff `RecordPayment::handle` include ledger posting in the **same transaction** as allocation and receipt. Reuse existing posting/method/period services; keep the current recording/verification permissions unchanged.
2. Require fresh Verified state at the payment-posting boundary; retain source idempotency and safe retries. Add pending/rejected/refunded/stale-state regression cases.
3. Register rollback cleanup for **newly written invoice artifacts only**, including outer/nested failures; never delete/re-render an existing valid frozen document.
4. Promote the corresponding red probes into normal regression tests. Retest both brands, form/Collect/verification parity, allocation versus unallocated credit, closed periods, method mapping failures, receipts/queues and frozen document hashes.
5. Produce a **read-only reconciliation report** for existing Verified payments without matching payment journals. Do **not** auto-post old receipts, rewrite history, backfill closed periods or touch owner financial data without a separate reviewed approval.

**Excluded:** schema changes, new financial approval policy, accountant permission broadening, historical report/cohort redesign, production data corrections, providers/sends, deployment and merge.

**Single decision requested:** approve this 45-B bundle (F01–F04), leaving historical reporting F05 for its own metric-definition decision?

## 5. Evidence and limits

- `platform/tests/Feature/Audit/TransactionalFlowAuditTest.php`: six new passing native workflow cases.
- `evidence/45/45-observed-discrepancies.json`: amounts/counts only from isolated fixtures, including failed Verify retry.
- `evidence/45/45-red-contract-results.txt`: actual six-failure Pest output, not a claimed green result.
- `evidence/45/45-unresolved-contract-probes.php.txt`: reproducible desired contracts, excluded from normal discovery until repairs.
- `evidence/45/45-action-coverage.csv`: per-case actor/action/expected-observed disposition.
- `evidence/45/45-verification.txt`: exact commands/outcomes and runtime recovery notes.

Runtime recovered after sandbox reset: all present source files were byte-checked against pushed revision `7313d7e`; only omitted committed build assets were restored. Git metadata was realigned with a mixed reset that preserved working files. Runtime/vendor dependencies and demo SQLite were rebuilt **outside Git**, reusing the committed v1/admin build without a new asset build. No production source change was made.

MySQL locks/isolation, realistic scale/concurrency, live provider delivery, the owner's separate deployment hostname, all roles, all API/website actions and complete project lifecycle remain unverified here. Passing 454 regressions and six selected new chains is not whole-OS sign-off. Revision44's master inventory remains a backlog, not a completion certificate.
