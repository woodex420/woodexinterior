#!/usr/bin/env bash
# Full-suite gate for the wasm PHP sandbox. Pest runs one shard per process because a single
# process exhausts wasm memory on this suite (known sandbox gotcha). Ends with SUMMARY + SHARD-DONE.
# Usage: bash /tmp/woodex-run/shard-gate.sh [shard-count]
set -u

APP=/tmp/woodex-run/app
NODE=/tmp/phpwasm/node_modules/.bin/node
PHP="/tmp/phpwasm/php-cli.mjs"
SHARDS="${1:-9}"
LOG=/tmp/shard-gate.log

cd "$APP" || { echo "no app dir"; exit 1; }

mapfile -t FILES < <(find tests -name "*Test.php" | sort)
TOTAL=${#FILES[@]}
PER=$(( (TOTAL + SHARDS - 1) / SHARDS ))

: > "$LOG"
PASS_TOTAL=0
FAIL_TOTAL=0
FAILED_SHARDS=0

for (( s=0; s<SHARDS; s++ )); do
  CHUNK=("${FILES[@]:s*PER:PER}")
  [ ${#CHUNK[@]} -eq 0 ] && continue

  echo "== shard $((s+1))/${SHARDS} (${#CHUNK[@]} files)" | tee -a "$LOG"
  OUT=$(timeout 1200 "$NODE" --experimental-wasm-jspi --stack-size=8000 "$PHP" vendor/bin/pest "${CHUNK[@]}" --compact -d memory_limit=1400M 2>&1 | sed 's/\x1b\[[0-9;]*m//g')
  echo "$OUT" >> "$LOG"

  LINE=$(echo "$OUT" | grep -E "Tests:.*passed" | tail -1)
  P=$(echo "$LINE" | grep -oE "[0-9]+ passed" | grep -oE "[0-9]+" || echo 0)
  F=$(echo "$LINE" | grep -oE "[0-9]+ failed" | grep -oE "[0-9]+" || echo 0)
  PASS_TOTAL=$((PASS_TOTAL + P))
  FAIL_TOTAL=$((FAIL_TOTAL + F))
  [ "${F:-0}" -gt 0 ] && FAILED_SHARDS=$((FAILED_SHARDS + 1))
  echo "   $LINE" | tee -a "$LOG"
done

echo "SUMMARY: $TOTAL files | $PASS_TOTAL passed | $FAIL_TOTAL failed | $FAILED_SHARDS failing shards" | tee -a "$LOG"
echo "SHARD-DONE"
[ "$FAIL_TOTAL" -eq 0 ] || exit 1
