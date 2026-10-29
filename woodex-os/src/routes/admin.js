import express from 'express';
import path from 'node:path';
import fs from 'node:fs';
import crypto from 'node:crypto';
import {
  can, createSession, destroySession, sessionCookie, clearCookie, csrfValid, readCsrf,
  verifyPassword, hashPassword, passwordProblems, createRateLimiter, clientIp, CAPABILITIES,
} from '../lib/auth.js';
import { toCsv, invalid, ApiError } from '../lib/http.js';
import {
  listPages, getPageById, savePage, deletePage, revisionsFor, restoreRevision,
  listProjects, getProjectById, saveProject, deleteProject,
  listInsights, getInsightById, saveInsight, deleteInsight,
  listServices, getServiceById, saveService, deleteService,
  listEnquiries, dashboardStats, contentHealth,
} from '../lib/content.js';
import { pagePayload, projectPayload, insightPayload, servicePayload } from '../lib/payloads.js';
import { logActivity, recentActivity } from '../lib/audit.js';
import { BLOCK_TYPES, ARRAY_ITEM_FIELDS, blockLabel } from '../lib/blocks.js';
import { getSiteConfig, setSettings, EDITABLE_SETTINGS } from '../lib/settings.js';
import { createUploader } from '../lib/media.js';
import { exportStatic } from '../lib/exporter.js';

const ROLE_LABELS = { owner: 'Owner', editor: 'Editor', viewer: 'Viewer' };

