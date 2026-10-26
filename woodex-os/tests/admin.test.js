import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { startTestServer, makeClient, csrfFrom, PNG_PIXEL } from './helpers.js';

let server;
before(async () => { server = await startTestServer(); });
after(async () => { await server.close(); });

async function signedInAdmin(role = 'owner') {
  const client = makeClient(server.base);
  const account = server.accounts[role];
  const response = await client.post('/admin/login', { email: account.email, password: account.password, next: '/admin' });
  assert.equal(response.status, 302);
  assert.equal(response.location, '/admin');
  return client;
}

describe('Admin authentication', () => {
  it('shows the sign-in screen to anonymous visitors', async () => {
    const client = makeClient(server.base);
    const response = await client.get('/admin/login');
    assert.equal(response.status, 200);
    assert.match(response.text, /Sign in/);
    assert.match(response.text, /name="password"/);
  });

  it('reports a friendly error for a bad password', async () => {
    const client = makeClient(server.base);
    const response = await client.post('/admin/login', { email: server.accounts.owner.email, password: 'nope' });
    assert.equal(response.status, 401);
    assert.match(response.text, /Email or password is incorrect/);
  });

  it('redirects anonymous admin requests to the login screen', async () => {
    const client = makeClient(server.base);
    for (const route of ['/admin', '/admin/pages', '/admin/settings', '/admin/enquiries']) {
      const response = await client.get(route);
      assert.equal(response.status, 302, route);
      assert.match(response.location, /\/admin\/login\?next=/, route);
    }
  });
});

