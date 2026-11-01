import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { startTestServer, makeClient } from './helpers.js';

let server;
before(async () => { server = await startTestServer(); });
after(async () => { await server.close(); });

/** Sign in through the JSON API and return { client, csrf }. */
async function signIn(role = 'owner') {
  const client = makeClient(server.base);
  const account = server.accounts[role];
  const response = await client.login(account.email, account.password);
  assert.equal(response.status, 200, `login should succeed for ${role}`);
  return { client, csrf: response.json.data.csrf };
}

describe('Public API', () => {
  it('exposes only published pages', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/api/v1/pages');
    assert.equal(response.status, 200);
    const slugs = response.json.data.map((page) => page.slug);
    assert.ok(slugs.includes('about'));
    assert.ok(!slugs.includes('draft-page'), 'draft pages must not be public');
  });

  it('returns 404 for a draft page by slug', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/api/v1/pages/draft-page');
    assert.equal(response.status, 404);
    assert.equal(response.json.error.code, 'not_found');
  });

  it('paginates projects with meta', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/api/v1/projects?per_page=1&page=1');
    assert.equal(response.status, 200);
    assert.equal(response.json.data.length, 1);
    assert.equal(response.json.meta.per_page, 1);
    assert.ok(response.json.meta.total >= 1);
    assert.ok(!response.json.data.some((p) => p.slug === 'draft-project'));
  });

  it('filters projects by category', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/api/v1/projects?category=commercial');
    assert.equal(response.status, 200);
    assert.ok(response.json.data.every((project) => project.category === 'commercial'));
  });

  it('serves settings, navigation and sitemap', async () => {
    const client = makeClient(server.base);
    const settings = await client.get('/api/v1/settings');
    assert.equal(settings.status, 200);
    assert.ok(settings.json.data.contact.email.includes('@'));
    const sitemap = await client.get('/api/v1/sitemap');
    assert.equal(sitemap.status, 200);
    assert.ok(sitemap.json.data.some((entry) => entry.loc === '/about'));
  });

  it('returns a JSON 404 envelope for unknown endpoints', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/api/v1/nope');
    assert.equal(response.status, 404);
    assert.equal(response.json.error.code, 'not_found');
  });
});

describe('Authentication', () => {
  it('rejects wrong credentials with 401 and a generic message', async () => {
    const client = makeClient(server.base);
    const response = await client.login('owner@test.local', 'wrong-password');
    assert.equal(response.status, 401);
    assert.equal(response.json.error.code, 'invalid_credentials');
    assert.ok(!/user not found|no such user/i.test(response.json.error.message));
  });

  it('rejects unknown emails the same way as wrong passwords', async () => {
    const client = makeClient(server.base);
    const response = await client.login('ghost@test.local', 'whatever');
    assert.equal(response.status, 401);
    assert.equal(response.json.error.code, 'invalid_credentials');
  });

  it('creates a session, sets an HttpOnly cookie and returns capabilities', async () => {
    const client = makeClient(server.base);
    const response = await client.login(server.accounts.owner.email, server.accounts.owner.password);
    assert.equal(response.status, 200);
    const setCookie = response.headers.get('set-cookie') || '';
    assert.match(setCookie, /HttpOnly/);
    assert.match(setCookie, /SameSite=Lax/);
    assert.ok(response.json.data.csrf.length >= 32);
    assert.ok(response.json.data.capabilities.includes('content:write'));

    const session = await client.get('/api/v1/session');
    assert.equal(session.status, 200);
    const signOut = await client.jsonRequest('DELETE', '/api/v1/session', undefined, { 'x-csrf-token': response.json.data.csrf });
    assert.equal(signOut.status, 200);
    assert.equal((await client.get('/api/v1/session')).status, 401);
  });

  it('requires CSRF for cookie-authenticated writes', async () => {
    const { client } = await signIn('owner');
    const noToken = await client.jsonRequest('POST', '/api/v1/admin/pages', { title: 'No CSRF', slug: 'no-csrf' });
    assert.equal(noToken.status, 403);
    assert.equal(noToken.json.error.code, 'csrf_failed');
  });

});

