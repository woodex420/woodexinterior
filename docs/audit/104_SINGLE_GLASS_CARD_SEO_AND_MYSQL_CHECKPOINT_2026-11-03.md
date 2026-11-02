# Revision 104 — single transparent welcome card, SEO triage and website diagnostics

3 November 2026, Asia/Karachi. Same fixed working branch. Production data and deployment remain held; auto-deploy OFF.

## 1. MySQL first: checked, not falsely marked fixed

Requested source reviewed: https://github.com/topics/mysql-server . The featured `dolthub/go-mysql-server` describes a **MySQL-compatible Go engine**, not genuine Oracle MySQL; it was not installed as a substitute. `mysql/mysql-server` provides source, not GitHub binary releases. Additional binary/wrapper repositories and npm package metadata were inspected without installing unknown packages or executing their install scripts. Available old 5.6/5.7 packages and macOS-only packages are not acceptable substitutes for a supported Linux test server.

A fresh, cache-busted request for the known MySQL **8.4.6 Ubuntu22 x64** release asset was attempted:

- Repository: `shogo82148/actions-setup-mysql`, release `v1.45.0`, asset `285751243`, `mysql-8.4.6-ubuntu-22.04-x64.tar.zstd`.
- GitHub metadata: 99,389,781 bytes; SHA256 `9bc8528b4c6de2c0e7e2ba960e2bb25a9f7833a0640a5a4d2298dfcd90dd786e`.
- Actual result: redirect to `release-assets.githubusercontent.com` fails `SSL_ERROR_SYSCALL`; **0 bytes downloaded**. The checksum is expected release metadata, not a claimed verification of a downloaded binary.
- No local `mysqld`, `mysql`, Docker or MariaDB engine is installed. No fake engine, obsolete server, new production connection, remote tunnel, schema change or DB guard weakening was used.

**Local genuine MySQL is still BLOCKED.** Unblocking requires access to a trusted supported Linux archive through an allowed source or user upload, followed by integrity/dependency/version/driver checks. Do not upload a production DB or any password. Preview stays on the fresh isolated SQLite database; native MySQL/concurrent-writer/staging gates remain open.

Separately, GitHub API confirms **hosted genuine MySQL CI SUCCESS** for prior source `c793425443299755d3b9c1f0f0c09f83a03e9027`, run [41523310965](https://github.com/woodex420/woodexinterior/actions/runs/41523310965). Downloading its full logs also hit a blocked Actions results host; no unobserved assertion count is claimed. That successful parent run is not local MySQL runtime proof or exact-new-revision104 acceptance.

## 2. Single welcome card, matching the new sample

- Removed the split left-side promotional panel. Exactly **one centred welcome card**, with an interior photograph across the whole page.
- Changed the card from 86%-opaque pale glass to **28%-opaque charcoal glass**, blurred backdrop, thin light border and white text. The demo hint is a flat divider section, not another opaque/nested card.
- Transparent inputs; high-contrast controls/focus states; both appearance modes retain the requested smoke-glass look. Reduced-transparency preference uses an opaque readable fallback; reduced-motion remains supported.
- Preview's Woodex Interior link now opens the preview site instead of sending a reviewer to the unchanged production site. Installed production behaviour keeps the configured public URL.
- Original filled vector W/gold dot retained. Normal authentication, prefill gates, credential masking/toggle, native fallback and sandbox click/Enter behaviour preserved.

Visual evidence: [desktop](evidence/104/login-desktop.png) · [mobile](evidence/104/login-mobile.png). Visible credentials are intentionally synthetic only.

## 3. Implemented product work, not just plans

### SEO triage

- Existing listings/SEO agent and all seven Website tools preserved.
- Canonical & social view now has Needs review / All pages, category counts/filter for canonical/social/JSON-LD/indexing/unreadable, bounded case-insensitive search across page/canonical/finding, showing-count status, useful empty state and an atomic clear action.
- URL-bound audit/category controls allow Assistant citations to open the exact review view/category. Invalid URL controls normalize safely.
- Only the selected audit runs: metadata interactions no longer rerun the unrelated full listing/link audit.
- Website permission is rechecked on render, including revocation after a component was opened.
- Indexing restrictions are **review prompts**, never instructions to remove noindex. No page/schema/canonical edit, network fetch, publish, redirect, new page or removal was performed.

### Assistant

- Added **Check website search metadata** using the same native local-file metadata audit: counts and bounded next-review recommendations, direct permission-checked SEO category citations.
- Total **12 deterministic questions**: original nine record queries plus runtime, connection and website diagnostics. Existing nine are not replaced by generation.
- Website answer returns counts/recommendations, not page contents or customer text. Explicitly not Search Console, live crawl, rankings or eligibility proof.
- Owner/permission checks remain server-side; no executable fix or autonomous write added. External AI/provider calls and Telegram report remain unsent.

### Selective charcoal/Preline

Existing revision103 sidebar filter, clear/no-result states, neutral cards and native stack remain implemented. This slice extends the same native controls and mobile-safe diagnostic rows; no gated Pro code, whole-stack replacement or blue theme introduced. Full Chat/media/theme/speed/integration/accessibility backlog is not declared complete.

## 4. Executed acceptance

| Check | Result |
|---|---|
| Full strict serial native PHP/SQLite suite | **1,969 tests /11,070 assertions PASS**, 224.87s |
| Focused final Assistant/SEO suite | **28 tests /151 assertions PASS** |
| PHPStan changed Assistant/SEO paths | **0 errors** |
| Pint six changed non-Blade PHP files | **PASS** |
| Frontend build | **PASS**, existing large-chunk advisory only |
| Release/source/enquiry contracts | **92 PASS** |
| Theme/admin/auth helper/source contracts | **55 PASS** |
| Real local Chromium harness | **28 PASS**, final command exit0 |

Private ignored evidence: `.cache/preview/104-full-native.log`, `104-focused-final.log`, `104-phpstan.log`, `104-build.log`, `104-release-contracts.log`, `104-ui-contracts.log`, `104-browser-final.log`, `104-brand-auth-browser.json`. Reproducible browser source remains `tools/sandbox/branding-login-check.mjs`.

Browser checks extend103 with strict single-card/alpha/transparent-hint assertions at360/390/768/1440px in light/dark, actual reduced-transparency fallback, real SEO filtering/clear/all-pages and direct-link rendering, and all three Assistant diagnostics on mobile. Actual Fortify/native/constrained-frame login and manager/accountant restrictions still pass. Mocked HTTP/network failures remain explicitly synthetic. One initial browser invocation returned a nonzero process exit despite all28 case records passing; acceptance uses the subsequent final reruns with explicit exit0, not that ambiguous run.

## 5. Honest master status

`ox-pending.md` and the 36-gate register are updated with implemented/tested work, the successful parent hosted CI and the still-blocked local MySQL requirement. The file itself is maintained; **its entire backlog is not complete**.

Actual Arena parent/proxy retention, browser 2FA/intended/timeout/duplicate scenarios, compact Website Chat/lifecycle, remaining content/whole-action audits, installed identity, private consistent backup/verified restore, isolated staging/concurrency and explicit final release approval remain open. Existing project-cost/accounting/frozen-document regressions pass in the native suite; historical financial and PDF data/templates were not rewritten.

**No production writes/import/account reset/migration/provider send, merge or deployment. Deployment remains FINAL; auto-deploy OFF.**
