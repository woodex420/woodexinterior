import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const script = path.join(root, 'tools/release/validate-reviewed-candidate.sh');
const workflow = fs.readFileSync(path.join(root, '.github/workflows/native-app-validation.yml'), 'utf8');
const source = fs.readFileSync(script, 'utf8');
const env = { PATH: process.env.PATH, GITHUB_ACTIONS: 'true', GITHUB_REPOSITORY: 'woodex420/woodexinterior', GITHUB_REF: 'refs/heads/arena/8be318a2-woodexinterior', RUNNER_ENVIRONMENT: 'github-hosted', GITHUB_RUN_ID: '123', GITHUB_RUN_ATTEMPT: '1', GITHUB_SHA: '0'.repeat(40) };
for (const [label, args, changes] of [
  ['no argument overrides', ['--bootstrap=unsafe'], {}],
  ['not local runtime', [], { GITHUB_ACTIONS: '' }],
  ['no other repo', [], { GITHUB_REPOSITORY: 'other/repo' }],
  ['no master', [], { GITHUB_REF: 'refs/heads/master' }],
  ['no self hosted', [], { RUNNER_ENVIRONMENT: 'self-hosted' }],
  ['numeric run ID', [], { GITHUB_RUN_ID: '../unsafe' }],
  ['no Docker host override', [], { DOCKER_HOST: 'SYNTHETIC_DO_NOT_PRINT' }],
  ['no Composer credentials', [], { COMPOSER_AUTH: 'SYNTHETIC_DO_NOT_PRINT' }],
  ['no DB credentials', [], { DB_PASSWORD: 'SYNTHETIC_DO_NOT_PRINT' }],
  ['exact checkout required', [], {}],
]) test(label, () => {
  const p = spawnSync('bash', [script, ...args], { env: { ...env, ...changes }, cwd: root, encoding: 'utf8' });
  assert.equal(p.status, 64, p.stderr); assert.match(p.stderr, /HOLD:/); assert(!p.stderr.includes('SYNTHETIC_DO_NOT_PRINT'));
});
test('candidate workflow is exact manual opt-in, least privilege, no deployment secrets/write', () => {
  assert.match(workflow, /message == '\[woodex-qa:validate-artifact\] source-only manual QA'/);
  assert.match(workflow, /inputs.confirm_candidate_only == true/);
  assert.match(workflow, /default: false/);
  assert.match(workflow, /actions: read/);
  assert(!/secrets\.|contents: write|actions: write|id-token:|git push|ssh |scp |pull_request:|schedule:/.test(workflow));
  for (const [, value] of workflow.matchAll(/uses:\s+(\S+)/g)) assert.match(value, /^actions\/(checkout|upload-artifact)@[a-f0-9]{40}$/);
  assert.match(workflow, /persist-credentials: false/);
});
test('no committed-lock guard is bypassed: distinct candidate-only path, no staging/adoption', () => {
  assert(!/git add|git commit|git update-index|git push/.test(source));
  assert.match(workflow, /Candidate locks are uncommitted/);
  assert.match(source, /readyForProductionDeployment':False/);
  assert.match(source, /--no-deps php-deps/);
  assert.match(source, /--no-deps php-test --compact --fail-on-skipped --fail-on-warning/);
  assert(!/--stop-on-failure|tests\/.*\.php/.test(source));
  assert.match(source, /backupRestore':'NOT RUN'/);
});
test('artifact ID, digest and resolver source are pinned, not free inputs', () => {
  assert.match(workflow, /artifacts\/13063243998\/zip/);
  assert.match(workflow, /d3ca74419dd0b10053e11386462b19e5fd6fd0f1d6b9b0656bcb3ccdbb06bf91/);
  assert.match(workflow, /176e65e0c7f689af9be8494a8e5d17971ea8671b/);
  assert(!/inputs\.(artifact|source|run_id|digest)/.test(workflow));
});
test('generated QA passwords and URL credentials/query are removed before artifact logs', t => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-qa-filter-'));
  t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
  fs.mkdirSync(path.join(dir, 'tools/docker'), { recursive: true });
  const pw = 'a'.repeat(48), rootPw = 'b'.repeat(48);
  fs.writeFileSync(path.join(dir, 'tools/docker/.env'), `WOODEX_QA_PASSWORD=${pw}\nWOODEX_QA_ROOT_PASSWORD=${rootPw}\n`);
  const p = spawnSync(process.execPath, [path.join(root, 'tools/release/redact-disposable-qa.mjs')], {
    cwd: dir, env: { PATH: process.env.PATH, GITHUB_ACTIONS: 'true' }, encoding: 'utf8',
    input: `password=${pw}, root=${rootPw}\nURL https://synthetic:do-not-print@example.test/path?token=do-not-print\n`,
  });
  assert.equal(p.status, 0, p.stderr); assert(!p.stdout.includes(pw)); assert(!p.stdout.includes(rootPw)); assert(!p.stdout.includes('do-not-print')); assert.match(p.stdout, /https:\/\/example.test\/path/);
});
