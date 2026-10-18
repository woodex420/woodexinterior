# Woodex OS — implementation, integration and automation audit

**As of:** 17 October 2026, Asia/Karachi. **Baseline audit scope:** audit and planning only; no business features, provider configuration or production writes during that audit.

Read next: [master architecture](22-MASTER-AGENT-AUTOMATION-PLAN.md) · [ordered completion backlog](23-AUTOMATION-COMPLETION-BACKLOG.md).

> **Implementation follow-up:** the owner subsequently authorized local work. The first finance/WhatsApp repairs and new desired-behavior regression evidence are recorded in [A0 implementation](24-A0-SAFETY-IMPLEMENTATION.md). F01–F07 below describe the **pre-repair audit baseline**, not the current local behavior. The subsequent [Telegram/consent batch](25-TELEGRAM-CONSENT-HARDENING.md) addresses additional baseline actor/consent risks. Production/MySQL/business acceptance remains unverified.

## 1. Executive finding

**Keep the current Laravel system. Complete and harden its sales spine; add one shared automation/control layer over its existing actions. Do not build a second CRM or import an entire reference application.**

- **P1 has substantial implemented/tested CRM functionality.** Capture, assignment, scoring, SLA, tasks, visits, merge and pipeline screens exist. Contact 360, CSV import, alert routing and some cross-channel behaviors are incomplete.
- **P2 is not complete against its original requirements.** Builder, calculator, approval fingerprints, immutable sent snapshots/PDFs, signed client links, view tracking, revisions and acceptance exist. Scheduled quote follow-ups/expiry, options A/B and historical quote/template migration remain gaps.
- **P3 is not complete against its original requirements.** Advance/milestone drafts, payment recording, proof verification, allocation, receipts and client invoice links exist. Manual/variation invoice workflow, credit notes/void workflow, receivables/reporting/exports, collection sequences and financial migration remain gaps. Several integrity defects were reproduced below.
- **P4 conversations is a first installment, not a finished AI/live-chat/automation platform.** Official WhatsApp transport and a basic inbox exist. Website chat explicitly returns `on: false`; inbound WhatsApp creates contacts/conversations, not qualified leads through `CaptureLead`. No general sequence engine, agent runtime or agent dashboard was found.
- **Telegram presently links accounts, sends alerts and accepts text ideas/issues.** `/today`, `/leads`, `/approve`, natural-language business execution and callback actions are not implemented. An alert with an “Open” URL is not an approval button.
- **Production completion is unknown.** A public fetch of `https://woodex.com.pk/admin` reached the Woodex sign-in page on this date. That proves public login reachability only, not deployed revision, migrations, cron, mail delivery, provider readiness or business acceptance.

### Evidence vocabulary

| Label | Meaning |
|---|---|
| Implemented | Concrete action/controller/screen exists locally; not a promise that every clause of its PRD ID is satisfied. |
| Partial | A useful subset exists; missing clauses are listed. |
| Gap | Required executable flow/control not found in the inspected code/routes/scheduler. A table/enum/settings field alone does not count. |
| Tested locally | Relevant automated tests or recorded browser checks exist. Faked provider calls do not prove a real connection. |
| Reproduced | This audit exercised the behavior in an isolated, seeded in-memory database with providers faked. |
| Live unknown | Not authenticated/verified on production in this task. Applies to every feature below unless expressly stated otherwise. |

There is deliberately **no percentage complete**: partial requirements and unverified release gates make such a number misleading. Historical “P0–P3 done and live / P4 complete” entries in `platform/STATE.md` are progress notes, not acceptance certificates. Some called the project workspace “P4”; original PRD P4 is Conversations, while projects/portal span P3/P8. Use requirement IDs and the acceptance gates, not those labels.

## 2. Baseline and verification boundary

- Repository branch: `arena/fe1287c9-woodexinterior`; committed HEAD at inspection: `08490e986ccbcfc6f69e34d4d36ba1e871d9497d`, **plus existing uncommitted Telegram/MCP/Google work**. HEAD alone does not describe this audited working tree.
- Architecture/requirements: `docs/01-MASTER-PLAN.md`, `02-MASTER-PRD.md`, `03-MASTER-SYSTEM-PROMPT.md`, `07-DECISIONS.md`; current code and `platform/STATE.md`; deployment/import/website/integration/OAuth runbooks. Conflicts are recorded rather than silently treating plans as code.
- Prior local baseline, already executed in this session before this audit: **511 passed, 3,504 assertions, 1 skipped**. Google feature subset 33 tests/186 assertions; MCP feature subset 18 tests/75 assertions; Node MCP tests 3/3. Prior build, explicit Pint and targeted Larastan checks passed. Those are historical local results, **not a new full-suite run in this audit** and not production proof.
- Prior broad browser run: admin, mobile, quotes/PDF and client acceptance passed; **two existing homepage form expectations failed** (JS-on / JS-off). Google setup passed 1440/390/360 widths and both preview iframe modes. Do not describe the entire browser suite as green.
- **New audit evidence: 7 behavioral probes, 22 assertions, all observations reproduced.** Source and interpretation: [behavior probes](audit-evidence/2026-10-17-behavior-probes.php.txt), [recorded result](audit-evidence/2026-10-17-results.md). They assert present undesirable behavior; they are not passing regression tests for a repair. Separately reran existing `PaymentsTest` + `ConversationsTest`: **24 passed / 124 assertions**. Their normal cases remain green while the uncovered negative/batch cases reproduce defects.
- No authenticated production inspection, new deployment, real Google consent, real WhatsApp round-trip or production accountant sign-off occurred. No real credentials were requested or changed. The owner has already saved the replacement Telegram values in the private environment; the next step is **loaded-config/webhook verification**, not asking for them again.

