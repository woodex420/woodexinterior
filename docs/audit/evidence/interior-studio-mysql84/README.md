# Studio PHP 8.4 / genuine MySQL 8.4 — explicitly authorized hosted validation

## Execution status

**BLOCKED at workflow dispatch; genuine MySQL tests have NOT RUN.**

The reviewed Website snapshot was committed and pushed to the approved branch as `41c21f30e9b8ac8fd349084de33f445fe2659120`. `gh workflow run native-app-validation.yml --ref arena/3727507a-woodexinterior` was rejected with **HTTP 403: Resource not accessible by integration**. A subsequent branch run list was empty. `dispatch.json` records the nonsecret facts. The follow-up handoff commit adds contract checks, an unexecuted test-harness deadlock-wrapper correction and this report; a future run must record the actual branch-tip SHA it tests, not assume the first snapshot SHA.

No push trigger, alternate credential or provider route was added to work around the denied dispatch. The app integration needs Actions-run permission, or the repository owner can manually start the already-published workflow.

### Required manual handoff

1. Open [Native branch tests — Actions](https://github.com/woodex420/woodexinterior/actions/workflows/native-app-validation.yml).
2. Select **Run workflow** and choose branch **`arena/3727507a-woodexinterior`** (not `master`).
3. Click **Run workflow**. The `studio-mysql84` job is restricted to this branch; the older native job is skipped here.
4. Share the resulting run URL, or ask the agent to check it. The source identity, actual MySQL/PHP versions and result must be reviewed before any passing MySQL claim.

Alternatively reconnect GitHub in Arena with permission to run Actions. Do not send tokens/passwords. A manual job may reveal test failures that require another scoped source correction; the prepared MySQL fixture is **not runtime-validated** yet.

The user explicitly approved switching from blocked local runtime uploads to a disposable GitHub Actions runner, including reviewing and committing/pushing the necessary Website source snapshot and workflow on `arena/3727507a-woodexinterior`. No merge, deployment, production secrets/database or provider operation beyond this GitHub test job is authorized.

The snapshot includes the preceding bounded Website private drafts/resources/rendering/listing/SEO/recovery implementation and its tests, because those working-tree files were not yet in the branch baseline. It excludes unrelated inherited work, private runtime state, `.env`, credentials, vendor dependencies, generated databases, binary downloads and old evidence exports. Dependency locks and original 142 public HTML files are unchanged. Baseline: `603428cca1e45f9da2ffd9c4a0d88eb741285339`.

## Manual workflow boundary

The existing `native-app-validation.yml` retains its existing push triggers unchanged; they do not include this branch. The new `studio-mysql84` job is restricted to **workflow_dispatch + this exact branch + GitHub-hosted runner**. Permissions are contents-read. Pinned checkout/setup-PHP/artifact actions, locked Composer install with application scripts disabled, native PHP 8.4 and a disposable `mysql:8.4` service. MySQL is published only on the runner's `127.0.0.1:33060`; there is no remote/production host override. No repository/environment secrets are referenced. An empty root password belongs only to this temporary local service; application actors use a random temporary account restricted to newly created synthetic schemas.

The harness verifies actual server version/comment, creates unique databases without adopting existing schemas, applies real migrations only there and drops only databases/account that it successfully created. Private per-scenario credentials and fixture payloads are stored outside the artifact allowlist. GitHub destroys the service/runner at job end. The test adapter refuses other repositories/refs/non-hosted contexts and unguarded database targets.

## Local checks actually completed (not MySQL acceptance)

- **17 Node contract tests PASS**: six new workflow/guard/source-scope checks plus eleven existing source-CI checks (`contracts.log`).
- **Pint six PHP fixture/harness files PASS**, PHP syntax six files PASS, parsed workflow YAML PASS and `git diff --check` PASS (`pint.log`, `syntax.log`).
- The actual PHP guard rejects this unapproved local context before application/bootstrap/database access (`syntax.log`).
- No local or hosted MySQL test was executed, no test server/schema was initialized, no hosted artifact was created, and no Website/PHPStan/browser/build suite was rerun in this increment. Prior SQLite evidence remains historical, not MySQL certification.
- Only the scoped source snapshot/workflow and handoff files were committed/pushed. Unrelated inherited changes and all earlier evidence remain in place. `preservation.json` compares file bytes to the pre-CI inventory; commits alone do not count as content changes.

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


## Package integrity and remaining authorization

Tool-log trailing whitespace is normalized without changing test outcomes. `source-sha256.txt` pins this CI workflow/harness/contract-test source and STATE. `SHA256SUMS` covers the README, local diagnostics, dispatch facts and preservation report, excluding itself. It contains no runtime downloads, environment, credentials, vendor tree or private test fixtures. Older evidence is unchanged. The user approved scoped commits/pushes and this synthetic GitHub test run only; no PR/merge/deployment/production operation was performed or implied.
