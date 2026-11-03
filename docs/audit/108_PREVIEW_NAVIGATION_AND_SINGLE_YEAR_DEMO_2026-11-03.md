# 108 — Preview navigation and one-year import/export demo

**3 November 2026 · Isolated synthetic preview only. Production unchanged; deployment FINAL / auto-deploy OFF.**

## Owner clarification

The owner confirmed March/April/May are copied views of the **same 16 test enquiries**, not separate opportunity batches, and permitted a simpler single-year demonstration. This resolves107's copied-tab question. It does not approve production imports or changing original workbook dates/data. Earlier workbook candidate totals are source-review counts, not100 live customers or an approved migration backlog.

The preview now contains **16 fictional 2026 enquiries**, using clearly labelled synthetic names and reserved NANP555-01xx test numbers. The supplied workbooks were not modified or loaded into the app. No historical finances were imported or dry-run.

## Preview-access repair

The actual owner screenshot shows **E2B403: Missing traffic access token**, which is an infrastructure response before Laravel. Previous controlled tests on the raw sandbox hostname did not certify the actual Arena preview shell.

- New private-launcher transport rewrites only the exact configured self-origin to **relative URLs** in HTML navigation/redirects and Livewire effects. Links remain inside the authorized wrapper origin rather than jumping to a raw E2B hostname. Foreign URLs/prefix-lookalike hosts are untouched.
- **Signed Livewire snapshots are not rewritten** (HTML attributes or JSON state). Ordinary JSON exports, CSV and binary downloads are untouched. Content-Length is removed only when a text response may change.
- XHR uploads now carry the same cookie-independent synthetic demo context as fetch requests. Context is restricted to the exact page origin, not appended to URLs, and is not a session/traffic-access credential. Normal CSRF checks remain enabled; missing-token POST still returns419.
- Middleware is loaded only by the explicit private native launcher after its existing clone/isolation/opt-in guards. No production front controller/authentication bypass, E2B token handling, access-control disabling or provider credential change.

**Actual Arena-owner acceptance remains open.** Refresh the preview through Arena's preview control so cached absolute links are discarded. If E2B rejects even the initial document, the Arena/E2B preview authorization must be refreshed by the platform; PHP cannot mint or repair an infrastructure traffic token. Never request/publish that token or add it to links.

## Working single-year CSV controls

The lead-import screen now includes an owner-only **Export year CSV**, a clearly labelled16-row2026 synthetic CSV download in direct-demo mode, and an optional private-config link to the manually created Drive snapshot.

- Export requires an active owner and the import permission; validates year2000–current; selects active/non-archived leads with an active contact using the stored UTC year boundary.
- Canonical columns: `Sr, Date, Name, Contact, Status`. Date preserves the stored UTC calendar date (avoids an application-timezone shift across New Year). Phone uses importer-supported international00 prefix; current stage is reference only. Names/stages that could execute spreadsheet formulas are escaped **only in the download**, not changed in storage.
- Bounded to5000 rows; oversized years receive a validation error, not silent truncation. Private/no-store download; no ledger/provider data or record writes.
- Import still requires header mapping, source-date review, database-aware dry-run and explicit final confirmation. A browser-driven demo applied exactly16 synthetic leads; a fresh upload of the same batch was recognized as already imported, not duplicated.

## Drive: real manual snapshot, not automatic synchronization

A **new private native Google Sheet** was created through the owner's connected Drive app from the16-row app export. It does not overwrite any original workbook. Its link/identifier is held only in private preview configuration, not in public source/audit logs. The app labels it **Open manual Drive snapshot**, separately from Google Workspace connection settings.

An initial CSV-to-Sheets conversion removed leading phone zeros. That newly created test snapshot was superseded by an explicitly text-formatted XLSX-to-Sheets conversion; only the superseded file created in this operation was moved to recoverable trash. The final Sheet was exported back to CSV and **all17 records including the header matched the application export field-for-field**, retaining dates and phone text. The Drive CSV was also uploaded through the app and recognized as the same16 already-imported demo rows.

**Recommended current workflow:** CRM is authoritative → explicit one-year export → separate demo snapshot → owner-reviewed CSV import when needed. Keep dates/phones as plain text. No polling, scheduled/background or two-way sync is enabled. Arena's connected Drive is not the application's OAuth credential store. App-side automatic Sheets writes still require a separately configured least-privilege connection, explicit destination, conflict/version policy and owner-approved writes. Existing Google sign-in/read scopes must not silently gain write access.

## Evidence at this checkpoint

- Genuine Composer installation from the byte-identical committed application lock into private preview/QA copies; no dependency solver/lock replacement. Native PHP8.5.12;104 migrations only in a fresh synthetic SQLite database;3 synthetic staff. No existing database reset/reseed.
- **15 native focused tests /138 assertions PASS**:3 preview-transport unit cases,3 CSV-export feature cases plus the9 existing lead-import-screen cases. Covers foreign hosts, signed-state preservation, redirects, CSV scope/formula safety/year boundary/authentication, mapper and source provenance.
- **15 controlled alternate-origin browser checks PASS**: raw-origin requests refused, cookie/Set-Cookie dropped, real sidebar click and Livewire actions, welcome page, reload/new tab, CSRF refusal, desktop/mobile. This is not actual Arena-parent certification.
- Streaming reverse-proxy browser checks preserve real multipart bytes (Playwright request-body interception omits uploaded file bytes). Verified synthetic CSV upload, date inventory,16-ready dry-run, confirmation/apply, fresh-upload repeat recognition, one-year export and Drive-CSV re-import. Initial apply and repeat/export were verified in successive browser sessions without resetting the database.
- **92 source/safety contracts +62 UI/context contracts PASS; Pint7 PHP files PASS; Vite preview build PASS.** Tests/builds ran against isolated copies; unrelated restored generated-asset differences in the main checkout remain unstaged.
- Full local PHPStan was attempted but its runtime optimizer re-executes PHP_BINARY as a conventional PHP CLI; the FrankenPHP multicall binary rejected that invocation. This is not a static-analysis pass. Full exact-source hosted native MySQL results remain tracked against the pushed candidate in PR8;107's2033/11282 pass is not relabelled as108 evidence.
- Preview inventory:16 leads/16 contacts/16 lead-import provenance references; invoice/payment/journal records remain zero. Reference-data provenance rows from defaults are not lead imports. No worker started, provider send, production write, deployment, merge or repository-privacy conversion.

All36 parent acceptance gates are retained. This slice repairs the preview transport and demonstrates import/export plus a manual Drive snapshot; it does not declare the whole release complete.
