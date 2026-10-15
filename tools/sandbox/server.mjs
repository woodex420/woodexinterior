// Live-preview web server for the AI development sandbox (no native PHP there).
// - Static files under platform/public are served by Node; everything else goes to Laravel via php-wasm (PHP 8.4).
// - Every absolute self-URL in PHP output (HTML, JSON, Location/Link headers) is rewritten to a root-relative
//   path, so the page works under whatever origin the preview proxy shows to the browser.
// - Each request is logged to /tmp/woodex-run/requests.log (incl. proxy headers and cookie names) to debug preview issues.
// - Cookie memory: the preview proxy drops the Cookie request header; the server keeps each browser's session (below).
// - The sign-in page gets a preview-only "demo account" box with a one-click sign-in.
// Usage: PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import zlib from 'node:zlib';
import { PHP, PHPRequestHandler, setPhpIniEntries } from '/tmp/phpwasm/node_modules/@php-wasm/universal/index.js';
import { loadNodeRuntime, useHostFilesystem } from '/tmp/phpwasm/node_modules/@php-wasm/node/index.js';

const APP = process.env.APP_DIR || '/tmp/woodex-run/app';
const DOCROOT = path.join(APP, 'public');
const PORT = Number(process.env.PORT || 8080);
const ORIGIN = process.env.ABSOLUTE_URL || `https://${PORT}-${process.env.E2B_SANDBOX_ID || 'sandbox'}.e2b.app`;
const HOST = new URL(ORIGIN).host;
const LOG_FILE = '/tmp/woodex-run/requests.log';

const handler = new PHPRequestHandler({
    documentRoot: DOCROOT,
    absoluteUrl: ORIGIN,
    // The browser's own cookies must reach PHP; the built-in shared cookie jar would mix up sessions.
    cookieStore: false,
    // One runtime: two would share a process id and confuse SQLite file locking. Requests queue up.
    maxPhpInstances: 1,
    phpFactory: async () => {
        const runtime = await loadNodeRuntime('8.4', { extensions: ['intl'], emscriptenOptions: { processId: process.pid } });
        const php = new PHP(runtime);
        useHostFilesystem(php);
        await setPhpIniEntries(php, {
            memory_limit: '1G',
            display_errors: '0',
            log_errors: '1',
            error_log: '/tmp/woodex-run/php-errors.log',
            'date.timezone': 'UTC',
            upload_max_filesize: '25M',
            post_max_size: '30M',
            max_execution_time: '0',
            disable_functions: 'proc_open,popen,exec,shell_exec,system,passthru,pcntl_exec',
        });
        return php;
    },
    getFileNotFoundAction: () => ({ type: 'internal-redirect', uri: '/index.php' }),
});

// https://HOST/x -> /x, bare https://HOST -> /, and the JSON-escaped forms (https:\/\/HOST\/x -> \/x).
const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const SELF_URL = new RegExp(`https?:(\\\\?/)(\\\\?/)${escapeRe(HOST)}(?::\\d+)?(\\\\?/)?`, 'g');
const relativize = (text) => text.replace(SELF_URL, (match, slash1, slash2, pathSlash) => pathSlash ?? slash1);
const REWRITABLE = /^(text\/(html|plain|css|javascript|xml)|application\/(json|javascript|xml|ld\+json|vnd\.api\+json))/i;

// Preview-only diagnostics (never part of the app): tells whether JavaScript runs in the viewer's browser, whether
// Alpine/Livewire started and whether the browser kept the session cookies (cookieKept=1 means the XSRF-TOKEN cookie
// is visible to scripts), via /__preview-beacon lines in the request log. <noscript> pixel = scripts are off.
const BEACON_HEAD = '<script>(function(){var sa="na";try{document.hasStorageAccess&&document.hasStorageAccess().then(function(v){sa=v?1:0},function(){sa="err"})}catch(e){}function b(q){try{navigator.sendBeacon("/__preview-beacon?"+q+"&p="+encodeURIComponent(location.pathname)+"&w="+innerWidth)}catch(e){}}b("js=1");addEventListener("load",function(){setTimeout(function(){var ck="err";try{ck=/(^|;\\s*)XSRF-TOKEN=/.test(document.cookie)?1:0}catch(e){}b("loaded=1&alpine="+!!window.Alpine+"&livewire="+!!window.Livewire+"&cookieKept="+ck+"&cookieEnabled="+navigator.cookieEnabled+"&framed="+(window.top!==window)+"&storageAccess="+sa)},1500)})})();</script>';
const BEACON_BODY = '<noscript><img src="/__preview-beacon?js=0" alt="" width="1" height="1" style="position:absolute;left:-9999px"></noscript>';
const injectBeacon = (html) => html.replace(/<\/head>/i, `${BEACON_HEAD}</head>`).replace(/<body([^>]*)>/i, `<body$1>${BEACON_BODY}`);

