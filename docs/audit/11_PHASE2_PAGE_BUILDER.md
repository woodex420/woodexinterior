# 11 · Phase 2 — page builder completion

**Authorised:** 19 Oct 2026, by the owner's message "Phase 2" (roadmap in `08` §3).
**Branch:** `arena/8be318a2-woodexinterior`.
**Constraint honoured:** no schema change. Conflict detection was first built on a new `pages.revision`
column and then **reverted to a content fingerprint** precisely because the standing rule is "no schema
changes without explicit approval"; the migration was deleted before it reached a commit.
**Public HTML:** unchanged unless a page's editor explicitly sets a device value. A page with no
per-device values keeps the same classes, content and public behaviour as before (asserted by the existing
`SectionStyleTest` cases, including the no-settings case).

---

## 1. Scope, and what this slice actually delivered

`08` §3 lists seven work areas for Phase 2. This slice takes five of them — including the `columns`
primitive — and leaves the rest listed in §9, not silently dropped.

| Roadmap item | Status in this slice | Where |
|---|---|---|
| **Breakpoints** — per-breakpoint styles for padding, spacing | **Delivered for section spacing + per-device visibility.** Typography and per-breakpoint column widths are not in this slice (see §9) | `SectionStyle::css()`, editor device switcher |
| **Editing speed** — copy/paste, multi-select | **Delivered.** Tick sections, copy, paste here or on another page; duplicate/delete the selection as one undo step | `PageEditor`, `PageEditorClipboardTest` |
| **Safety** — conflict detection on save | **Delivered** as an optimistic fingerprint check (no schema change) with an explicit reload path | `PageFingerprint`, `PageEditorConcurrencyTest` |
| **Visibility** — "served by a static file" badge | **Delivered** as an editor notice naming the exact file; the Phase 3 cutover removes it | `StaticShadow` |
| **Layout primitives** — `columns` section | **Delivered.** 2–4 leaf children (text, photo, quote) in the section's JSON; no nesting, no raw HTML. See §4b | `Columns`, `BlockType::Columns`, `site/sections/columns.blade.php` |
| **Content** — reusable sections, theme presets | Saved sections already existed; theme presets not in this slice | existing |
| **Parity** — `tools/website/compare.php` report | **Not run** — blocked until a group is ready for cutover (Phase 3) | — |

Everything below is reversible per capability through `config/website.php` (§6).

## 2. Per-device section styles

### Data

`SectionStyle::clean()` now returns a `responsive` map beside the five existing keys:

```php
'responsive' => [
    'desktop' => ['top' => ?int, 'bottom' => ?int, 'hidden' => false],
    'tablet'  => ['top' => ?int, 'bottom' => ?int, 'hidden' => bool],
    'mobile'  => ['top' => ?int, 'bottom' => ?int, 'hidden' => bool],
]
```

* `top`/`bottom` are **integers** in `0…200` px (`MAX_SPACING = 200`); anything else — `"80px;position:fixed"`,
  `-20`, `null`, `"abc"` — cleans to `null` (inherit), `999` clamps to `200`, `-20` to `0`.
* `hidden` is refused on **desktop** (the existing `visible` toggle is the desktop switch); it is
  accepted on tablet and mobile.
* Old pages have no `responsive` key: `clean()` fills the empty map, so their settings are unchanged
  and their HTML gains no `<style>`.
* The map lives inside the existing `page_blocks.settings` JSON column — **no migration**.

### CSS

`SectionStyle::css($settings, $key)` emits, only when something is set:

```css
[data-wx-key="hero1"]{padding-top:80px;padding-bottom:64px;}
@media (max-width:1024px){[data-wx-key="hero1"]{padding-top:48px;}}
@media (max-width:640px){[data-wx-key="hero1"]{padding-bottom:24px;display:none;}}
```

* The selector is the block's own `data-wx-key` wrapper (already printed by `site/page.blade.php`),
  so a rule cannot leak to another section.
