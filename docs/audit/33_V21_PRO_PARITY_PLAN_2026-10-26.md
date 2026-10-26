# v2.1 Pro parity plan — 26 October 2026 (Slices O, P, Q)

The owner sent four screenshots from the **old admin (v2.1 Pro, 127.0.0.1:8003)** and the Gauzy page list again,
asking for the missing pages to be developed and existing groups improved. This doc is the page list: what already
exists, what is partial, what is missing — audited against the code, not the screenshots alone. Gauzy stays a
**workflow reference** per the 26 Oct decision (its code is NestJS/Angular; nothing portable into this Laravel OS,
so no clone — the pages it inspired already shipped in Slices I, J, K).

## The Gauzy list — already done, not pending

Contacts / Visitors / Leads / Customers / Clients (Slice J), Proposals / Estimates / Invoices / Payments / Pipelines
(Slice K + earlier), Estimates / Invoices / **Invoices Received** / Income / Expenses / Recurring Expenses / Payments
(Slice I). The only genuinely missing item remains **Estimates Received** (supplier quotations) — no data model; a
schema slice of its own whenever approved.

## The v2.1 Pro screenshots — exists / partial / missing

| v2.1 Pro feature (screenshot) | State in Woodex OS | Verdict |
|---|---|---|
| **PDF blocks: drag order + show/hide** (Scope, Summary of cost, Totals & words, Items/BOQ, Terms, Payment details, Signatures) | Blocks exist in one **fixed order**, always on | **Shipped — Slice O** |
| **PDF design themes** (Classic / Minimal / Premium) | One design; brand name, accent colour, logo, header/footer only (`document_designs`) | **Shipped — Slice O** |
| **Layout & type** (Single page / Project / Classic) | One layout | **Shipped as themes — Slice O** |
| Signed by (Ahmad — Director) | Signatories in sales settings + per-quote list; no per-quote picker in the builder | **Shipped — Slice O** |
| Live preview / Preview-PDF / Send / Saved | PDF download + client portal link + send exist; in-page preview via `x-admin.pdf-preview` | Exists (different shape) |
| **Export / Import template (file)** | Templates are DB records; no file export/import | **Shipped — Slice O** |
| **New invoice** (manual create) | `InvoiceKind::Manual` exists; **no create UI/action** — invoices come from quotes, milestones, recurring | **Shipped — Slice P** |
| Invoice tracking: year filter, WI/WF filter, Classic list | Board groups exist; classic list is the Invoices tab; **no year/business-line filters** | **Shipped — Slice P** |
| **Import sheet / Export (invoices)** | Payments export exists; invoice export/import do not | **Export shipped — Slice P (import still its own decision)** |
| **Connect Facebook Pages** (Page ID + token + test) | WhatsApp has exactly this (real gateway + Meta test); **Facebook has no gateway** — the card still says phase P7 | **Missing — Slice Q** |

## Slice O — Document design system (quotes + invoices) · ✅ **shipped 26 Oct 2026**

Extend `document_designs` (per business line) with `pdf_theme` (`classic|minimal|premium`) and a `blocks` JSON
(order + visibility) for the quote blocks — scope, summary, totals, boq, terms, payment, signatures — and the
invoice subset. `DocumentStyles::copy()` already freezes the design into each new quote (and invoice snapshots
inherit it), so sent documents never change under a client. `pdf/quote.blade.php` and `pdf/invoice.blade.php`
render blocks in the stored order, skipping hidden ones; themes restyle via the shared
`pdf/partials/document-style.blade.php`. The **Document design** screen gains the theme picker, the block list
with up/down arrows and on/off toggles, and reset — the v2.1 panel, on the OS's real snapshot pipeline. Plus:
quote-template **export/import as JSON files**, and a *Signed by* picker in the builder (writing the existing
per-quote signatories). **Schema:** two nullable columns on `document_designs` — needs approval.
Acceptance: reorder/hide blocks → a new quote's PDF follows exactly; a previously sent quote's PDF is byte-identical.

## Slice P — Invoice completeness · ✅ **shipped 26 Oct 2026**

A **New invoice** flow (manual kind): pick client, lines, tax, due date → posts to the ledger exactly like a
milestone invoice (the freezer/snapshot path), numbered by the existing numbering. Tracking board gains the
**year** and **Interior/Furniture** filters from the screenshot. **Export** the invoice list/board to CSV.
*Import sheet* is deliberately split out: bulk-creating accounting documents from a spreadsheet is a money-safety
decision (duplicates, wrong clients, broken totals) and gets its own yes/no when O and P are in.

## Slice Q — Facebook Pages connect (then publish)

In the WhatsApp house pattern: credentials on `IntegrationSettings` (Page ID + Page access token, encrypted,
never shown back), a Graph API gateway + fake, a **Connect / Test** action that asks Meta what the token belongs
to (`GET /{page-id}` name/category) and remembers it, and the Integrations card flipping from "phase P7" to a real
state with the integration log behind it. Publishing Social Planner posts to the Page (`POST /{page-id}/feed`) is
Q2, once connect is proven with the owner's real token. **Blocked for live proof until the owner provides a Page
ID + token** — the build itself is not blocked; every state is testable against the fake, and the card will say
"not connected" honestly until then.

## Order and why

**O ✅ → P ✅ → Q** (owner's picks, 26 Oct). O shipped first — 100 % provable (PDF output changes on command,
sent documents frozen). The owner then put **P ahead of Q** — manual invoices were unblocked work, and P
shipped the same day. Q is next: buildable today against the fake, live-provable the moment a Page ID +
token is pasted.
