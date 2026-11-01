#!/usr/bin/env bash
# Checked-out, locked source only. No artifact download, solver, hosting or deployment.
set -euo pipefail
hold() { echo "HOLD: branch QA boundary rejected ($1)." >&2; exit 64; }
[[ $# == 0 ]] || hold 'no overrides'
[[ ${GITHUB_ACTIONS:-} == true && ${GITHUB_REPOSITORY:-} == woodex420/woodexinterior && ${RUNNER_ENVIRONMENT:-} == github-hosted ]] || hold 'hosted repository only'
[[ ${GITHUB_REF:-} == refs/heads/arena/8be318a2-woodexinterior ]] || hold 'fixed branch only'
[[ ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ && ${GITHUB_RUN_ATTEMPT:-} =~ ^[0-9]+$ ]] || hold 'run identity'
cd "$(dirname "$0")/../.."
[[ $(git rev-parse HEAD) == "${GITHUB_SHA:-}" ]] || hold 'checkout identity'
for key in COMPOSER_AUTH PHPRC PHP_INI_SCAN_DIR DOCKER_HOST DOCKER_CONTEXT DB_URL DB_PASSWORD MAIL_PASSWORD TELEGRAM_BOT_TOKEN WOODEX_HOME WOODEX_DATA_PATH; do
  [[ -z ${!key:-} ]] || hold 'unexpected private/runtime override'
done
for dir in . platform; do
  for file in "$dir"/.env*; do
    [[ ${file##*/} == .env.example || ( ! -e $file && ! -L $file ) ]] || hold 'runtime environment present'
  done
done
for file in composer.lock platform/composer.lock; do
  [[ -f $file && ! -L $file ]] || hold 'regular versioned locks required'
  git ls-files --error-unmatch "$file" >/dev/null || hold 'untracked locks'
done
[[ ! -e tools/docker/.env && ! -L tools/docker/.env ]] || hold 'fresh disposable environment required'
out=.cache/native-ci
mkdir -p "$out"
compose=(docker compose --project-name "woodex-source-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}" --env-file tools/docker/.env -f tools/docker/compose.yaml)
cleanup() {
  "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -f -- tools/docker/.env
}
sh tools/docker/setup-env.sh
finish() {
  status=$?
  trap - EXIT
  node tools/release/summarize-native.mjs "$out" "$status" || true
  cleanup
  exit "$status"
}
trap finish EXIT
# Docker cannot create nested volume mountpoints under a read-only source bind.
# Create empty ignored mount directories in this disposable checkout before mounting it.
for dir in platform/vendor platform/storage platform/bootstrap/cache; do
  [[ ! -L $dir ]] || hold 'mountpoint symlink'
  mkdir -p "$dir"
done
node tools/release/redact-disposable-qa.mjs --mask
"${compose[@]}" config --quiet
docker build --pull -t woodex-qa-php:local -f tools/docker/Dockerfile tools/docker 2>&1 | tee "$out/build.log"
"${compose[@]}" run --rm --no-deps --entrypoint sh php-deps /workspace/tools/docker/dependencies.sh 2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/install.log"
"${compose[@]}" up --detach --wait mysql-test
# Only this run's disposable volumes. Test as www-data so filesystem permission tests are real.
"${compose[@]}" run --rm --no-deps --user 0:0 --entrypoint sh php-test -ec \
  'mkdir -p /workspace/platform/storage; chown -R 33:33 /workspace/platform/storage /workspace/platform/vendor'
result=0
printf 'group\texit_code\n' > "$out/results.tsv"
for group in unit feature-a-f feature-g-p feature-q-z arch; do
  echo "::group::Native tests: $group"
  mapfile -t files < <(node tools/release/source-test-groups.mjs "$group")
  [[ ${#files[@]} -gt 0 ]] || hold 'empty test group'
  # Run ALL groups even after failure; bounded execution prevents another 45-minute loop.
  if timeout --signal=TERM --kill-after=20s 8m "${compose[@]}" run --rm --no-deps php-test \
      --compact --fail-on-warning --fail-on-skipped --display-all-issues "${files[@]}" \
      2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/$group.log"; then
    if node --input-type=module - "$out/$group.log" <<'JS_CHECK'
import fs from 'node:fs';
import {parseLog} from './tools/release/summarize-native.mjs';
if (!/\b[1-9][0-9]* passed\b/.test(parseLog(fs.readFileSync(process.argv[2],'utf8')).summary??'')) process.exit(1);
JS_CHECK
    then code=0; else code=1; result=1; fi
  else
    code=$?; result=1
  fi
  printf '%s\t%s\n' "$group" "$code" >> "$out/results.tsv"
  echo '::endgroup::'
done
cat "$out/results.tsv"
exit "$result"
