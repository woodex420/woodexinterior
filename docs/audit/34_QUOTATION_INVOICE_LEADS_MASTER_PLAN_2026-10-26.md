# Master plan 34 — Quotation · Invoice · Leads · Estimations · Marketing (26 Oct 2026)

**Owner brief (26 Oct):** improve the complete quotation, invoice and lead systems; **match the quotation
design with the attached PDFs** with PDF blocks *drag to reorder · switch to show*; quotation types; add the
**Woodex Furniture** quotation/invoice design and tracking; analyse old admin v2.1 Pro completely and **report
what is missing**; redevelop the leads page (side panel, organised, polished); convert **Estimator →
Estimations** completely (planned and developed natively in Woodex OS, not copied from v2.1); Marketing next.

**How this was analysed (honest):** the old admin runs on the owner's `127.0.0.1:8000` — unreachable from this
sandbox, so nothing was scraped from it. Sources are the owner's **10 screenshots + 2 PDFs**, both PDFs
rendered page-by-page and text-extracted, **our own current quotation PDF rendered from the preview**
(WI-10100, 3 pages) for a true side-by-side, and a code audit of every screen named below. Bank account
numbers visible in the screenshots are deliberately **not transcribed** into this document.

---

## 1. The two designs, side by side

### Interior quotation — v2.1 "Classic BOQ" (attached WI-10101) vs Woodex OS today

| Element | v2.1 Classic BOQ | Woodex OS today | Gap |
|---|---|---|---|
| Header | W logo mark + "WOODEX INTERIOR · Design · Build · Furniture"; **QUOTATION.** right, no/date/valid under it; gold+navy double rule | Text brand + tagline, no logo mark printed in the demo; QUOTATION left | Logo position/title layout |
| Client/project | Two cream boxes: **PREPARED FOR** and **PROJECT DETAILS** | One PREPARED FOR box; title printed under the number | Project-details box missing |
| **Summary of cost** | First-page rollup: SR / PARTICULARS / **TOTAL AREA (SFT)** / **PER SFT (RS)** / AMOUNT, then TOTAL row | **Does not exist** — the client meets the full BOQ immediately | **Biggest structural gap** |
| Amount in words | Dashed "AMOUNT IN WORDS" box + navy **TOTAL (Rs)** pill with gold figure | Italic words line + navy total bar at the end | Move + restyle |
| Detailed BOQ | **One table per section**, SR numbered **1.1 / 1.2 / 2.1**, "Total · Section" rows, QTY/AREA + UNIT + RATE (RS) | One continuous table, sections as band rows, SR flat 1…42, "Subtotal · Section" | Restructure to per-section tables |
| Money format | Plain `50,000`, zero lines print **–** | `Rs 50,000`, zero lines print **Rs 0**; qty shows `121.125` | Zero-as-dash + qty trimming |
| Terms | Numbered list | Numbered list ✅ | — |
| Payment schedule | Inside terms text | Real milestone table ✅ (**we are better — keep**) | — |
| Payment details | Two boxes: **Bank** (title/no/IBAN/branch **+ branch code**) and **Mobile wallets** (JazzCash/Easypaisa numbers) | One account list; branch prints, **branch code and wallets do not** (columns exist in DB: `branch_code`, `kind`, `wallet_provider`) | Split banks vs wallets, print branch code |
| Signatures | Signatory + **Accepted by client** (name · signature · date · stamp) | Two signatories; client acceptance is portal-only | Add paper acceptance block |
| Footer | Phone \| email \| address · "Thank you for your business" + navy/gold angled band | Footer rule: brand · number · validity · page x of y | Contact strip + thank-you |

### Furniture quotation — attached WF-10185 (Excel) vs Woodex OS today

The attachment is the team's current **Excel template** (green logo, "WoodEx Interior"), i.e. the *workflow*
to absorb, not a design to copy: **Item name + long specification** as two columns; **SUBTOTAL / Rent /
Advance % / SHIPPING/HANDLING / Balance Due** money block; bottom notes **Validity / Delivery time /
Transportation / Taxes**. (Its Balance Due even computes to "Rs: 1" on a Rs 490,000 quote — a broken Excel
formula, and exactly why documents must be computed server-side.) Woodex OS today renders furniture quotes with
the generic quote layout: no rent/shipping/balance-due block, no delivery-time line, one
`other_charges_label` only. **Plan:** a Furniture variant of the document layout carrying these fields, in the
same brand design as the interior Classic BOQ (owner to confirm the furniture header should mirror interior,
with WOODEX FURNITURE branding).

---

## 2. Gap matrix by module (code-audited)

### Quotation builder + templates (screenshots 2, 4, 5)

