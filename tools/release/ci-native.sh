#!/usr/bin/env bash
# GitHub-hosted SOURCE-ONLY QA. No hosting, deployment, original env or production DB.
set -euo pipefail
hold() { printf 'HOLD: source-only CI boundary rejected (%s).\n' "$1" >&2; exit 64; }
[[ $# == 1 ]] || hold 'one operation required'
mode=$1
[[ $mode == resolve-lock-candidates || $mode == locked-tests ]] || hold 'unknown operation'
[[ ${GITHUB_ACTIONS:-} == true && ${GITHUB_REPOSITORY:-} == woodex420/woodexinterior ]] || hold 'authorized GitHub repository only'
[[ ${GITHUB_REF:-} == refs/heads/arena/8be318a2-woodexinterior ]] || hold 'fixed session ref only'
[[ ${RUNNER_ENVIRONMENT:-} == github-hosted && ${RUNNER_OS:-} == Linux ]] || hold 'disposable GitHub-hosted Linux only'
[[ ${GITHUB_SHA:-} =~ ^[a-f0-9]{40}$ && ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ && ${GITHUB_RUN_ATTEMPT:-} =~ ^[0-9]+$ ]] || hold 'exact run identity required'
for name in COMPOSER_AUTH COMPOSER COMPOSER_VENDOR_DIR PHPRC PHP_INI_SCAN_DIR DOCKER_HOST DOCKER_CONTEXT \
    DB_URL DB_PASSWORD MAIL_PASSWORD TELEGRAM_BOT_TOKEN AWS_SECRET_ACCESS_KEY; do
  [[ -z ${!name:-} ]] || hold 'unexpected private/runtime override'
done
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)
cd "$root"
[[ $(git rev-parse HEAD) == "$GITHUB_SHA" ]] || hold 'checkout SHA differs from run'
# No content reads of env files. A source-only checkout must not carry original runtime data.
for dir in "$root" "$root/platform"; do
  for file in "$dir"/.env*; do
    [[ $(basename "$file") == .env.example ]] && continue
    [[ ! -e $file && ! -L $file ]] || hold 'runtime env present'
  done
done
for file in composer.json platform/composer.json; do
  [[ -f $file && ! -L $file ]] || hold 'regular source manifests required'
done
if [[ $mode == locked-tests ]]; then
  for file in composer.lock platform/composer.lock; do
    [[ -f $file && ! -L $file ]] || hold 'both reviewed genuine locks required before tests'
    git ls-files --error-unmatch -- "$file" >/dev/null 2>&1 || hold 'both locks must be versioned'
  done
  [[ ! -e tools/docker/.env && ! -L tools/docker/.env ]] || hold 'fresh disposable QA environment required'
fi
[[ ! -L .cache && ! -e .cache/qa82 && ! -L .cache/qa82 ]] || hold 'fresh evidence directory required'
mkdir -p .cache/qa82/evidence
out="$root/.cache/qa82"
# Curated non-secret provenance only; never dump the runner/container environment.
printf '{"sourceSha":"%s","runId":"%s","attempt":"%s","mode":"%s","readyForProductionDeployment":false}\n' \
  "$GITHUB_SHA" "$GITHUB_RUN_ID" "$GITHUB_RUN_ATTEMPT" "$mode" > "$out/evidence/run.json"
docker version --format '{{.Server.Version}}' > "$out/evidence/docker-version.txt"
docker build --pull --progress=plain --tag woodex-qa-php:local --file tools/docker/Dockerfile tools/docker 2>&1 | tee "$out/evidence/image-build.log"
# BuildKit's resolved FROM digests remain in the plain build log. Intermediate
# stages need not exist as local image tags; do not assume docker inspect can find them.
docker image inspect woodex-qa-php:local --format '{{.Id}}' > "$out/evidence/native-image-id.txt"
# Native ELF/extensions are checked with no network, no original env, no app bootstrap.
docker run --rm --network none --read-only --tmpfs /tmp --cap-drop ALL \
  --security-opt no-new-privileges --entrypoint php \
  --mount "type=bind,src=$root/tools/release,dst=/qa-tools,readonly" \
  woodex-qa-php:local /qa-tools/native-runtime.php | tee "$out/evidence/native-runtime.json"
if [[ $mode == resolve-lock-candidates ]]; then
  mkdir -p "$out/locks/root" "$out/locks/platform"
  cp composer.json "$out/locks/root/composer.json"
  cp platform/composer.json "$out/locks/platform/composer.json"
  # Only these two public source manifests and their fresh output folders are writable/mounted.
  # Never mount the app, .git, env, uploads, vendor, original locks or production storage.
  for project in root platform; do
    docker run --rm --read-only --tmpfs /tmp --cap-drop ALL \
      --security-opt no-new-privileges --pids-limit 256 --memory 4g --cpus 2 \
      --user "$(id -u):$(id -g)" --workdir /candidate --entrypoint sh \
      --env HOME=/tmp/qa-home --env COMPOSER_HOME=/tmp/qa-composer \
      --env COMPOSER_CACHE_DIR=/tmp/qa-cache \
      --mount "type=bind,src=$out/locks/$project,dst=/candidate" \
      woodex-qa-php:local -eu -c '
        composer --no-plugins --no-scripts --version
        composer --no-plugins --no-scripts update --no-install --no-interaction --prefer-dist --no-progress
        composer --no-plugins --no-scripts validate --strict --no-check-publish
      ' 2>&1 | tee "$out/evidence/resolve-$project.log"
  done
  # Present both genuine independently resolved locks to the existing alignment helper.
  mkdir -p "$out/locks/paired/platform"
  cp "$out/locks/root/composer.json" "$out/locks/root/composer.lock" "$out/locks/paired/"
  cp "$out/locks/platform/composer.json" "$out/locks/platform/composer.lock" "$out/locks/paired/platform/"
  docker run --rm --network none --read-only --tmpfs /tmp --cap-drop ALL \
    --security-opt no-new-privileges --entrypoint php \
    --mount "type=bind,src=$root/tools/release,dst=/qa-tools,readonly" \
    --mount "type=bind,src=$out/locks/paired,dst=/candidates,readonly" \
    woodex-qa-php:local /qa-tools/validate-locks.php /candidates | tee "$out/evidence/lock-alignment.json"
  printf '%s\n' 'CANDIDATES ONLY: review required. No install, app bootstrap, test, DB, commit or deployment.'
else
  project="woodex-qa82-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
  compose=(docker compose --project-name "$project" --env-file tools/docker/.env -f tools/docker/compose.yaml)
  cleanup() {
    # Only this run's uniquely named disposable QA resources; never a production project.
    "${compose[@]}" down --volumes --remove-orphans || true
    rm -f -- tools/docker/.env
  }
  sh tools/docker/setup-env.sh
  trap cleanup EXIT
  "${compose[@]}" config --quiet
  "${compose[@]}" run --rm php-test --compact 2>&1 | tee "$out/evidence/locked-tests.log"
fi
