# Woodex OS — start-to-completion backlog

**Date:** 17 October 2026 PKT. **Status:** implementation started locally with owner authorization. Checkboxes track full package acceptance, including verification/business acceptance as specified; they do not mean no code has been written. See [first A0 implementation and progress](15-A0-SAFETY-IMPLEMENTATION.md) and [Telegram/consent follow-up](16-TELEGRAM-CONSENT-HARDENING.md). An unchecked “verify” ticket does **not** mean its existing feature must be rebuilt.

Use with [audit/evidence](12-OPERATING-SYSTEM-AUDIT.md) and [master architecture](13-MASTER-AGENT-AUTOMATION-PLAN.md). This is a dependency-ordered set of **75 work packages**, not a calendar commitment or authorization to enable automation.

## How to execute

- **P0 priority:** correctness/security/revenue safety before more automated writes. **P1:** sales/control foundation. **P2:** conversational agents/selected integrations. **P3:** optional expansion. These priorities are unrelated to original delivery phases P1 CRM/P2 Quotes/P3 Finance.
- Owners: **Eng** engineer; **Owner** business owner; **Accounts** authorized accountant; **Sales** sales manager/team; **Content** publisher/SEO lead; **Ops** hosting/integration operator. Multiple names mean shared implementation/acceptance responsibility, not automatic permissions.
- Type: **Fix** demonstrated defect/risk; **Finish** extend existing workflow; **Verify** evidence/UAT/setup; **Build** new component; **Optional** requires a further business decision.
- “Depends” uses minimum prerequisites; acceptance also requires the shared gate beneath the tables. Never enable a specialist before its dashboard, policy gateway, data scope, budget and evaluation controls are ready.
- Track for every ticket: assignee, status, linked PR/local patch, requirement IDs, test evidence, deploy revision, owner acceptance and rollback. Git/code completion, live capability check and business acceptance are separate fields.
- Current session has **no remote publication**. Local implementation is authorized; a new coding session is required for publishing/deployment. Preserve existing local Telegram/MCP/Google changes.

## A0 — verify reality and repair correctness first

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B01 | P0 Verify | Ops + Owner | — | Capture deployed revision, pending migrations, loaded private configuration, actual cron heartbeat/queue drain, SMTP receipt, Telegram webhook/status/link round-trip and backup command/destination state. Already saved Telegram secrets are **not** requested again. Keep redacted dated evidence; login-page reachability alone fails this gate. |
| [ ] | B02 | P0 Fix | Eng + Accounts | — | **F01/F02:** validate allocation contact/currency/project/payable state and bank/line eligibility inside actions; protect balances with transaction/locks. UI/action negative tests reject cross-client, draft/void, incompatible currency/bank and over-allocation without side effects. INV-003/005. |
| [ ] | B03 | P0 Fix | Eng + Accounts | B02 | **F03:** re-read/lock pending payment, verify/reject once, stable unique receipt; after-commit delivery/file cleanup. Sequential stale-object and MySQL verify/verify + verify/reject tests produce one decision/receipt with reconciled amounts. INV-006/007. |
| [ ] | B04 | P0 Fix | Eng | B02/B03 | Idempotency/fresh-state hardening for accept/send/milestone invoice/number allocation. Unique business constraints, approval rechecks under lock, retried requests and transaction/PDF rollback tests; one committed effect, no changed frozen PDF. QUO-008/011/013, INV-001, PRJ-003. |
| [ ] | B05 | P1 Finish | Eng + Accounts | B02/B03 | **F04/F05:** honor or explicitly narrow allocation-map contract; owner decides targeted excess/credit policy. Implement multi-invoice selection/credit application and documented reallocation rules. Rs 300k → 200k + 100k fixture, remaining balance/credit/receipt all reconcile. INV-005, D-85. |
| [ ] | B06 | P0 Fix | Eng | — | **F06/F07:** iterate every WhatsApp entry/change/message/status with exact event payload. Batched and mixed-account fixtures preserve every message, update correct delivery status and replay without duplicates. Never mark unhandled event processed. WAP-002/003/004. |
| [ ] | B07 | P0 Fix | Eng + Ops | B06 | Durable webhook admission/dispatch, fast ack, lease/sweeper/redrive for received/failed events, failed-event replay recovery and unknown outcome handling. Simulated crash after insert/before queue and after business commit does not lose/double-process events. Extend existing webhook ledger. |
| [ ] | B08 | P0 Fix | Eng + Owner | — | Review Telegram sender vs group identity, active staff checks, secret-header migration/rate limits, finance own/team permissions and consent resurrection/STOP. Add denial fixtures; important bot writes remain disabled until B34/B35. Preserve owner-only optional 2FA and confirmed challenges. |
| [ ] | B09 | P1 Verify | Eng + Owner + Accounts | B01 | Establish accepted requirements/evidence register and reconcile historical completion labels, current 143-page map vs old 142/54-redirect notes, approval policy, stage-to-invoice D-88 and pricing assumptions. Reproduction probes become desired-behavior regression tests when fixed; do not keep asserting defects in CI. |