| v2.1 feature | Woodex OS today | Verdict |
|---|---|---|
| **Live preview** beside the form, updating while typing ("Classic BOQ" label) | "Preview PDF" button → modal; no always-on preview | **Missing — Slice S** |
| **PDF BLOCKS panel in the builder** (drag/↑↓ + show/hide per quotation, Reset order) | Per-**brand** blocks in Document design (Slice O); no per-quote override | **Partial — Slice S** |
| PDF design Classic/Minimal/Premium | ✅ Slice O (per brand) | Exists |
| Builder fields: client name/phone/email/company/address inline, Project, **Type**, Site, Date, **Intro line on the summary page** | Quote is built from a linked contact; has title, terms, notes, scope; **no type, no site, no intro line**, no inline client edit | **Missing fields — Slice S** |
| Rent / transport (Rs), Advance (Rs) quick fields | `other_charges` (one labelled charge) + milestone plan (advance %) | Partial — Slice R/S |
| Send / Saved states, Preview-PDF file modal (File name · A4 · Print · Download) | Send dialog + pdf-preview modal exist ✅ | Exists (polish parity only) |
| Quotation **types**: Renovation, Civil Work, Interior Designing, Interior, Fit-out, Architecture | Template `kind` = execution/design/scope; **no work-category type on quote or template** | **Missing — Slice T** |
| Template cards: type tag, description, **section chips**, "50% advance…", **total (Rs lac)**, Use/Edit/Delete, **New template**, Import from file | Card grid ✅ with description + Use/Edit; import ✅ (Slice O); **no chips/total/advance line, no blank "New template"** (save-from-quote only) | Partial — Slice T |
| Company & numbering (company, consultant signature line, NTN, banks w/ branch+code, "Print on") | ✅ BusinessSettings (incl. NTN) + signatories + bank accounts w/ branch/branch_code/kind — entered by the owner in admin, never seeded | Exists — owner fills live data |

### Invoice (screenshot 6) — on top of Slice P (shipped yesterday: manual MI invoices, filters, export)

| v2.1 feature | Woodex OS today | Verdict |
|---|---|---|
| Client/company **search or type a new company name** inline | Contact search/select only; new client made in CRM first | Missing — Slice U |
| Business line drives number family (Interior → WI-, Furniture → WF-) | Manual invoices = one **MI-** series | Owner decision — Slice U |
| **PO #**, **Project**, **Payment condition** presets, **Delivery date**, **Payment schedule** text, **Additional info printed as terms** | None of these columns exist on `invoices` | **Missing — Slice U** |
| Invoice templates ("invoice template") | Recurring profiles exist; no "new invoice from template" | Missing (small) — Slice U |
| Furniture invoice + invoice tracking design | Tracking board ✅ (Slice P); furniture invoice uses generic layout | Needs a sample PDF from owner |

### Leads (screenshots 7, 8)