**Paths below are relative to `platform/`, unless prefixed `docs/` or `tools/`.** Test filenames refer to `tests/Feature/` unless specified.

## 3. Reproduced defects: repair before automated writes

| Finding | Severity / evidence | Observed behavior and consequence | Required closure |
|---|---|---|---|
| **F01 — cross-client allocation** | High; Livewire reproduction | `PaymentsIndex::save()` validates `invoiceId` with `exists` only. `RecordPayment`/`AllocatePayment` do not enforce invoice contact equality. An accountant's crafted/stale form can record payment for client A and mark client B's invoice paid. | Reject mismatched client/project/currency and inactive bank/line combinations at the action boundary, not only in dropdowns. Negative UI and domain tests; reconcile balances. |
| **F02 — void invoice resurrected** | High; action reproduction | An explicit allocation to a void invoice with balance changes its status to paid through `Invoice::applyPayment()`. Targeted allocation does not enforce payable invoice state. | Only issued/part-paid eligible invoices may receive allocations; void/draft/credit-note rules explicit; reject atomically without any allocation or receipt side effect. |
| **F03 — stale verification replaces receipt** | High; deterministic stale-object reproduction | Two instances loaded while pending can both pass `VerifyPayment`'s pre-transaction guard. Second execution replaces the already issued receipt number. In the probe money was **not** allocated twice; the receipt identity changed. | Lock/reload payment and invoices, recheck pending state inside transaction; unique receipt/idempotency constraint and after-commit delivery. Test verify/verify and verify/reject interleavings on production-like MySQL. This probe was not a concurrent MySQL load test. |
| **F04 — explicit allocation map ignored** | Medium; action reproduction | `RecordPayment` documents `input.allocations`, but only passes `invoice_id` or `[]`; an explicit choice of newer invoice is ignored and the oldest gets paid. | Implement validated allocation map or remove unsupported contract; multi-invoice allocation/reallocation UI and tests. |
| **F05 — targeted excess does not settle next debt** | Medium; policy mismatch reproduced | With `invoice_id`, excess remains credit even if that same contact has another unpaid invoice. D-85 and STATE describe own invoice first **then** oldest other debts. Untargeted auto-allocation does spread. | Confirm desired credit policy; align action, documentation, receipts and tests. Never silently apply credit to another project against an owner's chosen allocation. |
| **F06 — batched inbound messages lost** | High; signed webhook reproduction | `WhatsAppWebhookController` selects `entry[0].changes[0]`; loops event IDs but `ReceiveWhatsAppMessage` always uses `messages[0]`. Two inbound messages produce one stored message; event two fails on the first message's unique ID. Replay skips both existing event IDs and does not repair the failed event. | Iterate every entry/change/message, persist each event's exact payload, queue individually, retry received/failed events safely, commit domain write + event outcome coherently. |
| **F07 — batched delivery status falsely processed** | High; signed webhook reproduction | Status handler always reads `statuses[0]`. Two “read” statuses mark both webhook events processed, but message two remains sent. This would corrupt follow-up/cost decisions. | Process the status matching each event; test multiple entries, changes, messages, statuses, out-of-order updates and duplicate delivery. |

### Additional source-backed risks / gaps, not reproduced exploits

