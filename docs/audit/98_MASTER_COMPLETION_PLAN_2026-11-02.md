# Woodex OS — consolidated completion plan and resume record

**Updated:** 2 November 2026, Asia/Karachi. **Not a declaration that the entire project is complete.**

This is the current execution register. Read it before resuming older audit plans. Earlier decisions, accepted requirements and frozen document history remain binding; their historical test counts are not current-release acceptance.

## 1. Exact baseline and authority

- Baseline master: `3fcaff8e0f78aaaa2e6e2aed2011541fd4cff944`, release 97 merged through PR #1. Its GitHub native run `41335633442` passed **1,818 tests / 10,348 assertions**; this is baseline evidence, not acceptance of subsequent edits or proof of deployment.
- Work only on the active session branch `arena/7093000c-woodexinterior`. GitHub access and branch operation are working. No replacement branch, new prompt, repeated Continue request or hosting reconnection is necessary.
- Master remains the source baseline. Drive ZIPs are compare/reference inputs, never authorization to overwrite source, restore data or combine releases.
- Production is `https://woodex.com.pk/admin` / publicv1. Installed production SHA, database engine/schema and restored-backup integrity are **unverified**. Auto-deployment remains **OFF**. No production write, migration, provider send or deployment was performed.
- Workbook reconciliation/import is deferred. Existing backups, owner-edited pages, uploads, financial history, APP_KEY and private storage must be preserved. Never publish those artifacts in this public repository.

## 2. Delivered in this increment

### Native full-application synthetic preview

Real FrankenPHP 1.13.1 / **PHP 8.5.12**, genuine **Composer 2.9.1**, application dependencies installed from the existing committed lock, a separate source copy and a fresh synthetic SQLite database. The verified binary's SHA-256 is `5c8b81c1d093fbc188f4ee09ed9bde6e5ef4f40c9a22b453fb60eeaf8b161767`, matching the official release asset digest. This is not the older WASM/custom dependency resolver.

- Runtime app: ignored `.cache/preview/source/platform`; server listens on `0.0.0.0:8080`. It serves both admin and public website.
- Package discovery, migrations, installer and local-only demo seeders completed. **103 applied migrations = 82 application migrations + 21 settings migrations.**
- Providers have no credentials; Laravel outgoing HTTP requests are rejected, mail uses the non-sending array driver, database queues have no worker and no scheduler is running. There is no live-data restore.
- **24 Chromium checks passed:** public home; owner form login/session; dashboard, leads, contacts, tasks, quotes, estimations, invoices, payments, projects, expenses, payables, trial balance, inbox, Website pages/media/theme/speed, account security; Livewire/Alpine presence; mobile rendering; working estimator; no uncaught page JavaScript errors.
- **4 additional cross-site iframe checks passed:** login under third-party-cookie phaseout, partitioned session cookie, actual Livewire navigation and reload persistence. Harness uses separate loopback sites, explicitly grants local-network access, and is not certification of the real Arena proxy/browser environment. Browser native autofocusing is blocked in a cross-origin frame; login itself works.
- HTTP checks: public home/contact/estimator/admin login/theme CSS return 200; `.env` and `composer.json` are not exposed (404).
- Read-only SQLite checks: integrity `ok`, zero foreign-key violations. Finance tables currently contain **zero** journals, project costs, payments, allocations, bills and bill payments. Consequently zero imbalance/duplicate-source/orphan/over-allocation counts are **empty-fixture results**, not financial reconciliation or production sign-off. Accounting feature tests below exercise populated fixtures separately.

### Two independently reproduced source bugs fixed

1. `ExpensesIndex`: date-range total and per-account summaries previously summed only the latest 300 displayed rows. They now aggregate the complete filtered range in SQL; detail rendering stays bounded at 300. Shared `OtherIncome` receives the same correction. No historical data is rewritten.
2. `ProjectShow::addExpense`: replaces float multiplication/rounding with existing `Money::ofRupees`. Reproduced errors included `1.005` becoming 100 rather than 101 paisa under this native runtime and a large exact decimal losing one paisa. Posting/reversal actions and frozen sales documents are unchanged.