**A0 gate:** F01/F02/F03/F06/F07 fixed and tested; F04/F05 policy and implementation resolved before their automation; stale actor/replay tests pass. The seven baseline probes are observations, not this gate's success criteria. Finance/WhatsApp desired-behavior tests now exist; see the implementation report for repaired sub-items and remaining gates.

> **Subsequent local update:** core quote-editor/recalculate/approval request+decision/reopen+revise/delete/view/change-request locking and original v2 content/recipient/revision binding now have regressions. Full suite **714 passed /4,422 assertions /1 skipped**; real MySQL races and related-writer protocols remain unverified, so B04 stays unchecked. Owner also approved a basic balanced-ledger ERP and preview-only, new-contact-only January import (16 created, two duplicate rows skipped, eight held). [Scope, evidence and next Accounts batches](18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md).

> **B04 local progress (first slice):** locked fresh-state send/accept checks, revision-bound public acceptance, serialized project/milestone helpers, unique milestone invoice constraint, historical suffix allocation and PDF/after-commit rollback tests are implemented. **34 new regressions; full suite 656 passed /4,202 assertions /1 skipped.** Coordinated editor/approval/reopen/revise locks, complete proposal fingerprints, MySQL races and business UAT remain; package stays unchecked. [Report and preview refresh](17-QUOTATION-IDEMPOTENCY-START.md).

> **B08 local progress:** sender/private-chat/active-link checks, header-auth migration and throttling, plus sticky STOP across capture/merge/edit/send are implemented and tested. Broader finance own/team permissions and runtime acceptance still keep the package unchecked. See the follow-up report; no financial/approval bot commands were enabled.

## A1a — finish P1 CRM/capture without rebuilding it

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B10 | P1 Verify/Finish | Eng + Sales | B08/B09 | CRM-001…013/016 acceptance: fields, mandatory next actions across entry points, business-hours/holiday/priority SLA, assignment fairness/caps/inactive users, scoring explanations/engagement, recurrence, pipeline guards, contacts/companies and own/team scope. 500-lead board and invalid-stage cases included; preserve existing actions. |
| [ ] | B11 | P1 Finish | Eng + Sales + Accounts | B10 | Complete Contact/Company 360 and My Day quote/chat queues; verify existing merge transfers for quotes/invoices/payments/projects, add missing conversation/file/consent-provenance handling and visibility checks. Zero orphaned records or financial disclosure; pending invoice/quote decisions remain coherent. CRM-002/012/013/015. |
| [ ] | B12 | P1 Fix/Finish | Eng + Ops | B08/B10 | Unify/document new public-lead vs legacy bridge spam/origin/phone/day/fill-time protection; Turnstile and no-JS behavior explicit. Real browser form submit and WhatsApp continuation/reference tests; resolve two known homepage expectations without undoing owner's current layout. CAP-001/002/007/010, WEB-005. |
| [ ] | B13 | P2 Build | Eng + Sales | B10/B16 | CSV contact/lead importer with column map, preview, duplicate warnings, batch idempotency/rollback, formula-safe reports and permission checks. Central capture or a documented no-notification import mode, not ad-hoc lead writes. CAP-009. |
| [ ] | B14 | P1 Verify/Finish | Eng + Owner + Accounts | B08 | Confirm/complete secure bank/wallet/tax/numbering settings UI and role boundaries; private account details never shipped in code. Missing-bank/invalid-business-line prevents issue/send; changes audited, frozen document bank details unchanged. INV-004/SYS-004. |
| [ ] | B15 | P2 Verify/Finish | Eng + Sales | B09/B10 | Field dictionaries/configurable pipeline/source/lost reasons/scoring/unit/booking rules meet PRD, including probability/priority and migration mappings. No enum-only “connected channel” claims; config change cannot invalidate historic states. CRM-003/004/011, CAP-004. |
| [ ] | B16 | P1 Verify | Eng + Owner | B08 | Explicit read/write/export/approval field-and-record permission matrix for owner/admin/sales/PM/accounts/editor/support and service identities. UI, HTTP, MCP and future tool denials tested; permission revocation stops waiting jobs; owner business-state guards remain active. |
| [ ] | B17 | P2 Finish | Eng + Owner | B16/B30 | Link business audit, notifications, integrations, approvals and export logs with correlation/channel/actor; masking and retention policy. Contact privacy export/erase covers new logs/transcripts/knowledge/media without corrupting ledger retention duties. SYS-007/013, GLB-018. |
| [ ] | B18 | P1 Verify | Eng + Accounts | B02–B08 | Production-like MySQL test fixtures for allocation/receipt/quote acceptance/number races, multi-worker claims, duplicate contact/enrollment admission and file/DB failure recovery. SQLite pass alone is not lock/concurrency proof. |
| [ ] | B19 | P1 Verify | Eng + Owner + Accounts | B09/B10 | Define KPI event provenance, cohort/date/timezone/currency rules and report truth table: sent vs delivered vs read, credit vs collected, lead vs client, skipped vs sent, model estimate vs actual cost. Dashboard metrics reconcile to underlying records. |

