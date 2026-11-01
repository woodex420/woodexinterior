import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { inspect, lockShape, safeAsset, applicationPaths, BRANCH } from './preflight.mjs';

// Synthetic contract fixtures only. None is a genuine Composer lock or native acceptance result.
const repository = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const extensions = ['pdo_mysql', 'pdo_sqlite', 'mbstring', 'intl', 'bcmath', 'zip', 'gd', 'openssl', 'fileinfo', 'exif', 'dom', 'xml', 'tokenizer'];
function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-readiness-fixture-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const write = (name, data) => { const p = path.join(root, name); fs.mkdirSync(path.dirname(p), { recursive: true }); fs.writeFileSync(p, typeof data === 'string' ? data : JSON.stringify(data)); };
  const require = { php: '^8.4', 'fixture/not-a-real-package': '1.0' };
  write('composer.json', { require, config: { 'vendor-dir': 'platform/vendor', platform: { php: '8.4.0' } } });
  write('platform/composer.json', { require, config: { platform: { php: '8.4.0' } } });
  const lock = { 'content-hash': '0'.repeat(32), packages: [{ name: 'fixture/not-a-real-package', version: '1.0', source: { reference: 'fixture-only' }, dist: { reference: 'fixture-only' } }], 'packages-dev': [] };
  write('composer.lock', lock); write('platform/composer.lock', lock);
  const manifest = {};
  for (const [i, key] of ['resources/css/admin.css', 'resources/js/admin.js', 'resources/css/site.css', 'resources/js/site.js'].entries()) {
    manifest[key] = { file: `assets/fixture-${i}.${i % 2 ? 'js' : 'css'}` }; write(`platform/public/build/${manifest[key].file}`, '/* SYNTHETIC */');
  }
  write('platform/public/build/manifest.json', manifest);
  const probes = { branch: BRANCH, dirty: [], locksTracked: true, php: { nativeBinary: true, version: '8.5.12', sapi: 'cli', extensions }, composer2: true };
  return { root, write, lock, manifest, probes, run: changes => inspect(root, { ...probes, ...changes }) };
}
const held = (r, id) => r.checks.find(c => c.id === id)?.status === 'HOLD';
test('synthetic prerequisite success never declares release, backup or native suite success', t => {
  const f = fixture(t); const r = f.run(); assert.equal(r.status, 'PREREQUISITES_ONLY'); assert.equal(r.readyForProductionDeployment, false);
  assert.match(r.nativeSuite, /NOT EXECUTED/); assert.match(r.backupRestore, /UNVERIFIED/);
});
for (const name of ['composer.lock', 'platform/composer.lock']) test(`missing ${name} holds`, t => {
  const f = fixture(t); fs.unlinkSync(path.join(f.root, name)); assert.equal(f.run().status, 'HOLD');
});
for (const bad of [null, {}, { 'content-hash': 'x', packages: [] }, { 'content-hash': '0'.repeat(32), packages: 'bad' }, { 'content-hash': '0'.repeat(32), packages: [{}] }, { 'content-hash': '0'.repeat(32), packages: [null] }]) {
  test(`reject malformed lock shape ${JSON.stringify(bad)}`, () => assert.equal(lockShape(bad), false));
}
test('malformed lock JSON holds rather than crashing', t => { const f = fixture(t); f.write('composer.lock', '{oops'); assert.equal(f.run().status, 'HOLD'); });
test('non-array package metadata holds rather than crashing', t => { const f = fixture(t); f.write('composer.lock', { packages: 'oops' }); assert.equal(f.run().status, 'HOLD'); });
test('manifest production requirements differ', t => { const f = fixture(t); f.write('composer.json', { require: { php: '^8.5' } }); assert(held(f.run(), 'production-manifests')); });
test('deployment vendor location must stay native', t => { const f = fixture(t); f.write('composer.json', { require: {}, config: { 'vendor-dir': 'elsewhere' } }); assert(held(f.run(), 'deployment-vendor-location')); });
for (const field of ['version', 'source', 'dist']) test(`production lock mismatch: ${field}`, t => {
  const f = fixture(t); const lock = structuredClone(f.lock); lock.packages[0][field] = field === 'version' ? '2.0' : { reference: 'different-fixture' };
  f.write('platform/composer.lock', lock); assert(held(f.run(), 'production-lock-alignment'));
});
test('untracked locks are not accepted', t => { const f = fixture(t); assert(held(f.run({ locksTracked: false }), 'locks-tracked-not-ignored')); });
test('wrong/detached branch holds without switching it', t => { const f = fixture(t); assert(held(f.run({ branch: '' }), 'session-branch')); });
test('dirty app candidate holds while reports/tooling do not impersonate app changes', t => {
  const f = fixture(t); assert(held(f.run({ dirty: ['platform/app/Example.php'] }), 'clean-application-candidate'));
  assert(!held(f.run({ dirty: ['docs/audit/example.md', 'tools/release/test.mjs'] }), 'clean-application-candidate'));
});
test('git metadata failure fails closed', t => { const f = fixture(t); assert(held(f.run({ dirty: null }), 'clean-application-candidate')); });
test('application path classifier includes build/schema/manifests and excludes cache/storage', () => {
  assert.deepEqual(applicationPaths(['platform/public/build/manifest.json', 'composer.lock', 'platform/database/migrations/example.php', 'platform/bootstrap/cache/config.php', 'platform/public/storage/a', 'docs/x']), ['platform/public/build/manifest.json', 'composer.lock', 'platform/database/migrations/example.php']);
});
test('runtime env existence holds without reading secret contents', t => {
  const f = fixture(t); f.write('platform/.env', 'DO_NOT_READ_FIXTURE_CONTENT'); const read = fs.readFileSync;
  const mock = t.mock.method(fs, 'readFileSync', (p, ...args) => { assert(!String(p).endsWith('/.env')); return read(p, ...args); });
  assert(held(f.run(), 'source-only-no-runtime-env')); mock.mock.restore();
});
test('no native PHP/WASM/incorrect version cannot pass native gate', t => {
  const f = fixture(t); for (const php of [null, { ...f.probes.php, nativeBinary: false }, { ...f.probes.php, version: '8.4.25' }]) assert(held(f.run({ php }), 'native-php85'));
});
test('native media EXIF requirement cannot be omitted', t => {
  const f = fixture(t); assert(held(f.run({ php: { ...f.probes.php, extensions: extensions.filter(e => e !== 'exif') } }), 'native-php-extensions'));
});
test('native extensions and Composer2 are required separately', t => {
  const f = fixture(t); assert(held(f.run({ php: { ...f.probes.php, extensions: [] } }), 'native-php-extensions'));
  assert(held(f.run({ composer2: false }), 'composer2-command'));
});
for (const unsafe of ['../private.env', '/private.env', 'assets/../private.env', 'https://example.test/a', 'assets\\a', 'assets/a?token=x', 'assets/a\0']) {
  test(`reject unsafe build path ${JSON.stringify(unsafe)}`, () => assert.equal(safeAsset(unsafe), false));
}
test('missing build artifact holds', t => { const f = fixture(t); fs.unlinkSync(path.join(f.root, 'platform/public/build/assets/fixture-0.css')); assert(held(f.run(), 'build-manifest-files')); });
test('missing imported chunk holds', t => { const f = fixture(t); f.manifest['resources/js/admin.js'].imports = ['missing']; f.write('platform/public/build/manifest.json', f.manifest); assert(held(f.run(), 'build-manifest-files')); });
test('bad manifest shape holds without exception', t => { const f = fixture(t); f.manifest['resources/js/admin.js'].css = {}; f.write('platform/public/build/manifest.json', f.manifest); assert(held(f.run(), 'build-manifest-files')); });
test('build symlink cannot escape build root', t => {
  const f = fixture(t); const p = path.join(f.root, 'platform/public/build/assets/fixture-0.css'); fs.unlinkSync(p); f.write('outside.css', '/* fixture */'); fs.symlinkSync(path.join(f.root, 'outside.css'), p);
  assert(held(f.run(), 'build-manifest-files'));
});
const safeEnv = () => ({ PATH: process.env.PATH, WOODEX_QA_RUN: '1', APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_HOST: 'mysql-test', DB_PORT: '3306', DB_DATABASE: 'woodex_test', DB_USERNAME: 'woodex_test', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', CACHE_STORE: 'array', SESSION_DRIVER: 'array', APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' });
const boundary = env => spawnSync('sh', [path.join(repository, 'tools/release/qa-boundary.sh'), '--environment-only'], { env, encoding: 'utf8' });
test('isolated environment-only boundary contract passes without app/DB execution', () => assert.equal(boundary(safeEnv()).status, 0));
for (const [key, value] of [['WOODEX_QA_RUN', ''], ['APP_ENV', 'production'], ['DB_HOST', 'production.invalid'], ['DB_PORT', '3307'], ['DB_DATABASE', 'live'], ['DB_USERNAME', 'root'], ['DB_CONNECTION', 'sqlite'], ['MAIL_MAILER', 'smtp'], ['QUEUE_CONNECTION', 'database'], ['CACHE_STORE', 'database'], ['SESSION_DRIVER', 'database'], ['APP_KEY', 'PRIVATE_FIXTURE_ONLY']]) {
  test(`boundary refuses unsafe ${key} without leaking values`, () => {
    const r = boundary({ ...safeEnv(), [key]: value }); assert.equal(r.status, 64); assert.match(r.stderr, /No application command started/); assert(!r.stderr.includes('PRIVATE_FIXTURE_ONLY'));
  });
}
for (const key of ['DB_URL', 'DB_SOCKET', 'WOODEX_HOME', 'APP_PUBLIC_PATH', 'APP_CONFIG_CACHE', 'TELEGRAM_BOT_TOKEN', 'WHATSAPP_TOKEN', 'MAIL_PASSWORD', 'COMPOSER_AUTH', 'LEGACY_DB_PASSWORD', 'PHPRC', 'COMPOSER_VENDOR_DIR']) {
  test(`boundary rejects ${key} override without exposing contents`, () => {
    const r = boundary({ ...safeEnv(), [key]: 'PRIVATE_FIXTURE_ONLY' }); assert.equal(r.status, 64); assert(!r.stderr.includes('PRIVATE_FIXTURE_ONLY'));
  });
}
test('CLI refuses arguments rather than accepting runtime/production overrides', () => {
  const r = spawnSync(process.execPath, [path.join(repository, 'tools/release/preflight.mjs'), '--deploy'], { encoding: 'utf8' }); assert.equal(r.status, 64); assert.match(r.stderr, /read-only/);
});

for (const args of [[], ['--compact'], ['--stop-on-failure', '--stop-on-error', '--display-warnings', '--display-all-issues'], ['tests/Feature/Accounts/PaymentPostingRepairTest.php', '--stop-on-failure']]) {
  test(`QA argument allowlist accepts ${JSON.stringify(args)} without executing tests`, () => {
    const r = spawnSync('sh', [path.join(repository, 'tools/release/qa-test-arguments.sh'), ...args], { encoding: 'utf8' }); assert.equal(r.status, 0);
  });
}
for (const args of [['--configuration=PRIVATE_FIXTURE_ONLY'], ['-cPRIVATE_FIXTURE_ONLY'], ['--no-configuration'], ['--bootstrap'], ['--extension=Fixture'], ['--parallel'], ['tests/../private.php'], ['/private.php'], ['tests/unsafe name.php']]) {
  test(`QA argument allowlist refuses ${JSON.stringify(args)} without leaking contents`, () => {
    const r = spawnSync('sh', [path.join(repository, 'tools/release/qa-test-arguments.sh'), ...args], { encoding: 'utf8' }); assert.equal(r.status, 64); assert(!r.stderr.includes('PRIVATE_FIXTURE_ONLY'));
  });
}
