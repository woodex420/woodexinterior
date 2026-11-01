# 01 · Source baseline and diff

**Phase 0 deliverable.** Read-only. Nothing in this document was inferred from a plan, a README or a
previous report: every number below was produced by a command run against the checkout on
19 Oct 2026 (Asia/Karachi) and is reproducible with the command shown.

Status words follow the master prompt: **Verified** (command output seen), **Observed** (read in
source, not executed), **Inferred** (reasoned from evidence), **Blocked** (access prevents it),
**Not tested**.

---

## 1. Repository and identity

| Item | Value | How it was checked | Status |
|---|---|---|---|
| Repository | `github.com/woodex420/woodexinterior` | `git remote -v` | Verified |
| Default branch | `master` @ `d77016bfbf36d695c2c4f5c55c1c380ec4b443a9` | `git ls-remote --heads origin` | Verified |
| Audit branch | `arena/8be318a2-woodexinterior` | `git branch --show-current` | Verified |
| Branch HEAD | `b817322de95a4ccbd146238c42d931333523b884` | `git rev-parse HEAD` | Verified |
| Remote HEAD | same `b817322…` | `git ls-remote --heads origin` | Verified |
| Working tree | clean | `git status --porcelain` (empty) | Verified |
| Reference commit in the brief | `fd6d4c73de992c9a83dee234b0c873c536fb2474` | `git cat-file -t fd6d4c7…` → commit | Verified |
| Reference commit subject | *P8.5 part 3: web chat widget — rule-based intake into the Conversations inbox (off by default)* · 17 Oct 2026 | `git log -1 fd6d4c7` | Verified |
| Reference commit position | **ancestor** of HEAD | `git merge-base --is-ancestor fd6d4c7 HEAD` → yes | Verified |
| Common ancestor | `fd6d4c7` itself (the branch has not diverged from it) | `git merge-base fd6d4c7 HEAD` | Verified |

**Consequence.** The brief's "reference commit" is not a separate baseline: it is the last commit
before the 39-commit Website/builder run on this same branch. Everything the brief asks to audit is
present at HEAD, and the diff against the reference is exactly the Website work plus its docs and
assets.

Other branches present on the remote at audit time:

```
master                                  d77016b
arena/8be318a2-woodexinterior            b817322   ← this session
arena/a2a9b311-woodexinterior            f6ad58a
arena/fe1287c9-woodexinterior            08490e9
```

No `arena/8a776c65-marketingwoodex` branch exists any more (see §6, access gaps).

---

## 2. Diff against the reference commit

```
git rev-list --count fd6d4c7..HEAD        → 39 commits
git diff --shortstat  fd6d4c7..HEAD       → 326 files changed, 25850 insertions(+), 494 deletions(-)
git diff --name-status fd6d4c7..HEAD      → 191 A, 132 M, 2 D, 1 R087
```

Changed files by area (top-level paths):

| Area | Files | What changed |
|---|---:|---|
| `platform/app/*` | 178 | Website builder, sections, templates/audit, old-admin parity screens, plus earlier module work between the two reference points |
| `platform/resources/*` | 51 | Blade views for the builder and the parity screens |
| `platform/tests/*` | 40 | Website tests (`tests/Feature/Website`, 26 files) and others |
| `platform/database/*` | 28 | Migrations, seeds, the generated template data |
| `tools/*` | 9 | Sandbox preview, builder/page checks |
| `platform/public/*` | 8 | Built assets only (`build/assets/*`, manifest) |
| `docs/*` | 7 | Plan/status documents |
| `platform/routes/*` | 3 | Website/admin routes |
| `platform/STATE.md`, `PROJECT_STATUS.md` | 2 | Progress logs |

Deleted/renamed files are only hashed Vite assets (`admin-D4pA9SLi.css` deleted, the JS chunk
renamed, `site-DHUcrYNl.css` deleted) — no source file was removed.

Against `master`, the branch is **114 commits ahead, 1,698 files changed, +191,860 lines** — this
branch contains the entire platform, not a patch.

---

## 3. Repository anatomy (tracked files)

```
git ls-files | wc -l                       → 1738 tracked files
git ls-files | xargs wc -l (php/blade/js/css, excluding build) → 107,429 lines
```

