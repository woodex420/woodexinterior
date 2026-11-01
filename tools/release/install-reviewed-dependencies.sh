#!/bin/sh
# Download/install only. No DB environment, original env, plugins, project scripts or app bootstrap.
set -eu
sh /workspace/tools/release/qa-boundary.sh dependencies
php /workspace/tools/release/native-runtime.php
php /workspace/tools/release/validate-locks.php /workspace
cd /workspace
composer --no-plugins --no-scripts validate --strict --no-check-publish
# Prove the production/root lock installs, without running the Hostinger post-install hook.
composer --no-plugins --no-scripts install --no-dev --no-interaction --prefer-dist --no-progress
composer --no-plugins --no-scripts check-platform-reqs --no-dev
cd /workspace/platform
composer --no-plugins --no-scripts validate --strict --no-check-publish
# Add the unchanged development graph for the full configured test suite.
composer --no-plugins --no-scripts install --no-interaction --prefer-dist --no-progress
composer --no-plugins --no-scripts check-platform-reqs
