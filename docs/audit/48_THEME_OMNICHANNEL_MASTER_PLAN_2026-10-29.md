# Revision48 — Theme, web-chat capture and omnichannel master plan

**Date:** 29 October 2026 · **Code baseline:** `c47a36a` · **Status:** 48-A approved and locally delivered; later schema/provider phases remain gated.

## 1. Recommendation and boundaries

**Approved first slice, now delivered — 48-A: native OS theme/header polish, reliable website-chat alerts, early enquiry capture and a dedicated Web Chat Leads tab. Audit Telegram's local diagnostics alongside it; authorize live pairing/testing separately.** Then deliver WhatsApp/Meta and other channels in individually verified slices.

Keep Laravel/Livewire, existing conversations/messages and the single CRM capture action. Give website chat its own usable tracking workspace, **not a second disconnected database or duplicate CRM**. A browser token resumes a conversation; it is not the database. Anonymous conversations must remain visible before visitors supply contact details.

This document does not authorize schema migrations, historical backfills, production credential changes, outbound messages, webhook replacement, deployment or merging. Approval of 48-A is not approval of every later phase. Public v1 pages, financial semantics, frozen quotations/invoices, F05 and production reconciliation remain outside this increment.

## 2. Audit: what exists versus what is missing

| Area | Source evidence | Finding / implication |
|---|---|---|
| Header marker | `platform/resources/views/components/admin/header.blade.php` | “Demo data” is conditioned on `app()->isLocal()`, not record provenance. Remove the misleading top-bar pill by relocating an accurate environment indicator; do not change APP_ENV or delete records to hide it. |
| Theme | `platform/resources/js/admin.js`, `resources/css/admin.css`, admin layout/components | Native light/dark store and `wx.theme` exist. A cohesive semantic palette, System preference, storage-failure handling and cross-surface contrast verification need a defined implementation. |
| Dependencies | `platform/package.json` | Declared Tailwind `^4.3.3`, Preline `^5.0.0`, Livewire-provided Alpine, ApexCharts `^4.7.0`. Reuse these; no framework swap or unreviewed major dependency upgrade. |
| Assistant | `platform/app/Livewire/Admin/AssistantDrawer.php` | Staff assistant is a permission-aware query shell with component-local, nonpersistent turns. It is distinct from the website intake bot and customer transcripts. Do not claim a persistent AI agent exists. |
| Website storage | `Domain/Conversations/Actions/StartWebChat.php`, models `Conversation` / `Message` | Real SQL conversations/messages, returning-visitor token and rule-based greeting already exist. No need to recreate storage. |
| Intake | `Domain/Conversations/Actions/ReceiveWebChatMessage.php` | Bot already asks name → phone/email → service. Phone/email can survive in encoded widget state/transcript, but contact/lead capture waits for the service answer. Abandonment at that point is the capture gap. |
| CRM capture | `Domain/Crm/Actions/CaptureLead.php` | Existing single entry point handles normalization, contact matching, lead merge rules, assignment, scoring, timeline and events. Reuse it; do not insert leads independently from adapters. |
| Popup | `Livewire/Admin/Conversations/LiveChatBell.php` and view | Backend already opens the closed drawer on a new visitor message, using session baseline and visible polling (10 seconds). Actual failure needs reproduction; missing implementation is not established. |
| Popup documentation | `resources/js/admin.js` chat-alert comment | Comment says the drawer never auto-opens; contradicts current backend behavior. Reconcile documented policy, implementation and tests. |
| Lead navigation | `Livewire/Admin/Hubs/LeadsHub.php` | List / Pipeline / Tracking already exist. Add website-chat tracking to this hub with proper permissions, not an isolated new application. |
| Channels | `Domain/Conversations/Enums/Channel.php` | Only WhatsApp, WebChat and Email. Many `LeadSource` values are attribution labels, not working channel transports. |
| Facebook connector | `Integrations/Connectors/FacebookPageConnector.php`, `ConnectorRegistry.php` | Page-token/identity check exists; it explicitly does not implement publishing. Identity proof does not prove Messenger, lead-form ingestion or comment replies. Graph URL currently uses v23.0; review compatibility before extending. |
| Social publishing | `Domain/Marketing/Social/SocialPublisher.php` | `connectedAccounts()` returns an empty list. Planner/review/scheduling exist; live publishing transport is not implemented here. No “connected” or “published” claims until provider evidence exists. |
| Automation | `Domain/Automation/Enums/SequenceTrigger.php`, `Actions/AutomatedSendGuard.php` | Existing lead/quotation/invoice/visit/lost/manual triggers; quiet-hour, reply-grace and daily-cap code. `sentToday()` currently counts WhatsApp conversations: not a proven cross-channel cap. A local reply grace is not provider permission to send. |
| Telegram | `Integrations/Telegram/*`, `Http/Controllers/Webhooks/TelegramWebhookController.php`, System Integrations | Configuration diagnostics, identity/webhook checks, pairing/QR, queued updates and personal test action already exist. No live production connection was checked in this audit. |
| Telegram authority | webhook controller / `HandleTelegramCommand.php` | Personal commands deliberately require a human private chat. Group/channel routing identities must never inherit private staff privileges. |
| Telegram test target | `Livewire/Admin/System/Integrations.php::testTelegram()` | Existing test sends to the acting user's linked Telegram notification destination. It is not a channel-selection or arbitrary-phone-number sender. |