## A1b — finish P2 quotations and P3 money/reporting/migration

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B20 | P1 Verify/Finish | Eng + Sales + Accounts | B04/B09/B14 | QUO-001…006/009/014/015/020 UAT: real rate book/16 templates, fractions/discount/tax/margin/milestones, WI/WF numbering, mobile/keyboard editing and multi-page PDF/terms/signatories. Owner-approved reference BOQs reconcile to paisa; flag any unsupported per-line tax/cover/image clauses explicitly. |
| [ ] | B21 | P1 Finish | Eng + Sales | B04/B16/B20 | QUO-007/008/010/011/013 fresh-state/approval fingerprint/link/revision/acceptance review. Sent PDF/hash immutable, stale/expired/revised links and changed discount/recipient cannot bypass required approval. Telegram transport approval waits for B35. |
| [ ] | B22 | P1 Build | Eng + Sales | B21/B31 | Quote validity runner: expire/reissue with deliberate date/link/revision policy, scheduled state/log and retry safety; expiration does not auto-send. Expired quote cannot accept and old snapshot stays retrievable by authorized staff. QUO-012. |
| [ ] | B23 | P1 Build | Eng + Sales | B21/B31/B41 | Quote D2/D5/D12 follow-ups, not-viewed/viewed branches, needs-follow-up view and call tasks. Stop immediately on reply/changes/accept/decline/lost/opt-out; current eligibility rechecked at send. Draft/approval first until owner adopts bounded policy. QUO-012/016, AUT-001/002. |
| [ ] | B24 | P2 Build | Eng + Sales | B21 | Quote options A/B under linked proposal/number, side-by-side client choice and atomic mutual exclusion on accept. Old copies alone do not satisfy QUO-019; exactly one won project/advance. |
| [ ] | B25 | P1 Finish | Eng + Accounts + Owner | B02–B05/B14/B20 | Full manual/variation/milestone draft editor → issue/freeze → approved send; controlled void/credit note and allocation reversal. Missing bank/tax problems block issue; issued data immutable; D-88 manual milestone draft policy preserved. INV-001…004/011, PRJ-001…003. |
| [ ] | B26 | P1 Finish/Verify | Eng + Accounts + Ops | B03/B21/B25/B30 | PDF/artifact privacy, signed client receipts/paid-record policy, email delivery/bounce/resend audit and receipt/invoice/quote transport binding. Delivery queued/sent/delivered separated. Real inbox receives accepted documents; uncertain delivery never blind-resends. INV-007/011, QUO-008. |
| [ ] | B27 | P1 Build | Eng + Accounts | B05/B20/B25 | Extend v2.1 importer to templates/quotes/revisions/options where present, invoices/payments/allocations/credits/receipts, projects/milestones/files. Dry-run counts/money hashes/orphans/number mapping; source-ID idempotency and rerun tests; ambiguous JSON values quarantined. Existing CRM steps retained; no legacy secrets/auth/code imported. SYS-006. |
| [ ] | B28 | P1 Build | Eng + Accounts | B05/B19/B25 | Finance aging current/1–15/16–30/31–60/60+, bank/line/month collections vs invoiced, ledger/client statement PDF and date-range CSV/XLSX scope. Reconciled penny-level totals/credit/refund/void edge cases and permission-safe exports. INV-009/010/012, RPT-005/009. |
| [ ] | B29 | P2 Build | Eng + Sales + Owner | B19/B28 | Funnel/drop-offs, sources/campaign ROI with manual ad spend, team response/visits/quotes/win/revenue reports, prior-period comparison and exports. Date/ownership filters consistent and totals traceable. RPT-001…004/009. |

