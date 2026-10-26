import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createApp } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { setSetting } from '../src/db.js';
import { hashPassword } from '../src/lib/auth.js';
import { savePage, saveProject, saveInsight, saveService } from '../src/lib/content.js';

/** A 1×1 transparent PNG, small enough to inline. */
export const PNG_PIXEL = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
  'base64',
);

export const ACCOUNTS = {
  owner: { email: 'owner@test.local', password: 'OwnerPass1234', role: 'owner', name: 'Test Owner' },
  editor: { email: 'editor@test.local', password: 'EditorPass1234', role: 'editor', name: 'Test Editor' },
  viewer: { email: 'viewer@test.local', password: 'ViewerPass1234', role: 'viewer', name: 'Test Viewer' },
};

export function seedFixtures(db) {
  setSetting(db, 'site.name', 'Woodex Interior');
  setSetting(db, 'site.url', 'https://woodex.com.pk');
  setSetting(db, 'contact.email', 'studio@example.com');
  setSetting(db, 'contact.phone', '+92 322 4000768');
  setSetting(db, 'contact.hours', 'Mon–Sat, 9:00 AM – 6:00 PM');

  for (const account of Object.values(ACCOUNTS)) {
    db.run(
      `INSERT INTO users (email, name, role, password_hash, status, created_at, updated_at)
       VALUES (?, ?, ?, ?, 'active', strftime('%Y-%m-%dT%H:%M:%SZ','now'), strftime('%Y-%m-%dT%H:%M:%SZ','now'))`,
      account.email, account.name, account.role, hashPassword(account.password),
    );
  }

  savePage(db, {
    title: 'Home', slug: 'home', page_type: 'home', status: 'published',
    seo_title: 'Woodex Interior — thoughtful interiors',
    seo_description: 'Design-led interiors, bespoke woodwork and considered detailing for homes and workplaces in Lahore.',
    blocks: [
      { type: 'hero', slides: [{ tag: 'Lahore studio', title: 'Spaces shaped by <br>purpose', word: 'DESIGN', copy: 'Considered interiors.', image: '/assets/detail.jpg', alt: 'Interior' }] },
      { type: 'richText', html: '<p>We design calm, material-led interiors.</p>' },
    ],
  });
  savePage(db, {
    title: 'About the studio', slug: 'about', status: 'published',
    seo_title: 'About the studio',
    seo_description: 'Meet the Woodex Interior studio and the way we approach residential and commercial interiors.',
    blocks: [
      { type: 'pageHero', eyebrow: 'Our studio', title: 'Designing spaces.<br>Understanding people.', intro: 'A design-led studio in Lahore.' },
      { type: 'richText', html: '<p>Every space has its own possibilities.</p>' },
    ],
  });
  savePage(db, {
    title: 'Contact', slug: 'contact', status: 'published',
    seo_title: 'Start your project',
    seo_description: 'Tell Woodex Interior about your space and the studio will reply within two working days.',
    blocks: [
      { type: 'pageHero', title: 'Every beautiful space starts with a conversation.' },
      { type: 'contactForm', formTitle: 'Tell us about your project.' },
    ],
  });
  savePage(db, {
    title: 'Portfolio', slug: 'portfolio', status: 'published',
    seo_title: 'Concept portfolio', seo_description: 'Residential, commercial and bespoke interior concepts from the Woodex studio.',
    blocks: [{ type: 'pageHero', eyebrow: 'Selected work', title: 'A considered collection of interiors.' }],
  });
  savePage(db, {
    title: 'Insights', slug: 'insights', status: 'published',
    seo_title: 'Studio journal', seo_description: 'Design notes on materials, space planning and lighting from the Woodex studio.',
    blocks: [{ type: 'pageHero', eyebrow: 'The studio journal', title: 'Ideas for a more considered interior.' }],
  });
  savePage(db, {
    title: 'Services', slug: 'services', status: 'published',
    seo_title: 'Interior design services', seo_description: 'Residential interiors, commercial spaces, bespoke woodwork and styling from Woodex Interior.',
    blocks: [{ type: 'pageHero', eyebrow: 'What we do', title: 'Interiors, woodwork and styling under one roof.' }],
  });
  savePage(db, {
    title: 'Draft page', slug: 'draft-page', status: 'draft',
    seo_title: 'Draft', seo_description: 'Not published yet.', blocks: [{ type: 'richText', html: '<p>Draft</p>' }],
  });

  saveProject(db, {
    title: 'The Arched Residence', slug: 'the-arched-residence', status: 'published', category: 'residential',
    location: 'Lahore', year: '2025', area: '420 sq m', summary: 'A calm, light-filled residence concept.',
    body: '<p>Arched openings frame the living spaces.</p>', cover_image: '/assets/detail.jpg',
    gallery: [{ src: '/assets/detail.jpg', alt: 'Sitting room' }], facts: [{ label: 'Scope', value: 'Full home' }],
  });
  saveProject(db, {
    title: 'Draft project', slug: 'draft-project', status: 'draft', category: 'commercial', summary: 'Hidden.',
  });
  saveInsight(db, {
    title: 'The quiet beauty of natural materials', slug: 'quiet-materials', status: 'published',
    excerpt: 'Why timber and stone age so well.', body: '<h2>Material honesty</h2><p>Natural materials settle into a room.</p>',
    category: 'Materials',
  });
  saveService(db, {
    title: 'Residential interiors', slug: 'residential', status: 'published', number: '01',
    summary: 'Homes shaped around the people who live in them.',
    body: '<p>From a single room to a whole home.</p>',
    deliverables: ['Space planning', 'Material palettes'],
  });
}

