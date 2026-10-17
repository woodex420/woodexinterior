# Woodex OS: build state

_Update at the end of every task (docs/03 Part A §7)._

## Current handoff: document-library/design vertical slice + complete estimation/Accounts PRD — 17 Oct 2026 PKT

- **Plan first:** `docs/19-DOCUMENTS-ESTIMATION-IMPLEMENTATION-PRD.md` completes the Interior/Furniture master plan before code, builds on the approved balanced-ledger scope in docs18, analyzes the private WF-10153 Furniture reference, distinguishes six template/master layers, maps the owner's frontend service families, links GitHub research with license cautions, and stages photo rows, advanced estimates, manual invoices and Accounts. No source PDF/PII/prices/images were added to public code or imported as transactions.
- **Implemented first local slice:** additive `description_templates`/`document_designs` migration,12 safe editable starter descriptions/two brand profiles. Sales → Description Library supports brand/section search, CRUD/archive, copy to a new unpriced quote line and New Rate Book Item. Existing rates/quantities/text do not follow template updates. Active/brand/state/permission checks and500-character catalog compatibility are enforced.
- **Document Design:** Settings-managed shared brand name/accent/header/footer/local JPEG/PNG logo (96KB,600×240px). New/duplicated quotes copy immutable profile values/logo bytes/hash into metadata; existing drafts adopt only through explicit **Apply current design**, using `QuoteEditor`/parent locking and approval fingerprint binding. Project shares Interior design. Quote-derived invoices inherit frozen sent-quote branding. Old snapshots retain legacy layouts; old pending approvals may require withdrawal/re-request, not silent upgrades.
- **In-window previews:** quote/header/version/invoice-card/invoice-list PDF controls use an authenticated same-window overlay with close/download/focus controls. Sent quote default PDF now serves frozen bytes. Missing issued invoice PDF fails409 for reviewed recovery rather than live regeneration. Existing advance-invoice creation/freeze flow verified in tests and isolated browser QA; no invoice/payment/acceptance record was created in the owner's preview by QA.
- **Evidence:** full **744passed /4,500assertions /1existing skip**,259.11s (`/tmp/documents-full-tests.log`),30new tests/78assertions. Larastan6 no errors; Pint22 PHP files PASS; Vite PASS (existing chart-size warning). Dedicated browser checks pass description creation/copy, footer/logo persistence, brand switch, quote/invoice inline PDF, no new tabs/JS errors,1440/390/360px. QA iframe checks both3pc-blocked/no-cookies PASS. Docker shell/XML structure checked, **native engine/Compose/MySQL execution NOT RUN**.
- **Broad browser gate remains OPEN:** old `browser-check.mjs` homepage `#lf-name` selectors fail on both the unchanged8080 baseline and QA homepage. Its admin/layout/blur-save/approval/send/client PDF/acceptance checks pass on the isolated copy. Do not say the complete browser check passed; public website behavior was not rewritten for this task.
- **Data-safe QA/refresh:** used a separate `/tmp/woodex-documents-qa/app` vendor/storage/database/cache copy; `SANDBOX_APP_DIR` allows isolated sync and preview cookie-memory file can be overridden. Before8080 migration stopped server, backed up SQLite privately, compared all66original table-row hashes and private env: unchanged. Added only migration bookkeeping +12description starters +2designs. No reset/reseed/reimport/backup restoration on active data. Existing January16new contacts/leads and review holds preserved.
- **Runtime request:** no native PHP/MySQL/Docker engines installed; Debian/DockerHub downloads unavailable under npm/PyPI-only sandbox networking. `tools/docker/` provides a prepared, guarded PHP8.4+MySQL8.4 QA harness with disposable credentials, read-only source, isolated volumes and no exposed database port. It is **not built or run**, not a change to Hostinger runtime and not evidence of concurrency acceptance.
- **Next:** furniture short-name/specification/photo columns + immutable media and PDF visual review; then advanced BOQ/estimate definitions and shared frontend pricing; chart/periods/journal foundations, manual invoice builder/receivables integration and payables/expenses/recurrence per docs18/19. Native MySQL races/related writers/outbox recovery/Accounts UAT/actual2025finance files remain gates. These features are **not implemented** by the new design/library screens.
- **Final8080 check:** authenticated existing Sales/imported lead and both new screens200; quote-modal PDF200, no new tab, zero JS errors,1440/390/360px; fresh iframe3pc-blocked/no-cookies PASS. Current process `woodex-document-builder-preview-fcc209b4`,PID25377,port8080. QA8090 stopped. Private proof raster confirms a local logo/custom footer fit without header collision; no product-photo or multipage certification. Logs `/tmp/documents-preview-final.log` and `/tmp/documents-preview-iframe.log`.
- **Publication:** local8080 preview only. Closed session cannot publish; start a new coding session for GitHub/Hostinger release. Preserve optional owner2FA, consent/STOP, manual milestone draft-only behavior and all existing private data.

## Previous handoff: January 2025 leads imported; core approval/edit locking; Accounts scope confirmed — 17 Oct 2026 PKT

- **Owner answers recorded before changes:** prioritize “2025 data update in lead sale”; **new contacts only**; **basic ERP with balanced ledger**; 2025 account history exists. Only the January lead CSV is attached so far. Actual invoice/receipt/expense/payable/bank/balance files still need upload; never fabricate financial history from lead notes. Complete scope, current/planned matrix and staged Accounts build: `docs/18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md`.
- **Applied to preview, not production:** supplied CSV had five preamble rows then 26 lead rows. Created **16 new contacts +16 historical leads**, skipped **two duplicate-phone rows**, held **eight rows** (four invalid/missing phones, four missing/invalid dates). Sanitized row review: `docs/2025-01-LEAD-IMPORT-REVIEW.csv`. Raw CSV/private data/reports/SQLite backups stay outside Git. Historical statuses/quotation/assignee/meeting fields retained in private lead enquiry/provenance; no invented won deals, assignments, appointments, quotes, invoices or payments.
- New `LeadCsvImporter` and `woodex:import-lead-csv` are dry-run by default; apply needs an explicit flag and a **fresh active owner**. New-only matching includes primary/alternate/WhatsApp numbers and archives. Stable batch/source IDs + checksums use `legacy_refs`; changed prior rows require review. Immediate second apply created **zero** additional records and recognized 16 imports. Historical capture goes through `CaptureLead` with explicit import-only historical date, no consent, no event/alert/automatic assignment/SLA clocks. Created/occurred times are **UTC-midnight day anchors**, not audited actual event times; source date/day precision retained. Global app timezone/storage conventions remain a finance-history review gate.
- Backed up the current DB while preview stopped, then applied the import. **Every pre-existing row across 66 checked tables was preserved**; expected lead numbering advanced. Added only contacts16/leads16/timeline16/legacy_refs16/audit32; no jobs, appointments, invoice/payment or other records were added by the import. Never restore an earlier backup over the owner's ongoing preview edits.
- **Core B04 coordinated locking implemented:** new `QuoteWriteLock`, parent-first editor/recalculator and fresh child rows; approval request/decision, reopen/revise, delete, view and public-change actions lock/recheck current state. Actors reload for privileged actions. Decisions compare original requested v2 content/revision/recipient/figures binding; changed proposals and stale decisions fail closed. Changed-revision approval asks allocate that revision before the request. Older v1 approvals require withdrawal/re-request/review, not silent upgrade. Public changes, like acceptance, require displayed revision; old forms must reload. Stale views cannot undo acceptance/lose counts.
- **Not full B04/MySQL acceptance:** related contact/company/bank/config/milestone writers, deadlock/retry and multi-worker tests remain. Native PHP/PDO MySQL, MySQL/MariaDB server and Docker are absent here; **real concurrency verification NOT RUN**, not replaced by SQLite claims. Orphan PDF and durable financial outbox recovery still pending. No important Telegram writes/automatic milestone issuing enabled; owner2FA policy unchanged.
- **Evidence:** 58 new regressions /220 assertions; full **714 passed /4,422 assertions /1 existing sandbox skip**, 232.30s (`/tmp/2025-approval-full-tests.log`). Larastan6 no errors; Pint `--test`19 PHP paths PASS; Vite build passes (existing chart-size warning). Final authenticated Leads/list/imported lead detail/Quotes/Invoices/Payments all200; no page JS errors. App Connections200, three scripts/styles200, 1440/390/360px no overflow. Iframe checks **3pc-blocked + no-cookies PASS** (`/tmp/2025-approval-iframe.log`). Broad homepage/browser acceptance not recertified.
- **Preview running on8080:** current source/assets synced, config/views refreshed without reset/reseed/env edit; no new schema migration in this slice. No browser financial/provider writes and no backup restoration during QA. Data is in the local runtime, not the production database.
- **Next implementation:** chart of accounts, periods, balanced immutable journal posting/reversal, source idempotency and account permissions; then wire existing invoices/verified receipts, supplier payables, expenses, outgoing payments, recurrence drafts and reconciled reports. These modules are **planned, not implemented by this delivery**. Confirm recognition/cutover/tax/entity policy with Accounts before historical posting. User selected balanced ledger, not inventory/payroll or an ERPNext/Gauzy replacement. Actual MySQL test environment and financial files remain explicit gates.
- **Publication:** preview only. Closed/merged session cannot publish; new coding session required for release. Preserve all existing local work and private preview data; do not reset/clean or use production money/data as test fixtures.

## Previous handoff: port 8080 refreshed; B04 foundations started — 17 Oct 2026 PKT

