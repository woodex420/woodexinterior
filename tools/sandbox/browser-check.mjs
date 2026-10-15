// Real-browser check of the running preview (headless Chromium from npm: @sparticuz/chromium + puppeteer-core,
// installed by setup.sh into /tmp/browser). Loads pages from a different origin than APP_URL, logs failed requests
// and console errors, checks that the CSS really applied and saves screenshots to /tmp/woodex-run/shots/.
// Usage: node tools/sandbox/browser-check.mjs [baseUrl]
import fs from 'node:fs';
import { createRequire } from 'node:module';

// The package only unpacks its bundled system libraries (libnss3, libnspr4, ...) on "Amazon Linux 2023";
// this flag makes it do so here too (it must be set before the import).
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', { force: true });
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;

const BASE = process.argv[2] || 'http://127.0.0.1:8080';
const SHOTS = '/tmp/woodex-run/shots';
fs.mkdirSync(SHOTS, { recursive: true });

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
    defaultViewport: { width: 1366, height: 860 },
});
const page = await browser.newPage();
const problems = [];
let jsDisabled = false; // Chrome cancels module scripts while JS is off: not a real failure
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
page.on('requestfailed', (r) => { if (!jsDisabled) problems.push(`request failed: ${r.url()} (${r.failure()?.errorText})`); });
page.on('response', (r) => { if (r.status() >= 400) problems.push(`HTTP ${r.status()}: ${r.url()}`); });
page.on('console', (m) => { if (m.type() === 'error') problems.push(`console error: ${m.text()}`); });
page.on('pageerror', (e) => problems.push(`page error: ${e.message}`));

async function inspect(name, url) {
    await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });
    const info = await page.evaluate(() => {
        const body = getComputedStyle(document.body);
        return {
            title: document.title,
            url: location.href,
            fontFamily: body.fontFamily,
            background: body.backgroundColor,
            stylesheets: [...document.styleSheets].map((s) => s.href).filter(Boolean),
            alpine: typeof window.Alpine !== 'undefined',
            livewire: typeof window.Livewire !== 'undefined',
        };
    });
    // Charts: exactly one ApexCharts canvas per [data-chart] element, never wider than its card.
    const charts = await page.evaluate(() => [...document.querySelectorAll('[data-chart]')].map((el) => {
        const svg = el.querySelector('svg.apexcharts-svg');
        const card = el.closest('section') ?? el.parentElement;
        return {
            canvases: el.querySelectorAll('.apexcharts-canvas').length,
            svgRight: svg ? Math.round(svg.getBoundingClientRect().right) : null,
            cardRight: Math.round(card.getBoundingClientRect().right),
        };
    }));
    charts.forEach((c, i) => {
        if (c.canvases !== 1) problems.push(`${name}: chart ${i} rendered ${c.canvases} times`);
        if (c.svgRight !== null && c.svgRight > c.cardRight + 1) problems.push(`${name}: chart ${i} overflows its card (${c.svgRight} > ${c.cardRight})`);
    });
    info.charts = charts;
    await page.screenshot({ path: `${SHOTS}/${name}.png` });
    console.log(`${name}: ${JSON.stringify(info)}`);
    return info;
}

const home = await inspect('home', `${BASE}/`);

// The public lead form must be visible even before/without JavaScript.
const formState = () => page.evaluate(() => {
    const visible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
    const button = document.querySelector('form button[type="submit"]');
    return {
        nameField: visible(document.querySelector('#lf-name')),
        button: visible(button) ? button.textContent.trim() : null,
        buttonDisabled: button?.disabled ?? null,
        thankYouVisible: visible([...document.querySelectorAll('form h2')].find((h) => h.textContent.includes('Thank you'))),
    };
});
jsDisabled = true;
await page.setJavaScriptEnabled(false);
await page.goto(`${BASE}/`, { waitUntil: 'networkidle0' });
const noJs = await formState();
console.log(`home without JS: ${JSON.stringify(noJs)}`);
if (!noJs.nameField || noJs.button !== 'Send enquiry' || noJs.thankYouVisible) problems.push(`home without JS: form not usable ${JSON.stringify(noJs)}`);
await page.screenshot({ path: `${SHOTS}/home-no-js.png` });
await page.setJavaScriptEnabled(true);
jsDisabled = false;
await page.goto(`${BASE}/`, { waitUntil: 'networkidle0' });
const withJs = await formState();
console.log(`home with JS: ${JSON.stringify(withJs)}`);
if (!withJs.nameField || withJs.buttonDisabled || withJs.thankYouVisible) problems.push(`home with JS: unexpected form state ${JSON.stringify(withJs)}`);

