# Private operator checklist — consistent backup, isolated restore and rollback

**Prepared, not executed.** No current backup/restore is certified by this file. Run only through an authorized private operator/hosting channel. Do not paste credentials, `.env`, backup archives, bank/customer rows or raw private paths into chat, GitHub, public logs or this repository. No new schema/import/provider/migration/deployment authority is implied.

## A. Identify the actual installation without changing it

- Production is `https://woodex.com.pk/admin`, same-site publicv1, existing Git branch/directory unchanged and auto-deployOFF.
- Privately identify active DB engine/version/schema, actual external environment/storage directory, app/data/public-page paths and whether any path is a symlink. Source `SiteState` can keep secrets/uploads outside public_html; a ZIP of the repository is insufficient.
- Record code commit, PHP/Composer/database versions, maintenance/scheduler/queue state, timezone and snapshot cutoff. Do not publish credentials or raw runtime diagnostics.
- Record intended recovery point and acceptable recovery time with the owner. Retain the29October copy; it cannot replace transactions created since then.

## B. Agree a consistency window

- Review pending jobs, due automations and provider destinations privately before pausing/resuming anything. Do not start a worker or scheduler to test connectivity.
- Agree how database writes and file/page edits will be quiesced or captured consistently, including external webhook ingress. Do not silently drop/replay incoming events.
- A DB transaction-consistent dump does not make independently copied uploads/public files a matching snapshot. Record DB start/end and file snapshot cutoff/integrity checks.
- Any maintenance/provider/scheduler change is an explicit operator action and requires its own controlled production scope. This checklist does not execute it.

## C. Include all required components privately

- Complete native DB, schema/migration state and relevant server settings/charset/timezone. Never run destructive commands to generate a backup.
- Active environment and APP_KEY/encryption material, protected separately with restricted access. Keep key identity consistent with encrypted credentials and frozen records; never regenerate it for Git repair.
- Actual storage: public/private media, attachments, immutable quotation/invoice/receipt PDFs, page versions, user uploads and required operational evidence.
- Live public HTML files including owner-edited tracked pages, Blog/Portfolio/new builder pages, mutable assets and any required custom redirects/server config. A redeploy can overwrite file-written changes; Git is not necessarily their latest copy.
- Exact code/build manifest and referenced assets; framework/bootstrap cache should be rebuilt for the restored environment rather than trusted as current production configuration.
- Backup retention and off-deploy copy outside public_html and the Git deployment target; encrypted transport/access controls. No public download link.

## D. Verify before retaining/pruning

- Check every archive/dump tool's exit code, archive entry list/counts and readability. Hash completed artifacts and compare source/control counts privately.
- Check failures/partial files rather than accepting a log line or timestamp. The present page-only SiteBackup implementation does not check every ZIP add/close return and is not sufficient full-backup proof.
- Confirm included data/storage/public paths and size expectations; do not assume excluded folders are backed up elsewhere.
- Do not prune any known-good older copy until a new complete snapshot has been validated and restored successfully. Keep an independent off-deploy copy.

## E. Restore in isolation — never over the live site

- Use a separate approved test database and separate filesystem/storage paths with an explicit isolated identity. This is QA isolation, not a second Website Chat production database.
- Default deny outbound network/provider delivery; disable scheduler/queue/automation activation and use synthetic/fake mail destinations. Preserve the encrypted key privately only where needed for the controlled restore; never use the public dummy test APP_KEY for owner records.
- Restore the verified DB/files/config consistently; verify hashes/control totals/record counts and relationships. Document missing or corrupt artifacts rather than silently rebuilding frozen PDFs or posting history.
- Check representative login/permissions, media, page edits, both-brand frozen PDFs, invoices/receipts, ledger controls, signed-link expectations and secret decryption without exposing any private value.
- Treat source-journal existence as one check, not financial sign-off; inspect amount/account/period/reversal/allocation privately. No automatic financial backfill or correction.
- Record actual completion time, integrity discrepancies and whether the stated recovery point/time were achieved. A prepared Docker file or passing schema check is not this evidence.

## F. Approve rollback and only then a release

- Name the exact code/build/DB/files snapshot to restore, responsible operator and stop/go criteria. Migrations may need a reviewed forward/rollback plan; code rollback alone may be incompatible with a new schema.
- Account for legitimate writes after the snapshot. Never replace recent financial data with the old29October copy or re-send old messages blindly.
- Review restored destination URLs/secrets/caches before re-enabling anything. Resume providers/cron deliberately, audit the backlog and get actual owner receipt for separately approved tests.
- Retain audit80/81 release HOLD until native dependencies/tests/MySQL concurrency/PDF/browser/build identity/security and private backup/restore evidence are accepted. No automatic deployment, master merge or data import follows from this checklist.

## Safe summary for a public readiness report

Only report dates, a non-sensitive evidence label, pass/fail/unknown, scope, counts/hashes approved for disclosure, operator confirmation and outstanding discrepancies. Do not attach the snapshot or any private archive location/key/records. Current status: **UNVERIFIED — operator execution and isolated restore still required.**
