# Revision 103 — vector identity, glass login and isolated test workspace

Date: 3 November 2026, Asia/Karachi. Candidate branch: `arena/7093000c-woodexinterior`. Production/deployment: **untouched and held**. This report replaces the proposed-only status of these specific revision-102 slices, not the rest of the master backlog.

## Implemented

### Approved filled W + gold dot

- Original path/circle geometry in `platform/public/assets/brand/woodex-mark.svg`; editable SVG lockups for Woodex OS and Woodex Interior. No embedded bitmap. Shared Blade mark uses identical geometry, enforced by a test.
- Shared OS/portal/setup identity, all 142 public HTML header/footer references, 41 organization-logo references, public 404, builder session screen, Studio and SVG favicons updated. ICO compatibility fallback generated at 16/32/48px from the canonical SVG.
- Header/footer CSS preserves the gold dot instead of inverting the entire image. Public URL count, business copy, client logos and contact/estimator contracts preserved.
- Retired raster assets remain available for historical compatibility; active owned brand references use SVG. **No frozen PDF templates/bytes, saved document snapshots, uploaded custom logos or financial records rewritten.**

### Photo/glass authentication

- Original translucent, blurred card over the existing `svc-interior-design.webp` site photograph, inspired by the supplied Preline screenshot; no gated Preline source or reference photograph copied.
- Charcoal controls; responsive light/dark layout; reduced-motion/transparency rules and opaque fallback; accessible status/error messages, explicit show/hide password and new-tab recovery.
- Normal Fortify POST, CSRF, active-account validation, session and redirect authority remain server-side. The JS handles button click and Enter explicitly because a sandbox without `allow-forms` can suppress native activation before a submit event.
- Error/throttle/network feedback unlocks retry; bounded 20-second abort and same-origin redirect validation. Authentication pages are `no-store, private`.
- Demo credential display/prefill defaults **OFF**. It requires explicit opt-in, local/testing environment, SQLite, an actual synthetic-named file (or testing-only memory DB), valid `@woodex.test` address and bounded configured test password. Symlinks to ordinary DB files, URL/driver overrides and read/write connection overrides fail closed without probing them. Production/staging never display the configured test password.
- No automatic login, password bypass, production password exposure or reset of an installed account.

### Fresh synthetic database

- New private disposable SQLite file, separate from the preceding preview database; prior DB and private environment copy retained.
- All **104 existing migrations** applied to the fresh file; no new migration added. Reference data only, plus three active synthetic staff accounts: owner, sales manager, accountant. No historical/customer/financial import.
- Explicit `SyntheticPreviewSeeder`, never invoked by normal DatabaseSeeder/production upgrade. Fails closed outside the opted-in isolated environment, with sending mail, synchronous/external queues or non-preview staff present. Passwords are hashed and not printed by this seeder.
- Mail transport `array`; queue `database`, **no worker started**; no provider send/call. Normal role permissions retained; manager/accountant both browser-tested to receive 403 for owner system health.
- This is **not** a second production/chat database, a supported local MySQL runtime or a production restore/staging rehearsal.

### Bounded product slices

- **SEO:** separate read-only Canonical & social tab alongside existing Search listings/SEO agent. Local public-file audit for missing/multiple/non-HTTPS/different-target canonical, intentional noindex review, OG gaps and JSON-LD syntax/shape. Uppercase/parameterized JSON-LD MIME types handled. Realpath/readability/2MB boundary; no external URL fetch, edit, publish, redirect or indexing-rule removal. Missing content/pages and owner-reviewed fixes remain pending.
- **Assistant:** two permission-gated runtime/connection diagnostics added to the existing nine cited deterministic questions. Runtime reports actual driver/SELECT 1, PHP/Laravel, build-entry/config checks and explicitly untested worker/browser retention. Connections report configuration presence, **not reachability or delivery**. No secret/DSN/exception text returned, provider integration or executable fix. Recommendations require owner review; no write mechanism added. Diagnostic rows wrap on mobile.
- **Selective UI:** permission-rendered sidebar client filter, temporary group expansion, clear/no-result states, compact charcoal stat surfaces/tabular numerals/active indicator and reduced-motion treatment. Existing routes, seven Website tools and native JS stack retained.

