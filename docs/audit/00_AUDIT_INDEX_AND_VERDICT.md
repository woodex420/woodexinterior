# Woodex OS — platform audit, Phase 0

**Date:** 2026-10-19 · **Commit audited:** `b817322de95a4ccbd146238c42d931333523b884`
**Branch:** `arena/8be318a2-woodexinterior` · **Method:** read-only inspection, automated tests, HTTP
smoke, headless-browser checks. **No code was changed, no migration was run, no production system was
touched.**

---

## 1. Verdict in one paragraph

Woodex OS is a **real, working platform**, not a prototype: 1,738 tracked files, 95 migrated tables,
158 routes, 89 Livewire screens, 122 test files with **1,189 passing tests**, green HTTP smoke on 68
pages, green browser checks on the builder and on CMS pages, and a coherent admin design system. It is
**not release-ready**, for reasons that are mostly evidence and operations rather than broken
features: repo-wide lint and static analysis fail, CI is dormant, there is no dependency lockfile, one
stored-XSS bypass exists in the HTML block, authorization is enforced inconsistently at route level,
80 public URLs have two sources, backups have no off-site copy, and production cannot be verified from
the sandbox. The page builder already covers the section-level workflow and is the right foundation;
it needs breakpoint control, layout nesting, conflict safety and editing speed — not a rewrite.

---

## 2. Documents

| # | Document | Answers |
|---|---|---|
| **00** | This file | Verdict, headline numbers, decisions, how to navigate |
| **01** | [Source baseline and diff](01_SOURCE_BASELINE_AND_DIFF.md) | What exactly was audited; SHAs; diff vs `master` and vs the earlier reference; runtime; commands and access gaps |
| **02** | [Route and module inventory](02_ROUTE_AND_MODULE_INVENTORY.md) | Every route; all 53 sidebar items → component → permission → test; public URL ownership; API surface |
| **03** | [Existing features matrix](03_EXISTING_FEATURES_MATRIX.md) | KEEP/MIGRATE/IMPROVE/MERGE/REPLACE/BLOCKED per module; builder capability matrix; v2.1 comparison; 33-file Pint list |
| **04** | [Architecture and dependencies](04_ARCHITECTURE_AND_DEPENDENCIES.md) | Stack, layout, request lifecycle, domains, integrations, deployment, risks, proposed structure |
| **05** | [UI/UX gap analysis](05_UI_UX_GAP_ANALYSIS.md) | Navigation, screen patterns, builder UX, accessibility, 7 verification items |
| **06** | [QA evidence and release gate](06_QA_EVIDENCE_AND_RELEASE_GATE.md) | Test totals, smoke, lint/static analysis, browser checks, coverage gaps, two release gates |
| **07** | [Security, data and permissions](07_SECURITY_DATA_AND_PERMISSIONS.md) | Auth, authorization, XSS/SQLi/CSRF, uploads, webhooks, secrets, backups, privacy |
| **08** | [Findings register and roadmap](08_FINDINGS_REGISTER_AND_ROADMAP.md) | One register, 7 phases, what not to build, **8 owner decisions**, approval request |
| — | [`evidence/`](evidence/README.md) | Raw command output with SHA-256 and provenance |

---

## 3. Headline numbers (verified)

| Metric | Value |
|---|---|
| Tracked files at `b817322` | 1,738 |
| Application code | ~107k LOC across `platform/` |
| Migrations → tables | 64 → **95** (all applied in sandbox) |
| Routes | **158** (150 named; 123 GET, 34 POST, 1 PUT, 1 DELETE; 107 admin) |
| Livewire components | 89 |
| Blade views | 158 |
| Tests | **122 files · 1,189 passed · 1 skipped · 0 failed · 8,424 assertions** |
| Smoke | **68 pages, all 200**, 5 legacy redirects correct |
| CMS pages / static pages | 143 / 80 (80 overlap, **63 CMS-only**) |
| Sidebar | 53 items (48 live, 5 planned) in 10 groups |
| Roles / permissions / users | 8 / 44 / 5 |
| Pint | **fails, 33 files** |
| PHPStan | **fails, 6 errors in 2 files** |
| `composer.lock` | **absent** |
| CI workflows active | **none** (2 templates exist) |

---

## 4. The ten findings that matter most

