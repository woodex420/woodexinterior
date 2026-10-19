// Real-browser proof of the page builder (19 Oct): the live preview follows typing, drag-and-drop
// reorders a page, a click in the preview jumps to that section's form, and a brand-new page saves
// itself and shows its preview before the first explicit Save.
//
// Usage: node tools/sandbox/builder-check.mjs [baseUrl]
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

// The drag and the autosave change demo data: copy the SQLite file first and put it back at the end.
const DB_FILE = process.env.BUILDER_DB_FILE || '/tmp/woodex-run/app/database/database.sqlite';
const DB_COPY = `${DB_FILE}.before-builder-check`;
const restoreDb = /^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE) && fs.existsSync(DB_FILE);
if (restoreDb) fs.copyFileSync(DB_FILE, DB_COPY);
process.on('exit', () => { if (restoreDb) fs.copyFileSync(DB_COPY, DB_FILE); });

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
    defaultViewport: { width: 1440, height: 900 },
});
const page = await browser.newPage();
const problems = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const step = (message) => console.log(`  ${message}`);
const fail = (message) => { problems.push(message); console.log(`  FAIL ${message}`); };

page.on('requestfailed', (r) => {
    if (r.url().startsWith(BASE)) problems.push(`request failed: ${r.url()} (${r.failure()?.errorText})`);
});
page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(BASE)) problems.push(`HTTP ${r.status()}: ${r.url()}`); });
page.on('console', (m) => { if (m.type() === 'error') problems.push(`console error: ${m.text()}`); });
page.on('pageerror', (e) => problems.push(`page error: ${e.message}`));

/** The preview frame, whenever Livewire has replaced it. */
async function previewFrame() {
    for (let i = 0; i < 40; i++) {
        const frame = page.frames().find((f) => f.url().includes('preview=1'));
        if (frame) {
            try {
                await frame.content();
                return frame;
            } catch {
                // The frame was replaced mid-read; take the next one.
            }
        }
        await sleep(250);
    }
    return null;
}

async function previewText() {
    // During a reload two frames can be alive for a moment: read every preview frame, not just
    // the first one found.
    const frames = page.frames().filter((f) => f.url().includes('preview=1'));
    let text = '';
    for (const frame of frames) {
        try {
            text += await frame.evaluate(() => document.body.innerText);
        } catch {
            // The frame was replaced mid-read; the next poll gets the new one.
        }
    }
    return text;
}

async function waitForPreview(needle, timeout = 20000) {
    // Case-insensitive: several sections uppercase their text with CSS, and innerText reports the
    // rendered (uppercased) words.
    const wanted = needle.toLowerCase();
    const until = Date.now() + timeout;
    while (Date.now() < until) {
        const text = await previewText();
        if (text.toLowerCase().includes(wanted)) return true;
        await sleep(400);
    }
    return false;
}

async function blockTitles() {
    return page.evaluate(() => [...document.querySelectorAll('[id^="blk-"]')].map((card) => {
        const label = card.querySelector('header .font-medium');
        return (label?.textContent ?? '').trim();
    }));
}

// ---- sign in -------------------------------------------------------------------------------------
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle0' });
await page.type('input[name="email"]', 'owner@woodex.test');
await page.type('input[name="password"]', 'Woodex-Preview-2026');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
step(`signed in: ${page.url()}`);

// ---- start a page from a template (a CMS-only URL, not one of the exported static pages) ---------
await page.goto(`${BASE}/admin/website/pages`, { waitUntil: 'networkidle0' });

const templateToggle = await page.$('button[aria-controls="template-picker"]');
if (!templateToggle) { console.log('FAIL no template picker on the pages screen'); process.exit(1); }
await templateToggle.click();
await sleep(400);

