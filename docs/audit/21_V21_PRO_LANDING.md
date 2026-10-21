# 21 — What is still landing from the v2.1 Pro admin

**Date:** 2026-10-21
**Question (owner):** "analysis what is still landing from v 2.1 pro to upgrade woodex os"
**Mode:** analysis only. Nothing in this document has been implemented.
**HEAD:** `b2bbc45` · 142 page files · tests 215 (1931) website / 1229 (6704) full

---

## 1. What "v2.1 Pro" actually is

The old admin was ported into Woodex OS as a bounded set of ES5 modules, not as a working system. Measured on this checkout:

| | |
|---|---|
| Modules | **20** files under `platform/public/assets/admin-v21/` |
| Lines | **5,019** |
| Distinct server actions they call | **105** |
| Screens in their `NAV` (admin.js L82–140) | **30** |
| Screens the OS lets you open (the door) | **4** — blog, portfolio, forms, estimator |

So the question "what is still landing" is really: of those 105 actions, which are already served, which are dead by rule, and which are genuinely absent.

## 2. The accounting

```
105 distinct v2.1 actions
 ├ 53  already served by a Woodex OS screen or the page builder   → port nothing
 ├ 22  retired by the standing rule (DB-only rows, shell, panels) → refuse by name
 └ 30  genuinely missing, in 8 features                           → the real gap
```

Method: every `api()`/`bapi()` call site in the 20 modules was extracted and compared against `StudioApi::ACTIONS` (20) + `StudioApi::RETIRED` (12) + `BuilderApi::handle()` (13) + the Livewire admin screens. Then each survivor was probed live against `/admin/website/builder/api` and `/admin/website/studio/api` on the running preview (33143) — the answers quoted below are the server's own.

## 3. Already covered — port nothing (53 actions)

Each of these has a home in Woodex OS today. Porting the v2.1 action would be a second way to change the same thing.

| v2.1 job | v2.1 actions | Where it is done in the OS now |
|---|---|---|
| Header & footer, menus, theme | `chrome_save`, `chrome_versions`, `global_chrome`, `global_menu`, `theme`, `theme_get` | Page builder regions `REG.header/panel/footer` + **Copy to all pages** (`propagateGlobal`), which backs up each page before it writes. Theme screen for tokens. |
| Page title, description, og tags | `page_meta_save` | Builder **SEO** panel (`#seo-title` / `#seo-desc`, 60/160 counters, SERP preview) writes `<title>`, description and og tags into the file. |
| Page list / open / create / retire | `pages`, `pages_list`, `load`, `save`, `page_delete` | `PagesIndex` (list, reversible **retire**) and the builder (`page_new`, page-select). |
| Blog & portfolio articles | `cms_list/get/save/status/delete/published` | `StudioContent` + the studio screen; `cms_save` renders the page file (rewire, `0207c9f`). |
| Service / city / area pages | `cms_page_kinds`, `cms_city_new`, `cms_city_bulk` | Same actions, ported (`pageKinds`, `cityNew`, `cityBulk`). |
| Forms | `cms_forms` | `formsList` — reads the enquiry forms the site posts. |
| Estimator rate book | `cms_estimator`, `cms_estimator_save`, `cms_estimator_tpl` | `estimatorBook/Save/Template` → `estimator-rates.js` + `estimator_rates`. |
| SEO audit & scores | `seo_list`, `seo_page`, `seo_save`, `seo_scores`, `seo_ai`, `seo_sitemap` | `SeoOverviewIndex` + `SeoAgentIndex`, both on `FileAudit`. Sitemap is a live route built from the 142 files (no rebuild action needed). |
| SEO agent | `sag_get`, `sag_scan`, `sag_propose`, `sag_fix_save`, `sag_cfg_save` | `FileAudit`. Fixes belong in the builder by design (documented in the class docblock), so the propose/fix round trip was dropped on purpose. |
| Speed / PageSpeed / health | `health_get`, `health_scan`, `health_speed`, `health_psi`, `health_settings` | `SpeedIndex` (internal audit always runs; Google behind `SpeedSettings::google_api_key`) + `SiteHealthIndex` (7 checks: broken links, orphans, redirects, alt, meta, robots, sitemap). |
| Page backups | `backups`, `backup_get`, `backup_list`, `backup_run`, `backup_restore`, `backup_delete`, `restore` | Builder **History** (per page, versioned) + site-wide `woodex:site-backup` daily 02:45, keeps 7 (`f671fb5`). |
| Redirects | `redirects`, `redirects_save` | `RedirectsIndex`. |
| Media upload & alt text | `media_upload`, `media_alt` | `MediaLibrary` — upload into `website/YYYY/MM/`, alt written across variants **and into the page files**. |

