import { vString, vSlug, vEnum, vBool, vInt, invalid, ApiError } from './http.js';
import { sanitizeHtml } from './sanitize.js';
import { normalizeBlocks } from './blocks.js';

export function parseMaybeJson(value) {
  if (value == null || value === '') return undefined;
  if (typeof value === 'object') return value;
  try { return JSON.parse(value); } catch { return undefined; }
}

export function parseJsonField(value, field, fallback = []) {
  if (value == null || value === '') return fallback;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    throw invalid({ [field]: 'This field expects valid JSON.' });
  }
}

/**
 * Run every field validator, then report all failures at once. Editors should
 * not have to submit a form three times to discover three problems.
 */
export function gather(spec) {
  const values = {};
  const errors = {};
  for (const [key, validate] of Object.entries(spec)) {
    try {
      values[key] = validate();
    } catch (err) {
      if (err instanceof ApiError && err.details) Object.assign(errors, err.details);
      else throw err;
    }
  }
  if (Object.keys(errors).length) throw invalid(errors);
  return values;
}

export function pagePayload(body) {
  const titleForSlug = typeof body.title === 'string' ? body.title : '';
  const v = gather({
    title: () => vString(body.title, { field: 'title', required: true, max: 200, label: 'Title' }),
    slug: () => vSlug(body.slug, { fallbackSource: titleForSlug }),
    page_type: () => vEnum(body.page_type ?? 'page', ['home', 'page', 'legal', 'system'], { field: 'page_type', fallback: 'page' }),
    template: () => vEnum(body.template ?? 'default', ['default', 'home', 'contact', 'style-guide', '404'], { field: 'template', fallback: 'default' }),
    status: () => vEnum(body.status ?? 'draft', ['draft', 'published'], { field: 'status', fallback: 'draft' }),
    seo_title: () => vString(body.seo_title, { field: 'seo_title', max: 70 }),
    seo_description: () => vString(body.seo_description, { field: 'seo_description', max: 320 }),
    og_image: () => vString(body.og_image, { field: 'og_image', max: 500 }),
    canonical: () => vString(body.canonical, { field: 'canonical', max: 500 }),
    noindex: () => vBool(body.noindex),
    hero_eyebrow: () => vString(body.hero_eyebrow, { field: 'hero_eyebrow', max: 200 }),
    hero_title: () => vString(body.hero_title, { field: 'hero_title', max: 400 }),
    hero_intro: () => vString(body.hero_intro, { field: 'hero_intro', max: 1200 }),
    hero_image: () => vString(body.hero_image, { field: 'hero_image', max: 500 }),
    sort_order: () => vInt(body.sort_order, { field: 'sort_order', fallback: 0 }),
    blocks: () => normalizeBlocks(parseJsonField(body.blocks, 'blocks', [])),
    revisionNote: () => vString(body.revisionNote, { field: 'revisionNote', max: 200 }),
  });
  return { id: body.id ? Number(body.id) : undefined, ...v };
}