describe('Admin screens', () => {
  let admin;
  before(async () => { admin = await signedInAdmin(); });

  it('renders every main screen for an owner', async () => {
    const routes = ['/admin', '/admin/pages', '/admin/pages/new', '/admin/projects', '/admin/projects/new',
      '/admin/insights', '/admin/insights/new', '/admin/services', '/admin/services/new',
      '/admin/media', '/admin/enquiries', '/admin/navigation', '/admin/settings', '/admin/users',
      '/admin/tokens', '/admin/activity', '/admin/health'];
    for (const route of routes) {
      const response = await admin.get(route);
      assert.equal(response.status, 200, `${route} should render`);
      assert.match(response.text, /Woodex OS/, route);
    }
  });

  it('shows the Website and Content groups in the navigation', async () => {
    const response = await admin.get('/admin');
    assert.match(response.text, />Website</);
    assert.match(response.text, />Content</);
    assert.match(response.text, />System</);
  });

  it('creates a page from the block editor form', async () => {
    const token = await csrfFrom(admin, '/admin/pages/new');
    const blocks = JSON.stringify([
      { type: 'pageHero', eyebrow: 'New', title: 'A brand new page', intro: 'Intro text' },
      { type: 'richText', html: '<p>Body copy</p>' },
    ]);
    const response = await admin.post('/admin/pages', {
      _csrf: token,
      title: 'Admin Created Page',
      slug: 'admin-created-page',
      template: 'default',
      page_type: 'page',
      status: 'published',
      sort_order: '5',
      seo_title: 'Admin created page',
      seo_description: 'A page created through the admin form in the QA suite.',
      blocks,
    });
    assert.equal(response.status, 302);
    assert.match(response.location, /\/admin\/pages\/\d+\?ok=Page(%20|\+)saved/);

    const publicPage = await makeClient(server.base).get('/admin-created-page');
    assert.equal(publicPage.status, 200);
    assert.match(publicPage.text, /A brand new page/);
    assert.match(publicPage.text, /Body copy/);
  });

  it('re-renders the form with errors and keeps what was typed', async () => {
    const token = await csrfFrom(admin, '/admin/pages/new');
    const response = await admin.post('/admin/pages', {
      _csrf: token, title: '', slug: 'Bad Slug', blocks: '[]',
    });
    assert.equal(response.status, 422);
    assert.match(response.text, /Please check the highlighted fields/);
    assert.match(response.text, /value="Bad Slug"/);
  });

  it('toggles publish state and restores a revision', async () => {
    const page = server.db.get("SELECT * FROM pages WHERE slug = 'admin-created-page'");
    const token = await csrfFrom(admin, `/admin/pages/${page.id}`);
    const unpublish = await admin.post(`/admin/pages/${page.id}/publish`, { _csrf: token });
    assert.equal(unpublish.status, 302);
    assert.equal(server.db.get('SELECT status FROM pages WHERE id = ?', page.id).status, 'draft');
    const publish = await admin.post(`/admin/pages/${page.id}/publish`, { _csrf: token });
    assert.equal(publish.status, 302);
    assert.equal(server.db.get('SELECT status FROM pages WHERE id = ?', page.id).status, 'published');

    const revision = server.db.get('SELECT id FROM page_revisions WHERE page_id = ? ORDER BY id DESC', page.id);
    assert.ok(revision, 'a revision should exist');
    const restore = await admin.post(`/admin/pages/${page.id}/revisions/${revision.id}/restore`, { _csrf: token });
    assert.equal(restore.status, 302);
  });

  it('saves navigation and settings', async () => {
    const navToken = await csrfFrom(admin, '/admin/navigation');
    const navItems = server.db.all("SELECT * FROM nav_items WHERE area='main'");
    const payload = { _csrf: navToken };
    navItems.forEach((item) => {
      payload[`label_${item.id}`] = item.label;
      payload[`href_${item.id}`] = item.href;
      payload[`sort_${item.id}`] = String(item.sort_order);
      payload[`visible_${item.id}`] = '1';
    });
    const nav = await admin.post('/admin/navigation', payload);
    assert.equal(nav.status, 302);

    const addToken = await csrfFrom(admin, '/admin/navigation');
    const added = await admin.post('/admin/navigation/add', { _csrf: addToken, area: 'main', label: 'Journal', href: '/insights' });
    assert.equal(added.status, 302);
    assert.ok(server.db.get("SELECT id FROM nav_items WHERE label = 'Journal'"));

    const settingsToken = await csrfFrom(admin, '/admin/settings');
    const saved = await admin.post('/admin/settings', {
      _csrf: settingsToken,
      'site.name': 'Woodex Interior',
      'contact.hours': 'Mon–Sat, 9:00 AM – 6:00 PM',
      'seo.default_og_image': '/assets/hero.jpg',
    });
    assert.equal(saved.status, 302);
    assert.match(server.db.get("SELECT value FROM settings WHERE key='contact.hours'").value, /9:00/);
  });

  it('uploads media through the admin form', async () => {
    const token = await csrfFrom(admin, '/admin/media');
    const form = new FormData();
    form.set('_csrf', token);
    form.set('alt', 'Studio detail');
    form.set('file', new Blob([PNG_PIXEL], { type: 'image/png' }), 'studio.png');
    const response = await admin.raw('POST', '/admin/media', { body: form });
    assert.equal(response.status, 302);
    assert.match(response.location, /uploaded=/);
    const media = server.db.get("SELECT * FROM media WHERE alt = 'Studio detail'");
    assert.ok(media);
    assert.ok(fs.existsSync(path.join(server.uploadDir, media.filename)));
  });

  it('handles the enquiry inbox end to end', async () => {
    const created = await makeClient(server.base).jsonRequest('POST', '/api/v1/enquiries', {
      name: 'Sara Malik', email: 'sara@example.com', phone: '+92 300 5556677',
      location: 'Gulberg', service: 'commercial', message: 'We need a new office interior for 20 people.',
    });
    assert.equal(created.status, 201);
    const id = created.json.data.id;

    const list = await admin.get('/admin/enquiries');
    assert.match(list.text, /Sara Malik/);

    const detail = await admin.get(`/admin/enquiries/${id}`);
    assert.equal(detail.status, 200);
    assert.match(detail.text, /new office interior/);

    const token = await csrfFrom(admin, `/admin/enquiries/${id}`);
    const updated = await admin.post(`/admin/enquiries/${id}`, { _csrf: token, status: 'in_review', notes: 'Called back.' });
    assert.equal(updated.status, 302);
    const stored = server.db.get('SELECT * FROM enquiries WHERE id = ?', id);
    assert.equal(stored.status, 'in_review');
    assert.equal(stored.notes, 'Called back.');

    const exportCsv = await admin.get('/admin/enquiries/export.csv');
    assert.match(exportCsv.text, /sara@example\.com/);
  });

  it('creates a user and blocks a non-owner from user management', async () => {
    const token = await csrfFrom(admin, '/admin/users');
    const created = await admin.post('/admin/users', {
      _csrf: token, email: 'teammate@test.local', name: 'Team Mate', role: 'editor', password: 'Teammate2026',
    });
    assert.equal(created.status, 302);
    assert.ok(server.db.get("SELECT id FROM users WHERE email = 'teammate@test.local'"));
    assert.match(created.location, /ok=/);

    const editor = await signedInAdmin('editor');
    const blocked = await editor.get('/admin/users');
    assert.equal(blocked.status, 403);
    assert.match(blocked.text, /cannot access this area/);
    const settings = await editor.get('/admin/settings');
    assert.equal(settings.status, 403);
  });

  it('issues an API token and shows it exactly once', async () => {
    const token = await csrfFrom(admin, '/admin/tokens');
    const response = await admin.post('/admin/tokens', { _csrf: token, label: 'Deploy script', scopes: 'content:read' });
    assert.equal(response.status, 302);
    assert.match(response.location, /issued=wox_/);
    const issued = decodeURIComponent(response.location.split('issued=')[1]);
    const read = await fetch(`${server.base}/api/v1/admin/pages`, { headers: { authorization: `Bearer ${issued}` } });
    assert.equal(read.status, 200);

    const list = await admin.get('/admin/tokens');
    assert.doesNotMatch(list.text, new RegExp(issued.slice(4, 20)), 'the token hash must not be displayed again');
  });
});

