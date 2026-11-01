# Checkpoint79 — screenshot-matched admin refresh evidence

**1 November2026 · local UI slice verified; not full-system/live deployment acceptance.** [Main report](../../79_SCREENSHOT_ADMIN_REFRESH_2026-11-01.md) · [results](results.json).

## Evidence

- `before-changed-source-hashes.json`, `after-changed-source-hashes.json`: bounded edited-source identity. Existing theme-unit test file itself is unchanged; new tests are separate. `.gitignore` only adds excluded scratch.
- `before-vs-published.json`: six UI files exactly matched published c437cad before edits. The old colors/untitled Website group are in the published source, not just a cached screenshot.
- `protected-source-hashes.json`:965 source files still identical—Accounts28,Projects26,database240,public619,PDF50,shared/site CSS2. Public compiled assets are separately built/hashed, not included in this unchanged count.
- `new-source-hashes.json`, `build-hashes.json`: new tests/proof/harness and13 built frontend files.
- `node-tests.log`:49 passed (42 new palette/contrast/actual pre-paint/store +7 existing theme cases),0 failed.
- `nav-proof.json`:32 pure-navigation assertions on PHP8.4.25WASM; no Laravel/DB boot. Before/after group JSON proves only one title changed, with all47 destinations/routes/permissions preserved.
- `browser-results.json`, `browser-final.log`:24 successful compiled-CSS/layout fixture cases;360/390/768/1440px,Light/Dark/System,both device preferences; no overflow/missing requests. **Not live/backend authentication, permission-filter, save or PDF acceptance.**
- `charcoal-layout-fixture.png`, `light-mobile-fixture.png`:clearly labeled synthetic local style fixture screenshots, not owner records or a deployed app.
- `npm-ci.log`, `build.log`:standard npm-lock install/build, engine and chunk-size warnings retained. Log copies normalize trailing whitespace only.
- `qa-dependencies.json`:excluded QA tool versions, not application Composer dependencies/reproducibility.
- `final-checks.json`, `artifact-hashes.json`:sanitized final local verification and artifact identities; artifact manifest excludes itself.

## Reproduction boundaries

No production environment/APP_KEY, private records, provider calls, DB/migration/imports, hosting changes or diagnostic scripts. The new two native Pest tests are **not executed** here. Native PHP8.5/standard Composer/MySQL/real-HTTP browser/full restore acceptance is still required for a full release.

Use Node24.18.0 for the QA dependencies recorded in JSON. Established php-wasm tooling is PHP8.4.25; the pure navigation script loads only AdminNavigation, never Laravel. Run:

```sh
node --test tools/sandbox/theme-unit-check.mjs tools/sandbox/admin-refresh-unit-check.mjs
# With native PHP>=8.4, or the established excluded WASM CLI:
php tools/sandbox/admin-navigation-proof.php
npm ci --prefix platform --ignore-scripts --no-audit --no-fund
npm run build --prefix platform
```

Browser harness expects excluded `.cache/ui79` dependencies and freshly generated `nav-after.json` (`AdminNavigation::groups()` JSON). It intercepts every browser request and serves only the actual build assets/local fixture; no external website is fetched. This sandbox needs the npm Chromium package's bundled shared libraries in `LD_LIBRARY_PATH`; they were extracted to `.cache/ui79/browser-libs/lib`. Initial launch failure is disclosed, not counted as successful checks. No system/hosting packages were changed.

Do not publish unreviewed local-only files or force-push old ancestry. Confirm the hosting-connected branch's automatic-on-push trigger is OFF/controlled before any publication. A push/deployment is a later observable result, not something proved by this local evidence snapshot.

Owner first confirmed automatic deployment was ON and publication was held. The owner has now confirmed **Auto deploy OFF**. `publication-gate.json` records UI-only branch/PR publication scope, with no deployment or migration.