1. **Telegram actor boundary:** `TelegramWebhookController::linkedUserOrNull()` resolves by chat ID, not `message.from.id` / `callback_query.from.id`, and does not itself join an active user. Group chat linking must never confer the linked person's permissions on every group member. Existing writes are text change requests; do not extrapolate this lookup into financial/quote commands. Require current active staff identity, separate group routing, callback ownership, TTL, rate limits and replay protection first.
2. **Webhook delivery/recovery:** Telegram commands and WhatsApp media processing run in the request. WhatsApp failures are stored then answered 200; duplicate IDs are skipped regardless of prior failure. A persisted event is not yet a durable retry mechanism. Add inbox/outbox dispatch and redrive controls (not blind resend).
3. **Telegram secret handling:** path secret is checked; supplied header must match, but a missing header is currently allowed. Preserve the current compatibility boundary until a tested migration, then require provider secret header before enabling privileged writes; redact the URL path secret from access/diagnostic logs.
4. **Notification delivery ≠ completion:** `TelegramChannel` intentionally swallows exceptions; reminder/SLA timestamps can be claimed before delivery. There is no universal per-recipient retry ledger or dead-letter control. Preserve nonblocking record saves, but record each channel's actual outcome independently.
5. **Settings not enforced:** searches found `telegram_enabled`, quiet-hour fields and digest time saved by settings, but not applied by the inspected notification delivery path. `AlertRule` has casts, but no executing rule dispatcher was found. Do not sell these settings as working automation controls.
6. **Transactional/file/provider boundary:** acceptance, invoicing and receipts create PDFs and/or dispatch notifications alongside DB writes. `SendQuote` obtains a row lock but validates state/approval before it. Review fresh-state rechecks, after-commit dispatch, file cleanup on rollback and duplicate acceptance/send/milestone operations before exposing them as retried tools. No double-accept exploit was reproduced here.
7. **Financial visibility:** invoice/payment policies use broad `invoices.view`, whereas the original matrix describes sales seeing own records. Review actual seeded permissions, own/team/global visibility and tool-field redaction. A readable admin finance screen is not permission to give that data to every agent.
8. **Consent:** inbound WhatsApp gives service-reply consent, not blanket marketing permission. Sending methods do not centrally enforce the full opt-out/category/recipient-cap policy. `CaptureLead::enrichContact()` ORs consent flags; require evidence-backed re-consent rather than accidental resurrection after STOP.
9. **Public bridge protection differs:** `LegacySiteBridgeController::config()` returns no Turnstile challenge; its form path uses honeypot/IP budgets. The new public-lead controller supports Turnstile when configured. Test both entry paths; enabling a setting is not proof the legacy path uses it.
10. **Review requests:** `RequestClientReviews` marks `review_requested_at` even when email or review URL is missing, intentionally preventing later sends. New dashboards must distinguish skipped/missing configuration from delivered; do not count those records as messages sent.
11. **Google lifecycle:** company identity consent/token handling is local and tested with HTTP fakes; real registration absent. Review concurrent reconnect/refresh/revoke, rejected consent and provider outages during acceptance testing; no live result should be inferred from setup UI.

## 4. P1 requirement traceability

### Lead capture

Evidence: `Domain/Crm/Actions/CaptureLead.php`, `Data/LeadInput.php`, `Http/Controllers/Api/Public/LeadController.php`, `Http/Controllers/Website/LegacySiteBridgeController.php`; tests `PublicLeadApiTest`, `LeadShowTest`, `Website/LegacySiteBridgeTest`.

| PRD IDs | Local finding | Pending acceptance / backlog |
|---|---|---|
| CAP-001/003/005 | Implemented central capture, phone/email matching, open-opportunity reuse, source/attribution, scoring/assignment/SLA/event. | Concurrency/deduplication load tests; channel adapters must use it; full production provenance/alert timing. B10/B30/B40. |
| CAP-002 | Partial protection across two different public entry paths; honeypot/rate limits, conditional Turnstile in new API. | Confirm origin/phone/day/fill-time budgets and bridge protection contract; JS-off submit and forged-origin tests. B12. |
| CAP-004 | Source enum exists; that does not connect the listed channels. | UI extensibility/source taxonomy and actual connector mapping. B10/B55–B58. |
| CAP-006 | Client acknowledgement/WhatsApp templates not wired to lead capture; staff new-lead alert is separate. | Approved, consent-aware client acknowledgement with recipient/day caps, email fallback and delivery ledger. B41. |
| CAP-007/010 | Legacy form bridge records leads/reference; schema differs from new JSON API. | Verify legacy-origin/per-origin-key design decision; WhatsApp continuation/reference round-trip and real browser submit. B12/B40. |
| CAP-008 | Manual lead quick-add and duplicate handling implemented. | Staff acceptance and own/team visibility regression in end-to-end gate. B10. |
| CAP-009 | Gap: generic CSV mapper/preview/batch rollback. Existing v2.1 importer is not that feature. | B13. |

### CRM

Evidence: `Domain/Crm/Actions/*`, `Livewire/Admin/Crm/*`, `Livewire/Admin/MyDay.php`, `Livewire/Admin/GlobalSearch.php`. Relevant tests: `ContactsTest`, `CompaniesTest`, `LeadsIndexTest`, `LeadShowTest`, `PipelineBoardTest`, `TasksAndVisitsTest`, `CrmAutomationTest`, `SlaCheckTest`, `SettingsAndRulesTest`, `CollaborationTest`, `GlobalSearchTest`.

