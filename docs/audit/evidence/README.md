# Audit evidence bundle

Raw command output captured during Phase 0 on **2026-10-19**, against checkout `b817322` and the
synced runtime at `/tmp/woodex-run/app` (sqlite, seeded demo data). Files are copied verbatim from
`/tmp` so the evidence survives the sandbox; nothing below was edited after capture.

| File | Produced by | What it proves |
|---|---|---|
| `smoke.txt` | `sh tools/sandbox/smoke.sh` | 68 admin/public GETs; summary line `SMOKE OK (68 pages)`; no non-200 |
| `routes.json` | `php artisan route:list --json > /tmp/routes.json` | The 158-route table used in `02` |
| `navmap.md` | `/tmp/navmap2.php` (boots the app, calls `AdminNavigation::groups()` and the route table) | 53 sidebar items → route → component → test (54 rows because of duplicate Alert Rules) |
| `root-results.txt` | five Pest batches, 15 files each | 808 passed, 1 skipped, 0 failed across the first 75 test files |
| `sweep-results.txt` | `sh /tmp/full-sweep.sh` (per-file Pest) | 16 files individually green before the sandbox window ended; **partial, not a full suite** |
| `pint.txt` | `vendor/bin/pint --test` | exit 1, style failures |
| `pint_files.txt` | same command, file list | exactly 33 files |
| `phpstan_full.txt` | `vendor/bin/phpstan analyse --no-progress --error-format=table` | exit 1, **6 errors in 2 files** |
| `all-tests.txt` | `sh /tmp/run_all_tests.sh` (1,189 tests, 9 batches) | **122 files, 1,189 passed, 1 skipped, 0 failed**; every batch EXIT=0 |
| `all-tests-lines.txt` | same run | per-batch `Tests:` lines used to compute the total |
| `all-tests-fail.txt` | same run | **empty** — no failures |
| `builder-check.txt` | `node tools/sandbox/builder-check.mjs` | `BUILDER CHECK OK` |
| `page-check.txt` | `node tools/sandbox/page-check.mjs /interior-design/ /lahore/dha/` | `PAGE CHECK OK`, desktop + phone |
| `home-check.txt` | `node tools/sandbox/home-check.mjs` | `HOME CHECK OK`, desktop + phone + no-JS |
| `iframe-check.txt` | `node tools/sandbox/iframe-check.mjs` | `IFRAME CHECK OK`, 22 checks in two cookie modes |

SHA-256 at capture:

```
de14f5a56a91c4591dc8a7ca14572ff6f40e71a0dd59f1cf7fe357a727fb4e3f  navmap.md
de386646686b093246f76011fd5e17750f08a91fdb6429236403703f61af6ca6  phpstan_full.txt
94599299a5122a5b0c85d020fb6256c2e14b86d813f179d3eabecebf54b5fc15  pint.txt
d06e226e35fdf11de058f54e1169ca2cd436e4194165de63d51ffe76bbb63cb8  pint_files.txt
a4600d4a3fa2e72a2503730976eadd7a603e948ee010a2e7608281297d77619d  root-results.txt
d1380a3c8120a98718498fd6ce5a4f9ef750bba1041aee06b2df24ba5942d3ec  routes.json
99204b8b732aacf41a43ab99e87fce7ddc052bc6765cc0c4491d9a21da959d2c  smoke.txt
955c7a1af977e1f732c1a46b50877c0942dded8c8f0045dba12defe1134aac4c  sweep-results.txt
654b599308155f6b6371e5495350edd17f79ead89263f2da34a97c2a834674a9  all-tests.txt
accafaefc25027a6c98c8e4fd378ef4cfed247c999e7935215643dc8c99f95e8  builder-check.txt
6b38e464192f45c6a6b9aa4df5956190fae08b393c71836237819619b2687434  page-check.txt
b0a34363f0fcd498ae4d6a3cc31ea80b473d6353162165ecbbc10c944a0264c6  home-check.txt
707dea976cffb9ceeaf3bfe5beb29b904a8a616277d961d81d3363227e966391  iframe-check.txt
```

The complete-suite and browser-check artifacts were added after the first copy; see
`06_QA_EVIDENCE_AND_RELEASE_GATE.md` §2 and §5. The suite file lists 122 files because it includes
`tests/ArchTest.php` in addition to the 121 under `Feature`/`Unit`.

## Phase 1 evidence (19 Oct, after the fixes)

| File | Produced by | Result |
|---|---|---|
| `phase1-all-tests.txt` + `phase1-all-tests-lines.txt` | batched Pest, 125 files | **1,212 passed, 1 skipped, 0 failed**, 8,475 assertions |
| `phase1-fail.txt` | same | empty |
| `phase1-pint.txt` | `pint --test` | **PASS, 817 files** |
| `phase1-phpstan.txt` | `phpstan analyse` | **`[OK] No errors`** |
| `phase1-smoke.txt` | `smoke.sh` | **SMOKE OK (68 pages)** |
| `phase1-builder-check.txt` | `builder-check.mjs` | **BUILDER CHECK OK** |

Notes on what these files do **not** cover:

- No production data or production server was touched. The database is the sandbox sqlite copy.
- `smoke.txt` proves HTTP 200 and non-error renders, **not** that every button works. Interaction
  evidence for the builder comes from `tools/sandbox/builder-check.mjs` and is summarised in `03`.
- PHPStan ran against the synced copy (`/tmp/woodex-run/app`), which is byte-identical to
  `platform/` at sync time; paths in the output are relative to `app/`.
