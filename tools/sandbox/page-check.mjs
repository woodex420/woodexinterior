// Real-browser check of one public page of the website (tools/sandbox/README.md, "Website check").
// The home page has its own check (home-check.mjs); this one takes any URL and is the check for the pages that
// live in the CMS: layout on a desktop and a phone, headings with their CSS, images with alt text and
// dimensions, working internal links, valid JSON-LD, and a page that still reads with JavaScript off.
//
// Usage: node tools/sandbox/page-check.mjs [path-or-url ...]   (default: /interior-design/)
import fs from 'node:fs';
import { createRequire } from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', { force: true });
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const SHOTS = '/tmp/woodex-run/shots';
const paths = process.argv.slice(2);
const targets = paths.length ? paths : ['/interior-design/'];
fs.mkdirSync(SHOTS, { recursive: true });

const browser = await puppeteer.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args, '--no-sandbox'], headless: true });
const problems = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const urlOf = (path) => (path.startsWith('http') ? path : `${BASE}${path}`);

async function check(path) {
    const name = path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home';
    const url = urlOf(path);

    for (const viewport of [{ suffix: 'desktop', width: 1440, height: 900 }, { suffix: 'phone', width: 390, height: 844 }]) {
        const page = await browser.newPage();
        await page.setViewport({ width: viewport.width, height: viewport.height });
        page.on('pageerror', (e) => problems.push(`${name}/${viewport.suffix}: page error: ${e.message}`));
        page.on('console', (m) => { if (m.type() === 'error') problems.push(`${name}/${viewport.suffix}: console: ${m.text()}`); });
        page.on('response', (r) => { if (r.status() >= 400) problems.push(`${name}/${viewport.suffix}: HTTP ${r.status()} ${r.url()}`); });

        const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });
        await sleep(400);

        const info = await page.evaluate(() => {
            const css = (sel) => { const el = document.querySelector(sel); return el ? getComputedStyle(el).fontSize : null; };
            return {
                status: document.title,
                overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
                width: document.documentElement.scrollWidth,
                h1: document.querySelectorAll('h1').length,
                h1Text: document.querySelector('h1')?.innerText ?? null,
                h1Size: css('h1'),
                h2Size: css('h2'),
                sections: document.querySelectorAll('main section').length,
                images: [...document.images].length,
                imagesWithoutAlt: [...document.images].filter((i) => !i.getAttribute('alt')).length,
                imagesWithoutSize: [...document.images].filter((i) => !i.getAttribute('width') || !i.getAttribute('height')).length,
                lazy: [...document.images].filter((i) => i.loading === 'lazy').length,
                ld: (() => { try { return JSON.parse(document.querySelector('script[type="application/ld+json"]')?.textContent ?? 'null'); } catch { return 'invalid'; } })(),
                links: [...document.querySelectorAll('a[href^="/"]')].map((a) => a.getAttribute('href')),
                canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href') ?? null,
            };
        });

        if (response.status() !== 200) problems.push(`${name}/${viewport.suffix}: HTTP ${response.status()}`);
        if (info.overflow) problems.push(`${name}/${viewport.suffix}: horizontal scroll (${info.width} > ${viewport.width})`);
        if (info.h1 !== 1) problems.push(`${name}/${viewport.suffix}: ${info.h1} h1 elements (expected exactly one)`);
        if (parseFloat(info.h1Size) < 30) problems.push(`${name}/${viewport.suffix}: h1 is ${info.h1Size} (CSS missing?)`);
        if (!info.h2Size) problems.push(`${name}/${viewport.suffix}: no h2 on the page`);
        if (info.imagesWithoutAlt) problems.push(`${name}/${viewport.suffix}: ${info.imagesWithoutAlt} image(s) without alt text`);
        if (info.imagesWithoutSize) problems.push(`${name}/${viewport.suffix}: ${info.imagesWithoutSize} image(s) without width/height`);
        if (info.ld === 'invalid' || info.ld === null) problems.push(`${name}/${viewport.suffix}: JSON-LD missing or invalid`);
        if (!info.canonical) problems.push(`${name}/${viewport.suffix}: no canonical URL`);

        console.log(`${name}-${viewport.suffix}`, JSON.stringify({ ...info, ld: Array.isArray(info.ld?.['@graph']) ? info.ld['@graph'].length : info.ld }));
        await page.screenshot({ path: `${SHOTS}/page-${name}-${viewport.suffix}.png`, fullPage: true });
        await page.close();
    }

    // Internal links must answer 200 (or one permanent redirect to a page that does).
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    const links = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href^="/"]')].map((a) => a.getAttribute('href')))]);
    await page.close();

    for (const link of links) {
        let target = urlOf(link);
        for (let hop = 0; hop < 2; hop++) {
            const res = await fetch(target, { redirect: 'manual' });
            if (res.status === 200) break;
            if ((res.status === 301 || res.status === 302) && hop === 0) { target = new URL(res.headers.get('location'), target).toString(); continue; }
            problems.push(`${name}: link ${link} -> ${res.status}`);
            break;
        }
    }
    console.log(`  ${links.length} internal link(s) checked`);
}

for (const path of targets) {
    await check(path);
}

await browser.close();
console.log(problems.length ? `PROBLEMS:\n  ${problems.join('\n  ')}` : 'PAGE CHECK OK');
process.exit(problems.length ? 1 : 0);
