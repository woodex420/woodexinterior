import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { fileURLToPath } from 'node:url';
import { Client } from '@modelcontextprotocol/client';
import { StdioClientTransport } from '@modelcontextprotocol/client/stdio';
import { readConfiguration } from '../bridge.mjs';

const token = 'wx_mcp_' + 'a'.repeat(64);
const env = { WOODEX_BASE_URL: 'https://woodex.test', WOODEX_BRIDGE_TOKEN: token };

test('requires HTTPS and an explicitly configured private key', () => {
  assert.throws(() => readConfiguration({ ...env, WOODEX_BRIDGE_TOKEN: '' }));
  assert.throws(() => readConfiguration({ ...env, WOODEX_BASE_URL: 'http://woodex.test' }));
  assert.throws(() => readConfiguration({ ...env, WOODEX_BASE_URL: 'https://user:pass@woodex.test' }));
  assert.throws(() => readConfiguration({ ...env, WOODEX_BASE_URL: 'https://woodex.test?token=secret' }));
  assert.equal(readConfiguration(env).base.href, 'https://woodex.test/');
});

test('permits loopback HTTP only with a separate development opt-in', () => {
  assert.throws(() => readConfiguration({ ...env, WOODEX_BASE_URL: 'http://127.0.0.1:8080' }));
  assert.equal(readConfiguration({ ...env, WOODEX_BASE_URL: 'http://127.0.0.1:8080', WOODEX_ALLOW_LOCAL_HTTP: '1' }).base.protocol, 'http:');
  assert.throws(() => readConfiguration({ ...env, WOODEX_BASE_URL: 'http://10.0.0.1', WOODEX_ALLOW_LOCAL_HTTP: '1' }));
});

test('negotiates actual stdio MCP, lists read-only tools, calls them, rejects excess arguments and redacts errors', async () => {
  const received = [];
  let mode = 'ok';
  const api = http.createServer((req, res) => {
    received.push({ url: req.url, method: req.method, authorization: req.headers.authorization });
    if (mode === 'redirect') { res.writeHead(302, { Location: 'http://127.0.0.1:1/leak' }); return res.end(); }
    if (mode === 'denied') { res.writeHead(403); return res.end(token); }
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify({ service: 'test-woodex', items: [{ reference: 'L-TEST' }], next_after_id: null }));
  });
  await new Promise(resolve => api.listen(0, '127.0.0.1', resolve));
  const port = api.address().port;
  const transport = new StdioClientTransport({
    command: process.execPath,
    args: [fileURLToPath(new URL('../server.mjs', import.meta.url))],
    env: { ...process.env, WOODEX_BASE_URL: `http://127.0.0.1:${port}`, WOODEX_BRIDGE_TOKEN: token, WOODEX_ALLOW_LOCAL_HTTP: '1' },
    stderr: 'pipe',
  });
  const client = new Client({ name: 'woodex-test-client', version: '1.0.0' });
  try {
    await client.connect(transport);
    const { tools } = await client.listTools();
    assert.deepEqual(tools.map(t => t.name).sort(), ['woodex_list_leads', 'woodex_list_quotes', 'woodex_status']);
    assert.ok(tools.every(t => t.annotations.readOnlyHint && !t.annotations.destructiveHint));
    let result = await client.callTool({ name: 'woodex_status', arguments: {} });
    assert.match(result.content[0].text, /test-woodex/);
    result = await client.callTool({ name: 'woodex_list_leads', arguments: { limit: 5, after_id: 7 } });
    assert.match(result.content[0].text, /L-TEST/);
    assert.match(received.at(-1).url, /limit=5&after_id=7/);
    assert.ok(received.every(r => r.method === 'GET' && r.authorization === `Bearer ${token}`));
    const before = received.length;
    assert.equal((await client.callTool({ name: 'woodex_list_leads', arguments: { limit: 5000 } })).isError, true);
    assert.equal((await client.callTool({ name: 'woodex_status', arguments: { url: 'https://attacker.test' } })).isError, true);
    assert.equal(received.length, before);
    mode = 'denied';
    result = await client.callTool({ name: 'woodex_list_quotes', arguments: {} });
    assert.equal(result.isError, true);
    assert.match(result.content[0].text, /HTTP 403/);
    assert.ok(!JSON.stringify(result).includes(token));
    mode = 'redirect';
    result = await client.callTool({ name: 'woodex_status', arguments: {} });
    assert.equal(result.isError, true);
    assert.match(result.content[0].text, /Redirects are not followed/);
  } finally {
    await client.close();
    await new Promise(resolve => api.close(resolve));
  }
});
