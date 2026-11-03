# Revision 99 — owner-only Telegram implementation and core completion scope

**Date:** 2 November 2026, Asia/Karachi. **Scope:** source/isolated synthetic QA only. No merge or production deployment.

## Owner decisions confirmed this turn

1. Deployment is the **final** task after remaining core pending/paused work and verification.
2. Vendors/project costs must support **both unpaid supplier bills and paid costs**, with linked payments and a single accounting source. Linking implementation is pending; no historical repost/backfill is authorized.
3. Keep previously deferred extras deferred: financial workbook reconciliation/import, Sheets append, Drive/GitHub backup integrations and provider-based AI. “All paused tasks” does not bring those extras into this release.
4. Preserve frozen financial documents/history. Continue existing approved owner-only Telegram scope without repeating permission/branch/hosting questions.

These decisions are saved in `docs/07-DECISIONS.md`, revision 98's master plan/register, `PROJECT_STATUS.md` and `platform/STATE.md`.

## Implemented — not just hidden controls

- **Fresh owner authority:** `User::telegramOwners()` / `canUseTelegram()` check the database, including current role, active state and soft deletion. Multi-role web-guard owners remain authorized; cached roles are not authority.
- **Usable private link:** `TelegramLink::usable()` requires an active positive-ID private chat and currently authorized owner. Null chat type remains accepted only as a legacy private link. Existing staff/group records are retained, but cannot confer authority.
- **Pairing:** issuance requires owner authority under the existing user/link transaction locks. Webhook pairing now uses the same domain pairing action as the alternate entry point, keeping consistent user -> link lock order, fresh code/expiry checks, one-time consumption and chat takeover protection. A code issued before demotion cannot pair afterward.
- **Commands/callbacks:** both command entry paths require owner authority for operational commands/help. Direct command/callback buses deny non-owner users as defense in depth. Callback handling checks the actual callback sender against the positive private chat, refuses bots/missing senders/group routing and silently ignores unauthorized callbacks without business effects or callback answers. Normal owner actions, expiry and replay protection remain.
- **Notifications:** routing reads fresh link/owner state. The channel refuses staff, deactivated/deleted/demoted owners and anonymous Telegram routes. Staff retain in-app notifications; existing enquiry email fallback remains. Owner quiet-hour and critical-alert behavior is retained.
- **Queued/quiet-hour delivery:** new pending messages require a usable owner link. Flushing selects authorized private links and rechecks role/link/chat/mute before **each** message, including authority changes during a flush. Unauthorized backlog remains undelivered; it is not falsely marked sent or bulk deleted.
- **Role lifecycle:** supported staff-management demotion/deactivation disables the link and clears outstanding pairing codes. Direct role changes still fail fresh routing/command/delivery checks even if an old model object holds cached owner roles.
- **Profile/integrations:** staff keep security/2FA and in-app notification information but receive no pairing QR/code or Telegram health panel. Configuration/connection/webhook/test actions and Telegram details require owner authority. Non-owner integration lists/logs omit Telegram information. Other connectors' permissions remain unchanged.
- **No schema migration or dependency change.** No financial posting, PDF snapshot, sent document, public URL or production data changes. Vite rebuilt committed assets for the profile view change; no old JavaScript replacement.

## Validation

- **152 Telegram tests / 769 assertions**, including **34 new owner-only security/lifecycle cases** covering all seven non-owner seeded roles, multi-role owners, legacy private links, cached-role/link changes, queued notification execution after demotion, anonymous routing, queued backlog, mid-flush role change, staff-management invalidation, pairing-code demotion, both command entry points, forged callbacks and server-side profile/integration denial.
- **22 adjacent tests / 100 assertions** for CRM enquiry notification/email fallback, public lead assignment, SLA alert idempotence and system integration screens.
- The first all-suite run found four old tests expecting staff Telegram access. Those expectations were deliberately changed to owner-positive/staff-negative checks while retaining assignment, in-app/email, SLA replay and secret-redaction assertions. The full final suite is rerun rather than counting those old expectations as acceptable failures.
- **Full application Larastan level 6: zero errors.** Native in-process adapter prevents FrankenPHP's different child CLI shape from restarting PHPStan; no source/vendor/test assertions were patched to force a pass.
- **Pint: 26 changed PHP/test files pass.** **Vite build passes**, with the existing large-chunk size advisory; no claim that this resolves performance work. **92 source/release/public-form contracts pass.**
- **37 current Chromium checks pass:** the prior 24 full-app checks and four cross-site iframe checks were rerun, plus **9 additional policy checks:** owner/staff login/security, owner-visible vs staff-hidden Telegram controls, owner-visible vs staff-hidden integration card, mobile pages/styles and no uncaught JavaScript errors. Separate synthetic staff fixture only in the isolated preview database; no production/staff credentials used.
- **Full final native suite: 1,857 passed / 10,510 assertions**, 216.80 seconds, exit 0, with `--fail-on-warning --fail-on-skipped --display-all-issues`. This is genuine native PHP 8.5.12 with disposable SQLite fixtures, not MySQL/production certification. Initial monolithic run exceeded PHP's 128 MB default; final isolated test process uses a private 1 GB PHP memory setting without changing application/vendor/TestCase behavior.
- Hosted native CI run `41495382678` succeeded for previous source `164050688445f050b4e7c0833f521f1eabfd3631`; it **does not** certify this new increment. New exact-candidate hosted CI remains a separate gate after push.

## Boundaries and remaining tasks

- No live Telegram request/send/webhook registration was performed; provider operations in tests use fakes. Preview has no provider credentials, rejects outgoing Laravel HTTP, uses array mail and has no scheduler/worker. Real owner receipt remains unverified and requires scoped authorization.
- This batch enforces owner-only authorization; it does **not** certify exactly-once external delivery under concurrent workers or provider/network races, nor all production lifecycle behavior. Those acceptance claims must be tested separately rather than inferred.
- No production writes/migrations, archive recovery, private data restore, merge or deployment. Auto-deployment stays OFF.
- **Whole project still incomplete:** archive content comparison; project/vendor financial links and cross-model duplicate-booking acceptance; Website Chat workspace/history/drafts/lifecycle; selective charcoal Website tools/SEO/media/theme/speed; permission-safe Assistant diagnostics; justified migrations/upgrade/concurrency/PDF/workflow QA; actual installation audit, consistent private backup/isolated restore and staging/recovery acceptance. Use revision 98's 30-item register.

## Next financial source gap — read-only schema inventory

The existing synthetic schema confirms `bills`, `bill_items`, `bill_payments` and `expenses` have **no project link column**. `project_expenses` has `project_id` and `journal_entry_id`, but no vendor/bill link; `journal_entries` has `source_type`, `source_id` and `project_id`. This is why the approved both-linked workflow is not already complete merely because Vendors/Bills screens exist. Design links around the existing posting sources; do not create duplicate liabilities or backfill historical rows automatically. No new financial migration has been added or run in this batch.

## Continuation evidence

Ignored private runtime evidence is under `.cache/preview`: `owner-telegram-final.log`, `owner-only-regressions.log` (earlier fixture correction run, not final acceptance), `owner-adjacent-tests.log`, `owner-static.log`, `owner-source-contracts.log`, `owner-policy-browser-results.json`, `full-owner-regression.log`, frontend build log and screenshots. Do not publish runtime environment, database, generated credentials or raw private data. The source changes and non-sensitive counts in this report are the durable deliverable.
