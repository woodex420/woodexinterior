# Woodex Interior + Furniture — documents, estimates and Accounts implementation PRD

**17 October 2026. Owner-requested master plan, completed before the first implementation batch. Preview-only authorization; not a production release.** Builds on [the approved Accounts plan](18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md), [current safety gates](14-AUTOMATION-COMPLETION-BACKLOG.md) and binding decisions, particularly immutable sent documents and manual milestone invoice drafts.

## 1. Outcomes and delivery boundaries

One coherent Sales/Accounts system for **Woodex Interior** and **Woodex Furniture**:

- Reusable description/specification libraries, catalog rates, section/BOQ templates and document designs are **different masters**, not one overloaded template.
- Estimates, quotations and invoices share each brand's letterhead/typography/footer but remain different business documents. Changing a heading does not turn a quote into an invoice or create a ledger entry.
- Designs and descriptions are stored in the database, edited with permission checks, and copied into document snapshots. Later catalog/template/style changes never rewrite a sent quote or issued invoice/PDF.
- Interior/Project keep WI numbering; Furniture keeps WF numbering. Do not renumber existing documents or import a sample PDF as a real transaction.
- Previews stay **inside the current application window**, with close/download controls; sharing to external providers is an explicit separate action.
- Default architecture remains Laravel/PHP, MySQL/MariaDB on Hostinger, database queue + cron. Docker is a **development/test environment**, not an unauthorized production hosting change.

**First implementation batch defined here:** database-backed description library for both brands, copy-to-builder/rate-book integration, shared brand document-style profiles including local logo/header/footer controls, and authenticated in-window quote/invoice PDF previews. Product-photo rows, structured furniture dimensions, persistent estimate documents, manual invoice builder and the ledger are later acceptance-gated batches, not implied by the first batch's screens.

## 2. Reference WF-10153

The owner's attached PDF is a **two-page Furniture quotation**, visibly numbered **WF-10153**, dated in 2026. It is not evidence of a 2025 payment or opening balance.

Its design requirements are useful: logo/brand and contact header, date/reference block, customer/company/location, sectioned table with serial, short item name, longer specification, product picture, quantity, unit price and amount, then totals and commercial/payment terms. The supplied product descriptions emphasize dimensions, materials, finish, mechanisms/upholstery and delivery details.

The source also illustrates why the new renderer needs tested row wrapping and page breaks: long specifications must not collide with adjacent columns. Do not force letter-size source geometry onto every document without review; A4 remains the existing default, with validated paper presets planned.

The PDF/customer details, phone numbers, commercial rates, bank details and extracted images remain private. No source customer is seeded into public code. Its pictures are **not yet catalog photo assets**; a later private-media import/upload and publication review is required. Do not silently publish reference prices as today's rate book.

## 3. Domain model: six separate reusable layers

| Layer | Data and behavior |
|---|---|
| Description template | Brand, section/category, title, reusable plain-text specification, active status; copied into a line, never evaluated as Blade/PHP/JavaScript |
| Catalog / rate book | SKU/title, category, unit, selling rate/internal cost, brand applicability, finish, effective date/version, description-template reference and eventually approved item media |
| BOQ/quotation template | Service type, brand, ordered sections, starter lines, optional items, standard scope/terms, milestone plan and design selection; copy into a new editable quote |
| Estimate definition | Measurable inputs, units, versioned rate mapping, assumptions, formulas and exclusions; frontend/admin/bot use the same server-side pricing source |
| Document design profile | Brand identity, logo, colors, safe typography, header/footer, validated layout/paper/column presets; separate from commercial values and bank account selection |
| Frozen document | Exact description, price, media bytes/hashes, applicable style/revision, recipient, terms and totals used at issue/send time; immutable snapshot + stored PDF/hash |

Catalog editing changes **future copies only**. Existing drafts require an explicit compare/apply action to adopt updated rates/specs; historical sent copies never follow mutable foreign keys for rendering.

## 4. Description database and user flow

Two brand libraries, each organized into sections. Initial safe starter categories:

