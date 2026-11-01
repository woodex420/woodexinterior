# A0 safety implementation — first local batch

**Date:** 17 October 2026, Asia/Karachi. **Authority:** after the audit, the owner instructed implementation to start in the recommended order. **Release state:** local changes only; not pushed, deployed, or accepted on production.

Use with the [baseline audit](21-OPERATING-SYSTEM-AUDIT.md), [75-package backlog](23-AUTOMATION-COMPLETION-BACKLOG.md) and [current handoff](../platform/STATE.md). This batch does not implement the master agent or enable automated financial decisions.

> **Subsequent batch:** [Telegram identity and consent hardening](25-TELEGRAM-CONSENT-HARDENING.md) implements the next B08 slice and records newer full-suite evidence. The results/next-work notes below describe this first batch at its completion.

## 1. What changed

### Finance — B02, B03 and part of B05

- `AllocatePayment` reloads/locks the payment and locks candidate invoices in ID order inside a transaction. It refuses pending/rejected/refunded payments, negative/non-integer/excess allocations and edits to already-receipted allocations.
- Explicit targets must belong to the payment's client, be payable issued/part-paid PKR invoices rather than draft/void/paid/credit notes, match an explicitly selected project, and be compatible with the selected receiving account's business line. Receiving accounts must be active; a selected project must belong to the client. Automatic candidates use the same contact/currency/state/project/account filters. Payments currently have **no currency column**: this is a PKR-only safety boundary, not a new multicurrency feature.
- `RecordPayment` now honors `allocations` as a strict `invoice ID => positive integer paisa` map. All selections are validated before any balance is changed. The total cannot exceed remaining payment credit, and a selected amount cannot exceed its invoice balance. A bad entry rolls back the entire payment/allocations/receipt.
- **Existing D-85 restored for automatic allocation:** an `invoice_id` is a preference, not an explicit allocation map. Pay it first, then eligible oldest other invoices, retaining final excess as credit. With an explicit map, pay only those selected amounts; retain its unselected remainder as credit. Supplying both a preference and a nonempty map is rejected. Explicit payment-project and account-line constraints are not bypassed to consume excess.
- `RecordPayment`, `VerifyPayment`, `RejectPayment` and receipt issuance check current active staff/finance permission at the action boundary, not just the Livewire button. This does not constitute the full own/team permission review in B08.
- Verification/rejection reload and lock the current payment before deciding. A stale retry cannot replace a receipt, reject verified money or verify an already-rejected claim. A direct receipt retry returns the original identity and does not rerender or notify again. The existing unique receipt-number constraint remains in use.
- Receipt storage failure rolls back confirmation. Laravel 13 rollback callbacks delete a newly generated receipt if its enclosing transaction rolls back, including an outer transaction. Client mail and team notifications are queued **after commit**, with real database-queue rollback/commit tests. A hard process kill can still leave an orphan file; crash-time reconciliation and durable financial delivery outbox remain follow-up work.
- Client proof submission reloads the invoice under lock and validates payable PKR state and positive integer paisa. It still creates only a pending claim; no OCR, AI or webhook verifies bank funds.
- Staff and public proof amount parsing use `Money::ofRupees`, not float multiplication. Forms reject scientific notation, more than two decimal places and unbounded inputs.

**Not added:** manual allocation-map UI, applying old receipt credit to later invoices, refunds, credit notes, receipt adjustment workflow, request idempotency keys for recording a brand-new payment, multicurrency ledger, or a durable mail/notification outbox. Attempts to change already-receipted allocations are blocked, not silently renumbered.

**Remaining delivery/unknown-outcome risk:** an after-commit enqueue failure can leave confirmed money without its delivery job, and an interrupted HTTP response does not prove the payment rolled back. Inspect the ledger before retrying a new payment; do not record the funds again just to resend a receipt. Durable financial delivery recovery and new-payment request idempotency remain release-hardening work, not guarantees supplied by these once-only verification tests.

### WhatsApp — B06 and core B07

