import { nowIso } from '../db.js';
import { conflict, notFound } from './http.js';
import { sanitizeHtml, stripTags, excerpt } from './sanitize.js';
import { normalizeBlocks } from './blocks.js';

export function parseJson(value, fallback) {
  if (value == null || value === '') return fallback;
  if (typeof value === 'object') return value;
  try { return JSON.parse(value); } catch { return fallback; }
}

const iso = () => nowIso();

/* ------------------------------------------------------------------- pages */

export function pageOut(row) {
  if (!row) return null;
  return {
    ...row,
    noindex: Boolean(row.noindex),
    blocks: parseJson(row.blocks, []),
  };
}

export function listPages(db, { status, type, q } = {}) {
  const where = [];
  const params = [];
  if (status) { where.push('status = ?'); params.push(status); }
  if (type) { where.push('page_type = ?'); params.push(type); }
  if (q) { where.push('(title LIKE ? OR slug LIKE ?)'); params.push(`%${q}%`, `%${q}%`); }
  const sql = `SELECT * FROM pages ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
               ORDER BY sort_order, id`;
  return db.all(sql, ...params).map(pageOut);
}

export function getPageBySlug(db, slug, { includeDrafts = false } = {}) {
  const row = db.get('SELECT * FROM pages WHERE slug = ?', slug);
  if (!row) return null;
  if (!includeDrafts && row.status !== 'published') return null;
  return pageOut(row);
}

export function getPublishedPages(db) {
  return db.all("SELECT * FROM pages WHERE status = 'published' ORDER BY sort_order, id").map(pageOut);
}

function assertUniqueSlug(db, table, slug, id) {
  const row = db.get(`SELECT id FROM ${table} WHERE slug = ?`, slug);
  if (row && Number(row.id) !== Number(id)) throw conflict(`The slug “${slug}” is already used.`);
}