- Interior: design scope; site/preliminaries; protection/demolition; civil/partitions; ceilings; electrical/lighting; plumbing; flooring; wall finishes; joinery/kitchen/wardrobes; installation; handover.
- Furniture: desks/tables; executive/task/visitor seating; sofas; storage; wardrobes; beds; reception/conference; upholstery/finish; delivery/assembly.

Users can search/filter by brand and section, create/edit/archive a description and copy it into the quote builder or New Rate Book Item form. Copying must not overwrite quantity/rate/discount/cost or create a catalog price implicitly. Inactive or wrong-brand templates cannot be applied. Free text remains available for project-specific work.

First-batch descriptions use literal text and a bounded length that fits existing catalog storage; no arbitrary templating language. Parameterized descriptions are a later feature: an allowlisted field schema (material/finish/dimensions/warranty), visible required fields, escaped values and a deny-send check for unresolved inputs. Do not insert unresolved placeholders into a client document silently.

## 5. Furniture line specification and pictures — next visual batch

A furniture line should distinguish short item name from specification, unit/quantity and optional dimensions. Keep free text plus validated structured material/finish/upholstery/mechanism/dimension fields where useful; units must be explicit and server conversions tested.

- Use private uploaded JPEG/PNG/WebP assets; verify MIME/decoded image, enforce size/dimension limits, strip unsafe metadata where appropriate and authorize access. SVG, HTML, remote arbitrary URLs and executable attachments are not inline product images.
- Copy the selected image into a content-addressed document asset or freeze bytes/hash. A later catalog image replacement cannot alter a sent quotation, even if a file path is reused.
- Same drawing/image can appear in templates, but copies into documents retain immutable provenance. Photos can be disabled per line/design.
- Furniture table preset follows WF-10153's separate item/specification/image columns without copying client data. Repeated headers, fixed image bounds, text wrapping, long words and multi-page rows require real PDF/browser tests.
- No claim that photos establish inventory availability, brand authenticity or manufacturing specifications; staff must approve the actual offer.

## 6. Brand document designer

**Shared identity per brand** across estimates, quotes and invoices. Interior and turnkey Project use the Interior family; Furniture uses Furniture. Distinct document titles/statuses remain obvious.

First batch: database profiles, restrained accent color, visible brand/header text, footer text, local raster-logo upload, preview and revision metadata. Preserve defaults for existing legacy snapshots. Frozen snapshots embed the actual profile values/logo bytes; historical output does not read the current profile.

Advanced batch: named versioned layouts; cover/title block; A4/Letter presets; portrait/landscape where tested; safe font allowlist; validated margins; optional column/section/image visibility; signatures; terms pagination; approved watermark/status stamps; language/RTL evaluation. No raw PHP, arbitrary Blade, arbitrary CSS/JS, filesystem path or remote fetch input. Logos are branding, not payment-account selectors.

Staff can preview sample/live draft output in an app modal. Preview is not send, issue, accept or publish. Save/publish design permissions and audit trail must be explicit. New style changes cannot automatically reissue old PDFs.

## 7. Estimation master plan: one pricing engine, many service templates

The repository already has `EstimatorRate` for frontend pricing and `QuoteCalculator` for exact quote totals. The new estimate system must extend/reconcile those sources, not introduce a second browser-side calculator with contradictory rates.

| Service family | Inputs / output sections |
|---|---|
| Interior design | Residential/commercial, apartment/office, area/unit, concept/layout/3D/working drawings/site visits; scope and design deliverables |
| Kitchens / wardrobes | Measured dimensions, unit standard, board/material, hardware, finish, accessories, installation; measured take-off and allowances |
| Fit out | Turnkey/commercial/office/clinic; site works, partitions, ceilings, floors, MEP, joinery, fixtures, delivery/handover |
| Renovation | Office/commercial/restaurant/house/refurbishment; survey, protection, demolition, making good, material/labor, access/waste/contingency |
| Architecture | Kanal/marla/square-foot inputs with explicit conversion assumptions; 10-marla/1-kanal variants, front elevation, drawings, approvals/visits and master planning |
| Furniture | Item quantities/dimensions/material/finish/upholstery/hardware; delivery/assembly and project-specific exclusions |

