import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { startTestServer, makeClient, ACCOUNTS } from './helpers.js';

let server;
let client;
before(async () => {
  server = await startTestServer();
  client = makeClient(server.base);
});
after(async () => { await server.close(); });

const meta = (html, name) => {
  const match = new RegExp(`<meta (?:name|property)="${name}" content="([^"]*)"`).exec(html);
  return match ? match[1] : '';
};

describe('Public pages', () => {
  it('renders the home page with complete, unique SEO metadata', async () => {
    const home = await client.get('/');
    assert.equal(home.status, 200);
    assert.match(home.text, /<title>Woodex Interior — thoughtful interiors<\/title>/);
    assert.match(meta(home.text, 'description'), /Design-led interiors/);
    assert.equal(meta(home.text, 'og:title'), 'Woodex Interior — thoughtful interiors');
    assert.equal(meta(home.text, 'og:url'), 'https://woodex.com.pk/');
    assert.match(meta(home.text, 'og:image'), /^https:\/\/woodex\.com\.pk\/assets\/\w+\.jpg$/);
    assert.match(meta(home.text, 'twitter:card'), /summary_large_image/);
    assert.match(home.text, /<link rel="canonical" href="https:\/\/woodex\.com\.pk\/">/);
    assert.match(home.text, /"@type":"InteriorDesignBusiness"/);
    assert.match(home.text, /"@type":"WebSite"/);
  });

  it('gives every page its own title, description and canonical', async () => {
    const about = await client.get('/about');
    const contact = await client.get('/contact');
    assert.equal(about.status, 200);
    assert.notEqual(meta(about.text, 'description'), meta(contact.text, 'description'));
    assert.equal(meta(about.text, 'og:url'), 'https://woodex.com.pk/about');
    assert.match(about.text, /rel="canonical" href="https:\/\/woodex\.com\.pk\/about"/);
    assert.match(about.text, /"@type":"BreadcrumbList"/);
  });

  it('renders project and insight detail pages from the CMS', async () => {
    const project = await client.get('/portfolio/the-arched-residence');
    assert.equal(project.status, 200);
    assert.match(project.text, /The Arched Residence/);
    assert.match(project.text, /420 sq m/);
    assert.match(project.text, /"@type":"CreativeWork"/);

    const insight = await client.get('/insights/quiet-materials');
    assert.equal(insight.status, 200);
    assert.match(insight.text, /Material honesty/);
    assert.match(insight.text, /"@type":"Article"/);
  });

  it('keeps drafts private and preview behind a login', async () => {
    assert.equal((await client.get('/draft-page')).status, 404);
    assert.equal((await client.get('/portfolio/draft-project')).status, 404);
    const preview = await client.get('/preview/draft-page');
    assert.equal(preview.status, 302);
    assert.match(preview.location, /\/admin\/login/);
  });

  it('returns a real 404 with noindex and the branded shell', async () => {
    const response = await client.get('/this-page-does-not-exist');
    assert.equal(response.status, 404);
    assert.match(response.text, /Page not found/);
    assert.match(response.text, /name="robots" content="noindex/);
    assert.match(response.text, /class="site-footer"/);
    assert.match(response.text, /class="site-header/);
  });

  it('serves sitemap, robots, feed and manifest', async () => {
    const sitemap = await client.get('/sitemap.xml');
    assert.equal(sitemap.status, 200);
    assert.match(sitemap.headers.get('content-type'), /xml/);
    assert.match(sitemap.text, /<loc>https:\/\/woodex\.com\.pk\/about<\/loc>/);
    assert.match(sitemap.text, /\/portfolio\/the-arched-residence/);
    assert.match(sitemap.text, /\/insights\/quiet-materials/);
    assert.doesNotMatch(sitemap.text, /draft/);
    assert.doesNotMatch(sitemap.text, /\/home</);

    const robots = await client.get('/robots.txt');
    assert.match(robots.text, /Disallow: \/admin/);
    assert.match(robots.text, /Sitemap: https:\/\/woodex\.com\.pk\/sitemap\.xml/);

    const feed = await client.get('/feed.xml');
    assert.equal(feed.status, 200);
    assert.match(feed.text, /The quiet beauty of natural materials/);

    const manifest = await client.get('/site.webmanifest');
    assert.equal(manifest.status, 200);
    assert.equal(JSON.parse(manifest.text).name, 'Woodex Interior');
  });

  it('sets security headers on HTML responses', async () => {
    const response = await client.get('/');
    assert.match(response.headers.get('content-security-policy'), /default-src 'self'/);
    assert.match(response.headers.get('content-security-policy'), /frame-ancestors/);
    assert.equal(response.headers.get('x-content-type-options'), 'nosniff');
    assert.equal(response.headers.get('x-frame-options'), 'SAMEORIGIN');
    assert.equal(response.headers.get('x-powered-by'), null);
  });

  it('renders semantic, accessible markup', async () => {
    for (const url of ['/', '/about', '/portfolio', '/contact', '/insights/quiet-materials']) {
      const { text } = await client.get(url);
      const h1 = text.match(/<h1\b/g) || [];
      assert.equal(h1.length, 1, `${url} should have exactly one h1`);
      const buttons = text.match(/<button\b[^>]*>/g) || [];
      for (const button of buttons) assert.match(button, /type="(button|submit)"/, `untyped button on ${url}`);
      const images = text.match(/<img\b[^>]*>/g) || [];
      for (const image of images) {
        assert.match(image, /\balt="/, `image without alt on ${url}: ${image}`);
        assert.match(image, /\bwidth="/, `image without width on ${url}: ${image}`);
        assert.match(image, /\bheight="/, `image without height on ${url}: ${image}`);
      }
      assert.doesNotMatch(text, /<h4/, `${url} should not skip to h4`);
    }
  });

  it('renders the search page with escaped, relevant results', async () => {
    const response = await client.get('/search?q=arched');
    assert.equal(response.status, 200);
    assert.match(response.text, /The Arched Residence/);
    assert.match(response.text, /noindex/);

    const empty = await client.get('/search?q=zzzzzz');
    assert.match(empty.text, /Nothing matched/);
  });
});

describe('Contact form', () => {
  it('renders the form with a CSRF token, honeypot and a working submit button', async () => {
    const response = await client.get('/contact');
    assert.equal(response.status, 200);
    assert.match(response.text, /<form[^>]*method="post"[^>]*action="\/contact"/);
    assert.match(response.text, /name="_csrf" value="[a-f0-9]{24,}"/);
    assert.match(response.text, /name="company_website"/);
    assert.match(response.text, /name="consent"/);
    assert.doesNotMatch(response.text, /type="submit"[^>]*disabled/);
    assert.ok(client.jar.has('woodex_form'), 'a form session cookie should be set');
  });

  it('rejects invalid input with per-field errors and keeps the values', async () => {
    const response = await client.post('/contact', {
      _csrf: client.jar.get('woodex_form'),
      name: 'A',
      email: 'not-an-email',
      service: 'nope',
      message: 'short',
    });
    assert.equal(response.status, 422);
    assert.match(response.text, /Your name/);
    assert.match(response.text, /valid email/i);
    assert.match(response.text, /value="not-an-email"/);
    assert.match(response.text, /privacy note/i);
  });

  it('stores a valid enquiry and redirects to the thank-you page', async () => {
    const token = client.jar.get('woodex_form');
    const response = await client.post('/contact', {
      _csrf: token,
      name: 'Ayesha Khan',
      email: 'ayesha@example.com',
      phone: '+92 321 5556677',
      location: 'DHA Phase 6',
      service: 'residential',
      timeline: '3–6 months',
      message: 'We have bought a new house and need help with the living and dining rooms.',
      consent: '1',
    });
    assert.equal(response.status, 303);
    assert.equal(response.location, '/thank-you');

    const stored = server.db.get("SELECT * FROM enquiries WHERE email = 'ayesha@example.com'");
    assert.ok(stored, 'enquiry should be stored');
    assert.equal(stored.name, 'Ayesha Khan');
    assert.equal(stored.status, 'new');
    assert.match(stored.source, /^website/);
  });

  it('silently drops honeypot submissions', async () => {
    const before = server.db.get("SELECT COUNT(*) AS n FROM enquiries WHERE email = 'bot@example.com'").n;
    const response = await client.post('/contact', {
      _csrf: client.jar.get('woodex_form'),
      name: 'Bot',
      email: 'bot@example.com',
      service: 'residential',
      message: 'Cheap watches, buy now from our spam website.',
      consent: '1',
      company_website: 'https://spam.example',
    });
    assert.equal(response.status, 303);
    const after = server.db.get("SELECT COUNT(*) AS n FROM enquiries WHERE email = 'bot@example.com'").n;
    assert.equal(after, before);
  });

  it('rejects a submission whose token does not match the cookie', async () => {
    const response = await client.post('/contact', {
      _csrf: 'not-the-cookie',
      name: 'Mismatch',
      email: 'mismatch@example.com',
      service: 'residential',
      message: 'This submission should not be stored anywhere at all.',
      consent: '1',
    });
    assert.equal(response.status, 422);
    assert.match(response.text, /session expired|reload/i);
    assert.equal(server.db.get("SELECT id FROM enquiries WHERE email = 'mismatch@example.com'"), null);
  });

  it('shows a helpful thank-you page', async () => {
    const response = await client.get('/thank-you');
    assert.equal(response.status, 200);
    assert.match(response.text, /thank you/i);
  });
});

describe('Preview and auth redirects', () => {
  it('shows a draft to a signed-in editor', async () => {
    const editor = makeClient(server.base);
    const login = await editor.post('/admin/login', {
      email: ACCOUNTS.editor.email, password: ACCOUNTS.editor.password,
    });
    assert.equal(login.status, 302);
    const preview = await editor.get('/preview/draft-page');
    assert.equal(preview.status, 200);
  });

  it('redirects anonymous admin visits to the login screen with a next URL', async () => {
    const anonymous = makeClient(server.base);
    const response = await anonymous.get('/admin/pages');
    assert.equal(response.status, 302);
    assert.match(response.location, /\/admin\/login\?next=%2Fadmin%2Fpages/);
  });
});
