#!/bin/sh
# Logs in to the running preview and requests every parameterless admin GET route; all must return 200.
# Usage: sh smoke.sh [email] [password]
set -e
EMAIL="${1:-owner@woodex.test}"
PASSWORD="${2:-Woodex-Preview-2026}"
PORT="${PORT:-8080}"
BASE="http://127.0.0.1:${PORT}"
APP=/tmp/woodex-run/app
JAR=$(mktemp)
fail=0

# The session cookie is Secure; let curl send it over the local http connection.
unsecure_jar() { sed -i 's/\tTRUE\t\([0-9]*\)\t/\tFALSE\t\1\t/' "$JAR"; }

login_page=$(curl -s -c "$JAR" "$BASE/admin/login")
unsecure_jar
token=$(printf '%s' "$login_page" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
[ -n "$token" ] || token=$(printf '%s' "$login_page" | grep -o '<meta name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"$//')
location=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' \
    --data-urlencode "_token=$token" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD" "$BASE/admin/login")
unsecure_jar
echo "login: $location"

routes=$(cd "$APP" && /tmp/woodex-run/php artisan route:list --json --method=GET 2>/dev/null \
    | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{for(const r of JSON.parse(s)){const u="/"+r.uri.replace(/^\//,"");if(u.startsWith("/admin")&&!u.includes("{")&&!/login|logout|two-factor|password|confirm|export|reset|forgot|register|verify|livewire/.test(u))console.log(u)}})' | sort -u)

for route in $routes; do
    # P10 consolidated the sidebar: legacy URLs 301 to their hub tab. Follow redirects and
    # accept any chain that ends on a real admin page — but never the login screen (an auth
    # bounce must still fail) and never a page outside /admin.
    out=$(curl -s -b "$JAR" -L -o /dev/null -w '%{http_code} %{url_effective}' "$BASE$route")
    code=${out%% *}
    final=${out#* }
    landing=${final#"$BASE"}
    if [ "$code" = "200" ] && [ "${final#"$BASE/admin"}" != "$final" ] && [ "${final#"$BASE/admin/login"}" = "$final" ]; then
        [ "$landing" = "$route" ] && echo "200 $route" || echo "200 $route -> $landing"
    else
        echo "FAIL $code $route"; fail=1
    fi
done

rm -f "$JAR"
[ "$fail" -eq 0 ] && echo "SMOKE OK ($(echo "$routes" | wc -l) pages)" || { echo "SMOKE FAILED"; exit 1; }