**Sales completion gate:** admin/email provisional “enquiry→receipt” flow, current-state money integrity and migration fixtures accepted. Full original R1 including Telegram approval and client-channel delivery is not signed off until B35/B41/B71. Every missing Must clause remains visible even when a provisional pilot is useful.

## A1c–A2 — shared runtime, permissions, controls and Telegram

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B30 | P1 Build | Eng | B04/B07/B16/B19 | Versioned event catalog, atomic domain event/outbox admission, per-recipient delivery attempts, correlation/causation, idempotency keys, lease/recovery/error classes. Crash and duplicate fixtures prove committed actions survive outages and do not double-send. Extend existing webhooks/integration logs where useful. |
| [ ] | B31 | P1 Build | Eng + Sales | B30/B16 | Constrained sequences engine: triggers, conditions, business-time waits, holidays, enrollment/step versions, reply/paid/accepted/opt-out exits, contact send lock/caps, pause/resume/cancel, retry/dead-letter/unknown reconciliation. Dry-run preview and stop/send race tests. AUT-001/002/004/005/006. |
| [ ] | B32 | P1 Finish | Eng + Sales | B30/B31 | Bring existing task/visit/SLA/review automation into observable run/delivery framework **without duplicating schedules/effects**. Apply real Telegram on/off/quiet-hours/priority/recipient rules; separate skipped/enqueued/delivered/retry. AlertRule becomes executed routing, not unused schema. AUT-003, TGM-002/008. |
| [ ] | B33 | P1 Build/Verify | Eng + Ops | B01/B30 | External missed-heartbeat watchdog, queue age/backlog/failed jobs, backup/disk/provider/token-health incidents, safe retry/ack and fallback channel if Telegram fails. Simulate cron off, provider timeout, full disk/failed backup and recovery; correlate incidents without false “no traffic = outage” alarms. SYS-008/009. |
| [ ] | B34 | P1 Build | Eng + Owner | B07/B08/B16/B30 | Telegram command/callback router with provider verification, sender-ID binding, active staff check, separate group destination, per-command scopes/rate limits, secure unlink/deactivation and safe attachment admission. Unknown group member/replay/forged header tests fail closed. TGM-001/004/007. |
| [ ] | B35 | P1 Build | Eng + Owner + Accounts | B04/B16/B21/B25/B30/B34 | Generic proposal/approval service and Telegram cards with opaque nonce, exact action/recipient/content/resource fingerprint, eligible approver, TTL/reject/expiry/single consumption and audit. Finance/admin sensitive actions web-confirmed initially; no direct refund/void/role/secret tool. Tamper/stale/forward/replay/role-revoke tests. TGM-005, QUO-007. |
| [ ] | B36 | P1 Build | Eng + Sales + Owner | B28/B32/B34/B35 | Deterministic permitted `/today /leads /lead /find /quotes /quote /due /visits /report /health /approvals`, proposal-based assign/note/next/draftquote/pause; idea/issue attachments/status feedback; scoped daily/weekly/monthly digests and mute. Owner completes test day; no unrestricted natural-language write path. TGM-002…008, SYS-010. |
| [ ] | B37 | P1 Verify/Finish | Eng + Owner | B01/B16 | Accept current read-only Woodex MCP with actual external client/local stdio adapter; scope/expiry/revoke/disabled-user/visibility tests and install runbook. Explicitly only status/leads/quotes abilities; new write-proposal tools require separate service policy and owner choice, not wider old tokens. |
| [ ] | B38 | P2 Build | Eng + Owner | B16/B30/B35/B59 | AI provider abstraction/model allowlist, typed tool registry, actor-bound policy gateway, bounded run/step persistence, prompt/tool versioning/redaction and safe-read retries. Model cannot issue raw SQL/code, self-grant tools, bypass approval or silently change provider/cost policy. No production autonomy yet. |
| [ ] | B39 | P1 Build | Eng + Owner | B19/B30/B35 | Separate `/admin/automations` and `/admin/agents` navigation/routes/layout/permissions and real overview counters with drill-downs. Honest empty/offline/config-unverified states and mobile/keyboard access; detailed controls completed in B63/B64 before specialist rollout. |