// Preview-only cookie memory. The preview proxy drops the Cookie request header: requests from the owner's browser
// arrive with no cookies although the browser keeps them (beacon cookieKept=1, storageAccess=1; 2026-10-15). Every
// request then started a new Laravel session: a wrong password landed on the public "/" without the error and a
// right one bounced back to the sign-in form. tools/sandbox/iframe-check.mjs reproduces it ("no-cookies"). For browser
// requests through the public preview host the server acts as that browser's cookie jar (key: User-Agent +
// Accept-Language): it stores every cookie Laravel sets and sends the stored values to PHP, also when the request has
// none (log "memory+name") or an older value ("memory~name"). Requests that do carry the current cookies are
// unchanged. Local checks on 127.0.0.1 never use it. Demo data only, sandbox only. PREVIEW_COOKIE_MEMORY=0 turns it off.
const MEMORY_ON = process.env.PREVIEW_COOKIE_MEMORY !== '0';
const MEMORY_FILE = '/tmp/woodex-run/cookie-memory.json';
const MEMORY_MAX_BROWSERS = 50;
let cookieMemory = {};
try {
    cookieMemory = JSON.parse(fs.readFileSync(MEMORY_FILE, 'utf8'));
} catch {
    cookieMemory = {};
}

function memoryKey(req) {
    if (!MEMORY_ON || req.headers.host !== HOST || !req.headers['sec-fetch-site']) return null;
    return `${req.headers['user-agent'] ?? ''}|${req.headers['accept-language'] ?? ''}`;
}

const parseCookieHeader = (header) => {
    const cookies = new Map();
    for (const part of String(header ?? '').split(';')) {
        const eq = part.indexOf('=');
        if (eq > 0) cookies.set(part.slice(0, eq).trim(), part.slice(eq + 1).trim());
    }
    return cookies;
};
const cookieNames = (header) => [...parseCookieHeader(header).keys()].join(',') || '-';

// Cookie header for PHP: the browser's cookies with the remembered values on top.
function withRememberedCookies(key, header) {
    const cookies = parseCookieHeader(header);
    const added = [];
    const replaced = [];
    for (const [name, value] of Object.entries(cookieMemory[key] ?? {})) {
        if (!cookies.has(name)) added.push(name);
        else if (cookies.get(name) !== value) replaced.push(name);
        cookies.set(name, value);
    }
    return { header: [...cookies].map(([name, value]) => `${name}=${value}`).join('; '), added, replaced };
}

function rememberCookies(key, setCookieHeaders) {
    const jar = { ...(cookieMemory[key] ?? {}) };
    for (const line of setCookieHeaders) {
        const [pair, ...attributes] = String(line).split(';');
        const eq = pair.indexOf('=');
        if (eq <= 0) continue;
        const name = pair.slice(0, eq).trim();
        const value = pair.slice(eq + 1).trim();
        const attr = (wanted) => attributes.map((a) => a.trim()).find((a) => a.toLowerCase().startsWith(`${wanted}=`))?.slice(wanted.length + 1);
        const maxAge = attr('max-age');
        const expires = attr('expires');
        const expired = maxAge !== undefined ? Number(maxAge) <= 0 : expires !== undefined && Date.parse(expires) <= Date.now();
        if (expired || value === '' || value === 'deleted') delete jar[name];
        else jar[name] = value;
    }
    delete cookieMemory[key]; // re-insert: most recently used browsers stay, the oldest are dropped first
    cookieMemory[key] = jar;
    const keys = Object.keys(cookieMemory);
    for (const old of keys.slice(0, Math.max(0, keys.length - MEMORY_MAX_BROWSERS))) delete cookieMemory[old];
    try {
        fs.writeFileSync(MEMORY_FILE, JSON.stringify(cookieMemory));
    } catch (error) {
        console.error('cookie memory not saved', error);
    }
}

