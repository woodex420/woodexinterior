#!/bin/sh
set -eu
# Refuse accidental destructive test execution against anything except the isolated Compose database.
[ "${APP_ENV:-}" = testing ] && [ "${DB_CONNECTION:-}" = mysql ] && \
[ "${DB_HOST:-}" = mysql-test ] && [ "${DB_PORT:-}" = 3306 ] && \
[ "${DB_DATABASE:-}" = woodex_test ] && [ "${DB_USERNAME:-}" = woodex_test ] && \
[ -z "${DB_URL:-}" ] || { echo 'Refusing: this is not the isolated Woodex QA database.' >&2; exit 64; }
cd /workspace/platform
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing \
    storage/framework/views storage/logs storage/app/private bootstrap/cache
# Source is read-only; Composer may only populate its isolated volume, not update a lock file.
composer install --no-interaction --prefer-dist --no-scripts
php artisan config:clear
php artisan package:discover --ansi
php vendor/bin/pest --configuration=/workspace/tools/docker/phpunit.mysql.xml "$@"
