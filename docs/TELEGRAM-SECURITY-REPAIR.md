# Security & Telegram: diagnosis and recovery

Date: 2026-10-16

> **Latest local follow-up, 17 October:** [Telegram identity/consent hardening](25-TELEGRAM-CONSENT-HARDENING.md) supersedes the old secret-URL registration behavior. The owner already confirmed replacement credentials are privately saved; do not ask for them again. Re-register with the stable header-authenticated endpoint after publication, using existing values. Earlier findings/rotation instructions below are historical, not a request to repeat completed credential setup.

## Findings and scope

- The screenshot's QR is a TOTP authenticator enrolment QR, **not a Telegram QR**. Scan it inside Google Authenticator, Microsoft Authenticator or 1Password. Enter the current six-digit code from that app in Confirm.
- The screenshot alone cannot establish whether the QR payload or server clock is wrong. There was no manual-key fallback, and the form accepted codes up to ten characters despite asking for six. The patch adds the manual key, exact six-digit validation and clock guidance.
- Creating a bot in BotFather does not configure this application. It needs a server-side bot token, a webhook secret, a publicly reachable HTTPS webhook registered with Telegram, and a personal `/start CODE` linking step. These production settings have not been inspected from this sandbox.
- Previously the linking page generated codes even without bot/webhook configuration, did not link to the actual bot, and used a potentially stale loaded relationship after actions. The patch adds configuration guidance, a direct `t.me/WoodexInteriorBot?start=CODE` link, code regeneration and fresh linking state on render. A configured status is **not** proof of webhook delivery.
- Unlinking now invalidates outstanding link codes. Telegram transport exceptions previously risked recording the bot token embedded in the API URL; raw exception messages are no longer persisted or reported by this client.
- **Live verification is blocked here:** outbound access is restricted to GitHub and package registries. Neither api.telegram.org, t.me nor the hosting domain can be reached. No production deployment or webhook registration has been performed.

## Immediate action: rotate the exposed token

The token pasted in chat must be considered compromised. In Telegram's official BotFather, revoke the token for `@WoodexInteriorBot` and generate a replacement. Do not paste the replacement in chat, a GitHub issue or this public repository. Put it only in the server's private environment configuration.

## Hostinger recovery steps

1. Deploy this branch's changes through the normal reviewed deployment process.
2. Use hPanel File Manager to edit the private environment file. On a Git installation it normally lives at `~/woodex-os/<site>/.env`, outside the public web folder; see docs/06. Do not create a downloadable `.env` in the public folder.
3. Set the new token as `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME=WoodexInteriorBot`, and `TELEGRAM_WEBHOOK_SECRET` to a separate, long random string containing only letters, numbers, `_` or `-`. Ensure `APP_URL` is the real HTTPS application URL.
4. Clear cached configuration through the deployment/terminal workflow (`php artisan config:clear` from `platform/`, if terminal access is available). Do not assume editing `.env` changes already-cached config.
5. In **System → Integrations**, press **Connect webhook**. With terminal access, the equivalent is `php artisan woodex:telegram:set-webhook`. Read any error in the integration log; never share the webhook secret or full secret-bearing URL publicly.
6. On **Security & Telegram**, generate a **new** link code, open the bot using the new button, and tap **Start**. If blocked, unblock the bot first. Expect `Linked to Woodex OS as …` in Telegram and `Linked` on the page within about five seconds.
7. In **System → Integrations**, press **Send me a test** and confirm that it arrives. Send `/help` in Telegram to check inbound commands too.

An administrator can privately call Telegram's `getMe` and `getWebhookInfo` methods using the replacement token to verify the bot username and webhook URL, `pending_update_count`, and `last_error_message`. Do not paste a token-bearing API URL into a public report. If a webhook delivery fails, check TLS, redirects, firewall/WAF rules and whether the POST webhook returns an error. The webhook must be reachable without staff login; its configured secret provides authentication. Keep that protection in place.

## Authenticator recovery

1. On the same signed-in account's Security page, scan the current QR **inside the authenticator app**. On a single phone or if scanning fails, choose **Enter a setup key** and use the key displayed under the QR, with **Time based** selected. Keep the key private.
2. Enable automatic date/time on the phone. Ensure the hosting server clock is correct as well.
3. Enter a fresh six-digit code and press Confirm. If there are multiple old Woodex entries, use the one matching the current setup key.
4. Expect the page to say the account is protected, then save the recovery codes privately. Never disable enforced 2FA or edit database secrets to bypass setup.