| v2.1 feature | Woodex OS today | Verdict |
|---|---|---|
| Header: Classic list / Pipeline / **Import sheet** / Export / Add lead | Board view + New lead + Export ✅; **CSV import is a console command, no screen** | Import UI missing — Slice V |
| **Month filter** (‹ October 2026 ›, All months) | Created-date filter exists; no month step-through | Missing — Slice V |
| Line tabs: All lines / Furniture / Interior / Project | Line filter (dropdown), lines = Interior/Furniture | Partial — Slice V (segmented tabs) |
| **Side panel**: contact actions (Call/WhatsApp/Email/**New quotation**), **Next follow-up card** (Not set + Last contact), tabs **Log activity / Details / Timeline (n)** | Drawer exists but is view + stage/owner only; the full log/timeline lives on the lead page | **Redevelop — Slice V** |
| Log activity: type chips, what happened, **outcome**, **move stage to**, next follow-up + type, **quick chips (Tomorrow 11am / In 3 days / Next week / Clear)** | Lead page logs type+outcome ✅; no quick chips, no stage-move inside the log form | Slice V |

### Estimator → Estimations (screenshot 9 is **our own** rate-book screen)

Exists today, but **scattered across two homes** (Website studio owns the public estimator rates; Sales owns
`EstimationsIndex` + `EstimateBuilder` + estimate→quote conversion + estimator-enquiry drafting). Missing as a
*system*: one **Estimations hub** — Estimates tracking (status draft→reviewed→converted, value, age, owner,
conversion rate), Rate book (services/tiers/ready-made services/±%/saved snapshots) and New estimate in one
place; an estimate **PDF/preview** in the document design; estimator→estimate→quote funnel visible end-to-end.
**Slice W** (native build, per owner — no v2.1 copying).

### Marketing (screenshot 10 is our social planner)

Planner/All posts exist; **no social account can connect** (Settings tab has no live Meta path), Comments tab
has no real source. This is planned **Slice Q** (Facebook Pages gateway, plan 33) extended to the planner:
**Slice X**. Live proof needs the owner's Facebook Page ID + token; buildable now against the fake.

### Already ahead of v2.1 (keep, do not regress)

Frozen sha-verified PDFs + revisions, client portal acceptance with IP, WhatsApp/email send, milestone
invoicing straight into the double-entry ledger, payment collection + reminders, quote approvals + margin
guard, automated quote chasing, per-brand design engine with template file export/import, estimates pricing
engine, marketing attribution (source → enquiry → won value).

---

## 3. The slices (each independently shippable, tested, live-proven)

**Slice R — Quotation design match (Classic BOQ parity).** ✅ **Shipped 26 Oct 2026** (MASTER_TODO Step 42). New "Summary of cost" block (section rollup;
area/per-sft filled when a section is sft-priced, dash otherwise), BOQ rebuilt as per-section tables with
1.1-style SR, words box + total pill, Project-details box, footer contact strip + thank-you, logo mark in the
header, zero-as-dash + qty trim, Payment details split **Bank (with branch code) | Mobile wallets** (uses
existing bank columns: `kind`, `wallet_provider`, `branch_code`), Accepted-by-client block, same treatment for
the invoice document. *Schema: none* (snapshot keys only). Proof: byte-level renders of WI-10100 before/after
+ block tests. **← recommended first: it is what clients actually see.**

**Slice S — Builder live preview + per-quote blocks.** Right-pane **live HTML preview** (same blade partials
as the PDF, refreshed on change — no PDF cost per keystroke) with layout label; **per-quote PDF blocks panel**
(↑↓ + toggles + Reset order, seeded from the brand design, stored on the quote, frozen into the snapshot);
builder fields **Type, Site, Intro line** (+ project title exists as `title`); inline client display edit
(name/phone/email/address) stored as quote-level display overrides — *recommendation: overrides, not silent
contact mutation*. *Schema: nullable `type`, `site`, `intro_line` on quotes (owner approves with this plan).*

**Slice T — Quotation types & template cards.** Work-category type list (owner-editable presets:
Renovation/Civil/Interior design/Fit-out/Architecture/Other) on templates + quotes + list filter; template
cards gain section chips, advance line (from milestone plan), blueprint total in Rs lac, Delete confirm,
blank **New template** (create → opens builder on a scaffold quote → Save as template, or a minimal template
editor — decide at build). *Schema: nullable `category` on quote_templates (+ quotes.type from Slice S).*

**Slice U — Invoice completeness 2.** Nullable `po_number`, `project_title`, `delivery_date`,
`payment_condition`, `payment_schedule`, `additional_info` on invoices → create screen, snapshot, PDF,
tracking columns; search-or-new company inline on New invoice; **invoice from template** (save manual
invoice shape as a reusable profile); furniture: number-family decision (keep MI- vs WF-) + furniture invoice
design once the owner shares a sample. *Schema: 6 nullable columns (owner approves with this plan).*

**Slice V — Leads redevelopment.** Header parity (Classic list/Board, **Import sheet** screen: upload →
column map → preview with phone de-dupe → import, reusing the tested CSV core), month step filter, line
segmented tabs, and the **side panel rebuilt**: quick actions (Call/WhatsApp/Email/New quotation), Next
follow-up card, tabs **Log activity / Details / Timeline (n)** with the full log form (type chips, outcome,
move-stage-to, next follow-up + type, quick chips Tomorrow 11am / In 3 days / Next week / Clear). The full
lead page stays. *Schema: none expected.*

**Slice W — Estimations, complete.** One Estimations hub in Sales: **Estimates** (tracking by status,
value/age/owner, conversion), **Rate book** (the services & tiers editor with ready-made services, ±%
adjust, saved snapshots — unified with the public estimator's source), **New estimate**; estimate preview/PDF
in the document design; public estimator and bot keep the same rates (single source, no "2 pages" split).
*Schema: none expected beyond settings keys.*

**Slice X — Marketing connections (Q+).** Facebook Pages gateway (Page ID + token + honest Meta test,
signature-verified webhook → inbox/leads, delivery truth), social Settings connect, planner publish +
Comments from connected pages. *Blocked for live proof until the owner provides Page ID + token.*

**Order: R ✅ → S → V → T → U → W → X** (owner picked R first, 26 Oct). R+S deliver the owner's headline ask ("match the PDF,
blocks drag & switch"); V is the daily-driver; T/U are structured-data completions; W unifies; X waits on
Meta credentials anyway.

## 4. Owner inputs that unblock later slices

1. A **furniture invoice** sample (PDF/photo) and an **interior invoice** sample from v2.1 — to match the
   invoice document design (only the create screen was shared).
2. Confirm furniture documents mirror the interior Classic BOQ design with WOODEX FURNITURE branding.
3. Fill bank accounts + wallets in the OS admin (Bank accounts screen) — printing uses owner-entered data
   only, by design.
4. Facebook **Page ID + page access token** when Slice X's live proof comes up.

*Rollback per slice = revert its commit; every slice ships with tests + a live proof on the 33143 preview,
as established.*
