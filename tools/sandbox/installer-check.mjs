// Real-browser walk through the web installer and the post-deploy update (D-58) on a simulated Hostinger site:
//   ~/domains/interior.test/public_html  = the repository as Hostinger's Git deploy leaves it (no .env)
//   ~/woodex-os/interior.test/           = the private data folder the installer creates
// Set up the site and start a second preview server first (tools/sandbox/README.md, "Installer check"), then:
//   node tools/sandbox/installer-check.mjs [baseUrl] [dataFolder]
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', { force: true });
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;

const BASE = process.argv[2] || 'http://127.0.0.1:8081';
const DATA = process.argv[3] || '/home/user/woodex-os/interior.test';
const SITE = process.env.SITE_DIR || '/home/user/domains/interior.test/public_html';
const SHOTS = '/tmp/woodex-run/shots';
fs.mkdirSync(SHOTS, { recursive: true });

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
    defaultViewport: { width: 1280, height: 900 },
});
const page = await browser.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(`page error: ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error' && !/status of 4\d\d/.test(m.text())) problems.push(`console: ${m.text()}`); });
const step = (msg) => console.log(`- ${msg}`);
const fail = (msg) => { problems.push(msg); console.log(`  FAIL ${msg}`); };
const heading = () => page.$eval('h1', (h) => h.textContent.trim()).catch(() => '');
const shot = (name) => page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true });

// 1. A fresh deploy opens the installer from any address.
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle0' });
step(`fresh site /admin/login -> ${new URL(page.url()).pathname}: "${await heading()}"`);
if (!page.url().endsWith('/install')) fail('a site without .env should redirect to /install');
const cssOk = await page.evaluate(() => getComputedStyle(document.querySelector('header')).backgroundColor !== 'rgba(0, 0, 0, 0)');
if (!cssOk) fail('installer page has no CSS');
await shot('installer-1-form');

const codeFile = path.join(DATA, 'setup-code.txt');
const code = (fs.readFileSync(codeFile, 'utf8').match(/[A-Z2-9]{4}(?:-[A-Z2-9]{4}){5}/) || [])[0];
step(`setup code file ${codeFile}: ${code ? 'found' : 'MISSING'} (mode ${(fs.statSync(codeFile).mode & 0o777).toString(8)})`);

async function fill(values) {
    for (const [name, value] of Object.entries(values)) {
        const el = await page.$(`[name="${name}"]`);
        await el.evaluate((input) => { input.value = ''; });
        await el.type(String(value));
    }
}
// The preview server turns its own absolute URLs into "/" in HTML, which empties the prefilled address: type it.
const ORIGIN = process.env.ABSOLUTE_URL || `https://8081-${process.env.E2B_SANDBOX_ID || 'sandbox'}.e2b.app`;
const owner = { app_url: ORIGIN, owner_name: 'Imtiaz Test', owner_email: 'owner@interior.test', owner_password: 'Interior-Test-2026', owner_password_confirmation: 'Interior-Test-2026' };

// 2. A wrong code is refused and nothing is written.
await fill({ setup_code: 'AAAA-BBBB-CCCC-DDDD-EEEE-FFFF', ...owner });
await page.click('input[name="connection"][value="sqlite"]');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('main form button[type="submit"]')]);
const wrong = await page.$eval('[role="alert"]', (a) => a.textContent).catch(() => '');
step(`wrong code -> ${wrong.includes('setup code does not match') ? 'refused' : 'NOT refused'}`);
if (!wrong.includes('setup code does not match')) fail('wrong setup code accepted');
if (fs.existsSync(path.join(DATA, '.env'))) fail('.env written despite the wrong code');

// 3. The right code installs (SQLite here; MySQL on Hostinger), step 2 continues by itself.
await fill({ setup_code: code.toLowerCase(), ...owner });
await page.click('input[name="connection"][value="sqlite"]');
await page.$eval('input[name="enforce_2fa"]', (c) => { c.checked = false; });
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('main form button[type="submit"]')]);
step(`after step 1: "${await heading()}"`);
if (!/installed/i.test(await heading())) {
    await page.waitForFunction(() => /installed|stopped/i.test(document.querySelector('h1')?.textContent || ''), { timeout: 120000 }).catch(() => {});
}
step(`after step 2: "${await heading()}"`);
if (!/is installed/.test(await heading())) fail(`installer did not finish: ${await page.$eval('main', (m) => m.innerText.slice(0, 400))}`);
await shot('installer-2-done');

const env = fs.readFileSync(path.join(DATA, '.env'), 'utf8');
const envMode = (fs.statSync(path.join(DATA, '.env')).mode & 0o777).toString(8);
step(`.env in the data folder (mode ${envMode}): APP_KEY ${/^APP_KEY="?base64:/m.test(env) ? 'set' : 'MISSING'}, APP_ENV=${(env.match(/^APP_ENV=(.*)$/m) || [])[1]}, DB=${(env.match(/^DB_CONNECTION=(.*)$/m) || [])[1]}`);
for (const leftover of ['setup-code.txt', 'install-pending.json']) {
    if (fs.existsSync(path.join(DATA, leftover))) fail(`${leftover} still exists after the installation`);
}
if (fs.existsSync(path.join(SITE, 'platform/.env'))) fail('a .env was written into the site folder');
if (!fs.existsSync(path.join(DATA, 'storage/framework/views'))) fail('storage folders missing in the data folder');

// 4. The installer is closed.
const closed = await page.goto(`${BASE}/install`, { waitUntil: 'networkidle0' });
step(`/install after installation -> HTTP ${closed.status()}`);
if (closed.status() !== 404) fail('installer still reachable after installation');

// 5. Sign in as the new owner.
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle0' });
await page.type('input[name="email"]', owner.owner_email);
await page.type('input[name="password"]', owner.owner_password);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('main form button[type="submit"]')]);
step(`sign in -> ${new URL(page.url()).pathname}`);
if (!/\/admin\/?$/.test(new URL(page.url()).pathname)) fail(`sign-in should open the dashboard, got ${page.url()}`);
await shot('installer-3-dashboard');

// 6. A deploy brings a new migration: the owner is asked to apply it, then the admin works again.
const migration = path.join(SITE, 'platform/database/migrations/2099_01_01_000000_installer_check_marker.php');
fs.writeFileSync(migration, "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('installer_check_marker', function (Blueprint $table): void {\n            $table->id();\n        });\n    }\n};\n");
await page.goto(`${BASE}/admin/leads`, { waitUntil: 'networkidle0' });
step(`after a deploy with a new migration, /admin/leads -> ${new URL(page.url()).pathname}: "${await heading()}"`);
if (!page.url().includes('/admin/system/updates')) fail('pending migration did not lead to the update page');
await shot('installer-4-update');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('main form button[type="submit"]')]);
const applied = await page.$eval('main', (m) => m.innerText);
step(`apply -> ${applied.includes('Update applied') ? 'applied' : 'NOT applied'}; "${await heading()}"`);
if (!applied.includes('Update applied')) fail('update not applied');
await page.goto(`${BASE}/admin/leads`, { waitUntil: 'networkidle0' });
step(`/admin/leads afterwards -> ${new URL(page.url()).pathname}`);
if (!page.url().endsWith('/admin/leads')) fail('admin still blocked after the update');

await browser.close();
console.log(problems.length ? `\nPROBLEMS (${problems.length}):\n${problems.map((p) => `  ${p}`).join('\n')}` : '\nInstaller check passed.');
process.exit(problems.length ? 1 : 0);