export async function startTestServer(overrides = {}) {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-test-'));
  const config = {
    dbPath: ':memory:',
    uploadDir: path.join(tmp, 'uploads'),
    assetsDir: path.resolve(process.cwd(), '../woodex/assets'),
    port: 0,
    host: '127.0.0.1',
    secureCookies: false,
    trustProxy: false,
    env: 'test',
    baseUrl: 'http://127.0.0.1',
    loginMaxAttempts: 100,
    enquiryMaxPerWindow: 100,
    rateLimitWindowMs: 60_000,
    ...overrides,
  };
  const { app, db } = createApp(config);
  if (!config.empty) seedFixtures(db);
  const server = await new Promise((resolve) => {
    const listener = app.listen(0, '127.0.0.1', () => resolve(listener));
  });
  const base = `http://127.0.0.1:${server.address().port}`;
  return {
    base,
    app,
    db,
    config,
    uploadDir: config.uploadDir,
    accounts: ACCOUNTS,
    async close() {
      await new Promise((resolve) => server.close(resolve));
      db.close();
      fs.rmSync(tmp, { recursive: true, force: true });
    },
  };
}

/** Minimal browser-ish client: keeps cookies, never follows redirects. */
export function makeClient(base) {
  const jar = new Map();

  async function request(method, url, { form, json, headers = {}, body } = {}) {
    const hdrs = { ...headers };
    const cookie = [...jar.entries()].map(([key, value]) => `${key}=${value}`).join('; ');
    if (cookie) hdrs.cookie = cookie;
    let payload = body;
    if (form) {
      payload = new URLSearchParams(form).toString();
      hdrs['content-type'] = 'application/x-www-form-urlencoded';
    } else if (json !== undefined) {
      payload = JSON.stringify(json);
      hdrs['content-type'] = 'application/json';
      hdrs.accept = 'application/json';
    }
    const response = await fetch(new URL(url, base), { method, headers: hdrs, body: payload, redirect: 'manual' });
    for (const raw of response.headers.getSetCookie()) {
      const [pair] = raw.split(';');
      const index = pair.indexOf('=');
      const key = pair.slice(0, index).trim();
      const value = pair.slice(index + 1).trim();
      if (value === '' || /expires=Thu, 01 Jan 1970/i.test(raw)) jar.delete(key);
      else jar.set(key, value);
    }
    const text = await response.text();
    let data = null;
    try { data = JSON.parse(text); } catch { /* html */ }
    return {
      status: response.status,
      headers: response.headers,
      location: response.headers.get('location'),
      text,
      data,
      json: data,
    };
  }

  return {
    base,
    jar,
    get: (url, headers) => request('GET', url, { headers }),
    post: (url, form, headers) => request('POST', url, { form, headers }),
    put: (url, json, headers) => request('PUT', url, { json, headers }),
    patch: (url, json, headers) => request('PATCH', url, { json, headers }),
    delete: (url, headers) => request('DELETE', url, { headers }),
    login(email, password) {
      return request('POST', '/api/v1/session', { json: { email, password }, headers: {} });
    },
    jsonRequest: (method, url, body, headers) => (body === undefined
      ? request(method, url, { headers })
      : request(method, url, { json: body, headers })),
    raw: request,
  };
}

/** Log in over the JSON API and return the client + CSRF token. */
export async function signIn(base, role = 'owner') {
  const client = makeClient(base);
  const account = ACCOUNTS[role];
  const response = await client.post('/api/v1/session', { email: account.email, password: account.password });
  if (response.status !== 200) throw new Error(`login failed (${response.status}): ${response.text}`);
  return { client, csrf: response.data.data.csrf, user: response.data.data.user };
}

/** Read the CSRF token out of a rendered admin form. */
export async function csrfFrom(client, url) {
  const response = await client.get(url);
  const match = /name="_csrf" value="([^"]+)"/.exec(response.text);
  if (!match) throw new Error(`no CSRF token found on ${url} (status ${response.status})`);
  return match[1];
}
