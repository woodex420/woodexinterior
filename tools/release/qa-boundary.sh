#!/bin/sh
# Pure boundary check. Never boots Laravel, runs SQL, reads secret contents or starts a worker.
set -eu
refuse() { echo "HOLD: isolated QA boundary rejected ($1). No application command started." >&2; exit 64; }
[ "${WOODEX_QA_RUN:-}" = 1 ] && [ "${APP_ENV:-}" = testing ] || refuse 'explicit test mode'
# Do not echo any rejected value, including credentials or paths.
for key in DB_URL DB_SOCKET WOODEX_HOME WOODEX_DATA_PATH APP_PUBLIC_PATH APP_CONFIG_CACHE \
    TELEGRAM_BOT_TOKEN TELEGRAM_WEBHOOK_SECRET WHATSAPP_TOKEN WHATSAPP_APP_SECRET \
    GOOGLE_CLIENT_SECRET AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY OPENAI_API_KEY \
    ANTHROPIC_API_KEY GEMINI_API_KEY LEGACY_DB_PASSWORD MAIL_PASSWORD MAIL_URL MAIL_DSN COMPOSER_AUTH PHPRC PHP_INI_SCAN_DIR \
    COMPOSER COMPOSER_VENDOR_DIR; do
    eval 'value=${'"$key"':-}'
    [ -z "$value" ] || refuse 'unexpected private/runtime override'
done
case "${1:-}" in
    --environment-only) ;; # Used to test the boundary itself; never used by a native runner.
    dependencies|tests)
        [ -f /.dockerenv ] && [ -d /workspace/platform ] || refuse 'isolated container/source required'
        for file in /workspace/.env /workspace/.env.testing /workspace/.env.production \
            /workspace/platform/.env /workspace/platform/.env.testing /workspace/platform/.env.production; do
            [ ! -e "$file" ] && [ ! -L "$file" ] || refuse 'runtime env file present'
        done
        [ -f /workspace/composer.lock ] && [ -f /workspace/platform/composer.lock ] || refuse 'both genuine Composer locks required'
        ;;
    *) refuse 'unknown runner mode' ;;
esac
if [ "${1:-}" != dependencies ]; then
    [ "${DB_CONNECTION:-}" = mysql ] && [ "${DB_HOST:-}" = mysql-test ] && \
        [ "${DB_PORT:-}" = 3306 ] && [ "${DB_DATABASE:-}" = woodex_test ] && \
        [ "${DB_USERNAME:-}" = woodex_test ] || refuse 'database target'
    [ "${MAIL_MAILER:-}" = array ] && [ "${QUEUE_CONNECTION:-}" = sync ] && \
        [ "${CACHE_STORE:-}" = array ] && [ "${SESSION_DRIVER:-}" = array ] || refuse 'side-effect drivers'
    [ "${APP_KEY:-}" = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ] || refuse 'public disposable test key required'
fi
