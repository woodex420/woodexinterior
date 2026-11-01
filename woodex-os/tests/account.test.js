import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { startTestServer, makeClient, csrfFrom, ACCOUNTS } from './helpers.js';
import { setSetting } from '../src/db.js';

let server;
before(async () => { server = await startTestServer(); });
after(async () => { await server.close(); });

async function signedIn(role = 'owner') {
  const client = makeClient(server.base);
  const account = ACCOUNTS[role];
  const response = await client.post('/admin/login', { email: account.email, password: account.password });
  assert.equal(response.status, 302);
  return client;
}

describe('My account', () => {
  it('renders for every role and links from the shell', async () => {
    for (const role of ['owner', 'editor', 'viewer']) {
      const client = await signedIn(role);
      const response = await client.get('/admin/account');
      assert.equal(response.status, 200, `${role} should see the account screen`);
      assert.match(response.text, /Change password/);
      assert.match(response.text, new RegExp(ACCOUNTS[role].email));
      const dashboard = await client.get('/admin');
      assert.match(dashboard.text, /href="\/admin\/account"/, 'the account screen is reachable from the shell');
    }
  });

  it('saves the profile', async () => {
    const client = await signedIn('editor');
    const token = await csrfFrom(client, '/admin/account');
    const response = await client.post('/admin/account', {
      _csrf: token, name: 'Priya Studio', email: ACCOUNTS.editor.email,
    });
    assert.equal(response.status, 302);
    assert.match(server.db.get("SELECT name FROM users WHERE email = 'editor@test.local'").name, /Priya Studio/);
  });

  it('refuses an email that another account already uses', async () => {
    const client = await signedIn('editor');
    const token = await csrfFrom(client, '/admin/account');
    const response = await client.post('/admin/account', {
      _csrf: token, name: 'Priya', email: ACCOUNTS.owner.email,
    });
    assert.equal(response.status, 422);
    assert.match(response.text, /already used/i);
    assert.equal(server.db.get("SELECT email FROM users WHERE email = 'editor@test.local'").email, 'editor@test.local');
  });

  it('requires the current password and a confirmed, strong new one', async () => {
    const client = await signedIn('editor');
    const wrong = await client.post('/admin/account', {
      _csrf: await csrfFrom(client, '/admin/account'),
      name: 'Priya', email: ACCOUNTS.editor.email,
      current_password: 'not-my-password', new_password: 'NewPassword123', confirm_password: 'NewPassword123',
    });
    assert.equal(wrong.status, 422);
    assert.match(wrong.text, /current password is not correct/i);

    const mismatch = await client.post('/admin/account', {
      _csrf: await csrfFrom(client, '/admin/account'),
      name: 'Priya', email: ACCOUNTS.editor.email,
      current_password: ACCOUNTS.editor.password, new_password: 'NewPassword123', confirm_password: 'NewPassword124',
    });
    assert.equal(mismatch.status, 422);
    assert.match(mismatch.text, /do not match/i);

    const weak = await client.post('/admin/account', {
      _csrf: await csrfFrom(client, '/admin/account'),
      name: 'Priya', email: ACCOUNTS.editor.email,
      current_password: ACCOUNTS.editor.password, new_password: 'short', confirm_password: 'short',
    });
    assert.equal(weak.status, 422);
  });

  it('rotates the password, signs out other devices and keeps this session', async () => {
    const client = await signedIn('editor');
    const originalHash = server.db.get("SELECT password_hash FROM users WHERE email = 'editor@test.local'").password_hash;

    // A second session for the same account (another browser).
    const second = makeClient(server.base);
    assert.equal((await second.post('/admin/login', { email: ACCOUNTS.editor.email, password: ACCOUNTS.editor.password })).status, 302);
    assert.equal((await second.get('/admin/pages')).status, 200);

    const response = await client.post('/admin/account', {
      _csrf: await csrfFrom(client, '/admin/account'),
      name: 'Test Editor', email: ACCOUNTS.editor.email,
      current_password: ACCOUNTS.editor.password, new_password: 'Rotated2026Pass', confirm_password: 'Rotated2026Pass',
    });
    assert.equal(response.status, 302);
    assert.match(decodeURIComponent(response.location), /other devices were signed out/i);

    assert.equal((await client.get('/admin')).status, 200, 'the current device stays signed in');
    assert.equal((await second.get('/admin')).status, 302, 'the other device is signed out');

    const login = makeClient(server.base);
    assert.equal((await login.post('/admin/login', { email: ACCOUNTS.editor.email, password: 'Rotated2026Pass' })).status, 302);
    const stale = await makeClient(server.base).post('/admin/login', { email: ACCOUNTS.editor.email, password: ACCOUNTS.editor.password });
    assert.equal(stale.status, 401);

    // Put the fixture password back so the other suites can keep signing in.
    server.db.run("UPDATE users SET password_hash = ? WHERE email = 'editor@test.local'", originalHash);
  });

  it('rejects a post without a CSRF token', async () => {
    const client = await signedIn('editor');
    const response = await client.post('/admin/account', { name: 'No token' });
    assert.equal(response.status, 403);
    assert.match(response.text, /Security check failed/);
  });
});

describe('Admin error screens', () => {
  it('renders a signed-out error page instead of crashing', async () => {
    const client = makeClient(server.base);
    const response = await client.post('/admin/pages', { title: 'No session' });
    assert.equal(response.status, 401);
    assert.match(response.text, /Signed out/);
    assert.match(response.text, /WOODEX/, 'the branded shell still renders');
    assert.match(response.text, /<html lang="en">/);
  });

  it('renders the CSRF failure page with the shell', async () => {
    const client = await signedIn('owner');
    const response = await client.post('/admin/settings', { 'site.name': 'Nope' });
    assert.equal(response.status, 403);
    assert.match(response.text, /Security check failed/);
    assert.match(response.text, /<\/html>/);
  });

  it('renders the 404 screen for unknown admin URLs', async () => {
    const client = await signedIn('owner');
    const response = await client.get('/admin/does-not-exist');
    assert.equal(response.status, 404);
    assert.match(response.text, /does not exist/);
  });

  it('renders a 403 for a role that lacks the capability', async () => {
    const client = await signedIn('viewer');
    const response = await client.get('/admin/users');
    assert.equal(response.status, 403);
    assert.match(response.text, /cannot access this area/);
  });
});

describe('Static export from the admin', () => {
  it('writes to the configured export folder', async () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-export-admin-'));
    try {
      setSetting(server.db, 'system.export_dir', dir);
      const client = await signedIn('owner');
      const token = await csrfFrom(client, '/admin/health');
      const response = await client.post('/admin/export', { _csrf: token });
      assert.equal(response.status, 302);
      assert.match(decodeURIComponent(response.location), /Exported \d+ files/);
      assert.ok(fs.existsSync(path.join(dir, 'index.html')), 'index.html should be exported');
      assert.equal(server.db.get('SELECT COUNT(*) AS n FROM export_runs').n > 0, true);
    } finally {
      setSetting(server.db, 'system.export_dir', 'dist');
      fs.rmSync(dir, { recursive: true, force: true });
    }
  });

  it('is restricted to owners', async () => {
    const client = await signedIn('editor');
    const response = await client.post('/admin/export', { _csrf: await csrfFrom(client, '/admin/health') });
    assert.equal(response.status, 403);
  });
});