## Production acceptance checklist

- Authenticator setup succeeds with both QR and manual-key enrolment.
- Incorrect/short codes show validation errors; the account stays unconfirmed.
- `/start` via the deep link replies and associates the correct staff account.
- Link state changes on the page without a full reload; regenerated/expired codes cannot be reused.
- `/help` and **Send me a test** both work.
- Unlink removes the chat and invalidates pending codes.
- No bot tokens, TOTP keys or full webhook secrets are shared in logs or reports.

## Local verification results

- 25 targeted tests passed (143 assertions): profile security, Telegram webhook, admin access, system screens and deploy manifest. Authenticator confirmation was checked with a real generated time-based OTP.
- Pint formatting passed for the four changed PHP files; Vite production build passed and deploy assets were refreshed.
- Targeted headless-browser security check passed: enable-2FA action, QR rendering, manual-key fallback, missing-Telegram-configuration guidance, and desktop/390px layout.
- Cross-site iframe checks passed both with third-party cookies blocked and with proxy-dropped cookies.
- The broader `browser-check.mjs` did **not** fully pass: the existing public homepage requests `/api/chat.php` (404) and lacks the enquiry form expected by that script. Its admin interaction checks ran successfully. These public-homepage issues were left outside this repair's scope.
- These are sandbox results, not proof of production Telegram connectivity or successful camera scanning on the user's phone.

## Runtime follow-up — 2026-10-17

The owner confirmed the previous fixes were deployed, the token was rotated and saved, and **Connect webhook** reported success. Screenshots show unanswered `/start` messages, including plain `/start`. Expired linking codes do not explain a missing reply to plain `/start`. The actual production blocker is still unverified.

Additional code fixes:

- The Telegram card now says **Configured**, not **Connected**, when only a token is present. Registration success explicitly asks for a delivery check.
- Added **Check live connection** at `https://woodex.com.pk/admin/integrations`. It performs read-only `getMe` and `getWebhookInfo` requests **from the deployed server**, checks the actual bot identity and exact webhook target, and displays pending updates, a redacted delivery error and local inbound timestamps. Diagnostic fields are locked and permission checked. Neither bot tokens nor secret-bearing URLs are displayed or stored by this diagnostic.
- Escaped plain command replies for Telegram's HTML parse mode. Previously `/help` contained literal `<text>` tags that Telegram can reject, and account names containing HTML characters could cause linking replies to fail. These are confirmed code defects, not proof of the reason plain `/start` is silent in production.
- Webhook re-registration now preserves pending messages rather than discarding them.

### Next production check

Deploy the latest changes, then open **System → Integrations → Check live connection**. Send a **new plain `/start`** to `@WoodexInteriorBot`, wait a few seconds and check again. Share only the diagnostic panel, never tokens or full webhook URLs.

| Diagnostic result | Action |
| --- | --- |
| Bot username is not `WoodexInteriorBot` | The configured token belongs to another bot; save the correct token privately, clear cached config, reconnect. |
| Webhook absent or mismatched | Press Connect webhook on woodex.com.pk. Ensure another site or integration is not overwriting this bot's single webhook. |
| API failed / log shows 401 | Check the replacement token and cached server configuration. |
| API failed / connection exception | Check Hostinger outbound HTTPS/DNS/TLS access to api.telegram.org. |
| Delivery error 301/302 | Fix canonical-host/HTTPS redirects for the webhook. Telegram must POST directly to the final endpoint. |
| Delivery error 404 | Verify the webhook route and deployed application; reconnect on the intended site. |
| Delivery error 403 | Check WAF/firewall and webhook secret consistency. |
| Delivery error 419 | Check deployed CSRF exclusions for `webhooks/*`; do not disable CSRF globally. |
| Delivery error 500 | Inspect private Laravel logs for the failing request; do not publish raw secret-bearing logs. |
| Fresh inbound timestamp, but no reply | Inspect the `sendMessage` result in the outbound log. HTTP 400 can indicate formatting; 403 can indicate a blocked bot. |
| No new inbound timestamp | Telegram is not delivering to this application yet, or a request is rejected before event storage. Inspect the reported delivery error and hosting access/error logs. |

A historical last-delivery error is not necessarily current; compare its timestamp with the latest message. The panel's last processed timestamp reflects webhook handling, **not** confirmation that the user received a reply. Confirm the `sendMessage` log and a visible reply as well.

