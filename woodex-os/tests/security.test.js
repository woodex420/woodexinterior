import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { startTestServer, makeClient, signIn, csrfFrom, PNG_PIXEL, ACCOUNTS } from './helpers.js';
import { sanitizeHtml } from '../src/lib/sanitize.js';
import { hashPassword, verifyPassword } from '../src/lib/auth.js';

let server;
before(async () => { server = await startTestServer(); });
after(async () => { await server.close(); });

describe('HTML sanitiser', () => {
  it('removes scripts, event handlers and javascript: URLs', () => {
    const dirty = '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(2)">x</a><img src="x" onerror="alert(3)">';
    const clean = sanitizeHtml(dirty);
    assert.doesNotMatch(clean, /<script/i);
    assert.doesNotMatch(clean, /onclick|onerror/i);
    assert.doesNotMatch(clean, /javascript:/i);
    assert.match(clean, /<p>Hi<\/p>/);
  });

  it('keeps the formatting the editor is allowed to use', () => {
    const clean = sanitizeHtml('<h2>Title</h2><p><strong>Bold</strong> <em>italic</em> <a href="/contact" title="Contact">link</a></p><ul><li>One</li></ul>');
    assert.match(clean, /<h2>Title<\/h2>/);
    assert.match(clean, /<a href="\/contact" title="Contact">link<\/a>/);
    assert.match(clean, /<ul><li>One<\/li><\/ul>/);
  });

  it('drops obfuscated schemes, iframes and style blocks', () => {
    assert.doesNotMatch(sanitizeHtml('<a href="&#106;avascript:alert(1)">x</a>'), /javascript/i);
    assert.doesNotMatch(sanitizeHtml('<iframe src="/x"></iframe>'), /iframe/);
    assert.doesNotMatch(sanitizeHtml('<style>body{display:none}</style>'), /display:none/);
    assert.doesNotMatch(sanitizeHtml('<img src="data:text/html;base64,PHNjcmlwdD4=">'), /data:text\/html/);
  });

  it('cleans stored content on the way in and escapes it on the way out', async () => {
    const { client } = await signIn(server.base, 'editor');
    const csrf = await csrfFrom(client, '/admin/projects/new');
    const created = await client.post('/admin/projects', {
      _csrf: csrf,
      title: 'XSS Project',
      slug: 'xss-project',
      summary: 'Testing sanitisation',
      body: '<p>Safe copy</p><script>alert(1)</script><img src="/assets/detail.jpg" onerror="alert(2)">',
      status: 'published',
    });
    assert.equal(created.status, 302);

    const stored = server.db.get("SELECT body FROM projects WHERE slug = 'xss-project'");
    assert.doesNotMatch(stored.body, /<script|onerror/);

    const page = await makeClient(server.base).get('/portfolio/xss-project');
    assert.doesNotMatch(page.text, /alert\(1\)|onerror/);
    assert.match(page.text, /Safe copy/);
  });

  it('escapes untrusted values in admin screens', async () => {
    const anon = makeClient(server.base);
    await anon.post('/api/v1/enquiries', {
      name: '<script>alert("name")</script>',
      email: 'xss@example.com',
      message: '<img src=x onerror=alert(1)> I would like a quote for my flat.',
    });
    const { client } = await signIn(server.base, 'owner');
    const inbox = await client.get('/admin/enquiries');
    assert.match(inbox.text, /&lt;script&gt;/);
    assert.doesNotMatch(inbox.text, /<script>alert\("name"\)<\/script>/);
  });

  it('sanitises page block HTML', async () => {
    const { client } = await signIn(server.base, 'editor');
    const csrf = await csrfFrom(client, '/admin/pages/new');
    const blocks = JSON.stringify([{ type: 'richText', html: '<p>Hello</p><script>alert(9)</script>' }]);
    await client.post('/admin/pages', {
      _csrf: csrf, title: 'Block XSS', slug: 'block-xss', blocks, status: 'published',
    });
    const stored = JSON.parse(server.db.get("SELECT blocks FROM pages WHERE slug = 'block-xss'").blocks);
    assert.doesNotMatch(JSON.stringify(stored), /<script/);
  });
});

