# Revision48-A — Native theme and website-chat capture upgrade

**Date:** 29 October 2026 · **Plan:** [revision48](48_THEME_OMNICHANNEL_MASTER_PLAN_2026-10-29.md) · **Status:** implemented and locally verified; no production deployment/merge.

Owner approved starting the recommended native work and will provide connection details later. This release implements 48-A, not the future external-provider transports or schema phases. Existing modules and SQL records are reused.

## Delivered

### 1. Woodex Light / Night / System

- Native three-mode selector in the admin header. Existing `wx.theme=light/dark` preferences remain valid; absent/invalid preferences follow the device.
- Pre-paint resolution, live device changes in System mode, cross-tab synchronization and a usable in-memory fallback when browser storage is blocked. Sidebar preference storage also tolerates blocked storage.
- Semantic page/surface/elevated/text/muted/border/control/primary/focus tokens, calm navy Night surfaces and accessible primary labels. Shared cards, forms, tracking panels, conversation canvases, badges, header, Assistant bubbles and status surfaces adapt without a framework/theme-package replacement.
- Existing charts update axis/legend text, grid, tooltip and default series colors without resetting the displayed data. Explicit per-chart colors remain respected.
- Shared focus styling and reduced-motion handling. Measured semantic text/background pairs exceed 4.5:1; control border/focus pairs exceed 3:1. This is **not** a whole-OS WCAG certification.
- Public v1 assets, shared brand token source and document HTML/PDF renderers are unchanged. Browser checks rendered real Interior, Furniture and invoice PDFs with white paper and visible ink in Night mode; the native invoice/quotation HTML previews remained separate document frames.

### 2. Honest header cleanup

Removed the top-nav **Demo data** pill. It inferred record provenance solely from `app()->isLocal()`, which was not a database check. A **Local preview** note now lives in the user menu, explaining that sample records may exist and the label describes the environment, not ownership. Other nonproduction environments are labeled as such; production receives no preview label.

No APP_ENV changes, record deletion, reseeding or fake production status were used.

### 3. Capture before abandonment

- A validated phone/email immediately invokes the existing `CaptureLead` path and links the conversation/contact/lead **before** the visitor answers the service question.
- Capture retains the existing default business-line/new-enquiry semantics; supplying a contact does not prove service intent, phone ownership, qualification or messaging/marketing consent. Staff can qualify/change the enquiry through existing CRM controls.
- Intake state transitions and capture/link writes run in one transaction with the conversation reloaded under lock. Stale models and repeated identification cannot recapture the same thread.
- Service completion enriches the linked enquiry, records incoming activity and does **not** fire another `LeadCaptured` event. If capture merged an existing open lead, its established service is not overwritten.
- New contact data is stored in existing structured contact columns. New `widget_state` is the short `asked_service` marker, not a phone/email payload. This also avoids overflowing the existing `varchar(120)` state column with long valid emails.
- Old encoded `asked_service|...` states remain readable/resumable. The new tracking view shows valid legacy contact data read-only and labels CRM capture pending; there is **no historical backfill**.
- Email-only capture creates the existing follow-up-task anchor before after-response jobs run, avoiding an extra automatic ask-for-number email. Phone/WhatsApp/marketing consent is not inferred from contact submission.
- A real staff reply stops competing rule-based intake/bot replies. Internal notes do not count as takeover. Conversations handed to staff before identification remain visible for manual follow-up; this release does not infer contact details from arbitrary later free text.
- Incoming messages reopen closed website conversations. The first actual staff web-chat reply records the linked lead's first-response timestamp without rewriting it on subsequent replies. Historical response timestamps are not backfilled.
- Public service text is stored as incoming system activity, **not** passed through the staff-note mention command path. A visitor typing `@name` cannot turn qualification into privileged staff mention notifications.

### 4. Leads → Web Chat

New linkable native hub tab: **`/admin/leads?tab=web_chat`**.

- Real SQL-backed anonymous, partial, linked and closed conversation records; no separate database or browser-only CRM.
- Access-scoped totals, contact-unlinked, needs-service and unread counts. Summaries describe the access scope; the result count describes the current filters.
- Search by name/phone/email/lead reference; capture/unread/unassigned/my-chat/overdue focus; status and started-date filters; reset and pagination. Invalid dates are explicitly reported/ignored rather than passed into database date parsing. Started-date controls and row timestamps consistently use PKT: local midnight is converted to UTC, with an inclusive start and exclusive next-day end.
- Contact ownership shown as unverified; latest visitor message, chat assignee, message count, lead first-response/SLA information, start time in PKT and a permission-checked lead link.
- **Open conversation** targets the actual website thread in the existing drawer. Existing Inbox remains the reply/assignment workspace; no duplicate conversation UI or provider transport was invented.
- Both `leads.view` and `inbox.view` are required. Linked leads respect existing `leads.view_all`/owner visibility; anonymous intake is available to staff with both permissions. Other channel records are excluded.
- Visible-page refresh and honest empty states. Structured new UTM/page-view/session analytics remain a later storage phase; missing historical data is not fabricated.

### 5. Reliable alerts and draft protection

Backend automatic opening already existed; this release did not pretend it was missing. It improves the existing implementation:

- New eligible inbound website messages open the correct closed drawer. Announcement ordering uses the same monotonically increasing message ID as the session baseline.
- A matching open thread updates/marks read without replacing the composer draft. Own/bot/history messages do not repeatedly reopen the drawer.
- Another thread's inbound message produces an explicit pending-thread action; current drafts survive even when the drawer was closed. Discarding a draft to switch requires confirmation.
- Reopening/clicking the **same** thread also preserves the draft, including a toast/tracking-row request. Direct thread events validate channel/access before opening.
- Toast actions carry the correct thread identity. The previous misleading click-only comment was corrected. Revision47's nonintercepting toast placement remains intact.
- Staff window focus/visibility restoration triggers catch-up. This remains **10-second visible polling plus request time**, not a subsecond websocket/push promise. Browser background suspension, sound/autoplay and intentional snooze behavior still apply.

