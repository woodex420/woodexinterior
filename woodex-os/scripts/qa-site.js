#!/usr/bin/env node
/**
 * Woodex site QA crawler.
 *
 * Checks every page of a running site (or a folder of static HTML) for the
 * things that actually break a launch: HTTP errors, broken internal links,
 * duplicate/missing SEO metadata, invalid heading outlines, images without
 * alt text or dimensions, buttons without a type, duplicate ids, missing form
 * labels, accessibility basics, and response security headers.
 *
 * Usage:
 *   node scripts/qa.js --base http://localhost:4321
 *   node scripts/qa.js --dir ../woodex --label "v1 static site"
 *   node scripts/qa.js --base http://localhost:4321 --json docs/qa-os.json --md docs/qa-os.md
 *
 * Exit code 1 when any error-level issue is found, so it can gate a deploy.
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const arg = (name, fallback = '') => {
  const index = process.argv.indexOf(`--${name}`);
  return index === -1 ? fallback : process.argv[index + 1] ?? fallback;
};
const label = arg('label', 'site');
const jsonOut = arg('json', '');
const mdOut = arg('md', '');
const base = arg('base', '');
const dir = arg('dir', '');
const maxPages = Number(arg('max', '60'));

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.avif': 'image/avif',
  '.woff2': 'font/woff2',
  '.otf': 'font/otf',
  '.ico': 'image/x-icon',
};

async function serveFolder(folder) {
  const root = path.resolve(folder);
  const server = http.createServer((req, res) => {
    const url = decodeURIComponent((req.url || '/').split('?')[0]);
    let filePath = path.join(root, url);
    if (!filePath.startsWith(root)) { res.writeHead(403).end('forbidden'); return; }
    if (fs.existsSync(filePath) && fs.statSync(filePath).isDirectory()) filePath = path.join(filePath, 'index.html');
    if (!fs.existsSync(filePath)) { res.writeHead(404, { 'content-type': 'text/html' }).end('<h1>Not found</h1>'); return; }
    res.writeHead(200, { 'content-type': MIME[path.extname(filePath).toLowerCase()] || 'application/octet-stream' });
    fs.createReadStream(filePath).pipe(res);
  });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  return { server, origin: `http://127.0.0.1:${server.address().port}` };
}

const decode = (value = '') => value
  .replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#34;/g, '"')
  .replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ');

const attr = (tag, name) => {
  const match = new RegExp(`${name}\\s*=\\s*("([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i').exec(tag);
  if (!match) return null;
  return decode(match[2] ?? match[3] ?? match[4] ?? '');
};

const stripTags = (html) => decode(html.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim());

function analysePage(url, status, html, headers) {
  const issues = [];
  const add = (level, rule, message, detail) => issues.push({ level, rule, message, ...(detail ? { detail } : {}) });

  const titleMatch = /<title[^>]*>([\s\S]*?)<\/title>/i.exec(html);
  const title = titleMatch ? stripTags(titleMatch[1]) : '';
  const metas = [...html.matchAll(/<meta\b[^>]*>/gi)].map((m) => m[0]);
  const metaByName = {};
  for (const tag of metas) {
    const name = attr(tag, 'name');
    if (name) metaByName[name.toLowerCase()] = attr(tag, 'content');
  }
  const metaByProperty = {};
  for (const tag of metas) {
    const property = attr(tag, 'property');
    if (property) metaByProperty[property.toLowerCase()] = attr(tag, 'content');
  }

  if (!title) add('error', 'title', 'Missing <title>.');
  else if (title.length > 60) add('warn', 'title-length', `Title is ${title.length} characters (aim for ≤ 60).`, title);
  const description = metaByName.description || '';
  if (!description) add('error', 'description', 'Missing meta description.');
  else if (description.length < 50 || description.length > 165) {
    add('warn', 'description-length', `Description is ${description.length} characters (aim for 50–165).`, description);
  }
  const canonical = (/<link[^>]+rel=["']canonical["'][^>]*>/i.exec(html) || [])[0];
  if (!canonical) add('error', 'canonical', 'Missing canonical link.');
  else if (!attr(canonical, 'href')) add('error', 'canonical', 'Canonical link has no href.');
  if (!metaByProperty['og:title']) add('error', 'og', 'Missing og:title.');
  if (!metaByProperty['og:description']) add('error', 'og', 'Missing og:description.');
  if (!metaByProperty['og:image']) add('error', 'og', 'Missing og:image.');
  if (!metaByProperty['og:url']) add('warn', 'og', 'Missing og:url.');
  if (!metaByProperty['og:type']) add('warn', 'og', 'Missing og:type.');
  if (!metaByName['twitter:card']) add('warn', 'twitter', 'Missing twitter:card.');
  const jsonLd = [...html.matchAll(/<script[^>]+application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi)];
  if (!jsonLd.length) add('warn', 'json-ld', 'No structured data (JSON-LD).');
  for (const block of jsonLd) {
    try { JSON.parse(block[1]); } catch { add('error', 'json-ld', 'JSON-LD block is not valid JSON.'); }
  }

  const htmlTag = (/<html\b[^>]*>/i.exec(html) || [])[0] || '';
  if (!attr(htmlTag, 'lang')) add('error', 'lang', '<html> is missing lang.');
  if (!/<meta[^>]+name=["']viewport["']/i.test(html)) add('error', 'viewport', 'Missing viewport meta tag.');
  if (!/<a[^>]+class=["'][^"']*skip-link/i.test(html) && !/<a[^>]+href=["']#main["']/i.test(html)) {
    add('warn', 'skip-link', 'No skip-to-content link.');
  }

  const headings = [...html.matchAll(/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi)]
    .map((m) => ({ level: Number(m[1]), text: stripTags(m[2]) }));
  const h1s = headings.filter((h) => h.level === 1);
  if (h1s.length !== 1) add('error', 'h1', `Found ${h1s.length} <h1> elements (expected exactly 1).`);
  for (let i = 1; i < headings.length; i += 1) {
    if (headings[i].level - headings[i - 1].level > 1) {
      add('error', 'heading-order', `Heading jumps from h${headings[i - 1].level} to h${headings[i].level} ("${headings[i].text}").`);
    }
  }

  const images = [...html.matchAll(/<img\b[^>]*>/gi)].map((m) => m[0]);
  images.forEach((tag, index) => {
    const alt = attr(tag, 'alt');
    const src = attr(tag, 'src') || '';
    if (alt === null) add('error', 'img-alt', `Image ${index + 1} has no alt attribute.`, src);
    if (!attr(tag, 'width') || !attr(tag, 'height')) add('warn', 'img-dimensions', 'Image missing width/height (causes layout shift).', src);
    if (!attr(tag, 'loading') && index > 0) add('info', 'img-lazy', 'Image not lazy-loaded.', src);
  });

  const buttons = [...html.matchAll(/<button\b[^>]*>/gi)].map((m) => m[0]);
  buttons.forEach((tag) => { if (!attr(tag, 'type')) add('warn', 'button-type', '<button> without an explicit type.', stripTags(tag)); });

  const ids = [...html.matchAll(/\sid\s*=\s*["']([^"']+)["']/gi)].map((m) => m[1]);
  const seen = new Set();
  for (const id of ids) {
    if (seen.has(id)) add('error', 'duplicate-id', `Duplicate id "${id}".`);
    seen.add(id);
  }

  // Controls wrapped in a <label> are labelled implicitly; strip those first.
  const withoutWrappedLabels = html.replace(/<label\b[\s\S]*?<\/label>/gi, '');
  const inputs = [...withoutWrappedLabels.matchAll(/<(input|select|textarea)\b[^>]*>/gi)].map((m) => m[0]);
  let uncontrolled = 0;
  for (const tag of inputs) {
    const type = (attr(tag, 'type') || 'text').toLowerCase();
    if (['hidden', 'submit', 'button', 'reset', 'image'].includes(type)) continue;
    const id = attr(tag, 'id');
    const labelled = id && new RegExp(`for\\s*=\\s*["']${id}["']`, 'i').test(html);
    if (!labelled && !attr(tag, 'aria-label') && !attr(tag, 'aria-labelledby') && !/aria-hidden=["']true["']/i.test(tag)) uncontrolled += 1;
  }
  if (uncontrolled) add('error', 'form-label', `${uncontrolled} form control(s) without a label.`);

  if (/href\s*=\s*["']javascript:/i.test(html)) add('error', 'xss', 'Inline javascript: links found.');
  if (/<img[^>]+onerror=/i.test(html)) add('error', 'xss', 'Inline event handler found in markup.');
  if (/<form[^>]*action=["']["']/i.test(html)) add('warn', 'form-action', 'Form has an empty action attribute.');

  const headersToCheck = ['content-security-policy', 'x-content-type-options', 'referrer-policy', 'x-frame-options'];
  if (headers) {
    const missing = headersToCheck.filter((name) => !headers.get(name));
    if (missing.length) add('warn', 'headers', `Missing security headers: ${missing.join(', ')}.`);
    if (headers.get('x-powered-by')) add('warn', 'headers', 'X-Powered-By header leaks the framework.');
    if (!/text\/html/.test(headers.get('content-type') || '')) add('error', 'content-type', 'Page is not served as text/html.');
  }

  return {
    url,
    status,
    bytes: Buffer.byteLength(html),
    title,
    description,
    h1: h1s[0] ? h1s[0].text : '',
    headings: headings.length,
    images: images.length,
    internalLinks: [...new Set([...html.matchAll(/href=["'](\/[^"'#?]*)/g)].map((m) => m[1]))],
    anchors: [...html.matchAll(/href=["']#([^"']+)["']/g)].map((m) => m[1]),
    issues,
  };
}

function markdown(report) {
  const lines = [];
  lines.push(`# Site QA — ${report.label}`, '');
  lines.push(`- Base: \`${report.base}\``);
  lines.push(`- Run: ${report.generatedAt}`);
  lines.push(`- Pages crawled: ${report.pages.length}`);
  lines.push(`- Errors: **${report.counts.error}** · Warnings: **${report.counts.warn}** · Info: ${report.counts.info}`, '');
  lines.push('## Pages', '');
  lines.push('| Page | Status | Size | Title | Description | H1 | Issues (E/W) |');
  lines.push('| --- | --- | --- | --- | --- | --- | --- |');
  for (const page of report.pages) {
    const errors = page.issues.filter((i) => i.level === 'error').length;
    const warns = page.issues.filter((i) => i.level === 'warn').length;
    lines.push(`| \`${page.url}\` | ${page.status} | ${(page.bytes / 1024).toFixed(1)} kB | ${page.title.replace(/\|/g, '\\|')} | ${page.description.length ? `${page.description.length} ch` : '—'} | ${page.h1.replace(/\|/g, '\\|') || '—'} | ${errors}/${warns} |`);
  }
  lines.push('', '## Rules', '');
  const rules = new Map();
  for (const page of report.pages) {
    for (const issue of page.issues) {
      const key = `${issue.level}:${issue.rule}`;
      if (!rules.has(key)) rules.set(key, { ...issue, pages: [] });
      rules.get(key).pages.push(page.url);
    }
  }
  const sorted = [...rules.values()].sort((a, b) => (a.level === b.level ? b.pages.length - a.pages.length : a.level === 'error' ? -1 : 1));
  lines.push('| Level | Rule | Pages | Detail |');
  lines.push('| --- | --- | --- | --- |');
  for (const rule of sorted) {
    lines.push(`| ${rule.level} | ${rule.rule} | ${rule.pages.length} | ${rule.message.replace(/\|/g, '\\|')} |`);
  }
  lines.push('', '## Broken links', '');
  if (!report.broken.length) lines.push('None found.');
  else report.broken.forEach((link) => lines.push(`- \`${link.from}\` → \`${link.href}\` (${link.status})`));
  lines.push('', '## Duplicate metadata', '');
  const byDescription = new Map();
  for (const page of report.pages) {
    if (!page.description) continue;
    if (!byDescription.has(page.description)) byDescription.set(page.description, []);
    byDescription.get(page.description).push(page.url);
  }
  const duplicates = [...byDescription.entries()].filter(([, pages]) => pages.length > 1);
  if (!duplicates.length) lines.push('No duplicate meta descriptions.');
  duplicates.forEach(([description, pages]) => lines.push(`- ${pages.length} pages share “${description}”: ${pages.join(', ')}`));
  lines.push('');
  return lines.join('\n');
}

async function main() {
  let server = null;
  let origin = base;
  if (!origin) {
    if (!dir) throw new Error('Pass --base <url> or --dir <folder>.');
    const local = await serveFolder(dir);
    server = local.server;
    origin = local.origin;
  }
  const originUrl = new URL(origin);

  const fetchPage = async (urlPath) => {
    const response = await fetch(new URL(urlPath, origin), { redirect: 'manual', headers: { 'user-agent': 'woodex-qa/1.0' } });
    const html = await response.text();
    // Header checks only make sense against a real server; a folder is expected
    // to inherit them from .htaccess once deployed.
    return { response, html, headers: server ? null : response.headers };
  };

  const sitemap = await fetchPage('/sitemap.xml');
  const pagePaths = [...sitemap.html.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => {
    const url = new URL(decode(m[1]));
    return url.pathname || '/';
  });
  const queue = [...new Set(['/', ...pagePaths])].slice(0, maxPages);

  const visited = new Set();
  const pages = [];
  const broken = [];
  while (queue.length) {
    const urlPath = queue.shift();
    if (visited.has(urlPath)) continue;
    visited.add(urlPath);
    const { response, html, headers } = await fetchPage(urlPath);
    const page = analysePage(urlPath, response.status, html, headers);
    pages.push(page);
    if (response.status >= 400) {
      broken.push({ from: '(sitemap crawl)', href: urlPath, status: response.status });
      continue;
    }
    for (const link of page.internalLinks) {
      if (visited.has(link) || queue.includes(link)) continue;
      if (/\.(jpg|jpeg|png|webp|avif|svg|pdf|xml|txt|css|js|woff2?|otf|ico)$/i.test(link)) continue;
      const head = await fetch(new URL(link, origin), { redirect: 'manual', headers: { 'user-agent': 'woodex-qa/1.0' } });
      if (head.status >= 400) broken.push({ from: urlPath, href: link, status: head.status });
      else if (head.status < 300 && head.status !== 200) queue.push(link);
      else if ((head.headers.get('content-type') || '').includes('text/html')) queue.push(link);
    }
  }

  // Assets referenced by the pages should resolve too.
  for (const urlPath of ['/assets/theme.css', '/assets/site.js', '/assets/favicon.svg']) {
    const head = await fetch(new URL(urlPath, origin), { redirect: 'manual' });
    if (head.status >= 400) broken.push({ from: '(assets)', href: urlPath, status: head.status });
  }

  const counts = { error: 0, warn: 0, info: 0 };
  for (const page of pages) for (const issue of page.issues) counts[issue.level] += 1;

  const report = {
    label,
    base: origin,
    generatedAt: new Date().toISOString(),
    pages,
    broken,
    counts,
  };

  if (jsonOut) {
    fs.mkdirSync(path.dirname(jsonOut), { recursive: true });
    fs.writeFileSync(jsonOut, `${JSON.stringify(report, null, 2)}\n`);
  }
  if (mdOut) {
    fs.mkdirSync(path.dirname(mdOut), { recursive: true });
    fs.writeFileSync(mdOut, markdown(report));
  }

  const table = pages.map((page) => ({
    page: page.url,
    status: page.status,
    kb: (page.bytes / 1024).toFixed(1),
    title: page.title.slice(0, 52),
    desc: page.description.length,
    h1s: (page.title, page.issues.filter((i) => i.rule === 'h1').length ? 'bad' : 'ok'),
    errors: page.issues.filter((i) => i.level === 'error').length,
    warns: page.issues.filter((i) => i.level === 'warn').length,
  }));
  console.log(`\nQA · ${label} · ${origin}`);
  console.table(table);
  if (broken.length) console.log('Broken links:', broken);
  console.log(`Errors: ${counts.error} · Warnings: ${counts.warn} · Info: ${counts.info}`);
  const ruleSummary = {};
  for (const page of pages) for (const issue of page.issues) ruleSummary[`${issue.level}:${issue.rule}`] = (ruleSummary[`${issue.level}:${issue.rule}`] || 0) + 1;
  console.log('Rules:', ruleSummary);

  if (server) server.close();
  process.exitCode = counts.error > 0 || broken.length ? 1 : 0;
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