* Tablet inherits desktop; mobile inherits every value it does not set. This is plain cascade, not
  duplicated declarations.
* Values are integers and the key is stripped to `[A-Za-z0-9_-]`, so the string is safe to print raw.
  No user text ever reaches the `<style>` block.
* `site/page.blade.php` collects the rules into `@push('head')`; `layouts/site.blade.php` now has the
  `@stack('head')` it was missing. A page with no device values pushes nothing.

### Editor

The section card has a **Per-device spacing** strip with Desktop / Tablet ≤1024px / Mobile ≤640px tabs,
a number field for space above and below each, and "Hide on this device" on tablet and mobile. The
section header shows a `hidden on mobile` badge so a hidden section is not mistaken for a deleted one.
Desktop is labelled as the base; empty means "as designed".

## 3. Conflict-safe saving (no schema change)

`PageFingerprint::of(Page)` hashes the page's type, title, slug, excerpt, SEO, template, canvas HTML and
every block (key, type, data, cleaned settings, visibility, position). The editor keeps the hash it
opened (`#[Locked] $sourceHash`).

* `save()` and the live-preview `persistDraft()` compare the current database hash with that value
  before writing. A mismatch sets `conflict = true`, shows a red panel, and **writes nothing**; the
  editor's own unsaved text stays on screen.
* The panel offers **Reload latest version**, which re-reads the page and clears the notice.
* The editor's own successful save updates `$sourceHash`, so autosave-preview does not fight itself.
  This was caught by an existing test (`CampaignCanvasTest`) during development, not by luck.
* A new page (no id) never conflicts.

**Honest limit:** this is an optimistic check, not a row lock. Two writes in the same instant can still
race; the window is the request between the hash read and `SavePage`'s transaction. A database-level
guard (`lockForUpdate`, or a `revision` column) would close it, but that is a schema change and needs
the owner's explicit approval. Recorded as R-01 below; not presented as solved.

## 4. Multi-select and the section clipboard

* `selectedKeys` (locked) holds the ticked sections; the toolbar shows *n* selected with **Copy**,
  **Paste**, **Duplicate**, **Delete**, **Clear**, and Select all (second press clears).
* **Copy** stores type, data, cleaned settings and visibility in the **session**
  (`website.section_clipboard`), so a section can be pasted into a different page in the same browser
  session. Ids and keys are deliberately not copied.
* **Paste** treats the clipboard as untrusted: unknown block types are dropped, data is merged over
  `BlockType::fields()` defaults, settings go through `SectionStyle::clean()`, and every pasted block
  gets a fresh key. **Duplicate/Delete** act on the selection in one `remember()` snapshot, so one
  Ctrl+Z restores them.
* The clipboard is per session, not per user account, and is capped only by the 25-step undo stack.

## 4b. Columns primitive

`BlockType::Columns` (`columns`) stores its children **inside the section's own JSON**, not in extra
rows:

```json
{"blocks": [
  {"kind": "text",  "heading": "…", "body": "…"},
  {"kind": "image", "image_id": 12, "caption": "…"},
  {"kind": "quote", "quote": "…", "cite": "…"}
]}
```

* **Why JSON:** the page keeps one `PageBlock` per section, so versions, undo, copy/paste, the
  fingerprint and the section settings all keep working with **no migration**. A nested row model
  (`parent_id`) would have been a schema change and a rewrite of every action.
* **2–4 children, enforced on save and on render** by `Columns::clean()` (which also pads a short list
  to two, so a hand-edited JSON cannot break the grid).
* **Three leaf kinds only** — text, photo, quote. Columns cannot contain columns, and raw HTML cannot
  hide inside one: `strip_tags()` runs on every text value, `image_id` is coerced to an integer or
  null, and the renderer escapes what it prints. This is the deliberate limit that keeps the primitive
  a layout tool rather than a second HTML editor.