// Preview-only demo-account box on the sign-in form (the owner tried an e-mail that does not exist in the demo data).
const DEMO_EMAIL = process.env.PREVIEW_LOGIN_EMAIL || 'owner@woodex.test';
const DEMO_PASSWORD = process.env.PREVIEW_LOGIN_PASSWORD || 'Woodex-Preview-2026';
const html = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const js = (s) => JSON.stringify(String(s)).replace(/</g, '\\u003c');
const CODE = 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;background:#fff;border:1px solid #e7d9bf;border-radius:6px;padding:1px 6px;color:#0f1e36;white-space:nowrap';
const LOGIN_HINT = `<div id="wx-preview-login" style="border:1px dashed #c9a46a;background:#fbf6ee;border-radius:12px;padding:14px 16px;font-size:13px;line-height:1.6;color:#4a3b22">`
    + `<div style="font-weight:600;color:#0f1e36">Preview demo account</div>`
    + `<div style="margin-top:4px">Email <code style="${CODE}">${html(DEMO_EMAIL)}</code> &nbsp;Password <code style="${CODE}">${html(DEMO_PASSWORD)}</code></div>`
    + `<button type="button" style="margin-top:10px;height:36px;padding:0 14px;border-radius:8px;border:0;background:#0f1e36;color:#fff;font:inherit;font-weight:500;cursor:pointer" `
    + `onclick="${html(`var f=this.closest('form');f.querySelector('[name=email]').value=${js(DEMO_EMAIL)};f.querySelector('[name=password]').value=${js(DEMO_PASSWORD)};f.requestSubmit?f.requestSubmit():f.submit();`)}">Sign in with the demo account</button>`
    + `<div style="margin-top:8px;font-size:12px;color:#7a6849">Shown only in this preview. Real staff accounts are created on the live server.</div>`
    + `</div>`;
const LOGIN_FORM = /(<form\b[^>]*\baction="[^"]*\/login"[^>]*>)/i;
const injectLoginHint = (page) => (/name="password"/.test(page) ? page.replace(LOGIN_FORM, `$1${LOGIN_HINT}`) : page);

const MIME = {
    '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
    '.json': 'application/json', '.map': 'application/json', '.webmanifest': 'application/manifest+json',
    '.html': 'text/html; charset=utf-8', '.txt': 'text/plain; charset=utf-8', '.xml': 'application/xml',
    '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.gif': 'image/gif',
    '.webp': 'image/webp', '.avif': 'image/avif', '.ico': 'image/x-icon',
    '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf', '.otf': 'font/otf', '.pdf': 'application/pdf',
};

// "Windows/Chrome150", "iPhone/Safari18", "curl/7.88.1"...: enough to tell the owner's browser from the checks.
function shortUa(ua) {
    if (!ua) return '-';
    const os = /Windows|Android|iPhone|iPad|Macintosh|CrOS|Linux/.exec(ua)?.[0] ?? '?';
    const tag = /iframe-check\/[\w-]+/.exec(ua)?.[0];
    for (const brand of ['HeadlessChrome', 'Edg', 'OPR', 'SamsungBrowser', 'Firefox', 'Chrome', 'Version']) {
        const match = new RegExp(`\\b${brand}/(\\d+)`).exec(ua);
        if (match) return `${os}/${brand === 'Version' ? 'Safari' : brand}${match[1]}${tag ? `/${tag}` : ''}`;
    }
    return ua.slice(0, 30).replace(/\s+/g, '_');
}

const summary = (h) => [
    `host=${h.host ?? '-'}`, `xfh=${h['x-forwarded-host'] ?? '-'}`, `origin=${h.origin ?? '-'}`, `referer=${h.referer ?? '-'}`,
    `site=${h['sec-fetch-site'] ?? '-'}`, `dest=${h['sec-fetch-dest'] ?? '-'}`, `mode=${h['sec-fetch-mode'] ?? '-'}`,
    `enc=${h['accept-encoding'] ?? '-'}`, `cookies=${cookieNames(h.cookie)}`,
    `ua=${shortUa(h['user-agent'])}`,
].join(' ');

