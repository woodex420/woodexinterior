#!/usr/bin/env node
/**
 * Seed Woodex OS with the current Woodex Interior site content.
 *
 *   npm run seed                 # insert missing content, keep edits
 *   npm run seed -- --reset      # wipe content tables first (accounts kept)
 *   WOODEX_ADMIN_EMAIL=you@x.com WOODEX_ADMIN_PASSWORD='...' npm run seed
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { openDatabase, setSetting, setJsonSetting } from '../src/db.js';
import { loadConfig } from '../src/app.js';
import { ensureSettings } from '../src/lib/settings.js';
import { hashPassword, passwordProblems } from '../src/lib/auth.js';
import { savePage, saveProject, saveInsight, saveService, listPages } from '../src/lib/content.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const seedDir = path.resolve(here, '../content/seed');
const reset = process.argv.includes('--reset');
const config = loadConfig();

const db = openDatabase(config.dbPath);
ensureSettings(db);

const read = (file) => JSON.parse(fs.readFileSync(path.join(seedDir, file), 'utf8'));

if (reset) {
  for (const table of ['page_revisions', 'pages', 'projects', 'insights', 'services', 'nav_items']) {
    db.exec(`DELETE FROM ${table};`);
  }
  console.log('· content tables cleared');
}

/* ------------------------------------------------------------------ settings */
const site = read('site.json');
for (const [key, value] of Object.entries(site.settings)) {
  const existing = db.get('SELECT value FROM settings WHERE key = ?', key);
  if (!existing || existing.value === '') setSetting(db, key, value);
}

/* ----------------------------------------------------------------------- nav */
if (db.get('SELECT COUNT(*) AS n FROM nav_items').n === 0) {
  const order = { main: 0, footer_explore: 0, footer_services: 0, footer_legal: 0 };
  for (const [area, items] of Object.entries(site.nav)) {
    items.forEach((item, index) => {
      db.run('INSERT INTO nav_items (label, href, area, sort_order, visible) VALUES (?,?,?,?,1)',
        item.label, item.href, area, order[area] + index);
    });
  }
  console.log('· navigation seeded');
}

/* --------------------------------------------------------------------- pages */
const pages = read('pages.json');
let pageCount = 0;
for (const page of pages) {
  const existing = db.get('SELECT id FROM pages WHERE slug = ?', page.slug);
  if (existing && !reset) continue;
  const saved = savePage(db, {
    ...page,
    seo_title: page.seo_title,
    seo_description: page.seo_description,
    revisionNote: 'Seeded from the v1 website',
  }, null);
  if (page.noindex) db.run('UPDATE pages SET noindex = 1 WHERE id = ?', saved.id);
  if (page.status === 'published') {
    db.run("UPDATE pages SET published_at = COALESCE(published_at, strftime('%Y-%m-%dT%H:%M:%SZ','now')) WHERE id = ?", saved.id);
  }
  pageCount += 1;
}

/* ------------------------------------------------------------------ projects */
let projectCount = 0;
for (const project of read('projects.json')) {
  if (db.get('SELECT id FROM projects WHERE slug = ?', project.slug)) continue;
  saveProject(db, { ...project, published_at: new Date().toISOString() });
  projectCount += 1;
}

/* ------------------------------------------------------------------ insights */
let insightCount = 0;
for (const insight of read('insights.json')) {
  if (db.get('SELECT id FROM insights WHERE slug = ?', insight.slug)) continue;
  saveInsight(db, insight);
  insightCount += 1;
}

/* ------------------------------------------------------------------ services */
let serviceCount = 0;
for (const service of read('services.json')) {
  if (db.get('SELECT id FROM services WHERE slug = ?', service.slug)) continue;
  saveService(db, service);
  serviceCount += 1;
}

/* --------------------------------------------------------------------- owner */
const ownerEmail = (process.env.WOODEX_ADMIN_EMAIL || 'admin@woodexinterior.pk').toLowerCase();
let ownerPassword = process.env.WOODEX_ADMIN_PASSWORD || '';
let generated = false;
if (!db.get('SELECT id FROM users WHERE email = ?', ownerEmail)) {
  if (!ownerPassword) {
    generated = true;
    ownerPassword = `Wx-${crypto.randomBytes(6).toString('base64url')}-${crypto.randomInt(1000, 9999)}`;
  }
  const problems = passwordProblems(ownerPassword);
  if (problems.length) {
    console.error('✖ WOODEX_ADMIN_PASSWORD is too weak:', problems.join(' '));
    process.exit(1);
  }
  db.run("INSERT INTO users (email, name, role, password_hash) VALUES (?,?,?,?)",
    ownerEmail, 'Studio Owner', 'owner', hashPassword(ownerPassword));
  console.log(`· owner account created: ${ownerEmail}`);
}

setJsonSetting(db, 'system.seeded_at', new Date().toISOString());

console.log('\nWoodex OS seed complete');
console.log(`  database : ${config.dbPath}`);
console.log(`  pages    : +${pageCount} (total ${listPages(db).length})`);
console.log(`  projects : +${projectCount}`);
console.log(`  insights : +${insightCount}`);
console.log(`  services : +${serviceCount}`);
console.log(`  admin    : http://localhost:${config.port}/admin`);
console.log(`  email    : ${ownerEmail}`);
if (generated) {
  console.log(`  password : ${ownerPassword}`);
  console.log('  → Store this password now, it is not shown again. Change it after first sign-in.');
} else if (process.env.WOODEX_ADMIN_PASSWORD) {
  console.log('  password : (from WOODEX_ADMIN_PASSWORD)');
} else {
  console.log('  password : unchanged (account already existed)');
}
db.close();
