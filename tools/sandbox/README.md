# Sandbox tooling (AI development environment only)

## Current native preview (revision105)

The active isolated preview now uses native FrankenPHP/PHP8.5.12 with private `.cache/preview` runtime/dependencies and a fresh synthetic SQLite DB. The older php-wasm bootstrap below is historical; do not run it over the active native preview. This does not supply a supported local MySQL engine.

- `node tools/sandbox/branding-login-check.mjs`:30 Chromium checks (including a controlled HTTPS→HTTP/internal-host proxy, not the actual Arena parent); requires the existing private Playwright/Chromium dependency bootstrap, local8080 and opted-in synthetic login. It refuses a non-synthetic target. Actual Arena/proxy certification is separate.
- `node --test tools/sandbox/auth-unit-check.mjs tools/sandbox/admin-refresh-unit-check.mjs tools/sandbox/theme-unit-check.mjs`:57 pure source/helper checks, no backend writes.
- `SyntheticPreviewSeeder` is explicit-only, defaults OFF, requires isolated local/testing SQLite/non-sending mail/no worker. Never run it against an installed DB.
- See [revision105](../../docs/audit/105_PROXY_SAFE_SIGN_IN_QUICK_FIX_2026-11-03.md) for exact boundaries and evidence. Never commit private environments, databases or provider credentials.

## Historical php-wasm tools


The AI coding sandbox has Node.js but **no native PHP or Composer**, packagist is blocked and every VM reset wipes
`/tmp`. These scripts rebuild a full PHP 8.4 dev environment there in about 3–4 minutes. They are **not** used by CI
(real PHP + Composer, `docs/ci/`) or by Hostinger (`docs/06`), and nothing here contains secrets.

| File | What it does |
|---|---|
| `setup.sh` | Full rebuild after a reset: php-wasm, `vendor/`, front-end build, runtime copy, preview `.env`, demo database, headless Chromium. |
| `php-cli.mjs` | PHP 8.4 CLI on php-wasm (`/tmp/woodex-run/php`). Process functions are disabled (php-wasm cannot spawn). |
| `resolve.mjs` | Composer substitute: resolves `platform/composer.json` against GitHub tags and writes a Composer-compatible `vendor/`. Never commit a lock file made from it. |
| `sync.sh` | Copies `platform/` into the runtime copy `/tmp/woodex-run/app` in place (safe while the preview runs). |
| `preview-env.sh` | Preview values in the runtime `.env` (iframe-safe cookies, root-relative assets, demo owner). |
| `server.mjs` | Live-preview server: static files from Node, Laravel through php-wasm, absolute self-URLs rewritten to root-relative, gzip/brotli. Request log `/tmp/woodex-run/requests.log` has start / done / ABORTED lines with proxy headers, cookie **names** (`cookies=`) and a short browser tag (`ua=Windows/Chrome150`; checks show `HeadlessChrome` or `curl`). A preview-only **beacon** is injected into HTML: `BEACON js=1`, then `loaded=1&alpine=…&livewire=…&cookieKept=…&framed=…`; a `<noscript>` pixel logs `js=0`; `PREVIEW_BEACON=0` turns it off. **Cookie memory** (D-52): the preview proxy drops the `Cookie` request header, so for real browser requests through the public preview host the server stores the cookies Laravel sets and sends them to PHP. Log notes: `memory+name` means the request had no cookie, `memory~name` means it had an older value. The memory is kept in `/tmp/woodex-run/cookie-memory.json`; `PREVIEW_COOKIE_MEMORY=0` turns it off. **Demo-account box** on the sign-in form with a one-click sign-in; hide it with `PREVIEW_LOGIN_HINT=0`; change the account with `PREVIEW_LOGIN_EMAIL` / `PREVIEW_LOGIN_PASSWORD`. |
| `smoke.sh` | Logs in and requests every parameterless admin GET route (all must be 200). |
| `assets-check.sh` | Checks a page's CSS/JS links are root-relative and load with the right content type. |
| `browser-check.mjs` | **Real Chromium** check (`@sparticuz/chromium` + `puppeteer-core` from npm): styles applied, Alpine/Livewire running, no failed requests or console errors, charts drawn once and inside their card, sidebar collapse, `wire:navigate`, mobile drawer, lead form with and without JS (`SUBMIT=1` also sends a real enquiry). **Quote builder:** it edits a quantity, checks that the server total changes, then puts the value back, which catches Livewire `wire:model` modifier problems (D-55). It also checks that the builder fits a phone screen. Screenshots go to `/tmp/woodex-run/shots/`. |
| `iframe-check.mjs` | **The preview as Arena shows it:** headless Chromium with the app in a **cross-site iframe**, behind a stand-in for the preview proxy (public Host header, no Referer). It runs twice: once with third-party cookies blocked, once with a proxy that drops **every** cookie (what the real preview proxy does, seen on 2026-10-15). Each run checks: a wrong password comes back to the form with the error; a double click on the demo-login button sends one request and opens the dashboard; the sign-in page leads back to the admin while signed in; and `wire:navigate` plus a full reload stay signed in. It uses ports 9090–9092 on 127.0.0.1 only; screenshots are saved as `shots/iframe-*.png`. |
| `lint.php` | `php -l` for a whole tree without spawning processes. |