| ID | Local finding | Remaining / verification |
|---|---|---|
| CRM-001 | Contact/profile, primary/alternate/WhatsApp fields, tags/consent/company relation. | Compare arbitrary multiple phones/emails with current fixed fields; preference/consent evidence and client acceptance. B10. |
| CRM-002 | Companies CRUD, people, leads/search. | Complete linked sales/finance/project overview, with permissions. B11. |
| CRM-003 | Rich lead record, ownership/source/next action/pipeline. | Field-by-field PRD UAT, history consistency and bulk negative tests. B10. |
| CRM-004 | Guarded stage action: visit/lost/won checks. | Confirm configurable stage editor and accepted-quote/override rules; no tool bypass. B10/B21. |
| CRM-005 | Board/list, filters, saved views, sorting, export. | 500-lead board performance and drag rollback under actual Hostinger latency. B10/B73. |
| CRM-006 | Next-action workflow and My Day overdue lists. | Enforce open-lead mandatory next action across every channel/import/agent; delivery-aware reminders. B10/B32. |
| CRM-007 | Tasks, recurrence, assignees and staff reminders. | Holiday-aware recurrence semantics, reliable delivery ledger and agent proposal path. B10/B32. |
| CRM-008 | Staff visit CRUD, calendar, conflict warning, measurements/outcomes. | Public slot availability/atomic booking and client confirmations/reminders are not delivered by staff calendar. B42. |
| CRM-009 | Ordered assignment rules, role/people pools, workload, round robin/least loaded, hours filtering. | Concurrency-safe distribution, inactive users, fallback/holiday real-data acceptance. B10. |
| CRM-010 | Per-source targets and two-level SLA escalation, atomic claims. | Live cron timing, retries/delivery, priority-specific behavior and external missed-heartbeat detection. B01/B10/B33. |
| CRM-011 | Configurable scoring and hot threshold. | Engagement/reply/view rescoring and explanation of score changes; not a new AI scoring model. B10/B43. |
| CRM-012 | Duplicate suggestions and merge; `MergeContacts::RELATED_TABLES` already moves leads, activities, tasks, visits, quotes, invoices, payments and projects. | Verify those existing transfers; add missing conversation/file handling, consent provenance and explicit audit/reversal policy. Do not rebuild financial/project reassociation. B11. |
| CRM-013 | My Day: uncontacted/due/overdue leads, tasks, visits, unassigned. | Quote follow-up and waiting-conversation sections missing from inspected render. B11/B43. |
| CRM-014 | Saved lead views exist; reusable marketing contact segments not equivalent. | Opt-in segment editor/materialization for campaigns; later, B54. |
| CRM-015 | Contact 360 has leads/timeline/files/quotes. | Invoices, payments, projects and conversations not included in inspected `ContactShow::render()`. B11. |
| CRM-016 | Call outcomes/activity logging. | Three no-answers → approved WhatsApp proposal and Telegram action parity. B43/B36. |

**P1 conclusion:** mature foundation; do not rebuild. Complete cross-domain views/merge, missing capture utilities and operational acceptance. Code tests do not establish sub-60-second live alerts or production data quality.

## 5. P2 quotation traceability

Evidence: `Domain/Sales/Actions/*Quote*`, `Services/QuoteCalculator`, `QuoteEditor`, `QuoteApprovalRules`, `QuoteSnapshot`, `QuoteDiff`, `QuotePdf`, `MilestonePlan`; sales Livewire screens; quote portal/controllers. Tests: `QuoteBuilderTest`, `QuotesTest`, `QuoteSendingTest`, `QuotePortalTest`, `QuoteScreensTest`, `AdvanceInvoiceTest`, `NumberingTest`, `ReferenceDataTest`, unit calculator/money/milestone tests.