### 6. Telegram: verified blocker, no fake connection

A read-only **local preview** configuration probe found:

| Flag | Local result |
|---|---|
| Environment file | Readable |
| Config cache | Not cached |
| Bot token loaded / present in selected file | No / No |
| Webhook secret loaded / present in selected file | No / No |
| File agrees with runtime | Yes |

Only booleans were recorded; no token/secret values or private paths are in the evidence. This is a local-preview diagnosis, **not a claim about production**. Existing bot diagnostics, pairing/security and test paths were exercised with provider fakes. No reproduced Telegram application defect justified a speculative rewrite.

No bot account was connected, webhook replaced, or message sent. The owner can provide setup details later through secured native settings. The actual bot and destination must be confirmed: a personal mobile chat is different from `@woodexinterior` channel posting, and a phone number alone is not a bot destination. Private staff authority must not be granted to a channel. The sandbox outbound allowlist also excludes provider API hosts; a real verification/send must run on the approved deployment.

## Verification and scope of the gates

| Gate | Result |
|---|---|
| Full Pest suite | **1722 passed /1 skipped /9199 assertions**, 463.21s, exit0 |
| Final affected suites after the three narrow safety guards | **218 passed /884 assertions**, 30.54s, exit0 |
| New dedicated regression cases | **26**, included in the final affected gate |
| PHPStan after final application edits | **0 errors** |
| Pint final changed application/test files | **8 files pass**; fixture separately formatted/linted |
| Actual theme-store unit tests | **7 pass**, including blocked storage/device/cross-tab behavior |
| Vite production build | Success; existing large ApexCharts chunk advisory only |
| Native Chromium walkthrough | **32 checks pass**, desktop1440px/mobile390px, **0 page JS errors** |
| Post-guard native browser proof | **5 checks pass**, serialized server response and real draft retention, no customer message emitted |
| Semantic contrast measurement | **14 text/control/focus pairs pass**; [values](evidence/48A/contrast.json) |
| Patch/source checks | `git diff --check` passes; changed runtime application/test files match the repository; no schema/public-v1/document-renderer/financial-domain changes |

**Gate ordering:** the full suite completed before the final same-thread draft, public-mention isolation and PKT date-boundary guards. The final218 affected tests and PHPStan cover all three final application changes. The32-check native walkthrough precedes those guards; the extra5 native checks verify the final draft behavior, and feature regressions verify public mention isolation and PKT midnight filtering. Counts are separate gates, not a sum of unique tests. Do not describe this as production/provider or every-action whole-OS certification.

The affected selection includes new capture/theme tests, communications redesign, P10 live chat/hubs, widget HTTP endpoints, payment-integrity repairs and Telegram diagnostics/security/pairing. The main preview still has **0 conversations /0 QA48 contacts**; browser mutations and the synthetic invoice/PDF fixture ran only in a guarded, separately copied local QA database with array mail and blank provider tokens.

### Browser evidence

- [Light tracking](evidence/48A/48-web-chat-light.png) · [Night tracking](evidence/48A/48-web-chat-night.png) · [Mobile tracking](evidence/48A/48-web-chat-night-mobile.png)
- [Early phone capture on mobile](evidence/48A/48-visitor-early-capture-mobile.png) · [Captured enquiry search](evidence/48A/48-browser-lead-tracking.png)
- [Draft-safe other-thread alert](evidence/48A/48-draft-safe-alert.png) · [Same-thread draft guard](evidence/48A/48-same-thread-draft-guard.png) · [Mobile chat](evidence/48A/48-night-chat-mobile.png)
- [Dashboard/charts](evidence/48A/48-dashboard-night.png) · [Assistant](evidence/48A/48-assistant-night.png) · [Inbox](evidence/48A/48-inbox-night.png) · [Notifications](evidence/48A/48-notifications-night.png) · [Lead activity](evidence/48A/48-lead-activity-night.png)
- [Interior PDF](evidence/48A/48-interior-pdf-night.png) · [Furniture PDF](evidence/48A/48-furniture-pdf-night.png) · [Invoice PDF](evidence/48A/48-invoice-pdf-night.png) · [Invoice HTML preview](evidence/48A/48-invoice-live-preview-night.png)
- [Verification manifest](evidence/48A/verification.txt) · [32 browser checks](evidence/48A/48-browser-results.json) · [5 draft checks](evidence/48A/48-draft-guard-results.json)

All screenshots use clearly synthetic isolated QA records, not imported owner/customer/bank data. Browser harness corrections addressed transition timing, native route/action selection and the serverless Chromium incognito limitation; completed checks use real visible controls and normal endpoints, not JavaScript bypasses of actions.

## Unchanged / next phases

- No production credentials, provider account connections, real Telegram/mobile sends, outbound provider transports, schema migrations, historical backfill, deployment or merge.
- Website widget enable/disable settings remain respected. The main preview is not populated with fake chats just to make the new tab look busy.
- Social source labels and Facebook Page identity checks are still not advertised as Messenger/lead-form/publishing transport. Existing WhatsApp code is reused, not newly certified live.
- Next: **48-B shared channel/account/event/attribution foundation and explicit additive-storage review**, then account-specific WhatsApp/Meta certification and later supported provider products. Provider access/details can be supplied later; no question or secret disclosure is needed to use the completed local UI.
- F05 semantics, production reconciliation, production concurrency/load/real-device behavior and whole-OS every-action certification remain separately open. Frozen quotation/invoice history, financial repairs and public v1 URLs/assets are preserved.
