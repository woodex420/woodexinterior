# 36 — Quotation workbench correction (27 October 2026)

## Decision and source

Owner paused Slice W after showing the quotation page and its optional preview drawer.
Asked for the actual v2.1 design, a side preview always shown, and changes visible while
editing. Selected **Review source first**, then explicitly **Start** after the comparison.
Approved correction: existing editor; three v2.1 layouts; current-input preview independent
of saving; both interior and furniture; preserve frozen sent versions.

Rechecked the actual `Woodex-Live-v2.1-Pro-Production.zip` from Drive:
`admin-print17.js`, `admin-print.js`, `admin-sales17.js`, `admin-sales.js`, template source
and bundled DM Sans fonts. Private folders and production database content were not read.
No browser-JavaScript admin or production records were transplanted into Laravel.

The previous Step 44/46 claims did **not** mean complete source-layout parity:
- The OS pane was a fixed drawer, closed by default, covering the editor.
- Blur-bound fields and a saved-state snapshot meant no unsaved typing preview.
- Some source colours/labels had been adapted; the OS header/font/geometry remained.
- Single and Project layouts were absent. The screen footer appeared before content.

## Delivered — Step 48

- One existing quotation editor with a persistent, sticky desktop preview in a separate
  column, not an overlay. Narrow screens stack it below the editor; no horizontal overflow
  at 390px. Header controls wrap on narrow screens rather than overflow.
- Layout and colour selectors are immediately above the preview. **Classic BOQ** = summary,
  totals, per-section BOQ. **Single page** = continuous table with section rows, then totals;
  the UI explicitly says long quotations may continue onto more pages. **Project** = summary
  and separate trade pages. Custom block arrangements remain available; changing a default
  layout adjusts its default blocks, without discarding a customised order.
- Source-derived native quotation shell, integrated brand/document header, rounded client/
  project boxes, DM Sans, source dimensions, table styles, total/words band, footer after
  content in HTML and fixed at the bottom in PDF. Source **Classic navy/gold**, **Minimal
  monochrome**, **Premium espresso/cream** palettes apply to both business lines. The old
  OS furniture-green override no longer recolours the v2.1 Classic template. Logo/company/
  footer data still come from the OS's existing settings, not production source records.
- Bundled v2.1 DM Sans 400/500/700 containers losslessly decompressed from WOFF2 into TTF for
  Chromium and dompdf to share the same outlines; OFL and provenance included.
- Read-only `POST /admin/quotes/{quote}/preview` accepts current form values. A 350ms debounce
  plus render/request time updates `srcdoc`; no native PDF iframe or opening action needed.
  Sequence checks reject stale responses; resize fitting preserves A4 geometry and scroll.
- **Preview is not a save.** A Livewire preview action was rejected during browser QA because
  it flushed pending blur-bound inputs. The separate authenticated, CSRF-protected endpoint
  performs no model writes. Existing save-on-blur remains. The preview explicitly distinguishes
  editing values from the saved PDF. Invalid/incomplete input keeps the last valid document
  with a paused-preview message; connection failures are visible and the next edit retries.
- Projection clones only this quotation's own rows, reuses the canonical QuoteCalculator,
  rounding settings and milestone remainder split, escapes text and sanitises scope HTML.
  Foreign item IDs are ignored. View/update policy checks protect the endpoint; read-only or
  sent quotations cannot be overlaid. Payload counts/types/lengths are bounded; rate limited.
- Layout/theme/blocks round-trip through existing template blueprints, native template-file import/export and v2.1 pack imports.
  No extra template table or new module; no schema change. Template presentation copies no
  client, bank, logo or company data.
- New snapshots carry a v2.1 renderer marker and layout. Old frozen snapshots retain their
  legacy renderer; stored sent PDF bytes are untouched. Read-only sent preview uses the frozen
  snapshot, never a changed contact or current brand defaults.
- Removed obsolete quotation Sending/P2 placeholder copy. No invoice redesign or Slice W work
  was folded into this correction. The public v1 website and its CSS were not changed.

## Verification

- **Final full Pest:** **1,547 passed · 1 skipped · 8,257 assertions · 0 failures**.
- **Pint:** clean on 25 touched PHP/blade files. **PHPStan:** no errors. Vite build and
  `git diff --check` pass.
- Six actual PDFs rendered for three layouts × two business lines. PDF text/font inspection
  confirmed embedded DM Sans; first-page raster proofs inspected. Browser/PDF use the same
  native quotation blade and projection data, with screen-only fitting kept out of PDF output.
- Chromium, port **33143**: WI-10102 and WF-10050. Persistent pane does not overlap editor;
  quantity changed while focus remained and saved total stayed unchanged. Rate, client name
  and scope changed visibly without blur. All three layouts switched; Project showed three
  interior trade pages and one furniture trade page. 390px width checked, no JS errors.
- Cookie-restricted iframe harness: both **third-party cookies blocked** and **all cookies
  stripped by the proxy** passed login/navigation and the live unsaved quotation preview.
- Browser proof restores the demo SQLite file; no proof customers, tokens or financial rows
  remain. Repeatable check: `PORT=33143 node tools/sandbox/quotation-workbench-check.mjs`.
- A first full run exposed the unrelated DailyDigest test's random-date premise: the visit
  lead factory sometimes also chose yesterday. Pinned that visit lead to today, leaving the
  explicit yesterday lead as the only counted row. Runtime digest code was not changed;
  isolated rerun and subsequent full gates passed.

Transient evidence: `/tmp/qa/quote-release-gate.log`, `quote-stan-final.log`,
`quote-pint-final.log`, `quotation-browser-final.log`, `quotation-iframe.log`;
`/tmp/woodex-run/shots/step48-*`. These can disappear on a sandbox reset.

## Boundaries and next decision

This is a native source-derived renderer, not a transplanted html2pdf engine or a claim of
pixel/byte-identical pagination. HTML preview is a continuous A4-width sheet; PDF pagination,
repeated table heads and canvas page numbers remain the PDF engine's responsibility.
The existing native priced-line model remains; a full v2.1 heading/specification row-kind
editor is not shipped by this correction. Source private banks/customer data were not imported.

**Slice W stays paused pending the owner's review of the corrected quotations.** T remainder
(template cards/chips/advance-%), U (invoice fields/sample PDFs), X and explicitly deferred
Sheets append/off-site backups remain as documented in Plan 35.