| ID | Local finding | Remaining / acceptance |
|---|---|---|
| QUO-001 | Rate book implemented/seeded. | Owner validates real current catalogue, unit names, prices and active/finish coverage. B20. |
| QUO-002 | Templates with sections/items/terms/milestones. | Validate required 16 business templates against real BOQs; old template import is absent. B20/B27. |
| QUO-003 | Builder/template/blank/duplicate/section/item editing. | Keyboard/drag/fractional quantities/internal-cost/mobile UAT against all clauses. B20. |
| QUO-004 | Server calculator, minor-unit amounts and discounts/tax/margin. | Signed accountant reference cases; verify per-line vs whole-quote tax clauses and concurrency rechecks. B20/B21. |
| QUO-005 | Basis-point plans/whole-rupee split, sum check. | Confirm 50/40/10, 75/25 and custom rounding with accountant. B20. |
| QUO-006 | Number sequence/revision support. | Reconcile imported/current WI/WF numbers, deleted records and concurrent reservation. B20/B27. |
| QUO-007 | Admin approval request/decision and amount fingerprint. | Telegram decision callback and shared actor/approval safety not implemented. B21/B35. |
| QUO-008 | Immutable sent version/PDF/hash; shareable link and optional queued email. | Actual mail delivery, WhatsApp event→template adapter; “sent” currently can mean link generated, not delivered. B26/B41. |
| QUO-009 | Signed expiring client view, typed-name acceptance/change request. | OTP enhancement optional until provider available; mobile/security/revision-link live acceptance. B20/B21. |
| QUO-010 | First/repeat/hot views/download and lifecycle events. | Delivery status must link to quote send; view duration and full expiry/decline coverage require verification/completion. B22/B26/B41. |
| QUO-011 | Transactional accepted quote → client/won/project/advance invoice. | Client thank-you/payment instructions/delivery not universally wired; stale/retry robustness; live finance gate. B21/B25/B41. |
| QUO-012 | Gap: scheduler has no quote follow-up/auto-expire runner. Validity guards are not scheduled expiry. | D2/D5/D12 sequences, stop conditions, expiry/reissue policy. B22/B23. |
| QUO-013 | Revisions and staff diff service; latest sent version preserved. | Race/retry and stale-approval acceptance. B21. |
| QUO-014 | Studio PDF, versioned snapshots/amount-in-words. | Real terms/signatories/banks, 40-line/multi-page/fractional/optional-image/cover fidelity approval. B20. |
| QUO-015 | Scope text/template support. | Sanitized rich-text/print parity against owner examples. B20. |
| QUO-016 | Quote list/status/filter basics. | Working “needs follow-up” view linked to actual sequence state. B23/B11. |
| QUO-017 | Gap: estimator-to-draft quote handoff. | Use validated rate book/estimate context; never turn indicative price into a sent commitment. B44. |
| QUO-018 | Later optional Urdu PDF headings/terms. | Urdu shaping/font/print acceptance, B75. |
| QUO-019 | Gap: linked alternatives with mutual exclusion on acceptance. | B24. Not the same as copying a quote. |
| QUO-020 | Other-charges handling exists in quotation data/editor/calculation. | Tax/order/PDF reference-case confirmation. B20. |

## 6. P3 finance, project and reporting traceability

Evidence: `RaiseAdvanceInvoice`, `Domain/Projects/Actions/RaiseMilestoneInvoice`, `RecordPayment`, `AllocatePayment`, `SubmitPaymentProof`, `VerifyPayment`, `RejectPayment`, `Services/Invoice*`, `ReceiptPdf`; `Livewire/Admin/Sales/{InvoicesIndex,PaymentsIndex}`; project/portal actions. Tests: `AdvanceInvoiceTest`, `PaymentsTest`, `ProjectsTest`, `ClientPortalTest` plus the new audit probes.

| ID | Local finding | Remaining / acceptance |
|---|---|---|
| INV-001 | Advance from acceptance; milestone invoice action. | Concurrency-safe one-per-milestone guarantee; reconcile numbering/issue/send. B04/B25. |
| INV-002 | Manual/variation kinds exist, but complete independent invoice editor/issue workflow not found. | Manual invoices/variation approval + server calculator/snapshot/send. B25. |
| INV-003 | Frozen issued snapshots/PDF; lifecycle fields/policies/enums. | Full issue/void/credit-note/reversal UI/actions and negative state tests. Enum/policy is not a reversal workflow. B03/B25. |
| INV-004 | Business-line bank selection and frozen details. | Owner's private accounts/settings, missing-bank hard stop and accountant routing checks. B25/B02. |
| INV-005 | Recording + allocation/oldest-first credit foundation. | **F01–F05**, explicit allocation/reallocation, credit ledger and concurrency controls. B02–B05. |
| INV-006 | Signed invoice/portal claim upload, pending verify/reject workflow. | **F03**, hostile files, repeat proof, receipt delivery, Telegram proposal/secure finance confirmation. B03/B35/B41. |
| INV-007 | Numbered receipt PDF and queued email. | Exactly-once identity; WhatsApp delivery mapping, signed downloadable client receipt and resend audit. B03/B26/B41. |
| INV-008 | Gap: D−3/D0/D+3/D+7/D+14 collections/escalation schedule. | Shared sequences + consent/template/stop-on-paid; EN/UR and owner-approved cadence. B45. |
| INV-009 | Overdue total/list exist; full aging report not found. | Current/1–15/16–30/31–60/60+ buckets, grouping/export and reconciliation. B28. |
| INV-010 | Payment list/receipt and dashboard snippets are not the full financial reports. | Monthly invoiced/collected by line/bank; ledger/client statement PDF. B28. |
| INV-011 | Signed client invoice page/PDF/instructions/proof; portal exists. | Access to paid records/receipts and revocation policy UAT; gateway intentionally later. B25/B26/B60. |
| INV-012 | Gap: accountant date-range invoice/payment/receipt export. | Formula-safe CSV, XLSX if required, totals and export permissions. B28. |
| PRJ-001/002/003 | Project creation/stages/milestones and manual milestone invoice drafts. | Preserve **D-88:** reaching a milestone does **not** auto-issue an invoice; owner checks draft. Configure/accept stage names and manual-create scope. B25/B46. |
| PRJ-004–008 | Project board/list, private vs shared updates, portal code access, handover, review request, case-study draft implemented in later project work. | Real client round-trip, WhatsApp adapter, review skips vs sends, photograph/consent proof. B46/B47. |
| PRJ-009 | Optional later cost tracking, not a completed margin ledger. | B61. |
| RPT-001 | Role-aware operational dashboard foundation. | Full finance, waiting-inbox and automation data reconciliation. B11/B28/B39. |
| RPT-002–005/009 | Gap/partial: no dedicated full funnel/source-ROI/team/revenue/aging report routes found. | Definitions, date filters/comparisons, exports and role scoping. B28/B29. |

