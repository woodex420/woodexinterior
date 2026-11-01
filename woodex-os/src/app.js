import express from 'express';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { openDatabase } from './db.js';
import { ensureSettings, getSiteConfig } from './lib/settings.js';
import { attachAuth, parseCookies } from './lib/auth.js';
import { excerpt, stripTags } from './lib/sanitize.js';
import { createSiteRouter } from './routes/site.js';
import { createApiRouter } from './routes/api.js';
import { createAdminRouter } from './routes/admin.js';
import { buildMeta } from './lib/seo.js';
import { getPageBySlug } from './lib/content.js';

const here = path.dirname(fileURLToPath(import.meta.url));
export const OS_ROOT = path.resolve(here, '..');
export const REPO_ROOT = path.resolve(OS_ROOT, '..');

export function loadConfig(overrides = {}) {
  const env = process.env;
  const isProd = (env.NODE_ENV || 'development') === 'production';
  return {
    port: Number(env.PORT || 4321),
    host: env.HOST || '0.0.0.0',
    dbPath: env.WOODEX_DB || path.join(OS_ROOT, 'data', 'woodex-os.sqlite'),
    baseUrl: env.WOODEX_BASE_URL || 'http://localhost:4321',
    assetsDir: env.WOODEX_ASSETS_DIR || path.join(REPO_ROOT, 'woodex', 'assets'),
    uploadDir: env.WOODEX_UPLOAD_DIR || path.join(OS_ROOT, 'data', 'uploads'),
    adminDir: path.join(here, 'public', 'admin'),
    viewsDir: path.join(here, 'views'),
    secureCookies: env.WOODEX_SECURE_COOKIES ? env.WOODEX_SECURE_COOKIES === '1' : isProd,
    trustProxy: env.WOODEX_TRUST_PROXY !== '0',
    env: isProd ? 'production' : 'development',
    maxUploadBytes: Number(env.WOODEX_MAX_UPLOAD || 5 * 1024 * 1024),
    loginMaxAttempts: Number(env.WOODEX_LOGIN_MAX || 8),
    enquiryMaxPerWindow: Number(env.WOODEX_ENQUIRY_MAX || 5),
    rateLimitWindowMs: Number(env.WOODEX_RATE_WINDOW_MS || 10 * 60 * 1000),
    ...overrides,
  };
}

export function createApp(options = {}) {
  const config = loadConfig(options);
  const db = options.db || openDatabase(config.dbPath);
  ensureSettings(db);
  fs.mkdirSync(config.uploadDir, { recursive: true });

  const app = express();
  app.disable('x-powered-by');
  if (config.trustProxy) app.set('trust proxy', true);
  app.set('view engine', 'ejs');
  app.set('views', config.viewsDir);

  /* ---------------------------------------------------------- security headers */
  app.use((req, res, next) => {
    res.set({
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'SAMEORIGIN',
      'Referrer-Policy': 'strict-origin-when-cross-origin',
      'Permissions-Policy': 'geolocation=(), microphone=(), camera=()',
      'Cross-Origin-Opener-Policy': 'same-origin',
      'Content-Security-Policy': [
        "default-src 'self'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "object-src 'none'",
      ].join('; '),
    });
    if (config.env === 'production') {
      res.set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
    next();
  });

  app.use(express.urlencoded({ extended: false, limit: '256kb' }));
  app.use(express.json({ limit: '1mb' }));
  app.use(attachAuth(db));

  /* ------------------------------------------------------------------- assets */
  if (fs.existsSync(config.assetsDir)) {
    app.use('/assets', express.static(config.assetsDir, { maxAge: config.env === 'production' ? '30d' : 0, etag: true }));
  }
  app.use('/uploads', express.static(config.uploadDir, { maxAge: config.env === 'production' ? '7d' : 0, etag: true }));
  app.use('/admin-assets', express.static(config.adminDir, { maxAge: config.env === 'production' ? '1h' : 0 }));

  /* -------------------------------------------------------------- locals */
  app.use((req, res, next) => {
    const site = getSiteConfig(db);
    req.site = site;
    res.locals.config = site;
    res.locals.user = req.user;
    res.locals.csrf = req.session?.csrf || '';
    res.locals.currentPath = req.path;
    res.locals.stripTags = stripTags;
    res.locals.excerpt = excerpt;
    res.locals.asset = (p) => (String(p || '').startsWith('/') || String(p || '').startsWith('http') ? p : `/assets/${p}`);
    res.locals.years = new Date().getFullYear();
    res.locals.formatDate = (value, opts = { day: 'numeric', month: 'short', year: 'numeric' }) => {
      if (!value) return '';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return String(value);
      return d.toLocaleDateString('en-GB', { timeZone: 'Asia/Karachi', ...opts });
    };
    res.locals.adminPath = (p = '') => `/admin${p}`;
    res.locals.siteMeta = (page, opts = {}) => buildMeta({ page, config: site, ...opts });
    res.locals.json = (value) => JSON.stringify(value);
    next();
  });

  app.get('/healthz', (req, res) => {
    res.json({ status: 'ok', version: '2.1.0', time: new Date().toISOString() });
  });

  app.use('/api/v1', createApiRouter({ db, config, getSiteConfig }));
  app.use('/admin', createAdminRouter({ db, config, getSiteConfig }));
  app.use('/', createSiteRouter({ db, config, getSiteConfig }));

  /* ------------------------------------------------------------------ 404s */
  app.use((req, res) => {
    if (req.path.startsWith('/api/')) {
      return res.status(404).json({ error: { code: 'not_found', message: 'Endpoint not found.' } });
    }
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, '404', { includeDrafts: true });
    const notFoundPage = page || { title: 'Page not found', seo_title: 'Page not found', blocks: [] };
    notFoundPage.noindex = true;
    return res.status(404).render('site/404', {
      page: notFoundPage,
      meta: buildMeta({ page: notFoundPage, config: site, path: req.path }),
      path: req.path,
      jsonLd: [],
    });
  });

  // eslint-disable-next-line no-unused-vars
  app.use((err, req, res, next) => {
    console.error('[woodex-os] error:', err);
    if (res.headersSent) return;
    if (req.path.startsWith('/api/')) {
      return res.status(err.status || 500).json({
        error: { code: err.code || 'internal_error', message: err.expose ? err.message : 'Something went wrong.' },
      });
    }
    let siteName = 'Woodex Interior';
    try { siteName = getSiteConfig(db)['site.name'] || siteName; } catch { /* database unavailable */ }
    res.status(500).render('site/error', {
      page: { title: 'Something went wrong' },
      meta: { title: 'Something went wrong', description: 'Please try again.', robots: 'noindex,nofollow', canonical: '', ogImage: '', siteName, type: 'website', twitterCard: 'summary' },
      path: req.path,
      jsonLd: [],
    }, (renderError, html) => {
      if (renderError) {
        console.error('[woodex-os] error page failed:', renderError);
        return res.type('text/plain').send('Something went wrong. Please try again.');
      }
      return res.send(html);
    });
  });

  app.locals.db = db;
  return { app, db, config };
}

export { parseCookies };
