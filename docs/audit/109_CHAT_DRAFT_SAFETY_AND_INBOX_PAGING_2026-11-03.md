# Revision 109 — Chat draft safety and Inbox conversation paging

Date: 3 November 2026 (Asia/Karachi). Scope: the next concrete C01/C02 repair from `ox-pending.md`. This is not completion of all Chat, all 36 gates, or production release approval.

## Reproduced defects

Ten initial regression cases failed before implementation. Inbox thread selection cleared reply, internal note, attachment and history even on reselecting the same thread. Filters cleared the selection; the next selection silently lost unsent work. Mobile Back detached the selected conversation. The Inbox returned only 60 conversations without pagination. The drawer's public `select` action bypassed its existing open-thread draft guard.

## Implemented

- Inbox lists use native Livewire pagination, 60 records per page, with deterministic `last_message_at`/ID ordering and existing admin pagination controls. Filters reset only the list page, not the current composer.
- Selecting the same conversation keeps its reply, note, upload and history window. Selecting another with unsent work presents **Keep current conversation** / **Discard unsent work and switch**. Only the explicit discard clears the composer and changes the selected thread.
- Mobile Back shows the list without detaching the draft from its original visitor. Returning to that thread restores its composer. Arrivals are not marked read merely because a hidden composer is retained; returning to the latest thread marks it read. Older history windows remain protected.
- Inbox and drawer selected/pending IDs are Livewire-locked: browser property updates cannot silently substitute a reply destination. Server actions still query and authorize the target; confirming a pending switch rechecks authorization before clearing work or marking the target read.
- Drawer direct selection now has the same same-thread/draft protection as its existing open-thread action, and an explicit keep-current control. Its existing discard confirmation remains.
- No schema changes, second Chat database, provider sends, WhatsApp consent/window changes, message delivery claims, automatic reopening, or lifecycle policy changes.

## Verification

| Check | Result / boundary |
|---|---|
| New draft-safety native cases | 17 cases covering reply/note/upload-only drafts, cancellation, confirmation, ID substitution, revocation, original-visitor send isolation, pagination, same-thread history and mobile unread behavior |
| Focused Chat regression suite | **135 tests / 547 assertions PASS**, native PHP 8.5.12, isolated SQLite |
| Real Chromium normal-login QA | **9 checks PASS**, separate disposable SQLite copy with 61 fictional conversations; pagination, deferred reply/note preservation, filters, cancellation, phone Back/reselect, explicit discard and both drawer choices; zero JS page errors |
| Controlled alternate-origin main preview | **15 checks PASS** after syncing the new code; cookies deliberately stripped; not actual Arena-parent certification |
| Source/release/enquiry contracts | **92 PASS** |
| Auth/theme/direct-context helper contracts | **62 PASS** |
| Vite production build | PASS in private preview clone; existing chunk-size advisory only; original generated-asset dirt not staged |
| Pint | Four changed PHP files PASS; final two-file incremental check also PASS |
| Full native regression / hosted MySQL | Exact-source completion and counts recorded separately on PR8; do not substitute revision108's CI result |

Private evidence stays under ignored `.cache/preview/`: `109-red.log`, `109-chat-suite-final.log`, `109-browser-results.json`, `109-preview-origin.log`, `109-source.log`, `109-ui-contracts.log` and `shots/109-*.png`. Browser QA used normal Fortify and normal HTTP/lax cookies only in its separate private QA environment. It did not weaken application authentication. Its temporary listener was stopped; the main synthetic8080 preview remains running. An initial all-in-one regression attempt exhausted the native runtime's default128MiB limit; a private768MiB test wrapper was used for the retry, not a production/runtime config change. Local PHPStan remains blocked by the previously recorded multicall respawn limitation; no static-analysis pass is claimed.

## Still open

Drafts are component-local, **not** durable storage across a full reload, leaving the page, tab closure or a different browser session. Existing compact drawer list limits and broader lifecycle/design/auto-open/visitor-isolation/permission/device acceptance remain separate C01/C02 work. Conversation list pagination is distinct from the existing bounded message-history paging.

Actual owner acceptance inside Arena's authorized parent remains unconfirmed. The earlier initial-document E2B traffic-token403 is not certified by controlled browser checks. Production, financial imports, external sends, restore/release approvals, deployment and final repository privacy conversion remain held. Existing generated-asset working-tree changes are not part of this revision. The main preview's16 imported demo leads and original workbooks were not changed by this Chat QA.
