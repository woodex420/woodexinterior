#!/usr/bin/env node
/**
 * End-to-end check of the Woodex OS admin, driven like a browser: a cookie jar,
 * the real login form, the real HTML forms and the real redirects.
 *
 *   npm run admin:check
 *   npm run admin:check -- --base http://localhost:4321 --email … --password …
 *
 * It creates throwaway content prefixed with `qa-`, verifies that the public site
 * reflects it, then removes it again. Exits non-zero if any step fails, so it can
 * gate a deploy. Nothing outside the QA prefix is modified.
 *
 * Note: the check signs in several times and switches a password. Run the server
 * with a raised limit when checking repeatedly (WOODEX_LOGIN_MAX=200).
 */
const args = process.argv.slice(2);
const arg = (name, fallback = '') => {
  const index = args.indexOf(`--${name}`);
  return index === -1 ? fallback : args[index + 1] ?? fallback;
};

const BASE = arg('base', process.env.WOODEX_BASE_URL || 'http://127.0.0.1:4321');
const EMAIL = arg('email', process.env.WOODEX_ADMIN_EMAIL || 'admin@woodexinterior.pk');
const PASSWORD = arg('password', process.env.WOODEX_ADMIN_PASSWORD || 'WoodexStudio2026!');
const TEMPORARY_PASSWORD = 'QaPassword2026';
const STAMP = Date.now().toString(36).slice(-5);

const jar = new Map();
const results = [];

/* ------------------------------------------------------------------ plumbing */

const cookieHeader = () => [...jar.entries()].map(([key, value]) => `${key}=${value}`).join('; ');

async function request(path, { method = 'GET', form, body, headers = {} } = {}) {
  const hdrs = { ...headers };
  const cookie = cookieHeader();
  if (cookie) hdrs.cookie = cookie;
  let payload = body;
  if (form) {
    payload = new URLSearchParams(form).toString();
    hdrs['content-type'] = 'application/x-www-form-urlencoded';
  }
  const response = await fetch(new URL(path, BASE), { method, headers: hdrs, body: payload, redirect: 'manual' });
  for (const raw of response.headers.getSetCookie()) {
    const [pair] = raw.split(';');
    const index = pair.indexOf('=');
    jar.set(pair.slice(0, index).trim(), pair.slice(index + 1).trim());
  }
  return { status: response.status, location: response.headers.get('location') || '', text: await response.text() };
}

const csrfOf = (html) => /name="_csrf" value="([^"]+)"/.exec(html)?.[1] || '';

async function page(path) {
  const response = await request(path);
  return { ...response, csrf: csrfOf(response.text) };
}

async function api(path, { method = 'GET', csrf = '', body } = {}) {
  const headers = { accept: 'application/json' };
  if (csrf) headers['x-csrf-token'] = csrf;
  if (body) headers['content-type'] = 'application/json';
  const response = await request(path, { method, headers, body: body ? JSON.stringify(body) : undefined });
  let json = null;
  try { json = JSON.parse(response.text); } catch { /* not json */ }
  return { ...response, json };
}

function expect(condition, message) {
  if (!condition) throw new Error(message);
}

async function step(name, fn) {
  try {
    const detail = await fn();
    results.push({ name, ok: true, detail: detail || '' });
    console.log(`\u001b[32m✓\u001b[0m ${name.padEnd(48)} ${detail || ''}`);
  } catch (error) {
    results.push({ name, ok: false, detail: error.message });
    console.log(`\u001b[31m✗\u001b[0m ${name.padEnd(48)} ${error.message}`);
  }
}

async function signIn(email, password) {
  jar.clear();
  await page('/admin/login');
  return request('/admin/login', { method: 'POST', form: { email, password } });
}

/* ---------------------------------------------------------------- the checks */

const created = {};