* **Renderer** (`site/sections/columns.blade.php`): `md:grid-cols-2|3|4`, one column on a phone;
  photos use the same `Media` map and lazy/width/height rules as the other sections; a missing photo
  falls back to its caption instead of an empty box.
* **Editor:** the section card draws its own 2/3/4 switch (`setColumnCount`) and one small form per
  child whose fields follow the chosen kind. Changing the count is one undo step; the preview follows
  the edit like every other section.
* **Test coverage:** `ColumnsBlockTest` (render, cleaning/clamping, stored-markup strip, add/switch/undo,
  non-columns guard) and a sample in `SiteRenderingTest`, which renders **every** block type on one
  page — the guard that a new section cannot ship without a renderer.

## 5. Static-file shadow notice (W-01 interim)

`StaticShadow::covers()` checks whether `public/{slug}/index.html` (or `public/index.html` for `/`)
exists. When it does, the editor shows a warning naming the file and stating plainly that publishing
will not change what visitors see until Phase 3 removes it. It does not block publishing: the draft and
`?preview=1` are unaffected, and blocking would break the cutover workflow.

## 6. Feature flags (rollback per capability)

`config/website.php` gains a `builder` block; each capability can be switched off without a deploy:

| Env var | Default | Off means |
|---|---|---|
| `WEBSITE_BUILDER_RESPONSIVE` | `true` | No per-device CSS is emitted; saved values stay in the database |
| `WEBSITE_BUILDER_CLIPBOARD` | `true` | Toolbar and tick boxes disappear; every selection action returns without changing anything |
| `WEBSITE_BUILDER_CONFLICT_CHECK` | `true` | Saves are last-write-wins again |
| `WEBSITE_BUILDER_SHADOW_NOTICE` | `true` | The shadow warning is not shown |

Off is a server-side no-op, not only a hidden button.

## 7. Tests added

| File | Tests | Covers |
|---|---|---|
| `SectionStyleTest` | +4 | scoped per-device CSS; tablet/mobile inheritance; hiding on one device only; integer clamping and key sanitising; `<style>` present only when set |
| `PageEditorConcurrencyTest` | 4 | save refused after another save; edits kept and reload path; normal save unaffected; shadow notice naming `services/index.html` |
| `PageEditorClipboardTest` | 5 | copy on one page + paste on another with fresh keys; unknown type dropped; multi-delete is one undo step; duplicate selection; select-all toggle |
| `ColumnsBlockTest` | 5 | 3-up grid renders; cleaning clamps 2–4, drops unknown kinds and extra fields; stored markup never reaches a visitor; add/switch/undo; the count switch ignores other sections |

**18 new tests** in total; the full suite grew from 125 to 128 files without weakening an existing test.
Two existing tests were updated **deliberately** for the new section: `SectionLibraryTest` now allows
the fifth `Layout` shelf, and `SiteRenderingTest` gained a columns sample (it renders every block type).

The one existing assertion that needed updating is `SectionStyle::clean()`'s array shape (it now has a
`responsive` key). No behavioural assertion was weakened.

## 8. Still open in Phase 2

| Item | Why it is not here | Next step |
|---|---|---|
| Per-breakpoint typography and column widths | There is no typography setting to make responsive yet; adding one that Tailwind child classes override would be a fake control | Decide whether typography is section-level or element-level (needs the inspector) |
| Per-element inspector | The editor is section-granular today; an inspector needs a target model | Scope after typography |
| Autosave recovery after a crash | Live preview saves drafts already; "recover unsaved" needs a local buffer design | Separate slice |
| Theme presets previewed live | Theme screen exists; presets need a token model | After typography |
| `tools/website/compare.php` report | Needs a group ready for Phase 3 cutover | Phase 3 |

## 9. Verification (19 Oct 2026, after the changes)

