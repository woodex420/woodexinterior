# Branch validation — one source of truth

The active workflow is `.github/workflows/native-app-validation.yml`. It checks
out the session branch and installs its **tracked** root and platform Composer
locks. It does not download an Actions archive, resolve new dependencies, connect
to Hostinger, run a production migration, or deploy.

`test-branch.sh` builds native PHP 8.5, validates both lock graphs, installs with
project scripts/plugins disabled, and runs all configured Unit/Feature/Arch tests
against synthetic internal-network MySQL. Tests run as www-data, not root, so
filesystem permission assertions are meaningful. Five exhaustive groups run even
if an earlier group fails. Each group has an eight-minute limit; the job has a
25-minute limit. Warnings and skipped tests fail the check.

The legacy check name `first-failure-diagnostic` is retained for continuity. It
now validates committed source rather than stopping at the first application
failure. Read the five log groups and the `native-tests-<sha>` artifact for results.

The old artifact importer, solver workflow and warning-inspection loop are retired.
Historical audit reports are historical evidence, not current deployment approval.

## Deployment remains separate

Keep Hostinger auto-deploy OFF. Preserve the current repository, branch, deploy
path, APP_KEY, external environment, database and uploads. Do not reconnect Git.

Before any production write: require a passing native run, review pending
migrations on a restored copy, take a fresh consistent files/database backup and
verify its isolated restoration. Only then perform an owner-approved manual
Hostinger deployment and verify login, permissions, financial/PDF flows and public
forms. Do not assume redeploying Git updates the database: the checked-in Composer
hook prepares files/caches, not a verified production database upgrade.

See `PRIVATE_BACKUP_RESTORE_CHECKLIST.md`. No production backup or restore has been
performed by this CI workflow.
