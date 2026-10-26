#!/usr/bin/env node
/**
 * Render the published CMS content to a static site for Hostinger / any Apache host.
 *
 *   npm run export                 # -> woodex-os/dist
 *   npm run export -- --out ../site-public
 *   npm run export -- --with-qa    # export, then run the QA crawler over dist/
 *
 * The exporter boots the real Express app on an ephemeral port and crawls it, so
 * the static HTML is byte-identical to what visitors see on the dynamic site.
 */
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadConfig } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { exportStatic } from '../src/lib/exporter.js';
import { logActivity } from '../src/lib/audit.js';

const arg = (name, fallback = '') => {
  const index = process.argv.indexOf(`--${name}`);
  return index === -1 ? fallback : process.argv[index + 1] ?? fallback;
};
const flag = (name) => process.argv.includes(`--${name}`);

const config = loadConfig();
const outDir = path.resolve(arg('out', path.join(process.cwd(), 'dist')));
const db = openDatabase(config.dbPath);

const started = Date.now();
console.log(`Exporting Woodex OS content to ${outDir}`);
const result = await exportStatic(db, config, {
  targetDir: outDir,
  log: (line) => console.log(`  ${line}`),
});

logActivity(db, {
  actor: 'cli:export',
  action: 'content.export',
  entity: 'site',
  entityId: null,
  meta: { target: result.target, files: result.files, pages: result.pages },
});

console.log(`\nDone in ${((Date.now() - started) / 1000).toFixed(1)}s`);
console.log(`  routes: ${result.pages}`);
console.log(`  files:  ${result.files}`);
console.log(`  output: ${result.target}`);
console.log('\nUpload the contents of that folder to public_html (Apache). See EXPORT-README.txt.');

db.close();

if (flag('with-qa')) {
  const qa = spawnSync(process.execPath, [path.join(process.cwd(), 'scripts/qa-site.js'), '--dir', outDir, '--label', 'static export'], { stdio: 'inherit' });
  process.exitCode = qa.status ?? 0;
}