| Check | Result | Raw |
|---|---|---|
| Pest, 128 files, 13 batches | **1,230 passed · 1 skipped · 0 failed · 8,553 assertions** | `evidence/phase2-all-tests.txt` |
| Batch failures | none — failure log empty | `evidence/phase2-test-failures.txt` |
| Pint (`--test`) | **PASS — 823 files** | `evidence/phase2-pint.txt` |
| PHPStan / Larastan | **0 errors — 557 files** | `evidence/phase2-phpstan.txt` |
| Smoke | **SMOKE OK (68 pages)**, all 200 | `evidence/phase2-smoke.txt` |
| Builder browser check | **BUILDER CHECK OK** — 16 steps, now including *per-device spacing reached the preview (mobile ≤640px)*, *one section copied and pasted (7 → 8)* and *columns section added and switched 2 → 3* | `evidence/phase2-builder-check.txt` |

The Phase 1 numbers were 1,212 passed / 1 skipped / 125 files / 8,475 assertions. The difference is the
18 new tests, not a change to the old ones.

**Two builder-check failures were found and fixed during this work, both the same mistake:** the
layers-search and the selection-toolbar assertions used fixed waits (1.2 s and 0.6 s), and the heavier
editor (device controls, selection toolbar, columns) made the wasm round-trip exceed them, so working
features were reported as broken. Both now poll for the outcome (30–40 × 200 ms) instead of assuming one
round-trip. After the second fix the check passed twice consecutively, including 7 → 1 search narrowing,
copy/paste and the columns grid.

## 10. Deployment notes

* No migration. `php artisan optimize:clear` after deploy if config is cached (`builder.*` is new);
  columns are stored in `page_blocks.settings`/`data` JSON, so an old row needs nothing.
* Blade change: `layouts/site.blade.php` now prints `@stack('head')` — it had none, so `@push('head')`
  from `site/page.blade.php` was the only consumer and is the reason the hook was added. On a page
  with no device styles it prints nothing.
* The four env vars default to `true`; absence of a value is not "off".
* Roll back a single capability by setting its flag to `false`; roll back the whole slice by checking
  out the previous commit — no data migration to undo (saved `responsive` values are ignored when the
  flag is off, and remain in the JSON if it is switched back on).

---

# 11. Visual Page Builder — slice 1 (2026-10-20)

The v2.1 Pro visual builder now runs **inside Woodex OS**, against the CMS instead of the file
system. This is the first slice: open, edit, save, history. Media, theme and the saved-section
library deliberately answer “arrives with the next slice” rather than pretending.

## 11.1 What was imported and what was replaced

| v2.1 Pro | In Woodex OS |
|---|---|
| `builder/index.html` + `builder.css` + `builder.js` + `ui-kit` + 50 v26 templates + 50 blocks + CodeMirror/Lucide | reused unchanged in `public/builder/` (licences included) |
| `api/builder.php` (files on disk, its own password) | replaced by `WebsiteBuilderController` + `BuilderApi` (Laravel session, `website.edit` policy, CSRF) |
| page = `index.html` file | page = `pages.canvas_html` (`template = 'html'`), addressed as `about/index.html` |
| `mtime` conflict check | `pages.updated_at` timestamp, 409 + explicit force, same message |
| 30 file backups per page | `page_versions` rows, newest 30 kept, restore snapshots the current state first |
| `code_guard` (owner-only new scripts) | `HtmlGuard` — same rule, same site-wide allowance |
| login screen with a shared password | removed; OS login only, no second credential |

Only five edits were made to `builder.js`; each is marked `OS PORT`: API URL from
`window.WX_BUILDER_BOOT`, CSRF header, boot page, preview URL, and no admin-token renewal.

## 11.2 Storage

`pages.canvas_html` was `TEXT` (64 KB). The v1 home page is 130,672 bytes, so migration
`2026_10_20_120000_widen_canvas_html_on_pages` widens the column to `LONGTEXT` — one column, no new
tables, no data rewritten. **Approved by the owner before it was written.**

