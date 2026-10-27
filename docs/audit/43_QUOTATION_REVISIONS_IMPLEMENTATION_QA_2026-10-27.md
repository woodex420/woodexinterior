# 43 — Quotation PDF viewer, sharing and commercial-block revision

**Owner approval:** create the TODO plan and start complete native implementation for both brands.
**Status:** implemented; final release verification recorded below. No production deployment, migration or owner-data rewrite.

Plan: [revision checklist](43_QUOTATION_REVISIONS_QA_PLAN_2026-10-27.md). This revision supersedes report42's Furniture50% **new-blank default** and above-terms contact strip, not previously saved financial values or frozen documents.

## Delivered

### Real PDF viewing and client sharing

- Replaced the saved-PDF modal's plugin iframe with locally bundled, lazy-loaded **PDF.js6.4.299**. It fetches the native PDF endpoint and renders its actual bytes to canvases, with all pages, loading status, visible failure/retry, close/cancellation, downloadable original bytes and390px viewport support. No external PDF service or CDN receives documents.
- Kept PDF.js objects outside Alpine's reactive proxies; fixed document-worker cleanup using the loading task's lifecycle. Reopening, cancellation, failed requests and multi-page rendering were exercised in Chromium. Added the worker's JavaScript MIME mapping for Apache/nosniff, plus upstream Apache/Mozilla licence notices.
- Separated **Client page link** and **Client PDF link**. Browser-side resolution supplies the real current origin even when the sandbox proxy rewrites server URLs to relative paths. Clipboard fallback reports failure honestly instead of falsely displaying Copied.
- A genuinely separate anonymous browser was denied the staff quotation route, opened the copied signed client page, fetched the frozen PDF, and **clicked its Download link**. Chromium download-completion events and the resulting file SHA-256 verified the exact frozen bytes. Tampered links remain403; permanent tests additionally cover expired/unsigned links, draft-only access and missing stored files.
- Corrected the current client portal's frozen brand/logo, Furniture advance/remainder, six commercial rows, combined Rent/Transport including shipping, and previous-adjustment label. Furniture5 no longer uses Interior Studio identity, obsolete milestone percentages or amount-in-words.

### Both quotation designs and Show / Hide

- Added quotation-only **Furniture5** and **Interior3** with a flexible block policy. Quote-level and master-design controls use explicit click **Show / Hide**. BOQ and totals can genuinely be hidden without changing the calculator or saved amounts. Both native HTML and PDF consume the same block state.
- Furniture's duplicate whole-Terms / Notes / Signatories switches are replaced by the common PDF-block controls. Furniture-specific detail switches remain for advance/remainder, delivery/transport notes and optional zero-value financial rows. Nonzero discounts, tax and charges remain disclosed whenever the commercial amounts block is visible.
- Furniture groups commercial amounts and closing content above the final footer. Block arrows order within the main/closing groups rather than moving conditions ahead of the priced table. Interior retains its ordered native blocks.
- Fixed a QA-discovered master-default bug: explicitly selecting **Show for every block** is now honoured. Only a genuinely unset stored master uses Furniture's native hidden Summary/Payment defaults; an explicitly saved all-Show selection is not mistaken for untouched defaults.
- Template creation/replacement is brand-scoped. Furniture cannot overwrite an Interior template or be instantiated through an Interior template. Export/import/reuse retain the template's own business line, presentation blocks and commercial values. Interior/Project compatibility remains as before.

### Furniture closing and six commercial rows

- Removed the body contact strip above Terms. Company identity/contact remains in the proper letterhead and compact physical footer; signatories remain editable and centred.
- New blank Furniture quotations use **75% advance**, **12-day validity** (within the requested7–12-day range), and **15–20 days** delivery. Existing saved/template percentages, dates, delivery text and custom conditions are preserved.
- Six default rows: **Payment; Materials; Delivery commencement; Validity; Delivery time; Transportation**. Payment text uses the actual saved advance percentage; validity prints the actual expiry date. Custom percentages including37.5% agree with both the advance amount and optional payment schedule.
- The native renderer measures the final commercial group, then accepts a bottom spacer only if a second layout stays within the same page count and content boundary. Normal six-term closing blocks stay after advance/remainder and above the footer as the BOQ grows.
- Oversized/custom content flows naturally instead of being clipped. Long conditions use flowing definitions, not unsplittable table cells. A350-repetition single condition retained every token and its end marker across4pages, followed by the signatures.

## History and boundaries

