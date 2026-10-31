# Source-only manual native QA on the existing branch

Owner-selected environment: GitHub Actions. This is development/readiness QA, **never deployment, production access or a release certificate**. Keep Hostinger auto-deploy OFF.

## Explicit opt-in — not routine push/PR automation

`.github/workflows/source-native-qa.yml` has two ways to start a job:

1. `workflow_dispatch`, **only after GitHub has registered the workflow on the default branch**, selecting an operation and explicitly setting `confirm_source_only=true`. It still only accepts `arena/8be318a2-woodexinterior`.
2. A deliberately pushed source commit on **that same fixed branch**, whose **entire commit message** is exactly one of:
   - `[woodex-qa:resolve-locks] source-only manual QA`
   - `[woodex-qa:locked-tests] source-only manual QA`

The second path avoids changing/merging/repointing `master` merely to register a manual workflow. It is a **push event with an exact manual opt-in gate**, not a claim of dispatch-only configuration. Ordinary pushes produce a skipped workflow (no QA job); PRs, timers, tags and other branches do not start it. Never add these markers to an unrelated commit or use a new branch/force push. A code review, normal push and deliberate opt-in are still required. No job starts simply because a workflow exists.

At preparation time, Actions workflow listing returned zero workflows, `master` had no workflows directory, and the Actions policy read returned403. A policy-read denial does not prove whether workflow-file publication/dispatch/run permissions work. Only actual operation results can establish that. If publication is rejected for missing workflow permission, the Arena GitHub connection needs the corresponding permission/reconnection; never place a token or credentials in chat/source. No policy setting/default branch is changed by this tooling.

## First operation: genuine dependency-lock candidates

Current source has neither genuine lock. Start **resolve-lock-candidates** first. The job:

- Uses GitHub-hosted Linux only, a source-only checkout of the exact SHA, pinned official checkout/artifact actions, `contents:read` and no persisted Git credentials, repository/environment secrets or production mounts.
- Runs Node guard contracts (not native application tests), builds the prepared official PHP8.5/Composer2 image and records its ID/plain BuildKit resolved-image log. Image tags remain mutable; this exploratory native run is not a digest-pinned release pipeline.
- Verifies actual Linux ELF PHP8.5 CLI/extensions in a no-network container.
- Copies **only the two public source `composer.json` manifests** into fresh output folders. Each Composer resolver container sees only its manifest/output, fresh temporary Composer home/cache and package-download network. No app/DB/env/provider configuration, original locks, `.git`, vendor, uploads or backup is mounted.
- Executes real Composer `update --no-install` with plugins/project scripts disabled; then strict native Composer validation. No Artisan/bootstrap/autoload/install is run. No manually constructed lock/content-hash is accepted as solver evidence.
- Checks independently resolved root/app production version/reference alignment. An unavailable declared package/version, solver conflict, native-image failure or alignment mismatch is a genuine HOLD. Do not weaken guards, silently edit dependency constraints or call it a pass.
- Uploads only fresh candidate manifests/locks and curated native/solver evidence for seven days. Output is for review, **never automatically committed or deployed**. The upload step also runs on failure so partial evidence can be inspected; partial output is not a usable lock pair.

After a successful solve, review genuine candidate origin/content, advisory/compatibility results, root/app graph alignment and exact source scope before versioning both locks on this branch. A successful solve is NOT dependency installation, Laravel/Pest/MySQL acceptance, security clearance or production readiness.

## Second operation: reviewed locked native suite

Only after both genuine reviewed locks are versioned, manually select **locked-tests**. Missing/untracked locks or original runtime env files cause HOLD before Docker/application execution.

It uses the prepared [Docker harness](../docker/README.md): dependency download with project scripts/plugins disabled; read-only source; synthetic storage/vendor/cache; internal-only PHP/MySQL test network; no host ports; explicit test target/drivers/dummy key. New random disposable QA credentials are generated in `tools/docker/.env`, never an original app env. Only the unique run's disposable resources are removed on exit. Provider egress is unavailable in the application test container.

Artifacts are limited to `.cache/qa82/evidence` (synthetic QA logs/provenance), not an entire workspace, DB, storage, vendor, env or backup archive. The private production backup/isolated restore remains a [separate operator gate](PRIVATE_BACKUP_RESTORE_CHECKLIST.md).

This serial suite does not certify multi-process financial races, both-brand PDF/browser rendering, live integrations, private data reconciliation, exact deployment/build acceptance or backup/restore. Native failures must be investigated without widening isolation or uploading owner data. No migration/import against production, merge, deployment, provider send, automatic lock adoption or auto-deploy toggle is included.

## Local source tests only

```sh
node --test tools/release/preflight.test.mjs tools/release/ci-native.test.mjs
bash -n tools/release/ci-native.sh
```

These are guard/source tests, not an executed GitHub workflow or native runtime. The CI entrypoint intentionally rejects this local sandbox; faking GitHub metadata is not native acceptance. Actual run URL, source SHA, result and reviewed artifact evidence are required before any CI result is reported as executed.