- Owner requested updated App Connections on **8080**, restart, then next work. Completed two data-preserving preview refreshes; listener runs on `0.0.0.0:8080` with current code/rebuilt assets and B08 changes. First refresh applied pending WhatsApp recovery migration. Final `migrate --force` had nothing pending; B04 unique milestone index was already installed and verified. Backups remain outside Git; all **66 existing business/settings/session tables** were byte-for-byte row-equivalent across the final migration/cache refresh (migration bookkeeping excluded). No reset/reseed/backup restore over ongoing preview work; env/storage/vendor retained.
- **B04 first slice implemented**, not package completion. Report: `docs/17-QUOTATION-IDEMPOTENCY-START.md`. Send/accept reload/lock current quote and recheck status/validity/revision; send rechecks existing approval figures and fresh active actor/policy. Fresh relations used for snapshots. Repeated sends/acceptances are refused without duplicate business effects; acceptance HTTP form explicitly carries displayed revision so an old form cannot accept a later resend.
- Project/advance helpers serialize on quotation, require accepted state, and check existing/archived records inside the transaction. Advance and manual milestone invoices serialize quote → project → milestone; new `InvoiceNumbering` chooses max historical suffix including archives, not count+1. Nullable unique `invoices.project_milestone_id` migration refuses historical duplicates for Accounts review, never deletes them. Manual milestone invoices remain **draft-only** (D-88); advance setting unchanged.
- Frozen advance PDFs return unchanged on repeat freeze; missing/partial/hash-mismatched documents require reviewed repair. Failed PDF writes roll back the database effect. Quote mail and staff quote alerts queue after the **outermost** commit; real database-queue commit/rollback regressions added. No durable outbox claim; orphan files after a successful write followed by DB rollback remain a reviewed-recovery gate.
- **Evidence:** 34 new tests /134 assertions; complete suite **656 passed /4,202 assertions /1 existing sandbox skip**, 178.99s (`/tmp/b04-full-tests-final.log`); Larastan6 no errors; Pint15 PHP paths; Vite build passes (existing ApexCharts size warning). Authenticated App Connections200, zero page JS errors, 1440/390/360px no overflow. Cross-site iframe checks **both third-party-cookie-blocked and no-cookie scenarios pass**, including hub search/MCP setup/Google dialog. No provider connection/key creation/webhook registration or financial action was performed by UI checks. Broad homepage/browser acceptance was not recertified.
- **Next:** complete coordinated quote-editor/recalculate/approval request+decision/reopen+revise/view/change-request locking and original-proposal/recipient/content fingerprint checks; then MySQL multi-worker races, Accounts UAT and durable delivery recovery. Existing financial fingerprint is not complete recipient/content authorization. Do not mark B04 checked based on these SQLite regressions. B08 broader own/team authority and runtime/provider gates also remain.
- **Publication:** local preview only; nothing pushed/deployed to Hostinger. Closed session cannot publish; start a new coding session for release. Preserve optional-owner-only2FA/confirmed challenges and disabled important Telegram writes. Runtime process reference/QA details are in the session handoff; runtime data/private env must never be copied into Git.

## Previous handoff: B08 Telegram identity and consent safety — 17 Oct 2026 PKT

- Owner requested **start next**. Implemented the next bounded local safety slice: Telegram personal identity, active staff/link checks, header authentication migration, actor/link rate limits and STOP-preservation guards. Details: `docs/16-TELEGRAM-CONSENT-HARDENING.md`. B08 is **not fully accepted**: broader finance own/team permission review and real-runtime evidence remain. B04 quote/send/milestone idempotency was not started in that B08 batch; the current handoff above records its subsequent first slice.
- **Telegram identity:** new `HandleTelegramCommand` action accepts only human private messages with equal positive sender/chat IDs. Group/channel/sender-chat/bot/forwarded/via-bot identities and callbacks/edited messages are ignored; no group inherits a linked user's authority. Links/code use active fresh users and locked code rechecks; new 16-character codes, max 30 minutes, existing valid eight-character codes compatible. Cannot steal a chat already linked to another staff account. `/idea` and `/issue` remain available to active linked staff only; finance/approvals/arbitrary writes remain disabled.
- **Webhook migration:** new stable POST `/webhooks/telegram`, official secret header required. Both Integrations and existing console registration use the secret-free URL. Old secret path requires a matching header by default; explicit temporary `TELEGRAM_ALLOW_LEGACY_WEBHOOK=true` permits no header only on the correct old path, never a wrong header/new-path bypass. Default/example false; no private env edits or credential requests. Re-register after deployment using the existing saved values. Preserve pending updates and request only implemented message updates.
- **Limits:** authenticated global 300/min, sender 30/min, link commands 5/min; hashed sender keys and independent WhatsApp buckets. A new denial test caught Laravel sorting throttle ahead of the custom auth middleware; fixed explicit priority in `bootstrap/app.php` and proved invalid headers cannot exhaust a legitimate sender's bucket. Command business effect + event marker now commit together under event lock; trusted received replay retries after rollback. Reply errors do not repeat committed effects; safe generic logs. Full queued Telegram/reply-outbox recovery remains pending.
- **Personal alerts:** User routing blocks inactive staff and legacy group links; TelegramChannel reloads recipient/link before delivery, honoring unlink/disable/group/mute changes despite stale cached relations. Existing group rows retained, not deleted; re-link in a private chat for personal alerts. Separate group alert authorization is not implemented.
- **Consent:** returning `CaptureLead` cannot OR positive checkbox flags over existing opt-out. `MergeContacts` locks/reloads both contacts in ID order, propagates either side's suppression and keeps both consent flags false. `SaveContact` locks/reloads and refuses ordinary re-consent edits. Inbound matching locks contact; repeated STOP preserves suppression timestamp; START/returning messages do not erase STOP. Full conversation/media merge and legacy import suppression reconciliation remain separate backlog work.
- **Send boundary:** SendConversationMessage reloads current active staff permissions, conversation and contact suppression before provider calls; opted-out/missing contacts blocked, templates need affirmative WhatsApp consent. Internal notes remain allowed without a send. Existing inbox toast truthfully describes blocked sends. No provider HTTP call can recall an already in-flight message; outbox/cancellation, marketing/template-category policy and explicit evidence-backed resubscription remain pending.
- **Final validation:** complete fresh PHP suite **622 passed / 4068 assertions / 1 skipped**, 149.15s, `/tmp/a0-b08-full-tests-final.log`; **61 new regression cases**. Larastan level 6 no errors (`/tmp/a0-b08-phpstan-final.log`); Pint on 23 PHP paths. Existing owner-only optional 2FA/confirmed challenges, first-A0 finance/WhatsApp, Google/MCP and website cases remain green. One installer/database-driver test is sandbox-skipped. Providers faked; in-memory SQLite; no real provider/MySQL concurrency/production/UAT claim. No browser rerun or Blade/CSS/JS change; earlier homepage browser expectations remain open.
- **Release/next:** no schema changes in B08, but preceding WhatsApp recovery migration still required for the combined release. Existing user/preview database not reset, credentials unchanged, no remote operations/deployment. New coding session required to publish. Next B04 idempotency and remaining B01/MySQL/Accounts/permission gates, followed by durable events/outbox/approval/dashboard foundations. Telegram synchronous processing/reply-loss-on-crash remains explicitly documented; do not claim B07 complete for Telegram.

## Previous handoff: A0 finance and WhatsApp safety implementation — 17 Oct 2026 PKT

- **Owner authorized local implementation after receiving the audit:** start in the recommended order. The prior audit-only scope below is historical. This first bounded batch implements parts of B02/B03/B05/B06/B07/B08; it is not completion of all 75 packages. Closed session still cannot push, open a PR or deploy; continue publication/runtime acceptance in a new coding session.
- **Finance:** `AllocatePayment` reloads/locks the payment and locks invoice candidates in ID order; rejects cross-client, nonpayable/credit-note/non-PKR targets, project/account mismatches, inactive accounts and invalid/excess explicit amounts. PKR-only payment ledger remains explicit; no new multicurrency column. `RecordPayment` honors a strict explicit map (remainder is credit); an invoice preference instead follows existing D-85: own invoice → eligible oldest debts → credit. Selected project/account filters constrain eligible excess. No allocation/credit-adjustment UI or mutation of already-receipted allocations is exposed.
- **Receipt/claims:** verify/reject reload and lock current state; stale retries cannot replace a receipt or reverse a decided claim. Current active-user/finance-permission checks added at action boundaries. Receipt storage failure rolls back confirmation; Laravel 13 rollback callbacks remove generated files on an enclosing rollback. Client mail/team notifications queue after commit. Proof submission reloads payable invoice state. Staff/public proof money parsing uses integer `Money`, rejects scientific/overprecise/unbounded input. New-payment request idempotency keys, hard-crash file cleanup and durable delivery outbox remain pending.
- **WhatsApp:** signed intake persists every entry/change/message/status with its exact normalized payload before enqueue. `QueueWhatsAppEvent` / `ProcessWhatsAppEvent` process one trusted row under lock; domain write + outcome commit together. Exact-ID legacy failed replay and already-written message recovery supported; duplicate jobs do not duplicate effects. Five-minute enqueue lease; minutely `woodex:retry-whatsapp-webhooks` recovers received/failed events; backoff 1/2/4/8 minutes, max five processing attempts. Reviewed `--event=EVENT_ID` console redrive only; processed/ignored/untrusted/other-provider rows refused. Generic safe diagnostic errors/logs, no provider exception bodies. Early delivery statuses wait for outbound rows; late failures do not downgrade delivered/read. Delayed inbound uses provider time for the 24-hour window and preserves newer previews.
- **Schema/runtime handoff:** new `2026_10_17_000300_add_whatsapp_webhook_recovery.php` adds queue/retry timestamps + index. Migration tested in isolated SQLite; **not run on production or the existing preview/customer database**. Production must use the database queue and working existing minute cron; sync remains synchronous. Apply the migration before the changed webhook code is enabled; verify real drain/timeouts. No new cron/Redis/daemon, no credential edits or real provider calls. Existing Telegram/MCP/Google work preserved.
- **Final validation:** freshly reran the complete PHP suite: **561 passed / 3774 assertions / 1 skipped**, 148.07 seconds, `/tmp/a0-full-tests-final.log`. Includes **50 new desired-behavior cases** (30 finance integrity + 20 WhatsApp recovery). The skipped installer/database-driver test is sandbox-specific. Existing Node MCP adapter tests **3/3 passed**; Larastan level 6 no errors; Pint run on all 21 batch PHP paths. Isolated real Laravel database queue exercised for acknowledgment/worker and finance/webhook rollback/commit boundaries; providers faked. No browser suite rerun; two earlier homepage browser form expectations remain open. No MySQL concurrent verification proof or production/UAT claim.
- **Details and next work:** `docs/15-A0-SAFETY-IMPLEMENTATION.md` includes exact semantics, retry/redrive operations, schema/rollout and remaining gates. Checkboxes in doc 14 still mean full package acceptance. Next complete Telegram sender/group/active identity and consent review (B08), quote/milestone idempotency (B04), production-like MySQL/Accounts acceptance and actual runtime checks (B01–B07), then common events/outbox/approval/dashboard foundations. Historical batches falsely marked processed are not bulk reset: require reviewed reconciliation. Existing media downloader remains a separate recovery/validation/file-cleanup follow-up.
- Approval-gated important Telegram writes, D-88 manual milestone draft and optional-owner-only 2FA remain unchanged. No master agent/specialist dashboards/general sequences enabled. No commit/push/deployment in this batch.

