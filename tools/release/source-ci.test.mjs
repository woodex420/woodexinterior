import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import {parseLog} from './summarize-native.mjs';
import {groups, discover, groupFor} from './source-test-groups.mjs';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read=name=>fs.readFileSync(path.join(root,name),'utf8');
test('every configured native test belongs to exactly one nonempty group',()=>{
  const files=discover(path.join(root,'platform'));
  assert(files.length>100);
  assert.equal(new Set(files).size,files.length);
  const selected=groups.flatMap(group=>files.filter(file=>groupFor(file)===group));
  assert.deepEqual(selected.sort(),files.sort());
  for(const group of groups)assert(files.some(file=>groupFor(file)===group));
});
test('test runner refuses local execution without printing private overrides',()=>{
  const r=spawnSync('bash',[path.join(root,'tools/release/test-branch.sh')],{env:{PATH:process.env.PATH,DB_PASSWORD:'DO_NOT_PRINT'},encoding:'utf8'});
  assert.equal(r.status,64);assert(!r.stderr.includes('DO_NOT_PRINT'));
});
test('all groups run without fail-fast or archive imports, with strict outcomes and time bounds',()=>{
  const script=read('tools/release/test-branch.sh');
  assert(script.includes('--fail-on-warning --fail-on-skipped'));
  assert(script.includes('8m'));assert(script.includes('result=1'));
  assert(!script.includes('--stop-on'));assert(!script.includes('actions/artifacts'));
  assert(!script.includes('composer update'));assert(script.includes('down --volumes'));
});
test('workflow uses versioned source and read-only permissions, never deployment secrets',()=>{
  const workflow=read('.github/workflows/native-app-validation.yml');
  assert(workflow.includes('contents: read'));assert(workflow.includes('test-branch.sh'));
  for(const forbidden of ['actions/artifacts','import-reviewed-artifact','secrets.','contents: write','ssh ','migrate --force'])assert(!workflow.includes(forbidden));
});
test('both genuine public locks are present and production package graphs agree',()=>{
  const a=JSON.parse(read('composer.lock')),b=JSON.parse(read('platform/composer.lock'));
  const graph=lock=>lock.packages.map(p=>[p.name,p.version,p.source?.reference,p.dist?.reference]).sort();
  assert.deepEqual(graph(a),graph(b));assert(a.packages.length>50);
});

test('ignored nested mountpoints are prepared before binding source read-only',()=>{
  const script=read('tools/release/test-branch.sh');
  assert(script.indexOf('for dir in platform/vendor platform/storage platform/bootstrap/cache; do') < script.indexOf('run --rm --no-deps --entrypoint sh php-deps'));
  assert(script.includes('mountpoint symlink'));
});

test('native summary removes ANSI before extracting failures and counts',()=>{
  const r=parseLog('\x1b[31m FAILED Tests\\Example > example\x1b[0m\nExpected 1 got 0\n  Tests: \x1b[31m1 failed, 2 passed\x1b[0m (3 assertions)\n');
  assert.equal(r.summary,'1 failed, 2 passed (3 assertions)');
  assert.equal(r.failures.length,1);assert(!r.failures[0].includes('\x1b'));
});
test('each group must prove a nonempty passing test count, not just CLI exit zero',()=>{
  assert(read('tools/release/test-branch.sh').includes('JS_CHECK'));
  assert.equal(parseLog('No tests found.').summary,null);
});

test('event logging permits one fixed synthetic path, not arbitrary output paths',()=>{
  const script=path.join(root,'tools/release/qa-test-arguments.sh');
  assert.equal(spawnSync('sh',[script,'--log-events-text=/workspace/platform/storage/framework/testing/native-events.log']).status,0);
  assert.equal(spawnSync('sh',[script,'--log-events-text=/private/owner.log']).status,64);
});