### Evidence limits

The screenshot establishes an unwanted header pill, not the status of production records. Current popup failure has not been reproduced against a real incoming production visitor session. Production bot/token/webhook state, provider approvals, destination ownership and mobile receipt are **unverified**. A historical missing-token blocker is not today's demonstrated root cause.

## 3. Target experience

**Visitor:** open widget → greeting/name → valid number or email → enquiry saved immediately → optional service/location/budget → human handoff. Closing before the last question must not lose a valid contact enquiry. Anonymous chats remain visible without being invented as qualified leads.

**Staff:** incoming website message → badge/alert and safe auto-opening drawer → reply or assign → open the same transcript in Inbox → open the linked lead → follow up. No draft loss or duplicate conversation when switching views.

**Owner:** Leads → Web Chat → see anonymous, contact captured, needs qualification, assigned, overdue, converted and closed states. Channel Tracking shows attribution and transport health separately. “Facebook source” is not presented as “Facebook Messenger connected.”

## 4. Ordered implementation / TODO backlog

### 48-A — Approved native slice: locally delivered

#### A1. Theme and header

- [x] Define native semantic tokens for page, surface, elevated surface, sidebar/header, border, primary/soft-primary, text/muted text, input, focus and success/warning/error. Apply consistently instead of adding broad unscoped color overrides.
- [x] Deliver **Woodex Light / Woodex Night**, plus **Light / Dark / System** preference. Night should use calm dark navy/slate surfaces and restrained blue/wood accents, not harsh white cards on a black page. Preline is a reference, not a licensed CRM template imported wholesale.
- [x] Migrate existing `wx.theme` choices without losing manual preferences; resolve System before first paint; handle blocked localStorage without breaking navigation; update on system-theme changes and synchronize tabs.
- [x] Cover shared navigation, tables, forms, filters, dialogs, toasts, status badges, empty/loading/error states, chart labels/grid/tooltips, Assistant, Inbox, Notifications, Activity and the live-chat drawer. Reinitialize safely across Livewire navigation; no duplicate event listeners.
- [x] Keep document paper/PDF previews white and brand-correct in either theme. Do not invert quotation/invoice output or rewrite frozen documents. Public v1/site-builder output is not redesigned.
- [x] Move the header's “Demo data” pill out of the top bar. Show an accurate **Local preview** environment label in the user/environment menu and relevant system diagnostics. Do not infer all local records are demo, imply a preview is production, remove owner data, or disable safety indicators.
- [x] Verify keyboard focus and contrast: normal text 4.5:1; large text and meaningful UI boundaries 3:1. Statuses need labels/icons, not color alone.

#### A2. Reliable website-chat popup

- [x] Reproduce inbound delivery in separate visitor/staff browser contexts before changing logic. Instrument poll/alert state without logging visitor content or tokens.
- [x] Trace new-message baseline, unread/read transitions, selected thread, route navigation, reopened conversations, multiple tabs, session state, permissions and inactive-tab catch-up.
- [x] Closed drawer: a new inbound visitor message opens the correct thread once. Own replies, bot messages and historical unread messages do not repeatedly reopen it.
- [x] Same open thread: append/highlight the message without stealing the composer focus. Different thread while drafting: preserve the draft and present an explicit switch action rather than silently replace it.
- [x] Keep the existing lightweight poll transport initially. Target one visible polling interval plus request time (roughly 10 seconds under normal conditions), **not a promised subsecond/push service**. Resume/catch up on focus; offline and background browser behavior must be explained honestly.
- [x] Prevent toasts, dropdowns and drawers from intercepting Send/Close; preserve revision47's bounded-toast fix. Sound remains optional and browser-autoplay dependent.
- [x] Correct the stale click-only comment and lock the agreed behavior in feature and browser tests.

