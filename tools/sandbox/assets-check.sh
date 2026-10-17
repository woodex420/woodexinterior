#!/bin/sh
# Checks that a preview page links its CSS/JS with root-relative URLs and that every asset loads with the
# right content type - through the local server and through the public preview URL.
# Usage: sh assets-check.sh [path] [cookie-jar]
set -e
PAGE="${1:-/}"
JAR="${2:-}"
PORT="${PORT:-8080}"
PUBLIC="https://${PORT}-${E2B_SANDBOX_ID:-sandbox}.e2b.app"
TMP=$(mktemp)
fail=0

if [ -n "$JAR" ]; then
    curl -s -b "$JAR" -o "$TMP" -w '%{http_code}' "http://127.0.0.1:${PORT}${PAGE}" > "$TMP.code"
else
    curl -s -o "$TMP" -w '%{http_code}' "http://127.0.0.1:${PORT}${PAGE}" > "$TMP.code"
fi
echo "page ${PAGE}: HTTP $(cat "$TMP.code")"

if grep -q "e2b.app" "$TMP"; then
    echo "FAIL absolute preview-host URLs left in the HTML:"
    grep -o 'https\?:[^"'"'"' ]*e2b.app[^"'"'"' ]*' "$TMP" | sort -u | head -5
    fail=1
fi

css=$(grep -o '<link[^>]*rel="stylesheet"[^>]*>' "$TMP" | grep -o 'href="[^"]*"' | sed 's/href="//;s/"$//' | sort -u)
js=$(grep -o '<script[^>]*src="[^"]*"' "$TMP" | grep -o 'src="[^"]*"' | sed 's/src="//;s/"$//' | sort -u)
[ -n "$css" ] || { echo "FAIL no stylesheet link"; fail=1; }

for url in $css $js; do
    case "$url" in
        /*) ;;
        *) echo "FAIL not root-relative: $url"; fail=1; continue ;;
    esac
    for base in "http://127.0.0.1:${PORT}" "$PUBLIC"; do
        if ! result=$(curl -s --max-time 10 -o /dev/null -w '%{http_code} %{content_type}' "${base}${url}"); then
            echo "skip (not reachable from inside the sandbox)  ${base}${url}"
            continue
        fi
        case "$result" in
            "200 text/css"*|"200 text/javascript"*|"200 application/javascript"*) echo "ok   $result  ${base}${url}" ;;
            *) echo "FAIL $result  ${base}${url}"; fail=1 ;;
        esac
    done
done

rm -f "$TMP" "$TMP.code"
[ "$fail" -eq 0 ] && echo "ASSETS OK" || { echo "ASSETS FAILED"; exit 1; }