export function savePage(db, data, user) {
  const slug = data.slug;
  assertUniqueSlug(db, 'pages', slug, data.id);
  const blocks = normalizeBlocks(data.blocks || []);
  const payload = {
    slug,
    title: data.title,
    page_type: data.page_type || 'page',
    template: data.template || 'default',
    status: data.status === 'published' ? 'published' : 'draft',
    seo_title: data.seo_title || null,
    seo_description: data.seo_description || null,
    og_image: data.og_image || null,
    canonical: data.canonical || null,
    noindex: data.noindex ? 1 : 0,
    hero_eyebrow: data.hero_eyebrow || null,
    hero_title: data.hero_title || null,
    hero_intro: data.hero_intro || null,
    hero_image: data.hero_image || null,
    blocks: JSON.stringify(blocks),
    sort_order: Number.isFinite(Number(data.sort_order)) ? Number(data.sort_order) : 0,
  };

  if (data.id) {
    const existing = db.get('SELECT * FROM pages WHERE id = ?', data.id);
    if (!existing) throw notFound('Page');
    db.run(
      `INSERT INTO page_revisions (page_id, snapshot, note, created_by, created_at)
       VALUES (?, ?, ?, ?, ?)`,
      existing.id, JSON.stringify(existing), data.revisionNote || 'Before update', user?.id ?? null, iso(),
    );
    const publishedAt = payload.status === 'published' ? existing.published_at || iso() : existing.published_at;
    db.run(
      `UPDATE pages SET slug=?, title=?, page_type=?, template=?, status=?, seo_title=?, seo_description=?,
        og_image=?, canonical=?, noindex=?, hero_eyebrow=?, hero_title=?, hero_intro=?, hero_image=?,
        blocks=?, sort_order=?, updated_at=?, published_at=?, updated_by=? WHERE id=?`,
      payload.slug, payload.title, payload.page_type, payload.template, payload.status,
      payload.seo_title, payload.seo_description, payload.og_image, payload.canonical, payload.noindex,
      payload.hero_eyebrow, payload.hero_title, payload.hero_intro, payload.hero_image,
      payload.blocks, payload.sort_order, iso(), publishedAt, user?.id ?? null, existing.id,
    );
    return getPageById(db, existing.id);
  }

  const result = db.run(
    `INSERT INTO pages (slug, title, page_type, template, status, seo_title, seo_description, og_image,
       canonical, noindex, hero_eyebrow, hero_title, hero_intro, hero_image, blocks, sort_order,
       created_at, updated_at, published_at, updated_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    payload.slug, payload.title, payload.page_type, payload.template, payload.status,
    payload.seo_title, payload.seo_description, payload.og_image, payload.canonical, payload.noindex,
    payload.hero_eyebrow, payload.hero_title, payload.hero_intro, payload.hero_image,
    payload.blocks, payload.sort_order, iso(), iso(),
    payload.status === 'published' ? iso() : null, user?.id ?? null,
  );
  return getPageById(db, result.lastInsertRowid);
}

export function getPageById(db, id) {
  return pageOut(db.get('SELECT * FROM pages WHERE id = ?', id));
}

export function deletePage(db, id) {
  const row = db.get('SELECT id, page_type FROM pages WHERE id = ?', id);
  if (!row) throw notFound('Page');
  if (row.page_type === 'system') throw conflict('System pages (404) cannot be deleted.');
  db.run('DELETE FROM pages WHERE id = ?', id);
}

export function revisionsFor(db, pageId) {
  return db.all(
    `SELECT r.id, r.note, r.created_at, u.email AS author
       FROM page_revisions r LEFT JOIN users u ON u.id = r.created_by
      WHERE r.page_id = ? ORDER BY r.id DESC LIMIT 30`,
    pageId,
  );
}

export function restoreRevision(db, pageId, revisionId, user) {
  const revision = db.get('SELECT * FROM page_revisions WHERE id = ? AND page_id = ?', revisionId, pageId);
  if (!revision) throw notFound('Revision');
  const snapshot = parseJson(revision.snapshot, {});
  const current = db.get('SELECT * FROM pages WHERE id = ?', pageId);
  if (!current) throw notFound('Page');
  db.run(
    'INSERT INTO page_revisions (page_id, snapshot, note, created_by, created_at) VALUES (?,?,?,?,?)',
    pageId, JSON.stringify(current), 'Before restore', user?.id ?? null, iso(),
  );
  db.run(
    `UPDATE pages SET slug=?, title=?, seo_title=?, seo_description=?, og_image=?, canonical=?, noindex=?,
       hero_eyebrow=?, hero_title=?, hero_intro=?, hero_image=?, blocks=?, status=?, updated_at=?, updated_by=?
     WHERE id=?`,
    snapshot.slug, snapshot.title, snapshot.seo_title, snapshot.seo_description, snapshot.og_image,
    snapshot.canonical, snapshot.noindex, snapshot.hero_eyebrow, snapshot.hero_title,
    snapshot.hero_intro, snapshot.hero_image, snapshot.blocks, snapshot.status, iso(), user?.id ?? null, pageId,
  );
  return getPageById(db, pageId);
}

/* ---------------------------------------------------------------- projects */

const projectOut = (row) => row && ({
  ...row,
  featured: Boolean(row.featured),
  gallery: parseJson(row.gallery, []),
  facts: parseJson(row.facts, []),
});

export function listProjects(db, { status, category, featured, limit, offset, q } = {}) {
  const where = [];
  const params = [];
  if (status) { where.push('status = ?'); params.push(status); }
  if (category && category !== 'all') { where.push('category = ?'); params.push(category); }
  if (featured != null) { where.push('featured = ?'); params.push(featured ? 1 : 0); }
  if (q) { where.push('(title LIKE ? OR summary LIKE ?)'); params.push(`%${q}%`, `%${q}%`); }
  const clause = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const total = db.get(`SELECT COUNT(*) AS n FROM projects ${clause}`, ...params).n;
  const rows = db.all(
    `SELECT * FROM projects ${clause} ORDER BY sort_order, id LIMIT ? OFFSET ?`,
    ...params, limit ?? 100, offset ?? 0,
  ).map(projectOut);
  return { rows, total };
}

export const getProjectBySlug = (db, slug, { includeDrafts = false } = {}) => {
  const row = db.get('SELECT * FROM projects WHERE slug = ?', slug);
  if (!row || (!includeDrafts && row.status !== 'published')) return null;
  return projectOut(row);
};
export const getProjectById = (db, id) => projectOut(db.get('SELECT * FROM projects WHERE id = ?', id));

export function saveProject(db, data) {
  assertUniqueSlug(db, 'projects', data.slug, data.id);
  const fields = {
    slug: data.slug,
    title: data.title,
    category: data.category || 'residential',
    location: data.location || null,
    year: data.year || null,
    area: data.area || null,
    summary: data.summary || null,
    body: data.body ? sanitizeHtml(data.body) : null,
    cover_image: data.cover_image || null,
    gallery: JSON.stringify(Array.isArray(data.gallery) ? data.gallery.slice(0, 20) : []),
    facts: JSON.stringify(Array.isArray(data.facts) ? data.facts.slice(0, 12) : []),
    featured: data.featured ? 1 : 0,
    sort_order: Number(data.sort_order) || 0,
    status: data.status === 'published' ? 'published' : 'draft',
    seo_title: data.seo_title || null,
    seo_description: data.seo_description || null,
  };
  if (data.id) {
    const existing = getProjectById(db, data.id);
    if (!existing) throw notFound('Project');
    db.run(
      `UPDATE projects SET slug=?, title=?, category=?, location=?, year=?, area=?, summary=?, body=?,
         cover_image=?, gallery=?, facts=?, featured=?, sort_order=?, status=?, seo_title=?, seo_description=?,
         updated_at=?, published_at=? WHERE id=?`,
      fields.slug, fields.title, fields.category, fields.location, fields.year, fields.area, fields.summary,
      fields.body, fields.cover_image, fields.gallery, fields.facts, fields.featured, fields.sort_order,
      fields.status, fields.seo_title, fields.seo_description, iso(),
      fields.status === 'published' ? existing.published_at || iso() : existing.published_at, existing.id,
    );
    return getProjectById(db, existing.id);
  }
  const res = db.run(
    `INSERT INTO projects (slug,title,category,location,year,area,summary,body,cover_image,gallery,facts,
       featured,sort_order,status,seo_title,seo_description,created_at,updated_at,published_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    fields.slug, fields.title, fields.category, fields.location, fields.year, fields.area, fields.summary,
    fields.body, fields.cover_image, fields.gallery, fields.facts, fields.featured, fields.sort_order,
    fields.status, fields.seo_title, fields.seo_description, iso(), iso(),
    fields.status === 'published' ? iso() : null,
  );
  return getProjectById(db, res.lastInsertRowid);
}

export function deleteProject(db, id) {
  const res = db.run('DELETE FROM projects WHERE id = ?', id);
  if (!res.changes) throw notFound('Project');
}

/* ---------------------------------------------------------------- insights */

export const insightOut = (row) => row && ({ ...row, featured: Boolean(row.featured) });

export function listInsights(db, { status, category, limit, offset, q } = {}) {
  const where = [];
  const params = [];
  if (status) { where.push('status = ?'); params.push(status); }
  if (category) { where.push('category = ?'); params.push(category); }
  if (q) { where.push('(title LIKE ? OR excerpt LIKE ?)'); params.push(`%${q}%`, `%${q}%`); }
  const clause = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const total = db.get(`SELECT COUNT(*) AS n FROM insights ${clause}`, ...params).n;
  const rows = db.all(
    `SELECT * FROM insights ${clause} ORDER BY COALESCE(published_at, created_at) DESC LIMIT ? OFFSET ?`,
    ...params, limit ?? 100, offset ?? 0,
  ).map(insightOut);
  return { rows, total };
}

export const getInsightBySlug = (db, slug, { includeDrafts = false } = {}) => {
  const row = db.get('SELECT * FROM insights WHERE slug = ?', slug);
  if (!row || (!includeDrafts && row.status !== 'published')) return null;
  return insightOut(row);
};
export const getInsightById = (db, id) => insightOut(db.get('SELECT * FROM insights WHERE id = ?', id));

export function saveInsight(db, data) {
  assertUniqueSlug(db, 'insights', data.slug, data.id);
  const f = {
    slug: data.slug,
    title: data.title,
    category: data.category || 'Design notes',
    reading_time: data.reading_time || '4 min read',
    excerpt: data.excerpt || excerpt(data.body || ''),
    body: data.body ? sanitizeHtml(data.body) : null,
    cover_image: data.cover_image || null,
    author: data.author || 'Woodex Studio',
    featured: data.featured ? 1 : 0,
    status: data.status === 'published' ? 'published' : 'draft',
    published_at: data.published_at || (data.status === 'published' ? iso() : null),
    seo_title: data.seo_title || null,
    seo_description: data.seo_description || null,
  };
  if (data.id) {
    const existing = getInsightById(db, data.id);
    if (!existing) throw notFound('Insight');
    db.run(
      `UPDATE insights SET slug=?, title=?, category=?, reading_time=?, excerpt=?, body=?, cover_image=?,
         author=?, featured=?, status=?, published_at=?, seo_title=?, seo_description=?, updated_at=? WHERE id=?`,
      f.slug, f.title, f.category, f.reading_time, f.excerpt, f.body, f.cover_image, f.author, f.featured,
      f.status, f.published_at, f.seo_title, f.seo_description, iso(), existing.id,
    );
    return getInsightById(db, existing.id);
  }
  const res = db.run(
    `INSERT INTO insights (slug,title,category,reading_time,excerpt,body,cover_image,author,featured,status,
       published_at,seo_title,seo_description,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    f.slug, f.title, f.category, f.reading_time, f.excerpt, f.body, f.cover_image, f.author, f.featured,
    f.status, f.published_at, f.seo_title, f.seo_description, iso(), iso(),
  );
  return getInsightById(db, res.lastInsertRowid);
}

export function deleteInsight(db, id) {
  const res = db.run('DELETE FROM insights WHERE id = ?', id);
  if (!res.changes) throw notFound('Insight');
}

/* ---------------------------------------------------------------- services */

export const serviceOut = (row) => row && ({ ...row, deliverables: parseJson(row.deliverables, []) });

export function listServices(db, { status } = {}) {
  const where = status ? 'WHERE status = ?' : '';
  const params = status ? [status] : [];
  return db.all(`SELECT * FROM services ${where} ORDER BY sort_order, id`, ...params).map(serviceOut);
}

export const getServiceBySlug = (db, slug, { includeDrafts = false } = {}) => {
  const row = db.get('SELECT * FROM services WHERE slug = ?', slug);
  if (!row || (!includeDrafts && row.status !== 'published')) return null;
  return serviceOut(row);
};
export const getServiceById = (db, id) => serviceOut(db.get('SELECT * FROM services WHERE id = ?', id));

export function saveService(db, data) {
  assertUniqueSlug(db, 'services', data.slug, data.id);
  const f = {
    slug: data.slug,
    title: data.title,
    number: data.number || null,
    summary: data.summary || null,
    body: data.body ? sanitizeHtml(data.body) : null,
    icon: data.icon || null,
    deliverables: JSON.stringify(Array.isArray(data.deliverables) ? data.deliverables.slice(0, 20) : []),
    sort_order: Number(data.sort_order) || 0,
    status: data.status === 'published' ? 'published' : 'draft',
    seo_title: data.seo_title || null,
    seo_description: data.seo_description || null,
  };
  if (data.id) {
    const existing = getServiceById(db, data.id);
    if (!existing) throw notFound('Service');
    db.run(
      `UPDATE services SET slug=?, title=?, number=?, summary=?, body=?, icon=?, deliverables=?,
         sort_order=?, status=?, seo_title=?, seo_description=?, updated_at=? WHERE id=?`,
      f.slug, f.title, f.number, f.summary, f.body, f.icon, f.deliverables, f.sort_order, f.status,
      f.seo_title, f.seo_description, iso(), existing.id,
    );
    return getServiceById(db, existing.id);
  }
  const res = db.run(
    `INSERT INTO services (slug,title,number,summary,body,icon,deliverables,sort_order,status,seo_title,
       seo_description,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    f.slug, f.title, f.number, f.summary, f.body, f.icon, f.deliverables, f.sort_order, f.status,
    f.seo_title, f.seo_description, iso(), iso(),
  );
  return getServiceById(db, res.lastInsertRowid);
}

export function deleteService(db, id) {
  const res = db.run('DELETE FROM services WHERE id = ?', id);
  if (!res.changes) throw notFound('Service');
}

/* --------------------------------------------------------------- enquiries */

export function createEnquiry(db, data, { ip, userAgent, source = 'website' } = {}) {
  const res = db.run(
    `INSERT INTO enquiries (name,email,phone,location,service,timeline,message,source,ip,user_agent,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)`,
    data.name, data.email, data.phone || null, data.location || null, data.service || null,
    data.timeline || null, data.message, source, ip || null, (userAgent || '').slice(0, 250), iso(), iso(),
  );
  return db.get('SELECT * FROM enquiries WHERE id = ?', res.lastInsertRowid);
}

export function listEnquiries(db, { status, q, limit = 50, offset = 0 } = {}) {
  const where = [];
  const params = [];
  if (status && status !== 'all') { where.push('status = ?'); params.push(status); }
  if (q) { where.push('(name LIKE ? OR email LIKE ? OR message LIKE ?)'); params.push(`%${q}%`, `%${q}%`, `%${q}%`); }
  const clause = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const total = db.get(`SELECT COUNT(*) AS n FROM enquiries ${clause}`, ...params).n;
  const rows = db.all(
    `SELECT * FROM enquiries ${clause} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`,
    ...params, limit, offset,
  );
  return { rows, total };
}

/* ------------------------------------------------------------ content health */

export function contentHealth(db, config) {
  const issues = [];
  const pages = db.all('SELECT * FROM pages').map(pageOut);
  for (const page of pages) {
    if (!page.seo_description) {
      issues.push({ severity: 'warning', area: 'SEO', entity: 'page', id: page.id, slug: page.slug, message: 'Missing meta description.' });
    }
    if (!page.seo_title) {
      issues.push({ severity: 'info', area: 'SEO', entity: 'page', id: page.id, slug: page.slug, message: 'Missing SEO title (falls back to page title).' });
    }
    if (page.status === 'draft') {
      issues.push({ severity: 'info', area: 'Publishing', entity: 'page', id: page.id, slug: page.slug, message: 'Draft is not live.' });
    }
    const html = JSON.stringify(page.blocks);
    if (/<img(?![^>]*\balt=)/.test(html)) {
      issues.push({ severity: 'warning', area: 'Accessibility', entity: 'page', id: page.id, slug: page.slug, message: 'An image is missing alt text.' });
    }
  }
  const mediaNoAlt = db.all("SELECT id, filename FROM media WHERE alt IS NULL OR alt = ''");
  if (mediaNoAlt.length) {
    issues.push({
      severity: 'warning', area: 'Accessibility', entity: 'media', id: null, slug: null,
      message: `${mediaNoAlt.length} media file(s) missing alt text.`,
    });
  }
  const projectNoSeo = db.get("SELECT COUNT(*) AS n FROM projects WHERE status='published' AND (seo_description IS NULL OR seo_description='')").n;
  if (projectNoSeo) issues.push({ severity: 'warning', area: 'SEO', entity: 'project', message: `${projectNoSeo} published project(s) without meta description.` });
  const insightNoSeo = db.get("SELECT COUNT(*) AS n FROM insights WHERE status='published' AND (seo_description IS NULL OR seo_description='')").n;
  if (insightNoSeo) issues.push({ severity: 'warning', area: 'SEO', entity: 'insight', message: `${insightNoSeo} published insight(s) without meta description.` });
  const dupes = db.all(
    `SELECT seo_description AS d, COUNT(*) AS n FROM pages
      WHERE seo_description IS NOT NULL AND seo_description != ''
      GROUP BY seo_description HAVING n > 1`,
  );
  if (dupes.length) {
    issues.push({ severity: 'warning', area: 'SEO', entity: 'page', message: `${dupes.length} duplicate meta description(s) across pages.` });
  }
  if (!config['seo.default_og_image']) {
    issues.push({ severity: 'info', area: 'SEO', entity: 'setting', message: 'No default social share image configured.' });
  }
  return issues;
}

export function sitemapEntries(db, baseUrl) {
  const entries = [{ loc: '/', priority: '1.0', changefreq: 'weekly' }];
  for (const page of getPublishedPages(db)) {
    if (page.noindex) continue;
    if (['404', 'style-guide'].includes(page.slug)) continue;
    if (page.page_type === 'home' || page.slug === 'home') continue; // served at "/" only
    entries.push({ loc: `/${page.slug}`, priority: page.page_type === 'home' ? '1.0' : '0.8', changefreq: 'monthly' });
  }
  for (const p of listProjects(db, { status: 'published', limit: 500 }).rows) {
    entries.push({ loc: `/portfolio/${p.slug}`, priority: '0.7', changefreq: 'monthly' });
  }
  for (const i of listInsights(db, { status: 'published', limit: 500 }).rows) {
    entries.push({ loc: `/insights/${i.slug}`, priority: '0.7', changefreq: 'monthly' });
  }
  const seen = new Set();
  return entries.filter((e) => {
    const key = e.loc === '/' ? '/' : e.loc.replace(/\/$/, '');
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  }).map((e) => ({ ...e, url: `${baseUrl}${e.loc}`.replace(/\/$/, '/') }));
}

export function dashboardStats(db) {
  const count = (sql) => db.get(sql).n;
  return {
    pages: count('SELECT COUNT(*) AS n FROM pages'),
    publishedPages: count("SELECT COUNT(*) AS n FROM pages WHERE status='published'"),
    projects: count('SELECT COUNT(*) AS n FROM projects'),
    publishedProjects: count("SELECT COUNT(*) AS n FROM projects WHERE status='published'"),
    insights: count('SELECT COUNT(*) AS n FROM insights'),
    publishedInsights: count("SELECT COUNT(*) AS n FROM insights WHERE status='published'"),
    services: count('SELECT COUNT(*) AS n FROM services'),
    media: count('SELECT COUNT(*) AS n FROM media'),
    enquiries: count('SELECT COUNT(*) AS n FROM enquiries'),
    newEnquiries: count("SELECT COUNT(*) AS n FROM enquiries WHERE status='new'"),
    users: count('SELECT COUNT(*) AS n FROM users'),
    drafts: count("SELECT COUNT(*) AS n FROM pages WHERE status='draft'"),
  };
}

export function searchContent(db, term) {
  const like = `%${term}%`;
  return {
    pages: db.all('SELECT id, slug, title, status FROM pages WHERE title LIKE ? OR slug LIKE ? LIMIT 10', like, like),
    projects: db.all('SELECT id, slug, title, status FROM projects WHERE title LIKE ? OR slug LIKE ? LIMIT 10', like, like),
    insights: db.all('SELECT id, slug, title, status FROM insights WHERE title LIKE ? OR slug LIKE ? LIMIT 10', like, like),
    services: db.all('SELECT id, slug, title, status FROM services WHERE title LIKE ? OR slug LIKE ? LIMIT 10', like, like),
  };
}

export { stripTags, excerpt };