Five new regression cases: expense and income ranges exceeding 300, opposite-direction/date exclusion, older account groups, reversed/empty ranges, ordinary decimal, half-up rounding and large-integer precision. Before the fixes: **4 failed / 1 passed**. Afterward: focused **25 passed / 136 assertions**; expanded Unit + Accounts + Projects coverage **196 passed / 786 assertions**. Changed components pass Larastan level 6; four changed PHP files pass Pint. Source/release/website contract tests: **92 passed**. No dependency or schema changes, no frontend/build changes, no merge or production deployment implied.

The native CI allowlist now explicitly includes this active review branch in addition to the existing review branch and master. Hosted-runner, checkout-SHA, no-secret, immutable-source and isolated-MySQL boundaries remain enforced. This enables testing, not deployment.

## 3. Archive inventory — comparison still pending

Drive folder listing refreshed on 2 November: four items, no further page. These SHA-256 values are **Drive metadata**, not independent hashes of downloaded bytes or proof of a successful restore.

| Input | Bytes | Drive SHA-256 | Current disposition |
|---|---:|---|---|
| `woodex.com.pk (1).zip` | 26,656,610 | `aec40957af095eb44b833d2dac809ce838775cdbbf8ca3db8018666c9c2a39fe` | Contents not inspected; compare against exact master before selecting any recovery changes. |
| `woodex.com.pk bup 30-10-26.zip` | 207,289,626 | `61ea84dc62eb9aa304ab179d4c32a986e8f0fb38b33dedade4b9c1cf685f5035` | Exceeds current connector's 100 MB raw-download cap; not restored. Retain privately. |
| `woodexinterior-arena-8be318a2-woodexinterior (1).zip` | 61,802,108 | `fd7cfe6f961d84a6f72fddf7a5059f869ca6e11e4279104039eaa4a267042ac7` | Contents not inspected; do not treat archive name as commit identity. |
| `woodex ox 2026` workbook | Native Sheet | Not applicable | Reconciliation/import deferred by owner; prior CSV extraction failed. Do not repeat the failed export or infer contents. |

No archive has overwritten master, preview source, owner data or financial records. Source comparisons must classify identical, missing, modified, generated/runtime, private and rejected files; use a separate private extraction directory with ZIP path/symlink/size validation. Never execute archive installers or commit `.env`, databases, backups or uploads. Do not claim this comparison is complete from metadata alone.

## 4. Authoritative task register

Machine-readable status: [`98_COMPLETION_REGISTER_2026-11-02.csv`](98_COMPLETION_REGISTER_2026-11-02.csv). Each item separates source implementation, tests, merge, deployment and live verification, plus priority, exact gap, schema impact, dependencies and evidence. **Existing ≠ missing; local test ≠ merged; merged ≠ deployed; deployed ≠ live verified.**

Execution order:

1. **A01–A03:** preserve this register/preview and complete archive comparison without importing it. Do not rebuild release 97's already merged forms/estimator/CTA/theme/native-test cleanup.
2. **F01–F04:** review these two fixes, finish source-level posting/reversal/duplicate-booking/concurrency checks. Phase A actual-installation checks are read-only and separate from local synthetic evidence.
3. **V01–V03:** finish Vendors/Costs beside Projects and consistent linked financial summaries using existing Vendor/Bill/BillPayment/Expense/ProjectExpense models. They already exist; do not create parallel financial modules.
4. **T01–T02:** enforce owner-only Telegram through link/pairing, webhooks, commands, notification delivery, queued execution and role changes, not merely visibility of a menu.
5. **C01–C03:** dedicated Website Chat workspace on the existing application DB; compact/opening behavior, full paginated history, draft retention, visitor/staff isolation and mobile lifecycle tests. Website reply, Open WhatsApp and official API delivery must remain distinct.
6. **W01–W03 / I01:** selective charcoal shared UI and seven Website tools/heading, SEO tabs, media/theme/speed and missing pages; preserve native behavior, public URLs, Blog/Portfolio/file-writing and frozen documents. Finish real integration actions and signed event contracts without fake-success cards.
7. **S01–S02:** extend permission-safe Assistant diagnostics first. Its existing scoped queries are not absent; provider-based AI is a separate reviewed integration.
8. **Q01–Q03:** only justified versioned schema changes, fresh/upgrade/idempotence/concurrency tests and exact-candidate native CI/browser/PDF acceptance. Review source changes through a PR, not direct master writes.
9. **A04–A05 / R01–R02:** actual installed revision/runtime/schema/ledger inventory; private consistent backup and successful isolated restore; approved staging rehearsal; then separately approved manual production rollout. No auto-deploy activation or arbitrary Database Update click.

