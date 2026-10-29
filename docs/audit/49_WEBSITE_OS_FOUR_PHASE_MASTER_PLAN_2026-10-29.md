# Revision49 — Website first, four-phase Woodex OS master plan

**29 October 2026 · Decision pending · Planning/audit only.**

Owner's new direction supersedes revision48-A's navy Night palette and large staff-chat drawer design. Revision48-A remains the implemented baseline, not proof that these new requirements are complete. This plan prioritizes Website before the remaining omnichannel foundation in revision48. No application implementation, cleanup, deployment or merge in this audit.

[Seven-page QA report and evidence](49_WEBSITE_PAGE_QA_2026-10-29.md) · [Current project status](../../PROJECT_STATUS.md) · [Prior implemented baseline](48A_NATIVE_THEME_WEB_CHAT_UPGRADE_2026-10-29.md)

## 1. Decisions and safety boundaries

- Keep native Laravel/Livewire, public v1, existing SQL CRM, page-file writer, routes, financial rules and frozen document history. Reuse existing v2.1 workflows; do not revive its parallel admin/auth/CRM database.
- Put the seven requested destinations under a visible **Website** group. Consolidate **SEO / SEO agent** into one SEO workspace with two tabs; preserve both old URLs. This means six sidebar entries exposing seven existing screens. Existing Pages & builder / Content destinations remain accessible; no site-editing links disappear.
- **Admin dark grey** and **public website Theme** are different systems. Change admin navy surfaces to neutral charcoal; preview/publish public brand tokens separately. Do not recolor PDFs or force visitors into the admin theme.
- The screenshot shows a staff drawer, not the visitor widget. Replace its default presentation with a compact single-thread popup; retain full history/assignment through Inbox.
- No fake PageSpeed, health, AI, tracking verification or provider-connected status. Label configured, enabled, emitted and externally verified separately.
- Missing provider keys do not block navigation, Media browsing, internal file audits, preview, chat or useful deterministic Assistant queries. Real Google/provider testing remains a deployment/account gate.
- No destructive demo deletion, historical import/backfill, schema migration, production secrets/webhooks or external sends. Recoverable Media trash may need an additive persistence proposal; approve that separately, rather than silently adding a table or using permanent deletion as a substitute.

## 2. Phase 1 — Website + compact chat + charcoal theme + Assistant plan

### Recommended implementation order

| Slice | Deliverable | Acceptance / proof |
|---|---|---|
| **1A — navigation and immediate UX** | Website heading; SEO Overview/Agent tabs; charcoal admin tokens; compact chat; honest chat availability in Settings; Speed mobile layout | All seven bookmarks still work; role checks on direct routes and tabs; 390px no page overflow; correct-thread inbound + replies; no lost drafts; no full-height thread sidebar auto-open |
| **1B — Media parity, safe first** | Grid/list, folder/type/size/usage filters, selection, details/copy URL, dimension/size/variant/usage display; crop/resize as a new copy, optimization preview and progress | Actual public file/DB agreement; unchanged original for crop; truthful byte savings; large/corrupt image bounds; variants and alt propagation; unauthorized and used-image delete rejection |
| **1C — real preview and publish** | Public Theme side-by-side live preview and device/page switcher; Website Settings reactive preview, saved/pending/error state | Unsaved edits change only preview; explicit Save publishes; reload proves persistence; cancel leaves live CSS/settings unchanged; iframe/proxy/cookie/keyboard tests; token validation and rollback |
| **1D — useful audits and latency** | File-backed Speed coverage, shared versioned scan snapshot for SEO/health, targeted rescan, honest diagnostics, async external audits | Reconcile actual served files and DB-backed pages; no scan per keystroke; progress/failure/last-scan states; invalidation on actual file/media changes; no synthetic scores; measured before/after latency |
| **1E — Assistant** | Final Assistant capability/permission plan; implement approved bounded read-only Website questions using native query services | Source rows/pages and scan timestamp cited; permission/ownership parity; unavailable/stale data and unsupported questions explicit; no autonomous changes or invented AI connection |

Finish/report each slice before unrelated work. Main preview stays usable; mutation tests use a separate synthetic QA copy. Phase 1 approval authorizes this native order, not destructive cleanup, new schema, arbitrary AI writes or provider sends.

### 2.1 Website workspace

Proposed Website navigation:

