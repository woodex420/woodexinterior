# Slice W — Estimations hub audit and implementation plan

**Date:** 27 October 2026
**State:** implemented and verified (27 October); no production deployment.
**Approval:** Furniture accepted; “Sure start” approved this audit/plan, then **“start next” approved the native three-tab implementation and all eight fixes**, without new schema.

## Delivery summary

Implemented native **Estimates / Rates / Estimator Enquiries**, one sidebar hub, permission-specific tabs/actions and legacy Rates redirect. All eight audited defects below are covered by permanent passing regressions. Saved historical amounts remain distinct from live prices; repricing is explicit, substantive changes invalidate review, and CRM/native conversion shares one idempotent, Furniture-aware path.

- Final exact-tree full gate: **1,601 passed / 1 skipped / 8,565 assertions / 438.16s**. PHPStan0, touched-file Pint30 pass, Vite pass.
- Actual desktop/390px browser workflow,18 public-v1-JS/native-PHP base cases, active33143 restricted-cookie frames and both-brand generated-PDF inspection pass.
- **No new tables/columns, public-v1 redesign, Furniture renderer changes or production deployment.**
- Detailed release evidence and reproducer: [verification record](evidence/39_estimations_release_results.md). Implementation details and honest boundaries: §7 below. §§1–6 retain the original audit/plan as historical evidence.

## 1. Recommendation

Consolidate existing Sales estimates and Website Studio estimator rates into **one native Laravel/Livewire Estimations hub**:

1. **Estimates** — searchable, paginated work list with service/status filters and linked client, enquiry and quotation; existing estimate builder remains the detail workflow.
2. **Rates** — native editor for the same public estimator rate book, Essential/Standard/Premium service prices, units, finish labels, terms and existing named rate-book snapshots. Show public-file/database consistency and distinguish draft changes from publishing.
3. **Estimator Enquiries** — existing CRM estimator submissions, scoped by the existing visibility rules; show their estimate/quotation links or why they cannot be priced. Open/create the existing estimate instead of inventing another submission store.

One sidebar entry, not a new parallel Sales module. No new tables or columns expected. Keep the public v1 estimator/frontend and the accepted Furniture renderer unchanged.

## 2. What already exists — reuse first

| Existing asset | Role / reuse |
|---|---|
| `EstimationsIndex`, `EstimateBuilder` | Native work list and edit/review/conversion UI; improve rather than replace their workflow |
| `Estimate`, `estimates.inputs`, `computed`, `catalog_lines` | Existing saved input/rate/amount snapshots, review fields and quote links |
| `EstimatorRate`, `estimator_rates` | Staff/bot service prices mirrored from the public file |
| `RateBook` | Parser, normaliser and file writer for `public/assets/js/estimator-rates.js` |
| `StudioApi::estimatorSave`, `mirrorEstimatorRates` | Existing coordinated public-file + DB save and rollback path; extract shared service, do not duplicate it |
| `EstimatorSettings::rate_books` | Named rate-book snapshots in existing settings; preserve load-as-draft, explicit publish and 12-book limit |
| `ComputeEstimate`, `SaveEstimate`, `ReviewEstimate`, `ConvertEstimateToQuote` | Existing native calculation, snapshot, review and conversion services |
| `DraftEstimateFromEstimatorLead`, `EstimatorLeadDetails`, listener/backfill | Existing submissions → draft estimate chain; keep legacy field formats supported |
| `CreateQuoteFromEstimate` | Older CRM conversion path; consolidate behind the existing native conversion to stop divergent behaviour |
| `CatalogItem` | Staff quotation add-ons; **not** the separate public-file catalogue. Do not merge or overwrite the two |
| `LeadPolicy`, current permissions | Retain lead visibility, `estimates.view`, `estimates.manage`, `quotes.manage`, `website.edit` |

The existing ported v2.1 rates screen was inspected locally (`public/assets/admin-v21/admin-estimator.js`); no fresh Drive download is needed for this **native OS** slice. The public v1 calculator uses JavaScript, while staff calculations use PHP. They share rates and base formulas, not literally one executable engine. Acceptance must check parity without replacing public v1.

## 3. Runtime findings

Existing targeted tests: **43 passed / 212 assertions / 9.55s**. Those tests miss the defects below.

