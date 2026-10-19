# 06 · QA evidence and release gate

Everything in this document was produced in the sandbox against `b817322` (checkout) and
`/tmp/woodex-run/app` (synced copy, sqlite, seeded demo data). Raw files are in
`docs/audit/evidence/`; commands are reproducible.

**What this proves:** the code compiles, routes resolve, the test suite passes, the public pages render,
and the builder's core interactions work.

**What it does not prove:** production behaviour, the owner's real database, payment/WhatsApp/Telegram
providers, email deliverability, or that every button in 53 admin screens does something. Those remain
release-gate items with named owners.

---

## 1. Environment

| Item | Value |
|---|---|
| Commit | `b817322de95a4ccbd146238c42d931333523b884` |
| Branch | `arena/8be318a2-woodexinterior` |
| PHP | 8.4.25 (php-wasm); extensions: pdo_sqlite, mbstring, dom, openssl, curl |
| Laravel / Livewire / Pest | 13.37.0 / 4.4.7 / 5.3.3 |
| Database | sqlite file, 95 tables, seeded (`DemoSeeder` + website content) |
| Server | `tools/sandbox/server.mjs` on `0.0.0.0:8080` (preview) |
| Browser | headless Chromium via `@sparticuz/chromium` + puppeteer-core |
| Date | 2026-10-19 |

Caveats: php-wasm runs one PHP process at a time, has no `proc_open`, no `sqlite3` CLI and is roughly
5–10× slower than a normal PHP build. That is why the suite is run in batches and why one command
(`artisan about`) exits non-zero. None of these are application defects.

---

## 2. Automated test suite

Method: Pest 5, 122 files (121 discovered under `tests/Feature` + `tests/Unit`, plus `tests/ArchTest.php`),
run in sequential batches of 15 files; each batch's `Tests:` line captured. The whole suite in one
process hangs in php-wasm, so per-file/batch execution is the only reliable method here (recorded in
`01` §5 and the retained error notes).

**Result: 1,189 passed, 1 skipped, 0 failed — 122 files, 8,424 assertions, 9 batches, every batch exit 0.**

```
BATCH 15   (files 1–15)     134 passed (747 assertions)      EXIT 0
BATCH 30   (files 16–30)    184 passed, 1 skipped (893)       EXIT 0
BATCH 45   (files 31–45)    203 passed (864)                  EXIT 0
BATCH 60   (files 46–60)    142 passed (748)                  EXIT 0
BATCH 75   (files 61–75)    117 passed (596)                  EXIT 0
BATCH 90   (files 76–90)    153 passed (1,150)                EXIT 0
BATCH 105  (files 91–105)   95 passed (2,886)                 EXIT 0
BATCH 120  (files 106–120)  131 passed (484)                  EXIT 0
BATCH 122  (files 121–122)  30 passed (56)                    EXIT 0
```

Raw: `evidence/all-tests.txt`, `evidence/all-tests-lines.txt`; `all-tests-fail.txt` is **empty**.
The single skip is intentional (environment-gated provider test).

No batch failed, so there is no failure output to report. This also closes the earlier doubt from the
partial per-file sweep (`evidence/sweep-results.txt`, 16 files before the sandbox window ended): the
complete suite is green at this commit.

Feature groups exercised: website (editor, publishing, versions, templates, sections, redirects, SEO),
CRM (leads, contacts, companies, tasks, visits, pipeline, import, privacy), sales (estimates, quotes,
portal, payments, invoices), accounts (ledger, posting), projects, inbox/conversations, automation,
Telegram (8 files), WhatsApp (4 files), auth/2FA, MCP bridge, installer, system screens, theme tokens.

---

## 3. Route smoke

```
sh tools/sandbox/smoke.sh     → SMOKE OK (68 pages)
```

- 68 GETs: 20 admin entry screens, 20 public URLs, portals, sitemap, robots, theme CSS, API health.
- All returned **200**; zero 302-to-login, zero 500.
- Legacy redirects verified: `/admin/companies` → 301 `/admin/contacts?tab=companies`;
  `/admin/my-day` → `/admin?tab=my-day`; `/admin/pipeline` → `/admin/leads?tab=pipeline`;
  `/admin/rate-book` → `/admin/sales/documents?tab=rate-book`;
  `/admin/receivables` → `/admin/payments?tab=receivables`.
- 404 behaviour: unknown URL → `SiteRouter` → CMS miss → branded 404 (not tested exhaustively).

A 200 does **not** mean the screen works. It means it renders without error for an owner session with
demo data. Screen-level behaviour is covered by the Pest files named in `02` and `03`.

---

## 4. Static analysis and style

| Check | Result | Gate |
|---|---|---|
| `pint --test` | **FAIL** — 33 files (list in `03` §6) | Blocks CI until fixed |
| `phpstan analyse` (Larastan 3, level from `phpstan.neon`) | **FAIL** — 6 errors in 2 files | Blocks CI until fixed |
| `pest` | see §2 | Must be green |
| Blade compile | implicit in every render/route test | — |
| `npm run build` | green; assets hashed in `public/build` | Must be green in CI |

PHPStan findings are type-annotation only (array shapes in `LibraryEntry::toRow()` and
`SiteHealthIndex::pagesWithoutAlt()`); no runtime bug is implied, but the status file's "Larastan 0"
claim is false.

---

## 5. Browser checks

The sandbox has scripts that drive headless Chromium against the preview. They are **not** part of
Pest and are not run by CI today.

