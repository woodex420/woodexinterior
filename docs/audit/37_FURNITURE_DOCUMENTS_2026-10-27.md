# 37 — Furniture quotation and invoice redevelopment

## Approved decision (27 October 2026)

Owner explicitly approved the complete furniture quotation/invoice redevelopment and additive database changes,
using `Quotation-10183.xlsx - B4U-4th (1).pdf` as the reference. Sales → Quotations → New must visibly offer
Woodex Interior and Woodex Furniture. Reuse native Sales, templates, numbering, receipts and accounting;
no parallel sales module. Slice W remains paused. The linked spreadsheet returned Drive 404; the supplied PDF
is readable and is the design authority, not proof that its formulas are correct.

## Audit and build contract

- Native quote/invoice lines currently have only description, not separate product name/specification.
- Furniture reference: green table, grey/green bands, compact header/footer; quantity and unit must be separate.
- Reference subtotal Rs490,000, advance75% means Rs367,500 requested and Rs122,500 remaining after that
  requested advance, **not** its broken Rs1 balance. Never mark a requested advance as received payment.
- Existing document-design editor already supports private PNG/JPEG logo, brand name, accent, header/footer.
  Extend it with safe furniture style options (alignment, secondary colour, default document headings).
- Existing invoice issue path already freezes PDF/snapshot and posts journal entries; reuse it. Full quotation
  conversion must be idempotent and mutually exclusive with milestone billing, including archived records.

## Additive storage approved for this slice

- `quote_items.product_name`, `invoice_items.product_name`: nullable string; existing description is the spec.
- `quotes.shipping_minor`, `quotes.advance_bp`: explicit numeric commercial values. Existing other-charges is rent;
  existing tax, validity, terms and notes stay. Additional furniture text fields use existing quote meta.
- `invoices.rent_minor`, `shipping_minor`, `tax_bp`, `advance_bp`, `commercial` JSON; nullable unique
  `source_quote_id` FK identifies the one full furniture invoice. Existing quote_id retains standard links.
- `document_designs.furniture_style` JSON: safe style defaults copied into snapshots. No new parallel table.
- No production customer, bank or private contact data imported from the reference. Old documents remain frozen.

## Planned proof

New Interior/Furniture selection and template filtering; product/spec round-trip through templates, duplication,
quote snapshots and full invoices; independent live editing preview; rent/shipping/tax/advance arithmetic;
custom branding/alignment/title; full conversion idempotency and milestone exclusion; invoice issue + ledger;
verified payment + receipt + outstanding balance; immutable old PDFs; policies and malformed inputs.
Full Pest, Pint, PHPStan, real Chromium on33143 and PDF raster inspection before delivery.

## Outcome

Implemented and verified in preview; no production deployment is claimed.

### Implemented workflow

1. Quotations → New: explicit Interior / Furniture brand cards, filtered active templates and WF numbering.
2. Furniture drafts: separate product and material/specification, quantity/unit, rate, rent, shipping, tax,
   advance%, delivery, transport/tax notes, PO reference and a safe printed heading. The native unsaved projection
   drives the persistent side preview. Templates and duplication preserve these fields.
3. One shared green/grey Furniture quotation/invoice blade uses copied design identity. Uploaded logo, reference
   mark opt-out, company name, primary/secondary colours, headings and left/centre/right header/footer are configurable.
   The owner's supplied logo is extracted as a public brand asset, not generated. Scope and signatories still print.
4. Sent/viewed/accepted Furniture quotations expose **Create full invoice** to permitted Accounts actors. The
   sent snapshot supplies lines/client/commercial content, optional rows are excluded, and the invoice starts as a
   WFI-numbered draft with **paid=0, balance=grand total**, regardless of requested advance.
5. Quote locking + unique source_quote_id make conversion idempotent. Existing invoices, including archived ones,
   block a second billing method. Both milestone actions and the scheduler exclude full-billing quotations.
   New full billing suppresses automatic advance invoices; historical milestone contracts are not converted silently.
