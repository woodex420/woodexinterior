# 47 — CRM and communications redesign

**Approval:** owner approved the five-area scope via “Approve — start”. **Date:** 29 October 2026.

## Locked scope and reuse

Redevelop Leads list/detail, Alerts dropdown/centre, Inbox, Activity timeline/drawer, and staff/visitor live chat in existing Laravel/Livewire. Reuse LeadsIndex/LeadShow, NotificationBell/NotificationsPage, Inbox/LiveChatBell, existing CRM activities, conversation services and widget endpoints. No new CRM/chat engine, schema, provider-send policy, financial changes, deployment or merge.

Reference: owner screenshots plus https://preline.co/templates/dashboards/admin-dashboard/?page=users.html (fetched preview shell; licensed template source not imported). Adapt the visual hierarchy to Woodex navy, existing TailAdmin/Preline tokens and native actions. Do not copy reference demo people/messages, fabricate presence, or show unsupported calls/voice-recording buttons.

## Read-only audit before implementation

- Leads already has permission-scoped search, source/stage/owner/line/month filters, saved views, columns, CSV, bulk operations, a quick drawer and full lead actions. Preserve them; improve workspace hierarchy and expose real follow-up shortcuts rather than duplicating services.
- Activity already exists on lead detail and quick drawer. Reuse recorded actor/type/time/body; add practical filtering and coordinated timeline presentation. Do not mix financial audit logs into an unrestricted new global feed.
- Notification dropdown/centre lack archive and notification search. Both redirect directly to the persisted URL without restricting external/scheme-relative destinations. Add shared own-user actions, JSON metadata archive (no migration) and internal-only navigation.
- Inbox Unread tab renders duplicate wire:click attributes and conflicting active state; it can behave as Open instead of Unread. Channel filtering exists in PHP but is not exposed. No assignment filter; assignment accepts arbitrary IDs without active-staff validation. Selected render helper does not reauthorize view on hydrated requests. Fix within existing policies.
- LiveChatBell selected ID is public; render reads it without enforcing WebChat channel or fresh view authorization. Drawer selection can target other channels and mark them read. Scope every selected thread and read mutation to authorized website conversations.
- Both staff chat surfaces fetch oldest messages without a bounded latest-history strategy (drawer limits first100). Redesign with bounded recent history and explicit load-earlier controls.
- Visitor widget clears input before send completes and loses text on network failure. Add retained drafts/retry, connecting/offline feedback, accessible panel, and updated layout without fabricated online presence.

## Execution checklist

- [x] Owner approval, existing-module audit and bounded repair list.
- [x] Implement all five visual surfaces and related functional repairs.
- [x] Native regression/security tests; build/static checks.
- [x] Desktop/mobile Chromium verification and durable screenshots.
- [x] Final results/limitations, status/evidence and PR source increment.

45-B remains preserved. F05 semantics, historical financial correction, real external sends, MySQL/concurrency/production-host certification and whole-OS every-action attestation remain separate.

## Delivered surfaces

| Surface | Native implementation |
| --- | --- |
|Advanced Leads|Permission-scoped matching/due/unassigned summary; actionable focus shortcuts; redesigned filter panel/table; existing line tabs, month/source/stage/owner filters, saved views, columns, CSV, pipeline/tracking and bulk actions retained. Lead detail has section navigation and contextual activity filtering.|
|Alerts|Coordinated dropdown and Notification centre, All/Unread/Archived views, centre search, readable previews and exact timestamps, own-user archive/restore/read operations. Archive uses existing JSON metadata, preserves content/URL and does not delete rows; restoring leaves an already-read item read. Internal-only notification navigation rejects external, scheme-relative, control-character and backslash destinations.|
|Inbox|Two-pane desktop / focused single-pane mobile, channel and assignment filters, exclusive Unread state, matching filter counts, channel/assignee labels, permission-safe lead link, date-grouped escaped messages, native attachments/delivery states/canned replies/notes retained. Website replies no longer show false WhatsApp-window warnings. Latest60 messages plus load-earlier up to600; select resets the limit.|
|Activity|Existing lead/contact recorded events, actor/type/outcome/time and mention-safe body retained; type filter and bounded earlier-history loading; coordinated full timeline and quick drawer. Not a new unrestricted global audit feed.|
|Live chat|Website-only authorized drawer, unread/new-message polling and sound preference retained, responsive visitor list/thread/composer, read-only staff controls hidden, latest-history loading and input errors. Visitor widget now shows connecting/failure/retry status, retains draft until confirmed success, has accessible panel/log, Escape/launcher focus and phone layout. Recent-message indicator retained (not verified online presence); no unsupported call/recording buttons.|

