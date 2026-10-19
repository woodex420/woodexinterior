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
    if (! r.url().startsWith(BASE)) return;
    // The preview reloads while a beacon is in flight; the cancelled sendBeacon is not a page failure.
    if (r.url().includes('/__preview-beacon') && r.failure()?.errorText === 'net::ERR_ABORTED') return;
    problems.push(`request failed: ${r.url()} (${r.failure()?.errorText})`);
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

// The library is fifty layouts now (19 Oct): the picker says so, and its type chips narrow it.
const library = await page.evaluate(() => {
    const picker = document.getElementById('template-picker');
    if (!picker) return null;

    const cards = [...picker.querySelectorAll('[x-show^="type"]')];
    const chip = [...picker.querySelectorAll('button[type="button"]')].find((el) => el.textContent.trim() === 'Service 32');

    return { label: document.querySelector('button[aria-controls="template-picker"]')?.textContent.trim(), cards: cards.length, hasChip: !!chip };
});
if (!library || library.cards !== 50) fail(`the template picker lists ${library?.cards ?? 0} layouts, not 50`);
else if (!/50 page templates/.test(library.label ?? '')) fail('the template button does not say how many layouts there are');
else if (!library.hasChip) fail('the template picker has no page-type chips');
else step(`the template picker holds ${library.cards} layouts with type chips`);

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
// The section's own settings (name, background …) sit at the top of the card now, so ask for a
// content field explicitly: typing into the section's *name* would prove nothing about the page.
// The binding attribute carries its modifiers (wire:model.live.debounce.700ms), so match on the
// attribute name rather than on a fixed spelling.
const fieldHandle = await page.evaluateHandle(() => {
    const card = document.querySelector('[id^="blk-"]');
    if (!card) return null;
    return [...card.querySelectorAll('textarea, input[type="text"]')].find((el) => {
        const binding = el.getAttributeNames().find((name) => name.startsWith('wire:model'));
        return binding !== undefined && !(el.getAttribute(binding) ?? '').includes('.settings.');
    }) ?? null;
});
const field = fieldHandle.asElement();
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
    // Put both drop points in the same screenful: the bottom of the first card (where the drop
    // lands) and the second card's handle. The cards are tall, so scrolling the list to its top is
    // not enough — the handle would be below the fold.
    await page.evaluate(() => {
        const cards = [...document.querySelectorAll('[id^="blk-"]')];
        if (!cards[0]) return;
        const bottom = cards[0].getBoundingClientRect().bottom + window.scrollY;
        window.scrollTo(0, Math.max(0, bottom - 220));
    });
    await sleep(400);
    await page.evaluate(() => {
        const cards = [...document.querySelectorAll('[id^="blk-"]')];
        const handle = cards[1]?.querySelector('.section-handle');
        if (handle && handle.getBoundingClientRect().bottom > window.innerHeight - 80) {
            handle.scrollIntoView({ block: 'center' });
        }
    });
    await sleep(400);

    const handleBox = await page.evaluate(() => {
        const cards = [...document.querySelectorAll('[id^="blk-"]')];
        const handle = cards[1]?.querySelector('.section-handle');
        const first = cards[0]?.getBoundingClientRect();
        const h = handle?.getBoundingClientRect();
        if (!h || !first) return null;
        const top = Math.max(120, Math.min(first.bottom - 50, window.innerHeight - 80));
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

// ---- 3b. undo / redo the drag from the toolbar -----------------------------------------------------
const afterDrag = await blockTitles();
const undoButton = await page.$('button[title^="Undo"]');
const redoButton = await page.$('button[title^="Redo"]');

if (!undoButton || !redoButton) {
    fail('the builder has no undo / redo buttons');
} else {
    await undoButton.click();
    await sleep(1800);
    const undone = await blockTitles();

    if (JSON.stringify(undone) !== JSON.stringify(before)) {
        fail(`undo did not restore the order: ${JSON.stringify(undone.slice(0, 3))}`);
    } else {
        await redoButton.click();
        await sleep(1800);
        const redone = await blockTitles();

        if (JSON.stringify(redone) !== JSON.stringify(afterDrag)) fail('redo did not put the change back');
        else step('undo and redo restored the section order');
    }
}

// ---- 3c. the layers panel: a row per section, and a search that filters the list --------------------
const layers = await page.evaluate(() => {
    const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Layers');
    if (!card) return null;
    return { rows: card.querySelectorAll('ul li').length, search: !!card.querySelector('input[type="search"]') };
});

if (!layers || layers.rows < 2) {
    fail('the layers panel does not list the sections');
} else {
    const filtered = await page.evaluate(() => {
        const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Layers');
        const input = card?.querySelector('input[type="search"]');
        if (!input) return null;
        const before = card.querySelectorAll('ul li').length;
        input.value = 'hero';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return before;
    });
    // The editor is heavier than it was (device controls, selection toolbar), and this runs in the
    // wasm preview: wait for the list to actually change rather than assuming a fixed round-trip.
    let after = -1;
    for (let i = 0; i < 30; i++) {
        await sleep(200);
        after = await page.evaluate(() => {
            const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Layers');
            return card ? card.querySelectorAll('ul li').length : -1;
        });

        if (after !== filtered) break;
    }

    if (filtered === null || after < 1 || after >= filtered) {
        fail(`the layers search did not narrow the list (${filtered} -> ${after})`);
    } else {
        step(`layers listed ${filtered} sections and the search narrowed them to ${after}`);
    }

    // Clear the filter so the rest of the check sees the whole page.
    await page.evaluate(() => {
        const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Layers');
        const input = card?.querySelector('input[type="search"]');
        if (!input) return;
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await sleep(800);
}

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

// ---- 4b. the page check is there, and it is talking about this page -------------------------------
const audit = await page.evaluate(() => {
    const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Search engine');
    if (!card) return null;
    return {
        title: card.textContent.includes('Page check'),
        findings: card.querySelectorAll('ul li').length,
    };
});

if (!audit || !audit.title) fail('the Search engine card has no page check');
else step(`the page check is in the editor (${audit.findings} item(s))`);

// ---- 5. the section settings reach the page (background, spacing) --------------------------------
const backgroundSet = await page.evaluate(() => {
    const card = document.querySelector('[id^="blk-"]');
    if (!card) return false;
    const label = [...card.querySelectorAll('label')].find((l) => /background/i.test(l.textContent));
    const select = label?.querySelector('select');
    if (!select) return false;
    select.value = 'beige';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
});

if (!backgroundSet) {
    fail('the first section has no background setting');
} else {
    const painted = await (async () => {
        for (let i = 0; i < 40; i++) {
            const found = await page.evaluate(() => {
                const frame = document.querySelector('iframe[title^="Draft preview"]');
                return !!frame?.contentDocument?.querySelector('.wx-sec--bg-beige');
            });
            if (found) return true;
            await sleep(400);
        }
        return false;
    })();

    if (!painted) fail('the background setting never reached the preview');
    else step('the section background reached the preview');
}
await page.screenshot({ path: `${SHOTS}/builder-5-section-settings.png`, fullPage: false });

// ---- 5b. per-device spacing reaches the preview as scoped css -------------------------------------
const mobileTab = await page.evaluate(() => {
    const tab = [...document.querySelectorAll('button')].find((b) => /Mobile/.test(b.textContent) && /640/.test(b.textContent));
    if (!tab) return false;
    tab.click();
    return true;
});

if (!mobileTab) {
    fail('the per-device spacing tabs are missing');
} else {
    const typed = await page.evaluate(() => {
        const label = [...document.querySelectorAll('label')].find((l) => /space above/i.test(l.textContent) && l.offsetParent !== null);
        const input = label?.querySelector('input[type="number"]');
        if (!input) return false;
        input.value = '24';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    });

    const scoped = typed && await (async () => {
        for (let i = 0; i < 50; i++) {
            const found = await page.evaluate(() => {
                const frame = document.querySelector('iframe[title^="Draft preview"]');
                const head = frame?.contentDocument?.head?.innerHTML ?? '';
                return /max-width:\s*640px/.test(head) && /padding-top:\s*24px/.test(head);
            });
            if (found) return true;
            await sleep(400);
        }
        return false;
    })();

    if (!typed) fail('the mobile spacing field was not found');
    else if (!scoped) fail('the per-device spacing never reached the preview as css');
    else step('per-device spacing reached the preview (mobile ≤640px)');
}

// ---- 5c. multi-select, copy and paste a section ---------------------------------------------------
const beforePaste = await page.evaluate(() => document.querySelectorAll('[id^="blk-"]').length);

const clipboard = await page.evaluate(() => {
    const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Sections');
    const tick = card?.querySelector('input[aria-label="Select this section"]');
    const button = (text) => [...(card?.querySelectorAll('button') ?? [])].find((b) => b.textContent.trim() === text);
    if (!tick) return { ok: false, why: 'no selection tick box' };
    tick.click();
    return { ok: true, copy: !!button('Copy'), paste: !!button('Paste') };
});

if (!clipboard.ok || !clipboard.copy || !clipboard.paste) {
    fail(`multi-select toolbar is incomplete (${JSON.stringify(clipboard)})`);
} else {
    await sleep(600);
    const selected = await page.evaluate(() => document.body.innerText.includes('1 selected'));

    await page.evaluate(() => {
        const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Sections');
        [...card.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Copy')?.click();
    });
    await sleep(700);
    await page.evaluate(() => {
        const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Sections');
        [...card.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Paste')?.click();
    });

    const pasted = await (async () => {
        for (let i = 0; i < 30; i++) {
            const count = await page.evaluate(() => document.querySelectorAll('[id^="blk-"]').length);
            if (count === beforePaste + 1) return true;
            await sleep(300);
        }
        return false;
    })();

    if (!selected) fail('the selection toolbar did not report the ticked section');
    else if (!pasted) fail(`paste did not add a section (${beforePaste} before)`);
    else step('one section copied and pasted — section count ' + beforePaste + ' → ' + (beforePaste + 1));

    // Undo the paste so the rest of the check starts from the template's own sections.
    await page.evaluate(() => {
        const card = [...document.querySelectorAll('section')].find((el) => el.querySelector('h2')?.textContent?.trim() === 'Sections');
        [...card.querySelectorAll('button')].find((b) => b.textContent.includes('Undo'))?.click();
    });
    await sleep(800);
}

// ---- 6. a brand-new page previews before the first explicit Save ---------------------------------
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
