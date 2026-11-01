import express from 'express';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { ApiError } from '../lib/http.js';
import { buildMeta, organizationLd, websiteLd, breadcrumbLd, articleLd, faqLd, projectLd, jsonLdScript } from '../lib/seo.js';
import {
  getPageBySlug, listPages, getPublishedPages, listProjects, getProjectBySlug, listInsights,
  getInsightBySlug, listServices, createEnquiry, sitemapEntries, listEnquiries,
} from '../lib/content.js';
import { logActivity } from '../lib/audit.js';
import { createRateLimiter, clientIp, parseCookies } from '../lib/auth.js';
import { gather } from '../lib/payloads.js';
import { vEmail, vString, vEnum, invalid } from '../lib/http.js';

const SERVICE_CHOICES = ['residential', 'commercial', 'bespoke', 'styling', 'renovation', 'consultation'];
const FORM_COOKIE = 'woodex_form';

export function createSiteRouter({ db, config, getSiteConfig }) {
  const router = express.Router();
  const enquiryLimiter = createRateLimiter({
    windowMs: config.rateLimitWindowMs,
    max: config.enquiryMaxPerWindow,
    keyFn: (req) => `enquiry:${clientIp(req)}`,
  });

  /** Shared locals for every public page. */
  function baseLocals(req, extra = {}) {
    const site = getSiteConfig(db);
    return {
      config: site,
      path: req.path,
      projects: listProjects(db, { status: 'published', limit: 200 }).rows,
      insights: listInsights(db, { status: 'published', limit: 100 }).rows,
      services: listServices(db, { status: 'published' }),
      ...extra,
    };
  }

  function renderSite(res, bodyTemplate, locals, status = 200) {
    return res.status(status).render('site/shell', { bodyTemplate, ...locals });
  }

  /* --------------------------------------------------------------- home */
  router.get('/', (req, res) => {
    const page = getPageBySlug(db, 'home') || getPageBySlug(db, 'index');
    if (!page) {
      return res.status(503).render('site/error', {
        page: { title: 'Site not published' },
        meta: { title: 'Site not published', description: 'Run the seed script to publish content.', robots: 'noindex,nofollow', canonical: '', ogImage: '', siteName: getSiteConfig(db)['site.name'], type: 'website', twitterCard: 'summary' },
        path: req.path,
        jsonLd: [],
      });
    }
    const meta = buildMeta({ page, config: getSiteConfig(db), path: '/' });
    return renderSite(res, 'pages/home', {
      ...baseLocals(req, { page, meta }),
      jsonLd: [organizationLd(getSiteConfig(db)), websiteLd(getSiteConfig(db))],
    });
  });

  /* ------------------------------------------------------------ portfolio */
  router.get('/portfolio', (req, res) => {
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, 'portfolio') || { title: 'Portfolio', blocks: [] };
    const category = typeof req.query.category === 'string' ? req.query.category : 'all';
    const all = listProjects(db, { status: 'published', limit: 200 }).rows;
    const projects = category === 'all' ? all : all.filter((p) => p.category === category);
    const categories = ['all', ...new Set(all.map((p) => p.category))];
    return renderSite(res, 'pages/portfolio', {
      ...baseLocals(req, { page, projects, categories, activeCategory: category }),
      meta: buildMeta({ page, config: site, path: '/portfolio', fallbackTitle: 'Portfolio' }),
      jsonLd: [breadcrumbLd([{ label: 'Home', href: '/' }, { label: 'Portfolio', href: '/portfolio' }], site['site.url'])],
    });
  });

  router.get('/portfolio/:slug', (req, res, next) => {
    const project = getProjectBySlug(db, req.params.slug);
    if (!project) return next();
    const site = getSiteConfig(db);
    const page = {
      title: project.title,
      seo_title: project.seo_title,
      seo_description: project.seo_description,
      og_image: project.cover_image,
    };
    return renderSite(res, 'pages/project', {
      ...baseLocals(req, { page, project }),
      meta: buildMeta({ page, config: site, path: `/portfolio/${project.slug}`, type: 'article' }),
      jsonLd: [
        projectLd(project, site),
        breadcrumbLd([
          { label: 'Home', href: '/' },
          { label: 'Portfolio', href: '/portfolio' },
          { label: project.title, href: `/portfolio/${project.slug}` },
        ], site['site.url']),
      ],
    });
  });

  /* ------------------------------------------------------------- insights */
  router.get('/insights', (req, res) => {
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, 'insights') || { title: 'Insights', blocks: [] };
    return renderSite(res, 'pages/insights', {
      ...baseLocals(req, { page }),
      meta: buildMeta({ page, config: site, path: '/insights', fallbackTitle: 'Insights' }),
      jsonLd: [breadcrumbLd([{ label: 'Home', href: '/' }, { label: 'Insights', href: '/insights' }], site['site.url'])],
    });
  });

  router.get('/insights/:slug', (req, res, next) => {
    const insight = getInsightBySlug(db, req.params.slug);
    if (!insight) return next();
    const site = getSiteConfig(db);
    const page = {
      title: insight.title,
      seo_title: insight.seo_title,
      seo_description: insight.seo_description,
      og_image: insight.cover_image,
    };
    return renderSite(res, 'pages/insight', {
      ...baseLocals(req, { page, insight }),
      meta: buildMeta({ page, config: site, path: `/insights/${insight.slug}`, type: 'article' }),
      jsonLd: [
        articleLd(insight, site),
        breadcrumbLd([
          { label: 'Home', href: '/' },
          { label: 'Insights', href: '/insights' },
          { label: insight.title, href: `/insights/${insight.slug}` },
        ], site['site.url']),
      ],
    });
  });

  /* ------------------------------------------------------------- services */
  router.get('/services', (req, res) => {
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, 'services') || { title: 'Services', blocks: [] };
    return renderSite(res, 'pages/services', {
      ...baseLocals(req, { page }),
      meta: buildMeta({ page, config: site, path: '/services', fallbackTitle: 'Services' }),
      jsonLd: [breadcrumbLd([{ label: 'Home', href: '/' }, { label: 'Services', href: '/services' }], site['site.url'])],
    });
  });

  /* -------------------------------------------------------------- contact */
  router.get('/contact', (req, res) => {
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, 'contact') || { title: 'Contact', blocks: [] };
    let formToken = parseCookies(req.headers.cookie || '')[FORM_COOKIE];
    if (!formToken) {
      formToken = crypto.randomBytes(24).toString('hex');
      res.cookie(FORM_COOKIE, formToken, {
        httpOnly: true, sameSite: 'lax', secure: config.secureCookies, maxAge: 60 * 60 * 1000, path: '/',
      });
    }
    const faqBlock = (page.blocks || []).find((b) => b.type === 'faq');
    return renderSite(res, 'pages/contact', {
      ...baseLocals(req, { page, formToken, values: {}, errors: {}, submitted: false }),
      meta: buildMeta({ page, config: site, path: '/contact', fallbackTitle: 'Contact' }),
      jsonLd: [
        breadcrumbLd([{ label: 'Home', href: '/' }, { label: 'Contact', href: '/contact' }], site['site.url']),
        ...(faqBlock?.items?.length ? [faqLd(faqBlock.items)] : []),
      ],
    });
  });

  router.post('/contact', enquiryLimiter, (req, res, next) => {
    try {
      const site = getSiteConfig(db);
      const page = getPageBySlug(db, 'contact') || { title: 'Contact', blocks: [] };
      const body = req.body || {};
      const cookies = parseCookies(req.headers.cookie || '');
      const tokenOk = cookies[FORM_COOKIE] && body._csrf && cookies[FORM_COOKIE] === body._csrf;
      const honeypotField = site['enquiry.honeypot'] || 'company_website';

      if (String(body[honeypotField] || '').trim()) {
        // Bot: pretend everything went fine, store nothing.
        return res.redirect(303, site['enquiry.success_path'] || '/thank-you');
      }

      const values = {
        name: String(body.name || '').slice(0, 100).trim(),
        email: String(body.email || '').slice(0, 200).trim(),
        phone: String(body.phone || '').slice(0, 40).trim(),
        location: String(body.location || '').slice(0, 150).trim(),
        service: String(body.service || ''),
        timeline: String(body.timeline || ''),
        message: String(body.message || '').slice(0, 5000).trim(),
        consent: body.consent ? '1' : '',
      };

      const errors = {};
      if (!tokenOk) errors._form = 'Your form session expired. Please submit the form again.';
      try {
        gather({
          name: () => vString(values.name, { field: 'name', required: true, min: 2, max: 100, label: 'Your name' }),
          email: () => vEmail(values.email),
          phone: () => {
            if (values.phone && !/^[+()\-\s\d.]{6,40}$/.test(values.phone)) {
              throw invalid({ phone: 'Use digits, spaces or + ( ) - only.' });
            }
            return values.phone;
          },
          service: () => vEnum(values.service, SERVICE_CHOICES, { field: 'service' }),
          message: () => vString(values.message, { field: 'message', required: true, min: 10, max: 5000, label: 'Project brief' }),
        });
      } catch (err) {
        if (err && err.details) Object.assign(errors, err.details);
        else throw err;
      }
      if (errors.service) errors.service = 'Choose the service you are interested in.';
      if (!values.consent) errors.consent = 'Please accept the privacy note so we can reply.';

      if (Object.keys(errors).length) {
        let formToken = cookies[FORM_COOKIE];
        if (!formToken) {
          formToken = crypto.randomBytes(24).toString('hex');
          res.cookie(FORM_COOKIE, formToken, { httpOnly: true, sameSite: 'lax', secure: config.secureCookies, maxAge: 60 * 60 * 1000, path: '/' });
        }
        return renderSite(res, 'pages/contact', {
          ...baseLocals(req, { page, formToken, values, errors, submitted: false }),
          meta: buildMeta({ page, config: site, path: '/contact', fallbackTitle: 'Contact' }),
          jsonLd: [],
        }, 422);
      }

      const enquiry = createEnquiry(db, values, {
        ip: clientIp(req), userAgent: req.get('user-agent') || '', source: 'website:contact',
      });
      logActivity(db, {
        user: null, action: 'enquiry.created', entity: 'enquiry', entityId: enquiry.id,
        ip: clientIp(req), meta: { service: values.service, source: 'website' },
      });
      res.clearCookie(FORM_COOKIE, { path: '/' });
      return res.redirect(303, site['enquiry.success_path'] || '/thank-you');
    } catch (err) {
      return next(err);
    }
  });

  router.get('/thank-you', (req, res) => {
    const site = getSiteConfig(db);
    const page = getPageBySlug(db, 'thank-you') || {
      title: 'Thank you',
      seo_description: 'Your enquiry is with our studio. We reply within two working days.',
      noindex: true,
      blocks: [],
    };
    return renderSite(res, 'pages/thank-you', {
      ...baseLocals(req, { page }),
      meta: buildMeta({ page: { ...page, noindex: true }, config: site, path: '/thank-you' }),
      jsonLd: [],
    });
  });

  /* --------------------------------------------------------------- search */
  router.get('/search', (req, res) => {
    const site = getSiteConfig(db);
    const q = String(req.query.q || '').trim().slice(0, 80);
    const results = { projects: [], insights: [], pages: [] };
    if (q) {
      const like = `%${q}%`;
      results.projects = db.all("SELECT slug,title,summary,category FROM projects WHERE status='published' AND (title LIKE ? OR summary LIKE ?) LIMIT 20", like, like);
      results.insights = db.all("SELECT slug,title,excerpt,category FROM insights WHERE status='published' AND (title LIKE ? OR excerpt LIKE ?) LIMIT 20", like, like);
      results.pages = listPages(db, { status: 'published', q }).filter((p) => p.page_type !== 'system');
    }
    return renderSite(res, 'pages/search', {
      ...baseLocals(req, { page: { title: q ? `Search: ${q}` : 'Search' }, q, results }),
      meta: buildMeta({ page: { title: 'Search', noindex: true }, config: site, path: '/search' }),
      jsonLd: [],
    });
  });

  /* -------------------------------------------------------------- preview */
  router.get('/preview/:slug', (req, res, next) => {
    if (!req.user) return res.redirect(302, `/admin/login?next=${encodeURIComponent(req.originalUrl)}`);
    const page = getPageBySlug(db, req.params.slug, { includeDrafts: true });
    if (!page) return next();
    const site = getSiteConfig(db);
    return renderSite(res, 'pages/default', {
      ...baseLocals(req, { page }),
      meta: buildMeta({ page: { ...page, noindex: true }, config: site, path: `/${page.slug}` }),
      jsonLd: [],
      preview: true,
    });
  });

  /* ---------------------------------------------------- sitemap / robots / feed */
  router.get('/sitemap.xml', (req, res) => {
    const site = getSiteConfig(db);
    const entries = sitemapEntries(db, site['site.url']);
    res.type('application/xml');
    res.send(`<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${entries.map((e) => `  <url>\n    <loc>${e.url.replace(/&/g, '&amp;')}</loc>\n    <lastmod>${new Date().toISOString().slice(0, 10)}</lastmod>\n    <changefreq>${e.changefreq}</changefreq>\n    <priority>${e.priority}</priority>\n  </url>`).join('\n')}
</urlset>\n`);
  });

  router.get('/site.webmanifest', (req, res) => {
    const site = getSiteConfig(db);
    res.type('application/manifest+json').json({
      name: site['site.name'],
      short_name: site['site.logo_text'],
      start_url: '/',
      display: 'standalone',
      background_color: '#ffffff',
      theme_color: site['theme.navy'],
      icons: [{ src: '/assets/favicon.svg', sizes: 'any', type: 'image/svg+xml' }],
    });
  });

  router.get('/robots.txt', (req, res) => {
    const site = getSiteConfig(db);
    res.type('text/plain').send(
      `User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\n\nSitemap: ${site['site.url'].replace(/\/$/, '')}/sitemap.xml\n`,
    );
  });

  router.get('/feed.xml', (req, res) => {
    const site = getSiteConfig(db);
    const base = site['site.url'].replace(/\/$/, '');
    const posts = listInsights(db, { status: 'published', limit: 20 }).rows;
    const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    res.type('application/rss+xml').send(`<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <title>${esc(site['site.name'])} — Insights</title>
  <link>${base}/insights</link>
  <description>${esc(site['site.description'])}</description>
  <language>en-PK</language>
${posts.map((p) => `  <item>
    <title>${esc(p.title)}</title>
    <link>${base}/insights/${p.slug}</link>
    <guid>${base}/insights/${p.slug}</guid>
    <description>${esc(p.excerpt)}</description>
    <pubDate>${new Date(p.published_at || p.created_at).toUTCString()}</pubDate>
  </item>`).join('\n')}
</channel></rss>\n`);
  });

  /* ------------------------------------------------------- generic pages */
  router.get('/:slug', (req, res, next) => {
    const page = getPageBySlug(db, req.params.slug);
    if (!page) return next();
    const site = getSiteConfig(db);
    const template = page.template === 'home' ? 'pages/home' : page.template === 'contact' ? 'pages/contact' : 'pages/default';
    const faqBlock = (page.blocks || []).find((b) => b.type === 'faq');
    return renderSite(res, template, {
      ...baseLocals(req, { page, formToken: '', values: {}, errors: {}, submitted: false }),
      meta: buildMeta({ page, config: site, path: `/${page.slug}` }),
      jsonLd: [
        breadcrumbLd([{ label: 'Home', href: '/' }, { label: page.title, href: `/${page.slug}` }], site['site.url']),
        ...(faqBlock?.items?.length ? [faqLd(faqBlock.items)] : []),
      ],
    });
  });

  return router;
}