| Top level | Files | Role |
|---|---:|---|
| `platform/` | 1,615 | The Laravel application (app, routes, resources, database, tests, public) |
| `docs/` | 41 | Plans, PRD, schema, deployment, decisions, prior audit (docs/29) |
| `woodex/` | 40 | The earlier static studio site — a **design reference**, not served by the app |
| `tools/` | 37 | Sandbox runtime/checks, Hostinger deploy hook, website audit/compare tools |
| root | 5 | `README.md`, `PROJECT_STATUS.md`, `.htaccess`, `composer.json`, `.gitignore` |

Inside `platform/` (verified counts):

| Item | Count | Command |
|---|---:|---|
| Migrations | 64 | `ls database/migrations \| wc -l` |
| Models (all namespaces) | 75 | `find app -path "*/Models/*.php"` |
| Livewire components | 89 | `find app/Livewire -name "*.php"` |
| Blade views | 158 | `find resources/views -name "*.blade.php"` |
| Controllers | 22 | `find app/Http/Controllers` |
| Domain PHP files | 347 | `find app/Domain -name "*.php"` |
| Policies | 8 | policy classes under `app/Domain/*/Policies` |
| Jobs | 4 | `find app -path "*Jobs*"` |
| Scheduled entries | 24 | `grep -c "Schedule::" routes/console.php` |
| Test files | 122 | `find tests -name "*Test.php"` |

`TODO` / `FIXME` / `XXX:` markers across `platform/app`, `platform/routes` and
`platform/resources/views`: **0** (`grep -rn`). There is one honest "not implemented" string in the
Integrations screen (`resources/views/livewire/admin/system/integrations.blade.php:73`) — see
`03_EXISTING_FEATURES_MATRIX.md`.

---

## 4. Runtime and dependency baseline

Installed versions were read from the runtime's `vendor/composer/installed.json` and the committed
`package-lock.json`; they are **Observed on the sandbox runtime**, not asserted for production.

| Component | Version | Source |
|---|---|---|
| PHP | 8.4.25 | `php -v` (php-wasm runtime) |
| Laravel | 13.37.0 | `vendor/composer/installed.json` (require `^13.36`) |
| Livewire | 4.4.7 | same (require `^4.4`) |
| Pest | 5.3.3 | same |
| Larastan / PHPStan | 3.13.0 | same |
| Pint | 1.32.1 | same |
| spatie/laravel-permission | 8.3.2 | same |
| spatie/laravel-medialibrary | 11.23.10 | same |
| spatie/laravel-backup | 10.3.3 | same |
| Node | 22.22.3 | `node --version` |
| Vite | 8.3.4 | `package-lock.json` |
| Tailwind CSS | 4.3.3 | `package-lock.json` |
| Alpine | 3.17.4 | `package-lock.json` |
| Installable Composer packages | 167 | `installed.json` |

**Lockfiles**

| Lockfile | Present | Consequence |
|---|---|---|
| `platform/package-lock.json` | **Yes** | JS install is reproducible |
| `tools/mcp-bridge/package-lock.json` | **Yes** | — |
| `composer.lock` (root or `platform/`) | **No** | `composer install` resolves fresh versions each time; a dependency can change between deploys without a code change |

The root `composer.json` is a Hostinger deploy manifest whose `require` block must stay identical to
`platform/composer.json` (enforced by `tests/Unit/DeployManifestTest.php`, which passed).

---

## 5. Build, test and quality commands actually run

| Command | Environment | Exit | Result |
|---|---|---:|---|
| `npm run build` (in `platform/`) | Node 22, Vite 8.3.4 | 0 | Built in ~1.3 s; assets written to `public/build/assets` |
| `vendor/bin/pest tests/Feature/Accounts` | PHP 8.4.25, SQLite `:memory:` | 0 | 28 passed (194 assertions) |
| `vendor/bin/pest tests/Feature/{Crm,Conversations,Sales,System,Telegram,Website}` | same | 0 | 7 + 13 + 31 + 17 + 9 + 174 passed |
| `vendor/bin/pest tests/Unit` | same | 0 | 99 passed (244 assertions) |
| `vendor/bin/pest` over the 74 root Feature files, in 5 batches | same | 0 | 808 passed, 1 skipped |
| `vendor/bin/pest tests/ArchTest.php` | same | 0 | 3 passed |
| **Total** | | **0** | **1,189 passed, 1 skipped, 0 failed (122 files)** |
| `vendor/bin/pint --test` | same | **1** | **33 files with style issues** (list in `03` §6) |
| `vendor/bin/phpstan analyse` (whole app) | same | **1** | **6 errors in 2 files** — `Domain/Website/Contracts/LibraryEntry.php:21`, `Livewire/Admin/Website/SiteHealthIndex.php:121–124` (missing iterable value types; the four `collect` template errors are consequences) |
| `sh tools/sandbox/smoke.sh` | running preview on :8080 | 0 | **SMOKE OK (68 pages)** — every parameterless admin GET route answered 200 |
| `node tools/sandbox/builder-check.mjs` | headless Chromium | 0 | **BUILDER CHECK OK** (preview follows typing, drag, undo/redo, layers, click-to-edit, page check) |
| `node tools/sandbox/page-check.mjs` | headless Chromium | 0 | **PAGE CHECK OK** (51 internal links) |
| `php artisan about` | same | 1 | Blocked by the sandbox runtime (`proc_open` unavailable), not an application defect |