1. **Media** → existing `/admin/website/media`.
2. **SEO** → Overview tab `/admin/website/seo-overview`; Agent tab `/admin/website/seo`.
3. **Speed** → existing `/admin/website/speed`.
4. **Site health** → existing `/admin/website/health`.
5. **Theme** → existing `/admin/website/theme`.
6. **Website Settings** → existing `/admin/website/settings`.

Use native components and permission-filtered navigation. Keep `website.edit`, `theme.manage`, `settings.manage` boundaries; hiding a link is not authorization. Preserve query filters/back/forward and active states. Builder/Blog/Portfolio/Forms full-document navigation stays intact; do not apply Livewire body-only navigation to their independent bundles.

### 2.2 Media — port the workflow, not dead API names

The committed v2.1 `admin-media.js` really contains folders, grid/list, filters, selection, usage, crop/resize, compression, replace, responsive variants and trash/restore workflows. Native Media currently offers upload, search, missing-alt filter, metadata and guarded permanent delete. The owner is correct that functional parity is incomplete.

- Phase 1B starts schema-free: use existing `folder`, image dimensions/path and native public-file index; bounded/paginated usage lookup shared with other tools.
- New uploads: MIME/content/dimension/pixel-count limits; safe names/path boundaries; server validation of any browser-generated WebP; progress/error reporting. No SVG/document upload expansion without explicit sanitizer policy.
- Crop/resize: new copy, original retained. Do not silently rewrite source assets referenced by the site.
- Optimization/replacement: dry run and byte-size comparison, exact selected set, backups, atomic file writes, invalidation and rollback. Do not report a gain when output is larger. Preserve URLs/variant relationships and test public delivery.
- Bulk controls: select current page vs all results explicitly; per-item failures and skipped in-use assets; no "unused" conclusion from the current results page alone.
- Trash: design retention/restore/collision/reference safety before destructive controls. If new metadata/storage is necessary, submit the additive proposal separately. Until then retain in-use protection and do not enable bulk purge.

### 2.3 Theme and Website Settings — actual real-time feedback

**Public Theme:** reuse `ThemeBuilder`, `ThemeCss`, `ThemePresets` and v2.1's `admin-theme.js` interaction pattern. Put an always-visible preview beside controls on desktop; stacked/device switcher on mobile. Same-origin allowlisted site pages only; preview receives validated draft CSS, not arbitrary scripts or a public unauthenticated mutation endpoint. Draft presets/reset must not publish immediately. Explicit Save validates/persists/invalidates, with last-saved and failure states. Keep v1 aliases, local fonts and valid public URLs. Test real computed color/font/radius changes in the embedded website, not only updated swatches.

**Website Settings:** keep existing analytics/chat settings as the source of truth. Add reactive preview and readable effect summary, explicit saved/unsaved state, enabled-vs-configured diagnostics and links to relevant tools. Don't auto-publish credentials or tracking IDs on every keystroke. Show chat off/on and loader/public availability separately. Do not label a nonempty Search Console verification string "verified". Broader business/header/footer controls should reuse the existing business settings, builder/global propagation and find/replace workflows with per-file backups, not a second set of unused database fields. Inventory extra v2.1 settings before adding controls; only expose settings that affect live output.

### 2.4 Chat — small current-conversation popup

- Desktop target: roughly **380px wide × 540px high**, capped to viewport; bottom-right rather than a 680px-wide full-height modal. Mobile: width bounded by viewport/safe-area, height bounded by dynamic viewport/keyboard. Exact geometry verified in-browser.
- One transcript and composer, no conversation sidebar inside the popup. Full Inbox link keeps thread selection, assignment and history available elsewhere.
- Only eligible new visitor inbound messages auto-open; do not replay old messages or reopen on own/bot replies. Keep existing snooze, visibility and draft safeguards.
- Same-thread inbound preserves typed draft. Different-thread inbound shows a small pending action without silently switching or discarding. Manual open/close, Escape, focus return and polite announcements; no forced focus theft from an unrelated form.
- Public widget is **off in the current main preview**. Phase 1 must verify/toggle the native setting intentionally, then test two separate staff/visitor contexts. Do not infer production is off from local configuration.
- Test public loader, session isolation, name → valid phone/email → immediate CRM record → service enrichment, staff response to same visitor, reopen, missing contact, consent flags and duplicate requests. No live WhatsApp/Telegram sends needed.

### 2.5 Admin charcoal palette