describe('Pages CRUD', () => {
  let client;
  let csrf;
  before(async () => { ({ client, csrf } = await signIn('owner')); });

  it('creates a page and derives the slug from the title', async () => {
    const response = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'Design Notes & Ideas',
      status: 'draft',
      seo_description: 'A page about design notes.',
      blocks: [{ type: 'richText', html: '<p>Hello</p>' }],
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 201, JSON.stringify(response.json));
    assert.equal(response.json.data.slug, 'design-notes-and-ideas');
    assert.equal(response.json.data.status, 'draft');
    assert.equal(response.json.data.blocks.length, 1);
  });

  it('rejects a duplicate slug with 409', async () => {
    const response = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'About duplicate', slug: 'about',
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 409);
    assert.equal(response.json.error.code, 'conflict');
  });

  it('validates input with field-level errors', async () => {
    const response = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: '', slug: 'Bad Slug!', status: 'archived',
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 422);
    assert.equal(response.json.error.code, 'validation_failed');
    assert.ok(response.json.error.details.title);
    assert.ok(response.json.error.details.slug);
    assert.ok(response.json.error.details.status);
  });

  it('updates a page, records a revision and restores it', async () => {
    const created = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'Revision target', slug: 'revision-target', seo_description: 'First version.',
    }, { 'x-csrf-token': csrf });
    const id = created.json.data.id;

    const updated = await client.jsonRequest('PUT', `/api/v1/admin/pages/${id}`, {
      title: 'Revision target', slug: 'revision-target', seo_description: 'Second version.',
      status: 'published', revisionNote: 'second pass',
    }, { 'x-csrf-token': csrf });
    assert.equal(updated.status, 200);
    assert.equal(updated.json.data.seo_description, 'Second version.');
    assert.equal(updated.json.data.status, 'published');
    assert.ok(updated.json.data.published_at);

    const revisions = await client.get(`/api/v1/admin/pages/${id}/revisions`);
    assert.ok(revisions.json.data.length >= 1);
    const restored = await client.jsonRequest(
      'POST', `/api/v1/admin/pages/${id}/revisions/${revisions.json.data[0].id}/restore`, undefined,
      { 'x-csrf-token': csrf },
    );
    assert.equal(restored.status, 200);
    assert.equal(restored.json.data.seo_description, 'First version.');
  });

  it('publishes and unpublishes through the admin UI endpoint', async () => {
    const created = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'Toggle page', slug: 'toggle-page', status: 'draft',
    }, { 'x-csrf-token': csrf });
    const id = created.json.data.id;
    const token = await (await client.get(`/admin/pages/${id}`)).text;
    const formToken = /name="_csrf" value="([a-f0-9]+)"/.exec(token)[1];
    const publish = await client.post(`/admin/pages/${id}/publish`, { _csrf: formToken });
    assert.equal(publish.status, 302);
    const page = await client.get(`/api/v1/admin/pages/${id}`);
    assert.equal(page.json.data.status, 'published');
  });

  it('refuses to delete a system page', async () => {
    const created = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'System page', slug: 'system-page', page_type: 'system',
    }, { 'x-csrf-token': csrf });
    const response = await client.jsonRequest('DELETE', `/api/v1/admin/pages/${created.json.data.id}`, undefined, { 'x-csrf-token': csrf });
    assert.equal(response.status, 409);
  });
});

describe('Role based access control', () => {
  it('blocks a viewer from writing content', async () => {
    const { client, csrf } = await signIn('viewer');
    const response = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'Viewer page', slug: 'viewer-page',
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 403);
    assert.equal(response.json.error.code, 'forbidden');
  });

  it('lets an editor write content but not manage users or settings', async () => {
    const { client, csrf } = await signIn('editor');
    const page = await client.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'Editor page', slug: 'editor-page',
    }, { 'x-csrf-token': csrf });
    assert.equal(page.status, 201);

    const users = await client.get('/api/v1/admin/users');
    assert.equal(users.status, 403);
    const settings = await client.jsonRequest('PUT', '/api/v1/settings', { settings: { 'site.name': 'Hacked' } }, { 'x-csrf-token': csrf });
    assert.equal(settings.status, 403);
    const tokens = await client.get('/api/v1/admin/tokens');
    assert.equal(tokens.status, 403);
  });

  it('protects the last owner account', async () => {
    const { client, csrf } = await signIn('owner');
    const users = await client.get('/api/v1/admin/users');
    const owner = users.json.data.find((user) => user.role === 'owner');
    const demote = await client.jsonRequest('PATCH', `/api/v1/admin/users/${owner.id}`, { role: 'editor' }, { 'x-csrf-token': csrf });
    assert.equal(demote.status, 409);
    const remove = await client.jsonRequest('DELETE', `/api/v1/admin/users/${owner.id}`, undefined, { 'x-csrf-token': csrf });
    assert.equal(remove.status, 409);
  });

  it('validates new user passwords', async () => {
    const { client, csrf } = await signIn('owner');
    const weak = await client.jsonRequest('POST', '/api/v1/admin/users', {
      email: 'weak@test.local', name: 'Weak', password: 'short',
    }, { 'x-csrf-token': csrf });
    assert.equal(weak.status, 422);
    assert.ok(weak.json.error.details.password);
    const good = await client.jsonRequest('POST', '/api/v1/admin/users', {
      email: 'new-editor@test.local', name: 'New Editor', password: 'StrongPass2026', role: 'editor',
    }, { 'x-csrf-token': csrf });
    assert.equal(good.status, 201);
    assert.equal(good.json.data.role, 'editor');
    assert.equal(good.json.data.password_hash, undefined);
  });
});

