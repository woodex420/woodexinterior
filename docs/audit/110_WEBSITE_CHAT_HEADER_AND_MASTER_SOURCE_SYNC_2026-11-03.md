# Revision110 — complete Website Chat header totals and default-branch source sync

Date: 3 November 2026 (Asia/Karachi).

## Owner request and boundaries

The owner requested continued pending work and all updated files on the main/default branch. The repository's default target is **`master`**, not a new `main` branch. Integrate the cumulative intended source through existing **PR8**, from the fixed `arena/7093000c-woodexinterior` branch, only after exact-candidate checks. The PR's actual merge state/SHA and subsequent master checks are authoritative; a source merge does **not** approve production deployment or close the36 acceptance gates.

The only repository workflow is native isolated test CI, including pushes to master. No deployment workflow was introduced or enabled. Hosting ref/path, APP_KEY, installed data/storage, original workbooks, provider configuration and external auto-deploy settings were not changed. Standing auto-deploy OFF / deployment FINAL holds remain. No production migration, accounting importer, provider send, database restore or repository-privacy change.

## Reproduced and repaired

The header counted unread threads and recent incoming activity only inside its latest30-row drawer list. Consequently an older unread enquiry could be omitted from the badge, even though the full Inbox contains it. Three new regression cases failed before repair; the other four preserved existing channel/permission/poll behavior.

- Count **all active website conversations** with scalar SQL queries; exclude WhatsApp and closed threads. The badge counts unread **conversations**, not the sum of unread messages.
- Recent incoming activity uses the same complete active-website scope, independently of the list limit. It remains a last-message indicator, not a transport/delivery receipt or a new presence service.
- Keep the compact list bounded at30 and order timestamp ties by descending ID. Disclose the full active total and exactly how many unread conversations are outside the list.
- The full Inbox link requests website channel and **all statuses**, so pending threads are not silently excluded by the Inbox's default Open filter. Existing native conversation pagination reaches older records.
- Preserve permissions/revocation, original-visitor drafts, history windows, per-session alert baseline and read behavior. Opening the drawer reads its selected visitor only; hidden older threads stay unread. No channel send rules or schema changes.

## Rebuilt deployable assets, not leftover files

This repository versions `platform/public/build`; hosting does not run Node. The pre-existing generated working-tree changes were inventoried and preserved privately before replacement. A clean private build copy was made from committed source plus this repair, with the genuine matching npm lock/dependencies and no environment file. The first symlinked dependency build emitted private-relative manifest keys; it was rejected and replaced by a physical dependency copy and a fresh build.

The accepted manifest contains only stable project/node_modules keys, every referenced file/import exists, and the auth entry is retained. Ship its regenerated admin CSS, site CSS and manifest; remove only the superseded generated admin hash. The previously missing tracked auth bundle is restored byte-for-byte. No application page, upload or history was removed. Original generated leftovers remain in an ignored private archive, not the release tree. Existing Vite chunk-size and unresolved runtime-image advisories are not claimed as performance certification.

## Verification and evidence

- **Seven native header cases**, covering totals beyond30, message-vs-conversation counting, pending/read/closed/channel exclusions, outside-list activity, stable ordering, read behavior, older-thread auto-open and permission absence/revocation.
- Focused native Chat regression: **76 tests /283 assertions PASS** in isolated SQLite.
- **Six real Chromium header checks PASS** on a separate61-conversation disposable QA copy:35 unread conversations, five outside the compact list, recent activity outside the list, selected-only reading, phone layout and real full-Inbox pagination/navigation. Wait for responsive reflow before checking overflow; an immediate post-resize measurement was not treated as a product regression.
- Existing **nine draft-safety Chromium checks PASS** against the rebuilt assets, with nonzero unread counts. The helper now accepts the header's optional unread suffix rather than assuming a zero-unread fixture.
- **154 source/release/enquiry/auth/theme/context contracts PASS**. Pint on both changed PHP files PASS; clean Vite build PASS. Final full native/local and exact-source hosted MySQL results, plus merge/master verification, are recorded on PR8 rather than inferred from109's passing run.
- Main alternate-origin browser verification is recorded separately in private `110-preview-origin.log`; this is not actual Arena-parent/user acceptance. Local full PHPStan remains blocked by the previously documented native multicall re-exec limitation; no pass is claimed.

Private evidence: `.cache/preview/110-red.log`, `110-chat-focused.log`, `110-browser-results.json`, `110-draft-browser.log`, `110-contracts.log`, `110-vite.log`, `110-full-suite.log`, `shots/110-chat-header-mobile.png`; `.cache/revision110/` holds the original generated inventory/archive and accepted build. No credentials, databases or private workbook extracts are versioned.

## Remaining work

C01/C02 broader design/device/lifecycle/visitor-isolation/actual-parent acceptance remains partial; durable drafts across page exits/reloads remain unimplemented. C03 live provider receipt acceptance is separate. Installed identity, consistent backup/restore, financial concurrency, staging, external-provider approvals and final production release remain gated. Main synthetic8080 preview remains running with its16 leads/16 contacts and no accounting records; all61-conversation mutations were confined to the separate QA copy, whose temporary listener is stopped after testing.
