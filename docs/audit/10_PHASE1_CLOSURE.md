# 10 · Phase 1 closure — truth and safety baseline

**Approved:** 19 Oct 2026 (D1, "approve as written"; D3 owner-only raw HTML; D5 reference supplied).
**Implemented on:** `arena/8be318a2-woodexinterior`, on top of the Phase 0 audit commit `93b897a`.
**Scope honoured:** no schema changes, no production writes, no feature removal. One new permission and
one new seeder entry; every other change is a guard, a test, a workflow or a documentation correction.

---

## 1. What closed

| Finding | Before | Now | Evidence |
|---|---|---|---|
| **S-03** stored XSS in the HTML block | Regex cleaner bypassed by unquoted attributes and entities | **DOM allow-list** in `CanvasHtml`: unknown tags unwrapped, scripts/forms/svg dropped, `on*` removed, URLs decoded then scheme-checked, `srcdoc` removed, CSS imports/expressions stripped. Free HTML is **owner-only** via the new `website.raw_html` permission, enforced in `SavePage` and the editor | `HtmlSanitizerTest` (15 cases, including every previously successful payload); `RawHtmlAccessTest` (6); `evidence/phase1-*` |
| **S-01** authorization unproven | 54 admin routes without route-level permission; 3 spot checks | **26 middleware guards added** to hubs, show pages, PDFs and legacy redirects. `AdminRouteAccessTest` walks every parameterless admin GET as the **support** role and asserts 200 only on the 7 screens its permissions allow, 301 on the 4 permitted redirects, **403 everywhere else**; a second test asserts every screen redirects to login when signed out | `AdminRouteAccessTest` (2 tests, 66 screens each); route diff |
| **Q-02** CI dormant | Two templates in `docs/ci/` | Workflows copied to `.github/workflows/` (CI + manual deploy); documentation updated | `.github/workflows/*.yml` |
| **Q-03** Pint red (33 files) | `pint --test` exit 1 | **PASS — 817 files** | `evidence/phase1-pint.txt` |
| **Q-05** PHPStan red (6 errors) | 6 errors in 2 files | **0 errors** (array shapes added to `LibraryEntry::toRow`, its two implementations, and `SiteHealthIndex::pagesWithoutAlt`) | `evidence/phase1-phpstan.txt` |
| **Q-04** stale status | 1,145 tests / 118 files / claims of clean | `PROJECT_STATUS.md` now reports the verified numbers and points here | `PROJECT_STATUS.md` |
| **Q-08** flaky builder check | 1 aborted beacon counted as failure | The check ignores aborted `__preview-beacon` requests | `tools/sandbox/builder-check.mjs` |
| **N-02** undefined `P4b` | No definition anywhere | Convention recorded in `docs/07-DECISIONS.md` (A-05); nav rename remains Phase 5 | decisions file |

## 2. Verification (19 Oct, after the changes)

| Check | Result |
|---|---|
| Pest, 125 files, 9 batches | **1,212 passed · 1 skipped · 0 failed · 8,475 assertions** |
| Pint (`--test`, 817 files) | **PASS** |
| PHPStan / Larastan | **0 errors** |
| Smoke | **SMOKE OK (68 pages)**, all 200 |
| Builder browser check | **BUILDER CHECK OK** (12 steps; owner session) |
| Route access matrix | 66 screens × support role: 7×200, 4×301, 55×403; signed-out: all 302 → login |

Raw: `evidence/phase1-all-tests.txt`, `phase1-all-tests-lines.txt` (empty failure file), `phase1-pint.txt`,
`phase1-phpstan.txt`, `phase1-smoke.txt`, `phase1-builder-check.txt`.

## 3. How S-03 was fixed

`CanvasHtml::clean()` now:

1. parses the fragment with `DOMDocument` (the browser's own attribute/entity handling),
2. walks the tree against an allow-list of tags (`section`, headings, text, lists, tables, `img`,
   `picture`, `iframe`, `video`, `audio`, `style`, …),
3. removes `script`, `form` controls, `svg`, `math`, `object`, `embed`, `template` **with their contents**,
4. removes every attribute not listed for that tag — so `on*` cannot survive in any quoting style,
5. decodes entities, strips whitespace/control characters/backticks, then accepts only
   `http`, `https`, `mailto`, `tel` or a relative URL,
6. strips `srcdoc`, `@import`, `expression()` and `behaviour` from embeds and CSS,
7. adds `rel="noopener noreferrer"` to every `target="_blank"` link.

The owner-only gate is defence in depth: even a caller that bypasses the editor (a crafted Livewire
request, an import, a future API) hits `AuthorizationException` in `SavePage` unless the user holds
`website.raw_html`.

**Deployment note:** existing installs must re-run `php artisan db:seed --class=RolesAndPermissionsSeeder`
(idempotent) so the permission exists and is attached to the owner role only — admin explicitly does not
receive it. The editor hides the HTML block and canvas button when the permission is absent.

## 4. Still open in Phase 1

| Item | Why it is open | Next step |
|---|---|---|
| **Q-01** `composer.lock` | No `composer` binary in the sandbox; the allowed hosts do not serve a PHAR | First CI run resolves dependencies and prints the lock (gzip+base64) between `WOODEX_LOCK_BEGIN/END`; commit that file, then switch CI to `composer install` |
| **S-01** parameterized routes | The new matrix covers parameterless screens; model routes rely on the 8 policies and the existing 100 `403` assertions, not a single exhaustive table | Phase 6: seed one row per entity and extend the matrix to `{id}` routes |
| **S-02** break-glass | No tested path if the owner loses their 2FA device | Document + test a one-time CLI/`install` recovery code |
| **S-04** off-site backups | Needs an S3/SFTP destination and a restore drill | Owner decision D8 (S3 recommended) |
| **Production verification** | Sandbox cannot reach the live host | Phase 7, read-only checklist |

## 5. Reversibility

- Revert the Phase 1 commit; there are **no migrations**.
- If only the security behaviour must be rolled back, revert `CanvasHtml.php`, `SavePage.php`,
  `PageEditor.php`, the Blade condition and the seeder line; the permission row can stay (unused).
- The route middleware additions are additive; reverting `routes/admin.php` restores the old behaviour.
- The CI workflows are additive and manual-deploy remains a no-op without secrets.

## 6. Phase 2 readiness

The v2.1 reference is verified (`09`): breakpoint styles (desktop / tablet ≤1024 / mobile ≤640),
three-panel editor, 50 `v26` templates. Phase 2 starts from a green baseline — tests, Pint, PHPStan and
CI — rather than from a pile of known-red checks.