| Script | What it checks | Phase 0 run |
|---|---|---|
| `builder-check.mjs` | live preview follows typing, drag reorder, click-to-edit, first-save autosave | **PASS on re-run** — 12 steps; 50 templates; layers; undo/redo; autosave. First run flagged a `net::ERR_ABORTED` on the test-only `__preview-beacon` (navigation cancelled the beacon); second and third runs clean — harness race, recorded as Q-08 |
| `page-check.mjs` | CMS pages: headings, images/alt, links, JSON-LD, desktop+phone | **PASS** — `/interior-design/` (51 links, 31 images, 0 missing alt/size) and `/lahore/dha/` (42 links, 11 images), 1440 px and 390 px, no overflow, valid LocalBusiness/Service/Breadcrumb/FAQ JSON-LD |
| `home-check.mjs` | static home page layout, console errors | **PASS** — 1440, 390 and no-JS; 11 sections; hero image loads; 45 images |
| `iframe-check.mjs` | admin inside the preview frame: cookie/session behaviour, 3rd-party-cookie blocking | **PASS** — 22 checks across two iframe modes (3rd-party cookies blocked, then all cookies dropped); signed in across `wire:navigate` and full reload; MCP/Google dialogs work; the cookie-less proxy mode still signs in |

Raw output: `evidence/builder-check.txt`, `evidence/page-check.txt`, `evidence/home-check.txt`,
`evidence/iframe-check.txt`.

### Q-08 · The builder check is flaky (P3)

The `requestfailed` listener treats the diagnostic `sendBeacon` as a failure when a Livewire preview
reload aborts it. Observed 1 failure in 3 runs, with every functional step passing in all three. The
check should ignore aborted `__preview-beacon` requests. Not an application defect; without the fix,
CI (once activated) will show false reds.

All screenshots land in `/tmp/woodex-run/shots` (not committed; they are large and environment-specific).

---

## 6. Coverage gaps (honest list)

| Area | State | Consequence |
|---|---|---|
| Reports and Tasks | hub render + `ReportsKitTest`; no component behaviour test | Regressions in tab logic would pass CI |
| Every admin screen × every role | not run | S-01 stays open |
| Public website 80 pages | sample-level DOM scan (W-06) + `/about/` byte compare | Visual regressions possible on the other 79 |
| Responsive at 390/768/1440 | only builder/page checks | U-01 |
| Keyboard/focus/ARIA | manual primitives review | U-02; no axe run |
| Email (quote send, reminders) | `Mail::fake` assertions | Real deliverability untested |
| WhatsApp/Telegram live | fakes + signed webhook tests | Provider sandbox untested |
| Payments | ledger/integrity tests, no gateway | No gateway integration exists to test |
| Backup restore | documented, not executed | S-04 |
| Production deploy | not reachable from sandbox | B-04 |
| Load/performance | none | Explicitly out of scope without authorisation |

---

## 7. Release gate

Two different gates; do not conflate them.

### 7.1 Gate for **starting Phase 1** (this audit)

| # | Requirement | Status |
|---|---|---|
| A1 | Baseline documented with SHAs | **Met** — `01` |
| A2 | Every route and nav item inventoried | **Met** — `02` |
| A3 | Every module classified KEEP/MIGRATE/… | **Met** — `03` |
| A4 | Findings have evidence and severity | **Met** — `03`, `07`, `08` |
| A5 | Owner approves scope and phase order | **Pending** — `08` §5 |

### 7.2 Gate for **production release** (later)

| # | Requirement | Current status |
|---|---|---|
| R1 | Pest green (all 122 files) | **Met** — 1,189 passed, 1 skipped, 0 failed at `b817322` |
| R2 | Pint clean | **Fail** (33 files) |
| R3 | PHPStan clean at the agreed level | **Fail** (6 errors) |
| R4 | CI active on `master` | **Fail** (workflows dormant) |
| R5 | Route × role authorization matrix green | **Not tested** (S-01) |
| R6 | No stored-XSS path in staff-authored HTML | **Fail** (S-03) |
| R7 | Public URL ownership decided and implemented | **Blocked on owner** (W-01) |
| R8 | Backup restore drill passed | **Not executed** (S-04) |
| R9 | Production deploy verified (version, migrations, queue, scheduler, mail) | **Blocked** (B-04) |
| R10 | No placeholder pages, dead buttons or fake success messages | **Partially verified** — smoke + code scans; manual screen pass pending (`09` scope) |
| R11 | Responsive/keyboard pass on 12 screens | **Not tested** (U-01/U-02) |
| R12 | Docs/status match reality | **Fail** (Q-04) |

**Verdict: not release-ready.** R2, R3, R4, R6 and R12 are mechanical and close in days. R8/R9 need
access. R5/R10/R11 are the real Phase 6 work. R7 needs a product decision from the owner.

---

## 8. Reproducing this evidence

```sh
sh tools/sandbox/sync.sh
cd /tmp/woodex-run/app
/tmp/woodex-run/php vendor/bin/pest tests/Feature/Website          # a directory
/tmp/woodex-run/php vendor/bin/pint --test                          # style
/tmp/woodex-run/php vendor/bin/phpstan analyse --no-progress        # static analysis
sh /home/user/woodexinterior/tools/sandbox/smoke.sh                 # HTTP smoke
node /home/user/woodexinterior/tools/sandbox/page-check.mjs /interior-design/
node /home/user/woodexinterior/tools/sandbox/builder-check.mjs
```

The preview server must be up (`tools/sandbox/server.mjs`, port 8080) before the last three commands.