- Earlier Furniture1–4 / Interior2 and older snapshot renderers remain available. Ordinary edits do not silently upgrade old drafts; choose **Apply current design** on an editable legacy quotation to use the new controls/layout. Sent documents retain frozen stored PDF bytes and reject design mutation.
- Invoice rendering, default percentages, ledger calculations and issue/receipt workflow were not redesigned. The common viewer also rendered the native invoice successfully; the invoice fixture retained its legacy50% text.
- No production customer records, bank details, credentials, downloaded source archives or database exports are included. Evidence uses isolated synthetic quotation fixtures and the owner-requested company signatories.
- Application signing cannot remove Arena's own external traffic gate. Anonymous browser proof is against the isolated native application's origin, **not a claim that an E2B preview address is publicly reachable from another device**. Real external clients need the normal publicly accessible deployment host; authentication/signatures were not weakened.

## Verification

| Check | Result |
| --- | --- |
| Final full Pest sweep | **1638passed,1skipped,8823assertions,453.70s** |
| Focused quotation/design/template/history suites | **93passed,548assertions,53.12s** |
| PHPStan refreshed after final application changes | **0errors** |
| Vite production build | **PASS**; lazy PDF engine and local module worker emitted |
| Formatting / patch hygiene | Touched PHP files formatted; `git diff --check` passes |
| Real browser viewer/share/download checks | **18checks PASS**, no recorded JavaScript errors |
| Restricted nested frame | **PASS** with third-party cookies blocked and with all Cookie/Set-Cookie headers removed; live Furniture/Interior and their actual PDF canvases render |
| Native pagination / bounds | **PASS**, matrix below; page counts, unique product rows, all A4 word bounds and final closing positions checked |
| Frozen history / invoice regression | **PASS** in permanent tests, anonymous download checksum and native PDF probes |

Native Furniture matrix:

| Product rows | PDF pages |
| --- | --- |
|1 /5|1|
|10 /14 /16 /18|2|
|22|3|
|45|4|

Across that default matrix: Remaining starts approximately**601.13pt**, Terms**630.20pt**, and the last signatory ends**788.80pt** on the final A4 page, above the footer. Terms occur once. A100-note oversized closing spans3pages; the long single-condition probe spans4pages, without losing custom conditions, end marker or signatures. Large/custom documents legitimately require additional pages; the spacer is not forced onto an oversized group.

The one skipped test is the existing installer database-connectivity failure probe: PHP-WASM lacks the required MySQL driver; it is not a skipped quotation test.

Test-harness correction: Lambda Chromium's single-process mode crashes when opening an extra incognito context. The anonymous proof now uses an independent browser process/profile; it does not reuse staff cookies or sidestep application authorization. Separate Pest suites were not run concurrently against shared fake storage.

## Evidence

- **[Furniture native A4 screenshot](evidence/43-furniture-quotation.png)** · [actual PDF](evidence/43-furniture-quotation.pdf)
- **[Interior native A4 screenshot](evidence/43-interior-quotation.png)** · [actual PDF](evidence/43-interior-quotation.pdf)
- [Actual PDF modal](evidence/43-pdf-modal.png) · [390px modal](evidence/43-pdf-modal-mobile.png)
- [Furniture PDF inside third-party-cookie-restricted frame](evidence/43-furniture-pdf-iframe-3pc-blocked.png)
- [Interior PDF inside completely cookieless frame](evidence/43-interior-pdf-iframe-no-cookies.png)
- [Native PDF results](evidence/43-native-pdf-results.json) · [browser results](evidence/43-browser-result.json) · [release summary](evidence/43-release-summary.json)

## Reproduction / handoff

- Native fixtures: `tools/sandbox/quotation-revision-proof.php` refuses anything except the isolated local QA database under `/tmp/woodex-furniture-qa/app`. Do **not** run against owner/production data. It creates fresh fixtures, so use the newly generated fixture IDs.
- Browser: `node tools/sandbox/quotation-revision-check.mjs` against isolated33144 and the fresh fixture manifest. The anonymous download check uses Chromium's actual browser download events/file, not a simulated anchor.
- Restricted frame: `PORT=33144 QA_FURNITURE_QUOTE_ID=<fresh draft id> QA_INTERIOR_QUOTE_ID=<fresh id> node tools/sandbox/furniture-iframe-check.mjs`.
- Native geometry: run `tools/sandbox/quotation-revision-pdf-check.py` in a Python environment with PyMuPDF, after the native fixture export. Generated exports and downloaded files remain outside Git; only selected synthetic evidence is versioned.
- Refresh the active native preview to load the rebuilt admin bundle. Old editable drafts opt into this version with **Apply current design**; that action does not change their existing advance percentage. New blank Furniture quotes start at75%.
- Existing session branch/PR only. No production deployment or automatic merge.
