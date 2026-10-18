# Google account connections and staff sign-in

Updated 2026-10-17. **Implemented locally; not published or tested with a live Google OAuth application.**
The owner selected Google first, both company connections and staff sign-in, and confirmed the OAuth app is not registered yet.

## What this release does

- **Integrations → Connect an app / Connect Google** opens a connection dialog, inspired by the supplied reference.
  It explains the permissions, displays a non-secret callback URI and guides server setup. It does not accept a
  Client Secret or a user-supplied authentication endpoint. OAuth uses Google's fixed official endpoints.
- When configured, an owner confirms their **Woodex** password, continues to Google's own account chooser and
  consent screen, and returns to the hub. Authorization uses top-level navigation (`target="_top"`), not an
  iframe or a popup which may be blocked. The connection shows the verified account, state, scopes and last check.
- Company tokens are encrypted in `google_connections`. Owners can check the connection, refresh an expiring
  token and revoke it. A failed revocation keeps the encrypted credential, disables the connection locally and
  explicitly says revocation is pending; it does not pretend the provider disconnected successfully.
- **Security & Telegram → Google staff sign-in** is a different flow. A signed-in staff member confirms their
  Woodex password and explicitly links a Google account. Only its immutable Google subject and verified email
  are stored; staff-linking/sign-in access tokens are discarded, not saved. Unlinking also requires the password.
- The login page shows **Continue with Google** only when server configuration is valid and sign-in has been
  explicitly enabled. Sign-in accepts only active, previously linked staff. It never creates a staff account,
  matches one automatically by email, changes roles or uses the company connection as a staff identity.
- Confirmed Woodex TOTP is still challenged through Fortify, including owners. Mandatory enrolment for other
  configured roles remains in place. Google linking does not enable, remove or satisfy Woodex TOTP.

### Deliberately limited access

Only `openid email profile` is requested. A connected Google identity **does not enable Drive, Sheets, Gmail,
Calendar or Meet**. Those cards remain Planned until their actual actions, scope decisions and acceptance tests
exist. No Google account password is collected by Woodex. Basic account authorization is not a sync feature.
Company connection requests offline access for its explicitly implemented refresh/check/disconnect lifecycle;
staff sign-in does not request offline access.

## Register the company Google application

1. In your company's Google Cloud project, configure the OAuth consent screen/audience, support details and
   authorized domain. Use the actual business details and website. Choose Internal only if the organization's
   Workspace policy permits it; otherwise use External and comply with Google's publication requirements.
2. Create an **OAuth client ID → Web application**. Register the **exact Authorized redirect URI** displayed on
   the deployed Woodex connection dialog. With the default apex-domain layout it is:
   `https://woodex.com.pk/admin/google/callback`.
   A different `ADMIN_DOMAIN` or `ADMIN_PATH` changes that route. Never register a wildcard or an Arena preview
   URL for production. Use a separate development client/project for local testing.
3. While the app is in Testing, add the owner and intended staff as test users. Review Google's current testing,
   refresh-token lifetime, consent and publication policies; do not promise that a testing grant lasts forever.
4. Add **only these new settings** to the application's existing, privately selected server environment. Do not
   overwrite the file or change APP_KEY, database, Telegram or other existing settings:

   ```dotenv
   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   GOOGLE_REDIRECT_URI=https://woodex.com.pk/admin/google/callback
   GOOGLE_SIGN_IN_ENABLED=false
   ```

   Fill the ID and secret privately from Google Cloud. `.env.example` contains empty placeholders only.
   Respect the application's environment-file precedence: diagnose the selected file instead of deleting or
   copying existing environments. The owner has already saved the Telegram settings; those are unrelated.
5. Publish the code/assets in a **new coding session**, then run **System → Database update**. The migration
   creates `google_identities` and `google_connections`; existing staff accounts and authentication settings are
   not rewritten. Reload saved server settings from the connection dialog, or run `php artisan config:clear`
   from the deployed application's `platform` directory. This applies **all** saved environment changes.
6. Connect the company account, inspect Google's consent (basic identity only), return, then use **Check Google
   connection**. Verify no Workspace data capability is implied. Disconnect and confirm the grant is revoked.
