import express from 'express';
import multer from 'multer';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import {
  ApiError, asyncHandler, sendError, notFound, forbidden, unauthorized, conflict, invalid,
  pagination, paged, vString, vEmail, vSlug, vEnum, vBool, vInt, toCsv,
} from '../lib/http.js';
import {
  attachAuth, can, createSession, destroySession, sessionCookie, clearCookie, csrfValid, readCsrf,
  createRateLimiter, clientIp, verifyPassword, hashPassword, passwordProblems, CAPABILITIES,
} from '../lib/auth.js';
import {
  listPages, getPageBySlug, getPageById, savePage, deletePage, revisionsFor, restoreRevision,
  listProjects, getProjectBySlug, getProjectById, saveProject, deleteProject,
  listInsights, getInsightBySlug, getInsightById, saveInsight, deleteInsight,
  listServices, getServiceBySlug, getServiceById, saveService, deleteService,
  createEnquiry, listEnquiries, contentHealth, sitemapEntries, dashboardStats,
} from '../lib/content.js';
import { normalizeBlocks } from '../lib/blocks.js';
import { logActivity, recentActivity } from '../lib/audit.js';
import { getSiteConfig, setSettings, EDITABLE_SETTINGS } from '../lib/settings.js';
import { sanitizeHtml } from '../lib/sanitize.js';
import { exportStatic } from '../lib/exporter.js';
import { createUploader } from '../lib/media.js';
import { pagePayload, projectPayload, insightPayload, servicePayload, gather } from '../lib/payloads.js';

const MIME_EXT = {
  'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp', 'image/avif': '.avif',
};