// One "start" line per request and one "done"/"ABORTED" line when the response is flushed or the client goes away,
// so slow or cancelled transfers through the preview proxy are visible too.
function track(req, res) {
    const started = Date.now();
    const id = Math.random().toString(36).slice(2, 7);
    fs.appendFile(LOG_FILE, `${new Date().toISOString()} start ${id} ${req.method} ${req.url} ${summary(req.headers)}\n`, () => {});
    const done = (state) => {
        const line = `${new Date().toISOString()} ${state} ${id} ${req.method} ${req.url} ${res.statusCode} ${Date.now() - started}ms ${res.getHeader('content-length') ?? '?'}b ${res.getHeader('content-encoding') ?? 'identity'} host=${req.headers.host ?? '-'}${res.wxNote ? ` ${res.wxNote}` : ''}`;
        fs.appendFile(LOG_FILE, `${line}\n`, () => {});
        console.log(line.slice(25));
    };
    res.on('finish', () => done('done'));
    res.on('close', () => { if (!res.writableFinished) done('ABORTED'); });
}

// Compression: the proxy link can be slow and livewire.js / apexcharts are ~600 KB each uncompressed.
const COMPRESSIBLE = /^(text\/|application\/(json|javascript|xml|ld\+json|manifest\+json)|image\/svg\+xml|font\/(otf|ttf))/i;
const compressedCache = new Map();
function pickEncoding(req) {
    const accepted = String(req.headers['accept-encoding'] ?? '');
    if (/\bbr\b/.test(accepted)) return 'br';
    if (/\bgzip\b/.test(accepted)) return 'gzip';
    return null;
}
function compress(buffer, encoding, quality) {
    return encoding === 'br'
        ? zlib.brotliCompressSync(buffer, { params: { [zlib.constants.BROTLI_PARAM_QUALITY]: quality, [zlib.constants.BROTLI_PARAM_SIZE_HINT]: buffer.length } })
        : zlib.gzipSync(buffer, { level: quality >= 9 ? 9 : 6 });
}

function staticFile(urlPath) {
    let decoded;
    try {
        decoded = decodeURIComponent(urlPath);
    } catch {
        return null;
    }
    const file = path.normalize(path.join(DOCROOT, decoded));
    if (!file.startsWith(`${DOCROOT}${path.sep}`) || file.endsWith('.php')) return null;
    try {
        return fs.statSync(file).isFile() ? file : null;
    } catch {
        return null;
    }
}

function serveStatic(req, res, file) {
    const stat = fs.statSync(file);
    const type = MIME[path.extname(file).toLowerCase()] ?? 'application/octet-stream';
    const headers = {
        'Content-Type': type,
        'Cache-Control': req.url.startsWith('/build/assets/') ? 'public, max-age=31536000, immutable' : 'no-cache',
        'Last-Modified': stat.mtime.toUTCString(),
        'Access-Control-Allow-Origin': '*',
        'Cross-Origin-Resource-Policy': 'cross-origin',
        'X-Content-Type-Options': 'nosniff',
        Vary: 'Accept-Encoding',
    };
    const encoding = COMPRESSIBLE.test(type) && stat.size > 1024 ? pickEncoding(req) : null;
    let body;
    if (encoding) {
        const key = `${file}:${stat.mtimeMs}:${encoding}`;
        body = compressedCache.get(key);
        if (!body) {
            body = compress(fs.readFileSync(file), encoding, 9);
            compressedCache.set(key, body);
        }
        headers['Content-Encoding'] = encoding;
    } else {
        body = fs.readFileSync(file);
    }
    headers['Content-Length'] = body.length;
    for (const [name, value] of Object.entries(headers)) res.setHeader(name, value);
    res.writeHead(200);
    res.end(req.method === 'HEAD' ? undefined : body);
}

let phpQueue = Promise.resolve();
function oneAtATime(task) {
    const run = phpQueue.then(() => task());
    phpQueue = run.catch(() => {});
    return run;
}