### Financial workflow decision that remains open

Unpaid supplier bills and paid expenses already have different posting semantics. Recommend supporting **both with explicit status and linked source records/allocations** so the same project cost cannot be booked again as a bill and then an expense. This recommendation is not confirmation of the unresolved owner choice. Do not add guessed liabilities, silently backfill old rows or select an old financial backup as the current ledger.

## 5. Release gates and held scope

- Do not merge recovered archive changes before comparison/review. These two bug fixes were reproduced against the master baseline, not recovered from an archive; they remain an unmerged source candidate.
- Source gates: exact tested SHA and locked dependencies; complete native synthetic MySQL suite including concurrent sequence/payment/reversal behavior; warnings/skips examined; full static/style/build checks appropriate to touched files; both-brand PDF/browser regression.
- Production gates: actual install identity and read-only discrepancies; privately consistent fresh DB/config/files backup; verified isolated restore including frozen PDFs, encrypted settings, owner-edited pages and uploads; tested rollback and explicit staging/production approval. See `tools/release/PRIVATE_BACKUP_RESTORE_CHECKLIST.md`.
- Provider integration code tests use fakes. A token/configured label is not receipt proof; live sends/webhooks/queued work require separate scoped authorization and verified destinations. Do not start a worker to prove connectivity.
- Deferred: financial workbook/import; Sheets append; Drive/GitHub backup integration; provider-based AI pending review. Excluded: framework rewrite, NestJS/React, multitenancy, payroll/ATS, inventory, i18n and `wx_*` imports.
- Preserve approved Interior/Furniture renderers, commercial terms, blank-preview/share-PDF behavior, optional fields/signatures and all frozen historical snapshots/PDFs. Do not re-render sent history as a cleanup operation.

## 6. Resume instructions — no replacement master prompt needed

1. Read `platform/AGENTS.md`, `docs/03-MASTER-SYSTEM-PROMPT.md` Part A, `docs/07-DECISIONS.md`, this file and the CSV; then inspect current Git status. Preserve owner work.
2. Remain on the active session branch. Do not reset/clean, switch branches, direct-push master, enable auto-deployment or request GitHub secrets. Use configured `git`/`gh` directly.
3. Inspect whether ignored runtime artifacts and port 8080 still exist: they are disposable and may vanish across environment resets. The private runtime origin is in `.cache/preview/runtime.json`; never commit runtime `.env`, key, DB or dependencies.
4. Native executable: absolute `.cache/runtime/frankenphp` path followed by `php-cli`; Composer is `.cache/runtime/composer/bin/composer`. Use absolute paths when working in the isolated app. PHP <8.6 does not support `php-cli -v`; query `PHP_VERSION` with `-r`.
5. TestCase intentionally ignores developer `.env`. Supply a newly generated disposable APP_KEY through the test process environment without printing it; never change TestCase to read real secrets. PHPStan can run in-process with `PHPSTAN_RESTART=native-cli-adapter --debug` to avoid FrankenPHP's different child-process CLI shape; this does not certify conventional PHP subprocess behavior.
6. Current ephemeral evidence: `.cache/preview/{browser-results,iframe-results,database-audit}.json`, `{accounting-expanded,regression-before,static-analysis,source-contracts}.log`, screenshots under `.cache/preview/shots`. These are working evidence, not durable public attachments. The non-sensitive counts and limitations above are the durable record.
7. Next unresolved work starts with A03 archive contents comparison and the remaining ledger/vendor/Telegram source gaps. Preserve the two tested fixes; do not present this preview or partial increment as whole-project or production completion.
