import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { startTestServer } from './helpers.js';
import { exportStatic } from '../src/lib/exporter.js';

let server;
let target;
let result;
before(async () => {
  server = await startTestServer();
  target = fs.mkdtempSync(path.join(os.tmpdir(), 'woodex-export-'));
  result = await exportStatic(server.db, { ...server.config, baseUrl: 'https://woodex.com.pk' }, { targetDir: target });
});
after(async () => {
  await server.close();
  fs.rmSync(target, { recursive: true, force: true });
});

const exists = (relative) => fs.existsSync(path.join(target, relative));
const read = (relative) => fs.readFileSync(path.join(target, relative), 'utf8');

describe('Static export', () => {
  it('writes a page per route', () => {
    assert.ok(result.files > 15, `expected a full export, got ${result.files} files`);
    for (const file of ['index.html', 'about/index.html', 'portfolio/index.html', 'insights/index.html',
      'portfolio/the-arched-residence/index.html', 'insights/quiet-materials/index.html',
      'sitemap.xml', 'robots.txt', 'feed.xml', 'site.webmanifest', '404.html', '.htaccess']) {
      assert.ok(exists(file), `missing ${file}`);
    }
  });

  it('keeps the rendered SEO metadata and structured data', () => {
    const home = read('index.html');
    assert.match(home, /<title>Woodex Interior — thoughtful interiors<\/title>/);
    assert.match(home, /rel="canonical"/);
    assert.match(home, /"@type":"InteriorDesignBusiness"/);
    assert.match(home, /assets\/theme\.css/);
  });

  it('never exports drafts or unreachable URLs', () => {
    assert.ok(!exists('draft-page'), 'draft pages must stay private');
    assert.ok(!exists('portfolio/draft-project'), 'draft projects must stay private');
    const sitemap = read('sitemap.xml');
    assert.doesNotMatch(sitemap, /draft/);
    assert.doesNotMatch(sitemap, /<loc>https:\/\/woodex\.com\.pk\/home<\/loc>/, 'home is served at / only');
  });

  it('copies the design system and the upload folder', () => {
    assert.ok(exists('assets/theme.css'));
    assert.ok(exists('assets/site.js'));
    assert.ok(exists('assets/favicon.svg'));
    assert.ok(exists('uploads'));
  });

  it('ships a hardened Apache config', () => {
    const htaccess = read('.htaccess');
    assert.match(htaccess, /ErrorDocument 404 \/404\.html/);
    assert.match(htaccess, /Options -Indexes/);
    assert.match(htaccess, /Content-Security-Policy/);
    assert.match(htaccess, /ExpiresByType image\/jpeg/);
    assert.doesNotMatch(htaccess, /Options \+Indexes/);
  });

  it('documents what was exported', () => {
    const notes = read('EXPORT-README.txt');
    assert.match(notes, /Upload the contents of this folder/);
  });
});
