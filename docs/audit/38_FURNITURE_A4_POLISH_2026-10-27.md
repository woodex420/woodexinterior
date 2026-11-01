# Furniture A4 polish — 27 October 2026

## Approval and scope

The owner supplied six marked screenshots after the initial Furniture delivery, then approved the proposed
Furniture-only polish with **“start”**. The reference is the supplied quotation PDF, not an audited spreadsheet
formula export. This supersedes the initial centred header / 75% default for **new** Furniture documents.
No new schema, parallel Sales module, Interior redesign, public-v1 change or Slice W work was included.

## Delivered

- Premium A4 letterhead: logo/company/contact left, title/number/date/validity or due date right;
  reference grey/green/lime footer with thank-you message. Print header/footer repeat on every PDF page.
- Grid: **Sr / Product / Description / Qty / Unit / Unit price / Total**. No Amount in words section.
- Full-width aligned summary: subtotal, optional discount, combined **Rent / Transport**, applicable tax,
  highlighted total, advance percentage/amount and remaining after advance. No new separate shipping field or
  rounding row. Non-zero historic charges remain included; an old transport amount is explicitly labelled in
  the editor so replacing/removing it is intentional. New v2 quotations calculate exact paisa without rounding.
- **50% editable default** for new quotes and manual invoices. The calculated percentage generates the first
  Terms and conditions sentence: `50% advance payment with work order.` Changing it to 37.5 changes the wording
  and calculation together. Existing explicit template/document percentages are retained, not reset.
- Verified example: **Rs47,500 → Rs23,750 requested advance + Rs23,750 remaining**.
- Per-document Furniture switches for empty discount/charge/tax rows, advance summary, terms, notes, delivery,
  transport and signatories. Applied financial amounts always remain disclosed even if their empty-row switch
  is off; these switches never change the ledger. Reusable templates preserve these choices.
- Centred signatories initialise from Sales settings, not hardcoded people. Add/edit/remove a line in
  `Name | Title` format; an empty list removes all names, and the section can also be hidden.
- Invoice planned advance/remainder are explicitly separate from verified payments and actual outstanding.
  Requesting an advance is **not** receiving it. Full conversion, issue/freeze, ledger and verified-payment
  workflows remain native and protected against duplicate billing.

## Historical compatibility

The original Furniture Blade is preserved byte-for-byte as `furniture-document-v1.blade.php`.
Snapshots without revision 2 continue using it. New Furniture design copies carry `furniture_revision = 2`;
quote snapshots, invoice commercial copies and issued snapshots carry that decision with them.

Existing editable drafts adopt the new layout only through **Apply current design**. This initialises missing
signatory text without resetting stored percentages, discounts, rent or transport charges. Sent/issued content
is not upgraded or rewritten. New documents from an existing 75% template still request 75%; that is saved
commercial content, not the new fallback default. Interior retains its existing renderer and rounding setting.

## QA and outcomes

| Check | Result |
|---|---|
| Full Pest release gate | **1,577 passed / 1 skipped / 8,418 assertions / 314.78s** |
| Focused Furniture cases | **30 passed / 161 assertions** (initial 20 plus 10 polish regressions) |
| PHPStan | **0 errors** |
| Pint | **16 touched PHP/proof files pass** |
| Vite | Build successful; unchanged public-v1 `site.css` restored; existing large-chunk advisory remains |
| Isolated Chromium33144 | Live product/description/rate, editable advance and matching terms, show/hide terms and signatories, custom names, 390px width, full conversion and issue; **JS_ERRORS []** |
| Cookie-restricted nested iframe | Both blocked-third-party and strips-all-cookies modes pass on isolated33144 **and active33143** |
| Actual A4 PDFs | Quotation **1 page**, invoice **1 page**, Rs47,500 example **1 page**, 45-row long-description stress **8 pages** |
| PDF checks | Embedded DM Sans, text bounds, every row present, page numbering, repeated letterheads/footers; raster proofs visually reviewed |

Real PDF raster inspection caught an initial header/client overlap. Extra reserved letterhead space fixed it;
terms now stay together instead of leaving a single trailing term with signatories on a fresh page. The final
three normal documents remain one page; the stress document paginates without shrinking or clipping content.
HTML previews are continuous sheets; PDF page breaks remain the PDF engine's responsibility.

The mutating Chromium fixture runs **only** against `/tmp/woodex-furniture-qa/app`, with its own SQLite file,
and restores only that isolated file. The isolated server was stopped after replay. Active33143 remains running
with **6 quotations / 26 contacts / 0 invoices / 0 Furniture QA contacts** as checked after QA; the owner's
Furniture draft was not edited or replaced. No stale backup was restored over the active database.

Repeatable sources: `platform/tests/Feature/Sales/FurniturePolishTest.php`, existing Furniture tests,
`tools/sandbox/furniture-documents-check.mjs`, `furniture-proof.php`, `furniture-iframe-check.mjs`.
The fixture now exports the Rs47,500 PDF and longer numbered stress descriptions; the browser proof verifies
real editable percentages, optional sections and both builders' signatories, not just static text.

Transient evidence (not committed): `/tmp/qa/furniture-polish-{focused,release-gate,stan,build,browser,iframe,primary-iframe,pint-final}.log`,
`/tmp/qa/furniture-polish-pdf.json`, `/tmp/woodex-run/shots/furniture-*-polish.png` and generated PDFs.

## Review / boundaries

Review **Sales → Quotations → New → Furniture**, or **Invoices → New → Furniture** on33143.
An existing draft needs explicit Apply current design; frozen historical documents intentionally retain their old
appearance. No production deployment or MySQL runtime proof is claimed. The linked spreadsheet remains inaccessible;
its formulas were not inspected. Production customer/bank/credential data and generated proof files are not committed.
**W stays paused. Next decision: owner accepts the Furniture design.**
