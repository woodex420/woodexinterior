# Current GitHub QA

Push reviewed changes to `arena/8be318a2-woodexinterior`; **Native branch tests**
runs automatically. No magic commit message or archived dependency candidate is
needed. Both genuine Composer locks are versioned. The workflow can also be run
manually once GitHub registers it on the default branch.

Open the `first-failure-diagnostic` check and inspect all five native test groups.
The historical name is retained, but the current runner collects all groups and
fails on failures, errors, warnings, skipped tests or timeout. A cancelled or
incomplete run is not a pass. No merge or deployment follows automatically.

Only synthetic QA logs/results are uploaded. Never upload `.env`, application
storage, database dumps, vendor folders or production backups as QA evidence.

For deployment prerequisites and the production HOLD boundary, see `README.md`.