#### A3. Early capture and Web Chat Leads tab

- [x] Surface a dedicated **Leads → Web Chat** tab powered by existing SQL conversations, contacts, leads and messages. Include anonymous/partial intake, linked lead, last visitor message, owner, unread, reply due, status and capture stage where derivable from existing data.
- [x] Search/filter by contact, number/email, date, assignee, unread, unassigned, overdue and capture status. Show genuine empty/error states; no demo metrics.
- [x] After validation of a phone/email, call the existing `CaptureLead` workflow immediately using the existing unqualified/default enquiry path. Service can be collected afterward and update the same enquiry. Confirm current required fields/default semantics before coding; do not invent interest, budget or marketing consent.
- [x] Normalize Pakistani and international numbers through existing helpers. Display **provided/unverified**, not verified ownership. Allow email/no-phone and human-assistance paths; invalid input must never advance the intake state or create junk CRM records.
- [x] Make capture/link/state updates transactional and retry-safe. Respect existing contact matching and open-lead merge rules. A shared phone is not authorization to expose another visitor's transcript.
- [x] Connect widget → transcript → contact → lead → activity without creating a second lead on refresh, service answer or repeated submission. Assess existing `LeadCaptured` listeners so enrichment/retries do not double-enroll or send follow-ups.
- [x] Preserve all anonymous messages before identification. Show accurate missing fields and incomplete stage, not guessed customer identities.
- [x] Human takeover pauses intake replies; reopening/returning visitors retain history. Add a clear handoff/offline expectation without false “agent online” promises.
- [x] Use existing schema only in 48-A. If reliable attribution/state/idempotency needs new fields or constraints, document the precise limitation and request additive-schema approval rather than silently encoding unlimited metadata in labels/state strings.

**48-A tracking limit:** existing SQL can support conversations, capture stage and CRM links. New historical page-view/session/UTM timelines are not magically available. Derive only what is stored today; mark unavailable fields honestly. Structured cross-channel event/attribution storage belongs to 48-B after schema approval.

#### A4. Telegram audit alongside 48-A

- [x] Read sanitized local configuration/diagnostic flags; distinguish wrong environment file, stale cached config, wrong bot identity, missing token/secret, webhook mismatch, queue errors and an unlinked personal destination.
- [x] Fix only reproduced application/diagnostic defects, with faked tests first. Do not assume the token is absent or regenerate credentials to make a status green.
- [x] Prepare the owner-side verification checklist in §7. A preview code repair is not proof of production delivery.

**48-A exit:** A1–A4 code/UX work demonstrated locally, focused/full regression gates recorded, desktop/mobile browser capture→popup→reply→lead trace passes, and Telegram blockers are truthful. Live external tests are a separate gate.

### 48-B — Shared channel foundation and attribution (schema approval required)

- [ ] Specify adapters by capability: authenticate, verify identity, ingest lead form, receive message, reply privately, manage comments, publish, analytics. Do not use one Boolean “connected.”
- [ ] Present distinct states: not implemented, needs setup, credentials saved/unverified, identity verified, permission missing, webhook subscribed, transport healthy, delivery confirmed, degraded/revoked. Show last check and error without tokens.
- [ ] Propose additive storage for channel accounts/permissions/token expiry, external conversation/contact mappings, inbound event deduplication, outbound attempts/provider statuses and structured attribution/consent. Reuse existing connection and webhook-event models where suitable before proposing tables.
- [ ] Scope deduplication to provider + account + event/message identity; handle retries, concurrent delivery, ordering and account disconnects. Keep a visible retry/dead-letter trail and auditable actor actions.
- [ ] Normalize adapter events into existing conversation, message and `CaptureLead` workflows. Separate first/last-touch source, campaign/UTM, landing/referrer and external lead ID; validate lengths and reject sensitive URL query data.
- [ ] Cross-channel identity links need verified evidence or staff confirmation; do not merge people by display name, a lookalike username or guessed phone.
- [ ] Verify webhook signatures/verification challenges, least-privilege RBAC, encrypted credentials, secure secret rotation, attachment constraints, replay protection, privacy/redaction, retention/export/deletion and rate limits.
- [ ] Connected-account settings need real Connect / Details / Verify / Disconnect / delivery history actions. Prevent publishing/replying through an identity-only connector.