## Everyday use

```sh
sh tools/sandbox/setup.sh                                   # after a VM reset (~3-4 min)
PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs   # start the preview (use start_process, cwd /tmp/woodex-run)

sh tools/sandbox/sync.sh                                    # after editing platform/ (server can keep running)
(cd platform && npm run build)                              # after changing classes, CSS or JS

cd /tmp/woodex-run/app && /tmp/woodex-run/php vendor/bin/pest --colors=never
sh tools/sandbox/smoke.sh && sh tools/sandbox/assets-check.sh / && node tools/sandbox/browser-check.mjs
node tools/sandbox/iframe-check.mjs                         # sign-in inside a cross-site iframe, cookies blocked/dropped
```

Demo login on the preview: `owner@woodex.test` / `Woodex-Preview-2026` (sandbox only).

## Website check (public site)

```sh
node tools/sandbox/home-check.mjs          # home page: desktop, phone, JavaScript off
node tools/sandbox/page-check.mjs /interior-design/ /about/   # any page of the site, one or many
```

`home-check.mjs` checks: no console errors or 404s; no horizontal scrolling at 1440px and 390px; the hero photograph
loads; the `h1` really picks up the clamp size and the Nohemi heading font (a missing `npm run build` shows up as a
15px `h1`); and without JavaScript the enquiry fields, its button, the FAQ answer and the menu are all visible (D-49).

`page-check.mjs` does the same for one or more pages of the site (a CMS page, by default `/interior-design/`): one
`h1`, an `h2`, no horizontal scrolling, alt text and width/height on every image, JSON-LD that parses, a canonical
URL, and every internal link that answers 200 (or one redirect to a page that does). Screenshots land in
`/tmp/woodex-run/shots/`.

## Installer check (Hostinger Git deploy, D-56…D-58)

Simulates what Hostinger's Git deploy leaves behind (the repository, no `.env`) and walks it through the web installer
and a post-deploy database update in a real browser. The site lives under `/home/user` so that the `/home/<user>/`
rule finds the home folder; delete both folders afterwards (they are not part of the repository).

```sh
SIM=/home/user/domains/interior.test/public_html
rm -rf /home/user/domains /home/user/woodex-os && mkdir -p $SIM
cd /home/user/woodexinterior
git ls-files -z --cached --others --exclude-standard | tar --null -T - -cf - | tar -xf - -C $SIM
ln -s /tmp/woodex-run/app/vendor $SIM/platform/vendor
# start_process (cwd /tmp/woodex-run):
#   APP_DIR=$SIM/platform PORT=8081 PREVIEW_COOKIE_MEMORY=0 PREVIEW_LOGIN_HINT=0 node --stack-size=8000 $T/server.mjs
node tools/sandbox/installer-check.mjs          # then stop the server and rm -rf /home/user/domains /home/user/woodex-os
```

It checks: every page of a fresh site opens `/install`; the setup code file is private (0600) and has a copy in the
site folder (`WOODEX-SETUP-CODE.txt`, named on the form, not served, deleted after installing); a wrong code is
refused and writes nothing; the right code installs (SQLite) and continues to step 2 by itself; `.env` (0600) and
`storage/` are in `~/woodex-os/interior.test/`, nothing in the site folder; `/install` is 404 afterwards; the owner
signs in; a new migration file sends the owner to System → Database update, and Apply opens the admin again.

## Gotchas

