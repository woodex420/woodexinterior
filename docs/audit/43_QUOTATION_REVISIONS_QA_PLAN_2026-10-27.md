# 43 — Quotation revision checklist and implementation plan

**Owner instruction:** complete both quotations, create the todo/QA plan and start implementation. Screenshot shows the staff Document preview modal empty.
**State:** implementation and final QA complete. See [completion report](43_QUOTATION_REVISIONS_IMPLEMENTATION_QA_2026-10-27.md). No production deployment.

## Reproduced source findings

- Modal still embeds the raw PDF URL in an iframe. The always-visible editor HTML pane fixed the earlier plugin problem, but the saved-PDF modal did not: browsers inside a cross-origin iframe can block the native PDF plugin, leaving a blank region with no error handling.
- Preview middleware rewrites absolute self-URLs to relative paths. Clipboard handlers copy those strings verbatim; a copied relative client link is not a shareable absolute URL. Signed customer links and staff-only download URLs must not be confused. External E2B preview hosts additionally require platform traffic authentication; application code must not disable signed links or disclose traffic tokens to work around that restriction.
- Furniture4 puts a contact/thank-you strip directly above terms, contrary to this latest revision. Its commercial block flows immediately after the summary instead of anchoring above the physical page footer.
- Furniture's fixed renderer ignores normal quotation block visibility/order. Its builder hides the common PDF-block card, while document design misleadingly says the controls only affect compatibility layouts.
- Legacy normalizers force BOQ/totals visible. New quotation revisions need configurable visibility without changing the meaning of old frozen snapshots or invoice locks.
- Save-as-template replacement is not scoped to business line: it can overwrite an Interior template with Furniture data. The replacement picker also lists both brands. Template creation/reuse must preserve the brand, blocks and per-template commercial fields.

## Approved work order / todo

- [x] Replace plugin-dependent staff PDF iframe with a lazy, locally bundled PDF.js canvas viewer of the **actual saved PDF bytes**. Include loading, visible errors/retry, all pages, download, close/cancel and mobile/nested-frame proof. Do not send documents or regenerate missing issued files from preview.
- [x] Fix clipboard absolute URL resolution, offer separate signed client-page and PDF links, and test anonymous valid/tampered/expired links. Explain sandbox-only external access limits rather than weaken authentication.
- [x] Add quotation-only Furniture5 / Interior3 with a flexible block policy; retain previous renderers and invoice defaults. All quotation block switches operate on current designs; remove misleading “Always shown” labels from quotation controls. Keep explicit upgrade for old editable drafts.
- [x] Show one consistent PDF-block editor for both brands with click Show/Hide, retained values and safe ordering. Save and reload the same state in design defaults, per-quote overrides, preview and reusable templates.
- [x] Remove the Furniture body contact strip above terms. Keep a compact physical company footer beneath the commercial closing section.
- [x] Format six Furniture commercial rows: Payment, Materials, Delivery commencement, Validity, Delivery time, Transportation. The payment percentage is always derived from the amount calculation; validity from the saved expiry date; extra custom conditions are preserved, not silently discarded.
- [x] Latest requested **75%** becomes the new blank-Furniture default, without rewriting existing50%/custom quotes or template percentages. Use **12 days** (the upper end of the requested7–12-day range) as the new blank-Furniture validity, editable through the existing date field; Interior/default settings and saved dates are unchanged. New Furniture delivery text defaults to **15–20 days**, editable; existing template text wins.
- [x] Anchor visible terms/notes/payment details/signatories immediately above the final page footer. Allow the whole closing group to move to another page if it cannot fit; never overlay priced rows, clip content or repeat terms on every page. Native PDF and HTML preview need matching safe placement logic.
- [x] Add same-brand save/replacement/creation guards and test Furniture templates remain Furniture, Interior remain Interior; preserve configured blocks and commercial fields.
- [x] Permanent regression tests for preview endpoints/bytes, signed sharing, flexible blocks, frozen history, six terms/defaults, custom values and template brand safety.
- [x] Native PDF tests: short1-page, growing rows near closing-group boundary, overflow/multipage, many rows/long descriptions, all text/page numbers/font subsets/bounds. Real browser tests: all PDF pages rendered to canvas, loading/error/retry, modal close/reopen, authenticated and fresh-client sharing, desktop390px/nested restricted-cookie cases, no JS errors.
- [x] Focused + full suite, PHPStan, touched-file formatting, build, concise report and native screenshot evidence. Commit/push only the existing branch; no schema change or production deployment.

## Guardrails

No destructive schema or business-data changes. Do not globally alter historical block normalization or existing font/logo files. Native calculations, saved percentages/dates, discounts/tax/transport, optional lines, owner overrides and frozen stored PDFs remain authoritative. Invoice rendering/accounting remains outside the design change; the shared PDF viewer may improve invoice display without changing bytes.

## Environment recovery

The session's local Git metadata had reverted to the original base while all restored source files matched remote `cc07aeb` byte-for-byte. Fetched the fixed session branch, compared every existing file, restored local branch/index to that remote without overwriting working files, and restored only11 missing committed build assets. `/tmp` runtimes were rebuilt from the existing sandbox tooling and verified. This is a fresh demo, not recovered owner data.
