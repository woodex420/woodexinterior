# 42 — Approved Interior integration and Furniture quotation polish

**Date:** 27 October 2026
**Status:** implemented and verified; no schema change or production deployment.

## Owner decision and scope

After reviewing the standalone Interior screenshot in the private GitHub branch, the owner explicitly said **“good update now”** and requested Furniture polish: logo/header, **Prepared For / Project Details**, a better footer above terms, and bottom show/hide controls for the four commercial conditions and the two named signatories.

This supersedes report41's preview-only approval gate. The source comparison/recommendation is in [report41](41_INTERIOR_HTML_REFERENCE_AUDIT_2026-10-27.md); the standalone preview remains archived as an earlier, non-native artifact. No unrelated T/U schema, integrations or public-v1 work was included.

## Delivered

### Interior — new quotation-only revision2

- New `interior-quotation-v2` renderer for **Interior Classic BOQ** only. Navy/ochre, source W mark, locally embedded Plus Jakarta Sans/Space Grotesk, quotation/BOQ metadata, paired recipient/project cards, summary with actual section notes, dark native net-total band and six-column detailed BOQ.
- Summary sections, BOQ subtotal and net total use the existing snapshot/calculator. The12-row native reference is **Rs1,488,750**; neither erroneous source aggregate is imported. Optional items remain labelled/excluded from subtotal; tax/discount/charges/rounding remain disclosed.
- No invented project code, physical covered area or phase-progress status. Available area is honestly labelled **Measured BOQ area**; section notes are **Phase / Notes**. Multi-section BOQ row numbers preserve section membership; section headers still print if the summary is hidden.
- Current commercial blocks, ordering/visibility, amount-in-words, payment schedules, terms, notes, payment details, client overrides and signatories remain native. Acceptance does not unnecessarily repeat the client name already in Prepared For.
- Fixed PDF letterhead/footer repeat safely; actual page counts are added by the existing native PDF service. Existing Single / Project layouts stay on the prior source renderer, and all colour themes remain available.

### Furniture — new quotation-only revision4

- Improved W mark in Furniture green, balanced one-line brand lockup and metadata, with custom uploaded logos retained instead of overwritten.
- Replaced the unboxed three-column client strip with paired **Prepared for / Project details** cards. Company/client attention, real contact/location, project title/type and PO reference remain native and escaped.
- Contact/thank-you strip now sits **above Terms and conditions**. A slim repeated physical page footer retains brand/reference and native page numbers.
- The four requested conditions remain at the bottom after pricing, followed by centred editable signatories. **50%** remains the editable new-quotation default; the payment sentence and amount derive from the same saved percentage, including37.5% tests. Existing percentages are not reset.
- New default additional-terms text contains the three actual conditions, not an obsolete advance-placeholder sentence; the advance sentence is separately generated. Legacy placeholder/percentage filtering is retained so older snapshots render as before.
- Existing Sales settings already contain **Imtiaz Ahmad | Director** and **Nabeel Afzal | Marketing Manager**. New quotations reuse those configurable settings; no names are hardcoded into the renderer or silently overwritten on an existing quote.
- **Furniture print options** now live at the bottom of the editor, after terms/notes, and remain present even if terms/signatures are hidden. Show/hide preserves text/names and financial amounts; the automatic advance term is visible beside the additional-terms editor. All prior discount/charges/tax visibility and signatory editing controls remain.

## Frozen history / invoice boundary