## Previous handoff: OS audit + master automation plan — 17 Oct 2026 PKT

- Owner selected **audit/plan only**, approval-gated Telegram writes, compare Hostinger vs optional worker, and balanced shared controls before specialist agents. No new business feature/provider configuration/deployment in this task; preserve existing local Telegram/MCP/Google work.
- Deliverables: `docs/12-OPERATING-SYSTEM-AUDIT.md` (P1–P3 traceability, all integration/automation inventory, evidence/unknowns), `docs/13-MASTER-AGENT-AUTOMATION-PLAN.md` (master/tools/agents/full funnel/approvals/separate dashboards/hosting comparison), `docs/14-AUTOMATION-COMPLETION-BACKLOG.md` (**75** dependency-linked work packages), and `docs/audit-evidence/2026-10-17-results.md` + probe source snapshot. Documentation index updated.
- **Do not repeat historical “P0–P3 complete / P4 complete” as PRD acceptance.** P1 foundation substantial; P2 quote follow-up/expiry/options/import and P3 manual/variation/credit/aging/reports/financial import remain gaps. Project-workspace phase labels conflicted with original P4 Conversations. Website chat/public slot booking explicitly off; general sequences/AI/master runtime/agent dashboards not implemented.
- **New isolated observations: 7 probes, 22 assertions**, fake providers and seeded in-memory SQLite, not production and no preview DB restore. F01 cross-client allocation (reproduced through Livewire); F02 allocation resurrects void invoice; F03 stale verification replaces receipt identity (one allocation, no concurrent MySQL test); F04 explicit allocation map ignored; F05 targeted excess credit conflicts with D-85; F06 batched inbound loses second message and failed-event replay does not repair; F07 batched statuses mark unprocessed second message processed. Full details/reproduction in doc 12 and evidence files. These probes assert observed defects and are **not repair regression expectations**.
- Recommendations: new coding session starts **B01–B08** (runtime truth, finance integrity, webhook batching/recovery, sender/permission/consent boundaries), then common event/outbox/sequence/approval controls and dashboards; complete sales/report/import gaps in parallel. Reuse domain actions; no replacement CRM or unofficial WhatsApp business-number transport. Preserve D-88 manual milestone invoice draft and optional-owner-only 2FA choice.
- Public production check reached Woodex sign-in at `/admin/login` via `/admin`; no authenticated inspection. Deployed revision/migrations/cron/provider/business acceptance remain unknown. Already saved private Telegram replacements must be verified as loaded, not requested again. Real Google registration/consent remains pending.
- Prior **511 passed / 3504 assertions / 1 skipped** remains the earlier local baseline, not rerun for this docs-only audit. Separately reran existing PaymentsTest + ConversationsTest: **24 passed / 124 assertions**; the uncovered negative/batch cases still reproduced in the seven probes. Two known homepage browser form expectations still pending. New probe result must not be presented as a new 518-test full-suite run. Validated all 75 unique backlog IDs, dependency references/no cycles, local document links and Markdown table/fence structure.
- Reference decisions: PHP Telegram Bot core README says development on hold; retain existing gateway unless separately justified. WhatsApp on-prem scripts archived/deprecated; use existing official Cloud API. OpenWA README explicitly warns of unofficial/ban risk; wacrm is useful UX inspiration but Next.js/Supabase, not a Laravel module. Older Woodex feature guide describes more flows, but documentation is not migrated implementation/live proof. Official Meta pricing says per-message began July 2025 with October 2026 changes; use effective-date delivered-usage rates, not original stale examples.
- No remote push/PR/deployment in this closed session. This audit is not authorization to enable agents or publish changes. Next batch requires the owner's implementation/deployment approval in a new coding session.

## Google connections and staff sign-in follow-up — 17 Oct 2026 PKT

- Owner chose **Google first**, **both business connection and staff login**, and **not registered yet**. Added a
  reference-inspired Connect Google dialog, permission summary, collapsible setup checklist, non-secret callback
  URI, server configuration reload and an explicit disabled setup gate. No editable client-secret/auth-base-URI
  fields. Configured authorization navigates to Google's official page outside embedded frames and returns here.
- Business connection is owner/password-confirmed and identity-only (`openid email profile`): encrypted hidden
  access/refresh tokens, account/state/scopes/last-check display, explicit refresh/check and provider revocation.
  Failed checks require reconnect; failed revocation keeps a disabled pending record rather than pretending to
  disconnect. No Drive/Sheets/Gmail/Calendar/Meet APIs or background sync are enabled by basic Google consent.
- Staff sign-in is separate: explicit password-confirmed linking while signed in, immutable Google subject,
  verified email, no saved staff provider tokens, no auto-registration/email matching/role changes, active-user
  check and existing Fortify confirmed TOTP challenge (including owners). Personal unlink requires the password.
  Google sign-in is disabled by default; password login stays available. Mandatory staff enrolment is preserved.
- Session-bound one-use 10-minute state + PKCE S256; fixed HTTPS provider endpoints; callback intent/actor checks;
  generic redacted failures; metadata-only lifecycle audit; no-store/no-referrer OAuth responses. Separate named
  IP throttles avoid sharing the counter between nested limits. No new Composer packages or server daemon.
- New migration creates `google_connections` + `google_identities`. `.env.example` contains empty Google client
  placeholders only. No actual environment credentials were changed or requested. D-20/D-47 and schema docs
  updated; full registration, private environment and acceptance guide: `docs/GOOGLE-OAUTH-SETUP.md`.
- **Verification:** full Pest **511 passed / 3,504 assertions, 1 skipped**; Google-specific **33 / 186**; Node MCP
  **3/3**; changed PHP Pint + Larastan level 6 pass; production assets rebuilt (existing chart-size warning only).
  Google setup UI passes at 1440/390/360px, Escape, hidden unconfigured login, and personal setup guidance without
  making a Google request. Both iframe-cookie scenarios include the Google dialog and pass.
- General browser check rerun against an **isolated app/DB copy** so quote/PDF/client-acceptance checks cannot
  overwrite edits in the user's active preview. Admin/mobile assertions pass; the same two unrelated expectations
  for a missing inline v1-homepage form fail. Browser tooling now accepts BROWSER_DB_FILE/BROWSER_DB_COPY overrides.
- **Not live-connected or published.** An OAuth app is not registered, so real Google consent, refresh/revocation
  and staff federated sign-in remain deployment acceptance steps. Session is closed after PR #2; all follow-ups
  remain local. Start a new coding session to publish, apply Database update and configure Google privately.

## Current local follow-up — 17 Oct 2026 PKT

- **Integrations hub + read-only MCP foundation implemented locally.** Search/category filters, 12 explicitly
  Planned provider cards with requirements dialogs, existing service controls, and an MCP setup panel. The owner
  selected business connections, Woodex tools exposed to AI clients, and a local adapter alongside Hostinger PHP.
- Owner-only, password-confirmed key creation/revocation actions are transactional with metadata-only audits.
  SHA-256-only token storage, 1–90 day expiry, scopes, one-response plaintext display, current active-user checks,
  existing leads/quotes permissions and record visibility, pagination, production HTTPS and IP/key rate limits.
  No writes, sends, shell/filesystem access, provider OAuth or general-purpose URL proxy. D-47 explicitly records
  the narrow generated-first-party-key extension; provider secrets remain server-side and never appear in the hub.
- `tools/mcp-bridge` uses pinned official MCP v2 packages, runs locally over stdio and exposes status, lead summaries
  and quote summaries. See `docs/INTEGRATIONS-MCP-PLAN.md` and the adapter README for setup and roadmap. No Node
  daemon on Hostinger. Vendor-client installation is not verified; exact Hermes product remains unspecified.
- **Verification:** full Pest **478 passed / 3,318 assertions, 1 skipped**; MCP subset **18 / 75**; Node **3/3**
  including real stdio protocol calls; changed-PHP Pint and Larastan level 6 pass; production Vite assets rebuilt;
  runtime npm audit reports no vulnerabilities. Browser acceptance creates a demo key and makes all three actual
  MCP calls against Laravel, checks revocation, search/requirements/Telegram diagnostics and responsive layout.
  Both iframe-cookie modes also test hub search and MCP setup. No real keys are captured in screenshots/logs.
- General `browser-check.mjs` was rerun: admin navigation, chart/sidebar geometry, quote editing/sending/PDF/client
  acceptance and mobile checks pass; its two pre-existing public-homepage form expectations still fail because
  the restored v1 home has no inline enquiry form. This is not an all-browser-suite-green claim.
- Previous secret-free Telegram configuration/cache repair is retained. Live Telegram delivery and Hostinger
  deployment remain unverified; saved private credentials have not been changed or requested again.
- **LOCAL ONLY:** this session is closed following PR #2. No GitHub operations, push or deployment attempted for
  these follow-ups. Start a new coding session to publish; then apply Database update for `mcp_access_tokens`.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the original site went live and its forms were wired)

