# Studio PHP 8.4 / genuine MySQL 8.4 — explicitly authorized hosted validation

## Execution status

**Pending first hosted execution. This file is not a passing MySQL acceptance report.**

The user explicitly approved switching from blocked local runtime uploads to a disposable GitHub Actions runner, including reviewing and committing/pushing the necessary Website source snapshot and workflow on `arena/3727507a-woodexinterior`. No merge, deployment, production secrets/database or provider operation beyond this GitHub test job is authorized.

The snapshot includes the preceding bounded Website private drafts/resources/rendering/listing/SEO/recovery implementation and its tests, because those working-tree files were not yet in the branch baseline. It excludes unrelated inherited work, private runtime state, `.env`, credentials, vendor dependencies, generated databases, binary downloads and old evidence exports. Dependency locks and original 142 public HTML files are unchanged. Baseline: `603428cca1e45f9da2ffd9c4a0d88eb741285339`.

## Manual workflow boundary

The existing `native-app-validation.yml` retains its existing push triggers unchanged; they do not include this branch. The new `studio-mysql84` job is restricted to **workflow_dispatch + this exact branch + GitHub-hosted runner**. Permissions are contents-read. Pinned checkout/setup-PHP/artifact actions, locked Composer install with application scripts disabled, native PHP 8.4 and a disposable `mysql:8.4` service. MySQL is published only on the runner's `127.0.0.1:33060`; there is no remote/production host override. No repository/environment secrets are referenced. An empty root password belongs only to this temporary local service; application actors use a random temporary account restricted to newly created synthetic schemas.

The harness verifies actual server version/comment, creates unique databases without adopting existing schemas, applies real migrations only there and drops only databases/account that it successfully created. Private per-scenario credentials and fixture payloads are stored outside the artifact allowlist. GitHub destroys the service/runner at job end. The test adapter refuses other repositories/refs/non-hosted contexts and unguarded database targets.

## Planned checks — results must be read from the actual run

- Actual Blog/Portfolio Studio private save/stale revision/Preview parity/public-unlisted-public/withdrawal lifecycle.
- Actual exception compensation at six boundaries, both types and publish/withdrawal.
- Real application-process SIGKILLs at eight boundaries (including committed acknowledgement and uncommitted ambient transaction), competing writer rejection, repeat recovery and interruption during rollback.
- Guarded KILL CONNECTION on this synthetic account/schema before and after commit, followed by fresh-connection reconciliation. Not universal network/server/power-loss certification.
- Actual receipt reconciliation with an already-open REPEATABLE READ snapshot, and READ COMMITTED comparison, preserving committed file bytes.
- Actual published-resource shared-lock timeout 1205, stale promotion rejection, inverse-row-lock StudioResources deadlock 1213 and atomic survivor.
- Real migration InnoDB table engines; Website SQLite regression remains explicitly SQLite, not renamed MySQL coverage.

Artifacts are allowlisted synthetic result JSON/logs/source checksums only. No whole `.cache`, storage, environment, credential, private fixture, database or vendor upload. Test stdout is redacted for the generated account/password before being included in failures.

## Remaining boundary

Until the actual hosted run completes and its artifacts are reviewed, MySQL acceptance is pending. Full application CI outside Website, staging/owner approval, host capabilities/storage/restore/SSL/cron and production release remain separate gates. All 16 findings retained; Phases 3–6 remain unapproved. No production-readiness claim follows from passing CI.