Use the owner's second image as a visual reference, not a licensed template import. Candidate neutral surfaces: page `#1f1f1f`, surface `#252525`, elevated/header `#303030`, border `#454545`, primary text `#ededed`, muted `#b0b0b0`. Measure actual contrast before finalizing; keep recognizable action/status/focus colors. Replace navy backgrounds consistently across sidebar, header, cards, tables, forms, popovers, chat, charts and Assistant. Retain Light/System and stored `wx.theme=dark` compatibility. Verify disabled/hover/selected/error states, reduced motion and real PDFs staying white.

### 2.6 Speed and health

Separate **admin interaction speed**, **public page delivery** and **external PageSpeed/Lighthouse**.

- Profile full navigation, Livewire navigation, filters, create/save and global polling. Record TTFB, interactive completion, SQL count/time, scanned files and payload/bundle sizes. Slow state must be visible, with duplicate submits blocked.
- Profile Media image fanout as well: `/media/...` passes through Laravel, so queued thumbnails can compete with navigation in the sandbox. Use appropriate existing variants, bounded loading and verified caching/conditional requests; retain durable storage outside deployment-replaced public files.
- Current sandbox serializes PHP requests in one WASM runtime. Measure its queue separately; production PHP-FPM/OPcache/hosting must be measured on that deployment. Never use sandbox timings as a production SLA.
- `FileAudit` rereads site files for SEO/Agent/health renders; health rescan invokes it for toast and render. Use a shared freshness-aware snapshot and explicit invalidation, not blind TTL caching of wrong content. No "fresh scan" toast if only old data was served.
- `InternalSpeedAudit` uses published page/block/media records and page-cache measurements, not the same file inventory as SEO. Cover static HTML images/srcset/CSS backgrounds/scripts/styles, deduplicate assets and distinguish per-page transfer from shared site asset storage. Keep DB-backed pages covered too.
- `PageSpeed::run()` currently waits synchronously up to 90 seconds. Plan background jobs with progress, single-flight/rate limits, cancellation policy, error types and recent history; use the existing queue infrastructure. Check deployed queue worker before presenting async work as operational.
- Health should report its exact scan scope and timestamp, explain findings and link to real fixes. Expand tests for missing assets, redirect chains/loops, malformed canonicals/schema, public loader failures and actual robots/sitemap response. Permission-checked, preview-first repairs with backups; no broad automatic metadata rewrite.

## 3. Assistant — completion path, not an AI badge

Current engine has nine deterministic question types. It is deliberately read-only, cites results and keeps the thread in memory. No generic natural-language agent or AI-provider call is established by that UI. Adding a key alone does not implement those capabilities.

1. **A — reliable native answers:** review per-role AND per-record scope; clarify supported questions and numerical definitions. Add Website scan summary, unlinked/needs-service web chats, failed automations and pending approvals only through approved read-only query services; cite rows/files and timestamp. Test empty, stale, unsupported, ambiguous, forbidden and failure cases.
2. **B — planning assistant:** propose prioritized tasks from real diagnostics, never present a proposal as an executed fix. Owner can open the cited native action. Keep bounded context, no bank details/secrets sent off-device, document access and retention policy.
3. **C — optional model provider:** owner selects provider/model/key through secure settings later; approved outbound data, budget/rate limits, timeout/fallback and prompt-injection-resistant tool allowlist. Deterministic totals remain authoritative. Show keyless/provider-ready/provider-error honestly.
4. **D — controlled actions, separate approval:** explicit before/after preview, per-action authorization and owner confirmation, idempotency, durable audit and rollback. No direct SQL/shell, autonomous publishing, financial changes or messaging from model text. New action/history schema requires a separate approved design.

Phase 1 covers A's Website slice and the complete B–D plan, not an autonomous full-system AI agent.

## 4. Phase 2 — CRM, Sales, Accounts, Projects, Inbox, Automation, Marketing

After Phase 1 acceptance, inventory every route/action/API and execute real workflow QA, not just page-open checks. Existing reports44–48 remain evidence, not a waiver of new testing.

| Area | End-to-end lifecycle and high-risk tests |
|---|---|
| CRM | website/estimator/chat intake → contact dedupe → scoped lead → assignment/SLA → task/visit → qualification; anonymous/abandoned visibility; validation, retries and ownership |
| Sales | estimate/rates → Interior/Furniture quote → revision/approval/share → frozen snapshots → invoice → receipt; editor/HTML/PDF agreement; permission and tampering tests |
| Accounts | pending/verified/voided payment → staff settlement → ledger/journal/trial balance/payables/period locks; atomicity, replay, reversals and reconciliation; no unapproved F05 definition changes |
| Projects | quote/invoice linkage → milestones → task/board → handover → portal; access links, completion rules and cross-project isolation |
| Inbox | native channels, assignment/unread/history/draft/reply; visitor/staff isolation and webhook replay; live provider sends only to specifically approved deployment/destination |
| Automation | trigger → condition → native action → run log/retry/failure; dry run, dedupe, scheduling/timezones, disabled rules and missing-provider behavior; no surprise sends |
| Marketing | real connected account/capability → draft → approval → scheduled/published/failed; attribution/UTM; provider scope/window and rate failures; never claim label-only integrations are transports |

