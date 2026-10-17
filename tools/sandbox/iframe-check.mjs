// Real-browser check of the preview *as Arena shows it*: the app inside a cross-site <iframe> (top page on
// http://localhost:9090, app on http://127.0.0.1:<proxy port>) behind a small proxy that behaves like the preview
// proxy (public Host header, no Referer). browser-check.mjs opens the app as a normal tab, which never shows
// cookie problems; this check does. Two runs, each in a fresh Chromium:
//   3pc-blocked  Chromium blocks third-party cookies (only Partitioned cookies may work in the frame);
//   no-cookies   the proxy drops every Cookie / Set-Cookie header: the browser never keeps any cookie.
// In both runs: a wrong sign-in must come back to the sign-in form with the error (never the public "/"), a double
// click on the preview's demo-login button must send one request and open the admin dashboard, the sign-in page must
// lead back to the admin while signed in, and wire:navigate to Leads and a full reload must stay signed in.
// Usage: node tools/sandbox/iframe-check.mjs        (preview server running on PORT, default 8080)
import fs from 'node:fs';
import http from 'node:http';
import { createRequire } from 'node:module';

process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x'; // see browser-check.mjs
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', { force: true });
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;

const PORT = Number(process.env.PORT || 8080);
const PUBLIC_HOST = `${PORT}-${process.env.E2B_SANDBOX_ID || 'sandbox'}.e2b.app`;
const HARNESS_PORT = 9090;
const SHOTS = '/tmp/woodex-run/shots';
fs.mkdirSync(SHOTS, { recursive: true });
const listen = (server, port) => new Promise((resolve) => server.listen(port, '127.0.0.1', resolve));

// Stand-in for the preview proxy. Ports are bound to 127.0.0.1 only (never shown as a preview).
function previewProxy({ dropCookies }) {
    return http.createServer((req, res) => {
        const headers = { ...req.headers, host: PUBLIC_HOST };
        delete headers.referer;
        if (dropCookies) delete headers.cookie;
        const upstream = http.request({ host: '127.0.0.1', port: PORT, method: req.method, path: req.url, headers }, (up) => {
            const out = { ...up.headers };
            if (dropCookies) delete out['set-cookie'];
            res.writeHead(up.statusCode ?? 502, out);
            up.pipe(res);
        });
        upstream.on('error', (error) => {
            res.writeHead(502, { 'Content-Type': 'text/plain' });
            res.end(String(error));
        });
        req.pipe(upstream);
    });
}

// The "Arena page": a different site that embeds the preview in an iframe.
const harness = http.createServer((req, res) => {
    const src = new URL(req.url ?? '/', 'http://localhost').searchParams.get('src') ?? 'about:blank';
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`<!doctype html><meta charset="utf-8"><title>Preview harness</title>
<body style="margin:0;background:#d1d5db"><iframe id="preview" src="${src.replace(/"/g, '&quot;')}" style="display:block;width:1373px;height:860px;border:0;background:#fff"></iframe></body>`);
});

const servers = [harness];
await listen(harness, HARNESS_PORT);

