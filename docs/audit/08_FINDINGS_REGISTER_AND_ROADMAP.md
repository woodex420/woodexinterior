# 08 · Findings register and phased roadmap

This is the decision document. It consolidates every finding from `01`–`07`, proposes an order of work,
and ends with the questions the owner must answer before any code changes. **Nothing here has been
implemented.**

---

## 1. How to read the register

- **Severity** — P1 blocks release or trust in the system; P2 degrades quality or creates future risk;
  P3 is polish. Severity is about impact, not effort.
- **Evidence** — `Verified` = reproduced by a command/test in this audit; `Observed` = read in code or
  output but not exercised; `Inferred` = follows from architecture; `Blocked` = needs access that does
  not exist; `Not tested` = no evidence either way.
- Every finding keeps the ID used in its source document.

---

## 2. Register

### 2.1 Release-blocking (P1)

| ID | Finding | Evidence | Phase |
|---|---|---|---|
| **S-03** | HTML-block sanitiser is bypassable (unquoted attributes, entities) → stored XSS via `website.edit` | **Verified** — payloads executed against `CanvasHtml::clean()` | 1 |
| **S-01** | 54 admin routes lack route-level permission; `mount()` coverage not proven for all | Observed — route dump + 3 spot checks | 1 |
| **Q-01** | No `composer.lock`; dependency drift can reach production unnoticed | Verified — `git ls-files` | 1 |
| **Q-02** | CI exists only as templates; nothing runs on push | Verified — no `.github/` | 1 |
| **W-01** | 80 public URLs have two sources; CMS publishing does not change them | **Verified** — byte-identical `/about/`, static-wins rule | 2 (decision now) |
| **B-04** | Production deploy and database cannot be verified from the sandbox | **Blocked** — live site unreachable, no credentials | 7 |
| ~~**B-05**~~ | **Resolved** — v2.1 pro source verified in `marketingwoodex-cloud/-marketingwoodex` at `720d817` | GitHub tree API | — |
| **S-04** | Backups have no off-site destination; restore never rehearsed | Verified — config + docs | 1 |

### 2.2 Quality and risk (P2)

| ID | Finding | Evidence | Phase |
|---|---|---|---|
| Q-03 | Pint fails on 33 files | Verified | 1 |
| Q-05 | PHPStan: 6 errors in 2 files; status claims 0 | Verified | 1 |
| Q-04 | `PROJECT_STATUS.md` counts are stale (1,145/118 vs actual suite) | Verified | 1 |
| Q-06 | Empty demo data for invoices, payments, projects, inbox, automations | Verified — SQL counts | 4 |
| Q-07 | Reports/Tasks have no component behaviour tests | Verified | 4 |
| Q-08 | Builder browser check is flaky on the diagnostics beacon | Verified — 1 of 3 runs | 1 |
| N-01 | Duplicate "Alert Rules" label | Verified | 5 |
| N-02 | `P4b` phase tag undefined in docs | Verified | 1 (doc) |
| N-03 | Client portal shown as future work though built | Verified | 5 |
| N-07 | Bot domain (5 providers) has no screen | Verified | 4 |
| N-08 | No command palette/search over 53 nav items | Observed | 5 |
| S-02 | No tested break-glass if owner loses 2FA | Observed | 1 |
| S-06 | No dependency audit in CI | Inferred from Q-01/Q-02 | 1 |
| W-02 | Static→CMS conversion 0 of 8 groups | Verified | 2 |
| W-03 | Publishing to a shadowed URL silently has no effect | Verified | 2 |
| W-08 | Two frontends in the repo (`woodex/`, `public/`) | Verified | 1 (docs) |
| R-01…R-08 | Architecture risks (sources of truth, no lockfile, dormant CI, deploy size, demo gaps, doc drift, split domains, shared hosting) | See `04` §9 | 1–7 |
| U-01/U-02 | Responsive and keyboard behaviour unproven | Not tested | 6 |
| U-06 | 232 toast sites not audited for "success before commit" | Observed (sample) | 4 |
| S-07 | Activity-log retention undefined | Observed | 6 |

### 2.3 Polish (P3)