### Migration is still a release blocker

`Domain/System/Import/V21Importer::STEPS` = **users, clients, leads, notes, bookings**. Existing wizard/dry run and `ImportV21Test` prove this CRM importer, not historical sales migration. `wx_quotes`, templates, invoices, payments/allocations/receipts and projects/files require mapping, dry-run/re-run tests and money checksums. Website import is a different pipeline.

Do not import legacy executable PHP, secrets, auth sessions or MCP tokens. Read-only old database; evidence-backed contact/opportunity links; stage legacy ambiguous JSON money for manual resolution. Release gate requires equal source/target totals with documented merge exceptions, orphan report, numbering collision checks, accountant signature and a rollback rehearsal. B27/B71.

## 7. Integrations: inventory, not connection claims

**Every live result in this table is unknown unless stated; configuration indicators are not successful round-trips.** Current code uses private environment configuration for Telegram/WhatsApp/SMTP/Turnstile, while Google company tokens are encrypted in the database and MCP keys are hashed. The original plan's “all keys editable encrypted in Integrations” is not the present universal storage model.

| Service / integration | Current local state | Next capability / proof |
|---|---|---|
| Telegram Bot API | Gateway/fake, linking, outbound notifications, webhook setup/diagnostics/config-cache repair, text `/idea` `/issue` `/help`. | Loaded-config + webhook match using already saved values; private chat sender binding, command/callback router, approval cards, digest/routing/delivery reliability. B01/B33–B36. |
| Woodex read-only MCP | Scoped revocable expiring token bridge; Laravel HTTP read endpoints and **local Node stdio adapter**. Abilities: `status.read`, `leads.read`, `quotes.read`; not arbitrary database access. | External client install/rotation/denials acceptance. Agent write tools are a separate permissioned proposal API, not silently widening these tokens. B37. |
| Google company connection | Local identity OAuth, encrypted company tokens, health/revoke flow. **App registration not done.** | Register exact callback/consent, real connect/check/refresh/revoke, failed-revoke handling. `openid email profile` does not grant Workspace APIs. B50. |
| Google existing-staff sign-in | Local password-authorized subject linking; no signup/role grant; confirmed TOTP preserved. | Real existing-staff round-trip, verified subject/email, challenge/recovery/revocation UAT. B50. |
| Google Drive | Planned card, no file sync/backup connector. | Narrow folder/file scope, explicit folder sharing, private documents/backup policy, retry/version conflicts. B52. |
| Google Sheets | Planned card, no report sync. | Named spreadsheet/export scope, redacted columns, one-way approved export first. B52. |
| Gmail | Planned card, no Gmail OAuth mailbox integration. | Restricted scopes/verification if needed; draft-first sending. SMTP is separate. B53. |
| Google Calendar / Meet | Planned cards. Staff visit calendar is internal, not Google synchronization. | Selected calendars, busy-time merge, Karachi timezone, conflict/idempotency and conferencing permissions. B51. |
| GA4 / Search Console | Website tracking-ID injection foundations; no working reporting API connector found. | Property ownership/service account or narrow reporting OAuth; measured conversion/GSC reports, read-only first. B48/B49. |
| WhatsApp Cloud API | Existing official gateway, HMAC webhook, inbox, template/text send methods/media/status and STOP storage; credentials private environment. | F06/F07 repair, guided readiness check, real number/permissions/templates, centrally enforced consent/window/cost/quality. B06/B07/B40/B41. |
| Meta Facebook / Instagram | Planned catalog entries, not connected inbox/Lead Ads/publishing adapters. | Business/page access/review, OAuth lifecycle, signed events → existing capture/inbox; approved social publishing later. B55/B56. |
| LinkedIn | Planned card; no adapter. | Product approval + company-page auth; Lead Sync only if approved; do not promise general LinkedIn DMs. B57. |
| SMTP / optional Resend/Brevo | Laravel mail configuration, connection/test path and queued business mails. | Real inbox receipt, SPF/DKIM/DMARC, bounce handling, quotas and alert fallback. No provider migration needed if SMTP works. B01/B26. |
| Turnstile | Verifier/new public-lead integration and readiness controls. Legacy bridge intentionally differs. | Verify keys on correct host, failure behavior, no-JS strategy and bridge security. B12. |
| AI provider(s) | No functioning provider/knowledge/tool orchestration runtime found. | Provider adapter, model allowlist, redaction, caps, multilingual grounded evaluation and handoff. B38/B43/B48. |
| Pusher / Ably | Architectural option; no broadcasting implementation found. | Start polling; add managed realtime only when measured concurrency/latency justifies. B40/B62. |
| Backups / S3-compatible / Drive offsite | Backup/prune commands scheduled. No repository backup configuration or verified remote destination/restore evidence found. Package availability alone does not establish operational backup. | Verify command availability, destination, encryption/key recovery, retention/alerts, restore drill off production. B01/B70. |
| Notion / Slack / Zoom | Planned cards, not implemented Woodex connectors. | Only add for an approved business use case: shared resources/scopes, lifecycle and event mapping. B58. Arena's connected apps are separate from this application's integrations. |
| IndexNow / indexing notifications | Sitemap/site SEO foundation; no verified IndexNow dispatch found. | Search-engine support check, key ownership, submit dedupe/backoff; never promise ranking or Google indexing from a ping. B49. |
| Payment gateways / bank feeds / SMS | Optional, not connected. Current bank proof verification is manual. | Merchant/provider approval; signed idempotent webhooks, settlement/reconciliation, refunds web-only and consent. No auto-verification from screenshot/OCR. B60. |
| OpenWA / unofficial WhatsApp | Not installed/integrated. | Not recommended for Woodex's main revenue number; see reference decision below. |

