// Composer substitute for the AI development sandbox: packagist is unreachable there, GitHub is not.
// Resolves composer.json requirements against GitHub tags, downloads codeload tarballs and writes a
// Composer-compatible vendor/ (autoload_*.php, installed.json/.php, bin proxies, pest-plugins.json).
// CI and production use real Composer; never commit a composer.lock produced from this.
// Usage: node --use-system-ca resolve.mjs <composer.json> <vendorDir>
import { execFileSync, execSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const [, , composerPath, vendorDir] = process.argv;
// Generated files are only rewritten when their content changes, so an unchanged re-run leaves mtimes alone
// (sync.sh rebuilds Laravel's package manifest only when vendor/composer/installed.json is newer).
const writeIfChanged = (file, content) => {
    const data = typeof content === 'string' ? content : Buffer.from(content);
    if (fs.existsSync(file) && Buffer.from(fs.readFileSync(file)).equals(Buffer.from(data))) return;
    fs.writeFileSync(file, data);
};
const CACHE = '/tmp/resolver/cache';
for (const dir of ['tags', 'json', 'tar']) fs.mkdirSync(`${CACHE}/${dir}`, { recursive: true });
const TOKEN = execSync('gh auth token').toString().trim();
const PHP_VERSION = [8, 4, 25, 0];
const root = JSON.parse(fs.readFileSync(composerPath, 'utf8'));

// Packagist name → GitHub repository, where they differ.
const REPO_OVERRIDES = {
    'egulias/email-validator': 'egulias/EmailValidator',
    'laravel/passkeys': 'laravel/passkeys-server',
    'sabberworm/php-css-parser': 'MyIntervals/PHP-CSS-Parser',
    'nesbot/carbon': 'briannesbitt/Carbon',
    'graham-campbell/result-type': 'GrahamCampbell/Result-Type',
    'monolog/monolog': 'Seldaek/monolog',
    'phpoption/phpoption': 'schmittjoh/php-option',
    'psy/psysh': 'bobthecow/psysh',
    'nikic/php-parser': 'nikic/PHP-Parser',
    'masterminds/html5': 'Masterminds/html5-php',
    'pragmarx/google2fa': 'antonioribeiro/google2fa',
    'bacon/bacon-qr-code': 'Bacon/BaconQrCode',
    'dasprid/enum': 'DASPRiD/Enum',
    'fakerphp/faker': 'FakerPHP/Faker',
    'brianium/paratest': 'paratestphp/paratest',
    'jean85/pretty-package-versions': 'Jean85/pretty-package-versions',
    'fidry/cpu-core-counter': 'theofidry/cpu-core-counter',
    'dflydev/dot-access-data': 'dflydev/dflydev-dot-access-data',
    'carbonphp/carbon-doctrine-types': 'CarbonPHP/carbon-doctrine-types',
    'phpdocumentor/type-resolver': 'phpDocumentor/TypeResolver',
    'phpdocumentor/reflection-common': 'phpDocumentor/ReflectionCommon',
    'phpdocumentor/reflection-docblock': 'phpDocumentor/ReflectionDocBlock',
    'tijsverkoyen/css-to-inline-styles': 'tijsverkoyen/CssToInlineStyles',
    'iamcal/sql-parser': 'iamcal/SQLParser',
    'myclabs/deep-copy': 'myclabs/DeepCopy',
};
const ORG_OVERRIDES = { guzzlehttp: 'guzzle', league: 'thephpleague', psr: 'php-fig', phpunit: 'sebastianbergmann', sebastian: 'sebastianbergmann' };
const discovered = fs.existsSync(`${CACHE}/repos.json`) ? JSON.parse(fs.readFileSync(`${CACHE}/repos.json`, 'utf8')) : {};

function repoFor(name) {
    let repo = REPO_OVERRIDES[name];
    if (!repo) {
        const [vendor, pkg] = name.split('/');
        repo = ORG_OVERRIDES[vendor] ? `${ORG_OVERRIDES[vendor]}/${pkg}` : name;
    }
    return discovered[repo] || repo;
}

// ---------- versions & constraints ----------
function parseVersion(raw) {
    const s = String(raw).trim().replace(/^v/i, '');
    const m = s.match(/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?(?:[-.+]?(.*))?$/);
    if (!m) return null;
    const parts = [m[1], m[2], m[3], m[4]].map((p) => (p === undefined ? 0 : parseInt(p, 10)));
    const given = [m[1], m[2], m[3], m[4]].filter((p) => p !== undefined).length;
    const suffix = (m[5] || '').trim();
    return { parts, given, pre: suffix || null, raw };
}
function cmp(a, b) {
    for (let i = 0; i < 4; i++) if (a[i] !== b[i]) return a[i] < b[i] ? -1 : 1;
    return 0;
}
function single(token) {
    token = token.trim().replace(/@\w+$/, '');
    if (token === '' || token === '*' || token === 'x' || token.startsWith('dev-')) return () => true;
    token = token.replace(/\.x-dev$/, '.*').replace(/-dev$/, '');
    let m;
    if ((m = token.match(/^\^(.+)$/))) {
        // composer caret: bump the first non-zero given part (or the last given part when all are zero)
        const v = parseVersion(m[1]);
        const upper = [0, 0, 0, 0];
        let idx = v.parts.slice(0, v.given).findIndex((p) => p !== 0);
        if (idx === -1) idx = v.given - 1;
        for (let i = 0; i < idx; i++) upper[i] = v.parts[i];
        upper[idx] = v.parts[idx] + 1;
        return (x) => cmp(x, v.parts) >= 0 && cmp(x, upper) < 0;
    }
    if ((m = token.match(/^~(.+)$/))) {
        // composer tilde (unlike npm): ~1.2 = >=1.2 <2.0, ~1.2.3 = >=1.2.3 <1.3
        const v = parseVersion(m[1]);
        const idx = Math.max(0, v.given - 2);
        const upper = [0, 0, 0, 0];
        for (let i = 0; i < idx; i++) upper[i] = v.parts[i];
        upper[idx] = v.parts[idx] + 1;
        return (x) => cmp(x, v.parts) >= 0 && cmp(x, upper) < 0;
    }
    if ((m = token.match(/^(>=|<=|>|<|!=|==|=)(.+)$/))) {
        const op = m[1];
        const v = parseVersion(m[2]);
        if (!v) return () => true;
        return (x) => {
            const c = cmp(x, v.parts);
            return { '>=': c >= 0, '<=': c <= 0, '>': c > 0, '<': c < 0, '!=': c !== 0 }[op] ?? c === 0;
        };
    }
    if (/\*|\.x$/.test(token)) {
        const nums = token.split('.').filter((p) => p !== '*' && p !== 'x').map((p) => parseInt(p.replace(/^v/, ''), 10));
        if (nums.length === 0) return () => true;
        const lower = [0, 0, 0, 0];
        nums.forEach((n, i) => (lower[i] = n));
        const upper = [...lower];
        upper[nums.length - 1] += 1;
        return (x) => cmp(x, lower) >= 0 && cmp(x, upper) < 0;
    }
    const v = parseVersion(token);
    return v ? (x) => cmp(x, v.parts) === 0 : () => true;
}
function parseConstraint(str) {
    const ors = String(str).split(/\s*\|\|?\s*/).filter((s) => s.trim() !== '');
    const groups = ors.map((group) => {
        group = group.trim();
        const hy = group.match(/^(\S+)\s+-\s+(\S+)$/);
        if (hy) {
            const lo = parseVersion(hy[1]);
            const hi = parseVersion(hy[2]);
            if (hi.given < 3) {
                const upper = [...hi.parts];
                upper[hi.given - 1] += 1;
                return [(x) => cmp(x, lo.parts) >= 0 && cmp(x, upper) < 0];
            }
            return [(x) => cmp(x, lo.parts) >= 0 && cmp(x, hi.parts) <= 0];
        }
        group = group.replace(/(>=|<=|>|<|!=|==|=|\^|~)\s+/g, '$1');
        return group.split(/\s*,\s*|\s+/).filter(Boolean).map((t) => single(t));
    });
    if (groups.length === 0) return () => true;
    return (x) => groups.some((fns) => fns.every((fn) => fn(x)));
}

// ---------- GitHub access ----------
function lsRemote(repo) {
    return execFileSync('git', ['ls-remote', '--tags', '--refs', `https://github.com/${repo}`], {
        env: { ...process.env, GIT_TERMINAL_PROMPT: '0' },
        maxBuffer: 64 * 1024 * 1024,
        stdio: ['ignore', 'pipe', 'ignore'],
    }).toString();
}
function discoverRepo(repo) {
    // Same owner, name differing only in case/punctuation (e.g. CssToInlineStyles).
    const [owner, name] = repo.split('/');
    const norm = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
    for (let page = 1; page <= 10; page++) {
        const out = execFileSync('curl', ['-sf', '-H', `Authorization: Bearer ${TOKEN}`, `https://api.github.com/users/${owner}/repos?per_page=100&page=${page}`]).toString();
        const list = JSON.parse(out);
        const hit = list.find((r) => norm(r.name) === norm(name));
        if (hit) return hit.full_name;
        if (list.length < 100) break;
    }
    return null;
}
function listTags(repo) {
    const file = `${CACHE}/tags/${repo.replace('/', '__')}.json`;
    if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    let out;
    try {
        out = lsRemote(repo);
    } catch {
        const found = discoverRepo(repo);
        if (!found) throw new Error(`Cannot list tags of ${repo}`);
        process.stderr.write(`  (repo ${repo} -> ${found})\n`);
        discovered[repo] = found;
        fs.writeFileSync(`${CACHE}/repos.json`, JSON.stringify(discovered));
        return listTags(found);
    }
    const tags = out.trim().split('\n').filter(Boolean).map((line) => {
        const [sha, ref] = line.split('\t');
        return { sha, tag: ref.replace('refs/tags/', '') };
    });
    fs.writeFileSync(file, JSON.stringify(tags));
    return tags;
}
async function fetchJson(url) {
    for (let attempt = 0; attempt < 4; attempt++) {
        const res = await fetch(url, { headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/vnd.github+json' } });
        if (res.status === 404) return null;
        if (res.ok) return res.json();
        await new Promise((r) => setTimeout(r, 1000 * (attempt + 1)));
    }
    throw new Error(`GitHub API failed: ${url}`);
}
async function composerJsonAt(repo, tag) {
    const file = `${CACHE}/json/${repo.replace('/', '__')}@${tag.replace(/\//g, '_')}.json`;
    if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    const data = await fetchJson(`https://api.github.com/repos/${repo}/contents/composer.json?ref=${encodeURIComponent(tag)}`);
    if (!data) throw new Error(`No composer.json in ${repo}@${tag}`);
    const json = JSON.parse(Buffer.from(data.content, 'base64').toString('utf8'));
    fs.writeFileSync(file, JSON.stringify(json));
    return json;
}

// ---------- resolution ----------
const isPlatform = (name) => !name.includes('/') || name.endsWith('-implementation');
const chosen = new Map();
const constraints = new Map();
const replacedBy = new Map();
const queue = [];
function addConstraint(name, source, kind, value) {
    if (!constraints.has(name)) constraints.set(name, new Map());
    const entry = constraints.get(name).get(source) ?? {};
    entry[kind] = value;
    constraints.get(name).set(source, entry);
    if (!queue.includes(name)) queue.push(name);
}
function dropSource(source) {
    for (const [name, map] of constraints) if (map.delete(source) && !queue.includes(name)) queue.push(name);
}
for (const [name, c] of Object.entries({ ...root.require, ...root['require-dev'] })) {
    if (!isPlatform(name)) addConstraint(name, '__root__', 'req', c);
}

async function choose(name) {
    const entries = [...(constraints.get(name)?.values() ?? [])];
    const reqs = entries.filter((e) => e.req).map((e) => parseConstraint(e.req));
    const conflicts = entries.filter((e) => e.conflict).map((e) => parseConstraint(e.conflict));
    const tags = listTags(repoFor(name));
    const repo = repoFor(name);
    const seen = new Set();
    const candidates = tags
        .map((t) => ({ ...t, v: parseVersion(t.tag) }))
        .filter((t) => t.v && !t.v.pre)
        .sort((a, b) => cmp(b.v.parts, a.v.parts))
        .filter((t) => (seen.has(t.v.parts.join('.')) ? false : seen.add(t.v.parts.join('.'))));
    for (const c of candidates) {
        if (!reqs.every((fn) => fn(c.v.parts)) || conflicts.some((fn) => fn(c.v.parts))) continue;
        const json = await composerJsonAt(repo, c.tag);
        if (json.require?.php && !parseConstraint(json.require.php)(PHP_VERSION)) continue;
        return { repo, tag: c.tag, sha: c.sha, version: c.v.parts.join('.'), pretty: c.tag, json };
    }
    const detail = [...constraints.get(name).entries()].map(([s, e]) => `${s}: ${JSON.stringify(e)}`).join('; ');
    throw new Error(`No version of ${name} (${repo}) satisfies ${detail}`);
}

let guard = 0;
while (queue.length > 0) {
    if (++guard > 5000) throw new Error('Resolution did not converge');
    const name = queue.shift();
    if (isPlatform(name)) continue;
    const replacer = replacedBy.get(name);
    if (replacer && chosen.has(replacer.by)) continue;
    if (name.startsWith('illuminate/') && (chosen.has('laravel/framework') || constraints.has('laravel/framework'))) continue;
    const current = chosen.get(name);
    const entries = [...(constraints.get(name)?.values() ?? [])];
    if (!entries.some((e) => e.req)) {
        if (current) {
            chosen.delete(name);
            dropSource(name);
        }
        continue;
    }
    if (current) {
        const v = current.version.split('.').map(Number);
        if (entries.every((e) => (!e.req || parseConstraint(e.req)(v)) && (!e.conflict || !parseConstraint(e.conflict)(v)))) continue;
    }
    const pick = await choose(name);
    if (current && current.version === pick.version) continue;
    if (current) dropSource(name);
    chosen.set(name, pick);
    process.stderr.write(`  ${name} ${pick.pretty}${current ? ` (was ${current.pretty})` : ''}\n`);
    for (const [dep, c] of Object.entries(pick.json.require ?? {})) {
        if (!isPlatform(dep)) addConstraint(dep, name, 'req', c === 'self.version' ? `==${pick.version}` : c);
    }
    for (const [dep, c] of Object.entries(pick.json.conflict ?? {})) {
        if (!isPlatform(dep)) addConstraint(dep, name, 'conflict', c);
    }
    for (const [dep, c] of Object.entries(pick.json.replace ?? {})) {
        replacedBy.set(dep, { by: name, constraint: c === 'self.version' ? pick.version : c });
    }
}
for (const [name] of chosen) {
    if (![...(constraints.get(name)?.values() ?? [])].some((e) => e.req)) chosen.delete(name);
}

// ---------- download ----------
fs.mkdirSync(vendorDir, { recursive: true });
const names = [...chosen.keys()].sort();
await Promise.all(names.map(async (name, i) => {
    await new Promise((r) => setTimeout(r, (i % 12) * 50));
    const p = chosen.get(name);
    const tarFile = `${CACHE}/tar/${p.repo.replace('/', '__')}@${p.tag.replace(/\//g, '_')}.tar.gz`;
    if (!fs.existsSync(tarFile)) {
        const res = await fetch(`https://codeload.github.com/${p.repo}/tar.gz/refs/tags/${p.tag}`);
        if (!res.ok) throw new Error(`Download failed ${name} ${p.tag}: ${res.status}`);
        fs.writeFileSync(`${tarFile}.part`, Buffer.from(await res.arrayBuffer()));
        fs.renameSync(`${tarFile}.part`, tarFile);
    }
    const dest = path.join(vendorDir, name);
    const stamp = path.join(dest, '.sandbox-version');
    if (fs.existsSync(stamp) && fs.readFileSync(stamp, 'utf8') === p.tag) return;
    fs.rmSync(dest, { recursive: true, force: true });
    fs.mkdirSync(dest, { recursive: true });
    execFileSync('tar', ['-xzf', tarFile, '--strip-components=1', '-C', dest]);
    fs.writeFileSync(stamp, p.tag);
}));
for (const vendor of fs.readdirSync(vendorDir)) {
    if (['composer', 'bin', 'autoload.php', 'pest-plugins.json'].includes(vendor)) continue;
    const vdir = path.join(vendorDir, vendor);
    if (!fs.statSync(vdir).isDirectory()) continue;
    for (const pkg of fs.readdirSync(vdir)) {
        if (!chosen.has(`${vendor}/${pkg}`)) fs.rmSync(path.join(vdir, pkg), { recursive: true, force: true });
    }
}

// ---------- autoload ----------
const prodSet = new Set();
(function walk(list) {
    for (const dep of list) {
        if (isPlatform(dep)) continue;
        const target = chosen.has(dep) ? dep : replacedBy.get(dep)?.by;
        if (!target || prodSet.has(target)) continue;
        prodSet.add(target);
        walk(Object.keys(chosen.get(target).json.require ?? {}));
    }
})(Object.keys(root.require ?? {}));

function scanClasses(dir, excludes) {
    const out = {};
    const scanFile = (file) => {
        const src = fs.readFileSync(file, 'utf8');
        if (!/\b(class|interface|trait|enum)\s/i.test(src)) return;
        const clean = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*(\/\/|#(?!\[)).*$/gm, '');
        let ns = '';
        const re = /\bnamespace\s+([\w\\]+)\s*[;{]|^\s*(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)/gim;
        let m;
        while ((m = re.exec(clean))) {
            if (m[1]) ns = m[1];
            else if (m[3] && !['extends', 'implements'].includes(m[3].toLowerCase())) out[(ns ? `${ns}\\` : '') + m[3]] = file;
        }
    };
    const walkDir = (d) => {
        if (!fs.existsSync(d)) return;
        if (fs.statSync(d).isFile()) return scanFile(d);
        for (const f of fs.readdirSync(d)) {
            const full = path.join(d, f);
            if (excludes.some((e) => full.includes(e))) continue;
            if (fs.statSync(full).isDirectory()) walkDir(full);
            else if (/\.(php|inc|hh)$/.test(f)) scanFile(full);
        }
    };
    walkDir(dir);
    return out;
}
const psr4 = {};
const psr0 = {};
const classmap = {};
const files = {};
for (const name of names) {
    const json = chosen.get(name).json;
    const base = path.join(vendorDir, name);
    const al = json.autoload ?? {};
    for (const [ns, dirs] of Object.entries(al['psr-4'] ?? {})) psr4[ns] = [...(psr4[ns] ?? []), ...[].concat(dirs).map((d) => `${name}/${d}`.replace(/\/+$/, ''))];
    for (const [ns, dirs] of Object.entries(al['psr-0'] ?? {})) psr0[ns] = [...(psr0[ns] ?? []), ...[].concat(dirs).map((d) => `${name}/${d}`.replace(/\/+$/, ''))];
    const excludes = (al['exclude-from-classmap'] ?? []).map((e) => path.join(base, e.replace(/\*\*?/g, '')));
    for (const d of al.classmap ?? []) {
        for (const [cls, file] of Object.entries(scanClasses(path.join(base, d), excludes))) classmap[cls] = path.relative(vendorDir, file);
    }
    for (const f of al.files ?? []) files[crypto.createHash('md5').update(`${name}:${f}`).digest('hex')] = `${name}/${f}`;
}
const rootPsr4 = { ...(root.autoload?.['psr-4'] ?? {}), ...(root['autoload-dev']?.['psr-4'] ?? {}) };
const phpStr = (s) => `'${s.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
const vend = (p) => `$vendorDir . ${phpStr(`/${p}`)}`;
const base = (p) => `$baseDir . ${phpStr(`/${p.replace(/\/+$/, '')}`)}`;
const head = "<?php\n\n// Generated by the Woodex sandbox resolver (Composer substitute).\n\n$vendorDir = dirname(__DIR__);\n$baseDir = dirname($vendorDir);\n\nreturn array(\n";
fs.mkdirSync(path.join(vendorDir, 'composer'), { recursive: true });
writeIfChanged(path.join(vendorDir, 'composer/autoload_psr4.php'), head
    + Object.entries(psr4).map(([ns, dirs]) => `    ${phpStr(ns)} => array(${dirs.map(vend).join(', ')}),\n`).join('')
    + Object.entries(rootPsr4).map(([ns, d]) => `    ${phpStr(ns)} => array(${[].concat(d).map(base).join(', ')}),\n`).join('')
    + ');\n');
writeIfChanged(path.join(vendorDir, 'composer/autoload_namespaces.php'), head
    + Object.entries(psr0).map(([ns, dirs]) => `    ${phpStr(ns)} => array(${dirs.map(vend).join(', ')}),\n`).join('') + ');\n');
writeIfChanged(path.join(vendorDir, 'composer/autoload_classmap.php'), head
    + "    'Composer\\\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',\n"
    + Object.entries(classmap).map(([c, f]) => `    ${phpStr(c)} => ${vend(f)},\n`).join('') + ');\n');
writeIfChanged(path.join(vendorDir, 'composer/autoload_files.php'), head
    + Object.entries(files).map(([id, f]) => `    ${phpStr(id)} => ${vend(f)},\n`).join('') + ');\n');

for (const [f, src] of [['ClassLoader.php', 'src/Composer/Autoload/ClassLoader.php'], ['InstalledVersions.php', 'src/Composer/InstalledVersions.php']]) {
    const target = path.join(vendorDir, 'composer', f);
    if (fs.existsSync(target)) continue;
    let data = null;
    for (const ref of ['2.8.12', '2.8.10', '2.8.0', 'main']) {
        data = await fetchJson(`https://api.github.com/repos/composer/composer/contents/${src}?ref=${ref}`);
        if (data) break;
    }
    fs.writeFileSync(target, Buffer.from(data.content, 'base64'));
}
writeIfChanged(path.join(vendorDir, 'composer/autoload_real.php'), `<?php

// Generated by the Woodex sandbox resolver (Composer substitute).

class ComposerAutoloaderInitWoodexSandbox
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\\Autoload\\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \\Composer\\Autoload\\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInitWoodexSandbox', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \\Composer\\Autoload\\ClassLoader(\\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitWoodexSandbox', 'loadClassLoader'));

        foreach (require __DIR__ . '/autoload_namespaces.php' as $namespace => $path) {
            $loader->set($namespace, $path);
        }
        foreach (require __DIR__ . '/autoload_psr4.php' as $namespace => $path) {
            $loader->setPsr4($namespace, $path);
        }
        $classMap = require __DIR__ . '/autoload_classmap.php';
        if ($classMap) {
            $loader->addClassMap($classMap);
        }
        $loader->register(true);

        foreach (require __DIR__ . '/autoload_files.php' as $fileIdentifier => $file) {
            if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
                require $file;
            }
        }

        return $loader;
    }
}
`);
writeIfChanged(path.join(vendorDir, 'autoload.php'), `<?php

// autoload.php generated by the Woodex sandbox resolver (Composer substitute)

if (PHP_VERSION_ID < 50600) {
    echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6';
    exit(1);
}

require_once __DIR__ . '/composer/autoload_real.php';

return ComposerAutoloaderInitWoodexSandbox::getLoader();
`);

const normalized = (v) => v.split('.').concat(['0', '0', '0', '0']).slice(0, 4).join('.');
writeIfChanged(path.join(vendorDir, 'composer/installed.json'), JSON.stringify({
    packages: names.map((name) => {
        const p = chosen.get(name);
        const j = p.json;
        return {
            name,
            version: p.pretty,
            version_normalized: normalized(p.version),
            source: { type: 'git', url: `https://github.com/${p.repo}.git`, reference: p.sha },
            dist: { type: 'zip', url: `https://api.github.com/repos/${p.repo}/zipball/${p.sha}`, reference: p.sha, shasum: '' },
            require: j.require ?? {},
            'require-dev': j['require-dev'] ?? {},
            conflict: j.conflict ?? {},
            replace: j.replace ?? {},
            provide: j.provide ?? {},
            suggest: j.suggest ?? {},
            bin: j.bin ?? [],
            type: j.type ?? 'library',
            extra: j.extra ?? {},
            autoload: j.autoload ?? {},
            'notification-url': 'https://packagist.org/downloads/',
            license: [].concat(j.license ?? []),
            description: j.description ?? '',
            'install-path': `../${name}`,
        };
    }),
    dev: true,
    'dev-package-names': names.filter((n) => !prodSet.has(n)),
}, null, 4));
let ip = `<?php return array(\n    'root' => array('name' => ${phpStr(root.name)}, 'pretty_version' => 'dev-main', 'version' => 'dev-main', 'reference' => null, 'type' => 'project', 'install_path' => __DIR__ . '/../../', 'aliases' => array(), 'dev' => true),\n    'versions' => array(\n`;
ip += `        ${phpStr(root.name)} => array('pretty_version' => 'dev-main', 'version' => 'dev-main', 'reference' => null, 'type' => 'project', 'install_path' => __DIR__ . '/../../', 'aliases' => array(), 'dev_requirement' => false),\n`;
for (const name of names) {
    const p = chosen.get(name);
    ip += `        ${phpStr(name)} => array('pretty_version' => ${phpStr(p.pretty)}, 'version' => ${phpStr(normalized(p.version))}, 'reference' => ${phpStr(p.sha)}, 'type' => ${phpStr(p.json.type ?? 'library')}, 'install_path' => __DIR__ . ${phpStr(`/../${name}`)}, 'aliases' => array(), 'dev_requirement' => ${prodSet.has(name) ? 'false' : 'true'}),\n`;
}
for (const [dep, r] of replacedBy) {
    if (!chosen.has(dep) && chosen.has(r.by)) ip += `        ${phpStr(dep)} => array('dev_requirement' => false, 'replaced' => array(0 => ${phpStr(r.constraint)})),\n`;
}
writeIfChanged(path.join(vendorDir, 'composer/installed.php'), `${ip}    ),\n);\n`);

// bin proxies (PHP 8 strips the shebang of included files)
fs.mkdirSync(path.join(vendorDir, 'bin'), { recursive: true });
for (const name of names) {
    for (const bin of chosen.get(name).json.bin ?? []) {
        fs.writeFileSync(path.join(vendorDir, 'bin', path.basename(bin)),
            `#!/usr/bin/env php\n<?php\n\nnamespace Composer;\n\n$GLOBALS['_composer_bin_dir'] = __DIR__;\n$GLOBALS['_composer_autoload_path'] = __DIR__ . '/..'.'/autoload.php';\n\nreturn include __DIR__ . '/..'.${phpStr(`/${name}/${bin}`)};\n`,
            { mode: 0o755 });
    }
}
writeIfChanged(path.join(vendorDir, 'pest-plugins.json'), JSON.stringify(names.flatMap((n) => chosen.get(n).json.extra?.pest?.plugins ?? []), null, 4));
fs.writeFileSync('/tmp/resolver/resolved.txt', names.map((n) => `${n} ${chosen.get(n).pretty}${prodSet.has(n) ? '' : ' (dev)'}`).join('\n') + '\n');
process.stderr.write(`Resolved ${names.length} packages.\n`);
