import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

// Boundary/source contracts only. Never execute Docker, install packages or claim native acceptance.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const script = path.join(root, 'tools/release/ci-native.sh');
const source = fs.readFileSync(script, 'utf8');
const workflow = fs.readFileSync(path.join(root, '.github/workflows/source-native-qa.yml'), 'utf8');
const fixtureSecret = 'SYNTHETIC_REJECTED_VALUE_DO_NOT_PRINT';
const valid = {
  PATH: process.env.PATH,
  GITHUB_ACTIONS: 'true', GITHUB_REPOSITORY: 'woodex420/woodexinterior',
  GITHUB_REF: 'refs/heads/arena/8be318a2-woodexinterior',
  RUNNER_ENVIRONMENT: 'github-hosted', RUNNER_OS: 'Linux',
  GITHUB_SHA: '0'.repeat(40), GITHUB_RUN_ID: '123', GITHUB_RUN_ATTEMPT: '1',
};
function rejected(name, args, changes, pattern) {
  test(name, () => {
    const result = spawnSync('bash', [script, ...args], { cwd: root, env: { ...valid, ...changes }, encoding: 'utf8' });
    assert.equal(result.status, 64, result.stderr);
    assert.match(result.stderr, pattern);
    assert(!`${result.stdout}${result.stderr}`.includes(fixtureSecret));
  });
}
rejected('reject no operation', [], {}, /one operation/);
rejected('reject extra arguments', ['locked-tests', '--config=unsafe'], {}, /one operation/);
rejected('reject command injection/unknown mode', ['resolve-lock-candidates; echo unsafe'], {}, /unknown operation/);
for (const [key, value, message] of [
  ['GITHUB_ACTIONS', '', /authorized GitHub/],
  ['GITHUB_REPOSITORY', 'other/repository', /authorized GitHub/],
  ['GITHUB_REF', 'refs/heads/master', /fixed session ref/],
  ['GITHUB_REF', 'refs/pull/1/merge', /fixed session ref/],
  ['RUNNER_ENVIRONMENT', 'self-hosted', /GitHub-hosted/],
  ['RUNNER_OS', 'Windows', /GitHub-hosted/],
  ['GITHUB_SHA', 'not-an-exact-commit', /exact run identity/],
  ['GITHUB_RUN_ID', '123;echo unsafe', /exact run identity/],
  ['GITHUB_RUN_ATTEMPT', '', /exact run identity/],
]) rejected(`reject boundary ${key}=${value}`, ['resolve-lock-candidates'], { [key]: value }, message);
for (const key of ['COMPOSER_AUTH', 'COMPOSER', 'COMPOSER_VENDOR_DIR', 'PHPRC', 'PHP_INI_SCAN_DIR', 'DOCKER_HOST', 'DOCKER_CONTEXT', 'DB_URL', 'DB_PASSWORD', 'MAIL_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'AWS_SECRET_ACCESS_KEY']) {
  rejected(`reject private/runtime override ${key} without echoing value`, ['resolve-lock-candidates'], { [key]: fixtureSecret }, /unexpected private\/runtime override/);
}
rejected('reject checkout SHA mismatch before Docker', ['resolve-lock-candidates'], {}, /checkout SHA differs/);

test('workflow is limited to fixed branch and exact opt-in commit messages', () => {
  assert.match(workflow, /branches: \[arena\/8be318a2-woodexinterior\]/);
  assert.equal((workflow.match(/github\.event\.head_commit\.message == /g) || []).length, 2);
  assert.match(workflow, /== '\[woodex-qa:resolve-locks\] source-only manual QA'/);
  assert.match(workflow, /== '\[woodex-qa:locked-tests\] source-only manual QA'/);
  assert.equal((workflow.match(/inputs\.confirm_source_only == true/g) || []).length, 2);
  assert(!/^\s*(pull_request|pull_request_target|schedule|workflow_run|repository_dispatch):/m.test(workflow));
});
test('no deployment, mutable action tag, credentials persistence or secret references in workflow', () => {
  assert(!/secrets\.|id-token:|contents: write|actions: write|environment:|ssh |scp |ftp |git push/.test(workflow));
  const uses = [...workflow.matchAll(/uses:\s+(\S+)/g)].map(m => m[1]);
  assert.equal(uses.length, 4);
  assert(uses.every(v => /^actions\/(checkout|upload-artifact)@[a-f0-9]{40}$/.test(v)));
  assert.equal((workflow.match(/persist-credentials: false/g) || []).length, 2);
});
test('candidate resolution uses real Composer but never installs or boots an app', () => {
  const resolution = source.split('if [[ $mode == resolve-lock-candidates ]]; then')[1].split('\nelse\n')[0];
  assert.match(resolution, /update --no-install --no-interaction/);
  assert.match(resolution, /composer --no-plugins --no-scripts update/);
  assert.match(resolution, /validate --strict --no-check-publish/);
  assert(!/artisan|migrate|key:generate|composer install|git (add|commit|push)/.test(resolution));
  assert(!/src=\$root,dst=/.test(resolution));
  assert.match(resolution, /src=\$out\/locks\/\$project,dst=\/candidate/);
});
test('native metadata validation and isolated testing cannot be replaced by a boolean green', () => {
  assert.match(source, /--network none[\s\S]*native-runtime\.php/);
  assert.match(source, /both reviewed genuine locks required before tests/);
  assert.match(source, /git ls-files --error-unmatch/);
  assert.match(source, /php-test --compact/);
  assert.match(source, /readyForProductionDeployment\\?":false/);
  assert.match(source, /set -euo pipefail/);
});
test('artifact paths are narrowly curated, no workspace/env/storage/database archive', () => {
  assert.match(workflow, /\.cache\/qa82\/locks\//);
  assert.match(workflow, /\.cache\/qa82\/evidence\//);
  assert(!/path: \.$|path: \*|path: platform\//m.test(workflow));
  assert.equal((workflow.match(/retention-days: 7/g) || []).length, 2);
  assert.match(source, /fresh evidence directory required/);
});

test('native image and probe require actual GD WebP support used by project media', () => {
  const docker = fs.readFileSync(path.join(root, 'tools/docker/Dockerfile'), 'utf8');
  const probe = fs.readFileSync(path.join(root, 'tools/release/native-runtime.php'), 'utf8');
  assert.match(docker, /libwebp-dev/);
  assert.match(docker, /--with-webp/);
  assert.match(probe, /function_exists\('imagewebp'\)/);
  assert.match(probe, /\$ok = \$webp &&/);
});

test('native PHPUnit URL is authoritative over the Compose process default', () => {
  const xml = fs.readFileSync(path.join(root, 'tools/docker/phpunit.mysql.xml'), 'utf8');
  assert.match(xml, /<env name="APP_URL" value="http:\/\/localhost" force="true" \/>/);
});