async function servePhp(req, res) {
    const chunks = [];
    for await (const chunk of req) chunks.push(chunk);
    const headers = {};
    for (const [name, value] of Object.entries(req.headers)) {
        headers[name] = Array.isArray(value) ? value.join(', ') : value;
    }
    // Laravel always sees the same public host (so the rewrite above can strip it) over HTTPS.
    headers.host = HOST;
    headers['x-forwarded-proto'] = 'https';
    delete headers['x-forwarded-host'];
    delete headers['x-forwarded-port'];
    delete headers['content-length'];
    delete headers['transfer-encoding'];
    delete headers['accept-encoding'];

    // PHP requests run one at a time anyway (maxPhpInstances: 1). Queueing them here means each request reads the cookie
    // memory only after the previous request stored its cookies, so a request queued behind the sign-in cannot
    // bring back the old guest session.
    const key = memoryKey(req);
    const response = await oneAtATime(async () => {
        if (key) {
            const merged = withRememberedCookies(key, headers.cookie);
            if (merged.header) headers.cookie = merged.header;
            const note = [merged.added.length ? `memory+${merged.added.join(',')}` : '', merged.replaced.length ? `memory~${merged.replaced.join(',')}` : ''].filter(Boolean).join(' ');
            if (note) {
                res.wxNote = note;
                res.setHeader('x-preview-jar', note);
            }
        }
        const result = await handler.request({
            method: req.method,
            url: req.url,
            headers,
            body: chunks.length > 0 ? new Uint8Array(Buffer.concat(chunks)) : undefined,
        });
        const setCookies = Object.entries(result.headers).find(([name]) => name.toLowerCase() === 'set-cookie')?.[1] ?? [];
        if (key && setCookies.length > 0) rememberCookies(key, setCookies);
        return result;
    });

    const outHeaders = {};
    let contentType = '';
    for (const [name, values] of Object.entries(response.headers)) {
        const lower = name.toLowerCase();
        if (['content-length', 'transfer-encoding', 'connection', 'x-powered-by'].includes(lower)) continue;
        if (lower === 'content-type') contentType = values[0] ?? '';
        outHeaders[lower] = lower === 'set-cookie' ? values : values.map(relativize);
    }
    let body = Buffer.from(response.bytes);
    if (REWRITABLE.test(contentType) && body.length > 0) {
        let text = relativize(body.toString('utf8'));
        if (/^text\/html/i.test(contentType) && process.env.PREVIEW_BEACON !== '0') text = injectBeacon(text);
        if (/^text\/html/i.test(contentType) && process.env.PREVIEW_LOGIN_HINT !== '0') text = injectLoginHint(text);
        body = Buffer.from(text, 'utf8');
    }
    const encoding = COMPRESSIBLE.test(contentType) && body.length > 1024 && !outHeaders['content-encoding'] ? pickEncoding(req) : null;
    if (encoding) {
        body = compress(body, encoding, 5);
        outHeaders['content-encoding'] = encoding;
        outHeaders.vary = outHeaders.vary ? `${outHeaders.vary}, Accept-Encoding` : 'Accept-Encoding';
    }
    outHeaders['content-length'] = body.length;
    for (const [name, value] of Object.entries(outHeaders)) res.setHeader(name, value);
    res.writeHead(response.httpStatusCode);
    res.end(req.method === 'HEAD' ? undefined : body);
    if (response.errors) fs.appendFile('/tmp/woodex-run/php-errors.log', `${response.errors}\n`, () => {});
}

const server = http.createServer(async (req, res) => {
    track(req, res);
    try {
        const urlPath = new URL(req.url, 'http://local').pathname;
        if (urlPath === '/__preview-beacon') {
            req.resume();
            const line = `${new Date().toISOString()} BEACON ${req.url} ${summary(req.headers)}`;
            fs.appendFile(LOG_FILE, `${line}\n`, () => {});
            console.log(line.slice(25));
            res.writeHead(204, { 'Cache-Control': 'no-store' });
            return res.end();
        }
        const file = (req.method === 'GET' || req.method === 'HEAD') ? staticFile(urlPath) : null;
        if (file) return serveStatic(req, res, file);
        await servePhp(req, res);
    } catch (error) {
        console.error(error);
        fs.appendFile('/tmp/woodex-run/php-errors.log', `${error?.stack ?? error}\n`, () => {});
        if (!res.headersSent) res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('Preview server error - see /tmp/woodex-run/php-errors.log');
    }
});

server.keepAliveTimeout = 65_000;
server.listen(PORT, '0.0.0.0', async () => {
    console.log(`Woodex preview on 0.0.0.0:${PORT} (Laravel sees ${ORIGIN}; output URLs are root-relative)`);
    // Boot the PHP runtime and Laravel now so the first browser request is fast.
    try {
        const warm = await handler.request({ method: 'GET', url: '/up', headers: { host: HOST } });
        console.log(`warm-up /up -> ${warm.httpStatusCode}`);
    } catch (error) {
        console.error('warm-up failed', error);
    }
});