describe('API tokens', () => {
  it('creates a scoped token that can read but not write', async () => {
    const { client, csrf } = await signIn('owner');
    const created = await client.jsonRequest('POST', '/api/v1/admin/tokens', {
      label: 'QA script', scopes: ['content:read'],
    }, { 'x-csrf-token': csrf });
    assert.equal(created.status, 201);
    const token = created.json.data.token;
    assert.match(token, /^wox_[a-f0-9]{48}$/);

    const read = await fetch(`${server.base}/api/v1/admin/pages`, { headers: { authorization: `Bearer ${token}` } });
    assert.equal(read.status, 200);

    const write = await fetch(`${server.base}/api/v1/admin/pages`, {
      method: 'POST',
      headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
      body: JSON.stringify({ title: 'Token write', slug: 'token-write' }),
    });
    assert.equal(write.status, 403);

    const revoke = await client.jsonRequest('DELETE', `/api/v1/admin/tokens/${created.json.data.id}`, undefined, { 'x-csrf-token': csrf });
    assert.equal(revoke.status, 200);
    const after = await fetch(`${server.base}/api/v1/admin/pages`, { headers: { authorization: `Bearer ${token}` } });
    assert.equal(after.status, 401);
  });

  it('rejects invalid bearer tokens', async () => {
    const response = await fetch(`${server.base}/api/v1/admin/pages`, { headers: { authorization: 'Bearer wox_not-a-real-token' } });
    assert.equal(response.status, 401);
  });
});