Eight additional desired-behaviour diagnostic tests were run against actual Laravel/Livewire and disposable seeded SQLite: **8 failed / 10 assertions / 3.87s**. These are reproduced audit failures, **not** fixes or a green release gate.

| ID | Priority | Reproduced defect | Evidence / required behaviour |
|---|---|---|---|
| W-01 | High | Conversion through the editor silently re-saves at current rates | Saved rate 25,000 minor units became 50,000 after a rate-book change and `EstimateBuilder::convert`. Conversion should use the saved snapshot; dirty inputs require explicit save/discard and any repricing must be visible |
| W-02 | High | Reviewed estimate stays Reviewed after its price inputs change | Review quantity10, save quantity20: still `Reviewed`, old review attribution retained. A substantive edit/reprice must return to Draft and clear review attribution |
| W-03 | High | Converted estimate detail displays today's calculation, not its saved amount | Saved total Rs2,500, change service rate, reopen converted estimate: “Priced now” shows Rs5,000. Converted detail must be read-only and show its saved snapshot |
| W-04 | Medium | Catalogue choices lose their category/description | Builder loads `id,title,unit,rate_minor,business_line`; Blade displays `category`/`description` which were not loaded. Actual browser choices read `— · Rs70,000/job` etc. Load/display the correct identifying fields |
| W-05 | High | Negative quantities silently become positive | `ComputeEstimate('-20', …)` does not reject it: the regex strips the sign. Use strict positive-number validation for headline and catalogue quantities; report errors rather than changing meaning |
| W-06 | High | CRM Furniture estimator enquiry creates an Interior quotation | `CreateQuoteFromEstimate` hardcodes `BusinessLine::Interior`; runtime Furniture enquiry produced `interior`. Reuse native brand-aware conversion and populate Furniture product fields |
| W-07 | High | Repeated CRM estimator conversion creates another quotation | Same enquiry, two calls: quote IDs1 and2. Unify the conversion path and return the already-linked quote on a retry; deliberate new estimates must be a separate explicit action |
| W-08 | High | Lead visibility is bypassed through the estimate-builder entry | Sales user fails `LeadPolicy::view` on another owner's enquiry, but mounting the builder for it returns200 rather than403. Apply visibility checks on entry and action boundaries, not only on the CRM page button |

Source entry points at audit time:
- `EstimateBuilder.php:80,137,149,209,251` — lead lookup, catalogue projection, live summary, re-save on convert, active-rate lookup.
- `SaveEstimate.php:71–82` — update omits review/status reset.
- `ComputeEstimate.php:28,44` — permissive digit-stripping.
- `CreateQuoteFromEstimate.php:74,111` — hardcoded Interior and independent conversion/linking.

Additional scope observations, not counted as reproduced high-priority defects:
- Work list uses `limit(60)` with no pagination or search. Older work becomes unreachable from that list.
- The native estimate builder has a small **5px** document-level horizontal overflow at390px when an add-on row is present; list and rates screen fit390px in this audit.
- Native conversion deliberately records contingency in internal notes, rather than silently billing it as a quote line. Keep that distinction explicit in the conversion UI; do not silently change this financial rule.
- The current file/DB save is coordinated with rollback, not a true transaction across two storage engines. Preserve compensation, add conflict/concurrent-edit protection, and fail honestly; do not claim perfect cross-storage atomicity.

## 4. Proposed implementation boundary

### A. Native hub and navigation

- Keep `/admin/estimations` as the hub and existing `/new`/`/{estimate}` detail URLs.
- Add URL-addressable Estimates / Rates / Estimator Enquiries tabs, search, status/service filters and pagination.
- Replace the separate Estimator rates sidebar item. Preserve the legacy `/admin/website/estimator` named route as an authorised redirect to the Rates tab, not a broken URL.
- Route access must support current rate editors who have `website.edit` but not `estimates.view`, without granting them access to estimates or CRM records. Each tab and server action checks its own permission.
- Estimator enquiries reuse Leads and policy-scoped queries; no `wx_*` imports, bulk backfills or duplicate contacts.

### B. One rate publishing path