## 4. Retired by the standing rule (22 actions)

The rule from step 8–22: *the website is its page files; a row nothing renders cannot be a live result.* These wrote `pages` / `content_entries` rows or were pure shell.

| v2.1 actions | Why it is not ported |
|---|---|
| `page_new`, `cms_placements`, `cms_placements_save`, `cms_reorder` | Placing testimonials/team/"hero slides" as *rows* that a renderer injected. `content_entries` holds **0 rows** today, so nothing is lost; the same sections are inserted in the builder as file markup (see §6). |
| `login`, `login_2fa`, `logout`, `password`, `profile`, `me`, `status` | The OS has its own auth (Fortify), 2FA and profile. |
| `users`, `user_save`, `dashboard`, `activity`, `chat_list`, `leads_count`, `appr_count` | Admin shell and counters for tables the file site does not read. |
| `conn_list`, `crm_wa_status`, `gdata_status`, `tg_get` | Integration panels with no OS counterpart and no traffic-driving job. |
| `setup`, `db_reconnect` | Installer. |

Already refused by name in `StudioApi::RETIRED` (12): `cms_page_new/duplicate`, `cms_faq*`, `cms_testimonial*`, `logos_*`.

## 5. Genuinely missing (30 actions, 8 features)

Probed live; the error strings are the server's.

### 5.1 Global find & replace — 1 action (`global_replace`)

*The gap.* The v2.1 "Find & replace" screen previewed matches across chosen areas, then replaced everywhere. **The OS has no equivalent** — `grep` finds no `global_replace`, no "replace everywhere" in `app/`, `resources/views/` or `public/builder/`.

*Why it matters (measured on this checkout).* Every page repeats the business details as literal text:

```
pages: 142
"+92 322 4000768"   in 142 files (399 occurrences)
"info@woodex.com.pk" in 142 files
"Model Town"         in 142 files
```

Changing the phone number today is 142 file edits through the builder, one page at a time. That is the biggest day-to-day hole left by the move to files.

*A port* is one action plus one screen: preview (dry run, per-page counts, page checkboxes) → confirm → write, keeping a backup of each file first, exactly as `propagateGlobal` already does. The v2.1 screen's shape — preview, then "Replace everywhere" disabled until the search re-runs — is the right shape and can be copied.

### 5.2 Section library "My sections" — 6 actions (`blocks_list/save/delete/usage/sync/import`)

*The gap.* `blocks_list` answers **`{"ok":true,"blocks":[]}`**; the other five answer *"… arrives with the section-library slice."* Meanwhile the builder already ships the whole UI: the **My sections** box, the 💾 save-to-library dialog (name, category, tags, "Global section"), the delete-with-usage warning, and drag-to-insert. `renderMine()` runs on every load and prints "Save any section with 💾" into an empty panel.

*Not to be confused with:* the built-in library, which is complete — **50 sections** (`blocks.js`) + **20 elements** (`uikit.js`), shown as *All 70* with category chips (Hero 8, Services, Projects, Gallery, Process, FAQ, Testimonials, CTA, Contact, Stats, Content, Pricing, Team, Basic), plus **50 v26 section templates** in the same tab. What is missing is only *saving your own* and *global sections that stay in sync across pages*.