Each estimate version records measurements, quantity units, rate source/version/effective date, finish level, assumptions, exclusions, contingency/markup and uncertainty range. No invented final quantity when a survey is missing. Rates require owner review before going live. A frontend indicative range remains **not a binding quotation**.

Flow: frontend/admin inputs → shared validated server engine → saved versioned estimate → staff review/compare → explicit quote draft conversion → normal approval/send pipeline. Conversion is idempotent; later estimate edits do not mutate the converted quote. Capture enquiries only through `CaptureLead`; preserve consent/STOP and do not create financial posting from estimates.

The supplied old Arena service-page URL returned a preview-unavailable gate during research. Treat the owner's service list and repository CMS/service data as the working inventory, not a claim that the inaccessible old preview was fully audited. Keep existing public URLs/SEO intact.

## 8. Advanced quotation templates

Add brand/service filters, blueprint editing, copy/duplicate, archived/version history, required-input summary, compare and in-window preview. Templates carry sections, notes, rate placeholders and terms, not customer records or stored approvals. Saving a quote as a template must remove recipient/acceptance/payment data and frozen identifiers.

Defaults must cover Interior design, commercial/office/apartment interiors, kitchens, wardrobes, fit out/turnkey/clinic, renovation/refurbishment/restaurant, architecture/1-kanal/10-marla/elevation/master planning and Furniture suites. Public generic starter content is not a priced promise; no quote is sendable until required quantities/rates and terms are complete.

## 9. Invoice builder and accounting

Manual invoice creation is an explicit missing workflow, not solved by renaming a quote. Build draft-only manual and project/milestone invoices with sections/items, catalog/description reuse and the same brand design. Server-side totals, correct business-line bank selection, required evidence, approval/issue/frozen PDF, delivery and credit/reversal controls must be separate actions.

Keep one invoice per milestone, safe numbering and manual milestone drafts. “Verify payment” remains Accounts confirmation of actual receipt, not a model/AI judgment from an uploaded image. Pending proof cannot mark an invoice paid. Manual invoices do not silently create a project or accepted quote.

The approved ledger/Accounts plan remains in force: chart, periods, balanced immutable journals/reversal and source idempotency first; then receivables/invoice/receipt integration, suppliers/payables/expenses/outgoing payments, recurring drafts and reconciled reports. Revenue recognition for deposits, tax/entity/fiscal policy and 2025 financial evidence remain Accounts gates. The reference PDF is not a substitute for those files.

## 10. Runtime and test-engine setup

Native PHP 8.4 + PDO MySQL + MySQL/MariaDB + separate worker processes are needed for real lock tests. This Debian sandbox has passwordless sudo but no installed PHP/MySQL/Docker binaries or cached OS packages. Network downloads are restricted to npm/PyPI hosts; those registries mostly offer drivers/SDKs/download wrappers, not a verified complete OS/Docker engine. **Installing a Python Docker client is not installing Docker Engine.**

Provide a reproducible Docker Compose QA setup for an unrestricted development machine. Its PHP/MySQL definitions are **prepared, not executed/certified here**. No production database/credentials are mounted into tests; a generated local env file holds disposable test credentials. Services are not exposed publicly. Tests must refuse non-test database names and destructive setup against real business data.

Existing PHP-WASM/SQLite stays usable for local preview and regressions. Keep the preview's private env/data and 2025 imports. Isolate QA copies so test view-cache clearing or database restoration cannot overwrite ongoing preview edits. Real MySQL acceptance remains unchecked until actually executed.

## 11. In-window preview and frozen-file safety

Use authenticated same-origin PDF routes in a modal with an accessible close button, loading/fallback state and separate download link. Never navigate the main app away merely to preview. Do not use `target=_blank` for app document previews. Explicit external WhatsApp/provider sharing may still open the relevant provider.

Fix legacy staff-preview pitfalls: default PDF for a sent quote should select its stored frozen version, not render a live document with new branding; a missing issued invoice PDF must fail for reviewed repair rather than regenerate silently from mutable data. Existing version-specific links stay exact. No preview action creates/sends/verifies financial records.

