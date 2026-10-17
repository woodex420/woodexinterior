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

// The sending step changes demo data (an approval decided, a frozen version, a quotation accepted, its lead won). On
// the local preview the SQLite file is copied first and put back when the check ends, so the check can run again and
// the demo stays as seeded.
const DB_FILE = process.env.BROWSER_DB_FILE || '/tmp/woodex-run/app/database/database.sqlite';
const DB_COPY = process.env.BROWSER_DB_COPY || `${DB_FILE}.before-browser-check`;
const restoreDb = /^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE) && fs.existsSync(DB_FILE);
if (restoreDb) fs.copyFileSync(DB_FILE, DB_COPY);
process.on('exit', () => { if (restoreDb) fs.copyFileSync(DB_COPY, DB_FILE); });

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
const watch = (p, who = '') => {
    p.on('requestfailed', (r) => { if (!jsDisabled) problems.push(`${who}request failed: ${r.url()} (${r.failure()?.errorText})`); });
    p.on('response', (r) => { if (r.status() >= 400) problems.push(`${who}HTTP ${r.status()}: ${r.url()}`); });
    p.on('console', (m) => { if (m.type() === 'error') problems.push(`${who}console error: ${m.text()}`); });
    p.on('pageerror', (e) => problems.push(`${who}page error: ${e.message}`));
};
watch(page);
// Clicks the visible button or link whose whole text is `text` (icons have no text). False when there is none.
async function clickText(p, text) {
    const handle = await p.evaluateHandle((t) => [...document.querySelectorAll('button, a')]
        .find((el) => el.textContent.replace(/\s+/g, ' ').trim() === t && el.getClientRects().length > 0) ?? null, text);
    const el = handle.asElement();
    if (!el) return false;
    await el.evaluate((node) => node.scrollIntoView({ block: 'center' }));
    await el.click();
    return true;
}
const hasText = (p, text) => p.evaluate((t) => document.body.innerText.includes(t), text);
// Downloads a PDF in the page (with its cookies) and reports what came back.
const pdfInfo = (p, url) => p.evaluate(async (u) => {
    const r = await fetch(u, { credentials: 'same-origin' });
    const bytes = new Uint8Array(await r.arrayBuffer());
    return { status: r.status, type: r.headers.get('content-type'), magic: String.fromCharCode(...bytes.slice(0, 5)), kb: Math.round(bytes.length / 1024) };
}, url);
const pdfOk = (info) => info.status === 200 && info.magic === '%PDF-' && /application\/pdf/.test(info.type ?? '');

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

// Quote builder (P2): a field saves when it loses focus and the server total changes; the value is put back
// afterwards so the check leaves the demo quote as it was. Livewire 4's plain wire:model.blur never reached the
// server; this is the check that caught it (D-55).
await inspect('quotes', `${BASE}/admin/quotes`);
// A draft: quotations waiting for approval or already sent are read-only.
const firstQuote = (await page.evaluateHandle(() => [...document.querySelectorAll('tbody tr')]
    .find((tr) => /\bDraft\b/.test(tr.textContent))?.querySelector('a[href*="/admin/quotes/"]') ?? null)).asElement();
if (!firstQuote) {
    problems.push('quotes: no draft quotation in the list (run the DemoQuotesSeeder)');
} else {
    await Promise.all([page.waitForSelector('[data-quote-total]', { timeout: 30000 }), firstQuote.click()]);
    await sleep(300);
    const quoteTotal = () => page.$eval('[data-quote-total]', (el) => el.textContent.trim());
    const livewireUpdate = () => page.waitForResponse((r) => r.url().includes('/update') && r.request().method() === 'POST', { timeout: 30000 });
    const setField = async (selector, value) => {
        const input = await page.$(selector);
        await input.evaluate((el) => { el.scrollIntoView({ block: 'center' }); el.focus(); el.select(); });
        await page.keyboard.type(value);
        await Promise.all([livewireUpdate(), page.keyboard.press('Tab')]);
        await sleep(400); // morph
    };
    const qtySelector = '[data-item-id] input[aria-label="Quantity"]';
    const original = await page.$eval(qtySelector, (el) => el.value);
    const before = await quoteTotal();
    await setField(qtySelector, String(Number(original || 0) + 7));
    const changed = await quoteTotal();
    await setField(qtySelector, original);
    const restored = await quoteTotal();
    console.log(`quote builder: total ${before} -> ${changed} -> ${restored}`);
    if (changed === before) problems.push('quote builder: editing a quantity did not change the server total');
    if (restored !== before) problems.push(`quote builder: total not restored (${restored} vs ${before})`);
    await page.screenshot({ path: `${SHOTS}/quote-builder.png` });
}

