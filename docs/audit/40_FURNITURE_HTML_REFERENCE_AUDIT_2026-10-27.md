# Furniture quotation update — attached HTML audit

**Date:** 27 October 2026
**State:** approved with “start”; implemented and verified. No production deployment.
**Owner priority:** update the Woodex Furniture quotation from the new HTML attachment before the broader Slice T cards/chips audit.

## Delivery summary

The owner approved the **design-only** boundary with “start”. New Furniture quotations now use the attached visual direction, mapped to native Product/Description and financial data. **50% editable default, native tax/transport calculations, optional sections and signatories remain intact.** No new schema or sample-data import.

- New quotation-only renderer **revision3**; revision1/2 Blade files are untouched. Invoices remain on their existing renderer; Interior is unchanged.
- Right-aligned metadata and compact client strip, green table, alternate rows, right-side totals/remainder panel, structured terms and slim contact/thank-you footer. Configured company identity/logo is retained instead of hardcoded sample identity.
- Local **Inter400/500/700** fonts are reused from the repository and embedded in preview/PDF, with OFL included. No Tailwind/Google Fonts/Font Awesome CDN dependency.
- Actual five-row native example: **Rs661,050 total → Rs330,525 requested advance + Rs330,525 remaining**, on **one A4 page**. The reference's different tax logic is intentionally not imported.
- Normal3-row quotation, Rs47,500 example and unchanged invoice each fit1 page.45-row pagination stress spans9 pages with all45 distinct products present, valid bounds/page numbers and repeated quotation header/footer.
- Real desktop/390px and nested cookie-restricted new-layout editing pass without focus loss; optional controls, invoice conversion/issue and Interior regression pass. **JS_ERRORS []**.
- Final Pest: **1,608 passed /1 skipped /8,615 assertions /418.28s**; PHPStan0 / application Pint10 + proof PHP1 / Vite pass. Detailed evidence: [release record](evidence/40_furniture_release_results.md).

The audit/plan below is retained as history; its original decision has been answered. No production deployment.

## Source and actual checks

- Owner attachment: `woodex_a4_quotation_document.html`, approximately37KB.
- SHA-256: `43caf33750ae8a724e2f88cad7b976594c2814ed980082024d9b4706f52acedc`.
- Inspected its markup, embedded CSS and JavaScript, then rendered it in Chromium using the existing local Inter fonts, with external requests blocked. Original attachment was not changed or committed; sample customer/company content is not imported into the application.
- Reference A4 element measured **794 ×1123px**, with five sample rows. Browser screenshot visually inspected. Chromium PDF also generated; this is reference-rendering evidence, **not native dompdf compatibility or a completed implementation test**.
- Its actual JavaScript produced subtotalRs600,000, discountRs30,000, taxRs74,100, deliveryRs15,000, totalRs659,100,75% advanceRs494,325 and remainderRs164,775.
- Ran the existing native `QuoteCalculator` on the same five quantity/rate pairs,5% discount,13% tax andRs15,000 charges: subtotalRs600,000, discountRs30,000, taxableRs585,000, taxRs76,050, total**Rs661,050**. No database or application files were changed by that calculation probe.

Transient reference-only proof: `/tmp/furniture-reference-audit/{reference.png,reference.pdf,check.mjs,native-calculation.php}`. These are not customer documents and are not deployed.

## Visual direction to reuse

- White A4 sheet, clean green accent/wordmark left; large dark QUOTATION and right-side Ref/Date/Valid metadata.
- Compact Prepared for / Location / Attention client strip.
- Green table heading, subtle alternate rows, less visible vertical boxing.
- Compact **right-aligned** financial summary; pale green remainder panel with green top rule.
- Lower terms area with aligned labels, slim green footer divider, contact details left and thank-you message right.
- No amount-in-words block. Keep editable native fields and server-calculated totals rather than the attachment's independent contenteditable editor and floating print controls.

## Differences requiring an explicit boundary

| Attachment | Existing approved/native behaviour | Recommended update |
|---|---|---|
|75% hardcoded advance and payment sentence|50% editable default; amount and wording stay synchronised|Keep editable50% unless owner explicitly revises it; preserve saved explicit percentages|
|13% GST hardcoded; delivery added **after** tax|Editable tax; native engine taxes discounted subtotal **plus** combined rent/transport|Keep native financial engine and configured rate; do not change accounting through template JavaScript|
|Item Code column|Existing `product_name` and `description`, no distinct item-code field in quotation lines|Keep **Product / Description & Specs**; do not mislabel names as codes or fabricate identifiers|
|Separate Delivery & Logistics row|Previously approved combined Rent / Transport display, optional zero rows|Restyle existing combined row; applied amounts remain visible|
|Sample company/address/contact/customer text|Existing company settings, document client overrides and site data|Use actual native fields; do not overwrite shared business identity from sample text|
|Prepared for / Location / Attention|Snapshot already has company/name/address plus quote site|Map existing values with sensible omission/fallback; no invented contact person|
|Fixed warranty/delivery promises|Existing editable terms/delivery/transport text|Restyle saved text; do not introduce a one-year warranty or14–21-day commitment automatically|
|No signature block in the sample|Existing optional editable signatories|Retain optional controls; no hardcoded people|
|Tailwind CDN, Google Fonts, Font Awesome, flex-based layout|Native HTML preview plus server dompdf renderer|Use local assets and PDF-safe layout; verify matching preview/PDF, not a direct browser-only paste|

