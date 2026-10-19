# Page builder: parity with admin v2.1 pro / frontend-v1

19 Oct 2026 · Source of truth for the reference: `marketingwoodex-cloud/-marketingwoodex` PR #9,
branch `arena/8a776c65-marketingwoodex` (cloned for analysis, not vendored into this repo).

The reference repository contains the finished **frontend-v1** site (87 static pages), the full
**v2.1 pro admin** (`admin/*.js`), the **live page builder** (`frontend-v1/builder/`), the builder's
PHP backend (`frontend-v1/api/builder.php`) and 50 v26 section templates (`builder/templates-v26.js`).
Woodex OS already carries the same content model and the v26 look; what it does not carry is the
v2.1 pro **editing surface**.

## 1. What Woodex OS's builder does now (after the 19 Oct round)

| Capability | Where |
|---|---|
| Live preview inside the editor, refreshes after typing | `PageEditor::refreshPreview()` + iframe nonce |
| Preview exists before the first explicit Save (draft autosaves itself) | `PageEditor::refreshPreview()` |
| Click a section in the preview → jumps to its form | `SiteRouter::editorToolsScript()` + `editSectionFromPreview()` |
| Drag-and-drop reorder | `wire:sort` (Livewire 4 API) |
| Add / duplicate / hide / delete a section | `addBlock`, `duplicateBlock`, `toggleBlock`, `removeBlock` |
| Section library (17 typed blocks), saved sections, templates | `SectionLibraryIndex`, `page_templates` |
| Desktop / tablet / mobile frame | `setPreviewDevice` |
| SEO fields (title, description, keyword, robots, social image) | page editor sidebar |
| Page history / restore, share link (24 h), publish gate | `restore`, `makeShareLink`, `PageValidator` |
| Draft preview served by the CMS even where a static page exists | `routes/web.php` fallback + `?preview=1` rules |

## 2. What the v2.1 pro builder has that Woodex OS does not

The reference is a VvvebJs-style **HTML** editor: it edits the page's own markup, not blocks.

| Area | v2.1 pro behaviour | Woodex OS today |
|---|---|---|
| Canvas | Free selection of any element, hover outline, floating toolbar (parent, up, down, duplicate, delete, HTML, quick styles, save as block, drag-move) | Sections only; fields in a side form |
| Content panel | Text/tag/link/image/video/map editing, section label | Typed fields per block |
| Section panel | Container width (boxed/full), height, background image/video/YouTube, parallax, overlay colour + opacity, top/bottom separators (wave, slant, curve, triangle, zigzag), centre, swap image side, hide | Not available (design fixed per block type) |
| Style panel | Display, position, size, margin, padding, border, radius, typography (1 500+ Google Fonts), colour, background image, hover/focus/active states, transitions, transforms, per-device (desktop / ≤1024 / ≤640) | Not available |
| Advanced panel | id, classes, ARIA, hide per device, custom CSS | Not available |
| Layers tree | Whole DOM hierarchy, drag to reorder, search | Flat section list |
| Undo / redo | 100 snapshots, Ctrl+Z / Ctrl+Y | Not available |
| Code editor | Whole page HTML + CodeMirror, sanitising | `canvasHtml` (campaign HTML) only |
| Theme | Site-wide colours, fonts, sizes, spacing, width, radii → `theme.css` | `ThemeBuilder` (tokens) |
| SEO panel | SERP preview, counters, keyphrase analysis, accessibility/SEO audit | Counters only, no analysis |
| Media | Library, upload, delete, search, free-photo search (Openverse/Pexels) | `MediaLibrary` (upload, library) |
| Page actions | New / duplicate / export HTML / ZIP, page history (30 backups), change password | New / duplicate / history; no export |
| Global header/footer | Edits copied to all 87 pages with a warning | Header/footer are layout Blade files, not editable |
| Auth | Separate `/builder/` password, HMAC token | Laravel session, roles/permissions |

## 3. The structural difference

The v2.1 pro builder stores **HTML**. Woodex OS stores **typed blocks** (`page_blocks.type` + JSON
`data`) and renders them through Blade components (`resources/views/site/sections/*`). The block
model is what makes the publish gate, the section library, the slider/hero rules, the SEO fields and
the CRM form wiring possible — the HTML builder cannot validate or reuse any of that. It is also why
the fastest route to "the same feel" is not a wholesale port: it would replace the CMS's data model
with a blob of HTML per page.

## 4. Three upgrade paths

**A. Block editor + v2.1 pro panels (recommended).** Keep typed blocks, add the parts of the pro
builder that make it feel like a builder: a section style panel (background preset, spacing, width,
align, hide), a live layers list with drag reorder, undo/redo for structural edits, per-section
labels, and an SEO/accessibility audit panel. The v26 design stays fixed (the owner's decision), so
per-element CSS editing is deliberately out of scope.

**B. Import the 50 v26 templates as built-in page templates.** Convert `templates-v26.js` into
Woodex OS `page_templates` rows (typed blocks), so any page can start from "Hero · full image, bottom
text", "Numbers strip", "Why Woodex", etc. Independent of A, and it is the cheapest visible upgrade.

**C. Full visual HTML builder.** Port `frontend-v1/builder` into the admin: store page HTML
(`canvas_html` exists), render it through the CMS, add the style/content/advanced panels, layers,
undo, code editor. This is the closest to v2.1 pro but changes the storage model, the publish gate,
the section library and the click-to-edit contract — a multi-week build, and it loses block-level
validation unless the builder writes blocks back.

## 5. Recommendation

Do **B now**, then **A**: they are additive and keep every guarantee the current CMS has (publish
gate, Slider-led home, no double hero, preview before first save, click-to-edit). Revisit **C** only
if the owner wants free-form HTML editing on top of the block model — in that case the sane version
is an "HTML block" type that is sanitised on save, not a replacement of the storage model.

## 6. Open questions for the owner

1. Should the builder stay **block-based** (sections you add, fill in, reorder) or become a
   **free-form HTML canvas** like `/builder/` in the reference repo?
2. Should the 87 frontend-v1 pages be **imported into the CMS** (editable in the builder, static
   files removed) or stay as frozen files that only the CMS pages compete with? Today a static
   `index.html` shadows a CMS page of the same slug, except in `?preview=1`.
3. Which panel is worth building first after the template import: **section style panel**, **layers +
   undo/redo**, or the **SEO/accessibility audit**?