7. With password sign-in still available, staff link Google from Security. Set `GOOGLE_SIGN_IN_ENABLED=true`
   privately and reload server settings. In a separate/private browser, test linked and unlinked accounts,
   inactive staff, TOTP/recovery challenges and unlinking. Keep password sign-in as a fallback.

Use Google's current [web-server OAuth documentation](https://developers.google.com/identity/protocols/oauth2/web-server)
and the [Google Account connections page](https://myaccount.google.com/connections) when managing grants.
Google Cloud consent/credential screens may change; this is a setup guide, not a claim they were configured here.

## Security and operations

- State and a PKCE S256 verifier are session-bound, one-use and expire after 10 minutes. Intents and initiating
  staff IDs are server-set; login, personal linking and company connection cannot be swapped through a request
  parameter. Another authorization start in the same session replaces the pending flow; use one at a time.
- The backend exchanges the code directly with Google and verifies the profile through Google's authenticated
  userinfo endpoint. It requires a stable subject and `email_verified: true`; it does **not** authenticate using
  an unverified/decode-only ID-token payload or email matching.
- No automatic registration, role assignment, arbitrary authorization URL, arbitrary scopes, browser token
  storage, cross-origin postMessage completion or Google access through the Woodex MCP bridge.
- Company token casts are encrypted and hidden from model serialization; views select only non-secret fields.
  Preserve APP_KEY and keep database backups private. Client ID is public configuration, but the client secret,
  access token and refresh token must never be pasted into screenshots, prompts, Git or support reports.
- No request/response/token/code payloads are written to integration logs. Logs contain action, staff ID and
  outcome metadata. Configure hosting/proxy/APM logging to redact callback query strings and exclude OAuth
  exchange bodies, Authorization headers and password-bearing forms. OAuth responses are no-store and use
  `Referrer-Policy: no-referrer`.
- Network requests are fixed HTTPS, time-bounded and do not follow redirects. Provider errors are shown only as
  generic messages; no raw provider body or exception is flashed. OAuth routes and sensitive starts are throttled
  with **separate named limiters** (20/min/IP and 5/min/IP respectively) so nested throttles do not share counters.
- Google disconnect may revoke other grants for the same Google app/account. Staff identity records remain
  separate, though Google may request consent again. Do not use the same OAuth project for unrelated systems.
- If revocation continues to fail, verify the app's grant in Google Account connections and provider connectivity.
  The current version intentionally retains the disabled record rather than silently deleting unverifiably
  revoked credentials. There is no automatic background sync or refresh worker.

## Verification boundary

PHP HTTP-fake tests cover the authorization redirects, PKCE exchange, encrypted storage, state failures/replay,
actor/owner/password rules, verified identity, no email auto-linking/registration, inactive/deleted staff, preserved
Fortify TOTP, refresh failure and confirmed/failed revocation. This is **not live Google consent verification**.

`node tools/sandbox/google-check.mjs` checks the unregistered UI at desktop/390/360px without changing the demo
business database or making a Google request. Iframe coverage includes the connection dialog. Production must
still pass the real registration, consent, refresh/revocation and staff-login acceptance above.

### Recorded local results (2026-10-17)

- Full Pest: **511 passed / 3,504 assertions; 1 skipped**. Google subset: **33 / 186**.
- Changed-PHP Pint and Larastan level 6 pass; production assets rebuilt. Local MCP tests remain **3/3**.
- Google unregistered-state browser check: **1440px, 390px and 360px pass**, with no provider request or demo
  business-data mutation. Both cookie-restricted iframe modes pass with Google-dialog coverage.
- General browser regression ran against an isolated application/database copy. Admin, quote editing/sending,
  PDFs, client acceptance, charts/sidebar and mobile checks pass. The two existing inline-homepage-form
  expectations still fail against the restored v1 home, which has no such form. No unrelated homepage rewrite.
- No live Google application, production sign-in or Hostinger deployment was verified. No claim of complete
  Workspace integrations or an entirely green general browser suite is made.