async function scenario(name, { dropCookies, blockThirdPartyCookies }) {
    const proxy = previewProxy({ dropCookies });
    const proxyPort = dropCookies ? 9092 : 9091;
    servers.push(proxy);
    await listen(proxy, proxyPort);
    const base = `http://127.0.0.1:${proxyPort}`;

    // A unique user agent per run keeps the preview server's per-browser cookie memory apart between runs.
    const userAgent = `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/153.0.0.0 Safari/537.36 iframe-check/${name}/${Date.now()}`;
    const browser = await puppeteer.launch({
        executablePath: await chromium.executablePath(),
        args: [...chromium.args, '--no-sandbox', `--user-agent=${userAgent}`, ...(blockThirdPartyCookies ? ['--test-third-party-cookie-phaseout'] : [])],
        headless: true,
        defaultViewport: { width: 1400, height: 900 },
    });
    const page = await browser.newPage();
    const problems = [];
    const jarResponses = [];
    page.on('response', (r) => {
        const jar = r.headers()['x-preview-jar'];
        if (jar) jarResponses.push(`${r.request().method()} ${new URL(r.url()).pathname} ${jar}`);
        if (r.status() >= 500) problems.push(`HTTP ${r.status()}: ${r.url()}`);
    });
    page.on('pageerror', (e) => problems.push(`page error: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error') problems.push(`console error: ${m.text()}`); });

    const frame = async () => (await page.$('#preview')).contentFrame();
    const state = async () => (await frame()).evaluate(() => ({
        path: location.pathname,
        loginForm: !!document.querySelector('input[name="password"]'),
        alert: document.querySelector('[role="alert"]')?.textContent.replace(/\s+/g, ' ').trim() ?? null,
        heading: document.querySelector('h1')?.textContent.replace(/\s+/g, ' ').trim() ?? null,
        sidebar: !!document.querySelector('aside a[href="/admin/leads"]'),
    }));
    const submitAndWait = async (action) => {
        const f = await frame();
        await Promise.all([f.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }), action(f)]);
    };
    const expect = (label, condition, detail) => {
        console.log(`  ${condition ? 'ok  ' : 'FAIL'} ${label}  ${JSON.stringify(detail)}`);
        if (!condition) problems.push(`${label}: ${JSON.stringify(detail)}`);
    };

    console.log(`\n[${name}] app in a cross-site iframe via ${base}${dropCookies ? ' (proxy drops all cookies)' : ''}${blockThirdPartyCookies ? ', third-party cookies blocked' : ''}`);
    await page.goto(`http://localhost:${HARNESS_PORT}/?src=${encodeURIComponent(`${base}/admin/login`)}`, { waitUntil: 'networkidle0', timeout: 60000 });

    // What the browser lets the framed page keep (informational).
    const probe = await (await frame()).evaluate(() => {
        const has = (n) => { try { return document.cookie.split('; ').some((c) => c.startsWith(`${n}=`)); } catch { return 'error'; } };
        try { document.cookie = 'wx_probe_plain=1; SameSite=None; Secure; Path=/'; } catch { /* blocked */ }
        try { document.cookie = 'wx_probe_part=1; SameSite=None; Secure; Path=/; Partitioned'; } catch { /* blocked */ }
        return { serverXsrfCookieKept: has('XSRF-TOKEN'), jsPlainCookie: has('wx_probe_plain'), jsPartitionedCookie: has('wx_probe_part'), cookieEnabled: navigator.cookieEnabled };
    });
    console.log(`  cookie probe ${JSON.stringify(probe)}`);
    const login = await state();
    expect('sign-in form shown', login.path === '/admin/login' && login.loginForm, login);
    const hint = await (await frame()).$('#wx-preview-login');
    expect('preview demo-login box shown', !!hint, { found: !!hint });
    await page.screenshot({ path: `${SHOTS}/iframe-${name}-1-login.png` });

    // 1. The owner's real attempt on 2026-10-15: unknown e-mail -> must stay on the form and show the error.
    await (await frame()).type('input[name="email"]', 'admin@woodex.com.pk');
    await (await frame()).type('input[name="password"]', 'not-the-password');
    await submitAndWait((f) => f.click('form button[type="submit"]'));
    const wrong = await state();
    expect('wrong password -> back on the form with the error', wrong.path === '/admin/login' && /do not match/i.test(wrong.alert ?? ''), wrong);
    await page.screenshot({ path: `${SHOTS}/iframe-${name}-2-wrong-password.png` });

    // 2. Demo login, clicked twice like the owner did on 2026-10-15 (signing in takes about a second). Only one
    //    POST may leave the page, and the result must be the admin dashboard, never the public "/".
    if (!(await state()).loginForm) {
        await (await frame()).goto(`${base}/admin/login`, { waitUntil: 'networkidle0', timeout: 60000 });
    }
    let signInPosts = 0;
    const countPosts = (r) => { if (r.method() === 'POST' && new URL(r.url()).pathname === '/admin/login') signInPosts += 1; };
    page.on('request', countPosts);
    if (await (await frame()).$('#wx-preview-login button')) {
        await submitAndWait(async (f) => {
            await f.click('#wx-preview-login button');
            await f.click('#wx-preview-login button').catch(() => { /* page already left */ });
        });
    } else {
        const f = await frame();
        await f.$eval('input[name="email"]', (el) => { el.value = ''; });
        await f.type('input[name="email"]', 'owner@woodex.test');
        await f.type('input[name="password"]', 'Woodex-Preview-2026');
        await submitAndWait((g) => g.click('form button[type="submit"]'));
    }
    page.off('request', countPosts);
    const signedIn = await state();
    expect('demo login (double click) -> admin dashboard', signedIn.path === '/admin' && signedIn.sidebar && !signedIn.loginForm, signedIn);
    expect('double click sends one sign-in request', signInPosts === 1, { signInPosts });
    await new Promise((r) => setTimeout(r, 1500));
    await page.screenshot({ path: `${SHOTS}/iframe-${name}-3-dashboard.png` });

    // 3. wire:navigate (fetch) to Leads keeps the session.
    if (signedIn.sidebar) {
        const f = await frame();
        await Promise.all([page.waitForNetworkIdle({ idleTime: 500, timeout: 60000 }), f.click('aside a[href="/admin/leads"]')]);
        await new Promise((r) => setTimeout(r, 500));
        const leads = await state();
        expect('wire:navigate to Leads stays signed in', leads.path === '/admin/leads' && !leads.loginForm, leads);
    }

    // 4. Opening the sign-in page again while signed in leads to the admin, not to the public site.
    await (await frame()).goto(`${base}/admin/login`, { waitUntil: 'networkidle0', timeout: 60000 });
    const again = await state();
    expect('sign-in page while signed in -> admin dashboard', again.path === '/admin' && !again.loginForm, again);

    // 5. A full page load inside the frame keeps the session too.
    await (await frame()).goto(`${base}/admin/pipeline`, { waitUntil: 'networkidle0', timeout: 60000 });
    const pipeline = await state();
    expect('full reload of /admin/pipeline stays signed in', pipeline.path === '/admin/pipeline' && !pipeline.loginForm, pipeline);
    await page.screenshot({ path: `${SHOTS}/iframe-${name}-4-pipeline.png` });

    // Informational: "memory+" = the browser sent no cookie at all, "memory~" = it sent an older value.
    const missing = jarResponses.filter((r) => r.includes('memory+'));
    const older = jarResponses.filter((r) => r.includes('memory~'));
    console.log(`  server cookie memory: browser sent no cookies on ${missing.length} requests${missing.length ? ` (first: ${missing[0]})` : ''}, older values on ${older.length}`);
    await browser.close();
    if (problems.length) console.log(`  PROBLEMS:\n    ${problems.join('\n    ')}`);
    return problems.length === 0;
}

const results = {
    '3pc-blocked': await scenario('3pc-blocked', { dropCookies: false, blockThirdPartyCookies: true }),
    'no-cookies': await scenario('no-cookies', { dropCookies: true, blockThirdPartyCookies: true }),
};
servers.forEach((s) => s.close());
const ok = Object.values(results).every(Boolean);
console.log(`\n${Object.entries(results).map(([k, v]) => `${k}: ${v ? 'OK' : 'FAILED'}`).join(', ')}`);
console.log(ok ? 'IFRAME CHECK OK' : 'IFRAME CHECK FAILED');
process.exit(ok ? 0 : 1);