## 12. Delivery sequence and definitions of done

| Batch | Scope | Exit gate |
|---|---|---|
| T01 — start now | Description DB/library, safe copy into builder/rate book; shared style profiles/local logo/header/footer; in-window PDFs | Role/brand/active/state checks, snapshot immutability, upload validation, live modal/browser + PDF regression tests |
| T02 | Native Docker QA harness + MySQL workers on a capable machine | Actual multi-process races/rollback/deadlock evidence; not merely Compose syntax |
| T03 | Furniture short-name/specification/photo rows and versioned document assets | WF-10153-style multi-page visual review, no overflow, frozen image hashes |
| T04 | Advanced BOQ blueprint editor and version history | Required-input validation, safe copy/compare, correct brand and no personal-data carryover |
| T05 | Persistent estimate definitions/inputs/versions; shared frontend/admin pricing | Unit/range/rounding equivalence, service coverage, reviewed live rates and idempotent quote conversion |
| T06 | Chart, accounting periods, journal post/reversal and permissions | Balanced, immutable, idempotent, closed-period/permission/MySQL tests |
| T07 | Manual invoice builder + receivables/verified receipt integration | Frozen design/totals, no duplicate ledger/revenue, Accounts UAT |
| T08 | Suppliers/payables, expenses, outgoing payments and recurrence drafts | Partial settlements, correct balances, human approval and bank reconciliation |
| T09 | Historical finance staging and reports | Real files, reconciled opening/closing balances and owner/Accounts sign-off |
| T10 | Advanced layout variants, accessibility/locales and rollout | Full document/brand/device matrix, production smoke and restore rehearsal in a new release session |

The first batch must not advertise the remaining batches as working. Production publication is not possible from this closed/merged coding session; all work remains local/preview until a new release session.

## 13. Acceptance matrix

Test both brands and Interior/Project fallback; draft/approved/pending/sent/viewed/accepted/expired states; fresh/revoked/inactive users; inactive/wrong-brand templates; malformed logo, SVG/HTML/oversized image; escaped header/footer; long text; missing frozen file; catalog/template/profile edits after send; retained old PDF/logo hash; draft versus frozen revision selection; no external window from preview; 360/390px and desktop; JS-disabled fallback; no auto-send/issue/verify from preview.

Money stays integer paisa, quantity decimal-string, units explicit, totals through the established calculator. Structured description/media additions must participate in approval fingerprints before important sends. Real MySQL tests also cover related contact/company/design/catalog writers and lock-order consistency, not just a single quote row.

## 14. Research and GitHub links

These are references, not installed dependencies or copied ERP code:

