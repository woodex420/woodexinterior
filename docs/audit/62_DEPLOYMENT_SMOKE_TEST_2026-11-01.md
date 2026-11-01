# 62 · Deployment smoke test — 2026-11-01

**Scope:** prove the repository state that is about to become `master` is the complete, organized project and
is deployable to Hostinger by hPanel → **Advanced → Git** (docs/06 §2). Nothing in the application was changed
by this test; it only measures the tree.

**Branch tested:** `arena/d2f81b57-woodexinterior` (= `arena/8be318a2-woodexinterior` @ `6ec9867`, 1 Nov 2026,
merged with `master` @ `e22b5c1`, which contributed only `docs/audit/61_BRANCH_SECURITY_SETTINGS_AUDIT_2026-10-30.md`).

**Sandbox limits:** no PHP binary, no Composer, no MySQL and no browser binary are available here (only the npm
registry is reachable), so the Laravel **runtime** could not be booted. Everything below is what *can* be proven
statically against the committed tree. The PHP-runtime items are listed in §6 as **not tested**, with the exact
hPanel steps that prove them on the real host.

---

## 1. Repository shape (what actually deploys)

| Path | Files | Deployed to | Web-reachable |
|---|---|---|---|
| `.htaccess`, `composer.json`, `composer.lock` | 3 | site folder root | no (routing only) |
| `platform/` | 2014 | site folder `platform/` | only `platform/public/` |
| `docs/` | 306 | site folder `docs/` | no |
| `tools/` | 80 | site folder `tools/` | no |
| `woodex/` | 40 | site folder `woodex/` | no |
| **Total tracked** | **2448** | | **635 files under `platform/public/` are the live site** |

The root `.htaccess` rewrites every request to `platform/public/$1` (and keeps `/.well-known/` for Let's
Encrypt, `Require all denied` when `mod_rewrite` is absent, and denies `WOODEX-SETUP-CODE*` by name). Source,
`vendor/`, `storage/` and any `.env` therefore never answer a web request — verified by reading the rules, and
by the fact that no `.env`, key or credential exists in the tree (§4).

## 2. Website layer — 142 pages, all green

The 142 published pages under `platform/public/` were served exactly as the root `.htaccess` routes them
(directory URL → `index.html`, 404 → Laravel) and crawled.

| Check | Result |
|---|---|
| Pages requested | **142** |
| Pages answering HTTP 200 | **142** |
| Unique local assets/links resolved | **395 / 395 reachable** |
| Broken local references | **0** |
| Pages missing `<title>` / `lang` / meta description / `<h1>` / multiple `<h1>` | **0** |
| External links, `mailto:`, `tel:`, `data:` | skipped (correct) |

Two references are deliberately **not** files and were excluded from the 404 count — both are live Laravel
routes, not dead links:

| Reference | Route | Why it is dynamic |
|---|---|---|
| `/assets/theme.css` | `web.php` → `ThemeCss` | Theme tokens come from the database, so a Theme Builder save re-skins the site without a deploy (D-116). A static stub would shadow it, and `tools/deploy/after-composer.php` deletes any leftover stub. |
| `/site/tags.js` | `web.php` → `SiteTagsController` | Analytics/tag switches set in the admin are read back by every page at runtime. |

Consequence for Hostinger: **the site needs PHP**. `platform/public/index.html` and friends are static, but
`/assets/theme.css` and `/site/tags.js` only exist when the app runs, so opening the folder without a working
PHP 8.4 + `vendor/` renders pages with a stale/404 theme. `platform/public/index.php` already shows an explicit
503 "Setup needed" page instead of a blank 500 when PHP < 8.4 or `vendor/` is missing — that is the correct
first-run behaviour to expect after the first deploy.

## 3. PHP source integrity

1038 PHP files (`platform/app`, `routes`, `config`, `database`, `bootstrap`, `tests`, `tools`) were parsed with
a PHP 8 grammar:

```
PHP files parsed: 1038, syntax errors: 0
```

This proves the tree contains no truncated/corrupt file from the branch transfer. It is not a substitute for
`php artisan test` (see §6).

## 4. Deploy contract — verified piece by piece