The total was reproduced on 19 Oct by a second run in **9 sequential batches over all 122 files**:
1,189 passed, 1 skipped, 0 failed (`docs/audit/evidence/all-tests.txt`).

Test environment is pinned by `phpunit.xml`: `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`.

Documentation drift found while checking the above: `PROJECT_STATUS.md` states "**1,145 passing** ·
118 files · **Pint 607 files clean** · Larastan 0". The suite is now **1,189 passed / 122 files**,
repo-wide Pint **fails on 33 files**, and PHPStan reports **6 errors in 2 files**. The status file is
stale, not the code (see finding Q-04).

---

## 6. Access gaps (Phase 0 cannot close these)

| Gap | Evidence | Impact | Status |
|---|---|---|---|
| **Frontend V1 source** (admin v2.1 pro: `frontend-v1/builder/*`, `templates-v26.js`, `api/builder.php`) | **Resolved after owner input (19 Oct, 20:05):** repository `marketingwoodex-cloud/-marketingwoodex`, branch `arena/8a776c65-marketingwoodex`, SHA `720d817cbce9d01c3a52278c2c9045c6cd7d2564`. Contains `frontend-v1/builder/templates-v26.js` (47,340 B), `builder.js` (166,844 B), `builder.css` (26,469 B), `api/builder.php` (36,130 B), `_templates/page-templates-v26.json` (47,800 B) | The earlier search covered the `woodex420` org only; this repo is in `marketingwoodex-cloud`. See `09_REFERENCE_SOURCE_VERIFICATION.md` | **Verified** |
| **Production deployment** `woodex.com.pk` / `admin` | `curl https://woodex.com.pk/` → `SSL_ERROR_SYSCALL` (sandbox egress allowlist) | Cannot prove that the deployed commit, database or assets match this checkout | **Blocked** |
| **Production database** | No credentials, by design | Cannot verify data integrity, row counts, indexes or backups in production | **Blocked** |
| **GitHub Actions** | No `.github/workflows` in the repository; workflows exist only as templates in `docs/ci/` | CI is **not running**; the green suite above is a local run, not a pipeline | Verified |
| **Owner-side services** | Meta/LinkedIn app review, WhatsApp/Turnstile keys, off-site backup destination | Those features are credential-gated and cannot be exercised end-to-end here | **Blocked** |

No secret values were read or printed. `.env.example` was inspected for **names only** (54 variables).
One separate repository under the account, `woodex420/woodex`, commits a root `.env` containing
`VITE_SUPABASE_URL`, `VITE_SUPABASE_ANON_KEY`, `VITE_STRIPE_PUBLISHABLE_KEY` and
`VITE_CUSTOM_DOMAIN` — publishable client keys, not server secrets, but a hygiene issue outside this
repository (recorded for the owner; not modified).

---

## 7. Baseline verdict

- **Source baseline is reproducible** at `b817322…`; the reference commit is its ancestor.
- **The application builds and its test suite is green** (1,189 passed, 0 failed), and every admin
  screen answers 200 on the seeded sandbox.
- **Two quality gates are not green as documented**: repo-wide Pint (33 files) and CI (inactive).
- **Dependency resolution is not reproducible** without `composer.lock`.
- **Production parity is unverifiable from this environment.** The v2.1 pro reference source was supplied and verified after the first pass — see `09`.
  Any statement in later phases about "the old admin did X" must cite the exported frontend, the
  owner's screenshots, or a recovered branch — never memory.
