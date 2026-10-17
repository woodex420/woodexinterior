#!/bin/sh
# Rebuilds the whole sandbox runtime after a VM reset (~3-4 min): php-wasm, vendor/, front-end build,
# runtime copy, preview .env and a demo database. Then start the preview with:
#   PORT=8080 node --stack-size=8000 /home/user/woodexinterior/tools/sandbox/server.mjs
set -e
REPO=/home/user/woodexinterior
TOOLS="$REPO/tools/sandbox"
APP=/tmp/woodex-run/app
PHP=/tmp/woodex-run/php
mkdir -p /tmp/phpwasm "$APP" /tmp/resolver

echo "== php-wasm"
if [ ! -d /tmp/phpwasm/node_modules/@php-wasm/node ]; then
    (cd /tmp/phpwasm && npm init -y > /dev/null && npm install --no-audit --no-fund @php-wasm/node@3.1.58 @php-wasm/universal@3.1.58)
fi
cp "$TOOLS/php-cli.mjs" /tmp/phpwasm/php-cli.mjs
printf '#!/bin/sh\nexec node --stack-size=8000 /tmp/phpwasm/php-cli.mjs "$@"\n' > "$PHP"
chmod +x "$PHP"
"$PHP" -r 'echo "PHP ", PHP_VERSION, PHP_EOL;'

echo "== vendor (GitHub-based resolver)"
node --use-system-ca "$TOOLS/resolve.mjs" "$REPO/platform/composer.json" "$APP/vendor" 2> /tmp/resolver/resolve.log
tail -1 /tmp/resolver/resolve.log

echo "== front-end build"
(cd "$REPO/platform" && { [ -d node_modules ] || npm ci --no-audit --no-fund; } && npm run build > /tmp/npm-build.log 2>&1)

echo "== runtime copy + preview env"
sh "$TOOLS/sync.sh"
if [ ! -f "$APP/.env" ]; then
    cp "$APP/.env.example" "$APP/.env"
fi
sh "$TOOLS/preview-env.sh"
cd "$APP"
grep -q '^APP_KEY=base64' .env || "$PHP" artisan key:generate --force
FRESH_DB=0
[ -s database/database.sqlite ] || FRESH_DB=1
touch database/database.sqlite

echo "== database + demo data"
"$PHP" artisan migrate --force
if [ "$FRESH_DB" -eq 1 ]; then
    "$PHP" artisan woodex:install --no-interaction
    "$PHP" artisan db:seed --class='Database\Seeders\DemoSeeder' --force
else
    echo "(existing database kept - no reseed)"
fi

echo "== headless Chromium for browser-check.mjs"
if [ ! -d /tmp/browser/node_modules/puppeteer-core ]; then
    mkdir -p /tmp/browser
    (cd /tmp/browser && npm init -y > /dev/null && npm install --no-audit --no-fund @sparticuz/chromium puppeteer-core > /dev/null) \
        || echo "(browser install failed - browser-check.mjs unavailable)"
fi

echo "== done. Start: PORT=8080 node --stack-size=8000 $TOOLS/server.mjs"