Verification: 32 targeted Pest tests passed (171 assertions), Pint passed, Vite build passed, targeted diagnostics browser check passed at desktop and 390px, and both iframe-cookie checks passed. Real Telegram API responses are covered with HTTP fakes, not a live production connection. No deployment was performed from this sandbox.

## Owner access unblock — 2026-10-17

The owner chose **optional 2FA for owner accounts only**. The patch exempts owners from mandatory enrolment redirects even when `ENFORCE_2FA=true` and old cached configuration includes `owner` in required roles. Admin/accountant policy stays enforced. No global environment change or database-secret deletion is necessary.

After deploying this version, an owner with no confirmed 2FA can navigate directly to `/admin/integrations` and complete Telegram setup. If a pending QR remains on the Security page, it is optional: leave it for later, or enter the account password and press **Cancel setup**. Linking Telegram does not automatically activate authenticator sign-in. When ready, enable 2FA and confirm a valid authenticator code; subsequent sign-ins will require it. Already-confirmed 2FA has not been bypassed or automatically removed.

Verification: 45 targeted tests (238 assertions), formatting, build, browser checks for pending-owner navigation and cancellation, mobile layout, and iframe sign-in checks passed. This remains a code change awaiting deployment, not a live-site modification.

## Credentials saved, but still reported missing — local follow-up

The owner confirmed the replacement credentials are in the private `.env`. That does not establish which file the live app selected or whether cached config contains old values. This version adds owner-only **Check server configuration**, automatically opened when Connect webhook finds missing credentials. It shows only:

- The settings file selected by the application, as a relative path.
- Whether that file is readable and syntactically valid.
- Presence of each Telegram credential on disk versus in the running configuration.
- Whether configuration is cached and whether the file values match the loaded values.
- Whether a separate private `.env` exists while another file (such as `platform/.env`) was selected.

An owner can use **Reload server configuration** to clear Laravel's config cache and fully reload the page. This does not edit settings, generate tokens, switch environment files or register the webhook. All saved `.env` changes—not just Telegram changes—take effect on the next request. Confirm any other saved changes are intentional before using it.

If the private `.env` is not the selected file, do not delete either file or copy the entire private file over the active one: database and APP_KEY settings may differ. Ask hosting support to verify the intended layout and configure only the Telegram keys in the active environment. If saved and loaded values continue to differ after clearing cache, check server-level environment overrides.

Immediate terminal equivalent (from the deployed application's `platform` directory): `php artisan config:clear`, then reload the Integrations page. This can resolve stale cache but cannot supply missing credentials or fix a wrong environment-file selection.

Verification: 71 targeted tests / 380 assertions, formatting/build, desktop/mobile diagnostics and cache-reload browser check, and both iframe modes passed. No live Telegram message was sent. This follow-up is local only: the session is closed after PR #2 merged, so a new coding session is required to publish it.

## Integrations redesign preservation — 2026-10-17

The new searchable integrations hub retains the Telegram test, webhook, live-connection, server-configuration
and configuration-reload controls. It distinguishes Planned providers from implemented services; an MCP key
cannot configure Telegram or send a message. Full local regression: 478 passed / 3,318 assertions, 1 skipped;
changed-PHP formatting/static analysis, production build, targeted desktop/mobile hub+Laravel-MCP acceptance
and both iframe modes passed. The general browser check retains two unrelated v1-homepage form-expectation
failures. These follow-ups remain unpublished; no live Telegram delivery or Hostinger deployment was verified.


## Header-authenticated private identity follow-up — 17 October 2026

New registrations, including `woodex:telegram:set-webhook`, use `/webhooks/telegram` with Telegram's official
secret header, not a secret in the path/query. The old path requires the header by default. Temporary
`TELEGRAM_ALLOW_LEGACY_WEBHOOK` compatibility is disabled unless explicitly reviewed; a wrong header is
never accepted. Register again with existing saved values, confirm private `/start`, `/help`, test alerts,
unlink/disabled-account denial and group denial; do not claim production readiness from registration alone.

Personal commands and notifications no longer treat group routing as staff authority. Current active user/link
checks, locked once-only linking, atomic command/replay markers and authenticated sender/link rate limits are
implemented. The fuller report includes the observed middleware-priority ordering defect and its regression.
Important financial/approval commands remain disabled. Owner-only optional 2FA and confirmed challenges are
unchanged. Fresh full PHP suite: **622 passed / 4,068 assertions / one skipped**; 61 new regressions,
Larastan clean, Pint 23 PHP files. Local only, no live provider call or deployment. Telegram durable queued
intake/reply outbox and hosting acceptance remain pending.
