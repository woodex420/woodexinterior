import { sanitizeHtml } from './sanitize.js';
import { ApiError, invalid } from './http.js';

/**
 * The page builder vocabulary. Each page stores an ordered array of blocks in
 * pages.blocks (JSON). The admin renders a form per type and the site renders a
 * matching EJS partial from views/site/blocks/<type>.ejs.
 */
export const BLOCK_TYPES = {
  hero: {
    label: 'Hero carousel',
    fields: ['slides', 'ctaLabel', 'ctaHref'],
    defaults: { slides: [], ctaLabel: 'Start your project', ctaHref: '/contact' },
  },
  pageHero: {
    label: 'Page hero',
    fields: ['eyebrow', 'title', 'intro'],
    defaults: { eyebrow: '', title: '', intro: '' },
  },
  richText: {
    label: 'Rich text',
    fields: ['html'],
    defaults: { html: '' },
  },
  imageText: {
    label: 'Image + text',
    fields: ['eyebrow', 'title', 'body', 'image', 'alt', 'ctaLabel', 'ctaHref', 'reverse', 'notes'],
    defaults: { eyebrow: '', title: '', body: '', image: '', alt: '', ctaLabel: '', ctaHref: '', reverse: false, notes: [] },
  },
  serviceCards: {
    label: 'Service cards',
    fields: ['eyebrow', 'title', 'ctaLabel', 'ctaHref', 'items'],
    defaults: { eyebrow: '', title: '', ctaLabel: '', ctaHref: '', items: [] },
  },
  projectGrid: {
    label: 'Featured projects',
    fields: ['eyebrow', 'title', 'ctaLabel', 'ctaHref', 'limit', 'category', 'note'],
    defaults: { eyebrow: '', title: '', ctaLabel: 'View all projects', ctaHref: '/portfolio', limit: 4, category: 'all', note: '' },
  },
  journalGrid: {
    label: 'Journal grid',
    fields: ['eyebrow', 'title', 'ctaLabel', 'ctaHref'],
    defaults: { eyebrow: '', title: '', ctaLabel: 'Explore insights', ctaHref: '/insights' },
  },
  processSteps: {
    label: 'Process steps',
    fields: ['eyebrow', 'title', 'steps', 'image', 'alt'],
    defaults: { eyebrow: '', title: '', steps: [], image: '', alt: '' },
  },
  accordion: {
    label: 'Accordion feature',
    fields: ['eyebrow', 'title', 'intro', 'items', 'ctaLabel', 'ctaHref', 'image', 'alt'],
    defaults: { eyebrow: '', title: '', intro: '', items: [], ctaLabel: '', ctaHref: '', image: '', alt: '' },
  },
  faq: {
    label: 'FAQ',
    fields: ['eyebrow', 'title', 'items'],
    defaults: { eyebrow: '', title: '', items: [] },
  },
  cardGrid: {
    label: 'Card grid',
    fields: ['eyebrow', 'title', 'columns', 'items'],
    defaults: { eyebrow: '', title: '', columns: 3, items: [] },
  },
  cta: {
    label: 'Call to action',
    fields: ['eyebrow', 'title', 'label', 'href', 'image'],
    defaults: { eyebrow: '', title: '', label: 'Start your project', href: '/contact', image: '' },
  },
  stats: {
    label: 'Stats',
    fields: ['items'],
    defaults: { items: [] },
  },
  contactForm: {
    label: 'Contact form',
    fields: ['formTitle', 'note'],
    defaults: { formTitle: 'Tell us about your project.', note: '' },
  },
};

const str = (v, max = 4000) => (v == null ? '' : String(v).slice(0, max));
const html = (v, max = 60000) => sanitizeHtml(str(v, max));
const bool = (v) => v === true || v === 'true' || v === 'on' || v === '1';
const num = (v, fallback = 0) => {
  const n = Number.parseInt(v, 10);
  return Number.isNaN(n) ? fallback : n;
};
const arr = (v) => (Array.isArray(v) ? v : []);