## A3 — official channels and the full conversational funnel

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B40 | P1 Finish/Verify | Eng + Ops + Sales | B06/B07/B12/B30/B34 | Official WhatsApp account/number/permission/coexistence feasibility and guided capability health check; complete normalized conversation intake/assignment/contact linkage, message/status/media/window behavior. Unknown inbound does not bypass central capture; actual signed round-trip and media privacy accepted. CNV-001/002/003/005/008, WAP-001…004/010. |
| [ ] | B41 | P1 Build | Eng + Sales + Owner | B30/B31/B35/B40/B59 | Approved EN/UR template sync/submit/status/map to enquiry/visit/quote/invoice/receipt/project/review events; centralized opt-in/STOP/category/window/contact caps, quality/rate limits, delivered usage/cost ledger. No placeholder approval statuses. Real out-of-window approved template and opt-out denial prove readiness. CAP-006, WAP-005/006/008/010. |
| [ ] | B42 | P2 Finish | Eng + Sales | B10/B31/B35/B41 | Public availability/booking API + calendar rules, held/confirmed slots, atomic conflict recheck, Karachi times, reschedule/cancel and staff/client D−1/2h reminders. Paused mode remains truthful until real slot booking works. CRM-008, BOT-005, WEB-007. |
| [ ] | B43 | P2 Build | Eng + Sales | B10/B31/B35/B38/B40/B48/B66 | Qualification and sales-assist flow: EN/UR/Roman Urdu fields, provenance, once-only `CaptureLead`, transcript link, assign/SLA and staff handoff. Engagement rescoring, three no-answers→template proposal, unanswered-chat SLA and sale follow-up stop logic. Evaluated ground truth, not invented price/date promises. BOT-004/007/009, CRM-011/016, CNV-007/009. |
| [ ] | B44 | P2 Finish | Eng + Sales | B20/B35/B43 | Estimator context → qualified lead → draft quote, validated rate book and indicative-price label. User/staff confirms actual measurements/scope before send; no direct commitment from estimate. QUO-017, WEB-006. |
| [ ] | B45 | P1 Build | Eng + Accounts | B28/B31/B35/B41 | Invoice collection sequence D−3/D0/D+3/D+7/D+14, balance-aware EN/UR messages, pending-proof/dispute/manual pause and overdue escalation. Payment/void/opt-out stop races tested; recipient/day caps combine with all other campaigns. INV-008, AUT-002. |
| [ ] | B46 | P2 Finish/Verify | Eng + PM + Accounts | B25/B35/B41 | Accept real project/portal client flows, stage/progress/private update/media sharing and milestone invoice-draft proposal. D-88 manual draft preserved; no internal costs/snags leak; revoked/expired portal access and actual client update delivery tested. PRJ-001…006. |
| [ ] | B47 | P2 Finish | Eng + PM + Content | B31/B35/B41/B46 | Handover/client OK → delayed review with real review URL/consent → optional case-study draft from approved photographs/facts. Missing email/config logged as skipped, not delivered; no fake testimonial or automatic publication. PRJ-007/008. |

**Conversational gate:** complete “WhatsApp day” plus website visitor session, no duplicate contact/opportunity, correct human takeover/quiet mode, approved delivery and stop-on-reply/STOP/paid; meet chosen response SLA or transparently stay human/delayed mode. Do not enable the bot just because transport works.

