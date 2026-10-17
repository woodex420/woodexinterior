# Woodex — basic Sales + Accounts ERP plan

> **17 October document-builder follow-up:** [the Interior/Furniture implementation PRD](19-DOCUMENTS-ESTIMATION-IMPLEMENTATION-PRD.md) adds the complete document/estimation roadmap and the locally implemented description/design/in-window preview slice. The balanced-ledger modules below remain planned; this follow-up does not implement them.

**Owner decisions: 17 October 2026 PKT. Preview only.**

## Confirmed scope and delivery status

The owner requested questions before changes, then selected:

- Prioritize the **2025 lead/sales update**.
- Import **new contacts only**; leave matched existing records untouched.
- Build the **basic ERP with a balanced double-entry ledger**.
- Full 2025 accounting history exists, but financial files have **not yet been attached**. The supplied file is a January lead-management sheet, not an accounting ledger.

| Area | Status after this delivery |
|---|---|
| January 2025 lead import | **Applied to the local preview:** 16 new contacts and leads, two duplicate-phone rows skipped, eight rows held for phone/date review |
| Coordinated quote editing/approval safeguards | **Implemented locally and tested** across the core quote editor, recalculator, request/decision, reopen/revise, delete, view and client-change paths |
| Real MySQL/MariaDB concurrency verification | **Blocked/not run:** this sandbox has PHP-WASM/SQLite, but no native PHP/PDO MySQL, MySQL/MariaDB server or Docker runtime |
| Existing quotations, invoices and payment records | Retained; existing local safety improvements remain in place |
| General ledger/chart, supplier payables, income/expense/recurrence screens | **Planned, not implemented by this delivery.** No empty screens or fake accounting totals were added |
| 2025 financial transactions/opening balances | **Not imported**; waiting for actual files and Accounts reconciliation |
| Hostinger production | Unchanged; no push/deployment/production import |

This is an incremental extension of the existing Laravel application, not a claim that a complete ERP is finished.

## 1. Product structure

Keep the existing Sales quotation → approval → sent snapshot → client acceptance → project → invoice workflow. Extend an **Accounts** workspace around the existing invoices/payments instead of creating a competing invoice database.

| Workspace | Required behavior |
|---|---|
| Overview | Actual receivables/payables, cash/bank balances, verified receipts/payments and overdue items, with date/business-line filters |
| Invoices | Draft/review/issue/deliver separately; credit notes/reversals; partial settlement; immutable issued documents |
| Income | Earned revenue versus other income, separated from cash received and customer advances |
| Expenses | Supplier/cash expense documents, categories, project attribution, evidence, review and reversal |
| Recurring expenses | Templates, next due date, pause/stop/end date; one **draft** per due period, never automatic payment |
| Payments received | Verified customer receipts, partial/multiple invoice allocations, unapplied advances and refunds under controls |
| Payments made | Supplier/expense payments, partial allocations and bank/cash references; no automatic money movement |
| Receivables / payables | Customer/supplier statements, due schedules, aging, remaining balances and unapplied amounts |
| Cash / bank | Book balances, transfers and bank reconciliation; private account details remain protected |
| Reports | General ledger, trial balance, profit/loss, balance sheet, cash movements and receivable/payable aging |

Use existing TailAdmin/Livewire components, model policies, integer paisa and database queue/cron. No additional ERP server, Node runtime on Hostinger or silent hosting change.

## 2. Accounting foundation — next implementation batch

Build this before expense/payment screens start posting money:

1. Separate the **accounting entity** from CRM client companies. Start with an owner-confirmed legal entity; treat Interior/Furniture as business-line dimensions unless Accounts confirms separate legal books. Do not confuse a customer's CRM `companies` row with the business's accounting entity.
2. A reviewed chart of accounts: assets, liabilities, equity, income and expenses; cash/bank, receivables, payables, customer advances, supplier advances, revenue and expense categories. Parent/group accounts cannot receive postings. No fabricated opening amounts.
3. Accounting periods and explicit closing/reopening authority. A closed period rejects normal backdated posting; corrections use reviewed reversals or a controlled reopening.
4. Journal headers/lines with source identity, business date, reference, actor, company/entity, business line and optional project/counterparty. **Total debits equal credits** in integer paisa, with positive amounts and one side per line.
5. Draft → reviewed/posted → reversed lifecycle. Posted entries are immutable: never silently edit/delete history or hide an imbalance in a balancing line.
6. Atomic, idempotent posting from an approved business source, with unique source identity. Repeating a verification or invoice action cannot create another journal entry.
7. Default to the existing PKR operating currency. Reject unsupported foreign-currency posting rather than silently convert it. An FX feature requires approved currency/rate/rounding rules.
8. An explicit Accounts/owner permission matrix, audit trail and safe read-only reports. Existing broader own/team permission gates remain open until tested.