6. The unchanged issue/freezer/ledger and verification/allocation/receipt paths book the sale and confirmed money.
   A 490,000 invoice still owes 490,000 until the 367,500 payment is verified, then owes 122,500.
7. Existing frozen quotation/invoice PDF bytes remain untouched; old snapshots route to their existing renderer.
   Legacy Furniture terms containing the old standard 50% advance no longer contradict new 75% defaults. Custom
   saved Furniture template terms remain editable. Printing “Invoice” as a quotation heading never creates an invoice.

### Final release evidence

- **Pest:** 1,567 passed, 1 skipped, 8,353 assertions, 323.63s; 20 focused Furniture cases included.
- **PHPStan:** zero errors. **Pint:** 38 touched PHP/proof files pass. **Vite build:** successful; public v1 site.css restored.
- Additive migration applied in preview. Disposable SQLite fresh migration + rollback pass; the rollback explicitly
  drops the unique index before its column, and never resets allocated WFI numbers. A regression test covers this.
- Chromium33143: brand selection, new WF draft, product/spec/rate preview while focus remains, correct advance/remainder,
  new Furniture invoice preview beside editing, 390px no horizontal overflow, repeated conversion opens the same
  invoice, issue exposes the existing collection/ledger workflow, Interior v2.1 regression, **zero JS errors**.
- Final repeatable replay uses an **isolated runtime/database on33144**, not the owner's active SQLite file;
  that QA server was stopped afterward. Both third-party-cookies-blocked and proxy-strips-all-cookies scenarios
  pass brand selection and a nested HTML Furniture invoice preview with focus retained.
- PDF engine produced **one-page quotation**, **one-page invoice**, **four-page stress quotation**. DM Sans embedded;
  text bounds, page numbers and repeated footers checked on every page. Raster proofs reviewed. A signature-only
  extra page found during QA was eliminated by compact spacing, not by hiding signatures.
- QA also caught and fixed an Alpine attribute expression on the rent placeholder. The older broad iframe script
  stops at an obsolete unrelated integrations selector (#app-search); the dedicated Furniture harness covers the
  requested nested-frame paths instead. No broader integrations fix or green result is claimed here.

Transient evidence (not committed; may disappear after sandbox reset):
`/tmp/qa/furniture-release-gate.log`, `furniture-stan-final.log`, `furniture-pint-final.log`,
`furniture-build-final.log`, `furniture-migration.log`, `furniture-browser-final.log`,
`furniture-isolated-browser-final.log`, `furniture-isolated-iframe-final.log`, `furniture-pdf-final.json`;
`/tmp/woodex-run/shots/furniture-*`.

Repeatable browser sources: `tools/sandbox/furniture-documents-check.mjs`, `furniture-proof.php`,
`furniture-iframe-check.mjs`. They require the isolated local runtime at `/tmp/woodex-furniture-qa/app` and
its own DB_DATABASE; the mutating proof refuses the active33143 preview. Temporary QA rows are restored only
inside that isolated database. No proof clients, invoices or receipts are retained in the owner preview.

### Boundaries / next decision

- The inaccessible spreadsheet's formulas were not audited; the attached PDF guides visual design only.
- This is a native A4 document, not a pixel/byte-identical spreadsheet export. HTML is continuous; PDF pagination
  remains the PDF engine's responsibility. The dedicated Furniture grid has fixed structure; v2.1 block reordering
  applies to compatibility layouts, not this new sheet.
- Existing issued PDFs remain immutable, so they show their issue-time balance; live outstanding amounts and later
  verified receipts remain in Sales/client payment views and accounting, rather than rewriting the original PDF.
- SQLite preview and rollback verified. Production MySQL/deployment still require their normal deployment review.
- Interior three-layout workflow and public v1 remain. No production bank/customer data, new parallel Sales module,
  Sheets append, off-site backup or W development was included. **W remains paused for the owner's Furniture review.**
