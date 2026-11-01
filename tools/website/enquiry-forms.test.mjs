import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const publicRoot = path.join(root, 'platform/public');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const walk = dir => fs.readdirSync(dir, {withFileTypes: true}).flatMap(entry => {
  if (entry.name === 'builder') return [];
  const file = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(file) : entry.name === 'index.html' ? [file] : [];
});
const pages = walk(publicRoot);
const attrs = tag => Object.fromEntries([...tag.matchAll(/([\w-]+)\s*=\s*["']([^"']*)["']/g)].map(m => [m[1], m[2]]));

test('all existing public enquiry forms have explicit private native POST transport', () => {
  let forms = 0;
  for (const file of pages) {
    const html = fs.readFileSync(file, 'utf8');
    for (const match of html.matchAll(/<form\b[^>]*>[\s\S]*?<\/form>/g)) {
      forms++;
      const opening = attrs(match[0].slice(0, match[0].indexOf('>') + 1));
      assert.equal(opening.method, 'post', file);
      assert.equal(opening.action, '/api/forms.php', file);
      assert.equal(opening.enctype, 'application/x-www-form-urlencoded', file);
      const inputs = [...match[0].matchAll(/<input\b[^>]*>/g)].map(m => attrs(m[0]));
      for (const name of ['form', 'page', '_response']) assert.equal(inputs.filter(i => i.name === name).length, 1, file + ' ' + name);
      assert.equal(inputs.find(i => i.name === '_response').value, 'html');
      assert(inputs.some(i => ['_hp', 'company_hp'].includes(i.name)), file);
    }
  }
  assert.equal(pages.length, 142);
  assert.equal(forms, 63);
});

test('all local fragment links in existing pages have actual source targets', () => {
  const urls = new Map(pages.map(file => ['/' + path.relative(publicRoot, file).replaceAll(path.sep, '/').replace(/index\.html$/, ''), fs.readFileSync(file, 'utf8')]));
  for (const [url, html] of urls) {
    for (const match of html.matchAll(/href=["']([^"']+)["']/g)) {
      const target = new URL(match[1], 'https://woodex.com.pk' + url);
      if (target.hostname !== 'woodex.com.pk' || !target.hash || !urls.has(target.pathname)) continue;
      const ids = [...urls.get(target.pathname).matchAll(/\bid=["']([^"']+)["']/g)].map(m => m[1]);
      assert(ids.includes(decodeURIComponent(target.hash.slice(1))), url + ' -> ' + match[1]);
    }
  }
});

test('every existing page requests the changed shared helper with a fresh cache version', () => {
  for (const file of pages) {
    const html = fs.readFileSync(file, 'utf8');
    assert(html.includes('/assets/site.js?v=enquiry-20261101'), file);
    assert(!html.includes('/assets/site.js?v=p21'), file);
  }
});

test('estimator fails closed without its dependencies and uses a real serialized snapshot', () => {
  const html = read('platform/public/estimator/index.html');
  assert.match(html, /id="est-submit" disabled/);
  assert.match(html, /id="est-result"[^>]*hidden/);
  assert.match(html, /id="est-unavailable"/);
  for (const name of ['service', 'area', 'finish', 'estimate']) assert.match(html, new RegExp('type="hidden" name="' + name + '"'));
  assert(html.includes('/assets/js/estimator-page.js?v=enquiry-20261101'));
  const script = read('platform/public/assets/js/estimator-page.js');
  new vm.Script(script);
  assert(script.includes('form.reportValidity()'));
  assert(script.includes('pending'));
  assert(!script.includes('localStorage') && !script.includes('sessionStorage'));
});

test('published rate book still supplies finite explicit rates, not inferred prices', () => {
  const context = {window: {}};
  vm.runInNewContext(read('platform/public/assets/js/estimator-rates.js'), context);
  const rates = context.window.WX_RATES;
  assert(rates.services.length > 0 && rates.finishes.length > 0);
  for (const service of rates.services) for (const finish of rates.finishes) assert(Number.isFinite(service.rates[finish.key]));
});

test('live theme endpoint reaches the app even when an old physical CSS file survives deployment',()=>{
  const rewrite=read('platform/public/.htaccess');
  const themeRule='RewriteRule ^assets/theme\\.css$ index.php [L]';
  assert(rewrite.includes(themeRule));
  assert(rewrite.indexOf(themeRule)<rewrite.lastIndexOf('RewriteCond %{REQUEST_FILENAME} !-f'));
  assert.match(read('platform/routes/web.php'), /Route::get\('\/assets\/theme\.css'/);
  for(const file of pages) assert(fs.readFileSync(file,'utf8').includes('/assets/theme.css?v=enquiry-20261101'),file);
});