For each area: inventory → reproduce → findings/severity → recommendation/decision → bounded repair → unit/feature/API/native-browser tests → short report. External account certification and concurrent production load remain distinct gates. Keep the financial regression suite in every relevant release gate.

## 5. Phase 3 — SEO after owner provides complete data

Do not fabricate SEO copy now. Prepare a structured intake for service/city/area targets, canonical domain, approved business identity/contact/NAP, actual service areas, page intent, keyword priorities, titles/descriptions, verified project/media rights, competitors, Search Console/GA4 access and current redirects.

Then: baseline crawl of actual served URLs → intent/duplicate/canonical/indexability map → owner-approved per-page metadata/content/schema/internal links → preview → staged publish with backups and redirect export → robots/sitemap/HTTP/schema/mobile/performance QA → Search Console submission/observation when access exists. Respect frozen public URLs and real business facts. No ranking guarantees or invented reviews/service locations. Phase 1's SEO workspace and structural repairs do not wait on all copy.

## 6. Phase 4 — OS organization, performance hardening and next upgrade list

1. **Inventory first:** baseline currently has 2,371 tracked files; [folder inventory](evidence/49/tracked-folder-inventory.json). It is an inventory, not a claim each file has been semantically reviewed. Exclude `.git`, dependencies and runtime/generated caches from code reorganization.
2. **Document boundaries:** maintain root runbook + domain/route/action/API/permission/storage/queue/integration maps. For each handwritten file, record purpose, module, callers, tests and deployment relevance in a generated index with human-reviewed module notes. Generated assets get provenance/build documentation, not copied prose per minified file.
3. **Cleanup proposal:** separate source, required built assets, owner uploads, durable storage, backups, synthetic fixtures, logs, caches and temp files. Dry-run exact paths/record selectors/counts/sizes; retention and backup/restore plan; explicit approval before destructive deletion. Never delete records by name/date alone, reset counters, flush sessions/queues or erase app encryption keys.
4. **Cache strategy:** cache is not automatically junk. Audit invalidation and growth. Keep required production caches warm; inspect stale entries and temporary uploads specifically. Stop/restart the WASM preview for compiled-view clearing because its stat cache can cause 500s. Do not use blanket `optimize:clear` as a speed fix.
5. **Optimize measured bottlenecks:** bounded queries/index proposals, pagination/lazy modules, shared audit snapshots, scoped polling/backoff, bundle splitting and native hosting config. Each optimization needs before/after evidence and invalidation/failure tests.
6. **Organize safely:** incremental moves only after import/route/build/deploy impact mapping. Do not rename root/public paths or disturb Hostinger entrypoints. Keep tracked production build artifacts required by existing deployment.
7. **Main branch delivery:** stay on fixed session branch `arena/8be318a2-woodexinterior`; use PR #1. Review evidence, CI, secrets scan, source/build coherence, backup/redirect export and deployment rollback. Merge to the repository default branch (`master`, as currently configured) only after explicit approval. No new branch or automatic merge.
8. **Next upgrades:** after this documentation and evidence, rank missing capabilities by impact/effort/dependency and ask for the next approved slice. Carry forward revision48 omnichannel foundation/provider certifications and open F05/production reconciliation items rather than dropping them.

## 7. Release acceptance and decision

Every slice must include source findings, reproducible tests, permission/error/empty/mobile checks, no-JS errors, native visual evidence, measured performance where relevant, updated status and a short honest report. Provider fakes and localhost tests are not production certification. A page returning 200 does not prove all its actions work.

**Recommended decision:** approve Phase 1 in the safe order **1A → 1B → 1C → 1D → 1E**, with separate approval for new storage/destructive cleanup/external sends. First implement Website grouping + SEO tabs + compact chat + charcoal palette, then complete the deeper Media/preview/audit work. Phase 2–4 are ordered plans, not authorized bulk changes or already-passed QA.