- Always run Node with `--use-system-ca` for HTTPS downloads (resolver).
- php-wasm keeps stat data of deleted files: never delete compiled views or app files while the server runs
  (`sync.sh` overwrites in place); restart the server instead. `artisan view:cache`, `view:clear` and
  `optimize:clear` delete compiled views too, so restart the preview after running them (symptom: 500
  "filemtime(): stat failed").
- Beacon `js=0` right after a `wire:navigate` is an artefact (Livewire parses the next page with `DOMParser`, which
  renders `<noscript>`); only `js=0` on a full page load means scripts are off in that browser.
- In-sandbox `curl` cannot see what the user's browser sees. UI work is only done after `browser-check.mjs` and
  `iframe-check.mjs` pass.
- The preview proxy drops the `Cookie` request header. Requests from the owner's browser show `cookies=-` in the log,
  even though the beacon reports `cookieKept=1` (the browser has the cookies). The cookie memory covers for it.
  Without it, every request starts a new session and sign-in bounces. Laravel 13 still accepts such POSTs because
  `PreventRequestForgery` trusts `Sec-Fetch-Site: same-origin`, so there is no 419 to warn you.
- Demo quotations come from `DemoQuotesSeeder` (called by `DemoSeeder`, so `setup.sh` creates them). Scratch
  quotations made while testing in the preview take real numbers. Remove them, and reset `number_sequences`, before
  handing the preview back.
- Every preview request reaches Laravel from 127.0.0.1, so the sign-in limiter (5 POSTs per minute per e-mail + IP)
  is shared by the owner and the checks. Leave a minute between back-to-back `iframe-check.mjs` / `smoke.sh` /
  `browser-check.mjs` runs, or the next sign-in gets a 429.
- The preview proxy drops the `Referer` header; never rely on `url()->previous()` for important redirects (D-51).
- Requests from `iframe-check.mjs` carry the public Host header too; tell them apart by `ua=…/iframe-check/…`.
- The public preview host (`https://8080-<sandbox>.e2b.app`) is not reachable from inside the sandbox; the checks use
  `http://127.0.0.1:8080`, which also proves the pages do not depend on one particular origin.


### Google connection dialog acceptance

`node tools/sandbox/google-check.mjs` checks the unconfigured Google dialog, callback checklist and staff-login
setup guidance at desktop/390/360px without making Google requests or editing the demo business database.
The iframe check also covers this dialog. Actual OAuth network behavior is covered with PHP HTTP fakes until
an owner registers a real app and runs deployment acceptance (`docs/GOOGLE-OAUTH-SETUP.md`).

When an active preview has user edits, run the broad quote-sending `browser-check.mjs` against an **isolated
application/database copy**, not the user's active preview. Set `BROWSER_DB_FILE` to that copy's SQLite path and
optionally `BROWSER_DB_COPY` for its backup location, and pass that isolated server's base URL. The script otherwise
backs up/restores the default preview DB and could overwrite edits made concurrently. Read-only targeted checks
should not restore a database at all.


### Isolated QA copies

`SANDBOX_APP_DIR=/tmp/your-qa/app sh tools/sandbox/sync.sh` syncs source/assets to an existing separate QA runtime
without touching `/tmp/woodex-run/app`. Give that copy its **own vendor directory, private env DB path, storage,
bootstrap cache and SQLite database**; sharing vendor through a symlink can point Composer's app autoloading back
to the original runtime. Use SQLite's backup API for a consistent live database copy, not an uncoordinated file copy.

Run an isolated server with `APP_DIR=/tmp/your-qa/app PORT=8090 PREVIEW_COOKIE_MEMORY_FILE=/tmp/your-qa/cookies.json node --stack-size=8000 tools/sandbox/server.mjs`.
Never run destructive/browser-restoring QA against the owner's active database. For the existing broad browser
script explicitly set `BROWSER_DB_FILE`/`BROWSER_DB_COPY` to QA-only paths and pass the QA base URL. Its legacy
homepage lead-form selectors currently mismatch the served homepage; see docs/19 for honest test boundaries.
The native PHP/MySQL harness in `../docker/` is prepared but not executed in this restricted sandbox.

## Quotation workbench proof (Step 48)

`PORT=33143 node tools/sandbox/quotation-workbench-check.mjs` uses the four demo quotes
from `setup.sh`. It checks interior WI-10102 and furniture WF-10050: persistent preview,
quantity/rate/client/scope updates with focus retained, the saved editor total unchanged
while a quantity is still unsaved, all three layout switches, and 390px mobile width.
It restores the preview SQLite file in `finally`. Screenshots go under
`/tmp/woodex-run/shots/step48-*`; never point this sandbox-only check at production.


