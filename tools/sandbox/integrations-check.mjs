// Local preview acceptance: hub controls + an actual stdio MCP client calling Laravel's bridge.
// Uses demo credentials only; never logs the generated key. Restores the local preview DB afterward.
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require = createRequire('/tmp/browser/');
const chromium = (await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer = (await import(require.resolve('puppeteer-core'))).default;
const sdk = createRequire(new URL('../mcp-bridge/package.json', import.meta.url));
const { Client } = await import(sdk.resolve('@modelcontextprotocol/client'));
const { StdioClientTransport } = await import(sdk.resolve('@modelcontextprotocol/client/stdio'));
const base = 'http://127.0.0.1:8080';
const db = '/tmp/woodex-run/app/database/database.sqlite';
const originalDb = fs.readFileSync(db);
const browser = await puppeteer.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args, '--no-sandbox'], headless: true, defaultViewport: { width: 1440, height: 1000 } });
let client;
try {
  const page = await browser.newPage(); const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('dialog', dialog => dialog.accept());
  await page.goto(base + '/admin/login');
  await page.type('input[name="email"]', 'owner@woodex.test');
  await page.type('input[name="password"]', 'Woodex-Preview-2026');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
  await page.goto(base + '/admin/integrations');
  await page.waitForSelector('#app-search');
  await page.screenshot({ path: '/tmp/woodex-run/shots/integrations-desktop.png', fullPage: true });
  await page.type('#app-search', 'Notion');
  await page.waitForFunction(() => document.body.innerText.includes('View requirements') && !document.body.innerText.includes('Google Drive'));
  await page.click('[wire\\:click="openIntegrationDetails(\'notion\')"]');
  await page.waitForFunction(() => document.body.innerText.includes('explicitly shared pages'));
  await page.click('[aria-labelledby="showIntegrationDetails-title"] button[aria-label="Close"]');
  await page.waitForFunction(() => !document.querySelector('[aria-labelledby="showIntegrationDetails-title"]'));
  await page.$eval('#app-search', el => { el.value = ''; el.dispatchEvent(new Event('input', { bubbles: true })); });
  await page.waitForFunction(() => document.body.innerText.includes('Google Drive'));
  await page.click('[wire\\:click="setTelegramWebhook"]');
  await page.waitForFunction(() => document.body.innerText.includes('Active settings file:'));
  for (const width of [1440, 390, 360]) {
    await page.setViewport({ width, height: 1000 });
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth + 1, { timeout: 5000 });
  }
  await page.screenshot({ path: '/tmp/woodex-run/shots/integrations-mobile.png' });
  await page.setViewport({ width: 1440, height: 1000 });
  await page.click('[wire\\:click="openMcpSetup"]');
  await page.waitForSelector('#bridgeName');
  await page.type('#bridgeName', 'Local acceptance test');
  await page.type('#bridgePassword', 'Woodex-Preview-2026');
  await page.click('input[value="leads.read"]');
  await page.click('input[value="quotes.read"]');
  await page.click('form[wire\\:submit="createBridgeKey"] button[type="submit"]');
  await page.waitForSelector('[data-bridge-key]');
  const token = await page.$eval('[data-bridge-key]', el => el.textContent.trim());
  client = new Client({ name: 'woodex-laravel-acceptance', version: '1.0.0' });
  const transport = new StdioClientTransport({ command: process.execPath, args: [fileURLToPath(new URL('../mcp-bridge/server.mjs', import.meta.url))], env: { ...process.env, WOODEX_BASE_URL: base, WOODEX_BRIDGE_TOKEN: token, WOODEX_ALLOW_LOCAL_HTTP: '1' }, stderr: 'pipe' });
  await client.connect(transport);
  const tools = await client.listTools();
  if (tools.tools.length !== 3) throw Error('Unexpected tool count');
  for (const name of ['woodex_status', 'woodex_list_leads', 'woodex_list_quotes']) {
    const result = await client.callTool({ name, arguments: name === 'woodex_status' ? {} : { limit: 1 } });
    if (result.isError) throw Error('Laravel MCP read failed: ' + name);
  }
  for (const width of [1440, 390, 360]) {
    await page.setViewport({ width, height: 1000 });
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth + 1, { timeout: 5000 });
  }
  await page.click('[wire\\:click^="revokeBridgeKey"]');
  await page.waitForFunction(() => !document.querySelector('[data-bridge-key]') && document.body.innerText.includes('Revoked'));
  const revoked = await client.callTool({ name: 'woodex_status', arguments: {} });
  if (!revoked.isError || !revoked.content[0].text.includes('HTTP 401')) throw Error('Revoked key still works');
  if (errors.length) throw Error(errors.join('; '));
  console.log('INTEGRATIONS CHECK OK: search, roadmap modal, Telegram diagnostics, desktop/mobile, owner key creation, real MCP initialize/list/call against Laravel, and immediate revocation');
} finally {
  if (client) await client.close();
  await browser.close();
  fs.writeFileSync(db, originalDb);
}
