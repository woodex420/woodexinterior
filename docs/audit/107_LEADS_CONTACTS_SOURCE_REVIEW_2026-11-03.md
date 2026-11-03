# 107 — Leads/contacts source review and year/provenance controls

**3 November 2026 · Asia/Karachi. Review only; no historical import or production change.**

“Start next” continues the proposed leads/contacts review. It does not approve applying rows, changing historical finances, deployment, or messaging clients. The earlier accounts/payments reference-only hold remains.

## Read-only workbook findings

The previously supplied native lead workbook was freshly exported through the connected Drive app and opened locally read-only, with formulas retained and external-link loading disabled. No spreadsheet formula/macro was executed. Raw bytes, source version/checksum, row-coordinate review files and duplicate groups remain in an ignored, permission-restricted private cache; no customer names, phones, bank details, raw extracts or access URLs are committed.

This review reads the five monthly tables and two contact-reference tables only. **Team, VendorSup and expenses are excluded**; the financial workbook was not opened or passed to an importer/dry-run.

| Source scope | Review candidates | Treatment |
|---|---:|---|
| January monthly table | 29 | 22 dates in2025;7 undated. Keep the undated rows on hold. |
| February monthly table | 0 | Template/summary content is not lead data. |
| March monthly table | 16 | Dates in2026; separate right-hand target block excluded. |
| April monthly table | 16 | Dates in2026. |
| May monthly table | 16 | Dates in2026. |
| Target clients | 42 | Contact/prospect-reference review, not permission to create42 opportunities.3 rows contain a secondary contact;5 lack a primary phone;1 further primary phone needs review. |
| Client Data base DB | 26 | Contact-reference review;30 serial-only filler rows excluded.21 date cells are in2025,5 undated/unresolved; the date's business meaning must be confirmed before treating it as an enquiry date.1 missing and2 further primary phones need review. |

**Monthly buckets:22 confirmed2025 /48 dated2026 /7 undated =77 review candidates, not approved import counts.**

- The48 monthly2026 occurrences comprise **16 repeated content groups across March, April and May**.13 groups have a usable matching primary phone; the other3 need additional identity evidence. Repeated content is a review flag, not authority to merge/delete or create48 opportunities.
- **33 cross-tab normalized-phone groups** appear in more than one reviewed tab, considering reference primary/secondary/WhatsApp numbers and monthly primary numbers. This is an overlap flag, not33 proven duplicate people or a production database comparison.
- January has2 missing and2 additional primary-phone values requiring review under the strict current check. March/April/May each have3 missing primary phones; alternate-person/phone columns must be reviewed separately, not silently substituted.
-21 Target-client rows and1 client-database row identify a company without a primary person. Do not fill the person's Name from the company automatically or invent an enquiry date.

### Proposed column mapping — not applied

Monthly tables use **header row5**: A→Sr, B→Date, D→Name, E→primary Contact. Status is I for January/February/April/May, **K for March**. C is Company reference data; extra contacts/phones, source, follow-up/notes retain their separate meanings. The March right-hand target block is a different table and must not be carried into a lead CSV.

For a future CSV, export only the intended table with its actual heading row first; the CSV upload does not automatically discover multiple workbook tables or a title above the headings. Original workbook row coordinates remain in the private review manifest. CSV record positions are relative to the supplied CSV, not claimed to be original workbook row numbers.

Target clients: B company/target, F primary person, H primary phone, I secondary person, J secondary phone, K location, L status, M last-contact date, N notes. **M is not automatically an enquiry date.**

Client database: A serial, B date requiring semantic review, C company, D primary person, E primary phone, G WhatsApp, H email, I secondary person, J secondary phone, K location, L last contact. Do not collapse secondary people into the primary contact. The current lead importer is not a contact-only importer; undated/reference contacts must not be forced through it with invented dates.

## Implemented source improvements

1. Owner-only **Review source dates** action on the lead mapper. It inventories the matched source Date column, shows separate year counts plus missing/invalid/future dates, and makes no importer call or application-record write. Counts are not import eligibility/approval. Date parsing is strict day/month/year, calendar checked, UTC cutoff; values are never repaired. Review data is locked against client updates and cleared when mapping/year/batch changes.
2. Dry-run reports retain **original source CSV record numbers**, including blank records, instead of showing shifted canonical-output positions. Embedded-newline cells count as one CSV record. Original workbook coordinates require the separate workbook-to-CSV mapping above.
3. Each canonical temporary file is removed in that run's `finally` block, including validation failure. Start-over no longer sweeps other reviews' canonical files. Uploaded source and application/import authority rules remain unchanged; no schema migration.
4. The106 hosted MySQL failure was identified from check annotations: one direct-preview guard test expected `basePath()` once, but short-circuiting on an absent private preview directory called it zero times. The launcher now resolves the actual path before the same refusal condition. The private-clone/explicit opt-in/isolation/CSRF/identity guards are not relaxed.

## Validation and boundaries

- Native PHP8.5 source-date helper checks: **17 PASS**; launcher rejection checked with the private directory both **absent and present**, preserving foreign-tree rejection.
- Syntax parsing and **Pint5 PASS** for changed PHP sources/tests. **92 release/source/form contracts** and **61 UI/helper contracts PASS**.
- Added3 pure unit cases and2 feature cases for year inventory/no application writes/stale state/source coordinates/per-review cleanup. Full Laravel/Pest/browser/static acceptance for this107 candidate is separate: the disposable local application dependencies/runtime from106 did not persist. Do not reuse106 local2028-test success as107 acceptance.
- Prior106 hosted run failed one guard test; all other groups passed. Full log download is unavailable from the restricted results host; exact failure/group results were obtained from GitHub check annotations, not guessed. A fresh exact-candidate hosted run is required after this correction.
- Restored working files were compared with the already-pushed106 commit before new work:2466 exact matches; unrelated generated assets differ/missing. Branch/index metadata was reconciled with that verified commit without checking out/resetting working files; unrelated asset changes are not included in this slice.

## Decisions required before applying any source rows

1. Are March/April/May copied views of the **same16 enquiries**, or intentionally distinct opportunities? Which source tab is authoritative? No automatic collapse or expansion.
2. Confirm the22 dated2025 candidates are the intended first review batch; keep the7 undated rows held. Resolve primary versus alternate phone/name mapping and overlaps with the installed CRM before apply.
3. Should Target clients and Client Data base DB remain reference-only, or later be handled through a separately approved **contact-only** workflow? Do not infer opportunity stage, customer status or dates from these lists.

No real source rows applied, existing CRM records changed, financial import/dry-run performed, provider send, production migration, merge, deployment or repository-privacy change. Deployment remains **FINAL**, auto-deploy **OFF**. Current private production backup/verified restore/staging and consequential owner approvals remain prerequisites for production writes.
