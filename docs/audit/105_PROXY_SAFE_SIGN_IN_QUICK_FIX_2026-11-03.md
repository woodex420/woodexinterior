# 105 — proxy-safe sign-in quick fix

3 November 2026, Asia/Karachi. Responds to the owner's two screenshots: the updated preview reports a network error, while production still displays the older split login. Earlier local browser success did not certify the actual Arena proxy.

## Implemented in source and the existing isolated preview

- Fortify's login form now emits a root-relative POST action, not a backend-derived absolute HTTP URL. The enhancement validates the endpoint against the browser's own origin before sending credentials.
- Enhanced sign-in requests JSON and refuses HTTP redirects. This avoids following an internal-host/HTTP `Location` through an HTTPS reverse proxy. A custom **login response only** returns a validated relative intended destination. Unknown hosts, protocol-relative paths, credentials, controls and malformed URLs are rejected. Missing/invalid results are visible errors, not assumed success.
- Normal Fortify password/active-user checks, CSRF, sessions and throttling still apply. The original two-factor challenge and final verification response are retained; pending 2FA does not authenticate or consume the intended URL. Native JavaScript-disabled responses remain unchanged.
- The owner's explicitly requested glass appearance is now the initial mode, including when automatic reduced-transparency/unsupported-blur fallbacks would make it opaque. A keyboard-operable **Glass appearance / Solid contrast** control persists the user's choice locally. Reduced-motion behavior remains intact.
- No database migration, seed rerun, password reset, production environment edit, provider send or financial-history change.

## Acceptance

- Full serial native regression: **1,988 tests / 11,115 assertions PASS, 241.59s**.
- Scoped PHPStan: **0 errors** for the new response and Fortify provider; Pint **3 files PASS**; Vite build PASS.
- Pure UI/auth/theme contracts: **57 PASS**.
- Chromium: **30 checks PASS, exit 0**, including a browser on HTTPS with a forwarding transport that deliberately sends requests to the native HTTP backend under an internal hostname and HTTP forwarded scheme. Sign-in reaches the dashboard and the browser session survives reload; no cross-origin browser request is allowed. This is a controlled proxy reproduction, **not a connection to or certification of the actual Arena parent browser**.
- Also tested: mismatched form endpoint sends **no POST**; click/Enter/frame/native fallback; wrong password; HTTP419/429/503; network failure/unlock; three roles; existing real Livewire SEO and Assistant interactions; explicit glass/solid persistence under reduced-transparency emulation.
- PHP tests additionally cover intended redirect consumption, external redirect rejection, confirmed-2FA challenge, incorrect recovery-code denial and valid recovery-code consumption with native final redirect.
- Parent revision104 hosted MySQL run **41526580232 SUCCESS**, API-confirmed. This is not revision105 or local MySQL runtime acceptance.

Reproduction: `node tools/sandbox/branding-login-check.mjs` with the existing private native preview/bootstrap; `node --test tools/sandbox/auth-unit-check.mjs tools/sandbox/admin-refresh-unit-check.mjs tools/sandbox/theme-unit-check.mjs`; native Pest `tests/Feature/ProxySignInTest.php` plus the full suite. Runtime environments, cookies, databases and raw private logs remain ignored under `.cache/preview/`.

## Still open — do not conflate preview, source and production

The actual Arena browser must retry the updated preview; an isolated/local proxy simulation cannot certify its live headers, iframe policy or cookie handling. A fresh page load is needed to receive the new hashed module. No repeated external curl attempted after the previous SSL failure.

**Production is not updated by this fix.** GitHub/PR access is not verified access to the installed hosting runtime. No deployment, merge, live migration or production write occurred. Do not deploy the whole pending branch just to change the login. Installed-source identity, a current private backup and verified restore/staging, and the appropriate manual hosting release remain prerequisites. Deployment remains FINAL and auto-deploy remains OFF; the other 36 master gates are not closed by this quick fix. Local genuine MySQL remains blocked.

Screenshots: [desktop](evidence/105/login-desktop.png), [mobile](evidence/105/login-mobile.png). These show the isolated synthetic workspace, not production.