- Extract shared rate-book read/publish/named-snapshot logic from StudioApi into a native service; old API actions delegate to it for compatibility.
- Native Rates UI uses that service, not the ported browser-admin shell.
- Keep the public rate file authoritative for the locked v1 estimator; one explicit publish updates file + mirrored rows. Removed services become inactive, not deleted.
- Preserve public catalogue entries unchanged and retain existing named books. Loading a named book is a draft until Publish.
- Show file/DB drift without mutating on GET. Explicit authorised resync/publish is required. Include conflict checks so a stale editor cannot silently overwrite a newer publication.
- Keep price publication restricted to `website.edit`; ordinary estimate staff do not gain website-price editing rights through the hub.

### C. Snapshot-safe estimate lifecycle

- Display the saved amount/rate/effective date separately from an editable current-rate preview. A changed rate book must not rewrite historical display or conversion amounts.
- Provide explicit repricing with before/after values; require save/discard for dirty forms before conversion. New estimates still use current rates; deliberately saving/repricing edits is visible to the person.
- Clear Reviewed status/by/at when saved content changes; unchanged saves should not falsely invalidate a review.
- Converted estimates are read-only in the UI and protected at action boundaries. Retired services must not erase an existing saved estimate's display or prevent converting its valid saved snapshot solely because the service is now inactive.
- Existing JSON snapshot fields can carry additional service-label/rate metadata for new saves if needed; old snapshots remain readable without a mass rewrite. No schema migration is proposed.

### D. Unified lead → estimate → quotation

- Route both CRM and hub actions through the existing estimate/conversion services, with the visibility/permission checks present on the server.
- Preserve estimate, enquiry, client and quotation links. Retry/double-click reuses the same conversion; no silent duplicate quotation or invoice.
- Furniture enquiries create Furniture drafts with product/description fields populated; Interior continues its current quotation path. Accepted Furniture renderer and invoice/payment/accounting semantics remain untouched.
- Disclose any contingency difference before conversion; keep it internal until deliberately included in a quotation. Nothing is sent automatically.

### E. Validation and usability

- Strict positive decimal parsing, finite/bounded amounts, valid active item selection and correct error-field mapping; reject negative/malformed values instead of stripping signs/letters.
- Correct catalogue labels and live add-on pricing updates; fit390px with responsive controls and table-local scrolling.
- Keep public JS/server price parity checks for all existing services and finish levels. No public frontend framework or layout change.

## 5. Acceptance and release plan

1. Turn all eight audit probes into proper regression tests; they must pass without weakening expected behaviour.
2. Retain the 43-test baseline and add hub tab permissions, scoped queries, search/pagination, legacy route compatibility and native Rates tests.
3. Prove a publish changes the copied public file and mirrored DB together; exercise validation failure, failed-save compensation, stale-editor rejection, deactivation and named-book load without publish. Never alter the owner's live rate file from tests.
4. Check new → save → review → edit/review reset → explicit reprice → saved-snapshot conversion, repeat conversion and retired-service history. Verify Furniture/Interior routing.
5. Run the full Pest suite, PHPStan, touched-file Pint and Vite build. Previous Furniture 1,577-test results are historical, not this audit's current full gate.
6. Use an isolated runtime/database for mutating browser proof. Check desktop/390px, live add-ons, all tabs, rates publish, lead-scoped actions, conversion and zero JS errors. Verify active33143 with read-only/unsaved checks; never restore an isolated/stale DB over an owner's preview.
7. Test cookie-restricted Arena framing and visually inspect the resulting quotation preview/PDF for both brands, keeping frozen documents intact.
8. Update docs, report actual results and preserve the working branch. No production deployment or MySQL-runtime claim without separate validation.

## 6. Audit-time evidence and environment boundaries (historical)

The earlier `/tmp` runtime, database and browser dependencies were absent after sandbox reset. Tooling was rebuilt with `tools/sandbox/setup.sh`; this created a **fresh disposable demo database**, not the owner's previous six-quotation preview. No previous owner database was recovered or overwritten, and no production database was used.

The fresh preview now runs on33143 at:
`https://33143-iasmzxllnpw5tve8pg0p6.e2b.app`.

Read-only/unsaved Chromium audit on that fresh demo:
- Estimates, New estimate and legacy Rates pages returned200.
- Confirmed missing catalogue descriptions in actual select options.
- Widths at390px: list390, builder395 with add-on, rates390.
- **JS_ERRORS []**. This is current-screen audit proof, not a post-implementation pass.