An HTML page is served as a whole document by `SiteRouter` (head, inline CSS and all); it is never
wrapped in the Blade layout. `PageCache` still applies, keyed on `updated_at`.

## 11.3 Verified in the running app

* `website:import-html /` stored `platform/public/index.html` byte for byte: page #144,
  `template=html`, `published`, `strlen(canvas_html) = 130,672`.
* Builder screen at `/admin/website/builder/144`: canvas mounted with the real home page
  (12 `<section>`s, `<h1>` “Spaces shaped by purpose and identity”), page selector `index.html`.
* Clicked the heading, typed a new one, clicked Save → toast “Saved & live ✓ (backup kept)”;
  read-back through the API returned the new text (130,580 bytes) and a new `mtime`.
* Automated: `tests/Feature/Website/VisualBuilderTest.php` — **12 passed / 42 assertions**.
  Whole website suite — **225 passed / 3,801 assertions**. Pint 832 files PASS, PHPStan 0 errors.

## 11.4 Known limits of this slice (by design)

* Only `template = 'html'` pages open; block pages keep the block editor. Opening a block page in
  the builder would flatten its sections, so `load`/`save` refuse it and `PageEditor` redirects an
  HTML page to the builder.
* Media library, theme panel, saved sections and approvals still return a named error.
* A new page starts from a small v1-styled skeleton; copying an existing page works.
* `public/assets/theme.css` and `public/api/forms.php` are still absent — the imported v1 pages link
  both, which is the theme slice and the form endpoint respectively.

## 11.5 Rollback

Delete the two `website/builder` routes and the builder view; the pages stay `template='html'` and
keep rendering through `SiteRouter`, so unpublishing the builder screen never takes the site down.
Reverting the migration to `TEXT` is possible only while no page exceeds 64 KB.

## 12. Theme panel — the builder and the OS share one theme (2026-10-20)

**Scope (T1 of the theme slice).** The builder's Theme tab edits `theme_tokens`; `ThemeCss` compiles
those tokens to `/assets/theme.css` (v1 pages) and `/theme/{hash}.css` (v2 pages). There is no second
theme store and no second stylesheet.

### 12.1 What changed

* `ThemeTokensSeeder` — 13 tokens the v2.1 panel controls but the store never had: `--wx-beige`,
  `--wx-c1…--wx-c4`, `--wx-font-head`, `--wx-base-size`, `--wx-h1/-h2/-h3`, `--wx-section`,
  `--wx-container`, `--wx-btn-radius`. 28 tokens total (15 carry the v1 design).
* `ThemeCss` — the v1 aliases (`--navy-2`, `--ink`, …) are now emitted **only when the key is
  absent**, so a real token can never be shadowed by an alias.
* `ThemeBuilder` (OS Theme screen) — seeds any seeder token missing from an older installation on
  mount, so the panel and the builder always expose the same controls.
* `App\Domain\Website\Builder\ThemeBridge` — the only translation layer between the panel's `--wx-*`
  keys and the stored tokens (`--navy`, `--r-lg`, …). Validates colours (`#hex`/`rgb()`), lengths
  (`px|rem|em|%`) and fonts; writes through `ThemeToken`; flushes `woodex:theme-css`.
* `BuilderApi` — `theme_get` returns the live tokens, `theme` saves them for `theme.manage` holders.
  Both were `later()` stubs before.

### 12.2 Defect found and fixed while testing

The v2.1 panel does not speak CSS font stacks: it stores `dm`, `system`, `serif` or
`g:Family:400;600`. Saving the panel unchanged therefore wrote `--wx-font: dm`, which is invalid CSS,
and the preview showed a blank font field. `ThemeBridge` now translates both ways — the panel always
receives a v2.1 font code, the store always receives a complete CSS stack
(`"Poppins", system-ui, …`), and a pasted stack is kept verbatim. Font values also reject `;`, `<`,
`>`, `{`, `}` so a theme save cannot inject CSS.

