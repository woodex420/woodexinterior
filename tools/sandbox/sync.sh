#!/bin/sh
# Copy the repo's platform/ into the sandbox runtime copy (/tmp/woodex-run/app), keeping the runtime-only
# parts there: vendor/ (from resolve.mjs), .env, the SQLite database and storage/.
set -e
SRC=/home/user/woodexinterior/platform
DST=${SANDBOX_APP_DIR:-/tmp/woodex-run/app}
mkdir -p "$DST"

# Files are overwritten in place (php-wasm caches stat data of deleted paths, so never delete-and-recreate
# while the preview runs); files deleted in the repo are removed from the copy afterwards.
for dir in app bootstrap/app.php bootstrap/providers.php config database/data database/factories database/migrations database/seeders database/settings lang resources routes tests public; do
    if [ -d "$SRC/$dir" ]; then
        mkdir -p "$DST/$dir"
        cp -R "$SRC/$dir/." "$DST/$dir/"
        (cd "$DST/$dir" && find . -type f) | while IFS= read -r file; do
            [ -e "$SRC/$dir/$file" ] || rm -f "$DST/$dir/$file"
        done
    elif [ -f "$SRC/$dir" ]; then
        mkdir -p "$(dirname "$DST/$dir")"
        cp "$SRC/$dir" "$DST/$dir"
    fi
done
for file in artisan composer.json package.json phpunit.xml phpstan.neon pint.json vite.config.js .env.example .gitignore; do
    [ -f "$SRC/$file" ] && cp "$SRC/$file" "$DST/$file"
done

# Repository-root deploy files (Hostinger Git deploy, D-56): the runtime copy mirrors the repository one level up,
# so tests/Unit/DeployManifestTest.php checks them here too.
REPO=$(dirname "$SRC")
mkdir -p "$(dirname "$DST")/tools/deploy"
cp "$REPO/composer.json" "$REPO/.htaccess" "$(dirname "$DST")/"
cp "$REPO/tools/deploy/after-composer.php" "$(dirname "$DST")/tools/deploy/"

# Runtime directories (never deleted: sessions, logs, uploads and compiled views live here).
mkdir -p "$DST/bootstrap/cache" "$DST/storage/app/private" "$DST/storage/app/public" "$DST/storage/framework/cache/data" \
    "$DST/storage/framework/sessions" "$DST/storage/framework/testing" "$DST/storage/framework/views" "$DST/storage/logs" "$DST/database"

# Rebuild the package manifest after a vendor change.
if [ -f "$DST/vendor/composer/installed.json" ] && [ "$DST/vendor/composer/installed.json" -nt "$DST/bootstrap/cache/packages.php" ]; then
    rm -f "$DST/bootstrap/cache/packages.php" "$DST/bootstrap/cache/services.php"
fi

# Sandbox-only test tweak: Mockery-backed console output mocks crash php-wasm.
if ! grep -q 'mockConsoleOutput' "$DST/tests/TestCase.php"; then
    sed -i 's|^    protected \$seeder = ProductionSeeder::class;|&\n\n    public $mockConsoleOutput = false;|' "$DST/tests/TestCase.php"
fi

echo "synced platform -> $DST"
