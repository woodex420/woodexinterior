# Sandbox tooling (AI development environment only)

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