- ERPNext quotation documentation describes item defaults, rates, terms, alternative items and reviewing print format/letterhead/images before sharing. Useful separation of data masters from document presentation. [Quotation documentation](https://docs.frappe.io/erpnext/user/manual/en/quotation)
- ERPNext repository: <https://github.com/frappe/erpnext>. Keep it as a workflow reference, not a replacement for the approved Laravel/Hostinger architecture.
- Laravel Invoices offers customizable PDF templates and streamed/stored output; the repository identifies GPL-3.0-only, so licensing/compatibility must be reviewed before adopting code. Existing dompdf remains the first implementation renderer. [1](https://github.com/LaravelDaily/laravel-invoices)
- Invoice Ninja is a Laravel invoicing/quote reference, but its public licensing explanation describes source-available Elastic licensing. Do not assume unrestricted code/branding reuse. [10](https://www.invoiceninja.org/) · <https://github.com/invoiceninja/invoiceninja>
- A Frappe price-estimation reference exists, but it is a separate framework and not verified as a production-ready Woodex module. <https://github.com/frappe/erpnext_price_estimation>

No external project was installed, copied wholesale, connected to private data or represented as already integrated.

## 15. First-batch implementation evidence — 17 October 2026

**Implemented locally and applied to the data-preserving 8080 preview, not production:**

- Two indexed masters: `description_templates` (12 editable safe generic starters across both brands) and `document_designs` (Interior and Furniture). Project uses the Interior design family. Description text is capped at 500 characters to fit the current catalog column; no silent truncation or fabricated commercial rates.
- Sales → Description Library: search, brand/section filters, paginated cards, create/edit/archive. Active descriptions copy into a new unpriced quote section line or the New Rate Book Item form. Existing quantities/prices/specifications are unchanged. Wrong-brand/inactive copies fail. Catalog editing permission controls library writes.
- Sales → Document Design: Settings-managed brand name, accent, header/tagline, footer and validated raster-logo upload/removal. Logo limits: JPEG/PNG, 96 KB, 600 × 240 pixels. Logo bytes/hash are stored in the database and copied into quote metadata/sent snapshots; no remote image fetch or public storage link is needed.
- New/duplicated quotes receive a value copy of the current brand design. Existing drafts keep the old/legacy copy until staff explicitly choose **Apply current design**. That uses the parent quote edit lock and is included in the approval fingerprint. Existing pre-change pending approvals may need withdrawal/re-request; do not silently upgrade or reuse an older fingerprint. Subsequent master edits do not invalidate a quote that has not explicitly adopted the new style.
- Quote and invoice PDFs consume copied styles, with legacy fallback for old snapshots. Quote-derived invoices inherit the latest frozen sent quote's design, not an unissued draft revision's style. The existing advance invoice creation/freeze flow was verified in tests and isolated browser QA; **no manual invoice builder or ledger was added**.
- PDF links in the quote builder, sent-version list, quote invoice cards and invoice list now open in a same-window app overlay with close/download controls and keyboard focus trapping. No preview button sends, issues or verifies a payment. A sent quote's default staff PDF is now its frozen version; a missing issued invoice PDF fails closed for reviewed repair.

**Evidence:** full Pest suite **744 passed, 4,500 assertions, one existing sandbox skip**, 259.11s (`/tmp/documents-full-tests.log`), including **30 new document tests /78 assertions**. Larastan level 6: no errors; Pint22 PHP paths: pass; Vite: pass (existing chart-size warning). Shell syntax and MySQL PHPUnit XML structure checked. Docker/Compose/native MySQL execution remains **not run**.

A separate QA copy was used for all tests and browser writes. Real-browser description creation/copy, footer/logo persistence, brand switching and quote/invoice in-window PDF checks passed, with no JavaScript errors and no overflow at 1440/390/360px. A QA-only advance invoice was created/frozen and its PDF hash verified; it was not copied back into the owner's preview. Cross-site iframe checks passed for both third-party-cookie-blocked and no-cookie scenarios. The refreshed8080 preview also passed an authenticated read-only new-screen/quote-modal smoke (PDF200, no new tab, zero JavaScript errors,1440/390/360px) and a fresh iframe check (`/tmp/documents-preview-final.log`, `/tmp/documents-preview-iframe.log`). A locally rasterized QA draft proof contained the embedded raster logo/custom footer with no visible header/body collision; photo-row/multi-page acceptance remains outstanding.

The legacy broad browser script is **not fully green**: its homepage `#lf-name` lead-form selectors do not match the currently served homepage, both before and after this batch. Its admin layout, quote blur-saving, approval/send/client PDF/acceptance checks passed in the isolated copy. The public website was not changed to mask that pre-existing mismatch; homepage-form smoke reconciliation remains a separate acceptance gate.

Before applying the additive migration to 8080, stopped the server and made a private SQLite backup. **All pre-existing rows across 66 checked tables, and the private env file, remained unchanged**; only migration bookkeeping and the two new library tables were added. No reset/reseed/reimport or financial record creation occurred in the owner's preview. QA vendor/storage/database/cache and preview cookie-memory files were isolated.

### Remaining work — not implied by the new screens

Product-photo columns and immutable line-media assets; separate furniture item-name/dimension fields; arbitrary long/parameterized description templates; advanced paper/column/layout variants; persistent estimate documents and frontend conversions; manual invoice builder; chart/journal/periods/Accounts modules; native MySQL multi-worker races, financial source files, Accounts UAT and production rollout. T01 is a locally tested vertical slice, not certification of the full advanced ERP or every browser gate.