Transient evidence: `/tmp/w-audit/{baseline,probes,browser,setup}.log`, screenshots `*-desktop.png` / `*-phone.png`.
Diagnostic source is preserved as **text** at `docs/audit/evidence/39_estimations_audit_probes.php.txt`; it is not installed in the application test suite. To reproduce after rebuilding the disposable runtime, copy it into that runtime's `tests/Feature/Sales/WAuditDesiredBehaviourTest.php` and run Pest on that file. Its eight audit-time failures described the defects; the implemented permanent regression suite now passes all eight. Do not run fixtures on production.

Repository metadata also differs from the earlier turn: local HEAD is `d77016b` while the prior OS/Furniture source is present as untracked files; `.gitignore` already has unrelated edits. This audit did not reset, stage, discard or commit those files or switch branches. Do not use `git add .` or assume the earlier commit IDs are present locally. Confirm branch/remote/worktree provenance before publishing implementation changes.

## Original decision — approved

The owner answered **“start next”**, approving this native three-tab hub and the eight workflow fixes. This approval has been implemented; it is not awaiting another implementation decision.

## 7. Delivered implementation and boundaries

### Native hub

`EstimationsIndex` now has URL-addressable Estimates / Rates / Estimator Enquiries, independent20-row pagination, estimate search/service/status filters and enquiry search. `EstimateAccess` scopes estimates/lead entry/actions and selectable contacts. A website-only editor reaches Rates without estimates/CRM access; hidden Livewire actions enforce their own permission. `/admin/website/estimator` redirects to native Rates, with one Sales sidebar entry. Existing Studio API actions delegate to the shared service for compatibility.

### Shared rate publishing

`EstimatorRateBooks` reuses `RateBook`, existing `EstimatorRate` rows and `EstimatorSettings`. Native draft changes and named-book loads do not publish. Named backups capture **currently published prices**, not unsaved editor input; existing12-book cap is retained. Explicit publication preserves the distinct public catalogue, mirrors prices and deactivates removed services. A shared cache lock serialises publishing, and native editors send a locked SHA revision to reject stale file versions. DB failure restores exact prior public bytes. Read detects file/DB drift without settings creation or automatic writes.

This is **compensated cross-storage publishing, not a perfect distributed transaction**. A hard process crash can still leave drift that must be reviewed and explicitly republished. Production must provide writable rate-file/directory permissions and a shared lock-capable cache. Legacy API callers may omit revision for compatibility; do not describe universal stale-client rejection. Named-book metadata itself retains existing settings last-save semantics, not a separate optimistic version protocol.

### Snapshot lifecycle and eight fixes

Strict positive decimal quantities reject negative/exponent/garbage rather than stripping signs; at most3 decimal places and10million units. Saved service/rate/finish/unit/amounts stay frozen for display and conversion, including retired services. Current-rate preview is explicitly unsaved. Empty add-on drafts are omitted only from that preview; saving still requires completion/removal. Catalogue choices show actual descriptions and fit390px. Review survives unchanged saves but resets on substantive input/reprice changes. Builder rejects unsaved-input and other-session conflicts before save/review/convert; repricing requires confirmation. Converted detail is read-only.

CRM and native conversions now reuse `ConvertEstimateToQuote`, return linked quotes on retry, retain enquiry brand for custom service keys, populate Furniture Product fields and preserve estimate provenance. Internal contingency remains disclosed and is **not silently billed**. No accounting receipt is created from an advance percentage.

### Verification and maintenance

See the [release results](evidence/39_estimations_release_results.md) for actual commands/evidence. The first full run's three failures were obsolete six-entry Sales navigation /200-status legacy Rates expectations; they were updated to the approved five-entry sidebar and authorised redirect, without weakening authorization. Final validation also corrected two existing false/nullsafe PDO-query static issues in `MysqlProbe`; live MySQL is untested in this wasm environment.

Remote/worktree provenance was reconciled with the existing branch commit **5fa957d** before implementation; the audit-time warning in §6 is historical, not the current repository state. The current preview URL is recorded in the verification file; the earlier §6 sandbox URL is obsolete. No recovered owner DB or production access is claimed.

### Next recommended decision

W is implemented. Recommend an **audit of Slice T's remaining quotation-template cards/category/chip workflow**, reusing v2.1 Pro source and delivered native features first. Do not start T code or schema changes without its own locked decision. U remains sample-blocked; deferred connector/backups work and the public-v1 lock are unchanged.