### 48-C — WhatsApp operational certification, then Meta expansion

- [ ] **WhatsApp first:** reuse existing Cloud API client, credentials, webhook receive/media handling, templates and checks. Verify actual WABA/phone identity, permissions, signed webhook ingress, provider windows, approved templates, consent/STOP, queued send, failure/retry and delivered/read statuses.
- [ ] Ensure returning/late/replayed events cannot reopen a fresh provider send window incorrectly. Keep direct replies and marketing automation distinct.
- [ ] **Facebook Page leads:** implement lead-form ingestion separately from Page identity and Messenger. Verify approved scopes, subscribed assets and sample lead data mapped to source/campaign/form/contact.
- [ ] **Messenger:** separate receive/reply adapter, scoped identities and correct policy enforcement. Verify actual Page/app permissions and a designated test user; no arbitrary-account sends.
- [ ] **Instagram professional messaging/comments**, if requested and eligible: separate account-link/access assessment and scoped adapters. Do not advertise support by reusing a Facebook logo or source label.
- [ ] **Publishing and public comment replies:** separate approval workflow/transport from private chat and lead forms. Scheduled posts remain pending/blocked until a real supported transport exists.

**Exit per transport:** signed inbound event → one durable native record → correct CRM/source link → permitted staff response → provider acknowledgement/status evidence. Sandbox/faked tests alone do not pass the live gate.

### 48-D — Additional channels, only for verified supported capabilities

- [ ] LinkedIn: pursue approved Lead Sync and relevant publishing/community products; assess each permission/product independently. Do not promise general automated private DMs.
- [ ] Google Business Profile (GMB): profile/review/website-contact attribution scope; do not rebuild retired native Business Messages. Eligible profile WhatsApp/text contact links route to those actual transports, not a fictional Google chat inbox.
- [ ] Pinterest: approved Pins/boards/publishing, analytics and tracked landing-page conversions first. No generic private-message transport is established by the API reference reviewed here.
- [ ] TikTok: verify exact business/developer product, account eligibility, region, lead-form and content-posting access before implementation. Messaging/lead access is **unverified** for Woodex; do not assume a general DM API.
- [ ] Other providers: manual source capture or tracked links can ship independently of API access, clearly labeled manual/tracked rather than connected.

### 48-E — Automation/control centre

- [ ] Reuse existing sequences, assignment/SLA and platform-jobs dashboard. Add event-driven enrollment only after reliable ingestion/deduplication is in place.
- [ ] Rules: new valid enquiry → assign; after-hours acknowledgement; no first reply → escalation; incomplete intake → staff task; qualified enquiry → follow-up; opt-out/human takeover/closed-lost → stop appropriate flows.
- [ ] Every rule must show trigger, channel capability, conditions, delay, quiet hours/timezone, template, permitted recipient, cap, owner, pause switch and run history. Provide dry-run/preview before enabling external sends.
- [ ] Generalize cap/window/consent enforcement by provider and account; current WhatsApp-only counting and a local 60-minute reply grace cannot authorize every network.
- [ ] Retry idempotently; prevent bot-to-bot loops, duplicate enrollment and replayed sends. Delivery accepted, delivered and read are separate states.
- [ ] Keep the website intake deterministic initially. Any later generative replies need approved knowledge, refusal/escalation, privacy/cost limits and human takeover; no autonomous financial edits or unrestricted cross-channel outreach.

### 48-F — Production rollout and handover

- [ ] Owner-approved backup/restore check, additive migration review, scheduler/worker verification, HTTPS webhook endpoint and deployment/rollback plan.
- [ ] Certify one account/channel at a time with a consented recipient; save redacted event/status evidence and owner-confirmed mobile receipt where applicable.
- [ ] Monitor queue lag, webhook failures, expired/revoked tokens, retry/dead-letter volume, capture conversion, missed replies and SLA. Define alerts and disable switches before unattended sends.
- [ ] Update project status/runbook; preserve URLs, v1 output, historical documents and financial results. No deploy/merge is authorized by this planning report.

## 5. Channel capability / dependency matrix

“Existing code” below is not a claim that an account is live.

