# Revision49 — Seven Website pages: baseline QA and v2.1 gap report

**29 October 2026 · Baseline `2bf4734` · Read-only audit, not a repair release.**

[Four-phase implementation/master plan](49_WEBSITE_OS_FOUR_PHASE_MASTER_PLAN_2026-10-29.md)

## 1. Evidence and limits

- Inspected all seven native route components, templates and relevant services/navigation; compared committed v2.1 Media/Speed/Theme/SEO-agent source. [Source hashes](evidence/49/sources.txt).
- Google Drive metadata freshly confirmed `Woodex-Live-v2.1-Pro-Production.zip`, 28,625,635 bytes, modified 19 October 2026. The comparison below uses actual **committed ported v2.1 modules**, with provenance in `platform/public/assets/admin-v21/PORT-NOTES.md`; the full ZIP/backend was **not freshly extracted or certified** in this pass.
- Native Chromium at **1440×1000 and 390×844**, against a guarded pre-existing isolated QA copy; no uploads/saves/deletes/reseeding/provider calls. Read-only filters/UI state and login sessions may change; no owner business records changed. Theme GET contains existing first-or-create token logic; browser work ran only against the separate copy. Relevant source hashes were checked against that copy.
- Seven pages each answered **200 twice**. Livewire sidebar navigation Media → SEO also reached the correct heading. **0 page JavaScript errors**. One real layout failure: Speed overflows at 390px. Screenshot inspection also shows its low-contrast cache pill in Night mode. These are page/runtime checks, **not seven pages of fully action-certified functionality**.
- Targeted feature baseline: **120 passed /845 assertions /17.69 seconds /exit0** in 13 suites. Covers native Website admin/parity, Media import/builder, SEO agent, Speed, Theme bridge/presets/tokens, tags, Assistant, revision48 theme/chat and widget endpoints. Tests use the isolated test database/fakes, not the browser QA/main database. [Log](evidence/49/baseline-tests.txt).
- Main-preview configuration/file audit was read-only and recorded only safe flags/counts: [probe](evidence/49/read-only-probe.json). The supplied `arena.site` deployment was **not independently authenticated/audited**; local configuration is not production diagnosis.
- [Preline setup documentation](https://preline.co/docs/) was reviewed: Tailwind source/variants/forms and manual/selective initialization guidance are relevant. The repo already uses Preline; no package upgrade or paid template import is implied. The owner's attached charcoal screenshot is a design reference, not proof of source/license entitlement.

## 2. Page-by-page findings

| Page | Native state / runtime | Gap and recommended repair |
|---|---|---|
| **Media** `/admin/website/media` | 200; upload, search, missing-alt filter, metadata/alt propagation, in-use delete guard, paginated cards. No desktop/mobile page overflow. | v2.1 has folder navigation, grid/list, selection, size/type/usage views, crop/resize, compression/replace, variants and recoverable trash workflows. Native page does not expose these. Port safely in slices, not by reconnecting legacy API names. |
| **SEO** `/admin/website/seo-overview` | 200; real file-backed listing/search/filter and builder links; no page overflow. | Standalone SEO-agent button instead of requested integrated tabs. Every search/filter render calls `FileAudit`; share fresh scan state rather than rereading all files per interaction. Preserve old URLs and role boundaries. |
| **SEO agent** `/admin/website/seo` | 200; actual rule/site findings and builder links; no page overflow. | Separate sidebar entry; mostly worklist, not an AI or automatic repair engine. Add second SEO tab, scan timestamp/scope and bounded fixes with explicit preview/backups if approved. Do not restore old DB-only fake fixes. |
| **Speed** `/admin/website/speed` | 200; internal page/block/media weights; Google path exists behind a key; desktop fits. **Mobile scroll width 434px at 390px viewport.** | Fix responsive layout. Audit inventories differ from file-backed site; current metrics are not a static-file network audit. External request blocks synchronously up to 90s; richer v2.1 CWV/suggestions/history workflow is incomplete. Add honest coverage, background progress/error states and real metrics. |
| **Site health** `/admin/website/health` | 200; seven structural/file checks; no page overflow. Main file scan currently reports zero issues. | No reproduced main broken-link/meta defect in this rule set. Expand coverage and explain limits rather than manufacture failures. Render scans all files; explicit rescan also scans for the toast. Robots/sitemap indicators use route existence, not full HTTP/content validation. |
| **Theme** `/admin/website/theme` | 200; token/preset editing, persistence/cache invalidation and v1 CSS bridge exist; no page overflow. **Zero embedded preview frames.** | User's "static" complaint is confirmed as a preview/workflow gap, not proof Save is a no-op. v2.1 has an actual live iframe, page/device switcher and draft-only preset/reset until publish. Native preset action currently writes immediately. Add draft preview + explicit publish, retain working CSS bridge. |
| **Website Settings** `/admin/website/settings` | 200; saves IDs/tracking/chat switch; no page overflow. **Zero embedded preview frames.** | Limited controls/no live preview; boolean nonempty IDs drive "Connected"/"Search Console verified" text without remote proof. Tracking-disabled or GTM-precedence states are not represented by those badges. Add saved/unsaved feedback and configured/enabled/emitted/verified distinctions. |

### Prioritized issue register

| ID | Priority | Evidence / classification | Acceptance for repair |
|---|---|---|---|
| W01 | P1 UX | `AdminNavigation::groups()` explicitly uses `group(null, ...)` for the seven links. Previous preference, now superseded by owner's new Website request. | Visible Website group, all seven old URLs reachable, SEO two-tab workspace, correct active/permission states. |
| W02 | P1 capability | Native Media action surface vs real v2.1 `admin-media.js` workflows. Source-confirmed parity gap. | Safe Media feature matrix, actual file/DB/usage behavior and per-action browser tests; no destructive fallback for trash. |
| W03 | P1 UX | No Theme iframe; v2.1 live iframe/input handler exists. Native `applyPreset` persists immediately. | Unsaved color/font/radius/preset/reset visible in site preview, no live writes until Save; cancel/reload/error/rollback proof. |
| W04 | P1 UX | Main chat flag false; measured staff drawer 680×1000 with thread navigation at 1440×1000 viewport. | Verify public widget switch and end-to-end native chat; compact current-thread popup, no auto-open full-height/sidebar UI. |
| W05 | P1 correctness | Settings `live` flags are `filled(...)`, while UI says Connected/verified. Source-confirmed status semantics gap. | Configured vs effective emission vs external verification, including disabled tracking, GTM overriding GA4, failed/missing verification. |
| W06 | P2 layout | Browser reproduced Speed document width 434 at viewport390. | No page-level horizontal scrolling at 320/390/768/1440; long URLs/metrics/actions remain usable. |
| W07 | P2 performance | SEO/Agent/health call file scanner on render; health rescan calls it in verdict plus render. | Shared freshness-aware snapshot; one explicit scan, no repeat scans on search/filter; invalidation/progress/failure tests. |
| W08 | P2 coverage | Internal Speed uses `Page`/`blocks`/`Media` and page cache; SEO uses `BuilderFile`. Same page count does not prove same asset coverage. | Trace representative static image/srcset/CSS/JS assets and DB pages; unique vs per-page bytes explicit, no fake cache/traffic claims. |
| W09 | P2 latency | `PageSpeed::run`: HTTP timeout90/connect10, called directly by Livewire run action. | Queue/progress/idempotency/rate/error UX; do not hang save/navigation or claim configured means measured. |
| W10 | P2 coverage | Health uses route existence for robots/sitemap; current main local HTTP smoke returns200 for both. | Parse actual responses and URLs, test failures/redirects/assets; preserve truthful current clean result within measured rule set. |
| W11 | P2 design | Browser body is `rgb(13,22,38)` (#0d1626); owner requests neutral charcoal. | Complete semantic charcoal surface update, contrast/status/focus/chart checks, retained Light/System and white PDFs. |
| W12 | P2 scope | Assistant source has nine deterministic read-only query types, no general model execution; UI thread is component-memory only. | Bounded useful Website questions, cited scope/timestamps, role/record tests and honest capability labels; separately gated optional provider/actions. |
| W13 | P2 hardening | Media upload/delete do filesystem work then DB work without checking returned storage result in these methods; metadata update lacks explicit length validation. Source risks, **not fault-injected runtime failures in this pass**. | Invalid/oversize values and disk/DB failure tests; no success toast for failed write, orphan rows/files or unintended live changes. |
| W14 | P2 contrast | Speed screenshot: pale `bg-soft` cache pill inherits near-white Night text; no local dark background override in that markup. Visually reproduced, no numeric contrast ratio asserted here. | Semantic surface/text pair for cache/status pills; computed contrast and actual screenshot checks in Light/charcoal/System. |
| W15 | P2 performance | Website media URLs go through Laravel; the preview has one PHP worker. QA request samples show many image responses queued alongside page requests. This is a measured local delivery pattern, not an isolated production root-cause benchmark. | Bounded thumbnail/variant loading, public-media cache/conditional-request checks and navigation timings with images in flight; preserve durable storage and path safety. |

P1 denotes owner-visible workflow/correctness priority, not an invented security incident. No runtime repair has been applied yet.

## 3. Main-preview read-only observations

- Public chat flag **false**, conversations **0**. This explains no local visitor bubble; an empty staff drawer alone does not prove the intake transport is broken. Revision48's prior isolated two-context proof remains historical evidence. New production behavior still needs separate reproduction.
- Published DB pages **142**; file scanner also reads **142** page files. Matching counts are not proof the inventories/payloads are identical.
- Existing internal speed aggregate: **535 image references /53,791,470 bytes /1 heavy reference /0 legacy formats**. These are that service's per-page media totals, **not** unique site storage, actual visitor transfer, LCP or production PageSpeed scores.
- `FileAudit`: **0 flagged /0 issues /0 missing-alt /0 broken-links /0 orphans /0 astray redirects** under its current rules. Do not rewrite content merely to create an improvement counter.
- Cache configuration flag true. This is not a cache-hit measurement. PageSpeed key not configured locally; no Google request was attempted.
- `/robots.txt` and `/sitemap.xml`: HTTP200 in local smoke; no full sitemap URL/canonical/indexability certification in this pass.

## 4. Native browser timings

Two direct navigations per page; below is the **second** sample from the final run. Measured in Chromium on the local WASM QA preview, with the normal shell/polling enabled; no concurrent Pest run. These are **not production benchmarks, Core Web Vitals or a p95 estimate**. They locate work for profiling, not a speed score.

| Page | Second TTFB ms | Second DOMContentLoaded ms | Desktop / mobile page fit |
|---|---:|---:|---|
| Media | 1220 | 1344 | pass / pass |
| SEO | 1823 | 1875 | pass / pass |
| SEO agent | 762 | 823 | pass / pass |
| Speed | 805 | 910 | pass / **fail (434px)** |
| Site health | 759 | 820 | pass / pass |
| Theme | 490 | 562 | pass / pass |
| Website Settings | 449 | 516 | pass / pass |

The preview server uses `maxPhpInstances: 1`; requests queue in one PHP-WASM runtime. Media images also pass through Laravel (`WebsiteMediaController`), so image fanout shares that worker with navigation. [Sanitized response sample](evidence/49/media-request-sample.txt). Repeated scans, global polling and bundle/init cost are profiling targets; this audit did not isolate their individual causal contribution. Deleting demo records or flushing all caches is not an evidence-based latency repair.

## 5. Screenshots and reproducibility

- [Media desktop](evidence/49/49-media-desktop.png) · [mobile](evidence/49/49-media-mobile.png)
- [SEO](evidence/49/49-seo-overview-desktop.png) · [SEO agent](evidence/49/49-seo-desktop.png)
- [Speed desktop](evidence/49/49-speed-desktop.png) · [mobile overflow](evidence/49/49-speed-mobile.png)
- [Site health](evidence/49/49-health-desktop.png)
- [Theme desktop](evidence/49/49-theme-desktop.png) · [mobile](evidence/49/49-theme-mobile.png)
- [Settings desktop](evidence/49/49-settings-desktop.png) · [mobile](evidence/49/49-settings-mobile.png)
- [Existing staff drawer](evidence/49/49-chat-existing-drawer.png)
- [Raw browser results](evidence/49/browser-results.json) · [Browser log](evidence/49/browser.txt) · [Feature baseline](evidence/49/baseline-tests.txt)

`tools/sandbox/website-phase-audit.mjs` runs only against the guarded pre-existing QA copy on33144; it calls the non-mutating fixture inspection, never init/documents/issue. Node syntax check passes. Do not run mutation/restore scripts against the main owner database. QA server is stopped after evidence capture; main33143 remains available.

## 6. Delivery boundaries and next decision

Only audit tooling, evidence, documentation and status changed. No app source/assets/config/schema/deletion/deployment/merge. No production/provider/real-mobile certification. Phase 2 CRM/Sales/Accounts/Projects/Inbox/Automation/Marketing lifecycle QA has **not** been completed by this seven-page audit; it is explicitly sequenced in the master plan. SEO content awaits owner data in Phase 3. Cleanup/reorganization/default-branch delivery is Phase 4, with explicit destructive/merge approval.

**Recommendation:** approve Phase 1's safe order, starting with Website grouping, SEO tabs, compact chat, charcoal and the responsive Speed repair; then native Media parity, real previews, audits/performance and bounded Assistant additions. Report after each slice.
