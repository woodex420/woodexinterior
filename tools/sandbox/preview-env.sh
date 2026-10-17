#!/bin/sh
# Preview-specific .env values for the sandbox copy (/tmp/woodex-run/app/.env). Demo credentials only.
set -e
ENV_FILE=/tmp/woodex-run/app/.env
PORT="${PORT:-8080}"
ORIGIN="https://${PORT}-${E2B_SANDBOX_ID:-sandbox}.e2b.app"

set_env() {
    key="$1"
    value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        # Use | as the sed delimiter; escape any | and & in the value.
        escaped=$(printf '%s' "$value" | sed 's/[|&]/\\&/g')
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL "$ORIGIN"
# Root-relative asset URLs: the browser may reach the preview through a different origin than APP_URL.
set_env ASSET_URL /
set_env DB_CONNECTION sqlite
set_env DB_DATABASE /tmp/woodex-run/app/database/database.sqlite
set_env SESSION_DRIVER database
set_env SESSION_SECURE_COOKIE true
# The preview is shown inside an iframe on another site: cookies must be SameSite=None + Partitioned.
set_env SESSION_SAME_SITE none
set_env SESSION_PARTITIONED_COOKIE true
set_env SECURITY_FRAME_OPTIONS ""
set_env QUEUE_CONNECTION sync
set_env CACHE_STORE database
set_env MAIL_MAILER log
set_env LOG_LEVEL debug
set_env PUBLIC_LEADS_ALLOWED_ORIGINS "$ORIGIN"
set_env OWNER_NAME '"Preview Owner"'
set_env OWNER_EMAIL owner@woodex.test
set_env OWNER_PASSWORD Woodex-Preview-2026

echo "preview env set for $ORIGIN"