## 8. Existing automation — reuse this inventory

| Trigger / schedule | Existing behavior | Missing control / delivery evidence |
|---|---|---|
| Web/manual lead → `CaptureLead` | Normalize/dedupe, attribution, score, assign, SLA/timeline; `LeadCaptured`. | Concurrent dedupe, qualified WhatsApp/chat adapters and provenance. |
| `LeadCaptured` → `SendNewLeadAlerts` | `SendLeadAlert::dispatchAfterResponse`; staff bell/Telegram. | Durable fallback/outbox, channel receipts/quiet hours and real <60s timing; do not assume the class's `tries=3` makes after-response delivery durable. |
| Every minute `woodex:sla-check` | First breach + later owner escalation, atomic claim markers. | Delivery retry, alert-rule routing and missed-cron external watchdog. |
| Every minute `woodex:reminders` | Task reminder and staff visit D−1/2h, claim timestamps. | Client template reminders, delivery ledger, pause/resume and missed-run recovery policy. |
| Task completed | Recurring successor created; existing task action. | Holiday/custom recurrence acceptance; avoid a competing agent recurrence loop. |
| Visit outcome | Measurements/outcome, next step/preparation tasks. | Reliable client acknowledgement and sequence enrollment. |
| Quote send/view/hot/change/accept | Snapshot/hash, view events and staff notifications; accepted quote creates project + advance. | Transport delivery binding; callbacks; safe retry/atomic state; expiry/follow-up sequence. |
| Staff-recorded or verified payment | Allocation, invoice balance/status, numbered receipt/PDF/email and staff notification. | F01–F05; after-commit/idempotent delivery and controlled credit policy. |
| Project stage/update/milestone | Stage email setting, private/shareable updates; milestone invoice **manual draft**. | WhatsApp adapters; any AI-proposed share must still be approved. |
| Daily 10:30 `woodex:review-requests` | Due handovers → email if address/review URL; records processed even if skipped. | Accurate delivered/skipped status; approved WhatsApp alternative; campaign consent. |
| Every minute bounded DB queue | `queue:work --stop-when-empty --max-time=50 --tries=3`, no-overlap lock. | Production cron/process permissions, throughput/queue age, job-time budgets and channel prioritization. |
| Daily/weekly operations | DB backup 02:30; full backup Sun 03:00; clean 03:30; monitor 04:00; failed prune 04:15; model prune 04:30. | Actual commands/destination/retention/restore proof and non-Telegram alert path. |
| CMS edit/publish/restore/import | Existing actions/validator/version/cache/media and URL parity tooling. | Agent draft/diff/approval bridge, safe rollback and production SEO measurement. |

**Not found in the scheduler:** general `automations:tick`, daily/weekly/monthly Telegram digests, quote D2/D5/D12, invoice collections, lead-nurture sequences, scheduled social publishing, general token refresh/expiry watcher or autonomous SEO runs. Some appear in diagrams/settings but are not executing jobs.

## 9. Website and reference-platform differences

- Current URL contract is the committed `LegacyUrlMap`/`database/data/legacy-website.php` (historical notes progressed from 142 URLs to **143 pages**). Do not reapply the old 54-redirect assumption; later owner decision preserved the specialist pages. Run parity/audit on the current map and content before any agent publishing.
- Current CMS/SEO foundations are useful. Missing live chat and paused public booking are explicit in `LegacySiteBridgeController`; a floating widget or staff calendar does not prove those public services work.
- The older marketingwoodex branch's **FEATURES.md** documents campaigns/flows, client auto-updates, chat/takeover, AI training, GA/GSC cards, CSV import and MCP draft tools. These are **reference claims, not migrated features or verified production behavior**. The new system implements different/authenticated data contracts. Create parity tickets only for useful requirements; preserve new security/approval boundaries.
- Reference Hostinger-only plan rejects Supabase/Vercel and uses PHP/MySQL/polling/SMTP. This aligns with starting the current system on its existing runtime, not transplanting a Next.js CRM. Old instructions such as “save draft → live” must not replace the present draft/publish permission gate.

