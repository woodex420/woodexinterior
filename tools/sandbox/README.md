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
| `server.mjs` | Live-preview server: static files from Node, Laravel through php-wasm, absolute self-URLs rewritten to root-relative, gzip/brotli, request log `/tmp/woodex-run/requests.log` (start / done / ABORTED lines with proxy headers). |
| `smoke.sh` | Logs in and requests every parameterless admin GET route (all must be 200). |
| `assets-check.sh` | Checks a page's CSS/JS links are root-relative and load with the right content type. |
| `browser-check.mjs` | **Real Chromium** check (`@sparticuz/chromium` + `puppeteer-core` from npm): styles applied, Alpine/Livewire running, no failed requests or console errors, charts drawn once and inside their card, sidebar collapse, `wire:navigate`, mobile drawer, lead form with and without JS (`SUBMIT=1` also sends a real enquiry). Screenshots go to `/tmp/woodex-run/shots/`. |
| `lint.php` | `php -l` for a whole tree without spawning processes. |

## Everyday use

```sh
sh tools/sandbox/setup.sh                                   # after a VM reset (~3-4 min)
PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs   # start the preview (use start_process, cwd /tmp/woodex-run)

sh tools/sandbox/sync.sh                                    # after editing platform/ (server can keep running)
(cd platform && npm run build)                              # after changing classes, CSS or JS

cd /tmp/woodex-run/app && /tmp/woodex-run/php vendor/bin/pest --colors=never
sh tools/sandbox/smoke.sh && sh tools/sandbox/assets-check.sh / && node tools/sandbox/browser-check.mjs
```

Demo login on the preview: `owner@woodex.test` / `Woodex-Preview-2026` (sandbox only).

## Gotchas

- Always run Node with `--use-system-ca` for HTTPS downloads (resolver).
- php-wasm keeps stat data of deleted files: never delete compiled views or app files while the server runs
  (`sync.sh` overwrites in place); restart the server instead. `artisan view:cache`, `view:clear` and
  `optimize:clear` delete compiled views too, so restart the preview after running them (symptom: 500
  "filemtime(): stat failed").
- In-sandbox `curl` cannot see what the user's browser sees. UI work is only done after `browser-check.mjs` passes.
- The public preview host (`https://8080-<sandbox>.e2b.app`) is not reachable from inside the sandbox; the checks use
  `http://127.0.0.1:8080`, which also proves the pages do not depend on one particular origin.
