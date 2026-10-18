# A0 next batch — Telegram identity and consent safety

**Date:** 17 October 2026 PKT. **Status:** locally implemented and tested; not published or deployed. Owner requested “start next” after the [first A0 safety batch](24-A0-SAFETY-IMPLEMENTATION.md). This addresses the Telegram/consent portions of B08, not every acceptance criterion of B08 or the complete automation backlog.

> Subsequent work: the owner requested the updated port 8080 preview and next work; the [first B04 slice and preview refresh](17-QUOTATION-IDEMPOTENCY-START.md) now have separate evidence. B08-specific implementation/results below remain historical to this batch; neither batch is a Hostinger deployment.

## Telegram: who may act

- Personal commands require a human sender in a **private** chat, with a positive sender ID equal to the private chat ID. Group/supergroup/channel identities, sender-chat/inline-bot/forwarded messages, bot senders and commands addressed to another bot are ignored. Group routing is never personal staff authority.
- `HandleTelegramCommand` is a domain action for trusted webhook messages. Linking locks/reloads the active staff user and link, checks expiry again under lock, and consumes the code once. New codes contain 16 alphanumeric characters, with a maximum 30-minute lifetime. Existing valid eight-character codes remain compatible until used/expired/rotated.
- A code cannot displace another staff account already attached to that chat. The existing owner must unlink it through their profile first. Disabled/deleted staff, inactive links and historical group links cannot submit `/idea` or `/issue` as a staff user.
- Personal alert delivery blocks inactive users and group routes. `TelegramChannel` reloads the user/link before sending so a cached link does not bypass unlink, disable, group or mute changes. Existing group-link records are retained, not deleted; relink privately before expecting personal alerts. A separate group-alert authorization feature was not built.
- Available operations remain linking, generic help and ideas/issues from active linked staff. **No finance, role, secret, approval or arbitrary business-write command was enabled.** Callback/edited/channel updates are ignored. All active linked staff may report an idea/issue; this is not a general privileged-command permission gateway.
- Command effects and the webhook processed marker commit together under the event lock. A crash before the marker rolls back the effect; a provider replay can retry the original trusted payload without duplicating a request. A changed payload with the same processed ID cannot execute again. Replies occur after the business transaction and are HTML-escaped. A reply transport failure cannot undo or repeat the business write; raw exception contents are not logged.

## Telegram: webhook authentication and limits

New registrations from **both Integrations and the console command** use:

```text
POST /webhooks/telegram
X-Telegram-Bot-Api-Secret-Token: [server-configured secret]
```

The token value is not included in the URL or query. Existing saved credentials are reused; no private environment file was read or changed in this batch.

- The existing `/webhooks/telegram/{secret}` path remains compatible **with a matching header**, but is not the URL new registrations produce.
- Both paths require the header by default. A temporary `TELEGRAM_ALLOW_LEGACY_WEBHOOK=true` opt-in permits a missing header **only on the matching old secret path**. It never permits a wrong supplied header or a missing header on the new path. The example/default remains false. Use only for an explicitly reviewed transition, then turn it off and reload configuration.
- Authentication is explicitly prioritized before Laravel's throttle middleware. A new denial test caught Laravel reordering the initial route middleware: unauthenticated requests could otherwise consume a real actor's bucket. The priority fix and its regression test are included.
- Authenticated intake is limited to 300 requests/minute globally, 30/minute per sender, and five `/start` or `/link` requests/minute per sender. Sender cache keys are hashed; these buckets are independent of WhatsApp's webhook limiter. A throttled request gets 429 so the provider can retry. This is not a guarantee that all provider bursts are harmless; check limits against real traffic before release.
- The API registration now requests only `message` updates, preserves pending updates, and does not solicit unimplemented callbacks. Malformed update IDs are acknowledged without creating a ledger row or replying.

## WhatsApp: STOP stays stopped

Three inspected paths could restore consent despite an existing opt-out: returning enquiry enrichment OR-ed submitted flags; contact merge OR-ed both records; ordinary contact edits could check consent again. The batch now:

