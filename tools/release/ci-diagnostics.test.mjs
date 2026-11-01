import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { FILES, safeExcerpt, annotation, collect } from './ci-diagnostics.mjs';
function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-diagnostic-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const base = path.join(root, '.cache/qa82/evidence'); fs.mkdirSync(base, { recursive: true });
  return { root, base, write: (name, value) => fs.writeFileSync(path.join(base, name), value) };
}
test('only source-only outputs, not application logs/env/backups, are eligible', () => {
  assert.deepEqual(FILES, ['lock-alignment.json', 'resolve-platform.log', 'resolve-root.log', 'native-runtime.json', 'image-build.log']);
});
test('missing evidence fails closed without broad inspection', t => {
  const f = fixture(t); fs.rmSync(f.base, { recursive: true }); assert.match(collect(f.root), /unavailable/);
});
test('latest produced resolver evidence wins over successful earlier stages', t => {
  const f = fixture(t); f.write('image-build.log', 'earlier build OK'); f.write('native-runtime.json', '{"native":true}'); f.write('resolve-root.log', 'Synthetic solver conflict');
  assert.match(collect(f.root), /resolve-root\.log/); assert.match(collect(f.root), /Synthetic solver conflict/); assert(!collect(f.root).includes('earlier build OK'));
});
test('alignment output wins after both solves', t => {
  const f = fixture(t); f.write('resolve-platform.log', 'synthetic solve'); f.write('lock-alignment.json', '{"status":"HOLD"}'); assert.match(collect(f.root), /lock-alignment\.json/);
});
test('empty later output does not hide actual previous-stage error', t => {
  const f = fixture(t); f.write('resolve-root.log', ''); f.write('native-runtime.json', '{"status":"HOLD"}'); assert.match(collect(f.root), /native-runtime\.json/);
});
test('unrelated files including application logs are never read', t => {
  const f = fixture(t); f.write('locked-tests.log', 'DO_NOT_READ_SYNTHETIC_PRIVATE'); f.write('.env', 'DO_NOT_READ_SYNTHETIC_PRIVATE'); assert(!collect(f.root).includes('DO_NOT_READ'));
});
test('symlinked evidence file is refused', t => {
  const f = fixture(t); const outside = path.join(f.root, 'outside'); fs.writeFileSync(outside, 'DO_NOT_READ_SYNTHETIC_PRIVATE'); fs.symlinkSync(outside, path.join(f.base, 'resolve-root.log')); assert(!collect(f.root).includes('DO_NOT_READ'));
});
test('symlinked evidence directory is refused', t => {
  const f = fixture(t); const other = path.join(f.root, 'other'); fs.mkdirSync(other); fs.writeFileSync(path.join(other, 'resolve-root.log'), 'DO_NOT_READ_SYNTHETIC_PRIVATE'); fs.rmSync(f.base, { recursive: true }); fs.symlinkSync(other, f.base); assert.match(collect(f.root), /unavailable/);
});
test('URL credentials, query and fragment are removed', () => {
  const s = safeExcerpt('Error https://fixture-user:fixture-password@example.test/file?token=fixture-secret#private');
  assert(s.includes('https://example.test/file')); for (const p of ['fixture-user', 'fixture-password', 'fixture-secret', '#private']) assert(!s.includes(p));
});
test('ANSI removed, excerpt size bounded and tail preserved', () => {
  assert(!safeExcerpt('\x1b[31merror\x1b[0m').includes('\x1b')); const s = safeExcerpt(('x'.repeat(500)+'\n').repeat(100)+'tail'); assert(s.length<=6000); assert(s.endsWith('tail'));
});
test('workflow command escapes newline, carriage return and percent', () => {
  assert.equal(annotation('a%\r\n::warning::not-a-command'), '::error title=Source-only native QA diagnostic::a%25%0D%0A::warning::not-a-command');
});
