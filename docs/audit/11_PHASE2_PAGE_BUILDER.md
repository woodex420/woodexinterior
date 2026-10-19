# 11 · Phase 2 — page builder completion

**Authorised:** 19 Oct 2026, by the owner's message "Phase 2" (roadmap in `08` §3).
**Branch:** `arena/8be318a2-woodexinterior`.
**Constraint honoured:** no schema change. Conflict detection was first built on a new `pages.revision`
column and then **reverted to a content fingerprint** precisely because the standing rule is "no schema
changes without explicit approval"; the migration was deleted before it reached a commit.
**Public HTML:** unchanged unless a page's editor explicitly sets a device value. A page with no
per-device values renders byte-for-byte as before (asserted by the existing `SectionStyleTest` cases).

---

## 1. Scope, and what this slice actually delivered

`08` §3 lists seven work areas for Phase 2. This slice takes the four that are independent of the
`columns` primitive. The rest are listed in §6, not silently dropped.

| Roadmap item | Status in this slice | Where |
|---|---|---|
| **Breakpoints** — per-breakpoint styles for padding, spacing | **Delivered for section spacing + per-device visibility.** Typography and column widths are not in this slice (see §6) | `SectionStyle::css()`, editor device switcher |
| **Editing speed** — copy/paste, multi-select | **Delivered.** Tick sections, copy, paste here or on another page; duplicate/delete the selection as one undo step | `PageEditor`, `PageEditorClipboardTest` |
| **Safety** — conflict detection on save | **Delivered** as an optimistic fingerprint check (no schema change) with an explicit reload path | `PageFingerprint`, `PageEditorConcurrencyTest` |
| **Visibility** — "served by a static file" badge | **Delivered** as an editor notice naming the exact file; the Phase 3 cutover removes it | `StaticShadow` |
| **Layout primitives** — `columns` section | **Not started** — needs a new block type, renderer, editor form and sanitisation tests (§6) | — |
| **Content** — reusable sections, theme presets | Saved sections already existed; theme presets not in this slice | existing |
| **Parity** — `tools/website/compare.php` report | **Not run** — blocked until a group is ready for cutover (Phase 3) | — |

Everything below is reversible per capability through `config/website.php` (§5).

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

**13 new tests** in total; the full suite grew from 125 to 127 files without weakening an existing test.

The one existing assertion that needed updating is `SectionStyle::clean()`'s array shape (it now has a
`responsive` key). No behavioural assertion was weakened.

## 8. Still open in Phase 2

| Item | Why it is not here | Next step |
|---|---|---|
| `columns` block (2–4 children) | New block type + renderer + nested editor + sanitisation; a half-built nesting model is worse than none | Design the JSON shape and the render contract first, then implement with tests |
| Per-breakpoint typography and column widths | There is no typography setting to make responsive yet; adding one that Tailwind child classes override would be a fake control | Decide whether typography is section-level or element-level (needs the inspector) |
| Per-element inspector | The editor is section-granular today; an inspector needs a target model | Scope after `columns` |
| Autosave recovery after a crash | Live preview saves drafts already; "recover unsaved" needs a local buffer design | Separate slice |
| Theme presets previewed live | Theme screen exists; presets need a token model | After typography |
| `tools/website/compare.php` report | Needs a group ready for Phase 3 cutover | Phase 3 |

## 9. Verification (19 Oct 2026, after the changes)

| Check | Result | Raw |
|---|---|---|
| Pest, 127 files, 13 batches | **1,225 passed · 1 skipped · 0 failed · 8,518 assertions** | `evidence/phase2-all-tests.txt` |
| Batch failures | none — failure log empty | `evidence/phase2-test-failures.txt` |
| Pint (`--test`) | **PASS — 821 files** | `evidence/phase2-pint.txt` |
| PHPStan / Larastan | **0 errors — 556 files** | `evidence/phase2-phpstan.txt` |
| Smoke | **SMOKE OK (68 pages)**, all 200 | `evidence/phase2-smoke.txt` |
| Builder browser check | **BUILDER CHECK OK** — 15 steps, now including *per-device spacing reached the preview (mobile ≤640px)* and *one section copied and pasted (7 → 8)* | `evidence/phase2-builder-check.txt` |

The Phase 1 numbers were 1,212 passed / 1 skipped / 125 files / 8,475 assertions. The difference is the
13 new tests, not a change to the old ones.

**One builder-check failure was found and fixed during this work:** the layers-search assertion used a
fixed 1.2 s wait, and the heavier editor (device controls, selection toolbar) made the wasm round-trip
exceed it, so a working filter was reported as broken. The check now polls for the list to change
(30 × 200 ms) instead of assuming one round-trip; it passed 7 → 1 on three consecutive runs.

## 10. Deployment notes

* No migration. `php artisan optimize:clear` after deploy if config is cached (`builder.*` is new).
* Blade change: `layouts/site.blade.php` now prints `@stack('head')` — it had none, so `@push('head')`
  from `site/page.blade.php` was the only consumer and is the reason the hook was added. On a page
  with no device styles it prints nothing.
* The four env vars default to `true`; absence of a value is not "off".
* Roll back a single capability by setting its flag to `false`; roll back the whole slice by checking
  out the previous commit — no data migration to undo (saved `responsive` values are ignored when the
  flag is off, and remain in the JSON if it is switched back on).