| Channel | Existing native baseline | Next useful scope | Owner/provider gate / honest limitation |
|---|---|---|---|
| Website | SQL widget intake, Inbox, staff drawer | Early capture + tracking + alert reliability | No external API required; deployment state still unverified |
| WhatsApp | Cloud API gateway, receive, templates/checks | Certify account, event→lead→reply→status | Business assets, approved access, phone identity, consented recipient and worker/webhook health |
| Facebook | Page identity connector; source labels | Lead forms, then Messenger; publishing/comments separately | Meta app/product permissions and asset subscriptions; identity is not transport |
| Instagram | No demonstrated native conversation channel in audited enum | Eligibility assessment then scoped adapter | Account/product eligibility and approved permissions; no current live claim |
| LinkedIn | Source label / social-planning context | Approved Lead Sync, then appropriate content/community access | Product approval and scoped OAuth; no generic-DM commitment |
| Google Business Profile | Source attribution, not native chat transport | Profile/review/contact attribution as approved | Legacy native chat ended; eligible WhatsApp/SMS links are different transports |
| Pinterest | Source/planning context, no demonstrated DM adapter | Pins/boards/analytics + tracked conversions | Approved app/account scopes; reviewed reference does not establish general DMs |
| TikTok | Source attribution; no demonstrated native adapter | Product/access audit; approved lead/content scope | Current account/region/product eligibility unknown; no universal chat promise |
| Telegram | Bot gateway, health, personal pairing/commands/test | Verify mobile pairing; channel posting separately | Confirm actual bot and destination ownership; personal authority must not be granted to a channel |
| Email | Conversation channel enum exists | Separate gateway/runtime audit if prioritized | Enum alone is not proof of receive/send delivery |

A practical initial priority is **Website → WhatsApp → Facebook leads/Messenger → eligible Instagram → remaining approved products**. Telegram staff alerts can be verified in parallel once ownership/destination is clear.

## 6. Acceptance tests and reporting

### Local acceptance matrix for 48-A

1. Light/Dark/System on desktop 1440px and mobile 390px; reload, Livewire navigation, changed OS theme, multiple tabs, blocked storage and no first-paint theme flash/JS errors.
2. Shared forms/tables/menus/dialogs/charts/Assistant in both modes; keyboard focus, meaningful status contrast and no horizontal overflow. PDF/HTML quotation and invoice previews remain paper-correct.
3. No “Demo data” header pill; preview/environment truth accessible elsewhere. No data deletion or APP_ENV change.
4. Two real browser contexts: visitor sends → staff drawer opens correct conversation within a visible poll interval → staff replies → visitor receives same-thread reply; database trace matches both views.
5. Repeat with drawer closed, same thread open, other-thread draft, reopened thread, route navigation, multi-tab session, hidden/focused tab, network failure/retry and mobile viewport. No repeated popup for own/bot/historical messages.
6. Visitor supplies valid phone/email then leaves before service: Web Chat tab and CRM show a linked unqualified enquiry. Resume/service completion/retry does not duplicate it. Anonymous chats remain accessible without fabricated contacts.
7. Invalid number, empty/name-only chat, existing contact/open lead, no consent, human takeover, unauthorized staff and guessed/cross-visitor token tests. No automatic marketing opt-in from submitting a phone number.
8. Capture/action/event retry tests verify assignment, activity and sequence side effects exactly as intended; no duplicate notifications or outbound automation from replay.
9. Telegram application paths use faked API responses: absent/stale config, wrong identity, webhook mismatch, auth failure, queue/recipient missing, token redaction and private-vs-channel security. Do not label these as real mobile delivery.
10. Focused tests, full Pest suite, PHPStan, Pint, production asset build, browser screenshot/trace evidence, `git diff --check` and a short release report. Re-run affected quotation/invoice/financial tests after shared theme changes. Do not silently overwrite locked public v1 build artifacts.

### Live gate for each external provider

Document the approved account/destination, permissions, webhook subscription/signature, inbound event ID, linked SQL records, allowed outbound attempt, provider response/status, failure/retry behavior and disconnect handling. Redact secrets and personal data. Report **blocked**, **application-tested**, **provider-accepted** and **recipient-confirmed** separately; accepted is not read.

## 7. Telegram repair and mobile/channel test checklist

`woodexinterior` is not enough to determine whether the owner means a bot username, public channel or personal recipient. Do not guess an account or derive an arbitrary bot destination from a mobile phone number.

