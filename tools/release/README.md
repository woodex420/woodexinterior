# Release prerequisites — read-only inspector and isolated QA boundaries

Implemented under owner approval to start audit80's recommended prerequisites. This tooling does not fix business features, migrate data, access hosting or grant release approval.

```sh
node tools/release/preflight.mjs
node --test tools/release/preflight.test.mjs
```

`preflight.mjs` takes no flags, reads curated source metadata only, checks the fixed branch and dirty application paths, inspects native PHP metadata only when a real Linux ELF executable is found, and may ask Composer for its version with plugins/scripts disabled. It never boots Laravel, installs/resolves dependencies, reads env contents, runs SQL, contacts a provider or deploys. It never prints credential values, command stderr, private env contents or full dirty-path lists.

**Exit2 / HOLD** means prerequisites are missing/unsafe. **Exit0 / PREREQUISITES_ONLY** is not a release pass. `readyForProductionDeployment` is always false: native suite, finance reconciliation, private backup/restore, PDF/browser/security and exact build acceptance require separate evidence and authority. Neither fabricated lock JSON nor a manual boolean can certify a release.

- `preflight.test.mjs`: synthetic contract fixtures and real shell environment-boundary checks; no real lock resolution/native DB/application acceptance.
- `qa-boundary.sh`: checks container/test intent, fixed disposable target/side-effect drivers, absent original envs/locks and rejects private/runtime overrides. `--environment-only` is only a pure guard test and never used by an application runner.
- `qa-test-arguments.sh`: pure argument allowlist; only known display/stop flags or clean `tests/*.php` paths. No config/bootstrap/extension/parallel overrides.
- `native-runtime.php`: no app bootstrap; requires actual native Linux PHP8.5 CLI and expected extensions. PHP-WASM must fail.
- `validate-locks.php`: no autoload/network/writes; sanity-checks both manifests/lock metadata and production version/reference alignment. Composer validation/install remains mandatory; this helper cannot establish origin or solver correctness.
- [Docker harness](../docker/README.md): prepared PHP8.5/internal-network MySQL native path. Not executed in the current sandbox.
- [Private backup/restore checklist](PRIVATE_BACKUP_RESTORE_CHECKLIST.md): operator procedure, not a restore command or evidence it happened.

The genuine locks are still absent. The root `.gitignore` now allows a real `composer.lock` to be versioned after compatible native resolution and review. No hand-made lock or dependency-manifest alteration is supplied. Composer2/Packagist/native-runtime access needs a capable authorized environment.

Keep auto-deployOFF. Do not use `composer setup`, `key:generate`, `migrate:fresh`, `woodex:update`, queue workers or `schedule:run` as a connection/readiness check. No production/native acceptance is inferred from successful guard tests.