- The signed HTTP controller iterates **every entry, change, message and status**. Each supported item gets its exact normalized payload, matching contact profile and original event identity in the existing unique webhook ledger. All items are persisted before enqueueing business work.
- `QueueWhatsAppEvent` and `ProcessWhatsAppEvent` separate admission from processing. With the production **database** queue, HTTP does not create contacts/conversations, download media or call a provider. Signature failure still rejects intake before storing anything. A sync queue remains synchronous, including ordinary test fixtures; do not configure sync on production and claim fast acknowledgment.
- A worker reloads/locks the event, processes its exact ID and commits domain writes with the processed marker in the same transaction. Duplicate jobs and duplicate HTTP delivery have no second business effect. Failed legacy batch rows are replayed by the correct message/status ID rather than item zero. An inbound message already written by the old receiver is not duplicated when its failed ledger row is recovered.
- A five-minute queue lease plus a bounded minutely sweeper recovers a received row left behind before enqueue, or an enqueue/worker failure. Processing failures retry after **1, 2, 4 and 8 minutes**, with a maximum **five processing attempts**. Thereafter the row remains failed for staff review; provider delivery alone cannot reset that limit. Transport-level job retries are separate from this inbox attempt counter.
- A status that arrives before its outbound message is retained as failed/retryable, not falsely processed. Delivery updates lock the outbound message; late sent/failed/delivered notifications cannot downgrade a delivered/read message. A later successful delivery can supersede a failed send status.
- A supplied phone-number ID that differs from the configured phone is ignored, not merged into the company inbox. Legacy payloads without that metadata retain compatibility; this is not a multitenant account-routing implementation.
- Delayed/replayed inbound messages use the original provider timestamp for the 24-hour reply window. They do not gain a fresh window from retry time or replace a newer conversation preview. STOP behavior remains unchanged and tested.
- Stored processing errors and these new diagnostic log entries contain only safe generic messages/event IDs/exception classes, not raw exception messages, SQL bindings or provider bodies. Trusted webhook payloads themselves remain private business records; retention/access controls are separate work.

**Not added:** a replay dashboard, Telegram redrive command, lead qualification/bot, new external worker, Redis, campaigns, or a general automation engine. Media acquisition still uses the existing downloader inside queued processing; failed-download recovery, HTTP/content validation, file cleanup and outbox separation need their own completion checks. Historical rows incorrectly marked processed by the old batch handler are **not automatically rewritten**: inspect/reconcile them before production acceptance.

## 2. Schema and operations handoff — not executed on production

New migration: `platform/database/migrations/2026_10_17_000300_add_whatsapp_webhook_recovery.php` adds `queued_at`, `next_attempt_at` and a recovery index to the existing webhook ledger. No customer table is truncated and no credentials are changed.

Before release, the operator must:

1. Back up the database and private files; record deployed revision and pending migrations (B01).
2. Publish from a **new coding session**; this session is closed to GitHub operations. Apply the migration through the existing database-update workflow before enabling the changed intake path. Keep the schema fields if application code is rolled back; do not drop a live inbox to roll back code.
3. Confirm `QUEUE_CONNECTION=database`, working queue tables, and the existing hPanel minute cron. The scheduler runs `woodex:retry-whatsapp-webhooks` before the existing bounded `queue:work` drain; no daemon/new cron is required. Verify real queue age, drain and worker-timeout/retry-after settings rather than relying on a test fake.
4. Check the configured Meta app secret and phone ID privately, then prove a real inbound/reply/delivery round-trip and failure recovery. Do not paste payloads or credentials into a public issue.
5. Have Accounts accept cross-client/state/currency/account rejection, D-85/explicit-map behavior and one stable receipt. Run verify/verify and verify/reject interleavings on production-like MySQL before sign-off.
6. Audit old failed/received rows and any old batched rows already marked processed. Do not bulk replay all history, reset successful events or assume old damage was repaired by deploying new code.

Due retries can be polled manually from `platform/`:

```sh
php artisan woodex:retry-whatsapp-webhooks --limit=100
```