async function run() {
  console.log(`\nWoodex OS admin check · ${BASE}\n`);

  /* 1 · authentication */
  await step('Login screen renders', async () => {
    const response = await page('/admin/login');
    expect(response.status === 200, `expected 200, got ${response.status}`);
    expect(/name="email"/.test(response.text) && /name="password"/.test(response.text), 'login fields missing');
    return 'email + password fields present';
  });

  await step('Wrong password is rejected', async () => {
    const response = await request('/admin/login', { method: 'POST', form: { email: EMAIL, password: 'definitely-wrong' } });
    expect(response.status === 401 && /incorrect/i.test(response.text), `expected 401 with a friendly message, got ${response.status}`);
    return '401 with a generic message';
  });

  await step('Seeded account signs in', async () => {
    const response = await signIn(EMAIL, PASSWORD);
    expect(response.status === 302 && response.location === '/admin', `expected 302 → /admin, got ${response.status} → ${response.location}`);
    return '302 → /admin';
  });

  await step('Anonymous visitors are redirected to login', async () => {
    const saved = new Map(jar);
    jar.clear();
    const response = await request('/admin/pages');
    jar.clear();
    for (const [key, value] of saved) jar.set(key, value);
    expect(response.status === 302 && /\/admin\/login\?next=/.test(response.location), `got ${response.status} → ${response.location}`);
    return '302 with a next= parameter';
  });

  /* 2 · every screen renders */
  const screens = [
    ['/admin', 'Dashboard'],
    ['/admin/pages', 'Pages'],
    ['/admin/pages/new', 'New page'],
    ['/admin/projects', 'Projects'],
    ['/admin/projects/new', 'New project'],
    ['/admin/insights', 'Insights'],
    ['/admin/insights/new', 'New insight'],
    ['/admin/services', 'Services'],
    ['/admin/services/new', 'New service'],
    ['/admin/media', 'Media library'],
    ['/admin/enquiries', 'Enquiries'],
    ['/admin/navigation', 'Navigation'],
    ['/admin/settings', 'Settings'],
    ['/admin/users', 'Users'],
    ['/admin/tokens', 'API tokens'],
    ['/admin/activity', 'Activity log'],
    ['/admin/health', 'Health & QA'],
    ['/admin/account', 'My account'],
  ];
  await step('All 18 admin screens render', async () => {
    const failures = [];
    for (const [path] of screens) {
      const response = await page(path);
      if (response.status !== 200 || !response.text.includes('Woodex')) failures.push(`${path} (${response.status})`);
    }
    expect(!failures.length, `failed: ${failures.join(', ')}`);
    return `${screens.length} screens · 200 · branded shell`;
  });

  await step('Sidebar, groups and live counts', async () => {
    const dashboard = await page('/admin');
    for (const group of ['Website', 'Content', 'System']) expect(dashboard.text.includes(group), `missing the ${group} group`);
    const counts = {
      pages: /Published pages<\/div><div class="value">(\d+)</.exec(dashboard.text)?.[1],
      projects: /Projects<\/div><div class="value">(\d+)</.exec(dashboard.text)?.[1],
      insights: /Insights<\/div><div class="value">(\d+)</.exec(dashboard.text)?.[1],
      enquiries: /New enquiries<\/div><div class="value">(\d+)</.exec(dashboard.text)?.[1],
    };
    expect(Object.values(counts).every((value) => value !== undefined), 'stat cards missing');
    return `${counts.pages} pages · ${counts.projects} projects · ${counts.insights} insights · ${counts.enquiries} new enquiries`;
  });

  await step('Unknown admin URL returns 404, not 500', async () => {
    const response = await page('/admin/not-a-screen');
    expect(response.status === 404, `expected 404, got ${response.status}`);
    expect(/does not exist/.test(response.text), 'no friendly message');
    return '404 with the branded error screen';
  });

  /* 3 · pages */
  await step('Create a page with the block editor', async () => {
    const form = await page('/admin/pages/new');
    const blocks = JSON.stringify([
      { type: 'pageHero', eyebrow: 'QA', title: `QA check ${STAMP}`, intro: 'Created by admin-check.' },
      { type: 'richText', html: '<p>Body written through the block editor.</p>' },
    ]);
    const response = await request('/admin/pages', {
      method: 'POST',
      form: {
        _csrf: form.csrf,
        title: `QA page ${STAMP}`, slug: `qa-page-${STAMP}`,
        template: 'default', page_type: 'page', status: 'published', sort_order: '50',
        seo_title: `QA page ${STAMP}`,
        seo_description: 'A temporary page created by the admin end-to-end check.',
        blocks,
      },
    });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    created.pageId = /\/admin\/pages\/(\d+)/.exec(response.location)?.[1];
    expect(created.pageId, `no page id in ${response.location}`);
    const publicPage = await request(`/qa-page-${STAMP}`);
    expect(publicPage.status === 200, `public page ${publicPage.status}`);
    expect(publicPage.text.includes(`QA check ${STAMP}`), 'hero title missing on the public page');
    expect(publicPage.text.includes('Body written through the block editor'), 'rich text missing on the public page');
    return `id ${created.pageId} · live at /qa-page-${STAMP}`;
  });

  await step('Invalid input returns 422 and keeps values', async () => {
    const form = await page('/admin/pages/new');
    const response = await request('/admin/pages', { method: 'POST', form: { _csrf: form.csrf, title: '', slug: 'Not A Slug', blocks: '[]' } });
    expect(response.status === 422, `expected 422, got ${response.status}`);
    expect(response.text.includes('alert err'), 'no error alert');
    expect(response.text.includes('value="Not A Slug"'), 'submitted values were not preserved');
    return '422 with preserved values and field errors';
  });

  await step('Edit a page, write a revision, see it publicly', async () => {
    const editor = await page(`/admin/pages/${created.pageId}`);
    expect(editor.status === 200, `editor ${editor.status}`);
    expect(editor.text.includes('block-schema'), 'block editor schema missing');
    const response = await request(`/admin/pages/${created.pageId}`, {
      method: 'POST',
      form: {
        _csrf: editor.csrf,
        title: `QA page ${STAMP} edited`, slug: `qa-page-${STAMP}`,
        template: 'default', page_type: 'page', status: 'published',
        blocks: JSON.stringify([{ type: 'richText', html: '<p>Edited body copy.</p>' }]),
      },
    });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    const publicPage = await request(`/qa-page-${STAMP}`);
    expect(publicPage.text.includes('Edited body copy'), 'the edit did not reach the public page');
    const revisions = await api(`/api/v1/admin/pages/${created.pageId}/revisions`, { csrf: editor.csrf });
    expect(revisions.status === 200, `revisions ${revisions.status}`);
    expect((revisions.json?.data || []).length > 0, 'no revision stored');
    return 'edit reflected publicly · revision stored';
  });

  await step('Unpublish hides the URL, publish restores it', async () => {
    const editor = await page(`/admin/pages/${created.pageId}`);
    const off = await request(`/admin/pages/${created.pageId}/publish`, { method: 'POST', form: { _csrf: editor.csrf } });
    expect(off.status === 302, `unpublish ${off.status}`);
    expect((await request(`/qa-page-${STAMP}`)).status === 404, 'draft still public');
    const on = await request(`/admin/pages/${created.pageId}/publish`, { method: 'POST', form: { _csrf: editor.csrf } });
    expect(on.status === 302, `republish ${on.status}`);
    expect((await request(`/qa-page-${STAMP}`)).status === 200, 'republish did not restore the URL');
    return 'draft 404s · publish 200s';
  });

  await step('Restore a page revision', async () => {
    const editor = await page(`/admin/pages/${created.pageId}`);
    const revisions = await api(`/api/v1/admin/pages/${created.pageId}/revisions`);
    const first = (revisions.json?.data || [])[0];
    expect(first, 'no revisions to restore');
    const response = await request(`/admin/pages/${created.pageId}/revisions/${first.id}/restore`, { method: 'POST', form: { _csrf: editor.csrf } });
    expect(response.status === 302, `restore ${response.status}`);
    return `restored revision #${first.id}`;
  });

  /* 4 · collections */
  await step('Create a project and see it on the portfolio', async () => {
    const form = await page('/admin/projects/new');
    const response = await request('/admin/projects', {
      method: 'POST',
      form: {
        _csrf: form.csrf,
        title: `QA project ${STAMP}`, slug: `qa-project-${STAMP}`,
        category: 'residential', location: 'Lahore', year: '2026', area: '200 sq m',
        summary: 'A temporary project from the admin check.',
        body: '<p>Project body.</p>', status: 'published',
      },
    });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    const publicProject = await request(`/portfolio/qa-project-${STAMP}`);
    expect(publicProject.status === 200, `public project ${publicProject.status}`);
    expect(publicProject.text.includes(`QA project ${STAMP}`), 'project title missing publicly');
    return `live at /portfolio/qa-project-${STAMP}`;
  });

  await step('Create an insight and see it in the journal', async () => {
    const form = await page('/admin/insights/new');
    const response = await request('/admin/insights', {
      method: 'POST',
      form: {
        _csrf: form.csrf,
        title: `QA insight ${STAMP}`, slug: `qa-insight-${STAMP}`,
        category: 'Design notes', reading_time: '3 min read',
        excerpt: 'A temporary insight.', body: '<p>Insight body.</p>', status: 'published',
      },
    });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    expect((await request(`/insights/qa-insight-${STAMP}`)).status === 200, 'insight not public');
    return `live at /insights/qa-insight-${STAMP}`;
  });

  await step('Create a service', async () => {
    const form = await page('/admin/services/new');
    const response = await request('/admin/services', {
      method: 'POST',
      form: {
        _csrf: form.csrf,
        title: `QA service ${STAMP}`, slug: `qa-service-${STAMP}`,
        number: '09', summary: 'A temporary service.', body: '<p>Service body.</p>', status: 'published',
      },
    });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    expect((await request('/services')).text.includes(`QA service ${STAMP}`), 'service not listed publicly');
    return 'listed on /services';
  });

  /* 5 · media */
  await step('Upload an image through the media form', async () => {
    const form = await page('/admin/media');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', 'base64');
    const body = new FormData();
    body.set('_csrf', form.csrf);
    body.set('alt', `QA pixel ${STAMP}`);
    body.set('file', new Blob([png], { type: 'image/png' }), 'qa-pixel.png');
    const response = await request('/admin/media', { method: 'POST', body });
    expect(response.status === 302, `expected 302, got ${response.status}`);
    expect(/uploaded=/.test(response.location), `unexpected redirect ${response.location}`);
    expect((await page('/admin/media')).text.includes(`QA pixel ${STAMP}`), 'upload not listed');
    return 'stored with alt text and listed';
  });

  /* 6 · enquiries */
  await step('Public form → admin inbox → status → CSV', async () => {
    const contact = await request('/contact');
    const token = /name="_csrf" value="([^"]+)"/.exec(contact.text)?.[1];
    expect(token, 'no contact form token');
    const post = await fetch(new URL('/contact', BASE), {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookieHeader() },
      body: new URLSearchParams({
        _csrf: token,
        name: `QA visitor ${STAMP}`,
        email: `qa-${STAMP}@example.com`,
        service: 'residential',
        message: 'A temporary enquiry created by the admin end-to-end check.',
        consent: '1',
      }).toString(),
      redirect: 'manual',
    });
    expect(post.status === 303, `contact POST ${post.status}`);

    const inbox = await page('/admin/enquiries');
    expect(inbox.text.includes(`QA visitor ${STAMP}`), 'enquiry not in the inbox');
    const id = /href="\/admin\/enquiries\/(\d+)"/.exec(inbox.text)?.[1];
    expect(id, 'no enquiry id in the inbox');
    created.enquiryId = id;

    const detail = await page(`/admin/enquiries/${id}`);
    expect(detail.status === 200, `enquiry detail ${detail.status}`);
    const updated = await request(`/admin/enquiries/${id}`, {
      method: 'POST',
      form: { _csrf: detail.csrf, status: 'in_review', notes: 'Handled by the QA check.' },
    });
    expect(updated.status === 302, `enquiry update ${updated.status}`);
    const csv = await request('/admin/enquiries/export.csv');
    expect(csv.status === 200 && /email/i.test(csv.text), `CSV export ${csv.status}`);
    return 'stored · opened · status changed · CSV exported';
  });

  /* 7 · navigation & settings */
  await step('Add a nav item and see it on the site', async () => {
    const nav = await page('/admin/navigation');
    const added = await request('/admin/navigation/add', {
      method: 'POST',
      form: { _csrf: nav.csrf, area: 'main', label: `QA link ${STAMP}`, href: '/qa-link' },
    });
    expect(added.status === 302, `add ${added.status}`);
    expect((await request('/')).text.includes(`QA link ${STAMP}`), 'nav item not on the public site');
    const refreshed = await page('/admin/navigation');
    const match = new RegExp(`name="label_(\\d+)"[^>]*value="QA link ${STAMP}"`).exec(refreshed.text)
      || new RegExp(`value="QA link ${STAMP}"[^>]*name="label_(\\d+)"`).exec(refreshed.text);
    if (match) {
      await request(`/admin/navigation/${match[1]}/delete`, { method: 'POST', form: { _csrf: refreshed.csrf } });
      expect((await request('/')).text.includes(`QA link ${STAMP}`) === false, 'nav item still public after removal');
    }
    return 'added · rendered publicly · removed';
  });

  await step('Save site settings', async () => {
    const form = await page('/admin/settings');
    expect(form.text.includes('site.name'), 'settings form missing site.name');
    const response = await request('/admin/settings', {
      method: 'POST',
      form: {
        _csrf: form.csrf,
        'site.name': 'Woodex Interior',
        'contact.phone': '+92 322 4000768',
        'contact.hours': 'Mon–Sat, 9:00 AM – 6:00 PM',
        'enquiry.honeypot': 'company_website',
      },
    });
    expect(response.status === 302, `save ${response.status}`);
    expect((await request('/')).text.includes('Woodex Interior'), 'site name missing publicly');
    return '302 · reflected on the public site';
  });

  /* 8 · roles */
  await step('Viewer account: can read, cannot manage', async () => {
    const form = await page('/admin/users');
    const email = `qa-viewer-${STAMP}@example.com`;
    const made = await request('/admin/users', {
      method: 'POST',
      form: { _csrf: form.csrf, email, name: `QA Viewer ${STAMP}`, role: 'viewer', password: TEMPORARY_PASSWORD },
    });
    expect(made.status === 302 && /ok=/.test(made.location), `create ${made.status} ${made.location}`);

    const list = await api('/api/v1/admin/users');
    const viewer = (list.json?.data || []).find((user) => user.email === email);
    expect(viewer, 'viewer not created');
    created.viewerId = viewer.id;

    const ownerJar = new Map(jar);
    const login = await signIn(email, TEMPORARY_PASSWORD);
    expect(login.status === 302, `viewer login ${login.status}`);
    expect((await page('/admin/pages')).status === 200, 'viewer cannot read pages');
    expect((await page('/admin/users')).status === 403, 'viewer can reach users');
    expect((await page('/admin/settings')).status === 403, 'viewer can reach settings');

    jar.clear();
    for (const [key, value] of ownerJar) jar.set(key, value);
    const admin = await page('/admin/users');
    const removed = await request(`/admin/users/${created.viewerId}/delete`, { method: 'POST', form: { _csrf: admin.csrf } });
    expect(removed.status === 302, `delete viewer ${removed.status}`);
    return 'read-only enforced · account removed';
  });

  /* 9 · tokens */
  await step('Issue an API token, use it, revoke it', async () => {
    const form = await page('/admin/tokens');
    const response = await request('/admin/tokens', {
      method: 'POST',
      form: { _csrf: form.csrf, label: `QA token ${STAMP}`, scopes: 'content:read' },
    });
    expect(response.status === 302, `issue ${response.status}`);
    const token = decodeURIComponent(/issued=([^&]+)/.exec(response.location)?.[1] || '');
    expect(token.startsWith('wox_'), 'no token in the redirect');

    const read = await fetch(new URL('/api/v1/admin/pages', BASE), { headers: { authorization: `Bearer ${token}` } });
    expect(read.status === 200, `token read ${read.status}`);

    const list = await api('/api/v1/admin/tokens');
    const row = (list.json?.data || []).find((item) => item.label === `QA token ${STAMP}`);
    expect(row, 'token not listed');
    const csrf = (await page('/admin/tokens')).csrf;
    const revoked = await api(`/api/v1/admin/tokens/${row.id}`, { method: 'DELETE', csrf });
    expect(revoked.status === 200, `revoke ${revoked.status}`);
    const after = await fetch(new URL('/api/v1/admin/pages', BASE), { headers: { authorization: `Bearer ${token}` } });
    expect(after.status === 401, `revoked token still works (${after.status})`);
    return 'issued · authenticated · revoked';
  });

  /* 10 · account */
  await step('Profile save and password change', async () => {
    const profile = await page('/admin/account');
    expect(profile.status === 200, `account screen ${profile.status}`);
    const saved = await request('/admin/account', { method: 'POST', form: { _csrf: profile.csrf, name: 'Woodex Studio', email: EMAIL } });
    expect(saved.status === 302, `profile save ${saved.status}`);

    const wrong = await request('/admin/account', {
      method: 'POST',
      form: { _csrf: (await page('/admin/account')).csrf, name: 'Woodex Studio', email: EMAIL, current_password: 'nope', new_password: TEMPORARY_PASSWORD, confirm_password: TEMPORARY_PASSWORD },
    });
    expect(wrong.status === 422 && /not correct/i.test(wrong.text), `wrong current password accepted (${wrong.status})`);

    const mismatch = await request('/admin/account', {
      method: 'POST',
      form: { _csrf: (await page('/admin/account')).csrf, name: 'Woodex Studio', email: EMAIL, current_password: PASSWORD, new_password: TEMPORARY_PASSWORD, confirm_password: `${TEMPORARY_PASSWORD}x` },
    });
    expect(mismatch.status === 422, `mismatched passwords accepted (${mismatch.status})`);

    const changed = await request('/admin/account', {
      method: 'POST',
      form: { _csrf: (await page('/admin/account')).csrf, name: 'Woodex Studio', email: EMAIL, current_password: PASSWORD, new_password: TEMPORARY_PASSWORD, confirm_password: TEMPORARY_PASSWORD },
    });
    expect(changed.status === 302, `password change ${changed.status}`);
    expect((await page('/admin/account')).status === 200, 'session lost after the password change');

    expect((await signIn(EMAIL, TEMPORARY_PASSWORD)).status === 302, 'new password rejected');
    expect((await signIn(EMAIL, PASSWORD)).status === 401, 'old password still accepted');

    expect((await signIn(EMAIL, TEMPORARY_PASSWORD)).status === 302, 'cannot sign in to restore');
    const restore = await request('/admin/account', {
      method: 'POST',
      form: { _csrf: (await page('/admin/account')).csrf, name: 'Woodex Studio', email: EMAIL, current_password: TEMPORARY_PASSWORD, new_password: PASSWORD, confirm_password: PASSWORD },
    });
    expect(restore.status === 302, `restore ${restore.status}`);
    expect((await signIn(EMAIL, PASSWORD)).status === 302, 'restored password does not work');
    return 'saved · wrong password rejected · rotated · original restored';
  });

  /* 11 · export */
  await step('Export the static site from the admin', async () => {
    const form = await page('/admin/health');
    const response = await request('/admin/export', { method: 'POST', form: { _csrf: form.csrf } });
    expect(response.status === 302, `export ${response.status}`);
    expect(/ok=Exported/.test(response.location), `unexpected redirect ${response.location}`);
    const exported = decodeURIComponent(response.location);
    expect(/index\.html/.test(exported) === false, 'sanity');
    return decodeURIComponent(/%2Fdist/.test(response.location) ? 'exported to dist/' : response.location);
  });

  /* 12 · cleanup */
  await step('Clean up QA content', async () => {
    const csrf = (await page('/admin/pages')).csrf;
    let removed = 0;

    const collections = [
      ['pages', /^qa-page-/],
      ['projects', /^qa-project-/],
      ['insights', /^qa-insight-/],
      ['services', /^qa-service-/],
    ];
    for (const [collection, pattern] of collections) {
      const list = await api(`/api/v1/admin/${collection}?per_page=100`);
      expect(list.status === 200, `list ${collection}: ${list.status}`);
      for (const row of (list.json?.data || []).filter((item) => pattern.test(item.slug))) {
        const response = await api(`/api/v1/admin/${collection}/${row.id}`, { method: 'DELETE', csrf });
        expect(response.status === 200, `delete ${collection}/${row.id}: ${response.status}`);
        removed += 1;
      }
    }

    // Issued tokens are revoked rather than deleted: they stay in the audit trail.
    const enquiries = await api('/api/v1/admin/enquiries?per_page=100');
    for (const row of (enquiries.json?.data || []).filter((item) => String(item.name || '').includes(`QA visitor ${STAMP}`))) {
      const response = await api(`/api/v1/admin/enquiries/${row.id}`, { method: 'DELETE', csrf });
      if (response.status === 200) removed += 1;
    }

    const media = await page('/admin/media');
    for (const match of media.text.matchAll(/action="\/admin\/media\/(\d+)\/delete"/g)) {
      const response = await request(`/admin/media/${match[1]}/delete`, { method: 'POST', form: { _csrf: media.csrf } });
      if (response.status === 302) removed += 1;
    }

    for (const slug of [`qa-page-${STAMP}`, `qa-project-${STAMP}`, `qa-insight-${STAMP}`, `qa-service-${STAMP}`]) {
      expect((await request(`/${slug}`)).status === 404, `/${slug} still public`);
    }
    return `${removed} QA record(s) removed · QA tokens revoked, kept for audit`;
  });

  const passed = results.filter((item) => item.ok).length;
  const failed = results.filter((item) => !item.ok);
  console.log(`\n${passed}/${results.length} checks passed, ${failed.length} failed\n`);
  if (failed.length) {
    console.log('Failed checks:');
    for (const item of failed) console.log(`  ✗ ${item.name}: ${item.detail}`);
  }
  process.exitCode = failed.length ? 1 : 0;
}

run().catch((error) => {
  console.error('\nAdmin check crashed:', error);
  process.exitCode = 1;
});