### 12.3 Verified in the running app

* `GET /assets/theme.css` → `200 text/css; charset=utf-8`, `cache-control: max-age=60, public`,
  body starts `--navy: #0C1628; --wx-navy: #0C1628; …`; `GET /theme/{hash}.css` → `200`, and an old
  hash is revalidated (`max-age=0, must-revalidate`).
* Browser run (Chromium): panel opens with the stored values (Plus Jakarta Sans, base 16, H1 72,
  H2 48, H3 22); picking Poppins re-renders the canvas immediately; **Save** → toast
  “Theme saved for the whole website ✓”; `/assets/theme.css` then carries
  `--wx-font: "Poppins", system-ui, …` and `--navy: #7A1F1F`; the palette and font were restored
  afterwards and the CSS returned to `#0C1628` / `"Plus Jakarta Sans"`.
* `tests/Feature/Website/ThemeBridgeTest.php` — **10 passed / 58 assertions**; `Feature/Website` —
  **235 passed / 3,859 assertions**; `ThemeTokensTest` + `ThemePresetsTest` — 10 passed. Pint 834
  files PASS, PHPStan 0 errors.
* Screenshot: `docs/audit/evidence/phase2-theme-panel.png`.

### 12.4 Known limits

* Owner Custom CSS (T3) is not built: the builder's custom-CSS box saves nothing yet.
* The builder panel has the v2.1 *Reset* button but no preset shelf; presets live on the OS Theme
  screen (`ThemePresets`).
* The theme preview inside the builder is injected into the canvas iframe only; a visitor's page
  picks the saved values up from `/assets/theme.css` on the next request.

### 12.5 Rollback

`git revert` the theme commit. Tokens are rows, not schema: the seeder additions and the bridge can
be removed without touching `pages` or the builder's HTML storage.

## 13. Media — the builder's picker is the OS media library (2026-10-20)

**Scope (first half of the media slice).** Replacing an image, setting a background or picking a
photo in the builder uses `website_media`, the same table the OS Media screen edits. No second
upload folder, no second list.

### 13.1 What changed

* `App\Domain\Website\Builder\BuilderMedia` — the picker's two actions:
  * `urls()` — newest 300 images, as the URLs `Media::url()` already serves (`/media/{id}/{name}?v=…`).
  * `upload()` — validates `jpg/jpeg/png/webp/avif`, ≤ 5 MB, stores under
    `storage/app/public/website/Y/m/`, reads the real pixel size and creates a `website_media` row
    with alt text from the file name.
* `BuilderApi` — `media` now returns the library (was `[]`), and `upload` is routed through the
  controller with the uploaded file. `media_delete` remains a named “not in this slice” error
  because deletion must go through the OS Media screen's in-use check.
* `builder.js` — one OS PORT fix: on a site with a single page, a header/footer edit opens an empty
  “Header / footer updated” dialog instead of saying the page was saved.

### 13.2 Verified in the running app

* Browser run: opened the home page, selected the logo (`img.brand-mark`), opened the picker —
  **108 images**, chose one → canvas replaced the `src`, toast “Image updated”; **Save** →
  “Saved & live ✓ (backup kept)”; `/?preview=1` then contains `/media/213/…`.
* `tests/Feature/Website/BuilderMediaTest.php` — **5 passed / 22 assertions** (list order, upload row
  + dimensions + stored file, PDF refused, >5 MB refused, permission enforced).
* `Feature/Website` — **240 passed / 3,881 assertions**. Pint 836 files PASS, PHPStan 0 errors.
* Screenshot: `docs/audit/evidence/phase2-builder-media.png`.

### 13.3 Known limits