// Approvals, sending and the client page (P2 part 2a: QUO-007..011, 014). A waiting approval (when there is one)
// is approved from the Approvals page. The owner never needs approval, so the first draft without problems is sent;
// then a client (separate browser profile, no staff session) opens the link on a desktop and a phone, downloads the
// PDF and accepts, and the builder shows the acceptance.
await inspect('approvals', `${BASE}/admin/approvals`);
if (await clickText(page, 'Approve')) {
    await page.waitForFunction(() => document.body.innerText.includes('Decided in the last 30 days'), { timeout: 30000 })
        .catch(() => problems.push('approvals: approving did not move the request to "Decided"'));
    await sleep(300);
    await page.screenshot({ path: `${SHOTS}/approvals-decided.png` });
    console.log('approvals: approved a waiting quotation');
}
await page.goto(`${BASE}/admin/quotes`, { waitUntil: 'networkidle0' });
const quoteHrefs = await page.$$eval('tbody a[href*="/admin/quotes/"]', (links) => [...new Set(links.map((a) => a.href))]);
let sent = null;
for (const href of quoteHrefs) {
    await page.goto(href, { waitUntil: 'networkidle0' });
    if (!(await clickText(page, 'Send'))) continue; // waiting for approval or already sent
    await page.waitForFunction(() => /Send now|Fix these first|Request approval/.test(document.body.innerText), { timeout: 30000 });
    await sleep(300);
    if (await hasText(page, 'Fix these first')) {
        await page.screenshot({ path: `${SHOTS}/quote-send-problems.png` });
        continue;
    }
    if (!(await hasText(page, 'Send now'))) continue;
    await page.screenshot({ path: `${SHOTS}/quote-send-modal.png` });
    await clickText(page, 'Send now');
    await page.waitForSelector('input[aria-label="Client link"]', { timeout: 90000 });
    await sleep(400);
    sent = { href, link: await page.$eval('input[aria-label="Client link"]', (el) => el.value) };
    await page.screenshot({ path: `${SHOTS}/quote-sent.png`, fullPage: true });
    break;
}
if (!sent) {
    problems.push('sending: no quotation could be sent (all have problems or wait for approval)');
} else {
    const staffPdf = await pdfInfo(page, await page.$eval('a[href$="/pdf"]', (a) => a.href));
    console.log(`sent ${sent.href}: staff PDF ${JSON.stringify(staffPdf)}`);
    if (!pdfOk(staffPdf)) problems.push(`staff PDF: ${JSON.stringify(staffPdf)}`);

    const link = new URL(sent.link, BASE);
    const portalUrl = `${BASE}${link.pathname}${link.search}`;
    // Signed-in staff opening the link are not counted as client views.
    await page.goto(portalUrl, { waitUntil: 'networkidle0', timeout: 60000 });
    // The client: the same tab without the staff cookies (this Chromium runs single-process and cannot open a second
    // browser profile); the staff cookies are put back afterwards.
    const staffCookies = await page.cookies();
    await page.deleteCookie(...staffCookies);
    const client = page;
    await client.setViewport({ width: 1366, height: 860 });
    await client.goto(portalUrl, { waitUntil: 'networkidle0', timeout: 60000 });
    const portal = await client.evaluate(() => ({
        title: document.title,
        h1: document.querySelector('h1')?.textContent.trim() ?? null,
        font: getComputedStyle(document.body).fontFamily,
        width: document.documentElement.scrollWidth,
        acceptForm: !!document.querySelector('#accept-name'),
    }));
    console.log(`client page (desktop): ${JSON.stringify(portal)}`);
    if (!portal.h1 || /^"?(Times|serif)/i.test(portal.font) || !portal.acceptForm) problems.push(`client page: ${JSON.stringify(portal)}`);
    await client.screenshot({ path: `${SHOTS}/portal-desktop.png`, fullPage: true });

    const clientPdf = await pdfInfo(client, await client.$eval('a[href*="/pdf"]', (a) => a.href));
    console.log(`client PDF: ${JSON.stringify(clientPdf)}`);
    if (!pdfOk(clientPdf)) problems.push(`client PDF: ${JSON.stringify(clientPdf)}`);

    await client.setViewport({ width: 390, height: 844 });
    await client.reload({ waitUntil: 'networkidle0' });
    const phoneWidth = await client.evaluate(() => document.documentElement.scrollWidth);
    console.log(`client page on a phone: page width ${phoneWidth}`);
    if (phoneWidth > 392) problems.push(`client page on a phone: page is ${phoneWidth}px wide`);
    await client.screenshot({ path: `${SHOTS}/portal-phone.png`, fullPage: true });

    await client.type('#accept-name', 'Ayesha Khan');
    await client.click('input[name="confirm"]');
    // The form's own button: the hero has an "Accept quotation" link that only scrolls to the form.
    await Promise.all([client.waitForNavigation({ waitUntil: 'networkidle0' }), client.click('#accept button[type="submit"]')]);
    const acceptedText = await client.evaluate(() => document.body.innerText);
    console.log(`client accepted: ${/accepted/i.test(acceptedText)}`);
    if (!/accepted/i.test(acceptedText) || await client.$('#accept-name')) problems.push('client page: accepting did not confirm (or the form is still open)');
    await client.screenshot({ path: `${SHOTS}/portal-accepted.png`, fullPage: true });
    const clientCookies = await page.cookies();
    if (clientCookies.length) await page.deleteCookie(...clientCookies);
    await page.setCookie(...staffCookies);
    await page.setViewport({ width: 1366, height: 860 });

    await page.goto(sent.href, { waitUntil: 'networkidle0' });
    const banner = await hasText(page, 'Accepted online by Ayesha Khan');
    const views = await page.evaluate(() => [...document.querySelectorAll('dt')].find((dt) => dt.textContent.trim() === 'Views')?.nextElementSibling?.textContent.trim() ?? null);
    console.log(`builder after acceptance: banner ${banner}, client views ${views}`);
    if (!banner) problems.push('builder: no "Accepted online" banner after the client accepted');
    // desktop + phone + the page after accepting; the staff visit above and the PDF download are not views
    if (views !== '3') problems.push(`builder: expected 3 client views, saw ${views}`);
    await page.screenshot({ path: `${SHOTS}/quote-accepted.png` });
}

// Phone width: the sidebar is an off-canvas drawer that the header button opens.
await page.setViewport({ width: 390, height: 844 });
await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle0' });
await expectLayout('mobile closed', (l) => l.sidebarLeft <= -280 && l.contentLeft === 0);
await page.click(toggle);
await sleep(600);
await expectLayout('mobile open', (l) => l.sidebarLeft === 0 && l.sidebarWidth === 280);
await page.screenshot({ path: `${SHOTS}/mobile-open.png` });

// The quote builder fits a phone screen without sideways scrolling.
await page.goto(`${BASE}/admin/quotes`, { waitUntil: 'networkidle0' });
const phoneQuote = await page.$eval('tbody a[href*="/admin/quotes/"]', (a) => a.href).catch(() => null);
if (phoneQuote) {
    await page.goto(phoneQuote, { waitUntil: 'networkidle0' });
    const width = await page.evaluate(() => document.documentElement.scrollWidth);
    console.log(`quote builder on a phone: page width ${width}`);
    if (width > 392) problems.push(`quote builder on a phone: page is ${width}px wide`);
    await page.screenshot({ path: `${SHOTS}/quote-builder-mobile.png` });
}

await browser.close();
const serif = (f) => /^"?(Times|serif)/i.test(f);
const ok = problems.length === 0 && !serif(home.fontFamily) && !serif(dashboard.fontFamily) && dashboard.url.includes('/admin') && !dashboard.url.includes('login');
console.log(problems.length ? `PROBLEMS:\n  ${problems.join('\n  ')}` : 'no failed requests or console errors');
console.log(ok ? 'BROWSER CHECK OK' : 'BROWSER CHECK FAILED');
process.exit(ok ? 0 : 1);