1. Locks/reloads matched contacts during capture, merge and ordinary edits, and during inbound matching before STOP handling.
2. Keeps an existing `opted_out_at` suppression on returning capture even when the new enquiry supplies positive consent flags. The enquiry is still recorded; receipt of an enquiry is not permission to resume messaging.
3. Carries either contact's opt-out into the merge target and forces both messaging/marketing flags false. The merge reloads both contacts in ID order rather than trusting stale models. This does not finish the broader conversation/media/provenance merge backlog.
4. Refuses ordinary contact edits that try to restore consent after opt-out, with a validation message. A normal name/detail edit preserves suppression. No new resubscription action was invented.
5. Does not interpret `START` or another inbound enquiry as permission to erase STOP, and preserves the existing suppression timestamp when STOP is replayed.
6. Reloads current staff permissions, conversation state and contact suppression at `SendConversationMessage`, before a provider call. Inactive, missing or unauthorized staff are denied. Opted-out/missing contacts cannot receive replies or templates. Templates additionally require affirmative WhatsApp consent; the method does not manufacture marketing consent. Internal notes remain possible without contacting the client.
7. Returns a truthful blocked-send explanation through the existing inbox toast rather than claiming a message was saved as failed when no send happened.

These are point-in-time pre-send checks. A provider request already in flight cannot be recalled if STOP arrives afterward. Durable send cancellation/outbox controls, affirmative re-consent evidence, template approval/category checks and marketing-specific policy remain separate work. Direct SQL/import writers need their own retained-suppression acceptance; this is not a completed legacy-consent migration.

## Verification

- **Final complete PHP suite: 622 passed, 4,068 assertions, one skipped**, 149.15 seconds. `/tmp/a0-b08-full-tests-final.log`.
- **61 new regression cases** across `TelegramSecurityTest.php` and `ConsentSafetyTest.php`. Existing payment/WhatsApp recovery, Telegram setup/diagnostics, owner optional-2FA, Google, MCP and website tests are included in the full run.
- Larastan level 6: **no errors**, `/tmp/a0-b08-phpstan-final.log`. Pint applied to **23 PHP files**, including the middleware priority and console registration fixes.
- All provider operations are faked. Tests use seeded in-memory SQLite. No real Telegram/WhatsApp delivery, production configuration changes, new production migration or concurrent MySQL proof occurred. The one skipped installer/database-driver check is sandbox-specific.
- No Blade/CSS/JS changes or browser-suite rerun in this batch. Existing homepage browser form expectations remain open. The new inbox denial explanation is covered through backend action tests, not new browser evidence.

## Release and remaining work

1. Publish in a **new coding session**, preserving all existing local Google/MCP/Telegram and first-A0 changes. This session cannot push or deploy. B08 itself adds no schema migration; the preceding WhatsApp recovery migration still belongs to the combined release.
2. Confirm already-saved private Telegram values are loaded. Do not ask the owner to save or disclose the replacement credentials again.
3. Re-register using **System → Integrations → Connect webhook**, or the existing `php artisan woodex:telegram:set-webhook` from `platform/`. This replaces the secret-bearing registration URL with the new stable URL and supplies Telegram's official secret header. Keep legacy mode disabled unless a transition is specifically required.
4. Prove private `/start CODE`, `/help`, `/idea`, test-alert delivery, unlink, disabled-staff denial and safe group denial on the real hosting environment. Inspect redacted diagnostics and pending-update counts; registration alone is not proof of delivery. Test malformed/auth failures without publishing webhook secrets.
5. Review real opt-out history and accept the conservative policy with the owner. Do not clear suppression to make a test send work. Re-consent requires a future explicit evidence-backed workflow.
6. B08 still needs the broader finance own/team permission review and real-hosting acceptance. B04 quote/send/milestone idempotency, production-like MySQL verification, B01 runtime evidence, durable financial delivery recovery and shared approval/outbox controls remain next work.

**Known Telegram delivery limit:** command handling is still synchronous, though business writes are atomic and replies run after the transaction. A hard crash after the command commits but before its reply can lose that reply. The processed marker deliberately prevents repeating the business operation just to recover a reply. A durable queued Telegram inbox/reply outbox and operator review surface are not claimed by this batch.

Owner-only optional 2FA, confirmed challenges, D-88 manual milestone invoice drafts and approval-gated important Telegram operations are unchanged. No master agent, campaign, external worker or new specialist dashboard was enabled.