export function projectPayload(body) {
  const gallery = parseJsonField(body.gallery, 'gallery', []);
  const facts = parseJsonField(body.facts, 'facts', []);
  if (!Array.isArray(gallery)) throw invalid({ gallery: 'Gallery must be a list of images.' });
  if (!Array.isArray(facts)) throw invalid({ facts: 'Facts must be a list of label/value pairs.' });

  const v = gather({
    title: () => vString(body.title, { field: 'title', required: true, max: 200, label: 'Title' }),
    slug: () => vSlug(body.slug, { fallbackSource: typeof body.title === 'string' ? body.title : '' }),
    category: () => vEnum(body.category ?? 'residential', ['residential', 'commercial', 'bespoke', 'styling', 'renovation'], { field: 'category', fallback: 'residential' }),
    location: () => vString(body.location, { field: 'location', max: 160 }),
    year: () => vString(body.year, { field: 'year', max: 20 }),
    area: () => vString(body.area, { field: 'area', max: 80 }),
    summary: () => vString(body.summary, { field: 'summary', max: 600 }),
    cover_image: () => vString(body.cover_image, { field: 'cover_image', max: 500 }),
    featured: () => vBool(body.featured),
    sort_order: () => vInt(body.sort_order, { field: 'sort_order', fallback: 0 }),
    status: () => vEnum(body.status ?? 'draft', ['draft', 'published'], { field: 'status', fallback: 'draft' }),
    seo_title: () => vString(body.seo_title, { field: 'seo_title', max: 70 }),
    seo_description: () => vString(body.seo_description, { field: 'seo_description', max: 320 }),
  });

  const cleanGallery = gallery.slice(0, 20)
    .map((item) => ({
      src: vString(item?.src, { field: 'gallery', max: 500 }),
      alt: vString(item?.alt, { field: 'gallery', max: 300 }),
    }))
    .filter((item) => item.src);
  const cleanFacts = facts.slice(0, 12)
    .map((item) => ({
      label: vString(item?.label, { field: 'facts', max: 80 }),
      value: vString(item?.value, { field: 'facts', max: 200 }),
    }))
    .filter((item) => item.label);

  return {
    id: body.id ? Number(body.id) : undefined,
    ...v,
    body: sanitizeHtml(String(body.body || '').slice(0, 80000)),
    gallery: cleanGallery,
    facts: cleanFacts,
  };
}

export function insightPayload(body) {
  const v = gather({
    title: () => vString(body.title, { field: 'title', required: true, max: 200, label: 'Title' }),
    slug: () => vSlug(body.slug, { fallbackSource: typeof body.title === 'string' ? body.title : '' }),
    category: () => vString(body.category || 'Design notes', { field: 'category', max: 80 }),
    reading_time: () => vString(body.reading_time || '4 min read', { field: 'reading_time', max: 40 }),
    excerpt: () => vString(body.excerpt, { field: 'excerpt', max: 400 }),
    cover_image: () => vString(body.cover_image, { field: 'cover_image', max: 500 }),
    author: () => vString(body.author || 'Woodex Studio', { field: 'author', max: 120 }),
    featured: () => vBool(body.featured),
    status: () => vEnum(body.status ?? 'draft', ['draft', 'published'], { field: 'status', fallback: 'draft' }),
    published_at: () => vString(body.published_at, { field: 'published_at', max: 40 }) || null,
    seo_title: () => vString(body.seo_title, { field: 'seo_title', max: 70 }),
    seo_description: () => vString(body.seo_description, { field: 'seo_description', max: 320 }),
  });
  return {
    id: body.id ? Number(body.id) : undefined,
    ...v,
    body: sanitizeHtml(String(body.body || '').slice(0, 80000)),
  };
}

export function servicePayload(body) {
  const deliverables = parseJsonField(body.deliverables, 'deliverables', []);
  const v = gather({
    title: () => vString(body.title, { field: 'title', required: true, max: 200, label: 'Title' }),
    slug: () => vSlug(body.slug, { fallbackSource: typeof body.title === 'string' ? body.title : '' }),
    number: () => vString(body.number, { field: 'number', max: 6 }),
    summary: () => vString(body.summary, { field: 'summary', max: 600 }),
    icon: () => vString(body.icon, { field: 'icon', max: 4 }),
    sort_order: () => vInt(body.sort_order, { field: 'sort_order', fallback: 0 }),
    status: () => vEnum(body.status ?? 'draft', ['draft', 'published'], { field: 'status', fallback: 'draft' }),
    seo_title: () => vString(body.seo_title, { field: 'seo_title', max: 70 }),
    seo_description: () => vString(body.seo_description, { field: 'seo_description', max: 320 }),
  });
  return {
    id: body.id ? Number(body.id) : undefined,
    ...v,
    body: sanitizeHtml(String(body.body || '').slice(0, 40000)),
    deliverables: (Array.isArray(deliverables) ? deliverables : []).slice(0, 20).map((d) => String(d).slice(0, 200)),
  };
}