**Definition of done:** balanced/imbalanced, duplicate, permission-revoked, closed-period, rollback, reversal and MySQL race cases pass; the trial balance reconciles to posted source documents. UI totals must come from these records, not parallel editable balance fields.

## 3. Posting rules to review with Accounts

| Business event | Intended accounting behavior |
|---|---|
| Quotation / draft invoice / payment proof | No general-ledger money posting merely because a draft/proof exists |
| Earned customer invoice issued | Debit receivables, credit earned revenue and configured applicable tax accounts |
| Acceptance/advance invoice | Use the confirmed customer-advance/deferred-revenue policy; do **not** automatically call a deposit earned profit |
| Verified customer receipt | Debit actual cash/bank, credit receivable or an unapplied customer-advance account. Do not recognize the same revenue again |
| Advance becomes earned | Reviewed reclassification from advance/deferred liability to revenue according to the agreed recognition policy |
| Supplier bill approved | Debit the appropriate expense, work-in-progress/asset or approved recoverable-tax account; credit payables |
| Supplier payment | Debit payable or supplier advance; credit the real cash/bank account |
| Cash expense / other income | Reviewed evidence and the appropriate expense/income versus cash/bank entry |
| Transfer | Cash/bank to cash/bank, not income or expense |
| Cancellation/refund/correction | Explicit reviewed reversal/credit/refund workflow, linked to the original source; preserve history |

A bank balance is not profit; an invoice is not a receipt; a deposit is not necessarily earned income. Do not combine these into one ambiguous “Income” total.

**Before posting historical money**, Accounts must confirm fiscal/cutover dates, revenue/deposit recognition, tax treatment and the legal-entity/business-line mapping. These decisions are not inferred from the January lead sheet or chosen tax rates in sample data.

## 4. Implementation batches

| Order | Batch | Acceptance gate |
|---|---|---|
| A | Core quotation edit/approval safeguards | Local stale-state regressions now pass; finish MySQL and related-writer acceptance below |
| B | January lead import | Applied to preview, repeat import creates zero additional records; unresolved rows stay held |
| C | Chart, periods, journal posting/reversal and account permissions | Balanced immutable ledger, safe source dedupe and period controls |
| D | Receivables/invoice and verified receipt integration | Reconcile the existing invoice/payment/allocation ledger to the GL without duplicate revenue or cash |
| E | Suppliers, bills/payables, expenses and outgoing payments | Partial settlement, overpayment/advance handling, private evidence, approvals and reconciliation |
| F | Recurring expense drafts | Unique template/period admission, correct month-end dates, pause/stop and no auto-pay |
| G | Statements, aging, cash/bank reconciliation and reports | Trial balance/P&L/balance sheet/AR/AP reconcile to independently reviewed examples |
| H | 2025 financial staging and reconciliation | Owner/Accounts sign-off on counts, balances, opening figures, unmatched records and reports before posting |

Keep manual milestone invoice drafts (D-88), confirmed owner 2FA challenges and important Telegram financial/approval-write restrictions. No background campaign or automated approval is enabled by historical import.

## 5. 2025 data: what was safely updated

- Parsed the real heading row after five preamble rows, with trimmed column labels. There were **26 nonempty data rows**.
- Added **16 new contacts and historical leads**, retained original source fields/notes and original January dates, and recorded import provenance/checksums in the private runtime database.
- Skipped **two duplicate-phone rows**. Eight other rows need review: **four invalid/missing phone values and four missing/invalid dates**. No phone splitting, date carry-forward or invented identity was used. See [sanitized row review](2025-01-LEAD-IMPORT-REVIEW.csv).
- Matched against normalized primary/alternate/WhatsApp numbers, including archived contacts. Matched existing records are not enriched, reassigned, restored or opted back in.
- Original status/quotation/assignee/meeting notes remain historical source data, not instructions to mark deals won, create quotes/invoices, schedule meetings or move money. Imported leads are unassigned and marked for review; their original fields are available in the lead's enquiry details/provenance.
- Dates are **day-precision** only: timestamps use a documented UTC-midnight day anchor, not an invented actual enquiry time. The source date is retained. Do not treat these anchors as audited transaction timestamps; global app-timezone/storage conventions require a separate review before accounting history posting.
- No new SLA clocks, first-response alerts, automatic assignments, marketing consent or WhatsApp consent. No invoice/payment/financial posting was inferred.
- Took a fresh SQLite backup with the server stopped. Every pre-existing row across **66 checked tables** was preserved; only the lead number sequence changed as expected. Added 16 contacts, 16 leads, 16 timeline entries, 16 import references and 32 audit entries. No other table gained records during the import.
- An immediate second apply run imported **zero** records and recognized all 16 earlier imports. The raw CSV, customer identifiers, private reports and database backup were not copied into Git.

