#!/bin/sh
set -eu
# Networked dependency install ONLY. No Laravel, DB credentials, env files, plugins or project scripts.
sh /workspace/tools/release/qa-boundary.sh dependencies
php /workspace/tools/release/native-runtime.php
php /workspace/tools/release/validate-locks.php /workspace
cd /workspace
composer --no-plugins --no-scripts validate --strict --no-check-publish
# Verify both production and development graphs without executing the Hostinger hook.
composer --no-plugins --no-scripts install --no-dev --no-interaction --prefer-dist --no-progress
composer --no-plugins --no-scripts check-platform-reqs --no-dev
cd /workspace/platform
composer --no-plugins --no-scripts validate --strict --no-check-publish
composer --no-plugins --no-scripts install --no-interaction --prefer-dist --no-progress
composer --no-plugins --no-scripts check-platform-reqs