* **The public URL still serves the static file.** `/` is `public/index.html`; the builder writes the
  database copy, which is visible at `/?preview=1` (the builder's “View page” already points there).
  Publishing to the real URL is Phase 3 (cutover per page group), as approved — nothing here changes
  the live site yet.
* Uploads in the sandbox are slow (php-wasm): a 130 KB page save measured ~68 s. Not a product
  limit; the same save is milliseconds on PHP-FPM.
* Free-photo search (`import_url`) is not implemented: Openverse search runs in the browser, the
  server-side copy is still a named error.

### 13.4 Rollback

Revert the commit. `BuilderMedia` is additive; the table and the files stay, so nothing is lost.

## 14. Phase 3 — the home page is served by the CMS (2026-10-20)

**Decision.** The owner asked for the home page to be the first cut over, so the builder's pilot page is live
rather than only visible at `?preview=1`. This is ahead of the group order in `docs/13` §4 (Home is group 8);
the reason it is safe now is that the CMS copy *is* the v1 file, byte for byte — there is no design difference to
compare, only a delivery path to switch.

### 14.1 What changed

* `platform/public/index.html` → `platform/resources/legacy/home-v1.html` (`git mv`). The file stays in the repo,
  outside the web root, so rollback is a move, not a re-export.
* `platform/public/.htaccess` — the `RewriteRule ^$ index.html [L]` rule is gone; the comment now explains the
  cutover. `DirectoryIndex index.html index.php` stays, so every other folder still answers with its own file.
* `tools/deploy/after-composer.php` — deletes a leftover `platform/public/index.html` on deploy, the same way it
  already deletes the old static `theme.css` stub. Hostinger replaces files but does not delete removed ones
  (docs/06, D-56), and without this the old file would keep answering `/`.

### 14.2 Verified in the running app

* The page was re-imported from the file first: `website:import-html /` → `canvas_html` **130,672 B, identical to
  `public/index.html`** (compared in PHP, byte for byte).
* After the move: `GET /` → **200, 131,429 B**, byte-identical to `GET /?preview=1`; the only markup the CMS adds
  in the sandbox is the preview server's own beacon (dev instrumentation, not the app).
* `GET /about/` → **200, 83,451 B**, still the static file (no beacon); `/nope-xyz` → 301 to `/nope-xyz/` → 404.
* `node tools/sandbox/home-check.mjs` → **HOME CHECK OK** at 1440, 390 and 1280-no-JS: same `<h1>`, same font,
  11 sections, no horizontal overflow.
* `sh tools/sandbox/smoke.sh` → **SMOKE OK (68 admin pages)**.
* `tests/Feature/Website` → **243 passed / 3,888 assertions**; `tests/Unit` 99 passed; Pint 837 files PASS;
  PHPStan 0 errors.
* `php artisan woodex:conversion-status` now reports `8 · Home | 1 | 0 | 1 | CMS serves this group` and
  “163 of 166 published pages are still served by v1 files; 3 by the CMS.”
* Evidence: `docs/audit/evidence/home-before.png`, `home-after.png` (same page height, 10,017 px).

### 14.3 Guards added

* `tests/Feature/Website/HomeCutoverTest.php` — the root has no static file, the v1 file is kept, other folders
  still have theirs, and `SiteRouter` returns the stored document for `/` unwrapped.
* `ConversionParityTest` now expects the home group to report CMS-served.

### 14.4 Rollback

`git mv platform/resources/legacy/home-v1.html platform/public/index.html`, restore the `^$ index.html` rule and
remove the `$homeStub` block from `after-composer.php`, then deploy. No data changes: the CMS page stays as it is.

### 14.5 Known limits

* 165 published pages are still served as files; the cutover is per group, as approved.
* `/` is now the builder's source of truth: editing it in the builder changes the live page immediately after
  Save (the page cache is invalidated per save).
* The sandbox save is slow (php-wasm, ~68 s for 130 KB); PHP-FPM is milliseconds.
