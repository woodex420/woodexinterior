// Read-only browser acceptance of Google's unregistered/setup-required state. Never calls Google or edits env/DB.
import fs from 'node:fs';
import { createRequire } from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;
const BASE = 'http://127.0.0.1:8080';
const SHOTS = '/tmp/woodex-run/shots';
fs.mkdirSync(SHOTS, { recursive: true });
const browser = await puppeteer.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args, '--no-sandbox'], headless: true, defaultViewport: { width: 1440, height: 1000 } });
try {
    const page = await browser.newPage(); const errors = []; const external = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('request', r => { if (new URL(r.url()).hostname.endsWith('google.com') || new URL(r.url()).hostname.endsWith('googleapis.com')) external.push(r.url()); });
    await page.goto(BASE + '/admin/login');
    if (await page.$('a[href$="/google/login"]')) throw Error('Google sign-in offered before configuration');
    await page.type('input[name="email"]', 'owner@woodex.test');
    await page.type('input[name="password"]', 'Woodex-Preview-2026');
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    await page.goto(BASE + '/admin/integrations');
    await page.waitForSelector('[wire\\:click="openGoogleSetup"]');
    await page.click('[wire\\:click="openGoogleSetup"]');
    await page.waitForSelector('[aria-labelledby="showGoogleSetup-title"]');
    for (const width of [1440, 390, 360]) {
        await page.setViewport({ width, height: 1000 });
        await page.waitForFunction(() => {
            const dialog = document.querySelector('[aria-labelledby="showGoogleSetup-title"] > div');
            const box = dialog.getBoundingClientRect();
            return document.documentElement.scrollWidth <= innerWidth + 1 && box.left >= 0 && box.right <= innerWidth + 1;
        });
        const status = await page.$eval('[aria-labelledby="showGoogleSetup-title"]', el => ({
            text: el.innerText, inputs: [...el.querySelectorAll('input')].map(x => ({ id: x.id, readOnly: x.readOnly })),
            blocked: [...el.querySelectorAll('button')].some(x => x.disabled && x.textContent.includes('Complete server setup')),
        }));
        if (!status.text.includes('Server setup required') || !status.text.includes('No Drive files') || !status.blocked || status.inputs.some(x => !x.readOnly)) throw Error('Unsafe/untruthful unregistered state');
        await page.screenshot({ path: `${SHOTS}/google-setup-${width}.png` });
    }
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('[aria-labelledby="showGoogleSetup-title"]'));
    await page.goto(BASE + '/admin/profile/security');
    await page.waitForFunction(() => document.body.innerText.includes('Google staff sign-in'));
    if (await page.$('form[action$="/google/link"]')) throw Error('Identity linking offered without configured app');
    if (!await page.evaluate(() => document.body.innerText.includes('Google setup is required on the server'))) throw Error('Missing staff setup guidance');
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth + 1);
    if (errors.length || external.length) throw Error(JSON.stringify({ errors, external }));
    console.log('GOOGLE CHECK OK: connection dialog at 1440/390/360px, explicit setup gate, callback checklist, no editable provider-secret fields, Escape, staff-linking guidance and no external auth request');
} finally {
    await browser.close();
}
