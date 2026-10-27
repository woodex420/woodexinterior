# Furniture quotation revision3 — verification

27 October 2026. Owner-approved **design-only** update from `woodex_a4_quotation_document.html`. No schema changes, sample-data import or production deployment.

## Gate

- Final exact-tree full Pest rerun: **1,608 passed /1 skipped /8,615 assertions /418.28s**, exit0. The single skip is the live MySQL probe, unavailable through php-wasm network sockets.
- Focused Furniture documents/polish/reference/live-preview: **42 passed,233 assertions,15.83s**.
- New permanent reference tests: **7**.
- PHPStan: **0 errors**.
- Pint: **10 touched application/test/Blade files +1 proof PHP file pass**.
- Vite: **pass**; existing ApexCharts large-chunk advisory only. Public-v1 site.css was preserved and build output is unchanged.

The first full run found3 failures:2 old assertions for the previous footer wording/default green, and1 genuine missing custom secondary-colour application. Footer/palette assertions now match the approved design; the new header divider honours the configured secondary colour. All financial, invoice-kind, frozen-snapshot and custom-brand assertions remain enforced. Focused and final full reruns pass; the final full rerun is the release gate, not the earlier failing run.

## Actual browser workflow

`PORT=33144 node tools/sandbox/furniture-documents-check.mjs` uses an independent `/tmp/woodex-furniture-qa/app` runtime/database and restores **only that QA SQLite file** in finally. Actual output:

```text
QUOTE LIVE PRODUCT SPEC RATE ADVANCE WITHOUT BLUR PASS
QUOTE EDITABLE ADVANCE TERMS SIGNATORIES SHOW HIDE PASS
QUOTE PHONE NO OVERFLOW
INVOICE LIVE PRODUCT SPEC RATE PREVIEW VISIBLE
INVOICE OPTIONAL SIGNATORIES AND MATCHING TERMS PASS
INVOICE PHONE NO OVERFLOW
CONVERSION OPENS SAME FULL INVOICE; ISSUE BOOKS PASS
INTERIOR V21 REGRESSION PASS
JS_ERRORS []
```

The live new Furniture quote uses revision3,50% by default; changing to37.5 updates the preview amount and payment sentence together. Product/specification/rate typing updates the always-visible side preview without losing focus.390px document-level width fits. Invoice remains on the old layout and verified receipts/outstanding remain separate from planned advance.

## Restricted-cookie framing

`tools/sandbox/furniture-iframe-check.mjs` passes both third-party-cookie-blocked and proxy-strips-all-cookies modes:

- Active33143: login, navigation, new Furniture brand selection and unsaved invoice preview. No active quotation is replaced/upgraded.
- Isolated33144: same checks **plus an actual revision3 draft quotation inside a nested iframe** using `QA_FURNITURE_QUOTE_ID`, live product typing and focus retention. The optional mutating case refuses ports other than33144. Its caller creates/restores only the isolated fixture database.

Both end **`3pc-blocked: OK, no-cookies: OK / IFRAME CHECK OK`**. Active33143 is still the fresh demo runtime, not recovered owner/customer data.

## Generated PDF and HTML inspection

The updated `furniture-proof.php` exports actual application PDFs, not browser-only facsimiles. `40_furniture_pdf_checks.json` records parsed page/font/bounds results.

| Native document | Pages | Verified |
|---|---:|---|
| Five-row reference amounts |1|Rs661,050 total;Rs330,525 advance and remainder; editable50%; no imported75%/tax-engine change|
| Three-row quotation with signatories |1|All products, terms and signatories retained|
| Rs47,500 quotation |1|Rs23,750 advance +Rs23,750 remainder|
|45-row long-description stress |9|All45 distinct numbered products present; repeated letterheads/footers; correct PageXofY; no extracted text outside page bounds|
| Existing-layout Furniture invoice |1|DM Sans/v2 renderer retained, planned advance separate from actual receipts/outstanding|

Revision3 PDFs embed local **Inter Regular/Medium/Bold** plus DejaVu for fallback/page numbers. Invoice fonts remain DM Sans. Actual PDF rasters were visually inspected. The first spacing pass overflowed normal terms/signatories onto page2; compacting spacing fixed this without removing rows/terms or imposing a whole-document shrink-to-fit rule. The stress snapshot is a pagination fixture, not a customer quotation or a financial-total test.

Actual generated HTML preview was also rendered in Chromium:794px sheet, revision3, matching financial text, terms bottom849px before footer top1046px. Its font bytes come from the same local TTFs as the PDF. HTML is continuous; the PDF engine handles page breaks and adds page totals.

Durable demo-only evidence:
- `40_furniture_quotation.pdf` — actual native five-row PDF.
- `40_furniture_quotation.png` — raster of that PDF.
- `40_furniture_preview.png` — actual native HTML preview.
- `40_furniture_pdf_checks.json` — parsed PDF results.

## Historical safety and reproduction

Revision1/2 Blade files and `QuoteCalculator` are unchanged. New quotes/duplicates and explicit draft **Apply current design** use `quotationCopy()` revision3. Ordinary edits do not upgrade old drafts. Sent documents reject upgrade. Tests retain a sent revision2 snapshot's exact HTML and stored PDF bytes/SHA after branding changes. Invoice dispatch explicitly remains v2 even when its source quote carries revision3 metadata.

Use the existing sandbox setup and browser dependencies. Create a fully independent `/tmp/woodex-furniture-qa/app` copy, with separate vendor/autoload, storage, environment and SQLite; start its server on33144. Never symlink an owner's runtime or restore a QA backup over active33143. Run the browser script above; for the nested quote case, create an isolated fixture, pass its actual draft ID through `QA_FURNITURE_QUOTE_ID`, and restore that isolated DB in finally. The reference source itself remains in the user's attachment, not published to Git.

Fonts are lossless container decompressions of existing repository Inter WOFF2 assets; provenance, rebuild instructions and SIL OFL are in `public/fonts/documents/README.md` and `Inter-OFL.txt`. No new application dependency or CDN request.

Live MySQL is not validated by this SQLite/php-wasm environment. Owner review of the updated Furniture quotation is next; broader Slice T work does not start automatically.