export function createApiRouter({ db, config, getSiteConfig }) {
  const router = express.Router();
  const loginLimiter = createRateLimiter({
    windowMs: config.rateLimitWindowMs,
    max: config.loginMaxAttempts,
    keyFn: (req) => `login:${clientIp(req)}:${String(req.body?.email || '').toLowerCase()}`,
  });
  const enquiryLimiter = createRateLimiter({
    windowMs: config.rateLimitWindowMs,
    max: config.enquiryMaxPerWindow,
    keyFn: (req) => `api-enquiry:${clientIp(req)}`,
  });

  router.use(express.json({ limit: '1mb' }));

  /* -------------------------------------------------------------- helpers */
  const authorize = (capability) => (req, _res, next) => {
    if (!req.user) return next(unauthorized());
    if (req.user.viaToken) {
      if (!req.tokenScopes?.includes(capability)) return next(forbidden(`Token is missing the ${capability} scope.`));
      return next();
    }
    if (!can(req.user, capability)) return next(forbidden());
    return next();
  };

  const csrfGuard = (req, _res, next) => {
    if (req.user?.viaToken) return next();
    if (req.method === 'GET' || req.method === 'HEAD' || req.method === 'OPTIONS') return next();
    if (!csrfValid(req, readCsrf(req))) {
      return next(new ApiError(403, 'csrf_failed', 'Missing or invalid CSRF token. Reload the page and try again.'));
    }
    next();
  };

  const writeGuard = [authorize('content:write'), csrfGuard];

  const publicPage = (page) => ({
    slug: page.slug,
    title: page.title,
    page_type: page.page_type,
    template: page.template,
    seo: {
      title: page.seo_title, description: page.seo_description,
      og_image: page.og_image, canonical: page.canonical, noindex: page.noindex,
    },
    hero: {
      eyebrow: page.hero_eyebrow, title: page.hero_title,
      intro: page.hero_intro, image: page.hero_image,
    },
    blocks: page.blocks,
    updated_at: page.updated_at,
    published_at: page.published_at,
  });
  const publicProject = (p) => ({
    slug: p.slug, title: p.title, category: p.category, location: p.location, year: p.year, area: p.area,
    summary: p.summary, body: p.body, cover_image: p.cover_image, gallery: p.gallery, facts: p.facts,
    featured: p.featured, published_at: p.published_at,
  });
  const publicInsight = (i) => ({
    slug: i.slug, title: i.title, category: i.category, reading_time: i.reading_time, excerpt: i.excerpt,
    body: i.body, cover_image: i.cover_image, author: i.author, featured: i.featured, published_at: i.published_at,
  });
  const publicService = (s) => ({
    slug: s.slug, title: s.title, number: s.number, summary: s.summary, body: s.body,
    icon: s.icon, deliverables: s.deliverables,
  });

  /* --------------------------------------------------------------- health */
  router.get('/health', (req, res) => {
    res.json({ status: 'ok', version: '2.1.0', endpoints: ['/pages', '/projects', '/insights', '/services', '/enquiries', '/session'] });
  });

  /* ------------------------------------------------------------- settings */
  router.get('/settings', (req, res) => {
    const site = getSiteConfig(db);
    res.json({
      data: {
        name: site['site.name'], tagline: site['site.tagline'], description: site['site.description'],
        url: site['site.url'],
        contact: {
          email: site['contact.email'], phone: site['contact.phone'], whatsapp: site['contact.whatsapp'],
          address: site['contact.address'], hours: site['contact.hours'],
        },
        socials: Object.fromEntries(site.socials),
        nav: site.nav,
        seo: { default_description: site['seo.default_description'], default_og_image: site['seo.default_og_image'] },
      },
    });
  });

  router.put('/settings', authorize('settings:write'), csrfGuard, (req, res, next) => {
    try {
      const updates = req.body?.settings && typeof req.body.settings === 'object' ? req.body.settings : req.body;
      const unknown = Object.keys(updates || {}).filter((k) => !EDITABLE_SETTINGS.includes(k));
      if (unknown.length) throw invalid({ settings: `Unknown setting(s): ${unknown.join(', ')}` });
      const changed = setSettings(db, updates);
      logActivity(db, { user: req.user, action: 'settings.updated', entity: 'settings', ip: clientIp(req), meta: { changed } });
      res.json({ data: { changed }, meta: { count: changed.length } });
    } catch (err) { next(err); }
  });

  /* ---------------------------------------------------------------- pages */
  router.get('/pages', (req, res) => {
    const includeDrafts = req.query.include_drafts === '1' && req.user;
    const pages = listPages(db, { status: includeDrafts ? req.query.status : 'published', type: req.query.type, q: req.query.q });
    res.json({ data: pages.map(publicPage), meta: { count: pages.length } });
  });

  router.get('/pages/:slug', (req, res, next) => {
    const page = getPageBySlug(db, req.params.slug, { includeDrafts: Boolean(req.user) && req.query.include_drafts === '1' });
    if (!page) return next(notFound('Page'));
    res.json({ data: publicPage(page) });
  });

  /* ------------------------------------------------------------- projects */
  router.get('/projects', (req, res) => {
    const p = pagination(req);
    const { rows, total } = listProjects(db, {
      status: 'published', category: req.query.category, featured: req.query.featured === '1' ? true : undefined,
      limit: p.limit, offset: p.offset, q: req.query.q,
    });
    res.json(paged(rows.map(publicProject), total, { page: p.page, perPage: p.perPage }));
  });

  router.get('/projects/:slug', (req, res, next) => {
    const project = getProjectBySlug(db, req.params.slug);
    if (!project) return next(notFound('Project'));
    res.json({ data: publicProject(project) });
  });

  /* ------------------------------------------------------------- insights */
  router.get('/insights', (req, res) => {
    const p = pagination(req);
    const { rows, total } = listInsights(db, { status: 'published', limit: p.limit, offset: p.offset, q: req.query.q });
    res.json(paged(rows.map(publicInsight), total, { page: p.page, perPage: p.perPage }));
  });

  router.get('/insights/:slug', (req, res, next) => {
    const insight = getInsightBySlug(db, req.params.slug);
    if (!insight) return next(notFound('Insight'));
    res.json({ data: publicInsight(insight) });
  });

  /* ------------------------------------------------------------- services */
  router.get('/services', (req, res) => {
    const services = listServices(db, { status: 'published' });
    res.json({ data: services.map(publicService), meta: { count: services.length } });
  });

  router.get('/services/:slug', (req, res, next) => {
    const service = getServiceBySlug(db, req.params.slug);
    if (!service) return next(notFound('Service'));
    res.json({ data: publicService(service) });
  });

  router.get('/sitemap', (req, res) => {
    const site = getSiteConfig(db);
    res.json({ data: sitemapEntries(db, site['site.url']) });
  });

  /* ------------------------------------------------------------ enquiries */
  router.post('/enquiries', enquiryLimiter, (req, res, next) => {
    try {
      const body = req.body || {};
      const honeypot = getSiteConfig(db)['enquiry.honeypot'] || 'company_website';
      if (String(body[honeypot] || '').trim()) {
        return res.status(202).json({ data: { received: true } });
      }
      const payload = gather({
        name: () => vString(body.name, { field: 'name', required: true, min: 2, max: 100, label: 'Name' }),
        email: () => vEmail(body.email),
        phone: () => vString(body.phone, { field: 'phone', max: 40 }),
        location: () => vString(body.location, { field: 'location', max: 150 }),
        service: () => vString(body.service, { field: 'service', max: 60 }),
        timeline: () => vString(body.timeline, { field: 'timeline', max: 60 }),
        message: () => vString(body.message, { field: 'message', required: true, min: 10, max: 5000, label: 'Message' }),
      });
      const enquiry = createEnquiry(db, payload, {
        ip: clientIp(req), userAgent: req.get('user-agent') || '', source: body.source || 'api',
      });
      logActivity(db, { user: null, action: 'enquiry.created', entity: 'enquiry', entityId: enquiry.id, ip: clientIp(req), meta: { source: 'api' } });
      res.status(201).json({ data: { id: enquiry.id, received: true } });
    } catch (err) { next(err); }
  });

  /* -------------------------------------------------------------- session */
  router.post('/session', loginLimiter, (req, res, next) => {
    try {
      const email = String(req.body?.email || '').toLowerCase().trim();
      const password = String(req.body?.password || '');
      const user = db.get('SELECT * FROM users WHERE email = ?', email);
      const ok = user && user.status === 'active' && verifyPassword(password, user.password_hash);
      if (!ok) {
        logActivity(db, { user: null, action: 'auth.login_failed', entity: 'user', ip: clientIp(req), meta: { email } });
        throw new ApiError(401, 'invalid_credentials', 'Email or password is incorrect.');
      }
      db.run("UPDATE users SET last_login_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", user.id);
      const session = createSession(db, user.id, { ip: clientIp(req), userAgent: req.get('user-agent') });
      res.set('Set-Cookie', sessionCookie(session, { secure: config.secureCookies }));
      logActivity(db, { user, action: 'auth.login', entity: 'user', entityId: user.id, ip: clientIp(req) });
      res.json({
        data: {
          user: { id: user.id, email: user.email, name: user.name, role: user.role },
          csrf: session.csrf,
          expires_at: session.expires.toISOString(),
          capabilities: CAPABILITIES[user.role] || [],
        },
      });
    } catch (err) { next(err); }
  });

  router.get('/session', (req, res) => {
    if (!req.user) return res.status(401).json({ error: { code: 'unauthorized', message: 'Not signed in.' } });
    res.json({
      data: {
        user: req.user, csrf: req.session?.csrf || null,
        capabilities: CAPABILITIES[req.user.role] || [], via_token: Boolean(req.user.viaToken),
      },
    });
  });

  router.delete('/session', csrfGuard, (req, res) => {
    if (req.session) destroySession(db, req.session.id);
    res.set('Set-Cookie', clearCookie());
    res.json({ data: { signed_out: true } });
  });

  /* ---------------------------------------------------------- admin: pages */
  const admin = express.Router();
  admin.get('/pages', authorize('content:read'), (req, res) => {
    const p = pagination(req);
    const rows = listPages(db, { status: req.query.status, type: req.query.type, q: req.query.q });
    const slice = rows.slice(p.offset, p.offset + p.limit);
    res.json(paged(slice, rows.length, { page: p.page, perPage: p.perPage }));
  });

  admin.post('/pages', writeGuard, (req, res, next) => {
    try {
      const data = pagePayload(req.body || {});
      const page = savePage(db, data, req.user);
      logActivity(db, { user: req.user, action: 'page.created', entity: 'page', entityId: page.id, ip: clientIp(req), meta: { slug: page.slug } });
      res.status(201).json({ data: page });
    } catch (err) { next(err); }
  });

  admin.get('/pages/:id', authorize('content:read'), (req, res, next) => {
    const page = getPageById(db, Number(req.params.id));
    if (!page) return next(notFound('Page'));
    res.json({ data: page });
  });

  admin.put('/pages/:id', writeGuard, (req, res, next) => {
    try {
      const existing = getPageById(db, Number(req.params.id));
      if (!existing) return next(notFound('Page'));
      const page = savePage(db, { ...pagePayload(req.body || {}), id: existing.id }, req.user);
      logActivity(db, { user: req.user, action: 'page.updated', entity: 'page', entityId: page.id, ip: clientIp(req), meta: { slug: page.slug } });
      res.json({ data: page });
    } catch (err) { next(err); }
  });

  admin.delete('/pages/:id', authorize('content:write'), csrfGuard, (req, res, next) => {
    try {
      deletePage(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'page.deleted', entity: 'page', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  admin.get('/pages/:id/revisions', authorize('content:read'), (req, res) => {
    res.json({ data: revisionsFor(db, Number(req.params.id)) });
  });

  admin.post('/pages/:id/revisions/:revisionId/restore', writeGuard, (req, res, next) => {
    try {
      const page = restoreRevision(db, Number(req.params.id), Number(req.params.revisionId), req.user);
      logActivity(db, { user: req.user, action: 'page.revision_restored', entity: 'page', entityId: page.id, ip: clientIp(req) });
      res.json({ data: page });
    } catch (err) { next(err); }
  });

  /* ------------------------------------------------------- admin: projects */
  admin.get('/projects', authorize('content:read'), (req, res) => {
    const p = pagination(req);
    const { rows, total } = listProjects(db, { status: req.query.status, category: req.query.category, limit: p.limit, offset: p.offset, q: req.query.q });
    res.json(paged(rows, total, { page: p.page, perPage: p.perPage }));
  });

  admin.post('/projects', writeGuard, (req, res, next) => {
    try {
      const project = saveProject(db, projectPayload(req.body || {}));
      logActivity(db, { user: req.user, action: 'project.created', entity: 'project', entityId: project.id, ip: clientIp(req) });
      res.status(201).json({ data: project });
    } catch (err) { next(err); }
  });

  admin.put('/projects/:id', writeGuard, (req, res, next) => {
    try {
      const existing = getProjectById(db, Number(req.params.id));
      if (!existing) return next(notFound('Project'));
      const project = saveProject(db, { ...projectPayload(req.body || {}), id: existing.id });
      logActivity(db, { user: req.user, action: 'project.updated', entity: 'project', entityId: project.id, ip: clientIp(req) });
      res.json({ data: project });
    } catch (err) { next(err); }
  });

  admin.delete('/projects/:id', authorize('content:write'), csrfGuard, (req, res, next) => {
    try {
      deleteProject(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'project.deleted', entity: 'project', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* ------------------------------------------------------- admin: insights */
  admin.get('/insights', authorize('content:read'), (req, res) => {
    const p = pagination(req);
    const { rows, total } = listInsights(db, { status: req.query.status, limit: p.limit, offset: p.offset, q: req.query.q });
    res.json(paged(rows, total, { page: p.page, perPage: p.perPage }));
  });

  admin.post('/insights', writeGuard, (req, res, next) => {
    try {
      const insight = saveInsight(db, insightPayload(req.body || {}));
      logActivity(db, { user: req.user, action: 'insight.created', entity: 'insight', entityId: insight.id, ip: clientIp(req) });
      res.status(201).json({ data: insight });
    } catch (err) { next(err); }
  });

  admin.put('/insights/:id', writeGuard, (req, res, next) => {
    try {
      const existing = getInsightById(db, Number(req.params.id));
      if (!existing) return next(notFound('Insight'));
      const insight = saveInsight(db, { ...insightPayload(req.body || {}), id: existing.id });
      logActivity(db, { user: req.user, action: 'insight.updated', entity: 'insight', entityId: insight.id, ip: clientIp(req) });
      res.json({ data: insight });
    } catch (err) { next(err); }
  });

  admin.delete('/insights/:id', authorize('content:write'), csrfGuard, (req, res, next) => {
    try {
      deleteInsight(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'insight.deleted', entity: 'insight', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* ------------------------------------------------------- admin: services */
  admin.get('/services', authorize('content:read'), (req, res) => {
    res.json({ data: listServices(db, { status: req.query.status }) });
  });

  admin.post('/services', writeGuard, (req, res, next) => {
    try {
      const service = saveService(db, servicePayload(req.body || {}));
      logActivity(db, { user: req.user, action: 'service.created', entity: 'service', entityId: service.id, ip: clientIp(req) });
      res.status(201).json({ data: service });
    } catch (err) { next(err); }
  });

  admin.put('/services/:id', writeGuard, (req, res, next) => {
    try {
      const existing = getServiceById(db, Number(req.params.id));
      if (!existing) return next(notFound('Service'));
      const service = saveService(db, { ...servicePayload(req.body || {}), id: existing.id });
      logActivity(db, { user: req.user, action: 'service.updated', entity: 'service', entityId: service.id, ip: clientIp(req) });
      res.json({ data: service });
    } catch (err) { next(err); }
  });

  admin.delete('/services/:id', authorize('content:write'), csrfGuard, (req, res, next) => {
    try {
      deleteService(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'service.deleted', entity: 'service', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* ------------------------------------------------------ admin: enquiries */
  admin.get('/enquiries', authorize('enquiries:read'), (req, res) => {
    const p = pagination(req);
    const { rows, total } = listEnquiries(db, { status: req.query.status, q: req.query.q, limit: p.limit, offset: p.offset });
    res.json(paged(rows, total, { page: p.page, perPage: p.perPage }));
  });

  admin.get('/enquiries/export.csv', authorize('enquiries:read'), (req, res) => {
    const { rows } = listEnquiries(db, { status: req.query.status, limit: 5000, offset: 0 });
    const csv = toCsv(rows, [
      { label: 'ID', key: 'id' }, { label: 'Received', key: 'created_at' }, { label: 'Name', key: 'name' },
      { label: 'Email', key: 'email' }, { label: 'Phone', key: 'phone' }, { label: 'Location', key: 'location' },
      { label: 'Service', key: 'service' }, { label: 'Timeline', key: 'timeline' }, { label: 'Status', key: 'status' },
      { label: 'Message', key: 'message' },
    ]);
    res.set({
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': `attachment; filename="woodex-enquiries-${new Date().toISOString().slice(0, 10)}.csv"`,
      'Cache-Control': 'no-store',
    });
    res.send(csv);
  });

  admin.patch('/enquiries/:id', authorize('enquiries:write'), csrfGuard, (req, res, next) => {
    try {
      const existing = db.get('SELECT * FROM enquiries WHERE id = ?', Number(req.params.id));
      if (!existing) return next(notFound('Enquiry'));
      const status = vEnum(req.body?.status ?? existing.status, ['new', 'in_review', 'quoted', 'won', 'lost', 'spam'], { field: 'status', fallback: existing.status });
      const notes = vString(req.body?.notes ?? existing.notes ?? '', { field: 'notes', max: 4000 });
      db.run("UPDATE enquiries SET status = ?, notes = ?, updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", status, notes, existing.id);
      logActivity(db, { user: req.user, action: 'enquiry.updated', entity: 'enquiry', entityId: existing.id, ip: clientIp(req), meta: { status } });
      res.json({ data: db.get('SELECT * FROM enquiries WHERE id = ?', existing.id) });
    } catch (err) { next(err); }
  });

  admin.delete('/enquiries/:id', authorize('content:write'), csrfGuard, (req, res, next) => {
    try {
      const out = db.run('DELETE FROM enquiries WHERE id = ?', Number(req.params.id));
      if (!out.changes) return next(notFound('Enquiry'));
      logActivity(db, { user: req.user, action: 'enquiry.deleted', entity: 'enquiry', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* ---------------------------------------------------------- admin: media */
  const upload = createUploader(config);

  admin.get('/media', authorize('content:read'), (req, res) => {
    const p = pagination(req, { defaultPerPage: 40 });
    const total = db.get('SELECT COUNT(*) AS n FROM media').n;
    const rows = db.all('SELECT * FROM media ORDER BY id DESC LIMIT ? OFFSET ?', p.limit, p.offset);
    res.json(paged(rows, total, { page: p.page, perPage: p.perPage }));
  });

  admin.post('/media', authorize('media:write'), upload.single('file'), csrfGuard, (req, res, next) => {
    try {
      if (!req.file) throw invalid({ file: 'Choose an image to upload.' });
      const alt = vString(req.body?.alt, { field: 'alt', max: 300 });
      const caption = vString(req.body?.caption, { field: 'caption', max: 300 });
      const out = db.run(
        'INSERT INTO media (filename, original_name, mime, size, alt, caption, created_by) VALUES (?,?,?,?,?,?,?)',
        req.file.filename, path.basename(req.file.originalname).slice(0, 200), req.file.mimetype,
        req.file.size, alt || null, caption || null, req.user.id,
      );
      logActivity(db, { user: req.user, action: 'media.uploaded', entity: 'media', entityId: out.lastInsertRowid, ip: clientIp(req) });
      res.status(201).json({ data: db.get('SELECT * FROM media WHERE id = ?', out.lastInsertRowid) });
    } catch (err) { next(err); }
  });

  admin.patch('/media/:id', authorize('media:write'), csrfGuard, (req, res, next) => {
    try {
      const media = db.get('SELECT * FROM media WHERE id = ?', Number(req.params.id));
      if (!media) return next(notFound('Media'));
      const alt = vString(req.body?.alt ?? media.alt ?? '', { field: 'alt', max: 300 });
      const caption = vString(req.body?.caption ?? media.caption ?? '', { field: 'caption', max: 300 });
      db.run('UPDATE media SET alt = ?, caption = ? WHERE id = ?', alt || null, caption || null, media.id);
      res.json({ data: db.get('SELECT * FROM media WHERE id = ?', media.id) });
    } catch (err) { next(err); }
  });

  admin.delete('/media/:id', authorize('media:write'), csrfGuard, (req, res, next) => {
    try {
      const media = db.get('SELECT * FROM media WHERE id = ?', Number(req.params.id));
      if (!media) return next(notFound('Media'));
      db.run('DELETE FROM media WHERE id = ?', media.id);
      const filePath = path.join(config.uploadDir, path.basename(media.filename));
      if (fs.existsSync(filePath)) fs.unlinkSync(filePath);
      logActivity(db, { user: req.user, action: 'media.deleted', entity: 'media', entityId: media.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* ---------------------------------------------------------- admin: users */
  admin.get('/users', authorize('users:manage'), (req, res) => {
    res.json({
      data: db.all('SELECT id, email, name, role, status, last_login_at, created_at FROM users ORDER BY id'),
    });
  });

  admin.post('/users', authorize('users:manage'), csrfGuard, (req, res, next) => {
    try {
      const email = vEmail(req.body?.email);
      const name = vString(req.body?.name, { field: 'name', required: true, max: 120, label: 'Name' });
      const role = vEnum(req.body?.role ?? 'editor', ['owner', 'editor', 'viewer'], { field: 'role', fallback: 'editor' });
      const password = String(req.body?.password || '');
      const problems = passwordProblems(password);
      if (problems.length) throw invalid({ password: problems.join(' ') });
      if (db.get('SELECT id FROM users WHERE email = ?', email)) throw conflict('A user with that email already exists.');
      const out = db.run('INSERT INTO users (email, name, role, password_hash) VALUES (?,?,?,?)', email, name, role, hashPassword(password));
      logActivity(db, { user: req.user, action: 'user.created', entity: 'user', entityId: out.lastInsertRowid, ip: clientIp(req), meta: { role } });
      res.status(201).json({ data: db.get('SELECT id, email, name, role, status, created_at FROM users WHERE id = ?', out.lastInsertRowid) });
    } catch (err) { next(err); }
  });

  admin.patch('/users/:id', authorize('users:manage'), csrfGuard, (req, res, next) => {
    try {
      const user = db.get('SELECT * FROM users WHERE id = ?', Number(req.params.id));
      if (!user) return next(notFound('User'));
      const role = vEnum(req.body?.role ?? user.role, ['owner', 'editor', 'viewer'], { field: 'role', fallback: user.role });
      const status = vEnum(req.body?.status ?? user.status, ['active', 'disabled'], { field: 'status', fallback: user.status });
      const name = vString(req.body?.name ?? user.name, { field: 'name', max: 120 });
      if (user.role === 'owner' && (role !== 'owner' || status !== 'active')) {
        const owners = db.get("SELECT COUNT(*) AS n FROM users WHERE role='owner' AND status='active'").n;
        if (owners <= 1) throw conflict('The last active owner cannot be demoted or disabled.');
      }
      db.run("UPDATE users SET role=?, status=?, name=?, updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id=?", role, status, name, user.id);
      if (req.body?.password) {
        const problems = passwordProblems(String(req.body.password));
        if (problems.length) throw invalid({ password: problems.join(' ') });
        db.run('UPDATE users SET password_hash = ? WHERE id = ?', hashPassword(String(req.body.password)), user.id);
        db.run('DELETE FROM sessions WHERE user_id = ?', user.id);
      }
      logActivity(db, { user: req.user, action: 'user.updated', entity: 'user', entityId: user.id, ip: clientIp(req), meta: { role, status } });
      res.json({ data: db.get('SELECT id, email, name, role, status FROM users WHERE id = ?', user.id) });
    } catch (err) { next(err); }
  });

  admin.delete('/users/:id', authorize('users:manage'), csrfGuard, (req, res, next) => {
    try {
      const user = db.get('SELECT * FROM users WHERE id = ?', Number(req.params.id));
      if (!user) return next(notFound('User'));
      if (user.id === req.user.id) throw conflict('You cannot delete your own account.');
      const owners = db.get("SELECT COUNT(*) AS n FROM users WHERE role='owner' AND status='active'").n;
      if (user.role === 'owner' && owners <= 1) throw conflict('The last active owner cannot be deleted.');
      db.run('DELETE FROM users WHERE id = ?', user.id);
      logActivity(db, { user: req.user, action: 'user.deleted', entity: 'user', entityId: user.id, ip: clientIp(req) });
      res.json({ data: { deleted: true } });
    } catch (err) { next(err); }
  });

  /* --------------------------------------------------------- admin: tokens */
  admin.get('/tokens', authorize('tokens:manage'), (req, res) => {
    res.json({
      data: db.all('SELECT id, label, scopes, last_used_at, expires_at, revoked_at, created_at FROM api_tokens ORDER BY id DESC'),
    });
  });

  admin.post('/tokens', authorize('tokens:manage'), csrfGuard, (req, res, next) => {
    try {
      const label = vString(req.body?.label, { field: 'label', required: true, max: 100, label: 'Label' });
      const scopes = Array.isArray(req.body?.scopes) ? req.body.scopes : String(req.body?.scopes || 'content:read').split(',');
      const allowed = ['content:read', 'content:write', 'enquiries:read'];
      const clean = scopes.map((s) => String(s).trim()).filter((s) => allowed.includes(s));
      if (!clean.length) throw invalid({ scopes: `Choose at least one of ${allowed.join(', ')}.` });
      const token = `wox_${crypto.randomBytes(24).toString('hex')}`;
      const hash = crypto.createHash('sha256').update(token).digest('hex');
      const out = db.run('INSERT INTO api_tokens (user_id, label, token_hash, scopes) VALUES (?,?,?,?)', req.user.id, label, hash, clean.join(','));
      logActivity(db, { user: req.user, action: 'token.created', entity: 'api_token', entityId: out.lastInsertRowid, ip: clientIp(req), meta: { scopes: clean } });
      res.status(201).json({ data: { id: out.lastInsertRowid, label, scopes: clean, token, note: 'Copy this token now — it is not shown again.' } });
    } catch (err) { next(err); }
  });

  admin.delete('/tokens/:id', authorize('tokens:manage'), csrfGuard, (req, res, next) => {
    try {
      const out = db.run("UPDATE api_tokens SET revoked_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", Number(req.params.id));
      if (!out.changes) return next(notFound('Token'));
      logActivity(db, { user: req.user, action: 'token.revoked', entity: 'api_token', entityId: req.params.id, ip: clientIp(req) });
      res.json({ data: { revoked: true } });
    } catch (err) { next(err); }
  });

  /* ------------------------------------------------ admin: insights & health */
  admin.get('/activity', authorize('audit:read'), (req, res) => {
    res.json({ data: recentActivity(db, Math.min(200, Number(req.query.limit) || 50)) });
  });

  admin.get('/dashboard', authorize('content:read'), (req, res) => {
    res.json({ data: { stats: dashboardStats(db), health: contentHealth(db, getSiteConfig(db)), activity: recentActivity(db, 10) } });
  });

  admin.get('/health', authorize('content:read'), (req, res) => {
    const site = getSiteConfig(db);
    let uploadsWritable = false;
    try { fs.accessSync(config.uploadDir, fs.constants.W_OK); uploadsWritable = true; } catch { uploadsWritable = false; }
    res.json({
      data: {
        checks: [
          { id: 'database', status: 'ok', detail: 'SQLite reachable' },
          { id: 'uploads', status: uploadsWritable ? 'ok' : 'fail', detail: uploadsWritable ? 'Upload directory writable' : 'Upload directory is not writable' },
          { id: 'base_url', status: site['site.url'].startsWith('https://') ? 'ok' : 'warn', detail: site['site.url'] },
          { id: 'sitemap', status: 'ok', detail: `${sitemapEntries(db, site['site.url']).length} URLs` },
        ],
        content: contentHealth(db, site),
      },
    });
  });

  admin.post('/export', authorize('system:export'), csrfGuard, async (req, res, next) => {
    try {
      const target = vString(req.body?.target, { field: 'target', max: 200 }) || path.join(config.dbPath, '..', '..', 'dist');
      const result = await exportStatic(db, getSiteConfig(db), { targetDir: target });
      db.run('INSERT INTO export_runs (target, pages, files, status) VALUES (?,?,?,?)', target, result.pages, result.files, 'ok');
      logActivity(db, { user: req.user, action: 'site.exported', entity: 'system', ip: clientIp(req), meta: { target } });
      res.json({ data: result });
    } catch (err) { next(err); }
  });

  router.use('/admin', admin);

  /* ------------------------------------------------------------- fallback */
  router.use((req, res, next) => next(notFound('Endpoint')));
  // eslint-disable-next-line no-unused-vars
  router.use((err, req, res, next) => {
    if (err instanceof multer.MulterError) {
      const message = err.code === 'LIMIT_FILE_SIZE'
        ? `Image is too large (limit ${Math.round(config.maxUploadBytes / 1024 / 1024)}MB).`
        : err.message;
      const status = err.code === 'LIMIT_FILE_SIZE' ? 413 : 422;
      return res.status(status).json({ error: { code: 'upload_failed', message } });
    }
    if (err?.message === 'Not allowed') return res.status(422).json({ error: { code: 'validation_failed', message: 'Only image uploads are allowed.' } });
    return sendError(res, err);
  });

  return router;
}

