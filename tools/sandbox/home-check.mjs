// Real-browser check of the public website (tools/sandbox/README.md, "Website check"): loads the home page on a
// desktop, a phone and with JavaScript off, and fails on console errors, 404s, horizontal scrolling, an unloaded
// hero image, a heading that lost its CSS, or a form/FAQ that is invisible without JavaScript.
// Usage: node tools/sandbox/home-check.mjs [baseUrl]
import fs from 'node:fs';
import { createRequire } from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', { force: true });
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;

const BASE = process.argv[2] || 'http://127.0.0.1:8080';
const SHOTS = '/tmp/woodex-run/shots';
fs.mkdirSync(SHOTS, { recursive: true });
const browser = await puppeteer.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args, '--no-sandbox'], headless: true });
const problems = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function shoot(name, { width, height, js = true }) {
    const page = await browser.newPage();
    await page.setJavaScriptEnabled(js);
    await page.setViewport({ width, height });
    page.on('pageerror', (e) => problems.push(`${name}: page error: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error') problems.push(`${name}: console: ${m.text()}`); });
    page.on('response', (r) => { if (r.status() >= 400) problems.push(`${name}: HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/`, { waitUntil: 'networkidle0', timeout: 60000 });
    await sleep(400);
    const info = await page.evaluate(() => ({
        width: document.documentElement.scrollWidth,
        overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        h1: document.querySelector('h1')?.innerText ?? null,
        h1Font: getComputedStyle(document.querySelector('h1')).fontFamily.split(',')[0],
        h1Size: getComputedStyle(document.querySelector('h1')).fontSize,
        h2Size: getComputedStyle(document.querySelector('h2')).fontSize,
        heroLoaded: document.querySelector('img[fetchpriority="high"]')?.naturalWidth ?? 0,
        images: document.images.length,
        sections: [...document.querySelectorAll('section[id]')].map((s) => s.id),
    }));
    if (info.overflow) problems.push(`${name}: horizontal scroll (${info.width} > ${width})`);
    if (!info.heroLoaded) problems.push(`${name}: hero image not loaded`);
    if (parseFloat(info.h1Size) < 30) problems.push(`${name}: h1 is ${info.h1Size} (CSS missing?)`);
    console.log(name, JSON.stringify(info));
    await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true });

    if (!js) {
        const visible = await page.evaluate(() => {
            const v = (sel) => { const el = document.querySelector(sel); return !!el && el.getClientRects().length > 0; };
            return { name: v('#lf-name'), phone: v('#lf-phone'), submit: v('form#enquiry button[type="submit"]'), faqAnswer: v('#faq details p'), menu: v('header nav a') };
        });
        console.log('  no-JS visible:', JSON.stringify(visible));
        Object.entries(visible).forEach(([key, ok]) => { if (!ok) problems.push(`no-JS: ${key} not visible`); });
    }
    await page.close();
}

await shoot('home-desktop', { width: 1440, height: 900 });
await shoot('home-phone', { width: 390, height: 844 });
await shoot('home-nojs', { width: 1280, height: 800, js: false });
await browser.close();
console.log(problems.length ? `PROBLEMS:\n  ${problems.join('\n  ')}` : 'HOME CHECK OK');
process.exit(problems.length ? 1 : 0);