const started = await page.evaluate(() => {
    const button = [...document.querySelectorAll('#template-picker button')]
        .find((el) => el.textContent.includes('Start this page'));
    if (!button) return null;
    button.scrollIntoView({ block: 'center' });
    button.click();
    return button.closest('div')?.parentElement?.innerText?.split('\n')[0] ?? 'template';
});
if (!started) { console.log('FAIL could not start a page from a template'); process.exit(1); }

// createFromTemplate redirects to the new page's editor.
await page.waitForFunction(() => /\/admin\/website\/pages\/\d+$/.test(location.pathname), { timeout: 30000 });
await page.waitForNetworkIdle({ idleTime: 600, timeout: 30000 }).catch(() => {});
step(`started "${started}" from a template at ${page.url()}`);

const editorUrl = page.url();

// Livewire binds the fields after hydration; typing before that would never reach the server.
await page.waitForFunction(() => window.Livewire !== undefined, { timeout: 20000 }).catch(() => {});
await sleep(900);

// ---- 1. the preview is there and shows the page ---------------------------------------------------
const firstPreview = await previewFrame();
if (!firstPreview) {
    fail('no preview frame on the editor');
} else {
    const text = await previewText();
    if (text.trim().length < 50) fail(`preview looks empty (${text.length} chars)`);
    step(`preview frame is live (${text.length} chars of page)`);
}
await page.screenshot({ path: `${SHOTS}/builder-1-editor.png`, fullPage: false });

// ---- 2. typing updates the preview, without pressing Save ----------------------------------------
const marker = `Live check ${Date.now().toString().slice(-6)}`;
const field = await page.$('[id^="blk-"] textarea, [id^="blk-"] input[type="text"]');
if (!field) {
    fail('no text field in the sections');
} else {
    await field.scrollIntoView();
    await field.click();
    await page.keyboard.press('End');
    await page.keyboard.type(` ${marker}`, { delay: 25 });

    // Let Livewire's debounce take the value before the preview is asked to follow it.
    await page.waitForFunction(
        (m) => [...document.querySelectorAll('textarea, input[type="text"]')].some((el) => (el.value ?? '').includes(m)),
        { timeout: 15000 },
        marker,
    ).catch(() => {});

    const shown = await waitForPreview(marker, 30000);
    if (!shown) {
        console.log('  debug:', JSON.stringify({
            values: await page.evaluate((m) => [...document.querySelectorAll('textarea, input[type="text"]')]
                .map((el) => el.value).filter((v) => (v ?? '').includes(m)), marker),
            iframe: await page.evaluate(() => document.querySelector('iframe[title^="Draft preview"]')?.getAttribute('src')),
            frames: page.frames().map((f) => f.url()),
            snippet: (await previewText()).replace(/\s+/g, ' ').slice(0, 160),
        }));
        fail(`the preview never showed the typed text "${marker}"`);
    }
    else step('preview followed the typing and reloaded itself');

    // The draft was saved quietly: a reload keeps what was typed.
    await page.goto(editorUrl, { waitUntil: 'networkidle0' });
    const kept = await page.evaluate((m) => [...document.querySelectorAll('textarea, input[type="text"]')]
        .some((el) => (el.value ?? '').includes(m)), marker);
    if (!kept) fail('the quietly saved draft did not survive a reload');
}
await page.screenshot({ path: `${SHOTS}/builder-2-live-preview.png`, fullPage: false });