| Contract | Evidence |
|---|---|
| Root `composer.json` mirrors `platform/composer.json` | `require` is **identical, 12 packages**; `config.vendor-dir` = `platform/vendor`; `post-install-cmd`/`post-update-cmd` = `tools/deploy/after-composer.php` (present) |
| Front-end build is committed (server has no Node) | `platform/public/build/manifest.json`: **12 entries, 21 files referenced, 0 missing** |
| Two locks are tracked | `composer.lock` (root) and `platform/composer.lock` present — both needed by the parity test |
| Data lives outside every `public_html` | `bootstrap/app.php` → `SiteState::detect()` + `useEnvironmentPath()` / `useStoragePath()` to `~/woodex-os/<site>/storage` (D-57) |
| Installer exists before install and 404s after | `platform/routes/install.php` registered via `bootstrap/app.php` (no `web` middleware, setup-code POST) |
| One cron entry drives everything | `platform/routes/console.php`: queue worker, webhooks, heartbeat, SLA, reminders, digests, automation + `woodex:site-backup` daily 02:45 |
| Nothing secret in Git | no `.env` tracked (only `platform/.env.example`, 70 keys); secret-pattern scan over `app/`, `config/`, `routes/`, `database/`, `tools/`, `woodex/` → **0 hits** (Telegram/WhatsApp/Meta/Google/AWS/OpenAI shapes) |
| Deploy hook survives the first run | `after-composer.php` needs no `.env`/DB, deletes stale `bootstrap/cache/*.php`, creates `storage/` folders, and removes the old `theme.css` stub Hostinger would otherwise keep |

## 5. What is organized now

- One branch, one truth: the complete OS (`platform/` + `docs/` + `tools/` + `woodex/` design reference) with
  `master`'s single audit document kept. Nothing was deleted.
- Deploy manifest, routing, build, installer, cron and data-folder separation are all consistent with
  `docs/06-DEPLOYMENT-HOSTINGER.md` §2 — no code change was required to make the tree deployable.
- The 2447-file / 302k-line branch content is intact; the only merge conflict in the whole transfer was the
  `.gitignore` (master's shorter version vs. the branch's superset) and the branch's version was kept.

## 6. Not tested here — prove these on the host

| Item | Why not here | How to prove it after deploy |
|---|---|---|
| App boot (`platform/vendor`) | no PHP, packagist not reachable | Deployment log: "Installing Composer dependencies" ends with `Woodex OS: PHP packages installed in platform/vendor` |
| Installer end-to-end | needs PHP + MySQL | Open the site → `/install` → setup code from `WOODEX-SETUP-CODE.txt` (or the MySQL credentials proof) |
| Database migrations / seeders | needs MySQL | Installer finishes → admin dashboard loads; System → Database update reports no pending migrations |
| Authenticated admin flows, CRM, quotations, PDFs | needs a session + DB | Admin smoke run: dashboard, Leads, Quote → PDF, Invoice → PDF |
| Browser checks (Chromium) | no browser binary in sandbox | `node tools/sandbox/page-check.mjs /about/` against the live URL |
| Full test gate (`1722` tests / PHPStan / Pint) | needs PHP + Composer | `cd platform && php artisan test` on any PHP 8.4 machine |
| Provider sends (WhatsApp, Telegram, Meta, Google) | credentials are intentionally absent | Only after the owner adds them in `.env` (production, gated — P8/P10) |

## 7. Go-live order (short form of docs/06 §2)

1. hPanel → the site that owns the target folder → **Advanced → PHP Configuration → 8.4** (+ extensions in docs/06 §1)
   **before** the first deploy.
2. **Advanced → Git → Connect → `woodex420/woodexinterior` → branch `master` → Root directory = the target
   folder** (never the main `public_html` while the main domain serves something else) → **Deploy**.
3. Read the deployment log (§6, row 1).
4. Create the empty MySQL database + user in hPanel → open the site → `/install` with the setup code.
5. Add the one cron entry (`platform/artisan schedule:run`, every minute) and enable SSL.
6. Sign in as owner, then System → Health: cron heartbeat + database + storage must be green.

## 8. Known operational rules (do not break after this merge)

- **Never `git clean -fd` inside the site folder** and never restore an old `public/` over the current one —
  pages published from the admin exist only on the server.
- **Never hard-reset `master`** after go-live: the deploy replaces the folder from `master`.
- Rebuild `platform/public/build/` (`npm run build`) before any commit that changes CSS/JS — the server does
  not build.
