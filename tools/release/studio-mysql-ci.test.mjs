import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const workflow = fs.readFileSync(new URL('../../.github/workflows/native-app-validation.yml', import.meta.url), 'utf8');
const job = workflow.slice(workflow.indexOf('\n  studio-mysql84:'));
const runtime = fs.readFileSync(new URL('../../platform/tests/Support/studio-mysql-runtime.php', import.meta.url), 'utf8');
const orchestrator = fs.readFileSync(new URL('../../platform/tests/Support/studio-mysql-ci.php', import.meta.url), 'utf8');

test('new job requires manual dispatch on the exact approved branch', () => {
  assert.match(job, /if: github\.event_name == 'workflow_dispatch' && github\.ref == 'refs\/heads\/arena\/3727507a-woodexinterior'/);
  const pushBranches = workflow.match(/push:\s*branches: \[([^\]]+)\]/)[1];
  assert(!pushBranches.includes('3727507a'));
});

test('native service and PHP versions and local-only port are explicit', () => {
  assert.match(job, /image: mysql:8\.4/);
  assert.match(job, /php-version: '8\.4'/);
  assert.match(job, /127\.0\.0\.1:33060:3306/);
  assert.match(job, /pdo_mysql/);
  assert.match(workflow, /permissions:\s*contents: read/);
});

test('no secret references, deploy, SSH, provider commands or automatic install scripts', () => {
  assert(!job.includes('${{ secrets.'));
  assert(!/\b(?:ssh|scp|rsync|vercel|supabase)\s/.test(job));
  assert.match(job, /composer install[^\n]+--no-scripts/);
  assert(!job.includes('migrate:fresh'));
});

test('artifacts are only the public synthetic allowlist, never the fixture or whole cache', () => {
  const paths = job.match(/path: \|\n([\s\S]+?)\n          retention-days:/)[1].trim().split('\n').map(s => s.trim());
  assert.deepEqual(paths, ['.cache/studio-mysql-ci-public/*.json', '.cache/studio-mysql-ci-public/*.log', '.cache/studio-mysql-ci-public/source-sha256.txt']);
});

test('worker guard rejects other refs, hosts, schema names and nonprivate credentials', () => {
  assert.match(runtime, /getenv\('GITHUB_REPOSITORY'\) !== 'woodex420\/woodexinterior'/);
  assert.match(runtime, /getenv\('RUNNER_ENVIRONMENT'\) !== 'github-hosted'/);
  assert.match(runtime, /getenv\('GITHUB_REF'\) !== 'refs\/heads\/arena\/3727507a-woodexinterior'/);
  assert.match(runtime, /\(\$c\['host'\] \?\? ''\) !== '127\.0\.0\.1'/);
  assert.match(runtime, /\(fileperms\(\$run\.'\/mysql\.json'\) & 0077\) !== 0/);
  assert(runtime.includes('^studio_ci_[a-f0-9]{12}_[0-9]{2}$'));
});

test('only newly created schemas are adopted and cleaned up, and only owned SQL sessions killed', () => {
  assert.match(orchestrator, /exec\('CREATE DATABASE `/);
  assert.match(orchestrator, /\$schemas\[\] = \$database/);
  assert(!orchestrator.includes("exec('CREATE DATABASE IF NOT EXISTS"));
  assert.match(orchestrator, /foreach \(\$schemas as \$database\)/);
  assert.match(orchestrator, /\$owner\['USER'\] === \$user && \$owner\['DB'\] === \$config\['database'\]/);
});