describe('Collections', () => {
  it('creates, updates and deletes a project', async () => {
    const { client, csrf } = await signIn('editor');
    const created = await client.jsonRequest('POST', '/api/v1/admin/projects', {
      title: 'Gallery Project', category: 'bespoke', status: 'published',
      gallery: [{ src: '/assets/detail.jpg', alt: 'Alt text' }],
      facts: [{ label: 'Scope', value: 'Kitchen' }],
    }, { 'x-csrf-token': csrf });
    assert.equal(created.status, 201);
    assert.equal(created.json.data.gallery.length, 1);

    const updated = await client.jsonRequest('PUT', `/api/v1/admin/projects/${created.json.data.id}`, {
      title: 'Gallery Project renamed', category: 'bespoke', status: 'published',
    }, { 'x-csrf-token': csrf });
    assert.equal(updated.status, 200);
    assert.equal(updated.json.data.title, 'Gallery Project renamed');

    const removed = await client.jsonRequest('DELETE', `/api/v1/admin/projects/${created.json.data.id}`, undefined, { 'x-csrf-token': csrf });
    assert.equal(removed.status, 200);
    assert.equal((await client.get(`/api/v1/projects/gallery-project`)).status, 404);
  });

  it('validates project categories', async () => {
    const { client, csrf } = await signIn('editor');
    const response = await client.jsonRequest('POST', '/api/v1/admin/projects', {
      title: 'Bad category', category: 'spaceship',
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 422);
    assert.ok(response.json.error.details.category);
  });

  it('creates an insight with a reading time default', async () => {
    const { client, csrf } = await signIn('editor');
    const response = await client.jsonRequest('POST', '/api/v1/admin/insights', {
      title: 'A new insight', category: 'Lighting', body: '<p>Words</p>', status: 'published',
    }, { 'x-csrf-token': csrf });
    assert.equal(response.status, 201);
    assert.equal(response.json.data.reading_time, '4 min read');
    assert.ok(response.json.data.excerpt.length > 0);
  });
});

describe('Enquiries', () => {
  it('accepts a public enquiry and hides it from unauthenticated clients', async () => {
    const client = makeClient(server.base);
    const created = await client.jsonRequest('POST', '/api/v1/enquiries', {
      name: 'Ayesha Khan', email: 'ayesha@example.com', message: 'We are renovating a 3-bedroom home in DHA.',
      service: 'residential', phone: '+92 300 1234567',
    });
    assert.equal(created.status, 201);
    assert.equal(created.json.data.received, true);

    const list = await client.get('/api/v1/admin/enquiries');
    assert.equal(list.status, 401);
  });

  it('validates enquiries and silently drops honeypot spam', async () => {
    const client = makeClient(server.base);
    const bad = await client.jsonRequest('POST', '/api/v1/enquiries', { name: 'A', email: 'nope', message: 'short' });
    assert.equal(bad.status, 422);
    assert.ok(bad.json.error.details.email);
    assert.ok(bad.json.error.details.message);

    const spam = await client.jsonRequest('POST', '/api/v1/enquiries', {
      name: 'Bot', email: 'bot@example.com', message: 'Buy cheap things at example dot com', company_website: 'http://spam',
    });
    assert.equal(spam.status, 202);
    assert.equal(spam.json.data.received, true);

    const { client: admin, csrf } = await signIn('owner');
    const list = await admin.get('/api/v1/admin/enquiries');
    assert.ok(!list.json.data.some((enquiry) => enquiry.email === 'bot@example.com'));
    assert.ok(list.json.data.some((enquiry) => enquiry.email === 'ayesha@example.com'));
    assert.equal(csrf.length >= 32, true);
  });

  it('updates status and notes, and rejects invalid statuses', async () => {
    const { client, csrf } = await signIn('owner');
    const list = await client.get('/api/v1/admin/enquiries');
    const enquiry = list.json.data.find((item) => item.email === 'ayesha@example.com');
    const patched = await client.jsonRequest('PATCH', `/api/v1/admin/enquiries/${enquiry.id}`, {
      status: 'quoted', notes: 'Site visit booked for Saturday.',
    }, { 'x-csrf-token': csrf });
    assert.equal(patched.status, 200);
    assert.equal(patched.json.data.status, 'quoted');
    assert.match(patched.json.data.notes, /Site visit/);

    const bad = await client.jsonRequest('PATCH', `/api/v1/admin/enquiries/${enquiry.id}`, { status: 'hired' }, { 'x-csrf-token': csrf });
    assert.equal(bad.status, 422);
  });

  it('exports enquiries as CSV', async () => {
    const { client } = await signIn('owner');
    const response = await client.get('/api/v1/admin/enquiries/export.csv');
    assert.equal(response.status, 200);
    assert.match(response.headers.get('content-type'), /text\/csv/);
    assert.match(response.text.split('\r\n')[0], /ID,Received,Name,Email/);
    assert.match(response.text, /ayesha@example\.com/);
  });
});

describe('Settings and health', () => {
  it('updates settings for owners and rejects unknown keys', async () => {
    const { client, csrf } = await signIn('owner');
    const ok = await client.jsonRequest('PUT', '/api/v1/settings', {
      settings: { 'contact.phone': '+92 300 0000000' },
    }, { 'x-csrf-token': csrf });
    assert.equal(ok.status, 200);
    const unknown = await client.jsonRequest('PUT', '/api/v1/settings', {
      settings: { 'not.a.setting': 'x' },
    }, { 'x-csrf-token': csrf });
    assert.equal(unknown.status, 422);
  });

  it('reports dashboard statistics and content health', async () => {
    const { client } = await signIn('owner');
    const dashboard = await client.get('/api/v1/admin/dashboard');
    assert.equal(dashboard.status, 200);
    assert.ok(dashboard.json.data.stats.pages >= 5);
    assert.equal(typeof dashboard.json.data.stats.newEnquiries, 'number');
    assert.ok(Array.isArray(dashboard.json.data.health));

    const health = await client.get('/api/v1/admin/health');
    assert.equal(health.status, 200);
    assert.ok(health.json.data.checks.some((check) => check.id === 'database'));
  });
});