The browser found a real cross-surface defect: the bottom-right **Sent** toast physically intercepted the drawer Send button after an Inbox reply. Hit testing identified the toast, not an authorization/provider failure. Toasts are now viewport-bounded and positioned away from right-hand composers (desktop lower-left / mobile upper-left). The drawer also has a keyed composer and explicit native click binding. Final browser verification exercises Inbox send followed immediately by drawer send and checks exact DB message counts, preventing a fake visual-only success.

## Verification results

- Dedicated new regressions: **24 passed /82 assertions /5.92s**. Own-user archive/restore/search, unsafe redirects, foreign mutations, Unread/channel/assignment/counts, inactive assignees, recent/earlier messages, forged non-website selection, read-only reply denial, owner-scoped lead statistics/drawer and real activity filtering.
- Earlier affected selection: **89 passed /369 assertions /21.02s**, including all20 revision46 financial-repair cases.
- Full regression: **1699 passed /1 skipped /9109 assertions /440.69s**, exit0. The existing MySQL-sockets installer skip remains a skip, not production verification.
- The first full run failed only the sidebar hub test's old lead description string. Updated that expected copy while retaining the single-heading/no-duplicate-hub-description contract; full rerun above passed.
- PHPStan: **0 errors** after correcting a displaced Collection PHPDoc (no behaviour change).
- Final scoped UI/finance tests after the last composer/toast-only adjustments: **157 passed /547 assertions /38.63s**, exit0. This final targeted gate and Chromium follow the full-suite gate above; no financial/domain PHP changed afterward.
- Final desktop/mobile Chromium workflow: **20 checks passed**, no page JavaScript errors, at1440px/390px. Uses only a separate guarded QA copy, array mail, sync queue and blank Telegram token, with real native message and notification actions. Widget network failure is intentionally intercepted to test draft retention.
- Native Vite build succeeds; existing ApexCharts chunk-size advisory remains. Public v1 site CSS/JS are restored byte-for-byte from the approved base after building; only the admin stylesheet/manifest changes. PHP/Blade syntax/style/diff evidence is recorded alongside raw test results.

Evidence is stored in [`evidence/47/47-verification.txt`](evidence/47/47-verification.txt); browser scripts and guarded fixture are `tools/sandbox/communications-{check.mjs,fixture.php}`. The fixture requires the exact copied QA DB, local environment and non-sending mailer/queue. The main preview receives no synthetic communication/financial records.

## Preserved scope and limitations

All revision46 financial action/report source, frozen quotation/PDF layouts, routes, schema, native permission policies, public v1 files and admin JavaScript are unchanged. No provider credentials, customer/bank data, demo tokens or databases are committed. No new provider sends, financial backfill, metric-policy change, deployment or merge.

Polling remains10s for staff and5s for the visitor, not websocket/verified online presence. The existing Inbox list cap60 and live drawer list cap30 remain; message/activity load-earlier is bounded at600. No voice/video calls or visitor attachment transport was invented. MySQL locks/scale, live WhatsApp/Telegram delivery, owner-host parity, every-role/device/action attestation, F05 reporting semantics and historical financial reconciliation remain open.

## Preview and screenshots

Native main preview: port33143, local demo only. QA writes used port33144 with its own vendor/autoload base, SQLite backup, private storage and session; main demo has **0 QA contacts /0 conversations** after the tests.

[Leads desktop](evidence/47/47-leads-desktop.png) · [Leads mobile](evidence/47/47-leads-mobile.png) · [Activity drawer](evidence/47/47-activity-drawer.png) · [Activity filter](evidence/47/47-activity-desktop.png) · [Alerts dropdown](evidence/47/47-alert-dropdown.png) · [Alerts centre](evidence/47/47-alert-centre.png) · [Alerts mobile](evidence/47/47-alert-mobile.png) · [Inbox desktop](evidence/47/47-inbox-desktop.png) · [Inbox mobile](evidence/47/47-inbox-mobile.png) · [Live chat desktop](evidence/47/47-live-chat-desktop.png) · [Live chat mobile](evidence/47/47-live-chat-mobile.png) · [Visitor widget](evidence/47/47-visitor-chat-mobile.png) · [Failed-send draft retained](evidence/47/47-visitor-retry-mobile.png).

The browser driver waits for responsive sidebar transitions before checking phone overflow, selects visible controls rather than hidden duplicate header/thread actions, and uses the actual icon close control. These harness corrections are not represented as application bug fixes. The toast interception and failed-send draft loss are actual repaired application defects.