if (process.env.SUBMIT === '1') {
    // Creates a real lead in the preview database.
    await page.type('#lf-name', 'Preview Browser Check');
    await page.type('#lf-phone', '0300 1234567');
    await page.click('input[name="consent"]');
    await sleep(3500); // min_fill_seconds anti-bot rule
    await page.click('form button[type="submit"]');
    await page.waitForFunction(() => [...document.querySelectorAll('form h2')].some((h) => h.textContent.includes('Thank you') && h.getClientRects().length > 0), { timeout: 30000 });
    const reference = await page.$eval('form strong', (el) => el.textContent.trim());
    console.log(`lead form submitted: reference ${reference}`);
    if (!/^L-\d{4}-/.test(reference)) problems.push(`lead form: unexpected reference "${reference}"`);
    await page.screenshot({ path: `${SHOTS}/home-submitted.png` });
}
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle0' });
await page.type('input[name="email"]', 'owner@woodex.test');
await page.type('input[name="password"]', 'Woodex-Preview-2026');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
const dashboard = await inspect('dashboard', page.url());
// Layout: the content column must already sit beside the sidebar on load (no slide-in).
const layout = () => page.evaluate(() => ({
    contentLeft: Math.round(document.querySelector('main').getBoundingClientRect().left),
    sidebarWidth: Math.round(document.querySelector('aside').getBoundingClientRect().width),
    sidebarLeft: Math.round(document.querySelector('aside').getBoundingClientRect().left),
}));
const expectLayout = async (label, check) => {
    const l = await layout();
    if (!check(l)) problems.push(`${label}: unexpected layout ${JSON.stringify(l)}`);
    console.log(`${label}: ${JSON.stringify(l)}`);
};
await expectLayout('dashboard layout', (l) => l.sidebarWidth === 280 && l.contentLeft === 280);

// Collapse the sidebar: content moves to 88px and the chart re-fits its card; then expand again.
const toggle = 'header button[\\@click="$store.sidebar.toggle()"]';
await page.click(toggle);
await sleep(900);
await expectLayout('collapsed', (l) => l.sidebarWidth === 88 && l.contentLeft === 88);
await inspect('dashboard-collapsed', page.url());
await page.click(toggle);
await sleep(900);
await expectLayout('expanded', (l) => l.sidebarWidth === 280 && l.contentLeft === 280);

// wire:navigate to Leads and back: no slide, one chart, no errors.
await Promise.all([page.waitForNetworkIdle({ idleTime: 400 }), page.click('aside a[href="/admin/leads"]')]);
await sleep(400);
await expectLayout('leads via wire:navigate', (l) => l.contentLeft === 280);
await Promise.all([page.waitForNetworkIdle({ idleTime: 400 }), page.click('aside a[href="/admin"]')]);
await sleep(900);
await inspect('dashboard-after-navigate', page.url());
await inspect('leads', `${BASE}/admin/leads`);
await inspect('pipeline', `${BASE}/admin/pipeline`);

// Phone width: the sidebar is an off-canvas drawer that the header button opens.
await page.setViewport({ width: 390, height: 844 });
await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle0' });
await expectLayout('mobile closed', (l) => l.sidebarLeft <= -280 && l.contentLeft === 0);
await page.click(toggle);
await sleep(600);
await expectLayout('mobile open', (l) => l.sidebarLeft === 0 && l.sidebarWidth === 280);
await page.screenshot({ path: `${SHOTS}/mobile-open.png` });

await browser.close();
const serif = (f) => /^"?(Times|serif)/i.test(f);
const ok = problems.length === 0 && !serif(home.fontFamily) && !serif(dashboard.fontFamily) && dashboard.url.includes('/admin') && !dashboard.url.includes('login');
console.log(problems.length ? `PROBLEMS:\n  ${problems.join('\n  ')}` : 'no failed requests or console errors');
console.log(ok ? 'BROWSER CHECK OK' : 'BROWSER CHECK FAILED');
process.exit(ok ? 0 : 1);