## Furniture documents proof (27 October)

Prepare a **separate** local runtime at `/tmp/woodex-furniture-qa/app` with the current source/build, dependencies,
a coherent copy of the demo SQLite database and its own `.env` DB_DATABASE pointing to
`/tmp/woodex-furniture-qa/app/database/database.sqlite`. Never use a production copy or restore over the owner's
active database. Start its server using the process tool:

```sh
APP_DIR=/tmp/woodex-furniture-qa/app PORT=33144 PREVIEW_COOKIE_MEMORY_FILE=/tmp/woodex-furniture-qa/cookies.json node --stack-size=8000 tools/sandbox/server.mjs
```

Then run `node tools/sandbox/furniture-documents-check.mjs` and
`PORT=33144 node tools/sandbox/furniture-iframe-check.mjs`. The first creates temporary Furniture documents,
checks unsaved product/spec/rate previews, phone width, full conversion/issue and Interior regression, exports
sample/stress PDFs under `/tmp/woodex-run/shots/furniture-*`, and restores **only the isolated** SQLite file in
`finally`. The PHP fixture rejects a non-local or wrongly configured runtime, and the mutating browser proof
refuses port33143. The second verifies brand selection and nested live invoice HTML with third-party cookies
blocked and with all cookies stripped. Stop the isolated QA server afterwards; leave33143 alone.


### Furniture A4 revision 2 polish

New Furniture defaults to 50% advance, with automatic matching Terms and conditions. Old snapshots without
revision2 retain the exact previous renderer; existing editable drafts adopt via Apply current design. Run
`/tmp/woodex-run/php vendor/bin/pest tests/Feature/Sales/FurnitureDocumentsTest.php tests/Feature/Sales/FurniturePolishTest.php --colors=never`
from the sandbox app for the 30-case focused check. See audit38 for final gate and explicit boundaries.

The isolated browser replay additionally exercises 37.5% → matching terms/amount, terms/signatory show/hide,
custom names in both builders, and a return to 50%. Exported PDFs include `furniture-47500.pdf` (47,500 with
23,750 advance/remainder) and numbered long descriptions on 45 rows. Inspect PDF rasters and every page's
header/footer/text bounds; HTTP success is not PDF/browser proof. Latest stress output is eight pages (not the
initial short-description four-page fixture). HTML previews remain continuous rather than paginated PDF viewers.

The iframe harness is non-document-mutating and can also verify the active preview with
`PORT=33143 node tools/sandbox/furniture-iframe-check.mjs`. Never run the **mutating/restoring** document harness
against33143. Final primary verification retained six quotes, 26 contacts, zero invoices and zero QA contacts.

### Revision48 theme + early web-chat capture

- `node --test tools/sandbox/theme-unit-check.mjs` exercises the actual theme store (legacy preferences,
  System, device/cross-tab changes and blocked storage) without a browser/dependencies.
- `theme-chat-fixture.php` mutates **only** `/tmp/woodex48/qa/app` with a separate SQLite database,
  local environment, sync queue, array mail and blank Telegram/WhatsApp tokens. Prepare that isolated
  copy first; never point it at owner/main/production data. `--init` is fresh-only; `--documents`
  creates synthetic Interior/Furniture drafts and a draft invoice; `--issue-document` issues only that
  synthetic invoice to exercise the actual PDF viewer. `--inspect` returns synthetic fixture state.
- `node tools/sandbox/theme-chat-check.mjs` requires that fresh QA copy running on port33144 with
  the sandbox demo-login helper, plus `--init`, `--documents` and `--issue-document` above. It uses
  distinct Chromium **processes** for staff and visitor (serverless Chromium's `--single-process`
  does not reliably support incognito contexts). No provider calls/sends are part of this proof.
  It checks real visible polling/popup, native sends, SQL capture before service, draft-safe switching,
  mobile layouts, theme/Assistant/charts and real Interior/Furniture/invoice PDF canvas output.
  Output is transient under `/tmp/woodex48/browser`; curated release evidence belongs in `docs/audit/`.

- `node tools/sandbox/theme-chat-draft-check.mjs` runs after that walkthrough against the same QA copy.
  It checks the real serialized Livewire server response and native same-thread reopening without
  sending a customer message. Do not wait for global network-idle on an actively polling page.