export function createAdminRouter({ db, config, getSiteConfig }) {
  const router = express.Router();
  const loginLimiter = createRateLimiter({
    windowMs: config.rateLimitWindowMs, max: config.loginMaxAttempts,
    keyFn: (req) => `admin-login:${clientIp(req)}`,
  });
  const upload = createUploader(config);

  const flash = (req) => ({
    ok: typeof req.query.ok === 'string' ? req.query.ok : '',
    err: typeof req.query.err === 'string' ? req.query.err : '',
    notice: typeof req.query.notice === 'string' ? req.query.notice : '',
  });

  /* ---------------------------------------------------------------- auth */
  router.get('/login', (req, res) => {
    if (req.user) return res.redirect('/admin');
    res.render('admin/login', { title: 'Sign in', error: null, email: '', next: req.query.next || '/admin' });
  });

  router.post('/login', loginLimiter, (req, res, next) => {
    try {
      const email = String(req.body?.email || '').toLowerCase().trim();
      const password = String(req.body?.password || '');
      const next_ = String(req.body?.next || '/admin');
      const user = db.get('SELECT * FROM users WHERE email = ?', email);
      const ok = user && user.status === 'active' && verifyPassword(password, user.password_hash);
      if (!ok) {
        logActivity(db, { user: null, action: 'auth.login_failed', entity: 'user', ip: clientIp(req), meta: { email, surface: 'admin' } });
        return res.status(401).render('admin/login', {
          title: 'Sign in',
          error: 'Email or password is incorrect.',
          email,
          next: next_,
        });
      }
      db.run("UPDATE users SET last_login_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", user.id);
      const session = createSession(db, user.id, { ip: clientIp(req), userAgent: req.get('user-agent') });
      res.set('Set-Cookie', sessionCookie(session, { secure: config.secureCookies }));
      logActivity(db, { user, action: 'auth.login', entity: 'user', entityId: user.id, ip: clientIp(req), meta: { surface: 'admin' } });
      const safeNext = /^\/(?!\/)/.test(next_) ? next_ : '/admin';
      return res.redirect(safeNext);
    } catch (err) { next(err); }
  });

  router.post('/logout', (req, res) => {
    if (req.session && csrfValid(req, readCsrf(req))) destroySession(db, req.session.id);
    res.set('Set-Cookie', clearCookie());
    res.redirect('/admin/login');
  });

  /* --------------------------------------------------------- session gate */
  router.use((req, res, next) => {
    if (!req.user) {
      if (req.method === 'GET') return res.redirect(`/admin/login?next=${encodeURIComponent(req.originalUrl)}`);
      return res.status(401).render('admin/error', {
        title: 'Signed out',
        message: 'Your session expired. Sign in and try again.',
        active: '', flash: { ok: '', err: '', notice: '' },
      });
    }
    next();
  });

  router.use((req, res, next) => {
    res.locals.active = '';
    res.locals.newEnquiries = db.get("SELECT COUNT(*) AS n FROM enquiries WHERE status='new'").n;
    res.locals.roleLabels = ROLE_LABELS;
    res.locals.capabilities = CAPABILITIES[req.user.role] || [];
    res.locals.capabilitiesByRole = CAPABILITIES;
    res.locals.flash = flash(req);
    res.locals.can = (cap) => can(req.user, cap);
    res.locals.blockTypes = BLOCK_TYPES;
    res.locals.blockSchema = { types: BLOCK_TYPES, arrays: ARRAY_ITEM_FIELDS };
    res.locals.mediaFiles = db.all('SELECT id, filename, alt, mime FROM media ORDER BY id DESC LIMIT 200');
    res.locals.mediaUrl = (file) => `/uploads/${file}`;
    res.locals.blockLabel = blockLabel;
    next();
  });

  const CSRF_EXEMPT_MULTIPART = (req) => (req.headers['content-type'] || '').startsWith('multipart/form-data');

  router.use((req, res, next) => {
    if (req.method === 'POST' && !CSRF_EXEMPT_MULTIPART(req) && !csrfValid(req, readCsrf(req))) {
      return res.status(403).render('admin/error', {
        title: 'Security check failed',
        message: 'Your form session expired. Reload the page and submit again.',
        active: '', flash: { ok: '', err: '', notice: '' },
      });
    }
    next();
  });

  const guard = (capability) => (req, res, next) => {
    if (!can(req.user, capability)) {
      return res.status(403).render('admin/error', {
        title: 'Not allowed',
        message: `Your ${ROLE_LABELS[req.user.role]} role cannot access this area.`,
        active: '', flash: { ok: '', err: '', notice: '' },
      });
    }
    next();
  };

  const redirectOk = (res, where, message = 'Saved') => res.redirect(`${where}${where.includes('?') ? '&' : '?'}ok=${encodeURIComponent(message)}`);

  /* ------------------------------------------------------------ dashboard */
  router.get('/', (req, res) => {
    const site = getSiteConfig(db);
    res.render('admin/dashboard', {
      title: 'Dashboard',
      active: 'dashboard',
      stats: dashboardStats(db),
      health: contentHealth(db, site),
      activity: recentActivity(db, 12),
      latestEnquiries: db.all('SELECT * FROM enquiries ORDER BY id DESC LIMIT 5'),
      drafts: listPages(db, { status: 'draft' }).slice(0, 5),
      site,
    });
  });

  /* ---------------------------------------------------------------- pages */
  router.get('/pages', (req, res) => {
    const status = typeof req.query.status === 'string' ? req.query.status : '';
    const q = typeof req.query.q === 'string' ? req.query.q : '';
    res.render('admin/pages/index', {
      title: 'Pages', active: 'pages',
      pages: listPages(db, { status: status || undefined, q: q || undefined }),
      filter: { status, q },
    });
  });

  router.get('/pages/new', guard('content:write'), (req, res) => {
    res.render('admin/pages/edit', {
      title: 'New page', active: 'pages', page: null,
      values: { title: '', slug: '', page_type: 'page', template: 'default', status: 'draft', blocks: [], sort_order: 0 },
      errors: {}, revisions: [],
    });
  });

  router.get('/pages/:id', (req, res, next) => {
    const page = getPageById(db, Number(req.params.id));
    if (!page) return next();
    res.render('admin/pages/edit', {
      title: `Edit: ${page.title}`, active: 'pages', page,
      values: page, errors: {}, revisions: revisionsFor(db, page.id),
    });
  });

  function savePageFromForm(req, res, next, id) {
    try {
      const body = { ...req.body };
      if (id) body.id = id;
      if (!body.blocks) {
        const existing = id ? getPageById(db, id) : null;
        body.blocks = JSON.stringify(existing ? existing.blocks : []);
      }
      const data = pagePayload(body);
      const saved = savePage(db, data, req.user);
      logActivity(db, {
        user: req.user, action: id ? 'page.updated' : 'page.created', entity: 'page',
        entityId: saved.id, ip: clientIp(req), meta: { slug: saved.slug },
      });
      return redirectOk(res, `/admin/pages/${saved.id}`, 'Page saved');
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const existing = id ? getPageById(db, id) : null;
        return res.status(422).render('admin/pages/edit', {
          title: id ? 'Edit page' : 'New page', active: 'pages', page: existing,
          values: { ...req.body, blocks: safeJson(req.body.blocks, []) },
          errors: err.details || {}, revisions: existing ? revisionsFor(db, existing.id) : [],
        });
      }
      return next(err);
    }
  }

  router.post('/pages', guard('content:write'), (req, res, next) => savePageFromForm(req, res, next, null));
  router.post('/pages/:id', guard('content:write'), (req, res, next) => savePageFromForm(req, res, next, Number(req.params.id)));

  router.post('/pages/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      deletePage(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'page.deleted', entity: 'page', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/pages', 'Page deleted');
    } catch (err) { return next(err); }
  });

  router.post('/pages/:id/publish', guard('content:write'), (req, res, next) => {
    try {
      const page = getPageById(db, Number(req.params.id));
      if (!page) return next();
      savePage(db, { ...page, status: page.status === 'published' ? 'draft' : 'published', blocks: page.blocks }, req.user);
      logActivity(db, { user: req.user, action: 'page.status_changed', entity: 'page', entityId: page.id, ip: clientIp(req), meta: { status: page.status === 'published' ? 'draft' : 'published' } });
      return redirectOk(res, `/admin/pages/${page.id}`, page.status === 'published' ? 'Moved to draft' : 'Published');
    } catch (err) { return next(err); }
  });

  router.post('/pages/:id/revisions/:rev/restore', guard('content:write'), (req, res, next) => {
    try {
      restoreRevision(db, Number(req.params.id), Number(req.params.rev), req.user);
      return redirectOk(res, `/admin/pages/${req.params.id}`, 'Revision restored');
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------- projects */
  router.get('/projects', (req, res) => {
    const projects = listProjects(db, { status: req.query.status || undefined, category: req.query.category || undefined, limit: 500 }).rows;
    res.render('admin/projects/index', {
      title: 'Projects', active: 'projects', projects,
      filter: { status: req.query.status || '', category: req.query.category || '' },
    });
  });

  router.get('/projects/new', guard('content:write'), (req, res) => {
    res.render('admin/projects/edit', {
      title: 'New project', active: 'projects', project: null,
      values: { title: '', slug: '', category: 'residential', status: 'draft', gallery: [], facts: [], sort_order: 0 },
      errors: {},
    });
  });

  router.get('/projects/:id', (req, res, next) => {
    const project = getProjectById(db, Number(req.params.id));
    if (!project) return next();
    res.render('admin/projects/edit', { title: `Edit: ${project.title}`, active: 'projects', project, values: project, errors: {} });
  });

  function saveProjectFromForm(req, res, next, id) {
    try {
      const body = { ...req.body };
      if (id) body.id = id;
      const saved = saveProject(db, projectPayload(body));
      logActivity(db, { user: req.user, action: id ? 'project.updated' : 'project.created', entity: 'project', entityId: saved.id, ip: clientIp(req) });
      return redirectOk(res, `/admin/projects/${saved.id}`, 'Project saved');
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const existing = id ? getProjectById(db, id) : null;
        return res.status(422).render('admin/projects/edit', {
          title: id ? 'Edit project' : 'New project', active: 'projects', project: existing,
          values: { ...req.body, gallery: safeJson(req.body.gallery, []), facts: safeJson(req.body.facts, []) },
          errors: err.details || {},
        });
      }
      return next(err);
    }
  }

  router.post('/projects', guard('content:write'), (req, res, next) => saveProjectFromForm(req, res, next, null));
  router.post('/projects/:id', guard('content:write'), (req, res, next) => saveProjectFromForm(req, res, next, Number(req.params.id)));
  router.post('/projects/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      deleteProject(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'project.deleted', entity: 'project', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/projects', 'Project deleted');
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------- insights */
  router.get('/insights', (req, res) => {
    const { rows } = listInsights(db, { status: req.query.status || undefined, limit: 500 });
    res.render('admin/insights/index', { title: 'Insights', active: 'insights', insights: rows, filter: { status: req.query.status || '' } });
  });

  router.get('/insights/new', guard('content:write'), (req, res) => {
    res.render('admin/insights/edit', {
      title: 'New insight', active: 'insights', insight: null,
      values: { title: '', slug: '', category: 'Design notes', reading_time: '4 min read', status: 'draft', author: 'Woodex Studio' },
      errors: {},
    });
  });

  router.get('/insights/:id', (req, res, next) => {
    const insight = getInsightById(db, Number(req.params.id));
    if (!insight) return next();
    res.render('admin/insights/edit', { title: `Edit: ${insight.title}`, active: 'insights', insight, values: insight, errors: {} });
  });

  function saveInsightFromForm(req, res, next, id) {
    try {
      const body = { ...req.body };
      if (id) body.id = id;
      const saved = saveInsight(db, insightPayload(body));
      logActivity(db, { user: req.user, action: id ? 'insight.updated' : 'insight.created', entity: 'insight', entityId: saved.id, ip: clientIp(req) });
      return redirectOk(res, `/admin/insights/${saved.id}`, 'Insight saved');
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const existing = id ? getInsightById(db, id) : null;
        return res.status(422).render('admin/insights/edit', {
          title: id ? 'Edit insight' : 'New insight', active: 'insights', insight: existing,
          values: req.body, errors: err.details || {},
        });
      }
      return next(err);
    }
  }

  router.post('/insights', guard('content:write'), (req, res, next) => saveInsightFromForm(req, res, next, null));
  router.post('/insights/:id', guard('content:write'), (req, res, next) => saveInsightFromForm(req, res, next, Number(req.params.id)));
  router.post('/insights/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      deleteInsight(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'insight.deleted', entity: 'insight', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/insights', 'Insight deleted');
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------- services */
  router.get('/services', (req, res) => {
    res.render('admin/services/index', { title: 'Services', active: 'services', services: listServices(db) });
  });

  router.get('/services/new', guard('content:write'), (req, res) => {
    res.render('admin/services/edit', {
      title: 'New service', active: 'services', service: null,
      values: { title: '', slug: '', status: 'draft', deliverables: [], sort_order: 0 }, errors: {},
    });
  });

  router.get('/services/:id', (req, res, next) => {
    const service = getServiceById(db, Number(req.params.id));
    if (!service) return next();
    res.render('admin/services/edit', { title: `Edit: ${service.title}`, active: 'services', service, values: service, errors: {} });
  });

  function saveServiceFromForm(req, res, next, id) {
    try {
      const body = { ...req.body };
      if (id) body.id = id;
      const saved = saveService(db, servicePayload(body));
      logActivity(db, { user: req.user, action: id ? 'service.updated' : 'service.created', entity: 'service', entityId: saved.id, ip: clientIp(req) });
      return redirectOk(res, `/admin/services/${saved.id}`, 'Service saved');
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const existing = id ? getServiceById(db, id) : null;
        return res.status(422).render('admin/services/edit', {
          title: id ? 'Edit service' : 'New service', active: 'services', service: existing,
          values: { ...req.body, deliverables: safeJson(req.body.deliverables, []) }, errors: err.details || {},
        });
      }
      return next(err);
    }
  }

  router.post('/services', guard('content:write'), (req, res, next) => saveServiceFromForm(req, res, next, null));
  router.post('/services/:id', guard('content:write'), (req, res, next) => saveServiceFromForm(req, res, next, Number(req.params.id)));
  router.post('/services/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      deleteService(db, Number(req.params.id));
      logActivity(db, { user: req.user, action: 'service.deleted', entity: 'service', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/services', 'Service deleted');
    } catch (err) { return next(err); }
  });

  /* ---------------------------------------------------------------- media */
  router.get('/media', (req, res) => {
    const media = db.all('SELECT * FROM media ORDER BY id DESC');
    res.render('admin/media', {
      title: 'Media library', active: 'media', media,
      uploaded: typeof req.query.uploaded === 'string' ? req.query.uploaded : '',
    });
  });

  router.post('/media', guard('media:write'), upload.single('file'), (req, res, next) => {
    try {
      if (!csrfValid(req, readCsrf(req))) {
        return res.status(403).render('admin/error', {
          title: 'Security check failed',
          message: 'Your form session expired. Reload the page and submit again.',
          active: '', flash: { ok: '', err: '', notice: '' },
        });
      }
      if (!req.file) return res.redirect('/admin/media?err=' + encodeURIComponent('Choose an image to upload.'));
      const out = db.run(
        'INSERT INTO media (filename, original_name, mime, size, alt, caption, created_by) VALUES (?,?,?,?,?,?,?)',
        req.file.filename, path.basename(req.file.originalname).slice(0, 200), req.file.mimetype,
        req.file.size, String(req.body?.alt || '').slice(0, 300) || null, null, req.user.id,
      );
      logActivity(db, { user: req.user, action: 'media.uploaded', entity: 'media', entityId: out.lastInsertRowid, ip: clientIp(req) });
      return res.redirect('/admin/media?uploaded=' + encodeURIComponent(req.file.filename));
    } catch (err) { return next(err); }
  });

  router.post('/media/:id', guard('media:write'), (req, res, next) => {
    try {
      db.run('UPDATE media SET alt = ?, caption = ? WHERE id = ?',
        String(req.body?.alt || '').slice(0, 300) || null,
        String(req.body?.caption || '').slice(0, 300) || null,
        Number(req.params.id));
      return redirectOk(res, '/admin/media', 'Media updated');
    } catch (err) { return next(err); }
  });

  router.post('/media/:id/delete', guard('media:write'), (req, res, next) => {
    try {
      const media = db.get('SELECT * FROM media WHERE id = ?', Number(req.params.id));
      if (media) {
        db.run('DELETE FROM media WHERE id = ?', media.id);
        const p = path.join(config.uploadDir, path.basename(media.filename));
        if (fs.existsSync(p)) fs.unlinkSync(p);
        logActivity(db, { user: req.user, action: 'media.deleted', entity: 'media', entityId: media.id, ip: clientIp(req) });
      }
      return redirectOk(res, '/admin/media', 'Media deleted');
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------ enquiries */
  router.get('/enquiries', guard('enquiries:read'), (req, res) => {
    const status = typeof req.query.status === 'string' ? req.query.status : 'all';
    const q = typeof req.query.q === 'string' ? req.query.q : '';
    const { rows, total } = listEnquiries(db, { status, q, limit: 200 });
    res.render('admin/enquiries/index', { title: 'Enquiries', active: 'enquiries', enquiries: rows, total, filter: { status, q } });
  });

  router.get('/enquiries/export.csv', guard('enquiries:read'), (req, res) => {
    const { rows } = listEnquiries(db, { status: req.query.status || 'all', limit: 5000 });
    const csv = toCsv(rows, [
      { label: 'ID', key: 'id' }, { label: 'Received', key: 'created_at' }, { label: 'Name', key: 'name' },
      { label: 'Email', key: 'email' }, { label: 'Phone', key: 'phone' }, { label: 'Location', key: 'location' },
      { label: 'Service', key: 'service' }, { label: 'Timeline', key: 'timeline' }, { label: 'Status', key: 'status' },
      { label: 'Message', key: 'message' }, { label: 'Notes', key: 'notes' },
    ]);
    res.set({
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': `attachment; filename="woodex-enquiries-${new Date().toISOString().slice(0, 10)}.csv"`,
      'Cache-Control': 'no-store',
    });
    res.send(csv);
  });

  router.get('/enquiries/:id', guard('enquiries:read'), (req, res, next) => {
    const enquiry = db.get('SELECT * FROM enquiries WHERE id = ?', Number(req.params.id));
    if (!enquiry) return next();
    res.render('admin/enquiries/show', { title: `Enquiry #${enquiry.id}`, active: 'enquiries', enquiry });
  });

  router.post('/enquiries/:id', guard('enquiries:write'), (req, res, next) => {
    try {
      const enquiry = db.get('SELECT * FROM enquiries WHERE id = ?', Number(req.params.id));
      if (!enquiry) return next();
      const status = ['new', 'in_review', 'quoted', 'won', 'lost', 'spam'].includes(req.body.status) ? req.body.status : enquiry.status;
      db.run("UPDATE enquiries SET status = ?, notes = ?, updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?",
        status, String(req.body.notes || '').slice(0, 4000), enquiry.id);
      logActivity(db, { user: req.user, action: 'enquiry.updated', entity: 'enquiry', entityId: enquiry.id, ip: clientIp(req), meta: { status } });
      return redirectOk(res, `/admin/enquiries/${enquiry.id}`, 'Enquiry updated');
    } catch (err) { return next(err); }
  });

  router.post('/enquiries/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      db.run('DELETE FROM enquiries WHERE id = ?', Number(req.params.id));
      logActivity(db, { user: req.user, action: 'enquiry.deleted', entity: 'enquiry', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/enquiries', 'Enquiry deleted');
    } catch (err) { return next(err); }
  });

  /* ----------------------------------------------------------- navigation */
  router.get('/navigation', (req, res) => {
    const items = db.all('SELECT * FROM nav_items ORDER BY area, sort_order, id');
    const groups = {
      main: items.filter((i) => i.area === 'main'),
      footer_explore: items.filter((i) => i.area === 'footer_explore'),
      footer_services: items.filter((i) => i.area === 'footer_services'),
      footer_legal: items.filter((i) => i.area === 'footer_legal'),
    };
    res.render('admin/navigation', { title: 'Navigation', active: 'navigation', groups, items });
  });

  router.post('/navigation', guard('content:write'), (req, res, next) => {
    try {
      for (const item of db.all('SELECT id FROM nav_items')) {
        const label = req.body[`label_${item.id}`];
        if (label === undefined) continue;
        db.run('UPDATE nav_items SET label = ?, href = ?, visible = ?, sort_order = ? WHERE id = ?',
          String(label).slice(0, 80),
          String(req.body[`href_${item.id}`] || '/').slice(0, 300),
          req.body[`visible_${item.id}`] ? 1 : 0,
          Number(req.body[`sort_${item.id}`]) || 0,
          item.id);
      }
      logActivity(db, { user: req.user, action: 'navigation.updated', entity: 'navigation', ip: clientIp(req) });
      return redirectOk(res, '/admin/navigation', 'Navigation saved');
    } catch (err) { return next(err); }
  });

  router.post('/navigation/add', guard('content:write'), (req, res, next) => {
    try {
      const area = ['main', 'footer_explore', 'footer_services', 'footer_legal'].includes(req.body.area) ? req.body.area : 'main';
      const label = String(req.body.label || '').trim().slice(0, 80);
      const href = String(req.body.href || '/').trim().slice(0, 300);
      if (!label) throw invalid({ label: 'Label is required.' });
      db.run('INSERT INTO nav_items (label, href, area, sort_order, visible) VALUES (?,?,?,?,1)', label, href, area, Number(req.body.sort_order) || 99);
      logActivity(db, { user: req.user, action: 'navigation.item_added', entity: 'navigation', ip: clientIp(req), meta: { label, href, area } });
      return redirectOk(res, '/admin/navigation', 'Link added');
    } catch (err) {
      if (err instanceof ApiError) return res.redirect('/admin/navigation?err=' + encodeURIComponent(err.message));
      return next(err);
    }
  });

  router.post('/navigation/:id/delete', guard('content:write'), (req, res, next) => {
    try {
      db.run('DELETE FROM nav_items WHERE id = ?', Number(req.params.id));
      return redirectOk(res, '/admin/navigation', 'Link removed');
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------- settings */
  router.get('/settings', guard('settings:write'), (req, res) => {
    const site = getSiteConfig(db);
    const values = {};
    for (const key of EDITABLE_SETTINGS) values[key] = site[key] ?? '';
    res.render('admin/settings', { title: 'Settings', active: 'settings', values, editable: EDITABLE_SETTINGS });
  });

  router.post('/settings', guard('settings:write'), (req, res, next) => {
    try {
      const updates = {};
      for (const key of EDITABLE_SETTINGS) {
        if (Object.prototype.hasOwnProperty.call(req.body, key)) updates[key] = String(req.body[key] ?? '').slice(0, 5000);
      }
      const changed = setSettings(db, updates);
      logActivity(db, { user: req.user, action: 'settings.updated', entity: 'settings', ip: clientIp(req), meta: { changed } });
      return redirectOk(res, '/admin/settings', `${changed.length} setting(s) saved`);
    } catch (err) { return next(err); }
  });

  /* ---------------------------------------------------------------- users */
  router.get('/users', guard('users:manage'), (req, res) => {
    res.render('admin/users', {
      title: 'Users', active: 'users',
      users: db.all('SELECT id, email, name, role, status, last_login_at, created_at FROM users ORDER BY id'),
    });
  });

  router.post('/users', guard('users:manage'), (req, res, next) => {
    try {
      const email = String(req.body.email || '').toLowerCase().trim();
      const name = String(req.body.name || '').trim();
      const password = String(req.body.password || '');
      const role = ['owner', 'editor', 'viewer'].includes(req.body.role) ? req.body.role : 'editor';
      if (!/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(email)) throw invalid({ email: 'Enter a valid email.' });
      if (!name) throw invalid({ name: 'Name is required.' });
      const problems = passwordProblems(password);
      if (problems.length) throw invalid({ password: problems.join(' ') });
      if (db.get('SELECT id FROM users WHERE email = ?', email)) throw invalid({ email: 'That email is already registered.' });
      db.run('INSERT INTO users (email, name, role, password_hash) VALUES (?,?,?,?)', email, name, role, hashPassword(password));
      logActivity(db, { user: req.user, action: 'user.created', entity: 'user', ip: clientIp(req), meta: { email, role } });
      return redirectOk(res, '/admin/users', 'User created');
    } catch (err) {
      if (err instanceof ApiError) return res.redirect('/admin/users?err=' + encodeURIComponent(err.message));
      return next(err);
    }
  });

  router.post('/users/:id', guard('users:manage'), (req, res, next) => {
    try {
      const user = db.get('SELECT * FROM users WHERE id = ?', Number(req.params.id));
      if (!user) return next();
      const role = ['owner', 'editor', 'viewer'].includes(req.body.role) ? req.body.role : user.role;
      const status = ['active', 'disabled'].includes(req.body.status) ? req.body.status : user.status;
      const owners = db.get("SELECT COUNT(*) AS n FROM users WHERE role='owner' AND status='active'").n;
      if (user.role === 'owner' && (role !== 'owner' || status !== 'active') && owners <= 1) {
        return res.redirect('/admin/users?err=' + encodeURIComponent('The last active owner cannot be demoted or disabled.'));
      }
      db.run("UPDATE users SET role=?, status=?, name=?, updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id=?",
        role, status, String(req.body.name || user.name).slice(0, 120), user.id);
      if (req.body.password) {
        const problems = passwordProblems(String(req.body.password));
        if (problems.length) return res.redirect('/admin/users?err=' + encodeURIComponent(problems.join(' ')));
        db.run('UPDATE users SET password_hash = ? WHERE id = ?', hashPassword(String(req.body.password)), user.id);
        db.run('DELETE FROM sessions WHERE user_id = ?', user.id);
      }
      logActivity(db, { user: req.user, action: 'user.updated', entity: 'user', entityId: user.id, ip: clientIp(req), meta: { role, status } });
      return redirectOk(res, '/admin/users', 'User updated');
    } catch (err) { return next(err); }
  });

  router.post('/users/:id/delete', guard('users:manage'), (req, res, next) => {
    try {
      const user = db.get('SELECT * FROM users WHERE id = ?', Number(req.params.id));
      if (!user) return next();
      if (user.id === req.user.id) return res.redirect('/admin/users?err=' + encodeURIComponent('You cannot delete your own account.'));
      const owners = db.get("SELECT COUNT(*) AS n FROM users WHERE role='owner' AND status='active'").n;
      if (user.role === 'owner' && owners <= 1) return res.redirect('/admin/users?err=' + encodeURIComponent('The last owner cannot be deleted.'));
      db.run('DELETE FROM users WHERE id = ?', user.id);
      logActivity(db, { user: req.user, action: 'user.deleted', entity: 'user', entityId: user.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/users', 'User deleted');
    } catch (err) { return next(err); }
  });

  /* --------------------------------------------------------------- account */
  router.get('/account', (req, res) => {
    res.render('admin/account', {
      title: 'My account', active: 'account',
      account: db.get('SELECT id, email, name, role, last_login_at, created_at FROM users WHERE id = ?', req.user.id),
      form: {}, errors: {},
    });
  });

  router.post('/account', (req, res, next) => {
    try {
      const me = db.get('SELECT * FROM users WHERE id = ?', req.user.id);
      if (!me) return next();
      const name = String(req.body.name || '').trim().slice(0, 120) || me.name;
      const email = String(req.body.email || '').toLowerCase().trim() || me.email;
      const current = String(req.body.current_password || '');
      const next_ = String(req.body.new_password || '');
      const confirm = String(req.body.confirm_password || '');

      const errors = {};
      if (!/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(email)) errors.email = 'Enter a valid email address.';
      const taken = db.get('SELECT id FROM users WHERE email = ? AND id <> ?', email, me.id);
      if (taken) errors.email = 'That email is already used by another account.';

      const wantsPassword = Boolean(next_ || confirm);
      if (wantsPassword) {
        if (!verifyPassword(current, me.password_hash)) errors.current_password = 'Your current password is not correct.';
        if (next_ !== confirm) errors.confirm_password = 'The two new passwords do not match.';
        const problems = passwordProblems(next_);
        if (problems.length) errors.new_password = problems.join(' ');
      }

      if (Object.keys(errors).length) {
        return res.status(422).render('admin/account', {
          title: 'My account', active: 'account',
          account: { ...me, name, email },
          form: { name, email },
          errors,
        });
      }

      db.run("UPDATE users SET name = ?, email = ?, updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", name, email, me.id);
      if (wantsPassword) {
        db.run('UPDATE users SET password_hash = ? WHERE id = ?', hashPassword(next_), me.id);
        // Sign out every other device, keep this session alive.
        db.run('DELETE FROM sessions WHERE user_id = ? AND id <> ?', me.id, req.session.id);
        db.run('UPDATE sessions SET csrf = ? WHERE id = ?', crypto.randomBytes(24).toString('hex'), req.session.id);
        logActivity(db, { user: req.user, action: 'account.password_changed', entity: 'user', entityId: me.id, ip: clientIp(req) });
        return redirectOk(res, '/admin/account', 'Password updated — other devices were signed out');
      }
      logActivity(db, { user: req.user, action: 'account.updated', entity: 'user', entityId: me.id, ip: clientIp(req), meta: { email } });
      return redirectOk(res, '/admin/account', 'Account updated');
    } catch (err) { return next(err); }
  });

  /* --------------------------------------------------------------- tokens */
  router.get('/tokens', guard('tokens:manage'), (req, res) => {
    res.render('admin/tokens', {
      title: 'API tokens', active: 'tokens',
      tokens: db.all('SELECT id, label, scopes, last_used_at, expires_at, revoked_at, created_at FROM api_tokens ORDER BY id DESC'),
      issued: typeof req.query.issued === 'string' ? req.query.issued : '',
    });
  });

  router.post('/tokens', guard('tokens:manage'), (req, res, next) => {
    try {
      const label = String(req.body.label || '').trim().slice(0, 100) || 'Untitled token';
      const scopes = (Array.isArray(req.body.scopes) ? req.body.scopes : [req.body.scopes || 'content:read'])
        .filter((s) => ['content:read', 'content:write', 'enquiries:read'].includes(s));
      if (!scopes.length) return res.redirect('/admin/tokens?err=' + encodeURIComponent('Choose at least one scope.'));
      const token = `wox_${crypto.randomBytes(24).toString('hex')}`;
      const hash = crypto.createHash('sha256').update(token).digest('hex');
      db.run('INSERT INTO api_tokens (user_id, label, token_hash, scopes) VALUES (?,?,?,?)', req.user.id, label, hash, scopes.join(','));
      logActivity(db, { user: req.user, action: 'token.created', entity: 'api_token', ip: clientIp(req), meta: { scopes } });
      return res.redirect('/admin/tokens?issued=' + encodeURIComponent(token));
    } catch (err) { return next(err); }
  });

  router.post('/tokens/:id/revoke', guard('tokens:manage'), (req, res, next) => {
    try {
      db.run("UPDATE api_tokens SET revoked_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", Number(req.params.id));
      logActivity(db, { user: req.user, action: 'token.revoked', entity: 'api_token', entityId: req.params.id, ip: clientIp(req) });
      return redirectOk(res, '/admin/tokens', 'Token revoked');
    } catch (err) { return next(err); }
  });

  /* -------------------------------------------------------- system screens */
  router.get('/activity', guard('audit:read'), (req, res) => {
    res.render('admin/activity', { title: 'Activity log', active: 'activity', activity: recentActivity(db, 200) });
  });

  router.get('/health', (req, res) => {
    const site = getSiteConfig(db);
    let uploadsWritable = false;
    try { fs.accessSync(config.uploadDir, fs.constants.W_OK); uploadsWritable = true; } catch { /* not writable */ }
    const pages = listPages(db);
    res.render('admin/health', {
      title: 'Health & QA', active: 'health',
      checks: [
        { id: 'database', label: 'Database', status: 'ok', detail: 'SQLite reachable' },
        { id: 'uploads', label: 'Uploads directory', status: uploadsWritable ? 'ok' : 'fail', detail: config.uploadDir },
        { id: 'base_url', label: 'Canonical base URL', status: site['site.url'].startsWith('https://') ? 'ok' : 'warn', detail: site['site.url'] },
        { id: 'secure_cookies', label: 'Secure cookies', status: config.secureCookies ? 'ok' : 'warn', detail: config.secureCookies ? 'Enabled' : 'Only enabled with HTTPS in production' },
        { id: 'pages', label: 'Published pages', status: 'ok', detail: `${pages.filter((p) => p.status === 'published').length} of ${pages.length}` },
      ],
      health: contentHealth(db, site),
    });
  });

  router.post('/export', guard('system:export'), async (req, res, next) => {
    try {
      const site = getSiteConfig(db);
      const configured = String(site['system.export_dir'] || 'dist').trim();
      const target = path.isAbsolute(configured)
        ? configured
        : path.resolve(path.dirname(config.dbPath), '..', configured);
      const result = await exportStatic(db, { ...config, baseUrl: site['site.url'] }, { targetDir: target });
      db.run('INSERT INTO export_runs (target, pages, files, status) VALUES (?,?,?,?)', result.target, result.pages, result.files, 'ok');
      logActivity(db, { user: req.user, action: 'site.exported', entity: 'system', ip: clientIp(req), meta: { target: result.target, files: result.files } });
      return redirectOk(res, '/admin/health', `Exported ${result.files} files to ${result.target}`);
    } catch (err) { return next(err); }
  });

  /* ------------------------------------------------------------------ 404 */
  router.use((req, res) => res.status(404).render('admin/error', {
    title: 'Not found', message: 'That admin screen does not exist.', active: '',
  }));
  // eslint-disable-next-line no-unused-vars
  router.use((err, req, res, next) => {
    if (err instanceof ApiError) {
      return res.status(err.status).render('admin/error', {
        title: 'Request failed',
        message: err.message,
        active: '',
        errors: err.details || {},
      });
    }
    console.error('[woodex-os admin]', err);
    return res.status(500).render('admin/error', {
      title: 'Something went wrong',
      message: 'The action could not be completed. Check the server log.',
      active: '',
    });
  });

  return router;
}

function safeJson(value, fallback) {
  if (value == null || value === '') return fallback;
  if (typeof value === 'object') return value;
  try { return JSON.parse(value); } catch { return fallback; }
}

