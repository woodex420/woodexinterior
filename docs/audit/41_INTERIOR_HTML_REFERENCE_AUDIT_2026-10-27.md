# Interior quotation / BOQ — supplied HTML and screenshot audit

**Date:** 27 October 2026
**State:** audit and preview stage complete. Owner first required **preview before approval**, then reviewed the screenshot and said **“good update now”**. Native integration and additional Furniture polish are now delivered in [report42](42_INTERIOR_IMPLEMENTATION_FURNITURE_POLISH_2026-10-27.md). The prototype-only descriptions below record the earlier stage, not the current implementation status.
**Owner request:** next update the Woodex Interior quotation using the supplied `code.html` and `screen.png` after the Furniture design-only delivery.

## Recommendation

Apply the supplied visual direction to **new Interior Classic BOQ quotations**, with the same native live-preview/PDF workflow, correct calculated amounts, retained commercial controls and historical version protection. Do not replace Furniture, invoices, alternative quotation layouts or public-v1. No schema change or sample/customer import.

## Source identity

- `code.html`: approximately23KB; SHA-256 `0b68404ce1923f73f39c6d4baaf6f3d7cd2175248e6b29e9095a602d3e14ec50`.
- `screen.png`: approximately284KB; SHA-256 `e81e21094fe399ff30fb36bfe0ef9aef6b2030c45e7951286bf1490f526e9fd5`.
- The supplied screenshot and HTML are the design authority, not an invented layout or image-generation result. Original source files/customer text remain attachments and are not copied into Git or demo records.
- Checked current `quote-v21.blade.php`, its style/summary/BOQ/totals/signatures partials, renderer dispatch, `DocumentStyles::quotationCopy()`, `QuoteSnapshot`, and section model fields. Existing native modules are reused; a fresh v2.1 archive download is unnecessary for this owner-supplied update.

## Reproduced arithmetic discrepancy

Parsed both source HTML tables, checked every detailed quantity × rate, then ran those12 input pairs through the actual native `QuoteCalculator` without discounts, charges or tax. All12 displayed line amounts match their multiplication, but aggregate figures conflict:

| Figure | Reference shows | Derived from the detailed rows |
|---|---:|---:|
| Demolition / civil grouping, rows1–2 |Rs145,000|Rs145,000|
| Architecture / woodwork grouping, rows3–8 |Rs825,000|Rs918,750|
| Electrical / MEP grouping, rows9–11 |Rs190,000|Rs260,000|
| Finishing / paint, row12 |Rs165,000|Rs165,000|
| Summary / net total |**Rs1,325,000**|**Rs1,488,750**|
| BOQ footer |**Rs1,435,750**|**Rs1,488,750**|

The grouping above follows the apparent reference order; final native section membership comes from the actual quotation, not inferred client data. Native probe returned **148,875,000 minor units** for both subtotal and total. Detailed numeric evidence is saved in `evidence/41_interior_arithmetic.json`. Transient probe source: `/tmp/interior-reference-audit/check-native.php`.

Implementation must derive section summaries and BOQ subtotal from the same priced, non-optional items. Net total still follows existing discount/charge/tax/rounding rules and may legitimately differ from subtotal when adjustments apply; those differences must be disclosed. Do not copy any conflicting aggregate from the reference.

## Visual mapping and boundaries

| Reference feature | Native reuse / proposed treatment |
|---|---|
| Navy/ochre identity, W mark and QUOTATION / BOQ title | New versioned quotation-only header; current company identity and uploaded logo take precedence over sample strings; local reference mark if needed |
| Plus Jakarta Sans / Space Grotesk | Existing local WOFF2 assets are present; prepare equivalent licensed local PDF-readable font copies and test embedding; no Google Fonts/CDN dependency |
| Prepared for / Project details cards | Existing snapshot client/company/address/phone/email overrides plus native quotation title/site/type; no sample customer/date/address import |
| Covered area | Existing `area_sft` is derived from BOQ measurements, not an independently stored physical project footprint. Do not relabel potentially overlapping work measurements as verified covered area. Omit the reference's physical-area claim unless it has a trustworthy native source; existing measured area can be labelled honestly |
| Project code | No dedicated native project-code field was found in this quotation flow. Omit when unavailable; do not invent a code or silently use an unrelated identifier |
| Summary of cost | Existing quotation sections, their names/notes and native subtotals |
| Phase status | Section position/note is available, but no progress-status field exists here. Use an honest **Phase / Notes** or section label, with blank/dash when absent, not a fabricated approval/progress state |
| Net-total pill | Native total with visible adjustments; do not create a second handwritten budget |
| One itemised BOQ table and dynamic item count | Real native items/units/quantities/rates/discounts/optional flags. Keep section context and correct subtotal; never hardcode12 rows |
| Client acceptance and company signatory | Restyle existing signatory/acceptance block, preserving supplied names and visibility controls; no hardcoded people |
| Slim contact footer and architectural colour bar | Current company contact/footer data and local graphics, repeated safely on PDF pages |
| Standalone Edit Details / Print buttons | Keep native editor, always-visible live preview and authenticated PDF routes; do not port the attachment's non-persistent contenteditable toolbar |
| Sample date/30-day validity | Actual native date and stored validity; do not change default commercial validity from a sample |
| No terms/payment details/amount-in-words shown in the screenshot | Preserve native block visibility/order and current commercial data, rather than silently dropping configured content. A visual reference is not permission to erase terms, payment instructions or other enabled blocks |