## A4–A6 — knowledge, selected integrations and marketing

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B48 | P2 Build | Eng + Content + Sales | B16/B35/B38 | Permission/publication-aware knowledge ingestion/retrieval, approved business facts/FAQ/rates, EN/UR/Roman Urdu retrieval citations and source invalidation. SEO audit→typed draft/diff workflow reuses CMS actions; no invented people/reviews/projects, no auto-publish. BOT-003, CMS/WEB SEO scope. |
| [ ] | B49 | P2 Build/Verify | Eng + Content + Owner | B19/B48/B50 | GA4/GSC read-only reporting with verified property access, conversion/landing/query views and effective periods; website events/consent configuration. Sitemap/indexing-key/engine support review, deduped submit/backoff if selected. No guaranteed ranking/indexing claims. RPT-007, WEB-013. |
| [ ] | B50 | P1 Verify/setup | Owner + Ops + Eng | B01/B16 | Register real Google OAuth app/exact callback; accept company identity consent/check/refresh/revoke plus existing-staff subject link/sign-in/TOTP/recovery. Test denied consent, failed revocation and concurrent lifecycle cases. Current identity scopes do not connect Workspace; add none silently. |
| [ ] | B51 | P2 Optional | Eng + Sales + Owner | B42/B50 | Choose Calendar/Meet requirement and narrow scopes/resources; busy/free synchronization, conflict/duplicate/event-change handling, timezone and approved conferencing links. If not selected, retain internal calendar and truthful planned status. |
| [ ] | B52 | P2 Optional | Eng + Owner + Accounts | B16/B35/B50 | Select Drive folders and/or Sheets reports use cases/scopes. Approved one-way export/file linking first; private project media/backup segregation, conflict/idempotency/revoke tests and actual resource access. Google identity connection alone fails this acceptance. |
| [ ] | B53 | P3 Optional | Eng + Owner | B16/B35/B50 | Decide whether Gmail mailbox OAuth adds value beyond working SMTP. If yes: scope/verification/retention, selected mailbox, draft-first send approval and ingest dedupe/visibility. Otherwise keep planned, with SMTP not mislabeled Gmail. |
| [ ] | B54 | P2 Build | Eng + Sales + Owner | B31/B35/B41/B59 | Reusable consent-aware contact segments and approved scheduled broadcasts, audience snapshot/cost preview, suppression/caps/pause and per-recipient delivery ledger. START/re-consent evidence explicit; no cold blasting or accidental post-STOP reenrollment. CRM-014, WAP-007. |
| [ ] | B55 | P2 Optional/setup | Owner + Eng + Sales | B10/B30/B35/B40 | Meta business/app/page permission approval and Lead Ads mapping; signed leadgen events fetch/test lead→central capture with campaign/form/ad IDs and valid opt-in. Replay and revoked-access fallback tested; real test lead <1min. SOC-001/004. |
| [ ] | B56 | P2 Optional | Eng + Content + Sales | B35/B40/B54/B55 | FB/IG eligible DM/comment adapters, moderation safeguards, approved content composer/calendar/scheduler and provider-specific media/window rules. Real scheduled post + inbox reply, permissions/cost/error retries and opt-out accepted; no copied second CRM. SOC-002/003/005/006/008…010, CNV-010. |
| [ ] | B57 | P3 Optional/setup | Owner + Eng + Content | B35/B54/B56 | LinkedIn granted product/company-page access; approved posts/comments and Lead Sync only if granted; CSV import fallback. Denials/expiry tested and no promise of general LinkedIn DMs. SOC-007. |
| [ ] | B58 | P3 Optional | Owner + Eng | B16/B30/B35 | Prioritize Notion/Slack/Zoom only for named business outcomes; shared pages/channels/meeting scope, secret lifecycle, retries/audit and real resource acceptance. Do not treat Arena connected apps as Woodex product connectors. |
| [ ] | B59 | P1 Build | Eng + Owner + Accounts | B19/B30 | Effective-dated AI/message rates, estimated vs actual usage ledger, account-level free tiers, quotas and atomic per-run/agent/conversation/org budget admission. Hard cap stops paid work and alerts; delivered WhatsApp reconciliation and concurrent cap tests. Provider prices/PKR conversion chosen explicitly, not old plan estimates. |

## A7–A8 — dashboards, agents, optional extensions and handover

These packages are numerically later but some are prerequisites for earlier functionality. In particular B63/B64 are **before** any specialist rollout, B65/B66 before live public AI and B59 before paid AI/message automation.