describe('Passwords and sessions', () => {
  it('hashes passwords with scrypt and never stores the plaintext', () => {
    const hash = hashPassword('SuperSecret123');
    assert.match(hash, /^scrypt\$\d+\$\d+\$\d+\$/);
    assert.ok(!hash.includes('SuperSecret123'));
    assert.equal(verifyPassword('SuperSecret123', hash), true);
    assert.equal(verifyPassword('SuperSecret124', hash), false);
    const stored = server.db.get("SELECT password_hash FROM users WHERE email = 'owner@test.local'").password_hash;
    assert.match(stored, /^scrypt\$/);
  });

  it('marks the session cookie HttpOnly and SameSite', async () => {
    const client = makeClient(server.base);
    const response = await client.post('/api/v1/session', { email: ACCOUNTS.owner.email, password: ACCOUNTS.owner.password });
    const cookie = response.headers.get('set-cookie') || '';
    assert.match(cookie, /HttpOnly/i);
    assert.match(cookie, /SameSite=Lax/i);
    assert.match(cookie, /Path=\//);
  });

  it('does not follow an open redirect after login', async () => {
    const client = makeClient(server.base);
    const response = await client.post('/admin/login', {
      email: ACCOUNTS.owner.email,
      password: ACCOUNTS.owner.password,
      next: '//evil.example/phish',
    });
    assert.equal(response.status, 302);
    assert.equal(response.location, '/admin');
  });

  it('rejects disabled accounts and bad passwords', async () => {
    const { client, csrf } = await signIn(server.base, 'owner');
    const users = await client.get('/api/v1/admin/users');
    const editor = users.data.data.find((user) => user.email === ACCOUNTS.editor.email);
    const disable = await client.patch(`/api/v1/admin/users/${editor.id}`, { status: 'disabled' }, { 'x-csrf-token': csrf });
    assert.equal(disable.status, 200);
    const attempt = await makeClient(server.base).post('/api/v1/session', {
      email: ACCOUNTS.editor.email, password: ACCOUNTS.editor.password,
    });
    assert.equal(attempt.status, 401);
    await client.patch(`/api/v1/admin/users/${editor.id}`, { status: 'active' }, { 'x-csrf-token': csrf });
  });

  it('requires a CSRF token for state-changing requests', async () => {
    const { client } = await signIn(server.base, 'owner');
    const noToken = await client.post('/api/v1/admin/pages', { title: 'No token', slug: 'no-token' });
    assert.equal(noToken.status, 403);
    assert.equal(noToken.data.error.code, 'csrf_failed');

    const adminForm = await client.post('/admin/settings', { 'site.name': 'Hijacked' });
    assert.equal(adminForm.status, 403);
    assert.match(adminForm.text, /Security check failed/);
  });

  it('rejects an unknown session token', async () => {
    const client = makeClient(server.base);
    client.jar.set('woodex_sid', 'not-a-real-session');
    const response = await client.get('/admin');
    assert.equal(response.status, 302);
    assert.match(response.location, /\/admin\/login/);
  });
});

describe('Rate limiting', () => {
  it('throttles failed logins', async () => {
    const limited = await startTestServer({ loginMaxAttempts: 3 });
    try {
      const client = makeClient(limited.base);
      let response;
      for (let attempt = 0; attempt < 5; attempt += 1) {
        response = await client.post('/api/v1/session', { email: ACCOUNTS.viewer.email, password: 'wrong' });
      }
      assert.equal(response.status, 429);
      assert.ok(Number(response.headers.get('retry-after')) > 0);
      assert.equal(response.data.error.code, 'rate_limited');
    } finally {
      await limited.close();
    }
  });

  it('throttles enquiry spam', async () => {
    const limited = await startTestServer({ enquiryMaxPerWindow: 2 });
    try {
      const client = makeClient(limited.base);
      const payload = { name: 'Spam', email: 'spam@example.com', message: 'A message that is long enough to pass validation.' };
      assert.equal((await client.post('/api/v1/enquiries', payload)).status, 201);
      assert.equal((await client.post('/api/v1/enquiries', payload)).status, 201);
      const blocked = await client.post('/api/v1/enquiries', payload);
      assert.equal(blocked.status, 429);
    } finally {
      await limited.close();
    }
  });
});

describe('Media uploads', () => {
  it('accepts an image, randomises the filename and keeps the alt text', async () => {
    const { client, csrf } = await signIn(server.base, 'owner');
    const form = new FormData();
    form.set('_csrf', csrf);
    form.set('alt', 'A single pixel');
    form.set('file', new Blob([PNG_PIXEL], { type: 'image/png' }), '../../evil name.png');
    const response = await client.raw('POST', '/api/v1/admin/media', { body: form });
    assert.equal(response.status, 201, response.text);
    const media = response.data.data;
    assert.match(media.filename, /^[a-z0-9-]+\.png$/i);
    assert.doesNotMatch(media.original_name, /\.\.\//);
    assert.equal(media.alt, 'A single pixel');
    assert.ok(fs.existsSync(path.join(server.uploadDir, media.filename)));
  });

  it('rejects non-image uploads', async () => {
    const { client, csrf } = await signIn(server.base, 'owner');
    const form = new FormData();
    form.set('_csrf', csrf);
    form.set('file', new Blob(['#!/bin/sh\necho hi'], { type: 'text/x-shellscript' }), 'run.sh');
    const response = await client.raw('POST', '/api/v1/admin/media', { body: form });
    assert.equal(response.status, 422);
    assert.equal(response.data.error.code, 'validation_failed');
  });

  it('enforces the configured upload size limit', async () => {
    const small = await startTestServer({ maxUploadBytes: 512 });
    try {
      const { client, csrf } = await signIn(small.base, 'owner');
      const form = new FormData();
      form.set('_csrf', csrf);
      form.set('file', new Blob([Buffer.concat([PNG_PIXEL, Buffer.alloc(2048, 1)])], { type: 'image/png' }), 'big.png');
      const response = await client.raw('POST', '/api/v1/admin/media', { body: form });
      assert.equal(response.status, 413);
      assert.match(response.data.error.message, /too large/i);
    } finally {
      await small.close();
    }
  });

  it('deletes the stored file with its record', async () => {
    const { client, csrf } = await signIn(server.base, 'owner');
    const list = await client.get('/api/v1/admin/media');
    const media = list.data.data.find((item) => item.alt === 'A single pixel');
    const filePath = path.join(server.uploadDir, media.filename);
    assert.ok(fs.existsSync(filePath));
    const response = await client.delete(`/api/v1/admin/media/${media.id}`, { 'x-csrf-token': csrf });
    assert.equal(response.status, 200);
    assert.equal(fs.existsSync(filePath), false);
  });
});