## Versioning / implementation plan

1. Add a **new Interior quotation design revision** in `quotationCopy()` and carry it in snapshots. Reuse the quotation-only creation/duplication/explicit-apply entry points delivered for Furniture; leave invoice `copy()` and Furniture revision3 unchanged.
2. Route new-revision **Interior classic** snapshots to a new renderer. Preserve the current v2.1 and legacy templates/partials for existing frozen snapshots; alternative single/project/trades layouts remain available unchanged.
3. New Interior Classic BOQ documents use the new design. Existing editable drafts upgrade only through **Apply current design**; sent versions/stored PDFs remain immutable. No bulk rewrite or re-import.
4. Reuse native visible blocks, section/item pricing, current client overrides, saved financial inputs, optional items, terms/payment information and signatories. Respect intentional block hiding/order; no separate HTML pricing engine.
5. Implement PDF-safe tables/spacing and local font embedding rather than runtime Tailwind/flex/grid/CDN scripts. Keep the same renderer/content for native live preview and PDF, with server pagination where needed.
6. The reference's `max-width:820px` and `min-height:1160px` are not a guarantee that all12 rows plus enabled commercial blocks fit a single A4 page. Test actual printed pages; do not clip or shrink text illegibly to mimic a tall browser screenshot.
7. Test new/old renderer dispatch, explicit upgrades and immutable historical bytes; summaries/BOQ/native totals with adjustments/optional lines; custom company data; live typing/focus;390px editing; actual PDF fonts/bounds/page numbers; multiple sections and long descriptions/many rows. Recheck Furniture and invoices.
8. Run focused/full regression, PHPStan, touched-file formatting and build. Update report/evidence and only then commit/push on the existing branch. No production deployment or live-MySQL claim.

## Preview-first decision and visual deliverable

Owner explicitly requested: **“start with preview first then update after approval.”** This authorizes only a visual prototype, not the native renderer update described above.

- Separate preview server on **33145**, alongside the untouched app preview on33143. Server exposes only the prototype HTML, PDF and screenshot; no database, business writes or application routes. Bind0.0.0.0; normal preview framing/host requests allowed.
- **Delivery correction:** owner screenshots show direct `e2b.app` URLs return **403 Missing traffic access token**. Internal HTTP200 checks do not prove external accessibility. Do not expose authentication headers/tokens or change sandbox security. Deliver preview-only PNG/PDF in the existing private GitHub branch so the owner can view them with normal repository access; do not use anonymous inline embeds of private GitHub images. This is artifact delivery only, not native-template integration.
- [Self-contained HTML](evidence/41_interior_design_preview.html), [one-A4-page visual PDF](evidence/41_interior_design_preview.pdf), [desktop screenshot](evidence/41_interior_design_preview.png), [390px screenshot](evidence/41_interior_design_preview_mobile.png), [checks](evidence/41_interior_preview_checks.json).
- Reuses supplied W artwork and existing local Plus Jakarta Sans/Space Grotesk fonts, with no CDN/external requests. Customer/contact/site data are explicitly fictional sample labels; company contacts remain placeholders pending actual saved-settings integration. No uploaded private client data is duplicated in this deliverable.
- Sample summary sections, total pill and all12 detailed rows agree at **Rs1,488,750**, matching the earlier native calculator probe. No tax/discount/extra-charge assumption is hidden in the review screen.
- **Real visual checks passed:** desktop1280, mobile390 fit-to-width with no document overflow,100%/fit toggle, print control, HTTP200 PDF download and iframe rendering. Zero JavaScript errors and zero external requests.
- The first draft overflowed A4 and was rejected by the browser geometry assertion. Reduced whitespace/padding, not item count or type size; reran the checks. The final PDF is **one A4 page**, has all three matching total occurrences, embedded font subsets, all text spans inside page bounds, and acceptance/footer present. Actual PDF raster was visually inspected.
- **Renderer boundary:** this PDF is a Chromium print of the standalone design prototype, **not** a Laravel/dompdf export. It proves this sample layout only, not native history routing, editing, financial workflows or long-quotation pagination. Integration and its regression plan remain pending.
- Terms, payment schedules, optional blocks and extra rows are intentionally not fabricated in this design sample. After approval, integration must retain actual configured blocks and may paginate further; this one-page sample is not a promise that every quotation fits one page.

## Current proof boundary

Changes are restricted to audit/status documents and standalone visual evidence. No `platform/`, database, schema, native template or business-workflow changes. No production deployment. The full application regression suite was not rerun for this prototype-only work. Furniture's prior **1,608 passed /1 skipped /8,615 assertions** remains its historical release gate, not proof that the Interior native update has shipped.

Transient build/check scripts live in `/tmp/interior-reference-audit/{build-preview.py,check-preview.mjs,preview-server.py}`; durable HTML/PDF/screenshots/checks are linked above.

## Approval gate

**Satisfied:** owner reviewed the screenshot and approved with **“good update now”**, adding Furniture polish. Native implementation and its final **1,620 passed /1 skipped /8,704 assertions** gate are in [report42](42_INTERIOR_IMPLEMENTATION_FURNITURE_POLISH_2026-10-27.md). No project-code storage or phase-progress tracking was introduced; those remain separate scope decisions.