1. **S-03 — HTML block allows stored XSS.** `CanvasHtml::clean()` is regex-based; unquoted attributes
   and HTML entities bypass it. Reproduced with five payloads. Restrict to owner now, replace with a
   DOM allow-list in Phase 1.
2. **W-01 — two sources for 80 public URLs.** Static files win; publishing a CMS page to `/about/`
   changes nothing visitors see. Needs an owner decision and a group-by-group cutover.
3. **S-01 — authorization not proven.** 53 of 107 admin routes carry a route-level permission; the
   rest authorize in `mount()`. Spot checks pass, but there is no route × role matrix.
4. **Q-01/Q-02 — the safety net is offline.** No lockfile; CI is a template. The green suite is local
   only.
5. **Q-03/Q-05/Q-04 — quality claims are stale.** Pint fails 33 files, PHPStan 6 errors, and
   `PROJECT_STATUS.md` reports both as clean with older test counts.
6. **S-04 — backups have no off-site destination** and no restore drill has been run.
7. **R-01 — the builder cannot change 80 live URLs**, which is the same fact as W-01 seen from the
   editor's chair.
8. **N-01/N-02/N-03 — navigation lies in small ways:** duplicate "Alert Rules", an undefined `P4b`,
   and a client portal shown as future work although it is built and tested.
9. **B-04/B-05 — two things are genuinely blocked:** production cannot be reached from the sandbox,
   and the v2.1 pro builder source does not exist in any supplied repository.
10. **Q-06 — demo data hides module risk.** Invoices, payments, projects, conversations, automations,
    alert rules, redirects and navigation items are empty, so their screens are only proven to render.

---

## 5. What is genuinely good (do not break it)

- **Domain architecture:** 10 bounded domains with actions, services, models and policies; consistent
  and testable.
- **Website CMS:** 143 pages, 1,450 blocks, versions, templates, redirects, media, SEO, theme tokens,
  publish gate — with tests, including a publish-permission denial test.
- **Builder foundation:** drag reorder, undo/redo, layers, click-to-edit, live preview, section
  styles, HTML block, 50 templates, saved sections, history/restore.
- **Admin design system:** 20 shared components, consistent empty states, one toast mechanism,
  permission-filtered nav, no dead links.
- **Operational thinking:** signed client links, hashed one-time codes, webhook signatures, rate
  limits, private media disk, privacy export/erasure, scheduled retention, audit log.
- **Test discipline:** 122 files, 8,424 assertions, arch tests, security regression tests.

---

## 6. Blocked, not failed

| Item | Why blocked | What unblocks it |
|---|---|---|
| Production parity (B-04) | `https://woodex.com.pk` unreachable from sandbox (SSL_ERROR_SYSCALL); no host credentials | Read-only host access, or owner-run checklist |
| v2.1 builder source (B-05) | Searched all 9 `woodex420` repositories and branches; no `templates-v26.js` or pro builder | Owner supplies branch/ZIP |
| Restore drill (S-04) | Needs a disposable database and backup target | Off-site bucket + 1-hour window |
| Full a11y/load testing | Requires authorisation for load; axe not installed | Phase 6 approval |

---

## 7. Decisions needed (detail in `08` §5)

| # | Decision | Recommendation |
|---|---|---|
| D1 | Accept audit and phase order | Approve, start Phase 1 |
| D2 | Static→CMS cutover order | After builder, group by group |
| D3 | HTML block while S-03 open | Owner-only |
| D4 | Free canvas / plugin registry | Decline, document |
| D5 | v2.1 source | Supply before Phase 2 |
| D6 | Production verification access | Read-only staging + owner checklist |
| D7 | Duplicate "Alert Rules" labels | "Lead alert rules" / "System alerts" |
| D8 | Off-site backups | S3-compatible + monthly drill |

---

## 8. Recommended next step

Approve **Phase 1 — truth and safety baseline** (3–5 days, no schema changes): fix S-03, prove
authorization with a route × role matrix, activate CI, commit the lockfile, make Pint/PHPStan green,
and correct the status documents. Phase 2 then delivers the page builder work the owner asked for
first, with a trustworthy baseline underneath it.

No implementation begins until the owner answers D1–D8 (defaults are acceptable). The audit itself is
complete; its remaining gaps are named above rather than papered over.