| Done | ID | Pri / type | Owner | Depends | Work and definition of done |
|---|---|---|---|---|---|
| [ ] | B60 | P3 Optional/setup | Owner + Eng + Accounts | B02–B05/B25/B28/B35 | Select merchant gateway/SMS/bank-feed needs, credentials/approval and signed idempotent payment events, settlement/reversal/refund reconciliation. Human controls for uncertain settlements; no screenshot-based automated verification. Current manual-payment workflow remains viable without gateway. |
| [ ] | B61 | P3 Optional | Eng + Accounts + PM | B25/B28/B46 | Project purchase/expense cost lines, approved allocation and actual vs quotation internal cost/margin reporting; restricted cost visibility and accountant export. PRJ-009. |
| [ ] | B62 | P2 Decision/Optional | Owner + Ops + Eng | B01/B18/B30/B33/B59 | Load/latency gate comparing cron/polling vs external worker/managed realtime; owner approves exact hosting/services/costs. If worker selected: scoped leases/API, revoked-service tests, no public DB/broad token, supervisor/health/recovery and single-executor guarantee. No silent runtime-policy change. |
| [ ] | B63 | P1 Build | Eng + Owner + Ops | B31/B32/B33/B35/B39/B59 | Complete separate Automation dashboard: definition versions, runs/steps/waits, approvals, per-recipient deliveries/unknown outcomes, due/failed/paused filters and safe retry/pause/resume/cancel/dead-letter/kill controls. Fixtures prove cards reflect actual state; mobile/permissions accepted. AUT-005. |
| [ ] | B64 | P1 Build | Eng + Owner | B35/B38/B39/B59 | Complete separate Agents dashboard/settings: read/draft/shadow modes, tool/knowledge masks, prompt/model versions, run/tool/approval/citation traces, budgets/usage, lease-based working/failed/paused states, pause/cancel and evaluation history. No static fake statuses or hidden spend. |
| [ ] | B65 | P2 Build | Eng + Sales | B12/B30/B35/B40/B62 | Website widget/public chat API: cache-safe scoped visitor tokens, persistence/short polling, upload/rate/spam limits, offline/human mode, page attribution, keyboard/mobile/no-JS fallback. Empty/off/paused states truthful; cross-session/private-record access denied. BOT-001, WEB-008. |
| [ ] | B66 | P2 Build | Eng + Sales | B35/B38/B48/B59/B63/B64/B65 | Grounded reply engine and atomic bot/human ownership, suggested/canned replies/translation/summary, turn/budget/outage fallback, complaint/negotiation/human/VIP handoff and quiet-until-release. EN/UR/Roman Urdu fixture evaluation; price/date/private-data/injection denials. BOT-002/006…010, CNV-004/006/009. |
| [ ] | B67 | P2 Build | Eng + Owner | B36/B38/B43/B48/B63/B64/B72 | Master coordinator: intent→bounded plan→permitted specialist→approval→domain result/delivery summary. Ambiguous recipient/amount asks a question; no recursive unlimited delegation, no arbitrary shell/SQL or write bypass. Shadow/draft promotion accepted on realistic staff scenarios. |
| [ ] | B68 | P2 Build | Eng + Sales | B10/B11/B23/B35/B43/B63/B64/B72 | CRM-hygiene and sales agents propose missing-next-action tasks, duplicate/owner repairs, drafts/revisions and eligible follow-ups. Reuse scoring/assignment/sequences, no duplicate timers. Own/team data scope, no unapproved merge/discount/send; shadow vs human outcomes measured. |
| [ ] | B69 | P2 Build | Eng + Accounts + PM | B03/B28/B35/B45/B46/B47/B63/B64/B72 | Finance/collections and project agents read anomalies/aging and draft update/invoice/receipt-related proposals. No model confirms money or auto-issues milestone invoices; PM/client-visible content controls and finance field redaction tested. |
| [ ] | B70 | P0 Verify/Finish | Ops + Owner | B01/B33 | Working encrypted daily DB/weekly files offsite backups, retention/key recovery and independent failure alerts; **restore into isolation** with docs/uploads/ledger counts and signed artifacts checked. Local scheduled command or same-host zip alone does not satisfy recovery. SYS-009. |
| [ ] | B71 | P1 Verify/release | Owner + Accounts + Ops + Eng | B18/B27/B28/B35/B41/B70/B73/B74 | Staged final import/freeze/source→target counts and money reconciliation, orphan/merge exceptions, numbering/attachments checks, complete original R1 Telegram/channel enquiry→receipt UAT and accountant/owner sign-off. Rehearsed rollback, protected archive/retention and no historic data loss. |
| [ ] | B72 | P1 Build/Verify | Eng + Owner + Sales | B16/B17/B35/B38/B48/B59 | Reusable evaluation/red-team and privacy suite before agent promotion: grounded EN/UR/Roman Urdu, injection/SSRF/files/PII, group identity, tool/role revoke/stale approvals, opt-out/budget race, model outage/handoff; approved processor/retention/data-export/erase policy. Track pass/fail, cost and latency per version. |
| [ ] | B73 | P1 Verify | Eng + Ops | B18/B30/B31/B62 | Actual hosting load/performance test: 500-lead board/10k lists, incremental inbox/widget polls, PDF/backup + queue coexistence, webhook <2s ack, bounded cron and chosen chat reply p95/queue age. No fabricated capacity claims; tune/index or select worker before rollout. NFR-002/003/004/013. |
| [ ] | B74 | P1 Verify/handover | Owner + Ops + Sales + Accounts | B09/B33/B63/B64/B70/B72 | Staff training and incident/deploy/rollback runbooks; staged pilot→approval-gated release, secret rotation evidence (already replaced Telegram values not reset), database update checklist, production smoke/provider checks and emergency stop/recovery drill. Log deployment revision/time separately from code/test completion. |
| [ ] | B75 | P3 Optional | Eng + Content + Sales | B20/B48 | Urdu PDF terms/headings and optional RTL campaign landing pages: licensed font/shaping/multiline print/browser/assistive-technology acceptance. Never block core English sales release on a separately deferred Could requirement. QUO-018, WEB-014. |

