# Native PHP 8.4 + MySQL QA (prepared, not executed in Arena)

This is an **isolated development/test harness**, not a production deployment and not a claim that Docker
Engine is installed in the Arena sandbox. It needs Docker Engine + Compose v2, Python 3 (credential generation),
and access to Docker Hub, Debian and Composer sources on a capable local/CI machine.

```sh
sh tools/docker/setup-env.sh
docker compose --env-file tools/docker/.env -f tools/docker/compose.yaml config --quiet
docker compose --env-file tools/docker/.env -f tools/docker/compose.yaml build php-test
docker compose --env-file tools/docker/.env -f tools/docker/compose.yaml run --rm php-test --compact
docker compose --env-file tools/docker/.env -f tools/docker/compose.yaml down
```

Test credentials are randomly generated and ignored; never paste production credentials into this environment.
MySQL stores disposable data in tmpfs and exposes **no host port**. The source checkout is read-only; vendor,
Laravel storage and bootstrap caches use separate QA volumes. The runner refuses a non-test schema/host/user
or database URL. PHPUnit's MySQL configuration overrides the default SQLite test connection explicitly.
The test APP_KEY is a public dummy, not a secret; do not reuse it for real data.

Run the focused new document tests with `run --rm php-test tests/Feature/DocumentLibraryTest.php --compact`.
All tests can recreate the isolated test schema. Do not point the test runner at a real business database.
No `migrate:fresh` or seed operation is run against the owner's preview or production database by this harness.

**Status:** files prepared; shell/XML structure checked locally. Images have not been built, Compose has not
been executed, and native PHP/MySQL tests have not run in this network-restricted sandbox. A successful serial
MySQL Pest run will still **not** prove simultaneous races: add/run the separate independent-process quote,
approval, invoice, payment, related-writer and sequence race scenarios from docs/17 before closing that gate.
Review/pin image digests and dependencies before using this as a release pipeline. Docker remains optional for
development; Hostinger retains the approved PHP + database queue + cron runtime.
