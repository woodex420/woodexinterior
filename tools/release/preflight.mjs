#!/usr/bin/env node
// Read-only prerequisite inspection. Does not boot Laravel, read .env, install packages or deploy.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

export const BRANCH = 'arena/8be318a2-woodexinterior';
const APP = /^(?:composer\.(?:json|lock)|platform\/(?:composer\.(?:json|lock)|package(?:-lock)?\.json|vite\.config\.js|(?:app|bootstrap|config|database|resources|routes|tests|public)\/))/;
export function applicationPaths(paths) {
  return paths.filter(p => APP.test(p) && !/^platform\/(?:bootstrap\/cache|public\/storage)\//.test(p));
}
function json(root, name) {
  try {
    const p = path.join(root, name);
    if (fs.lstatSync(p).isSymbolicLink()) return null;
    return JSON.parse(fs.readFileSync(p, 'utf8'));
  } catch { return null; }
}
export function lockShape(lock) {
  // Schema sanity only. Authenticity/content freshness still requires real Composer validate/install.
  return !!lock && /^[a-f0-9]{32}$/.test(lock['content-hash'] ?? '')
    && Array.isArray(lock.packages) && lock.packages.length > 0
    && lock.packages.every(p => p && typeof p.name === 'string' && typeof p.version === 'string')
    && (lock['packages-dev'] === undefined || Array.isArray(lock['packages-dev']));
}
export function safeAsset(name) {
  return typeof name === 'string' && name.length > 0 && !name.includes('\\')
    && !path.posix.isAbsolute(name) && !name.split('/').includes('..') && !/[\x00-\x1f?#:]/.test(name);
}
function insideFile(root, relative) {
  try {
    const base = fs.realpathSync(root);
    const actual = fs.realpathSync(path.join(root, relative));
    return actual.startsWith(base + path.sep) && fs.statSync(actual).isFile();
  } catch { return false; }
}
export function inspect(root, probes = {}) {
  const checks = [];
  const check = (id, ok, note) => checks.push({ id, status: ok ? 'PASS' : 'HOLD', note });
  const app = json(root, 'platform/composer.json');
  const deploy = json(root, 'composer.json');
  const stable = x => JSON.stringify(Object.entries(x ?? {}).sort(([a], [b]) => a.localeCompare(b)));
  const object = v => !!v && typeof v === 'object' && !Array.isArray(v);
  check('production-manifests', object(app?.require) && object(deploy?.require) && stable(app.require) === stable(deploy.require)
    && stable(app.config?.platform) === stable(deploy.config?.platform), 'Root/platform production requirements and platform overrides must match.');
  check('deployment-vendor-location', deploy?.config?.['vendor-dir'] === 'platform/vendor', 'Root deployment must retain the native vendor location.');
  const rootLock = json(root, 'composer.lock');
  const appLock = json(root, 'platform/composer.lock');
  check('root-lock-shape', lockShape(rootLock), 'A genuine root Composer lock is required; JSON shape alone is not authenticity or freshness.');
  check('app-lock-shape', lockShape(appLock), 'A genuine app/dev Composer lock is required; do not fabricate a lock to pass this check.');
  const rootPackages = new Map((Array.isArray(rootLock?.packages) ? rootLock.packages : []).filter(p => p && typeof p === 'object').map(p => [p.name, p]));
  const appPackages = new Map((Array.isArray(appLock?.packages) ? appLock.packages : []).filter(p => p && typeof p === 'object').map(p => [p.name, p]));
  check('production-lock-alignment', lockShape(rootLock) && lockShape(appLock) && rootPackages.size > 0 && rootPackages.size === appPackages.size
    && [...rootPackages].every(([name, p]) => {
      const q = appPackages.get(name);
      return q && p.version === q.version
        && (p.source?.reference ?? null) === (q.source?.reference ?? null)
        && (p.dist?.reference ?? null) === (q.dist?.reference ?? null);
    }), 'Compare production package versions and source/dist references across both locks.');
  check('locks-tracked-not-ignored', probes.locksTracked === true, 'Both real lock files must be versioned; presence alone is insufficient.');
  check('session-branch', probes.branch === BRANCH, 'Inspect the fixed session branch; do not switch/reset branches.');
  check('clean-application-candidate', Array.isArray(probes.dirty) && applicationPaths(probes.dirty).length === 0,
    'Unpublished application changes need explicit dependency review; do not add-all or discard them.');
  const envPaths = ['.env', '.env.testing', '.env.production', 'platform/.env', 'platform/.env.testing', 'platform/.env.production'];
  const present = p => { try { fs.lstatSync(path.join(root, p)); return true; } catch { return false; } };
  check('source-only-no-runtime-env', envPaths.every(p => !present(p)),
    'Native QA needs a source-only copy without original runtime env files. Contents are never read.');
  const manifest = json(root, 'platform/public/build/manifest.json');
  const entries = ['resources/css/admin.css', 'resources/js/admin.js', 'resources/css/site.css', 'resources/js/site.js'];
  const valid = !!manifest && entries.every(k => manifest[k]) && Object.values(manifest).every(e => {
    if (!object(e) || ['css', 'assets', 'imports', 'dynamicImports'].some(k => e[k] !== undefined && !Array.isArray(e[k]))) return false;
    const names = [e.file, ...(e.css ?? []), ...(e.assets ?? [])];
    return names.every(n => safeAsset(n) && insideFile(path.join(root, 'platform/public/build'), n))
      && [...(e.imports ?? []), ...(e.dynamicImports ?? [])].every(k => !!manifest[k]);
  });
  check('build-manifest-files', valid, 'Manifest entries/imports and referenced files must exist inside build/. Not a byte-reproduction or browser pass.');
  const php = probes.php;
  check('native-php85', php?.nativeBinary === true && /^8\.5\./.test(php.version ?? '') && php.sapi === 'cli',
    'Native PHP8.5 CLI required for host-parity QA; WASM/shell wrappers are not accepted as native.');
  const required = ['pdo_mysql', 'pdo_sqlite', 'mbstring', 'intl', 'bcmath', 'zip', 'gd', 'openssl', 'fileinfo', 'exif', 'dom', 'xml', 'tokenizer'];
  check('native-php-extensions', required.every(e => php?.extensions?.includes(e)), 'Native QA extension set must be available.');
  check('composer2-command', probes.composer2 === true, 'Composer2 must be available. This inspector does not execute Composer/install scripts.');
  const holds = checks.filter(c => c.status === 'HOLD').length;
  return { scope: 'Read-only prerequisites only; not native test, backup, security or live acceptance', branch: probes.branch ?? null,
    status: holds ? 'HOLD' : 'PREREQUISITES_ONLY', holdCount: holds, checks,
    nativeSuite: 'NOT EXECUTED BY THIS TOOL', backupRestore: 'UNVERIFIED_PRIVATE_OPERATOR_GATE',
    readyForProductionDeployment: false };
}
function command(name) {
  for (const dir of (process.env.PATH ?? '').split(path.delimiter)) {
    const p = path.join(dir, name);
    try { fs.accessSync(p, fs.constants.X_OK); return fs.realpathSync(p); } catch { /* continue */ }
  }
  return null;
}
function binary(p) {
  if (!p) return false;
  let fd;
  try {
    fd = fs.openSync(p, 'r'); const b = Buffer.alloc(4); fs.readSync(fd, b, 0, 4, 0);
    // This Linux QA path deliberately rejects PHP-WASM launchers, scripts and ambiguous wrappers.
    return b.equals(Buffer.from([0x7f, 0x45, 0x4c, 0x46]));
  } catch { return false; } finally { if (fd !== undefined) fs.closeSync(fd); }
}
export function collect(root) {
  const git = args => spawnSync('git', ['-C', root, ...args], { encoding: 'utf8', timeout: 10000 });
  const branch = git(['branch', '--show-current']);
  const changed = git(['diff', '--name-only', '-z', 'HEAD', '--']);
  const others = git(['ls-files', '--others', '--exclude-standard', '-z']);
  const tracked = git(['ls-files', '--error-unmatch', 'composer.lock', 'platform/composer.lock']);
  const dirty = changed.status === 0 && others.status === 0
    ? [...new Set((changed.stdout + others.stdout).split('\0').filter(Boolean))] : null;
  const phpPath = command('php'); let php = null;
  if (binary(phpPath)) {
    const p = spawnSync(phpPath, ['-r', 'echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"extensions"=>get_loaded_extensions()]);'],
      { encoding: 'utf8', timeout: 10000, maxBuffer: 16384 });
    try { if (p.status === 0) php = { ...JSON.parse(p.stdout), nativeBinary: true }; } catch { /* hold */ }
  }
  // Version only, with project plugins/scripts disabled. Never install or resolve packages here.
  const composer = command('composer'); let composer2 = false;
  if (php?.nativeBinary && composer) {
    const c = spawnSync(composer, ['--no-plugins', '--no-scripts', '--no-ansi', '--version'],
      { cwd: root, encoding: 'utf8', timeout: 10000, maxBuffer: 16384 });
    composer2 = c.status === 0 && /Composer (?:version )?2\./.test(c.stdout ?? '');
  }
  return { branch: branch.status === 0 ? branch.stdout.trim() : null, dirty, locksTracked: tracked.status === 0,
    php, composer2, composerCommandPresent: !!composer };
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  if (process.argv.length !== 2) { console.error('Usage: node tools/release/preflight.mjs (read-only, no flags)'); process.exitCode = 64; }
  else {
    const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
    const result = inspect(root, collect(root));
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = result.status === 'HOLD' ? 2 : 0;
  }
}