## Critical path and useful parallel tracks

```text
B01 live truth + B02/B03/B04 finance + B06/B07 webhook + B08/B16 authority
  -> B30 events/outbox -> B31 sequences + B35 approvals -> B39/B63 controls
  -> B34/B36 deterministic Telegram -> selected B40/B41/B65 channels
  -> B59 budgets + B38 tool/model runtime + B48 knowledge + B64 agent controls
  -> B72 evaluation -> B66/B43 grounded qualification/handoff
  -> B67 master + B68/B69 bounded specialists
  -> B71 sales/migration sign-off + B74 operational handover
```

This diagram is conceptual; **the dependency cells are authoritative** (for example B35 needs B34). No dependency cycle is intended.

Parallel once safety permits:
- **Sales track:** B10/B11/B12/B14 → B20/B21/B25 → B27/B28; Accounts reviews real money fixtures.
- **Automation track:** B30/B31/B32/B33/B35 → B39/B63; no extra agent logic before core observability.
- **AI track:** B59 → B38/B48/B64/B72; shadow only until channel/handoff/business acceptance.
- **Owner/provider track:** real Google app setup, Meta number/template/permission approvals, private business bank/NAP/photo/consent evidence, backup destination. Approval wait time does not justify fake success states.
- **Optional expansion:** B51–B58/B60/B61/B62/B75 selected by value and provider/hosting access, not required merely because a card/reference exists.

### First implementation batch (recommended)

1. B01, B02, B03, B06 and B07; bring F01–F07 probes into desired-behavior regression coverage through B04/B05.
2. B08/B16 and B18; lock in actor/money/consent boundaries.
3. B09 and owner/accounts review of the remaining Must requirements.
4. B30/B31/B35/B39/B63 before bot-write autonomy; complete sales/report/migration gaps alongside this.

## Universal definition of done

A ticket is only **code-complete** when the intended contract is implemented, negative/replay/state/permission tests pass, targeted static/style/build checks pass where relevant, and runbooks/STATE are updated. New UI also requires desktop/mobile/keyboard and real-browser checks including iframe auth where applicable.

It is only **live-verified** after an approved deployment/migration and dated runtime/provider evidence. It is only **business-accepted** after the named owner signs its realistic scenario and data/finance totals. Rollback/recovery and support ownership are required before broad rollout.

Do not close mandatory business gaps just because an optional automation could work around them. Do not close a connector because configuration is present, a provider call was faked, a demo badge says connected or another Arena app has access.
ays connected or another Arena app has access.