| ID | Finding | Phase |
|---|---|---|
| N-09 | Mobile sidebar with 53 items needs a real-device check | 5 |
| S-05 | HTTPS redirect commented out in `public/.htaccess` | 1 |
| U-03…U-05, U-07 | Error screens, long content, dark-mode contrast, planned-badge mapping | 5 |
| W-06 | Static DOM scan is sample-level only | 6 |
| — | `docs/` drift beyond the status file | 1 |

**Not findings:** the 1 skipped test (intentional), the missing `artisan about` result (php-wasm), and
the commented HTTPS block if the host enforces HTTPS (to be checked on the host).

---

## 3. Recommended phase plan

Principle: **small, reversible phases**, each with a demo and a rollback. The owner's priority — the
page builder — is Phase 2; Phase 1 is only the work that makes Phase 2 safe and provable.

### Phase 1 — Truth and safety baseline *(recommended first, ~3–5 working days)*

| Work | Detail |
|---|---|
| Fix S-03 | DOM-based allow-list; add the five payloads to tests; interim: HTML block owner-only |
| Route × role matrix | One Pest test per admin route per role, asserting 200/403; close S-01 or list exceptions |
| CI on | Activate `platform-ci.yml` (pint, phpstan, pest, build); run `composer audit` |
| Lockfile | Commit `composer.lock`, verify root manifest parity test |
| Style/static | `pint` once, fix 6 PHPStan errors, gate both |
| Status truth | Regenerate `PROJECT_STATUS.md` numbers from CI |
| Docs | Define `P4b` or remove it; document the break-glass path (S-02) |

**Exit criteria:** red→green on Q-01…Q-05; S-01 matrix green; S-03 tests fail on the old sanitiser and
pass on the new one. **Rollback:** revert the phase branch; no schema changes.

### Phase 2 — Page Builder completion *(owner priority, ~2–3 weeks)*

| Work | Detail |
|---|---|
| Breakpoints | Per-breakpoint styles for padding, spacing, columns, typography |
| Layout primitives | `columns` section type (2–4 children); no free canvas |
| Editing speed | Copy/paste, multi-select, duplicate across pages, per-element inspector |
| Safety | Conflict detection on save (version/ETag), explicit "Save & preview" semantics, draft autosave recovery |
| Content | Reusable saved sections across pages, theme presets previewed live, template picker polish |
| Publishing | Draft/publish history, scheduled publish, one-click rollback (exists; harden + test) |
| Visibility | "Served by static file" badge when a page's URL is shadowed (W-01 interim) |
| Parity | Run `tools/website/compare.php` against the current v1 pages and publish the report |

**Exit criteria:** builder-check plus new tests for each capability; every builder action reversible;
no change to public HTML without a publish. **Rollback:** per-capability flags in `config/website.php`.

**Reference input:** B-05 is resolved. The v2.1 builder source is available at
`marketingwoodex-cloud/-marketingwoodex`, branch `arena/8a776c65-marketingwoodex`, SHA `720d817`
(`frontend-v1/builder/*`, `api/builder.php`). Phase 2 compares behaviour against that source; it is a
reference, not code to merge (different stack, no licence file — see `09`).

### Phase 3 — Public cutover, group by group *(~1 week, owner-ordered)*

Execute `docs/13` in the order the owner chooses, not all at once. For each group: compare tool parity
report → delete static files for that group → 301 map for retired URLs → smoke + visual check →
rollback by restoring the files. Replace the blanket "static file wins" behaviour with per-group
ownership (W-01/W-03). The home page's `RewriteRule ^$ index.html` is the last switch.

**Exit criteria:** each converted URL is served by Laravel, carries the same visible content, and the
CMS publish button now changes the live page. **Rollback:** restore the group's folder from git.

### Phase 4 — CRM, sales and portal completeness *(~2 weeks)*

Close the gaps in `03`: seed realistic E2E fixtures (Q-06), finish handover screens, expose the bot
screen or remove the dead domain (N-07), add Reports/Tasks behaviour tests (Q-07), audit mutation
toasts for commit-order (U-06), and wire any remaining "planned" screens the owner wants.

### Phase 5 — Interface polish *(~1 week)*

Resolve nav labels (N-01/N-03), add command palette (N-08), error-state screens (U-03), long-content
and dark-mode contrast (U-04/U-05), planned-badge mapping (U-07), mobile sidebar pass (N-09).

