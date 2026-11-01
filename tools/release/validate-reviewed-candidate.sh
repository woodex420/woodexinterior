#!/usr/bin/env bash
# Candidate-only native Composer/MySQL validation. NEVER production/backup acceptance.
set -euo pipefail
hold() { echo "HOLD: candidate validation boundary rejected ($1)." >&2; exit 64; }
mode=full-suite
case "${1:-}" in
  '') [[ $# == 0 ]] || hold 'no overrides allowed' ;;
  --diagnose-first-failure) [[ $# == 1 ]] || hold 'no overrides allowed'; mode=first-failure-diagnostic ;;
  *) hold 'no overrides allowed' ;;
esac
[[ ${GITHUB_ACTIONS:-} == true && ${GITHUB_REPOSITORY:-} == woodex420/woodexinterior ]] || hold 'authorized CI only'
[[ ${GITHUB_REF:-} == refs/heads/arena/8be318a2-woodexinterior && ${RUNNER_ENVIRONMENT:-} == github-hosted ]] || hold 'fixed ref/hosted runner only'
[[ ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ && ${GITHUB_RUN_ATTEMPT:-} =~ ^[0-9]+$ ]] || hold 'run identity'
for key in DOCKER_HOST DOCKER_CONTEXT COMPOSER_AUTH DB_URL DB_PASSWORD TELEGRAM_BOT_TOKEN AWS_SECRET_ACCESS_KEY PHPRC PHP_INI_SCAN_DIR; do
  [[ -z ${!key:-} ]] || hold 'private/runtime override'
done
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
[[ $(git rev-parse HEAD) == "${GITHUB_SHA:-}" ]] || hold 'checkout identity'
[[ -f .cache/qa85/import-proof.json && ! -e tools/docker/.env && ! -L tools/docker/.env ]] || hold 'review proof/fresh QA environment required'
[[ ! -e .cache/qa85/results && ! -L .cache && ! -L .cache/qa85 ]] || hold 'fresh result directory'
# The artifact importer pins the exact archive/manifests and checks original env absence.
mkdir -p .cache/qa85/results platform/vendor platform/storage platform/bootstrap/cache
out="$PWD/.cache/qa85/results"
project="woodex-qa85-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
compose=(docker compose --ansi never --project-name "$project" --env-file tools/docker/.env -f tools/docker/compose.yaml)
progress() {
  # Fixed mode/stage labels only: no credentials, SQL, test data or hosting state.
  printf '::notice title=Disposable candidate QA progress::mode=%s; stage=%s; production=NOT RUN\n' "$mode" "$1"
}
stage=build
completed=''
qa_env=0
finish() {
  rc=$?
  trap - EXIT
  if [[ $qa_env == 1 ]]; then
    "${compose[@]}" down --volumes --remove-orphans 2>&1 | node tools/release/redact-disposable-qa.mjs > "$out/cleanup.log" || true
    rm -f -- tools/docker/.env
  fi
  python3 - "$out" "$stage" "$completed" "$rc" "$mode" <<'PY'
from pathlib import Path
import json,re,sys
out=Path(sys.argv[1]);stage=sys.argv[2];completed=sys.argv[3].split();rc=int(sys.argv[4]);mode=sys.argv[5]
summary={'scope':'FIRST-FAILURE DIAGNOSTIC ONLY; not a full suite pass' if mode=='first-failure-diagnostic' else 'Reviewed-artifact candidate validation ONLY; not committed-source/release/backup/live acceptance',
 'mode':mode,
 'readyForProductionDeployment':False,'exitCode':rc,'lastStage':stage,'completedStages':completed,
 'backupRestore':'NOT RUN','deployment':'NOT RUN','providerEgress':'internal-only app/test network',
 'applicationTestSummary':None}
p=out/'tests.log'
if p.exists():
 text=re.sub(r'\x1b\[[0-9;]*m','',p.read_text(errors='replace'))
 for line in text.splitlines():
  if re.match(r'^\s*Tests:',line):
   # Only numeric counts/status words, never raw test records/exception/SQL text in annotations.
   tokens=re.findall(r'\d+\s+(?:passed|failed|skipped|warnings?|risky|pending|assertions?)',line)
   if tokens:summary['applicationTestSummary']=', '.join(tokens)
(out/'result.json').write_text(json.dumps(summary,indent=2)+'\n')
message=json.dumps(summary,separators=(',',':')).replace('%','%25').replace('\n','%0A').replace('\r','%0D')
print('::'+('notice' if rc==0 else 'error')+' title=Reviewed artifact validation result::'+message)
if mode=='first-failure-diagnostic' and p.exists():
 tail='\n'.join(text.splitlines()[-65:])[-3000:]
 message=('DIAGNOSTIC ONLY — not full acceptance.\n'+tail).replace('%','%25').replace('\r','%0D').replace('\n','%0A')
 print('::notice title=First native failure — NOT acceptance::'+message)
PY
  exit "$rc"
}
trap finish EXIT
progress build-started
docker build --pull --progress=plain -t woodex-qa-php:local -f tools/docker/Dockerfile tools/docker 2>&1 | tee "$out/build.log"
completed+=' build'
progress build-completed
stage=native-runtime
docker run --rm --network none --read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges \
  --entrypoint php --mount "type=bind,src=$PWD/tools/release,dst=/qa-tools,readonly" \
  woodex-qa-php:local /qa-tools/native-runtime.php | tee "$out/native-runtime.json"
completed+=' native-runtime'
progress native-runtime-completed
sh tools/docker/setup-env.sh
qa_env=1
node tools/release/redact-disposable-qa.mjs --mask
"${compose[@]}" config --quiet
stage=install
# Only the downloader has egress; it has no DB env and runs no project scripts/plugins.
"${compose[@]}" run --rm --entrypoint sh --no-deps php-deps /workspace/tools/release/install-reviewed-dependencies.sh 2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/install.log"
completed+=' install'
progress install-completed
stage=mysql
"${compose[@]}" up --detach --wait mysql-test 2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/mysql.log"
completed+=' mysql'
progress mysql-completed
if [[ $mode == first-failure-diagnostic ]]; then
  stage=application-diagnostic
  progress first-failure-diagnostic-started
  # A separate investigation only; this can NEVER replace the unchanged full gate below.
  "${compose[@]}" run --rm --no-deps php-test --compact --stop-on-failure --stop-on-error --display-warnings 2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/tests.log"
  completed+=' application-diagnostic'
else
# FULL_SUITE_BRANCH
stage=application-tests
progress full-suite-started
# Run every configured Unit/Feature/Arch test, not stop-on-failure or a selected file.
# Skipped/warning outcomes cannot be represented as full acceptance.
"${compose[@]}" run --rm --no-deps php-test --compact --fail-on-skipped --fail-on-warning 2>&1 | node tools/release/redact-disposable-qa.mjs | tee "$out/tests.log"
python3 - "$out/tests.log" <<'PY'
import re,sys
text=re.sub(r'\x1b\[[0-9;]*m','',open(sys.argv[1]).read())
assert re.search(r'Tests:\s*(?:.*?\b)?[1-9][0-9]* passed',text), 'HOLD: no nonempty passing suite count found'
PY
completed+=' application-tests'
fi
stage=complete