/** Coerce + sanitise one block. Unknown fields are dropped. */
export function normalizeBlock(input) {
  if (!input || typeof input !== 'object' || !BLOCK_TYPES[input.type]) {
    throw invalid({ blocks: 'Unknown block type.' });
  }
  const type = input.type;
  const d = BLOCK_TYPES[type].defaults;
  const b = { type, id: str(input.id, 40) || Math.random().toString(36).slice(2, 10) };

  switch (type) {
    case 'hero':
      b.slides = arr(input.slides).slice(0, 6).map((s) => ({
        tag: str(s.tag, 120),
        title: html(s.title, 300),
        word: str(s.word, 40).toUpperCase(),
        copy: str(s.copy, 800),
        image: str(s.image, 500),
        alt: str(s.alt, 300),
      })).filter((s) => s.title);
      b.ctaLabel = str(input.ctaLabel ?? d.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref ?? d.ctaHref, 300);
      break;
    case 'pageHero':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.intro = str(input.intro, 1200);
      break;
    case 'richText':
      b.html = html(input.html);
      break;
    case 'imageText':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.body = html(input.body, 20000);
      b.image = str(input.image, 500);
      b.alt = str(input.alt, 300);
      b.ctaLabel = str(input.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref, 300);
      b.reverse = bool(input.reverse);
      b.notes = arr(input.notes).slice(0, 4).map((n) => ({ strong: str(n.strong, 80), text: str(n.text, 160) }));
      break;
    case 'serviceCards':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.ctaLabel = str(input.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref, 300);
      b.items = arr(input.items).slice(0, 12).map((i) => ({
        number: str(i.number, 6),
        title: str(i.title, 160),
        summary: str(i.summary, 500),
        href: str(i.href, 300),
        icon: str(i.icon, 4),
      }));
      break;
    case 'projectGrid':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.ctaLabel = str(input.ctaLabel ?? d.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref ?? d.ctaHref, 300);
      b.limit = Math.min(12, Math.max(1, num(input.limit, d.limit)));
      b.category = str(input.category ?? 'all', 40) || 'all';
      b.note = str(input.note, 400);
      break;
    case 'journalGrid':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.ctaLabel = str(input.ctaLabel ?? d.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref ?? d.ctaHref, 300);
      break;
    case 'processSteps':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.steps = arr(input.steps).slice(0, 8).map((s) => ({ title: str(s.title, 160), text: str(s.text, 400) }));
      b.image = str(input.image, 500);
      b.alt = str(input.alt, 300);
      break;
    case 'accordion':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.intro = str(input.intro, 600);
      b.items = arr(input.items).slice(0, 12).map((i) => ({ title: str(i.title, 200), text: str(i.text, 900) }));
      b.ctaLabel = str(input.ctaLabel, 80);
      b.ctaHref = str(input.ctaHref, 300);
      b.image = str(input.image, 500);
      b.alt = str(input.alt, 300);
      break;
    case 'faq':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.items = arr(input.items).slice(0, 20).map((i) => ({ q: str(i.q, 240), a: str(i.a, 1200) }));
      break;
    case 'cardGrid':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.columns = num(input.columns, 3);
      b.items = arr(input.items).slice(0, 9).map((i) => ({
        number: str(i.number, 6), title: str(i.title, 160), text: str(i.text, 600),
      }));
      break;
    case 'cta':
      b.eyebrow = str(input.eyebrow, 160);
      b.title = html(input.title, 400);
      b.label = str(input.label ?? d.label, 80);
      b.href = str(input.href ?? d.href, 300);
      b.image = str(input.image, 500);
      break;
    case 'stats':
      b.items = arr(input.items).slice(0, 6).map((i) => ({ value: str(i.value, 24), label: str(i.label, 120) }));
      break;
    case 'contactForm':
      b.formTitle = str(input.formTitle ?? d.formTitle, 160);
      b.note = str(input.note, 400);
      break;
    default:
      throw new ApiError(422, 'validation_failed', 'Unsupported block.');
  }
  return b;
}

export function normalizeBlocks(input) {
  const list = Array.isArray(input) ? input : [];
  if (list.length > 40) throw invalid({ blocks: 'Keep pages under 40 blocks.' });
  return list.map(normalizeBlock);
}

/**
 * Field shapes for repeatable block items, consumed by the admin block editor
 * (public/admin/blocks-editor.js) to render nested repeater rows.
 */
export const ARRAY_ITEM_FIELDS = {
  hero: { slides: ['tag', 'title', 'word', 'copy', 'image', 'alt'] },
  imageText: { notes: ['strong', 'text'] },
  serviceCards: { items: ['number', 'title', 'summary', 'href'] },
  processSteps: { steps: ['title', 'text'] },
  accordion: { items: ['title', 'text'] },
  faq: { items: ['q', 'a'] },
  cardGrid: { items: ['number', 'title', 'text'] },
  stats: { items: ['value', 'label'] },
};

export function blockLabel(block) {
  const meta = BLOCK_TYPES[block?.type];
  if (!meta) return block?.type || 'unknown';
  const title = block.title || block.formTitle || block.eyebrow || (block.slides?.[0]?.title ?? '');
  return title ? `${meta.label}: ${String(title).replace(/<[^>]+>/g, '').slice(0, 60)}` : meta.label;
}