After reviewing and resolving a **specific** failed event, a trusted console operator may replace `EVENT_ID` with its actual numeric ledger ID:

```sh
php artisan woodex:retry-whatsapp-webhooks --event=EVENT_ID
```

This starts a new bounded retry cycle only for a signature-verified, failed WhatsApp row. Processed, ignored, untrusted and other-provider rows are refused. It is a server-console operation, **not exposed through Telegram/MCP/public HTTP**. A safe notice records its event ID; no provider payload is printed.

## 3. Local verification

- New desired-behavior suites: `PaymentIntegrityTest.php` and `WhatsAppWebhookRecoveryTest.php`. The historical defect-observation probe remains documentary evidence only, not installed as passing repair CI.
- Existing `PaymentsTest.php` now restores the issued state after testing draft invisibility before recording a legitimate payment; it no longer depends on paying a draft. `ConversationsTest.php` supplies a current provider timestamp when testing an open 24-hour window.
- **Final complete PHP suite: 561 passed / 3,774 assertions / 1 skipped**, 148.07 seconds (`/tmp/a0-full-tests-final.log`). The sandbox-specific installer/database-driver check is skipped. This includes **50 new desired-behavior cases** in the two new suites, as well as existing finance/conversation, Google, Telegram, MCP, website and architecture checks. It is a fresh run, not arithmetic added to the old baseline.
- Earlier focused repair run: **72 passed / 385 assertions**, before the final two after-commit finance dispatch cases (`/tmp/a0-regressions-v3.log`). Final finance integrity subset: **30 passed / 146 assertions** (`/tmp/a0-finance-final-extra.log`). The final complete run above includes all of these cases.
- Larastan level 6: **no errors**, `/tmp/a0-phpstan-final.log`. Pint run on all **21** PHP paths in this batch; no unrelated integration files reformatted. Existing Node MCP adapter tests: **3/3 passed**, `/tmp/a0-mcp-node-tests.log`.
- Tests use seeded in-memory SQLite and fake providers, except that the real Laravel database queue is exercised against that isolated SQLite database. There is **no concurrent MySQL proof, real-provider proof or production migration/deployment** in these results. No browser suite was rerun; the two previously known homepage form expectations remain unresolved. No preview/customer database was reset.

## 4. Remaining gates / next batch

| Package | Local progress | Still required |
|---|---|---|
| B01 | No new production verification | Revision/schema/config/cron/mail/Telegram/backups proof in a new session. |
| B02 | Allocation invariants, locks, actor gates and negative regressions implemented | Accounts/context acceptance and production-like MySQL verification with B03. |
| B03 | Locked once-only decisions, stable receipts, after-commit dispatch and rollback cleanup tested | Concurrent MySQL verify/verify + verify/reject; crash/orphan reconciliation; durable delivery recovery/outbox. |
| B04 | Not started | Quote acceptance/send/milestone/numbering fresh-state and idempotency checks. |
| B05 | Map contract honored and automatic D-85 restored | Accounts policy acceptance, selection/credit UI, applying historical credit and documented adjustment/reallocation rules. |
| B06 | Full batch/exact-ID processing and replay regressions implemented | Real-provider acceptance and historical falsely-processed-row reconciliation. |
| B07 | Durable inbox, queue lease, sweep/backoff/limits and console redrive tested | Real hosting failure/timeout evidence, monitored review surface, media and retained-history recovery checks. |
| B08 | Finance action actor checks added; existing STOP tests preserved | Telegram sender/group identity, active-link/rate/header checks, full own/team finance permissions and consent-resurrection review. |

The backlog's package checkboxes remain **full acceptance gates**, not claims that locally repaired sub-items are absent. Next: complete B08 and B04, prove the remaining B01–B07 runtime/MySQL gates, then build common events/outbox/approval/dashboard foundations. Preserve approval-gated important Telegram writes, manual draft milestone invoices (D-88), optional-owner-only 2FA, and the existing Google/MCP/Telegram work.