## 10. External reference review and decisions

Fetched 17 October 2026; repository READMEs/demo/article descriptions are not independent security audits or provider guarantees.

| Reference | Observed basis | Decision |
|---|---|---|
| [PHP Telegram Bot core](https://github.com/php-telegram-bot/core), [organization](https://github.com/php-telegram-bot) | Core README explicitly says development is on hold; organization lists core/Laravel/examples. | Keep current small official-Bot-API gateway and Laravel action/policy design. Borrow command organization ideas, not an unassessed dependency or second database/router. Check PHP/Laravel/API compatibility and maintenance before any adoption. |
| [TailAdmin AI settings demo](https://demo.tailadmin.com/ai-settings) | Settings IA: models, connectors/keys, usage/billing, memory, files and data control. | Reuse layout concepts with real status/permissions. Demo model/balance labels are not configured providers or actual billing. |
| [Pineable agentic CMS article](https://pineable.com/blog/best-cms-platforms-for-agentic-ai) | Discusses structured content, tool access, event-driven multi-step work, approval/audit/control. | Relevant architecture criteria; marketing comparison, not evidence to replace this CMS or bypass publisher review. |
| [Old Woodex reference branch](https://github.com/marketingwoodex-cloud/-marketingwoodex/tree/arena/8a776c65-marketingwoodex), [feature guide](https://raw.githubusercontent.com/marketingwoodex-cloud/-marketingwoodex/arena/8a776c65-marketingwoodex/FEATURES.md), [Hostinger plan](https://raw.githubusercontent.com/marketingwoodex-cloud/-marketingwoodex/arena/8a776c65-marketingwoodex/HOSTINGER-MASTER-PLAN.md) | Feature guide and Hostinger plan read; useful functional inventory. No full old-code/security/deployment audit performed. | Map behavior to PRD/backlog; do not merge old code/config/secrets or claim feature parity from documentation alone. |
| [WhatsApp topics](https://github.com/topics/whatsapp), [bot topics](https://github.com/topics/whatsapp-bot) | Discovery pages mix official transports, reverse-engineered Web clients and whole CRMs. | Stars/topic membership do not establish official status, safe business-number use or stack fit. |
| [WhatsApp setup scripts](https://github.com/WhatsApp/WhatsApp-Business-API-Setup-Scripts) | Archived; README says on-premises API deprecated and directs to Cloud API. | Do not deploy old Docker/on-premises scripts. Extend existing official Cloud API integration. |
| [OpenWA README](https://raw.githubusercontent.com/rmyndharis/OpenWA/main/README.md) | Explicitly unofficial reverse-engineered whatsapp-web.js/Baileys; warns about restrictions/bans and advises against primary business number. Node/session infrastructure differs from PHP cron hosting. | Reject as primary production transport. A separately approved isolated experiment is not a prerequisite for Woodex automation and must not use the revenue number. |
| [wacrm README](https://raw.githubusercontent.com/ArnasDon/wacrm/main/README.md) | Official Business API shared inbox/flows/knowledge/MCP design; **Next.js + Supabase/Postgres/Auth** application, not a PHP SDK. | Good workflow/UX inspiration; not a drop-in Laravel module. Avoid duplicated CRM/database/auth and mandatory new hosting stack. Vendor hosting claims not independently tested. |
| [Meta pricing](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing) | Current page says per-message charging began July 2025, charges delivered outbound messages by market/category, describes October 2026 updates. | Original plan's “per-message starts October 2026” wording is inaccurate. Use effective-dated official rates/free-tier rules and delivered-event reconciliation, not hard-coded old estimates. Exact account-currency/PK rates and invoice reconciliation remain an onboarding task. |

## 11. Recommendation and immediate owner-facing action

1. **Repair finance integrity and webhook batching/recovery first.** No autonomous financial or quote-send writes until their regression/permission gates pass.
2. **Verify actual runtime**, preserving the already saved private Telegram configuration and optional-owner-2FA decision. Confirm deployed revision/migrations, cron, mail, backups and real channel round-trips.
3. **Complete P1–P3 release gaps** in parallel with the shared event/approval/delivery foundation; do not relabel a payment list “P3 complete”.
4. Build deterministic Telegram reads/approval cards and separate Automation/Agents dashboards before multi-agent autonomy.
5. Then complete public chat/WhatsApp qualification, stop-aware follow-ups, grounded specialist agents and selected provider integrations.

The concrete order, owners, dependencies and definition of done are in [the backlog](23-AUTOMATION-COMPLETION-BACKLOG.md). This is a plan, not authorization to implement or publish it. This coding session is closed for remote publication; implementation/deployment should begin in a new coding session.