- **woodex.com.pk now serves the original v1 site, file for file (owner's decision): 142 pages copied verbatim**
  into `platform/public/**` — same folders, same paths, same HTML, CSS, JavaScript and images. Nothing
  redesigned, nothing re-typed, nothing rebuilt. The CMS copy of the same 143 pages stays in the database as
  editable content, but it is **not** what the public sees now; how edits go live is the owner's call, later.
- **The two addresses the old JavaScript calls are answered by Woodex OS, so the pages needed no change at all**
  (`LegacySiteBridgeController`, routes in `routes/api.php`):
  - `GET /api/forms.php` → `{"ok":true,"turnstile":""}` (no captcha box is drawn; honeypot + rate limit instead).
  - `POST /api/forms.php` → the enquiry is validated and written through **CaptureLead**, so it lands on the same
    pipeline as every other lead, with its source set from the form that sent it (`whatsapp`, `estimator`,
    `booking`, otherwise website form) and every extra field kept in `meta.fields`. Replies `{"ok":true,"id"}`.
  - `action=wa_click` → `{"ok":true}`: a WhatsApp tap is a ping, not a lead.
  - `action=book_create` → **kept as a lead with source `booking`**, then answered `ok`, which shows the page's own
    "booking request received, our team will confirm on WhatsApp" panel. The slot is never promised.
  - `action=book_cfg` / `book_slots` → booking is **off** (`{"ok":true,"on":false,"types":[]}`), so `/book-a-visit/`
    shows its own pre-written "Online booking is paused" panel. Woodex OS has no diaries yet (PRD §5.6).
  - `GET|POST /api/chat.php` → `{"ok":true,"on":false}`: the widget stays out of the way until website chat (P4).
- **Rate limiting was split** so a page view cannot spend the enquiry budget: `throttle:public-site` (120/min)
  guards all three bridge routes, and only writing a lead spends the 5-per-minute / 50-per-day budget, which is
  now counted inside the controller (`legacy-leads-minute`, `legacy-leads-day`).
- **Verification:** Pest **431 passed**, 3 098 assertions (12 new in `LegacySiteBridgeTest`, which is the contract
  the pages depend on) · Larastan level 6 clean · Pint clean on 460 files · every endpoint checked with curl
  (enquiry → lead, honeypot → silent ok, invalid → the visitor's message, WhatsApp tap, booking off, chat off) ·
  preview checked on port 8000: `/`, `/kitchen-design/`, `/contact/` answer 200 and every asset the pages call
  for answers 200. No browser in this sandbox, so the form was verified by contract, not by clicking.
- **Next, in the owner's order:** the v1 → v2 conversion plan. Questions first, then the plan, then the work.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the complete old website went into the CMS)
- **The complete frontend v1 is the website now: 143 pages, 0 redirects (owner's decision, D-102).** The old site's
  54 specialist URLs used to 301 to the nearest discipline; they are pages now, editable in Website -> Pages with
  the Woodex builder. `database/data/legacy-website.php` holds 143 pages and an empty redirect list.
- **How it gets to the server in one step:** `woodex:reset-website` (new command) empties the public website —
  pages, sections, photographs, the 301s and the import bookkeeping — and
  `2026_10_17_000200_reload_the_complete_website.php` empties and reloads `database/data/website-bundle` in the
  same Database update, so woodex.com.pk is never blank. It records the bundle's checksum, so a later update with
  the same bundle changes nothing and pages the studio has since edited survive (D-104).
- **The four content fixes are actions now** (`app/Domain/Website/Actions/`, D-103), because an import brings the
  old pages back without them: AddMissingCallToActions, LinkCityPagesToAreas, LinkEmptyServiceUrls (123 empty
  discipline links filled in — the old site listed Furniture with no link at all) and RepairImportedCopy (the
  privacy page no longer promises Supabase and Vercel).
- **Two bugs found doing it:** a reset did not clear the *image* bookkeeping, so every imported block pointed at a
  deleted photograph (ImportLegacyMedia now checks a ref before believing it); and `/furniture/` carried the
  wardrobe page's title and description — it has its own words now, not yet its own photographs.
- **The audit after the reload:** 143 pages · 0 broken links · 0 orphans · min 489 words, median 815 · every page
  has one h1, JSON-LD, alt text and dimensions, and a closing · 5 pages still carry no photograph
  (`/services/`, `/projects/`, `/book-a-visit/`, `/estimator/`, `/privacy/`) · sitemap 143 URLs.
- **Verification:** Pest **419 passed** (6 new: the four actions and the reset command, which asks before it
  deletes and keeps media and redirects when asked) · Larastan level 6 clean · Pint clean on 458 files · admin
  smoke 34 pages · preview checked (frontend + dashboard) before pushing.
- **docs/11 is the v2 plan** the owner asked for: SEO first (the 54 specialist pages have to stop reading like
  copies of the six disciplines), then the section library and page patterns, then the design-token theme builder.
  Item 1 is still the owner's one click: **woodex.com.pk -> /admin -> System -> Database update.**

## Resume here (project memory, saved 17 Oct 2026 PKT, after the 89-page launch audit)
- **All 89 pages were audited page by page** (docs/10): every page rendered through the app's HTTP kernel, 704
  internal links followed. **Sound:** 89/89 answer 200, **0 broken links**, one `<h1>` each, titles ≤ 65 chars,
  descriptions 120–165 chars with no duplicates, JSON-LD on every page, every image has alt + width/height,
  `last_reviewed` set, **no thin content** (min 489 words, median 929, max 1 738).
- **Three defects found and fixed in this commit:**
  1. **The site had no navigation.** `navigation_items` was empty, so the header and the footer were blank and
     27 of 89 pages (home, about, projects, insights, privacy, all 12 cities, all 8 Lahore areas) had no link
     pointing at them — only the sitemap. `Menus::defaults()` now supplies a header and a footer until the studio
     fills in Website → Menus, and `Menus::cityLinks()` puts **Where we work** (the 12 cities) in the footer.
     Orphans **27 → 0**; average internal links per page **2.2 → 22**.
  2. **40 pages ended on a full stop** — every cost guide had no form and no next step. `..._130000` gives every
     published page without one an enquiry-form block with a heading written for its kind of page. **40 → 0**.
  3. **The 8 Lahore area pages were unreachable.** `..._130100` ends each city page with **"Areas of X we work
     in"**, listing its areas (Lahore → all 8; the other cities have none yet, so nothing was invented).
- **`tools/website/audit.php`** (new): renders every published page, follows every internal link, prints a summary
  and exits non-zero on a broken link. Run it after every content batch: `php tools/website/audit.php`.
- **Still open (owner):** 5 pages carry no photograph (`/services/`, `/projects/`, `/book-a-visit/`,
  `/estimator/`, `/privacy/`) — the library holds 74 imported images with no names, so nothing was picked at
  random; and **woodex.com.pk → /admin → System → Database update** still needs one click to publish the 89
  pages and create the conversation tables.
- **Verification:** Pest **413 passed**, 2 878 assertions (2 new navigation tests) · Larastan level 6 clean ·
  Pint clean on 450 files · audit re-run clean · preview checked (frontend + dashboard) before committing.

## Resume here (project memory, saved 16 Oct 2026 PKT, after P4: conversations part 1 - the inbox)
- **P4 part 1 — conversations: the pipes and the inbox (this commit, CNV-001/002/005, WAP-001…004/006):**
  - **Admin -> Inbox** (`/admin/inbox`, `inbox.view` to read, `inbox.reply` to answer): conversations on the left,
    the thread on the right with delivery ticks, assign, close/reopen, and internal notes that never leave.
  - **WhatsApp Cloud API** behind `WhatsAppGateway` (`app/Integrations/WhatsApp/`): send text and templates, fetch
    inbound media. Credentials come from the server `.env` (`WHATSAPP_TOKEN`, `_PHONE_NUMBER_ID`, `_APP_SECRET`,
    `_VERIFY_TOKEN`, `_WABA_ID`) — never the settings table — and every call is written to the integration log.
  - **Webhook** `/webhooks/whatsapp`: Meta's `GET` handshake, then `X-Hub-Signature-256` on every `POST`; each
    event is stored once in `webhook_events`, so a replay costs one insert and changes nothing.
  - **A client's message does the work**: it is matched to a contact by number (a stranger becomes one), opens or
    reopens a conversation, and reopens Meta's 24-hour window. Outside the window the composer refuses free-form
    text: only an approved template may go out. `STOP` / `UNSUBSCRIBE` / بند کریں are heard at once.
  - A failed send is stored as a message with status *failed* and Meta's error beside it — never a silent lie.
- **Verification of this commit:** Pest **412 passed, 2 867 assertions** (15 new in `tests/Feature/ConversationsTest.php`:
  the handshake, a forged signature, an inbound message becoming a contact and a conversation, a second message
  joining the same thread, a replayed message, opt-out, a closed thread reopening, a reply inside the window, a
  refusal outside it, a failed send, an internal note, delivered/read receipts that never move backwards, media
  stored on our side, and the two permission checks) - Larastan level 6 clean - Pint clean - PHP lint clean -
  smoke **34 admin pages** - the website's 89 pages still answer 200.
- **Still to come in P4:** the website chat widget + public chat API (CNV-001/008), the template manager and
  broadcasts (WAP-005/007), the usage and cost meter (WAP-008), canned replies (CNV-004), the AI bot (BOT-001…010),
  sequences (AUT-001/002/005/006) and broadcasting with a polling fallback (CNV-006).

## Where we were (project memory, saved 16 Oct 2026 PKT, after P4 + the full website going live in the CMS)
- **Where we are:** P0-P3 are done and live; **P4 is complete**: the project workspace (part 1) and the client
  portal, handover and case-study draft (part 2). **woodex.com.pk runs this branch** — every push redeploys it.
- **The demo home page is gone; the whole website is the CMS (this commit, D-93…D-95):**
  - `HomeController` + `resources/views/site/home.blade.php` are **deleted**. The home page is the CMS page with
    the slug `/`, served by the `SiteRouter` like every other page: what the studio edits in Website -> Pages is
    what visitors get. No second template to keep in step.
  - **All 89 pages are published** (`database/migrations/2026_10_16_110000_publish_imported_website_pages.php`):
    the home page, `/insights/` and its 43 guides, `/projects/` and its 6 studies, 12 city pages and the 8 Lahore
    area pages. Pages without a single visible block stay drafts.
  - The home page's imported "Tell us about your space" closing was text without a form; it is now an **enquiry
    form block** (`..._110010_add_enquiry_form_to_home.php`), keeping the words the import brought.
  - **Two live bugs fixed:** `public/robots.txt` was a static `Disallow: /` — the whole website was being kept
    out of Google (it is now answered per domain: the admin says no, the website says yes and points at the
    sitemap); and `public/.htaccess` **stripped the trailing slash** from every URL, which fought the SiteRouter
    that puts it back — two redirects for every page.
  - New settings properties now carry defaults, so a pending Database update can never 500 the public site.
  - A signed-in member of staff no longer fills the page cache: whoever opened a page first used to decide
    whether everybody else was counted in analytics for a day (`PageCache`).
- **P4 part 2 — the client's side (this commit, PRJ-006…PRJ-008, D-90…D-92):**
  - **Client portal** `/p/{public_id}` (signed, 120 days): where the job stands, the diary with its photographs,
    the drawings we chose to share, invoices (each opens its own signed invoice page), and the receipts.
  - **No client password**: a client who has lost the link asks for six digits at `/portal` with the phone number
    or email they booked with; the code is emailed, works once and dies in 15 minutes.
  - **Handover**: the checklist starts from Settings → Sales, the team ticks it and adds lines, the client signs
    with their name on the portal — and the job closes itself (stage completed, progress 100%).
  - **Review request**: `woodex:review-requests` (daily 10:30 in the cron) emails the Google review link a few
    days after the signature, once the owner has pasted their link into Settings → Business.
  - **Case study**: with the client's consent, a finished job becomes a **draft** portfolio page in the CMS —
    hero, the story from the shared diary, a gallery copied into the website's media library, and the facts.
  - The project page gained a **Client portal** card (link, copy, WhatsApp, email the link, review status) and a
    **Case study** card, and every file now has a *Private / On portal* toggle.
- **Verification of this commit:** Pest **397 passed, 2 888 assertions** (11 new in `tests/Feature/ClientPortalTest.php`:
  what the client sees and does not, the document that only appears once shared, the cancelled project, the
  checklist, signing the handover, the six-digit code, the review email, the case-study draft, the portal link
  email and the two "nothing is sent" guards) · Larastan level 6 clean · Pint clean · PHP lint clean (425 files) ·
  smoke 33 admin pages · `/portal` answers 200.
- **P4 part 1 — the project workspace (commit 90c8781, PRJ-002…PRJ-005, D-87…D-89):**
  - **Admin → Projects** — every job with its stage, status, progress, next milestone, value, invoiced/collected
    and target date (late jobs first); filter by stage, status and PM.
  - **Admin → Projects → Board** — the same jobs as cards, one column per stage (planning → design → procurement
    → execution → finishing → handover → completed). Drag a card to move the work on; *Completed* closes the job.
    A move tells the client one line about the stage (email now, WhatsApp with the conversations phase) and is
    switchable in Settings → Sales.
  - **The project page** — the money (value / invoiced / collected / outstanding), the milestones (tick one to
    mark it reached → progress moves; *Raise invoice* creates a **draft** invoice for it), the **site diary**
    (text + photographs + progress, optionally tied to a milestone, shared with the client on purpose), the files
    (drawings, approvals) and the internal notes.
  - The quotation page's project card now links straight to the project.
- **Verification of this commit:** Pest **386 passed, 2 811 assertions** (9 new in `tests/Feature/ProjectsTest.php`:
  the project that acceptance opens, a board move and what the client is told, closing the job, an internal note
  staying internal until it is shared, photographs + progress + the timeline, the milestone invoice raised once as
  a draft, raising it from the screen, `projects.manage` vs `projects.view`, and the screens) · Larastan level 6
  clean · Pint clean · PHP lint clean (407 files) · smoke 33 admin pages incl. both new ones.
- **P3 — get paid (commit e982c91, INV-005/006, D-84…D-86):** money reaches the ledger two ways and both land in the
  same place.
  - **Record payment** (Admin → Sales → Payments): the studio saying the money is in the bank — verified at once,
    receipt `RC-2026-0001` written to storage, mailed to the client, the team notified on Telegram.
  - **"I have paid"** on the client's invoice page (`/i/{public_id}`, signed for 90 days, no login): the client's
    amount, date, method, bank reference and an optional screenshot → **Pending verification**, nothing allocated.
    Accounts presses **Confirm** (allocates + receipt + mail) or **Send back** with a reason (mail with the reason).
  - Money settles **its own invoice first, then the oldest open invoices of that client first**, and an overpayment
    stays as unallocated client credit (`AllocatePayment`, D-85).
  - The client invoice page shows the frozen invoice, the bank account to pay into, the PDF link and a WhatsApp
    share; it 404s for a draft or a paid/void invoice. Every invoice row in the quote builder now has a
    **Client link** and a **WhatsApp** link.
  - Only `payments.verify` (accounts) may confirm or send back; a payment can only be acted on while it waits.
- **Verification of this commit:** Pest **377 passed, 2 745 assertions** (9 new in `tests/Feature/PaymentsTest.php`:
  record + receipt + mail, spreading over two invoices with the remainder as credit, client proof, confirm, send
  back, the client page, the draft/paid guards, permissions and the Livewire screen) · Larastan level 6 clean ·
  Pint clean · PHP lint clean (392 files) · smoke 31 admin pages green including `/admin/payments`.
- **Next (your call):** the **conversations phase** (docs/03 P4: WhatsApp Cloud API, the unified inbox, the chat
  bot, sequences) — that is what puts the portal link and the updates on WhatsApp instead of email; or the
  remaining P2 items (QUO-012 reminders/expiry, QUO-019 quote options, v2.1 quote import); or **website batch 3**
  (publish the 12 cities + 8 Lahore areas + `/insights/` + `/projects/`, humanize home and the six services).
  Tell me which and I start it.
- **Website (unchanged, still open):** 12 pages live from the CMS, **76 drafts waiting for a human**
  (12 cities, 8 Lahore areas, 48 insights, 6 projects), 54 old service URLs 301'd, parity test green.
- **Open items P-01…P-16** (docs/07 section C): P-06 (rotate credentials) blocks go-live; P-12 (real project
  photographs) blocks the case studies; P-15 (GA4/GTM/Clarity IDs into Website → Settings) blocks tracking;
  P-16 (email + hours in Settings) should be done now.
- **Rules to follow:** docs/07 (D-01…D-92), docs/09 and platform/AGENTS.md. The plan and specs are in docs/00-09.
- **Code:** everything is on branch `arena/8be318a2-woodexinterior` and pushed. `master` is still d77016b; merge
  through a pull request when the owner approves.
- **Preview (AI sandbox only):** after a VM reset run `sh tools/sandbox/setup.sh` (~3-4 min). Then start
  `PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs` from `/tmp/woodex-run`. Checks: `smoke.sh`,
  `browser-check.mjs`, `home-check.mjs`, `page-check.mjs`, `iframe-check.mjs`, `installer-check.mjs`
  (tools/sandbox/README.md). The preview's database holds the imported pages; `/tmp/ref/frontend-v1` is the
  read-only clone of the old site and `/tmp/ref` is never pushed to.
- **Preview data:** demo seed (26 leads, 5 users, 4 quotations) + the 89 imported pages (12 published).
  Owner login `owner@woodex.test` / `Woodex-Preview-2026`. The preview database lives in `/tmp` and is rebuilt
  from the seeders after a VM reset (the pages then come from the committed bundle via `woodex:import-website`).

### P4 part 2 delivered: the client portal, handover and case study (16 Oct 2026, D-90…D-92)
- **Portal** (`/p/{project:public_id}`, signed 120 days): `Portal\ProjectPortalController` + `site/project-portal.blade.php`
  — progress, the diary with its photographs, shared documents, invoices, receipts and the handover the client signs.
  Files are streamed per route; a photograph of a shared update is client-visible by definition, a drawing only
  when the team flips it to *On portal* in the admin.
- **Code instead of a password** (`/portal`): `RequestPortalAccess` / `OpenPortalWithCode`, `portal_access_codes`,
  a six-digit code by email (`PortalCodeMail`), one use, 15 minutes, five tries. Answer is the same either way.
- **Handover** (PRJ-007): `project_handover_items` + `sales.handover_checklist`, `StartHandover` and
  `AcceptHandover` (the client's typed name is the signature; the job closes itself), and `RequestClientReviews`
  run daily by `woodex:review-requests` with `business.google_review_url`.
- **Case study** (PRJ-008): `DraftCaseStudy` writes a **draft** `Page` (type project: hero, story, gallery, specs),
  copies the shared photographs into the website media library, and is idempotent — only with the consent tick on.
- `p` and `portal` are reserved in `SiteRouter` so the CMS 404 handler never answers for them.

### P4 part 1 delivered: the project workspace (16 Oct 2026, D-87…D-89)
- **Screens** (`App\Livewire\Admin\Projects`): `ProjectsIndex` (list, filters, money per job), `ProjectBoard`
  (Kanban by stage, drag a card → `moveProject` → `ChangeProjectStage`), `ProjectShow` (the workspace: stage and
  status, the money strip, milestones, the site diary, files, notes).
- **Actions** (`app/Domain/Projects/Actions/`): `ChangeProjectStage` (stage, closes the job at *Completed*, one
  line to the client, a note on the timeline, a bell + Telegram alert to the team), `ReachProjectMilestone`
  (reached_at + progress = the sum of the milestones reached), `RaiseMilestoneInvoice` (one **draft** invoice per
  milestone, numbered `{project code}-{n}`), `PostProjectUpdate` and `ShareProjectUpdate` (the site diary).
- **Model + schema:** `project_updates` (migration `2026_10_16_000800`) with a `photos` media collection;
  `Project` gained `HasAttachments` (files), `updates()`, `company()`, `invoicedMinor()` / `collectedMinor()` /
  `balanceMinor()`, `nextMilestone()` and `isOverdue()`; `project_update` added to the morph map.
- **Settings:** `sales.project_stage_notifies_client` (default on) — Settings → Sales.
- **Policy:** `ProjectPolicy` — `projects.view` to look, `projects.manage` to move, post, share and raise.

### P3 part 1 delivered: payments, proofs and receipts (16 Oct 2026, D-84…D-86)
- **Actions** (`app/Domain/Sales/Actions/`): `RecordPayment` (verified at once, receipt number `RC-{year}-{0001}`
  per year inside the same transaction, mail + Telegram), `AllocatePayment` (own invoice, then oldest due date
  first, remainder unallocated), `SubmitPaymentProof` (client claim, `Pending verification`, proof in the `files`
  media collection, throttled 10/min), `VerifyPayment` (confirm → allocate → receipt → mail), `RejectPayment`
  (reason 5–500 chars → mail). `Money::ofMinor()->format($lakhGrouping)` everywhere; paisa stored as integers.
- **Receipt PDF** (`ReceiptPdf` + `views/pdf/receipt.blade.php`): the studio's letterhead text, receipt number,
  client, the invoices the money settled and how much of each, the method/reference/date, and the amount in words.
- **Client invoice page** (`Portal\InvoicePortalController`, `views/site/invoice.blade.php`): signed `i/{public_id}`
  for 90 days — status banners, invoice/paid/balance, the frozen lines, the bank account to pay into, PDF +
  WhatsApp links, and the proof form. Draft and void invoices 404; a paid or already-reported invoice hides the form.
- **Admin → Sales → Payments** (`Livewire\Admin\Sales\PaymentsIndex`): status chips with counts, search, a
  "record payment" form (client typeahead, one or the oldest open invoices, method, reference, bank account, date,
  notes), and per row: **Proof** (the uploaded file), **Receipt** (PDF), **Confirm**, **Send back** (reason modal).
  A warning counts how many are waiting for accounts.
- **Wiring:** `PaymentPolicy` (view follows `invoices.view`; `payments.record` to record; `payments.verify` to
  confirm or send back, and only while pending) · `payments.invoice_id` migration `2026_10_16_000700` ·
  `Payment` gained `HasAttachments` + `implements HasMedia` · `Invoice::payments()` + `applyPayment()` ·
  `/i` reserved in `SiteRouter` (D-86) · "Client link" and WhatsApp per invoice in the quote builder.

## Website state (batch 2: the import, 16 Oct 2026)
- **Where the website stands:** the CMS (batch 1) and the import of the old site (batch 2) are built and pushed:
  - **Live:** the home page (`/`, still its own Blade page) plus 12 pages from the CMS: the six core services
    (`/interior-design/`, `/fit-out/`, `/renovation/`, `/architecture/`, `/3d-visualization/`, `/furniture/`),
    `/services/`, `/about/`, `/contact/`, `/privacy/`, `/estimator/`, `/book-a-visit/`.
  - **Draft in the admin:** 76 more pages (12 cities, 8 Lahore areas, 48 insights, 6 projects, `/insights/`,
    `/projects/`) - publish them per batch after a person has read them (Admin -> Website -> Pages).
  - **54 old service URLs 301** to the service that answers them, with a hit counter
    (Admin -> Website -> SEO & Redirects).
  - **Parity test green:** `tests/Feature/Website/LegacyUrlParityTest.php` requests all 143 URLs (3.7s).
- **How content reaches the server:** the old HTML lives on a laptop, so `php artisan woodex:export-website`
  writes `platform/database/data/website-bundle` (pages.json + 68 photographs, 9.5 MB, committed), and the
  migration `2026_10_16_000400_import_website_content` calls `woodex:import-website` - the owner's one-click
  "Database update" does the rest. Both importers are idempotent (`legacy_refs`, checksum per slug).
  To re-import from the old HTML: `php artisan woodex:import-legacy --path=/path/to/frontend-v1`
  (`--publish --only=/slug/,...` publishes; without `--publish` everything lands as a draft).
- **Owner to do:** after a deploy, sign in and press **System -> Database update** (it adds the new tables such as
  `project_updates`, `project_handover_items` and `portal_access_codes`, and all 89 pages); add the **cron line**
  shown by the installer (it now also runs the daily review request); paste the **Google review link** into
  Settings -> Business; set up 2FA.
- **Next (batch 3):** publish the 12 cities + 8 Lahore areas + `/insights/` + `/projects/` after reading them, and
    humanize home and the six services (docs/09 section 9). Then batch 4 (48 insights), 5 (projects + tracking +
    `llms.txt` + consent), 6 (estimator and book-a-visit as real tools).

## Hostinger deploy fix (16 Oct 2026, D-56…D-58)
- **What happened (15 Oct, deploy of 3155343):** hPanel's Git deploy copied the repository into the **main domain's**
  `public_html` (its default root directory). `woodex.com.pk` answered 403 (no index file at the repository root),
  the Composer step was skipped (no root `composer.json`), there was no CSS (`public/build` was gitignored) and the
  subdomain still showed Hostinger's default page. Hostinger's Git deploy copies files and runs only Composer.
- **Fix:**
  - Root `.htaccess` → everything to `platform/public`; nothing else is web-reachable.
  - Root `composer.json` → same `require` as `platform/composer.json`, `vendor-dir: platform/vendor` (works with or
    without Composer scripts); `tools/deploy/after-composer.php` clears stale caches.
  - `platform/public/build` committed (rebuild + commit with every CSS/JS change).
  - `.env` + `storage/` in `~/woodex-os/<site>/` when `platform/.env` is absent (`App\Support\Install\SiteState`).
  - Web installer `/install` (setup code from the data folder, two steps, then closed) and System → Database update
    after deploys (`EnsureDatabaseIsCurrent`, `woodex:update`). Health shows database, data folder and build checks.
  - `public/index.php` explains a wrong PHP version or missing packages instead of a blank 500.
  - Seeders only add missing rows (`firstOrCreate`): re-running never undoes the owner's rates/templates/stages.
  - Production forces https links when `APP_URL` is https (CDN-safe); `TRUSTED_PROXIES` for a CDN.
- **Verified:** Pest 256 passed, 1155 assertions (1 skipped in the sandbox only: MySQL needs sockets); Larastan clean; Pint clean; lint;
  smoke (22 pages), browser-check, iframe-check; `installer-check.mjs` on a simulated `~/domains/<site>/public_html`:
  fresh site → installer → wrong code refused → install → `/install` 404 → owner signs in → a new migration sends the
  owner to Database update → Apply → admin works.
- **Not verifiable here:** the real Hostinger Composer step (which command, PHP version, memory) and LiteSpeed's
  `.htaccess` handling. The first deploy log will tell; the preflight page and docs/06 §2.7/§10 cover the failures.

## Current phase: P2 Quotations, part 1 done → part 2 next

### P2 part 1 delivered: quote builder (16 Oct 2026)
- **Quotations list** (QUO-016) at Sales → Quotations: status chips with counts (incl. "Needs follow-up": sent or
  viewed, no reply for 2 days), search by number, title, client, company or phone; line, owner and sort filters;
  value, owner and age columns.
- **New quotation** (QUO-003/006): choose the client (search, or start from an enquiry or contact page), link the open
  enquiry, the line and a template card (16 templates + "Blank"). The number is given on creation: WI-10100+
  interior/project, WF-10050+ furniture. The draft starts with valid-until = today + 15 days and the template's
  terms (or the default execution/design terms), scope, notes and milestone plan.
- **Quote builder** (QUO-003/004/005/015/020) at `/admin/quotes/{id}`:
  - Sections (rename, note, reorder, delete) and lines: description, qty with 3 decimals, unit, rate, line discount
    as % or Rs, optional line, internal cost.
  - Every field saves on blur, and the server recalculates line totals, section subtotals, discount, other
    charges, tax, rounding, total and margin (`QuoteEditor` → `RecalculateQuote`, D-54).
  - Enter in qty/rate/discount adds a line below and focuses it. Lines and sections drag and drop (SortableJS) or
    use the up/down actions.
  - The rate book picker (search + category) copies the item into the quote.
  - Side panel: client, title, validity, quote discount (% or Rs), other charges (label + amount), tax %, payment
    milestones (Execution 50/40/10, Design 75/25 or Custom; amounts always add up to the total), scope of work
    (bullets), terms, client notes and internal notes.
  - A sticky totals bar warns about lines without a quantity or rate and about milestones that are not 100%.
  - Duplicate, Save as template (new or update) and delete draft (recycle bin). Read-only for viewers and for
    quotes that are no longer drafts.
- **Rate book** (QUO-001): list with search, category and hidden/visible filters. Managers (`quotes.catalog`, D-53)
  add, edit and hide items (rate, internal cost, finish, line).
- **Quote templates** (QUO-002): cards with type, line, plan, sections, lines and usage count; "View lines",
  "Use" and edit details (name, description, type, line, plan, scope, terms, notes, hide).
- **Around the CRM:**
  - The lead page has a Quotations card with a "New quotation" link. The contact page and company page list quotes.
  - Global search finds quotation numbers and titles; the recycle bin has a Quotations tab.
  - Quote events appear on the lead and contact timelines.
- **Visibility** (D-53): managers, admins and accounts see every quote. Sales and designers see their own quotes and
  those of their leads.
- **Found by the real-browser check:** Livewire 4's `wire:model.blur` never sends a request (D-55). The builder uses
  `wire:model.live.blur`, and `browser-check.mjs` now edits a quantity, checks the server total and puts it back.

### P2 part 1 verification (16 Oct 2026)
- Pest: 212 tests, 968 assertions, all passing. The 40 new tests include the 42-line Renovation template totalled
  to the paisa against an independent BigDecimal sum, plus discounts, other charges, tax, rounding, milestones,
  moves, locks, duplicate, templates, visibility and the Livewire builder.
- Pint clean · PHP lint clean (269 files) · Larastan level 6: no errors.
- `browser-check.mjs` passes, including the quote builder edit/restore and the phone width; `iframe-check.mjs`
  passes; the smoke test checks 21 admin pages.

### P2 part 2b delivered: project + advance invoice (16 Oct 2026, D-80…D-82)
Accepting a quotation now opens the job and asks for its first payment, in the same transaction as the acceptance:

- **Project (PRJ-001, `OpenProjectFromQuote`)** — code = the quotation number, so the client keeps the number they
  know; value, client, line, site city/area from the lead, PM = the quote owner, and the quotation's payment stages
  copied as project milestones. One project per quotation, however often acceptance is called.
- **Advance invoice (INV-001/004, `RaiseAdvanceInvoice`)** — the milestone with the `on_acceptance` trigger becomes
  the first invoice: 50% on an execution job, 75% on design, whatever the owner set on a custom plan. Number is the
  quotation number with a suffix (`WI-10123-1`), tax keeps the quotation's rate, the bank account is the one for the
  business line (Interior → WOODEX INTERIOR, Furniture → WOODEX FURNITURE), and it lands as **draft** or **issued**
  according to `sales.advance_invoice_mode` (issue by default: never sent without a person saying so). An issued
  invoice is frozen — snapshot + PDF stored with it.
- **Screens** — Sales → Invoices lists every invoice with status, amount, balance and the overdue total; the
  quotation page gained a "Project and invoices" card; the invoice PDF is served to the team from storage.
- **Tests:** 8 new (`tests/Feature/AdvanceInvoiceTest.php`) covering the project, the three plans, idempotency,
  draft mode, bank routing, the list + PDF permissions, the quote page card and tax.

### P2 part 2 plan (items 1–4 and 5 are done: part 2a + 2b, D-60…D-64, D-80…D-82)
1. **Approvals (QUO-007):** discount above the role limit (`discount_limits_bp`) or margin below `min_margin_bp` →
   pending approval; the owner or manager approves in the admin (and later on Telegram, TGM-002). Approvals menu.
2. **Send + versions (QUO-008/013):** an immutable `quote_versions` snapshot and PDF hash on each send; a copyable
   signed link plus email (WhatsApp in P4). Editing a sent quote creates revision R1, R2…; staff see the
   differences.
3. **PDF (QUO-014):** A4 Studio letterhead with dompdf: client block, scope, sections, totals, amount in words,
   milestones, terms, bank details and signatories, page numbers.
4. **Client page (QUO-009/010):** signed, expiring link with a Studio-branded view, PDF download, Accept (name +
   checkbox), Request changes and Ask on WhatsApp. View tracking with first-view and "hot" alerts.
5. **Accept automation (QUO-011): done.** Lead → Won, contact → client, project opened, advance invoice raised, the
   team alerted with the invoice number and amount — all in one transaction. Recording the payment against it and
   sending it to the client are P3.
6. **P2 part 3:** validity reminders and expiry (QUO-012), options A/B (QUO-019) and the v2.1 quotes/templates
   import.

### Preview sign-in fix (16 Oct 2026)
- **Test 1 (00:18 PKT):** the owner signed in as `admin@woodex.com.pk`, which does not exist in the demo data, and
  landed on the public holding page with no error. **Cause:** the preview proxy drops the `Cookie` request header.
  The browser keeps the cookies (beacon `cookieKept=1`), but every request reached Laravel without them and started
  a new session. Even the right password would have bounced back to the sign-in form.
- **Fixes:**
  - A failed sign-in returns to its form with the error (D-51).
  - The preview server keeps each browser's session (cookie memory, D-52).
  - The sign-in page has a demo-account box with a one-click sign-in (preview only).
- **Test 2 (00:43 PKT):** sign-in worked (the session was signed in), but a double click on "Sign in" sent a second
  POST. That request arrived already signed in, and Laravel's "already signed in" redirect goes to the route named
  `home`, which is the public website. Opening the sign-in page again went there too.
  **Fixed:** signed-in users on guest pages go to `admin.dashboard` (`redirectUsersTo`); the auth forms submit
  only once per page (D-51, tested).
- **Test 3 (00:47 PKT):** the owner was in the admin and browsed 10 screens without errors.
- **New check `tools/sandbox/iframe-check.mjs`** reproduces the Arena setup: a cross-site iframe, a proxy without
  Referer, third-party cookies blocked, and a run that drops every cookie. Before each fix it showed exactly what the
  owner saw. Now both runs pass:
  - a wrong password shows the error;
  - a double click sends one request and opens the dashboard;
  - the sign-in page leads back to the admin;
  - `wire:navigate` and a full reload stay signed in.
- The request log shows cookie names and a short browser tag (`ua=Windows/Chrome153`). The beacon reports
  `cookieKept`, `framed` and `storageAccess`.
- **Verification:**
  - Pest: 172 tests, 726 assertions, all passing.
  - Pint clean.
  - `browser-check.mjs` passes; `iframe-check.mjs` passes both runs; smoke test: 17 admin pages return 200.

### Preview fix (16 Oct 2026, after P1 part 2)
- **Holding page unstyled in the owner's browser (reported twice):** the preview emitted absolute asset URLs on a
  guessed preview host; they failed in the real browser while in-sandbox curl passed. All output URLs are now
  root-relative (preview server rewrite + `ASSET_URL=/`), so pages work under any origin (D-36). Confirmed from the
  owner's own browser: CSS and fonts load from the preview origin and JavaScript runs (preview beacon: Alpine started).
- **Lead form invisible without JS:** the whole form sat in `<template x-if>`; it is now plain HTML enhanced by
  Alpine, `x-cloak` added to site.css, submit disabled until Alpine starts (D-49).
- **Dashboard chart drawn twice and overflowing its card; y-axis 0,1,1,2,2,3,3:** init race fixed, charts re-fit to
  their box, whole-number ticks (D-48).
- **Admin content slid in on every page load / `wire:navigate`:** sidebar width is now a CSS variable set before
  first paint (D-48).
- Preview server: gzip/brotli (livewire.js 609 → 121 KB, ApexCharts 567 → 135 KB), request log with start / done /
  ABORTED lines and proxy headers.
- Sandbox tooling moved into the repo (`tools/sandbox/`, D-50) including a real headless-Chromium check.

### P1 part 2 delivered (this branch)
- **Fix: public holding page without styling** (giant logo): the sandbox preview server was rebuilt so asset URLs
  always use the public https origin (no mixed content), static files are served with correct MIME types, and inline
  SVGs (logo, icons) now carry width/height fallbacks so no page can explode while CSS loads (D-36).
- **Pipeline exit test** (CRM-004/005): dragging a card to *Site visit* without a booked visit opens the booking
  modal; cancelling puts the card back; booking moves it (also when dragged back from a later stage).
- **Visit booking everywhere** via one concern (`BooksSiteVisits`) with a **double-booking warning** (same team member,
  overlapping time → "Book anyway").
- **Visit calendar** (CRM-008): week columns and month grid with status colours, previous/next/today, day → week.
- **Staff reminders** (CRM-007/008): `woodex:reminders` every minute → task reminders at `remind_at` (30 min before
  due) and site-visit reminders 24 h and 2 h before, in-app + Telegram, each sent once (D-40).
- **Repeating tasks**: daily, working days (Mon–Sat), weekly, monthly; completing creates the next one (D-40).
- **Companies** (CRM-002): list (search by name/phone/email/NTN, type, sort), company page (details, people with
  link/unlink, enquiries, P2/P3 placeholders), edit, delete; companies in global search.
- **Assignment rules** (CRM-009) at Settings → Lead assignment: ordered rules by line, service, city/area and source;
  role or chosen people; round robin or fewest open leads; workload cap; "working hours only" (after-hours rule
  below it); fallback = least-loaded sales person. **Reassign with a reason** on the lead page (D-37).
- **SLA** (CRM-010, AUT-004): response target per source, **holidays** in Settings → Business, second escalation to
  the final roles (default owner) after the escalation delay (D-38).
- **Lead scoring rules in Settings** (CRM-011): Settings → Lead rules (budget, size, line, timeline, lead type,
  services, cities, per-source points, hot threshold) (D-39).
- **List tools** on Leads (GLB-003): sort, 25/50/100 per page, column chooser, saved views per person (with default
  view), CSV export (`leads.export`, formula-injection safe), bulk assign / move stage / delete (D-43).
- **Notification centre** (GLB-008): header bell with unread count, mark read, deep links, 30 s polling while visible,
  and a full Notifications page (D-45).
- **@mentions** in notes (GLB-005): `@Sara`, `@Sara Khan`, `@sara.khan` notify in-app + Telegram (D-44).
- **Attachments** (GLB-006) on leads and contacts: photos, PDF, DWG/DXF, Excel, Word up to 20 MB, private disk,
  authorised download route, WebP thumbnails for images, timeline entry (D-41).
- **Recycle bin** (GLB-010): delete lead / contact (with its enquiries) / company, restore together; owner-only
  **erase** of personal data for privacy requests (D-42).
- **Integrations hub** (SYS-005) + **integration log viewer** (GLB-018): status cards (Telegram, email, website forms,
  Turnstile, v2.1 database; WhatsApp/Meta/LinkedIn planned), test buttons, connect Telegram webhook (D-47).
- **Data import wizard** (SYS-006): connection check, choose parts, queued dry run, reports with warnings/duplicates
  downloads, real run only after a dry run in the last 24 h + typing IMPORT (D-46).

### Verification (16 Oct 2026, PHP 8.4.25 sandbox, real dependency versions)
- **Pest: 170 tests, 704 assertions, all passing** (133 from part 1 + 37 new).
- **Pint clean · PHP lint clean (246 files) · Larastan level 6: no errors · all Blade views compile.**
- Live preview: 17 admin pages + holding page return 200 as owner; CSS/JS URLs root-relative, `text/css` /
  `text/javascript`. **Real-browser check (headless Chromium) passes:** styles applied, Alpine/Livewire running, no
  failed requests or console errors, one chart inside its card (expanded and collapsed sidebar), no layout slide on
  load or `wire:navigate`, mobile drawer, lead form visible without JS and submitted with JS (reference returned).

### Next (after P1)
1. Activate CI (docs/ci/README.md; Larastan is blocking there), then commit the first `composer.lock`. Waiting for
   the owner: this GitHub connection cannot write `.github/workflows`.
2. Staging on Hostinger: subdomain, PHP 8.4, cron, `woodex:install`, Telegram webhook (docs/06); first import dry run
   from System → Data import against a copy of the v2.1 database (docs/08). Waiting for P-05.
3. P2 Quotations (docs/03 Part B): part 1 done, see above.
4. Deferred from P1 (small): CSV import of leads/contacts with mapping (CAP-009); full alert-rule builder (AUT-003,
   P5); client WhatsApp visit reminders (P4); saved views on Contacts/Companies (the concern is ready).

### P1 part 1 delivered (commit cfd0fd5)
- **Lead page** `admin/leads/{lead}` (CRM-003/006/016, GLB-004): one-tap call outcomes, WhatsApp/email/meeting/note
  logging, next action with presets (1 h, tomorrow, 3 days, next week), stage bar with lost-reason modal, owner
  reassignment, site visit booking, tasks, editable details, score badge and SLA status, full timeline.
- **First-response SLA** (CRM-010, D-31): first outbound attempt stops the timer; New → Contacted only when the client
  was reached; 3 unanswered calls in a row suggest WhatsApp; `woodex:sla-check` every minute flags breaches once and
  alerts owner + escalation roles (in-app + Telegram).
- **Lead scoring** (CRM-011) on capture: budget band, size, service, line, referral/returning, timeline; Hot ≥ 80.
- **Contacts** list + **Contact 360** (CRM-001/012/015): search by phone in any format, client/prospect filter, tags,
  duplicate detection (phone/email/name) and **merge** (moves leads, timeline, tasks, visits; fills blank fields),
  new enquiry for an existing client. Sales users see only their own contacts.
- **Tasks** (CRM-007): mine / whole team, overdue / today / upcoming / no date, complete and reopen, link to a lead
  by reference.
- **Site visits** (CRM-008): agenda (needs update / today / tomorrow / dates), map link, one-tap WhatsApp
  confirmation, confirm / reschedule / no-show / cancel, **outcome form with room measurements** → lead size, a
  "prepare quotation" task due in 48 h and a follow-up call.
- **My Day** (CRM-013): uncontacted leads first (with SLA countdown), overdue follow-ups, later today, today's visits,
  tasks due, unassigned leads for managers.
- **Global search** (GLB-002): Ctrl/⌘ K palette in the header over leads and contacts (name, phone in any format,
  email, reference), keyboard navigation, respects visibility.
- **Users & roles** (SYS-002): invite by email (set-password link), roles with descriptions, edit, deactivate
  (signs out, unlinks Telegram: TGM-007), resend invite; guards: never the last active owner, never yourself, only
  owners grant the owner role.
- **Settings** screen (SYS-001): business identity, contact, working hours/days, social links; sales rules (validity,
  invoice terms, discount limits in %, minimum margin, SLA minutes, lost reasons, budget bands, terms, signatories);
  notifications (Telegram on/off, quiet hours, digest time, escalation roles). Phones normalised to E.164.
- **v2.1 importer** (SYS-006 CRM part, D-32): `php artisan woodex:import-v21 {--dry-run} {--only=*} {--connection=legacy}`
  for users, clients, leads, notes, bookings; idempotent via `legacy_refs`; report + warnings/duplicates CSV.
  Runbook: `docs/08-V21-IMPORT-RUNBOOK.md`.
- Lead sources extended for organic/offline channels (D-33); manual lead form offers them.
- Admin JS: Preline per-plugin import (44 KB instead of ~390 KB, D-35). Navigation: My Day, Contacts, Tasks, Site
  Visits, Settings, Users & Roles enabled; lead links everywhere (list, board, dashboard, Telegram, notifications).
- Demo seeder (local only) adds calls, visits (today/tomorrow/overdue/completed with measurements) and tasks.

### Part 1 verification (15 Oct 2026)
- **Pest: 133 tests, 518 assertions, all passing** (60 from P0 + 73 new: lead page, contacts, tasks/visits/My Day,
  SLA, global search, users/settings, importer against a v2.1-shaped database, value conversions).
- **Pint: clean (210 files). PHP lint: clean.**
- **Larastan level 6: no errors** after enabling `parseModelCastsMethod` (D-34). It found two real bugs while P1 was
  built (a renamed method still called in one path; an untyped schema call).
- Live preview: all 16 admin pages render as owner (dashboard, My Day, leads, lead page, pipeline, contacts,
  contact page, tasks, site visits, settings × 3 tabs, users, health).

## P0 Foundation (done, commit ce819db)
- Laravel 13 + PHP 8.4, Livewire 4 class components, Fortify (login, reset, TOTP 2FA + enforcement), spatie
  permission/activitylog/medialibrary/settings/backup, dompdf, brick/math; Vite 8 + Tailwind 4.3 + Preline 5;
  TailAdmin-style shell with the full navigation (later screens shown disabled with their phase).
- Roles/permissions (PRD §7), settings groups, schema for system/CRM/sales/projects/billing (docs/04), support classes
  (Money, AmountInWords, Phone, Numbering, BusinessHours), CaptureLead + public lead API (throttle, honeypot,
  Turnstile, bridge key), Telegram alerts + account linking + `/idea` `/issue`, dashboard, leads list, pipeline board,
  system health; seeders incl. 16 real quote templates and the rate book.

### Assumptions
- WhatsApp/phone +92 322 4000768, email woodexinterior.pk@gmail.com, hours Mon–Sat 10:00–19:00 (P-01…P-03).
- Asia/Karachi stored as application time zone (no DST in Pakistan).
- Free Preline blocks only until a Pro licence is confirmed (P-04).

### Known issues / risks
- CI not active yet: the Arena GitHub connection cannot write `.github/workflows/` (docs/ci/README.md).
- Importer: v2.1 phone numbers that are not valid Pakistani/international numbers are kept in contact notes; the
  dry-run warnings list them. Budget text that names no amount stays in the lead details.
- Nohemi font commercial licence to be confirmed before the public website launch (P-09).
- Credentials exposed in the old public repository must be rotated before go-live (P-06).

## Security & Telegram repair — 2026-10-16

- Added manual TOTP setup-key fallback, time-sync/app guidance and exact six-digit confirmation validation.
- Telegram linking now checks token/secret configuration, opens the configured bot with a `/start` deep link,
  allows code regeneration, refreshes the loaded link state on render, and invalidates pending codes on unlink.
  `TELEGRAM_BOT_USERNAME` defaults to the owner's public bot, `WoodexInteriorBot`.
- Removed raw Telegram transport exception logging/reporting: the request URL can contain the bot token.
- Report/runbook: `docs/TELEGRAM-SECURITY-REPAIR.md`. Exposed bot token must be rotated in BotFather and the new
  token placed only in the private production environment; production webhook registration remains outstanding.
- Verification: 25 targeted Pest tests / 143 assertions, Pint, production build, targeted desktop/phone security
  browser check and both iframe modes passed. The general browser check still fails on unrelated public-homepage
  `/api/chat.php` 404s and missing expected enquiry form. No production/live bot verification: Telegram and the
  hosting domain are outside the sandbox's network allowlist. No deployment performed.

## Telegram runtime follow-up — 2026-10-17

- Owner reports previous patch deployed, replacement token saved, webhook registration succeeds; screenshots show
  no replies to plain `/start`. Production cause remains unverified (network allowlist prevents direct checks).
- Added admin-only, locked **Check live connection** diagnostics: server-side getMe/getWebhookInfo, expected bot
  and webhook comparison, pending count, redacted delivery error and local inbound timestamps. No secrets shown.
- Corrected misleading token-present "Connected" label to "Configured" and registration success wording.
- Fixed HTML escaping of command replies (`/help` placeholders and staff names); preserved pending updates on
  webhook re-registration. Neither fix is claimed as a verified cure for production plain `/start` silence.
- 32 targeted tests / 171 assertions, Pint, Vite build, targeted desktop/mobile diagnostics browser check and both
  iframe-cookie modes passed. Full general browser check remains subject to the public-homepage issues above.
- Production next step: deploy, use /admin/integrations → Check live connection, send a fresh /start and recheck.
  Runtime interpretation table added to docs/TELEGRAM-SECURITY-REPAIR.md. No production deployment performed here.

## Owner-only optional 2FA enrolment — 2026-10-17

- Owner explicitly selected optional 2FA for owner accounts only. D-20 updated: owners can open admin pages and
  integrate Telegram before authenticator setup; admin/accountant enforcement is unchanged when enabled.
- Centralized the effective requirement in User::requiresTwoFactorAuthentication(); middleware, cancellation/
  disable guard and page wording use the same rule. Explicit owner exemption covers old cached role lists and
  multi-role owners. No global ENFORCE_2FA change, no database secrets cleared automatically, no Fortify bypass.
- Confirmed owner 2FA still challenges at sign-in. Unconfirmed setup can be cancelled with the account password;
  linking Telegram does not turn 2FA on or make it mandatory. Installer wording reflects the role policy.
- Verification: 45 targeted tests / 238 assertions, Pint, Vite build, real browser navigation while enforcement
  is on with pending owner QR setup (dashboard/leads/integrations/security), password-protected cancellation,
  desktop/mobile layout and both iframe-cookie checks passed. General homepage check has the unrelated known
  failures documented above. Production not modified: deploying this branch is required for the live fix.

## Deployment preparation — 2026-10-17

- Owner requested deployment to woodex.com.pk but could not identify the Hostinger-connected branch. No Hostinger
  access or GitHub deployment workflow is available in this session; production deployment cannot be confirmed.
- Committed security/Telegram changes on arena/fe1287c9-woodexinterior and merged the latest previous application
  branch (8d3eee1) into it so the website enquiry-form bridge is preserved. No changes pushed to other branches.
- Post-integration verification: 57 targeted tests / 309 assertions passed, including the website bridge tests.
- Release branch is intended for hPanel → woodex.com.pk → Advanced → Git → select
  arena/fe1287c9-woodexinterior → Deploy. Preserve the existing target directory and private production settings.
  No new database migrations are introduced by the security repair. Confirm the deployed commit and owner
  access after hPanel finishes; then use /admin/integrations → Check live connection for Telegram.

## Telegram configuration-loading repair (local only) — 2026-10-17

- Owner confirms credentials were saved in the private .env, but Connect webhook still reports missing runtime
  credentials. Production cause is unverified: stale cached config, classic platform/.env precedence, parsing/
  permissions or server environment overrides can produce this result. No production files were inspected.
- Added read-only TelegramConfiguration diagnostics using the application's selected environmentFilePath():
  active relative path, readable/invalid state, credential presence on disk vs runtime, config-cache status,
  and a warning when a separate private env exists but the classic file was selected. Secret values and raw
  dotenv exceptions never leave the service. The diagnostics open automatically for an owner on missing setup.
- Added owner-only Reload server configuration: clears only Laravel config cache, checks failure, and performs a
  full redirect so the next request reboots services. Warns that all saved env changes take effect; never changes
  .env precedence, credentials, database settings or webhook registration. Other staff cannot invoke it.
- 71 targeted tests / 380 assertions, Pint, production build, desktop/mobile configuration browser check and both
  iframe modes passed. Existing general-homepage-check limitations remain outside this repair.
- This coding session is closed after PR #2 merged. These additional changes are LOCAL ONLY; no push, PR, remote
  GitHub call or production deployment attempted. A new coding session is required to publish this follow-up.