// ---- 3. drag a section with the handle ------------------------------------------------------------
const before = await blockTitles();
if (before.length < 2) {
    fail(`need two sections to drag, found ${before.length}`);
} else {
    // Bring the list into view first: the second section's handle may be below the fold and the
    // first section's top above it, which would make the drop land outside the container.
    await page.evaluate(() => document.querySelector('[wire\\:sort]')?.scrollIntoView({ block: 'start' }));
    await sleep(400);

    const handleBox = await page.evaluate(() => {
        const cards = [...document.querySelectorAll('[id^="blk-"]')];
        const handle = cards[1]?.querySelector('.section-handle');
        const first = cards[0]?.getBoundingClientRect();
        const h = handle?.getBoundingClientRect();
        if (!h || !first) return null;
        const top = Math.max(120, Math.min(first.top + 50, window.innerHeight - 80));
        return { x: h.x + h.width / 2, y: h.y + h.height / 2, targetX: first.x + 60, targetY: top };
    });

    if (!handleBox) {
        fail('no drag handle on the second section');
    } else {
        await page.mouse.move(handleBox.x, handleBox.y);
        await page.mouse.down();
        for (let i = 1; i <= 12; i++) {
            await page.mouse.move(
                handleBox.x + ((handleBox.targetX - handleBox.x) * i) / 12,
                handleBox.y + ((handleBox.targetY - handleBox.y) * i) / 12,
            );
            await sleep(30);
        }
        await page.mouse.up();
        await sleep(2000);

        const after = await blockTitles();
        if (after[0] === before[0]) fail(`drag did not reorder: ${JSON.stringify(before.slice(0, 3))} -> ${JSON.stringify(after.slice(0, 3))}`);
        else step(`drag reordered ${before[0]} / ${before[1]} -> ${after[0]} / ${after[1]}`);
    }
}
await page.screenshot({ path: `${SHOTS}/builder-3-drag.png`, fullPage: false });

// ---- 4. click a section in the preview and land on its form --------------------------------------
// The drag above reloads the preview; give the new frame time to paint its section markers.
const clickedKey = await (async () => {
    for (let i = 0; i < 40; i++) {
        const key = await page.evaluate(() => {
            const frame = document.querySelector('iframe[title^="Draft preview"]');
            if (!frame) return null;
            const inner = frame.contentDocument?.querySelector('[data-wx-key]');
            if (!inner) return null;
            inner.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            return inner.getAttribute('data-wx-key');
        });
        if (key) return key;
        await sleep(400);
    }
    return null;
})();

if (!clickedKey) {
    fail('no clickable section in the preview');
} else {
    await sleep(1200);
    const highlighted = await page.evaluate((key) => {
        const card = document.getElementById(`blk-${key}`);
        if (!card) return false;
        const style = card.className;
        const box = card.getBoundingClientRect();
        return style.includes('border-brand-500') && box.top < window.innerHeight && box.bottom > 0;
    }, clickedKey);

    if (!highlighted) fail(`clicking section ${clickedKey} in the preview did not reach its form`);
    else step(`click-to-edit jumped to section ${clickedKey}`);
}
await page.screenshot({ path: `${SHOTS}/builder-4-click-to-edit.png`, fullPage: false });

// ---- 5. a brand-new page previews before the first explicit Save ---------------------------------
await page.goto(`${BASE}/admin/website/pages/new`, { waitUntil: 'networkidle0' });
const newTitle = `Builder check ${Date.now().toString().slice(-5)}`;
await page.type('#title', newTitle, { delay: 20 });
await page.keyboard.press('Tab'); // leave the title: the debounce saves and the preview appears

const created = await (async () => {
    for (let i = 0; i < 40; i++) {
        if (/\/admin\/website\/pages\/\d+$/.test(page.url())) return true;
        await sleep(400);
    }
    return false;
})();

if (!created) {
    fail(`a new page did not save itself into the editor URL (at ${page.url()})`);
} else {
    if (!(await waitForPreview(newTitle, 25000))) {
        fail('the new page preview does not show its title');
    } else {
        step(`new page "${newTitle}" saved itself and previewed at ${page.url()}`);
    }
}

await page.screenshot({ path: `${SHOTS}/builder-5-new-page.png`, fullPage: false });

// ---- result --------------------------------------------------------------------------------------
await browser.close();
console.log(problems.length === 0
    ? 'BUILDER CHECK OK'
    : `BUILDER CHECK FAILED (${problems.length}):\n - ${problems.join('\n - ')}`);
process.exit(problems.length === 0 ? 0 : 1);