- `DocumentStyles::quotationCopy()` alone supplies Interior2/Furniture4. Invoice `copy()` remains unchanged (Furniture invoice revision2). Default marks are frozen into new quotation metadata; original mark/font assets are not overwritten.
- Snapshot dispatch is explicit. Existing Interior snapshots without the new revision keep `quote-v21`; Furniture revisions1/2/**3** keep their original files. New views are additive, not edits to historical renderers/partials.
- New quotations/duplicates adopt current quotation designs. Existing editable drafts opt in via **Apply current design**. Ordinary edits do not silently upgrade them; sent quotes reject design changes. Regression tests compare old HTML, stored PDF bytes and SHA-256.
- Invoice HTML/PDF, full Furniture conversion, issue/journal workflow and receipts were not redeveloped. Native invoice browser/PDF regression passes.

## Verification

| Check | Result |
|---|---|
| Final full Pest suite | **1,620 passed /1 skipped /8,704 assertions /461.13s** |
| Focused quotation/preview/history/legacy suites | **74 passed /434 assertions /26.43s** |
| New permanent coverage |6 Interior reference +6 Furniture polish cases |
| PHPStan |0 errors |
| Pint |11 touched PHP/test/proof files pass |
| Vite |Build passes; existing large-chunk warning only; generated tracked public site CSS restored |
| Native Interior reference |12 rows, correct3 totals, signatures, **1 A4 page** |
| Native Interior with configured commercial blocks |**2 A4 pages**, payment schedule/terms/signatories retained |
| Native Interior55-row stress |**5 A4 pages**, all55 work descriptions retained; actual native-calculated Rs5,500 total |
| Native Furniture five-row/signatory reference |**1 A4 page**, Rs600,000 total /Rs300,000 advance/remainder; four terms and both names retained |
| Other native Furniture PDFs |Tax/transport reference, normal3-row sample, Rs47,500 and invoice all1 page;45-row stress8 pages with all45 products retained |
| PDF geometry/fonts |All text spans inside A4; embedded local subsets; Page N of M on every page; repeated header/footer checked |
| Native browser proof |Interior live description/rate/summary, typing focus; Furniture cards/footer order, hydrated signatory fields, hide/show/restore,37.5% sentence,390px no document overflow; zero JS errors |
| Broader Furniture/invoice browser regression |Product/spec/rate/advance/options, invoice live preview,390px, full conversion and issue pass; zero JS errors |
| Nested restricted-cookie framing |New Interior2/Furniture4 on isolated33144 pass blocked-third-party-cookie **and** stripped-cookie cases; primary33143 login/new-form/invoice framing also passes |
| Data cleanup |Both primary and isolated SQLite have0 remaining proof contacts and0 seeded bank accounts; isolated business fixtures restored only in isolated DB |

The sole full-suite skip remains the live MySQL/php-wasm network probe. No production/MySQL proof is claimed.

Exploratory QA rejected real A4 overflow, a two-line Furniture lockup/header collision and a signature-only extra Interior page. Fixed geometry/spacing and removed only redundant repeated client text; no priced lines or enabled commercial blocks were dropped, and body table type size was not reduced to force the reference onto A4. The first broad gate also exposed four assertions for the previous v2.1 default. Legacy-contract suites now explicitly freeze their original revision, while the current font-routing assertion and new-revision suites cover the approved change. Final full gate is green.

## Owner-facing native outputs

- **[Interior screenshot](evidence/42_interior_quotation.png)** · [native PDF](evidence/42_interior_quotation.pdf)
- **[Furniture screenshot](evidence/42_furniture_quotation.png)** · [native PDF](evidence/42_furniture_quotation.pdf)
- [Interior PDF with commercial blocks retained](evidence/42_interior_commercial_details.pdf)
- [Furniture390px editor proof](evidence/42_furniture_mobile.png)
- [PDF checks](evidence/42_pdf_checks.json) · [browser checks](evidence/42_browser_checks.json) · [release log](evidence/42_release_log.txt)

These are now **actual Laravel/dompdf documents**, unlike the earlier standalone Chromium prototype. Native PDF rasters and live browser screens were inspected. Private customer records and owner bank data were not imported; exported client/site data are isolated QA examples, with the owner-requested company signatories.

The owner reported that direct `e2b.app` URLs are blocked by sandbox traffic authentication. Do not repeat those URLs as anonymously accessible screenshots or expose traffic tokens. Deliver the two screenshot files using normal access to the existing **private GitHub branch**. The live app preview remains33143; old review prototype33145 is superseded and stopped after delivery.

## Reproduction / remaining boundary

- Sync: `sh tools/sandbox/sync.sh`; separate QA: `SANDBOX_APP_DIR=/tmp/woodex-furniture-qa/app sh tools/sandbox/sync.sh`.
- Browser/native export proof: `REFERENCE_IFRAMES=1 node tools/sandbox/quotation-reference-check.mjs` while isolated33144 runs. It rejects primary-port fixture execution and restores only the independent QA SQLite in `finally`.
- Additional invoice regression: `node tools/sandbox/furniture-documents-check.mjs` against33144. Primary framing is read-only: `PORT=33143 node tools/sandbox/furniture-iframe-check.mjs` without fixture IDs.
- Runtime test/build logs: `/tmp/interior-implementation/{full-final,focused-final,stan-final,pint-final,build,browser-final,furniture-regression,iframe-active}.log`.
- No deployment, schema migration, live-data upgrade or blanket rewrite of older drafts. Owner can visually review the current native screenshots and explicitly upgrade an editable draft via **Apply current design**. Large/custom documents may legitimately span more pages.