1. Owner confirms the bot identity and whether the target is **their private Telegram on mobile**, **the @woodexinterior channel**, or both. Verify exact destination and authority through the secured native settings—not by posting tokens in chat.
2. On the real deployment, inspect sanitized configuration-source/cache flags; check the expected bot identity and webhook URL/secret, pending updates and last error. No replacement of an existing production webhook without specific approval.
3. Private mobile: owner initiates the bot and pairs using the existing one-time link/QR. Confirm the active staff/private link; preserve one-use/expiry and user permissions.
4. Channel: confirm ownership and bot posting/admin permissions, then verify the intended channel identity. Channel broadcasting is a separate destination/authorization feature; never repurpose a privileged private staff link or accept channel posts as personal commands.
5. Confirm HTTPS ingress, webhook secret validation, worker/scheduler liveness and a processed pairing/update event. Preserve pending updates during any approved webhook registration.
6. Request approval for **one clearly labeled Woodex OS test** to the confirmed destination. Use the native action where suitable; do not broaden its current private-user target silently.
7. Record sanitized provider result and ask the owner to confirm receipt on mobile. A channel post may produce a mobile notification only according to the user's Telegram subscription/notification settings; the OS cannot guarantee a push notification.
8. Exercise failure/retry safely; record the actual cause and fix. No test may expose customer/bank data or secrets.

**Sandbox limitation:** the coding workspace's outbound host allowlist does not include `api.telegram.org` or Meta/provider APIs. Direct live transport checks/sends must run through the owner's approved deployed environment. A documentation fetch or mocked test does not bypass that restriction or prove a live connection.

## 8. Verification performed for this planning revision

- Source audit of header/theme, Assistant, widget/database/intake/capture, LiveChatBell, Leads hub, channel/source enums, connector registry/Facebook identity, social publishing, automation guards and Telegram diagnostics/pairing/security/test destination.
- Read-only planning: **no application implementation, migrations, production config inspection, provider API calls, outbound messages, webhook replacement, deploy or merge**.
- Targeted baseline tests in `/tmp/woodex-run/app`, using the isolated test database and provider fakes: **98 passed, 513 assertions, 12.12 seconds, exit 0**.
- Suites: `P10LiveChatTest`, `Conversations/WebChatWidgetTest`, `TelegramDiagnosticsTest`, `TelegramSecurityTest`, `Telegram/TelegramPairingTest`, `AutomationsDashboardTest`, `SocialPlannerTest`.
- Durable log: [`evidence/48/audit-regression.txt`](evidence/48/audit-regression.txt).
- This is targeted baseline confidence, not a new whole-OS/production certification. Revision47's completed gates remain in its own report.

## 9. External references and verification limits

- [Preline CRM dashboard reference](https://preline.co/templates/dashboards/crm-dashboard/) and [themes documentation](https://preline.co/docs/themes.html): reviewed shell/theming guidance; semantic CSS variables, light/dark adaptation and presets inform the native design. No paid CRM template source was reviewed/imported; no additional license entitlement assumed.
- [Meta Messenger platform](https://developers.facebook.com/documentation/business-messaging/messenger-platform): official overview exposes messaging/webhooks as distinct integration capabilities; this does not establish Woodex's permissions or access.
- LinkedIn's official Lead Sync access documentation requires approved product access and scoped permissions. [1](https://learn.microsoft.com/en-us/linkedin/marketing/lead-sync/getting-access-leadsync?view=li-lms-2026-08)
- Google's official help confirms native Business Profile chat/call history ended on 31 July 2024; eligible profiles can instead expose WhatsApp/text contact options. This is not a restored Google Business Messages transport. [1](https://support.google.com/business/answer/14919056?hl=en)
- [Pinterest REST API reference](https://developers.pinterest.com/docs/api/v5/introduction/?): official documented scope includes Pins/boards, ads, conversions, shopping and analytics; no general private-chat capability established by that overview.
- Telegram Bot API and TikTok documentation fetches were denied (403); initially attempted Google/LinkedIn overview URLs returned 404 and were not used as evidence. Telegram findings above are grounded in existing native source/security behavior; detailed current provider requirements must be verified on implementation. TikTok's exact lead/messaging access remains an explicit discovery gate, not a promised feature.

## 10. Decision and delivery status

**Owner approved 48-A; locally delivered:** Woodex Light/Night/System + honest header cleanup + reliable website-chat popup + early CRM capture + dedicated Web Chat Leads tab, using existing schema and native modules, plus safe Telegram application diagnostics. Later social transports, new schema and real provider sends remain separately gated.

48-A completion, evidence and verification limits are recorded in [the implementation report](48A_NATIVE_THEME_WEB_CHAT_UPGRADE_2026-10-29.md). Later phases remain unchecked; no new schema or real provider sends were authorized/performed. The owner will provide connection details later.