## Executed validation

| Check | Result | Reproducible source / private log |
|---|---|---|
| Full serial native PHP suite, strict warnings/skips | **1,964 tests / 11,035 assertions PASS**, 225.41s final run | Existing Pest suite plus `SyntheticCredentialsTest`, `Assistant/DiagnosticsTest`, `Website/SearchMetadataAuditTest`; `.cache/preview/full-owner-regression.log` |
| Focused initial acceptance | 49 tests / 230 assertions PASS | `.cache/preview/103-focused.log` |
| PHPStan entire app | **0 errors** | `.cache/preview/103-phpstan.log` plus final changed-guard scope `103-phpstan-final-guard.log`; native runner restart adapter, serial debug mode, 1GB |
| Pint changed non-Blade PHP | **12 files PASS** | `.cache/preview/103-pint.log` |
| Frontend build | **PASS**; existing large-chunk advisory remains | `npm --prefix platform run build`; `.cache/preview/auth103-glass-build.log` |
| Release/source/enquiry contracts | **92 PASS** | `node --test tools/release/preflight.test.mjs tools/release/source-ci.test.mjs tools/website/enquiry-forms.test.mjs` |
| Theme/admin/auth source/helper contracts | **55 PASS** | `node --test tools/sandbox/admin-refresh-unit-check.mjs tools/sandbox/theme-unit-check.mjs tools/sandbox/auth-unit-check.mjs` |
| Real Chromium preview checks | **27 PASS** | `tools/sandbox/branding-login-check.mjs`; `.cache/preview/103-brand-auth-browser.json` |
| Whitespace | `git diff --check` PASS | Local candidate tree |

Browser checks cover: prefill without login; 360/390/768/1440px light/dark glass layout with no overflow; empty/password/server/throttle/network feedback; actual async login/session reload; native JS-disabled login; cross-site stand-in iframe **without `allow-forms`**; sidebar filtering; both SEO tabs via actual Livewire; both diagnostics and mobile wrapping; both additional role logins/403 restrictions; public SVG header/footer and uninverted colours. HTTP 419/429/503 and network failure are deliberately mocked; other stated login/Livewire/role actions use the real local backend. Repeat runs isolate the real limiter using TEST-NET client addresses in the trusted-proxy synthetic runtime; production guards are not changed.

Browser acceptance exposed and corrected: a mobile grid overflow; a malformed Blade comment rendered as text; prefix ordering that removed the effective standard backdrop filter in the built CSS; and long diagnostic details that needed wrapping. Final source and built assets include those fixes. An overlapping repeat run hit normal login throttling; isolated limiter fixtures make the final harness repeatable without changing server authentication.

### Visual evidence

- [Desktop login](evidence/103/login-desktop.png)
- [Mobile login](evidence/103/login-mobile.png)

These screenshots contain only intentionally displayed synthetic test credentials, never a production password.

## Explicit limitations and remaining gates

- Local suite/browser results concern the current source tree/native PHP 8.5.12 **SQLite** preview. Hosted exact-new-SHA MySQL CI is separate; prior 9c20de1 MySQL acceptance does not certify revision 103.
- Actual Arena parent/proxy/browser retention still requires owner confirmation. Stand-in iframe success is not actual Arena certification. Cookies/proxy policy were not relaxed in production.
- Browser-specific 2FA/intended redirect, 20-second expiry and rapid duplicate-submission scenarios are not newly certified by this harness; corresponding existing native tests/helper guards are not substitutes. Other Fortify forms still use native fallback and may need a new tab in a sandbox that disallows forms.
- Local supported MySQL acquisition remains blocked; MySQL concurrent writers, installed identity, complete both-brand/PDF rendering, private backup/verified restore and isolated staging/release rehearsal remain open.
- SEO content/publishing decisions, compact Website Chat/lifecycle work, whole-action media/theme/speed/integration checks, provider policy/connector and conditional owner Telegram receipt remain in `ox-pending.md`. No external AI request or Telegram report sent.
- **No production data/write/import/account reset/migration, merge or deployment. Deployment stays FINAL; auto-deploy OFF.**