## Existing code to reuse

- `pdf/furniture-document.blade.php` dispatches historical revision1/2 renderers.
- `pdf/furniture-document-v2.blade.php` is shared by quotations and invoices; replacing it would inadvertently restyle both current and frozen history. **Do not overwrite it.**
- `DocumentStyles`, `FurnitureDocument`, `QuoteSnapshot`, `QuoteDraftPreview` and `QuotePdf` already provide design copying, text/options, live data, preview and PDF routing.
- `QuoteEditor` and line snapshots already carry `product_name`, `description`, quantity, unit and price. No new item-code schema should be inferred from the attachment.
- `QuoteCalculator` remains the financial authority. The attachment's `calculateTotals()` must not become a second calculation engine.

## Recommended implementation slice

1. Add a **new quotation-only Furniture renderer revision**, following the attached design. Preserve revision1/2 templates and all issued/sent snapshots.
2. New Furniture quotations use the new layout. Existing editable drafts upgrade only through explicit **Apply current design**; no bulk rewrite or reset of their commercial values.
3. Preserve invoices and Interior documents. Do not let a shared Furniture design-copy default silently opt invoices into the new quotation renderer.
4. Reuse native company/client/product/commercial data, preview controls, optional rows, terms and signatories; retain editable50% and existing tax/charge calculation unless separately decided otherwise.
5. Use embedded/local fonts and PDF-safe tables/spacing; handle multiple pages rather than the reference's page-break-avoid wrapper clipping long content.
6. Test real native live preview, generated PDF,390px UI, five-row A4 and long descriptions/many rows; assert matching total/advance/terms, editable toggles, historical renderer preservation, invoice/Interior non-regression and explicit draft upgrade.
7. Run focused/full regression, PHPStan, touched-file formatting and build as applicable; inspect actual PDF rasters/page bounds/header/footer and update delivery evidence before commit/push.

**No new tables/columns, schema changes, sample data import, public-v1 change or production deployment.**

## Original decision — approved

**Apply the attached visual design to Furniture quotations only, retaining Product/Description, editable50% advance and existing native financial rules, with invoices and historical documents unchanged?**

Owner answered **“start”**, approving the recommended visual-only scope. Item codes,75%/13% defaults and delivery-after-tax were not approved or implemented.


## Implementation and historical safety

`DocumentStyles::quotationCopy()` stamps revision3 only for quotation creation/duplication and explicit **Apply current design**. Invoice paths retain `copy()` revision2. The dispatch additionally checks the invoice renderer marker, so converted invoices carrying a source quotation's design copy still use the unchanged invoice Blade. Default Furniture green is adapted in the quotation value-copy only; custom colours are preserved and the shared design master is not rewritten.

Existing editable drafts keep their revision on ordinary edits; **Apply current design** explicitly upgrades their layout without resetting commercial inputs, percentages or saved terms. Revision2→3 totals are regression-tested as identical; legacy revision1 retains its existing native upgrade/recalculation behaviour. Sent quotations reject that action. A new regression verifies a sent revision2 snapshot renders exactly the same HTML and retains its stored PDF bytes/SHA after master branding changes.

The first actual PDF raster pass found five-row terms and three-row signatories spilling onto page2. Compacting client metadata/table/summary/terms spacing fixed both without hiding content or shrinking every document to fit. Long documents paginate normally; no `page-break-inside: avoid` wrapper forces the whole document onto one page. The HTML preview is a continuous sheet; PDF pagination and numbering are server-renderer responsibilities, not pixel-identical browser page breaks.

### Verification boundary

The seven new permanent cases cover renderer/default selection, reference tax/advance amounts, explicit upgrade, revision2 HTML/PDF preservation, manual/converted invoice routing, unsaved preview projection and Interior/ordinary-edit non-regression. The prior Furniture polish tests now expect revision3 for **new/upgraded quotations**; their unchanged financial/visibility assertions remain enforced.

Mutating proof uses `/tmp/woodex-furniture-qa/app` with independent environment/vendor/storage/SQLite on33144 and restores **only that QA DB**. Active33143 was checked through non-business-mutating login/new-form/invoice preview iframe flows; it is still the fresh demo, not recovered owner data. Existing active quotations were not bulk upgraded or replaced. Fonts are container conversions of existing local Inter files, not a new frontend framework or network dependency.

No live-MySQL or production-deployment claim. The next step is **owner review of the updated Furniture quotation**, not another implementation approval or an automatic start of broader Slice T.