### Phase 6 — QA and release gate *(~1 week)*

Full route × role matrix, axe-core on 12 screens, 390/768/1440 passes, keyboard pass, browser
regression on the 80 public pages, backup **restore drill**, load test only with written authorisation.
Close every R-gate in `06` §7.2.

### Phase 7 — Reversible production deploy *(~2–3 days + observation)*

Pre-flight: host PHP/extensions, cron, queue, mail, storage permissions, HTTPS redirect, `.env` names.
Deploy to a staging subdomain first; run the release checklist; verify migrations, scheduler, queue,
backups, and the live site's actual SHA. Rollback: previous release + DB backup + static folder intact.
Monitoring: heartbeat, failed jobs, backup monitor, error log review for 7 days.

---

## 4. What I recommend we do **not** build

| Item | Why |
|---|---|
| Free-canvas builder / plugin registry | Duplicates GrapesJS for a section-based studio; high maintenance, no business gain. The section model already covers the workflow |
| Multi-tenancy | Single studio; the brief's tenant isolation does not apply |
| SPA rewrite or React admin | The Livewire admin is coherent and tested; a rewrite would burn the audit's main asset |
| Payment gateway integration | No requirement or provider decision; invoices/payments are records, not charges |
| AI-authored pages without review | The AI helpers exist; publishing stays human-gated |

If the owner disagrees, these become explicit scope additions with cost.

---

## 5. Decisions required before Phase 1

| # | Question | Options | My recommendation |
|---|---|---|---|
| **D1** | Is the audit accepted, and is the phase order approved? | Approve as written / reorder / reject | Approve; start Phase 1 |
| **D2** | Static→CMS cutover order (W-01) | `docs/13` order / owner's own order / keep static forever | Convert group by group after the builder is solid (Phase 3) |
| **D3** | The HTML block before S-03 is fixed | Restrict to owner / disable / accept risk | Restrict to owner in Phase 1, fix properly |
| **D4** | Free canvas and plugin registry | Build / decline | Decline, record in `docs/decisions` |
| ~~**D5**~~ | v2.1 source | **Answered** — owner supplied the repository; verified | Use as reference |
| **D6** | Production access for verification (B-04) | Read-only shell/DB / owner runs checklist / staging deploy | Read-only staging + owner-run checklist |
| **D7** | Nav label for duplicate "Alert Rules" (N-01) | Rename Automation / rename System / leave | "Lead alert rules" (Automation) and "System alerts" (System) |
| **D8** | Off-site backups (S-04) | S3 / SFTP / host snapshot | S3-compatible bucket + monthly restore drill |

Default if no answer: **nothing is implemented**; the audit stays as the baseline.

---

## 6. What approval unlocks

Immediately after approval I will, in this order:

1. Open the Phase 1 branch and write the route × role matrix **as a failing test first** (so the gap is
   visible), then fix it.
2. Replace `CanvasHtml` with a DOM allow-list and add the five bypass payloads as regression tests.
3. Activate CI, commit the lockfile, run Pint once, fix the six PHPStan errors, and update
   `PROJECT_STATUS.md` from the CI output.
4. Post the Phase 1 evidence (screenshots of red/green CI, test counts, payload tests) before touching
   the builder.

No migrations, no production writes, no feature removal without a separate confirmation.

---

## 7. Traceability

| Master-prompt requirement | Where answered | Status |
|---|---|---|
| Audit every file across FE, BE, APIs, DB, integrations, tests, deploy | `01`–`07` | Done (1,738 files scanned; production access blocked) |
| Feature preservation matrix | `03` M-01…M-27, W-01…W-08 | Done |
| Map every page/redirect to route/permission/API/entity/test | `02` | Done for 53 nav items + 158 routes; redirect rows are data, 0 seeded |
| Page Builder completeness | `03` §4, `05` §3, `08` §3 Phase 2 | Planned |
| Organised main branch, clean deps, env, tests, deploy docs | `04` §10 | Proposed |
| QA evidence, no placeholders/fake success | `06` | Partial — production and screen-level pending |
| Phased, reversible delivery | `08` §3 | Proposed |