*Why it matters.* This is a screen already in front of the owner that cannot do what it says — the standing-rule violation in its purest form. Also the natural home for "hero slides" and "team", which are now builder sections but have no reusable store.

*A port* needs a store (a JSON file next to the pages, or a small table), a usage scan over the 142 files, and import/export. The trickiest part is `global` (one block, many pages, edited once) — `propagateGlobal` is the precedent but this needs a live link, not a copy.

### 5.3 Media extras — 8 actions (`media_replace`, `media_folder`, `media_move`, `media_list`, `media_trash`, `media_trash_list`, `media_purge`, `media_sizes`)

*The gap.* `MediaLibrary` does upload, alt (to files), guarded delete, search, missing-alt. No folders, no trash, no size table, no **replace**.

*The one worth doing:* `media_replace` — swap an image and have every page that uses it point at the new file, with alt carried over. That is the same principle as the alt fix the owner asked for (`3775a78`) and it answers "this photo is wrong on 20 pages". Folders/trash/purge are housekeeping with low value on ~300 images.

### 5.4 404 log + redirect plan — 3 actions (`r404_list`, `r404_clear`, `redirects_plan`)

*The gap.* `RedirectsIndex` manages redirects, but nothing records the misses. There is **no hits/404 table** in the schema (only `seed_legacy_redirects`), so this needs a **schema change** — approval required — plus traffic to be worth anything. `redirects_plan` (suggest redirects from the misses) sits on top of it.

*Sequencing:* this is the same subject as the 36 redirects still waiting on the owner's export. Do that first; the log can follow.

### 5.5 Page templates — 3 actions (`cms_tpl_list/save/delete`)

*The gap.* There are **50 rows in `page_templates`**, 11 page types, plus a `CreatePageFromTemplate` action and a `FrontendTemplates` command — and **nothing in the OS calls any of them**. The editor's layout select shows one option ("Default layout"); the builder's "new page" copies an existing page or starts blank.

*But the rows are not reusable as-is.* Their `sections` are v2-renderer shapes (`{type: "hero", data: {...}}` against `BlockType`), and `CreatePageFromTemplate` writes `pages` + `blocks` rows — which, after the renderer retirement, produce **no file and no live page**. Porting the action verbatim would be exactly the fake result the standing rule forbids.

*So the honest port is smaller and later:* let the owner "save this page's layout as a template" from a real file page, and "start a page from a layout" that writes a new `public/<slug>/index.html` through the same writer `page_new` uses. The 50 v2 rows are reference material, not data to import.

### 5.6 AI settings — 5 actions (`cms_ai_get/save/models`, `ai_test`, `ai_run`)

*The gap.* The blog editor has an AI button (`#cm-ai`) that currently answers *"cms_ai_get is not ported yet"*. Nothing is stored, no provider is configured, and the owner has not supplied a key.

*Verdict:* do not build until the key exists. A settings screen for a provider that cannot be called is another screen that promises and cannot deliver.

### 5.7 Business details — 3 actions (`cms_biz_get/save/assets`)

*The gap, and the trap.* `BusinessSettings` (legal/brand name, tagline, address, phone, WhatsApp, email, hours, NTN, review URL) exists and saves — but it is reachable **only** under System settings, and **no page reads it**. The values are hard-coded in the files (see §5.1). NTN appears in **0** of 142 pages.

*Verdict:* do not port `cms_biz_*` as a settings screen. It would edit rows that change nothing a visitor sees. The useful half — phone, email, address, hours — is exactly what global find & replace (§5.1) solves; the rest (NTN, legal name) belongs where it is.

### 5.8 robots.txt editor — 1 action (`seo_robots_save`)

`/robots.txt` is a generated route (`Route::has('robots')` is checked by health). Editing it as text would create a file that shadows the route. **Do not port.**

## 6. Screens with no OS nav entry

v2.1 `NAV` had 30 entries; 8 have no home in the OS nav (37 entries). Their real status:

| v2.1 screen | Status | Where it lands |
|---|---|---|
| Header & footer | covered | Builder regions + Copy to all pages |
| Service pages | covered | `PagesIndex` + builder `page_new` |
| City pages | covered | `cms_city_new` / `cms_city_bulk` |
| Backups | covered | Builder History + `woodex:site-backup` |
| Section library | **missing** | §5.2 — UI exists, server does not |
| Page templates | **missing** | §5.5 — rows exist, nothing reads them |
| Hero slides | retire | The page's own hero (every v1 home/service page has a slider; 8 hero sections in the builder library). `content_entries` = 0 rows. |
| Team | retire | Builder **Team members** section. `content_entries` = 0 rows. |

## 7. Ranked recommendation

Ordered by *value to the owner per unit of risk*, not by how the v2.1 menu was laid out.

| # | Slice | Why first | Effort | Risk |
|---|---|---|---|---|
| 1 | **Global find & replace** | 142 pages carry the phone, email and address as literal text. Nothing else in the OS can change them together. Highest value, and it is one screen + one action. | 1 slice | Low — dry-run first, backup each file, same write path as the builder |
| 2 | **Section library "My sections"** | Removes the only panel in the builder that is visibly lying. Turns one-off sections into reusable ones, and gives hero/team/global bands a home. | 1–2 slices | Medium — `global` blocks are a new concept (live link, not copy) |
| 3 | **Media replace** | Same principle the owner already approved for alt: the row and the files both move. Solves "this photo is wrong on 20 pages". | ½ slice | Low–medium — must rewrite every page that uses the file |
| 4 | **404 log** (then redirect plan) | Makes redirects proactive. Needs a new table → **schema approval**. Only meaningful after the 36-redirect export. | 1 slice + approval | Medium — new table, traffic-dependent |
| 5 | **Page templates, file-shaped** | Real value, but only after #2 (it is "save/apply a layout" on top of sections). The 50 v2 rows are not importable. | 1–2 slices | Medium |
| — | AI settings | **Blocked** — no key. | — | — |
| — | Business details screen | **Do not build** — rows nothing reads (NTN in 0 files). Covered by #1. | — | — |
| — | robots.txt editor | **Do not build** — generated route. | — | — |

### What I would do next

**Slice 1: global find & replace** — the measured 142-file problem, low risk, and it makes the site editable at the scale it now exists.

The screen: Tools → Find & replace, in the OS admin. Search + replacement, scope checkboxes (pages / insights / projects), **Preview matches** showing per-page counts, then "Replace everywhere" enabled only for the same search. Every touched file backed up first (the builder's history mechanism), and the preview re-shown after the write.

## 8. Not in this document

- No code, route, migration or test has been written for any item above.
- No v2.1 screen has been re-enabled; the door still allows only blog, portfolio, forms, estimator.
- The 36 redirects remain blocked on the owner's export, before any DB deploy.

## 9. Evidence

Live probes on the preview (33143), as the signed-in owner:

```
blocks_list    {"ok":true,"blocks":[]}
blocks_save    {"ok":false,"error":"Blocks save arrives with the section-library slice."}
media_delete   {"ok":false,"error":"Media delete arrives with the media slice."}
cms_tpl_list   {"ok":false,"error":"“cms_tpl_list” is not ported yet — …"}
cms_ai_get     {"ok":false,"error":"“cms_ai_get” is not ported yet — …"}
r404_list      {"ok":false,"error":"“r404_list” is not ported yet — …"}
```

Builder library rendered in the live admin: **All 70** tiles (50 sections + 20 elements), *Hero · 8*, plus the separate 50-template gallery. Config: `window.WX_BLOCKS` 50 (`blocks.js`), `window.WX_ELEMENTS` 20 (`uikit.js`), `window.WX_TEMPLATES` 50 (`templates-v26.js`).

Database facts (live): `page_templates` **50** rows across 11 page types; `content_entries` **0** rows; `pages` 142.

File facts (`/home/user/woodexinterior/platform/public`, 142 `index.html`): `+92 322 4000768` in 142 files / 399 occurrences; `info@woodex.com.pk` in 142; `Model Town` in 142; `NTN` in 0.