### Financial files still needed

Upload the actual 2025 files in the formats already used by the business: customer invoices/credit notes, receipts and allocations, supplier bills, expenses, outgoing payments, bank/cash statements and approved opening/closing balances. If files cover multiple months or businesses, include the relevant date range/entity information.

Stage and reconcile before posting. Full-history import needs verified starting balances and chronological documents; a cutover import needs approved cutoff balances and an unpaid-document strategy. **Never book both an opening balance and the same historical invoice/receipt twice.** Unknown matches, missing evidence and imbalances remain exceptions, not guessed transactions.

## 6. Quotation locking delivered in this batch

- The quote editor/recalculator now lock and reload the parent before mutable-state checks. Child rows refresh under that parent lock so partial changes use current fields.
- Approval request/decision, reopen/revise, delete, view and client-change paths use parent-first transactions and fresh state. Approval decisions lock quote → approval and reload the active actor.
- Approval decisions compare the **original requested fingerprint**, not a newly assumed proposal. The version-2 binding covers financial figures, revision, recipient details and relevant sections/items/milestones/content.
- Changed proposals/recipients and previously decided/withdrawn requests fail closed. Older pending/approved version-1 fingerprints need withdrawal/re-request/review; they are not silently upgraded.
- Requested changes needing approval allocate the next revision before asking, so the approval and sent version identify the same revision.
- Public change forms now carry the displayed revision, like acceptance forms. Older forms must reload. A stale view cannot downgrade an accepted quote or overwrite view counters from a stale model.

This is core-writer hardening, **not a claim that every related contact/company/bank/config writer shares the final race protocol**. Those races, lock-order/deadlock behavior and provider/delivery recovery still require verification.

## 7. Real MySQL verification gate

**Not executed here.** Use native PHP 8.4 with PDO MySQL, separate worker processes/connections and a disposable MySQL 8/MariaDB test database, never the owner's preview or production database. Refuse destructive test setup against any non-test database. Do not ask for database passwords in chat.

Synchronize competing workers at barriers and record committed counts, document hashes, journal/source identity and queue admissions for:

- edit/recalculate versus send or approval request;
- two approval decisions; decision versus reopen/edit; changed recipient/company versus approve/send;
- two sends; acceptance versus revise/change request; two acceptances;
- advance versus the same manual milestone; different milestones allocating suffixes on the same project;
- archive/merge/reassignment versus the same operations;
- rollback, PDF failure, lock timeout/deadlock and process termination around commit.

A passing result requires one valid committed outcome, no changed frozen PDF, no duplicate invoice/project/number and recoverable denied/retried work. Test deadlock retries deliberately; do not equate an SQLite pass or a single request with concurrent correctness. A durable financial delivery outbox and orphan-PDF recovery remain separate unfinished gates.

## 8. Evidence and references

- **58 new regressions**, 220 assertions: `LeadCsvImportTest` and `ApprovalEditSafetyTest`.
- Complete PHP suite: **714 passed, 4,422 assertions, one existing sandbox skip**, 232.30 seconds; `/tmp/2025-approval-full-tests.log`.
- Larastan level 6: no errors; `/tmp/2025-approval-phpstan-final.log`. Pint covers 19 changed PHP files; front-end build passed with the existing large-chart-chunk warning.
- Local preview code/assets were refreshed with the existing env/storage/database retained. Browser acceptance is recorded in the current STATE handoff; production/provider acceptance remains separate.
- Owner-supplied references, corrected into two separate URLs: [Gauzy](https://github.com/ever-co/ever-gauzy) and [ERPNext](https://github.com/frappe/erpnext). These are references for the requested plan, **not installed dependencies, a source-code port or independently verified feature comparisons**.

Publishing or production rollout requires a new coding session because this session's PR is closed/merged. Keep all existing local work; no cleanup/reset or live financial import is authorized by this preview plan.