describe('Admin validation boundaries', () => {
  it('returns a 404 screen for unknown admin routes', async () => {
    const admin = await signedInAdmin();
    const response = await admin.get('/admin/nope');
    assert.equal(response.status, 404);
    assert.match(response.text, /does not exist/);
  });

  it('rejects form posts without a CSRF token', async () => {
    const admin = await signedInAdmin();
    const response = await admin.post('/admin/pages', { title: 'No token', slug: 'no-token' });
    assert.equal(response.status, 403);
  });

  it('lets an editor delete a page', async () => {
    const editor = await signedInAdmin('editor');
    const page = server.db.get("SELECT * FROM pages WHERE slug = 'about'");
    const token = await csrfFrom(editor, `/admin/pages/${page.id}`);
    const response = await editor.post(`/admin/pages/${page.id}/delete`, { _csrf: token });
    assert.equal(response.status, 302);
    assert.equal(server.db.get("SELECT id FROM pages WHERE slug = 'about'"), null);
  });

  it('refuses to delete a system page', async () => {
    const admin = await signedInAdmin();
    const created = await admin.jsonRequest('POST', '/api/v1/admin/pages', {
      title: 'System page', slug: 'system-boundary', page_type: 'system',
    }, { 'x-csrf-token': await csrfFrom(admin, '/admin/pages/new') });
    assert.equal(created.status, 201);
    const token = await csrfFrom(admin, `/admin/pages/${created.json.data.id}`);
    const response = await admin.post(`/admin/pages/${created.json.data.id}/delete`, { _csrf: token });
    assert.equal(response.status, 409);
    assert.match(response.text, /cannot be deleted/);
  });
});
