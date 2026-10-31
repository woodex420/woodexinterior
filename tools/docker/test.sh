#!/bin/sh
set -eu
# Offline native QA only. Both lock files and the disposable target are checked before app commands.
sh /workspace/tools/release/qa-boundary.sh tests
php /workspace/tools/release/native-runtime.php
php /workspace/tools/release/validate-locks.php /workspace
sh /workspace/tools/release/qa-test-arguments.sh "$@"
cd /workspace/platform
[ -f vendor/autoload.php ] || { echo 'HOLD: install locked dependencies with php-deps first.' >&2; exit 2; }
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing \
    storage/framework/views storage/logs storage/app/private bootstrap/cache
# The allowlisted Pest/discovery plugins may prepare vendor metadata here, on the INTERNAL network only.
# No dependency resolution/install or project scripts in the test runner.
composer dump-autoload --no-interaction --no-scripts
composer --no-plugins --no-scripts check-platform-reqs
php artisan config:clear
php artisan package:discover --ansi
php vendor/bin/pest --configuration=/workspace/tools/docker/phpunit.mysql.xml "$@"
