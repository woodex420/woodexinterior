#!/usr/bin/env node
/**
 * One-off migration for the hand-written v1 site in ../woodex.
 *
 * The v1 pages were authored page-by-page with copy-and-paste <head> blocks, so
 * every page shipped the same meta description, no canonical/OG/Twitter tags, no
 * structured data, footer <h4>s that broke the heading outline, buttons without a
 * type, and images without intrinsic dimensions. This script fixes all of that in
 * place without touching the visual design, and is safe to re-run.
 *
 *   node scripts/patch-legacy-site.js            # patch ../woodex/*.html
 *   node scripts/patch-legacy-site.js --dry-run  # report only
 *
 * The generated markup is what Woodex OS now renders from the CMS; the v1 folder
 * stays as a static fallback until the exported site is deployed.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const siteDir = path.resolve(here, '../../woodex');
const dryRun = process.argv.includes('--dry-run');
const ORIGIN = 'https://woodex.com.pk';
const OG_IMAGE = `${ORIGIN}/assets/hero.jpg`;

const pages = {
  'index.html': {
    type: 'website',
    title: 'Thoughtful spaces. Timeless interiors.',
    description: 'Woodex Interior is a design-led interior studio in Lahore creating calm, material-led homes and workplaces shaped around the people who use them.',
    image: 'detail.jpg',
    jsonLd: 'business',
  },
  'about.html': {
    type: 'website',
    title: 'About our studio',
    description: 'Meet the Woodex Interior studio: a Lahore-based team designing personal, material-led interiors with a considered, detail-first approach.',
    image: 'studio.jpg',
    jsonLd: 'about',
  },
  'services.html': {
    type: 'website',
    title: 'Interior design services',
    description: 'Residential interiors, commercial spaces, bespoke woodwork, styling and renovation — the design services Woodex Interior offers from concept to completion.',
    image: 'living.jpg',
    jsonLd: 'service',
  },
  'portfolio.html': {
    type: 'website',
    title: 'Concept portfolio',
    description: 'Explore the Woodex Interior concept portfolio: residential, commercial and bespoke interior studies showing how material, light and layout come together.',
    image: 'detail.jpg',
    jsonLd: 'collection',
  },
  'insights.html': {
    type: 'website',
    title: 'Interior design insights',
    description: 'Design notes from the Woodex studio on natural materials, space planning and lighting — practical ideas for a more considered interior.',
    image: 'lounge.jpg',
    jsonLd: 'collection',
  },
  'contact.html': {
    type: 'website',
    title: 'Start your interior project',
    description: 'Tell Woodex Interior about your space. Share your rooms, timeline and ideas, and the Lahore studio will reply within two working days.',
    image: 'hero.jpg',
    jsonLd: 'contact',
  },
  'privacy.html': {
    type: 'website',
    title: 'Privacy information',
    description: 'How Woodex Interior collects, uses and protects the details you share through this website, and how to ask for your information to be removed.',
    image: 'hero.jpg',
    jsonLd: 'page',
  },
  'style-guide.html': {
    type: 'website',
    title: 'Master style guide',
    description: 'Internal reference for the Woodex Interior design system: colour tokens, typography, spacing, buttons and image treatments used across the site.',
    image: 'hero.jpg',
    jsonLd: 'page',
    noindex: true,
  },
  '404.html': {
    type: 'website',
    title: 'Page not found',
    description: 'The page you were looking for is no longer here. Return to the Woodex Interior home page or explore the studio portfolio instead.',
    image: 'hero.jpg',
    jsonLd: 'page',
    noindex: true,
  },
};

const projectPages = {
  'project-the-arched-residence.html': ['The Arched Residence', 'A calm, light-filled residence concept built around arched openings, exposed timber and woven textures.'],
  'project-the-open-house.html': ['The Open House', 'An open-plan residential concept where circulation, timber screens and a sculptural stair shape the everyday flow.'],
  'project-the-collective-workspace.html': ['The Collective Workspace', 'A workplace concept balancing focus and collaboration with glass partitions, warm timber and quiet lighting.'],
  'project-the-oak-edit.html': ['The Oak Edit', 'A bespoke furniture and material study exploring oak joinery, soft plaster and restrained contemporary detailing.'],
  'project-the-soft-retreat.html': ['The Soft Retreat', 'A residential concept layering calm neutrals, tactile fabrics and gentle lighting for a restful interior.'],
  'project-the-gathering-room.html': ['The Gathering Room', 'A hospitality-led concept designed for conversation, with generous seating, warm materials and layered light.'],
};
for (const [file, [title, summary]] of Object.entries(projectPages)) {
  pages[file] = {
    type: 'article',
    title,
    description: summary,
    image: 'detail.jpg',
    jsonLd: 'project',
    name: title,
  };
}

const insightPages = {
  'insight-the-warmth-of-natural-materials.html': ['The quiet beauty of natural materials', 'Why timber, stone and woven textures age so well, and how to layer natural materials without making a room feel heavy.', 'Materials'],
  'insight-a-home-that-flows.html': ['How to create a home that flows', 'Practical space-planning ideas for clearer circulation, better rooms and a home that works with your daily routine.', 'Space planning'],
  'insight-layering-light.html': ['A softer approach to lighting your home', 'How to layer ambient, task and accent light so a room feels calm in the evening rather than flat or over-lit.', 'Design notes'],
};
for (const [file, [title, description, category]] of Object.entries(insightPages)) {
  pages[file] = { type: 'article', title, description, image: 'lounge.jpg', jsonLd: 'insight', name: title, category };
}

const absolute = (value) => `${ORIGIN}/${String(value).replace(/^\.?\//, '')}`;

function structuredData(file, config) {
  const url = `${ORIGIN}/${file === 'index.html' ? '' : file}`;
  const business = {
    '@context': 'https://schema.org',
    '@type': 'InteriorDesignBusiness',
    name: 'Woodex Interior',
    description: 'Design-led interior studio creating thoughtful residential, commercial and bespoke interiors.',
    url: `${ORIGIN}/`,
    image: OG_IMAGE,
    telephone: '+92-322-4000768',
    email: 'woodexinterior.pk@gmail.com',
    priceRange: '$$',
    address: { '@type': 'PostalAddress', addressLocality: 'Lahore', addressRegion: 'Punjab', addressCountry: 'PK' },
    areaServed: 'Lahore, Pakistan',
    sameAs: [],
  };
  const graph = [];
  if (config.jsonLd === 'business') graph.push(business);
  if (config.jsonLd === 'about') graph.push(business, { '@type': 'AboutPage', name: config.title, url });
  if (config.jsonLd === 'contact') graph.push(business, { '@type': 'ContactPage', name: config.title, url });
  if (config.jsonLd === 'service') {
    graph.push({
      '@type': 'Service',
      serviceType: 'Interior design',
      provider: { '@type': 'InteriorDesignBusiness', name: 'Woodex Interior', url: `${ORIGIN}/` },
      areaServed: 'Lahore, Pakistan',
      url,
    });
  }
  if (config.jsonLd === 'collection') graph.push({ '@type': 'CollectionPage', name: config.title, url });
  if (config.jsonLd === 'project') {
    graph.push({ '@type': 'CreativeWork', name: config.name, description: config.description, url, creator: { '@type': 'Organization', name: 'Woodex Interior' } });
  }
  if (config.jsonLd === 'insight') {
    graph.push({
      '@type': 'Article',
      headline: config.name,
      description: config.description,
      articleSection: config.category,
      url,
      author: { '@type': 'Organization', name: 'Woodex Interior' },
      publisher: { '@type': 'Organization', name: 'Woodex Interior' },
    });
  }
  if (config.jsonLd === 'page') graph.push({ '@type': 'WebPage', name: config.title, url });
  if (!graph.length) return '';
  const payload = { '@context': 'https://schema.org', '@graph': graph };
  return `<script type="application/ld+json">${JSON.stringify(payload).replace(/</g, '\\u003c')}</script>`;
}

/* --------------------------------------------------------------- image size */
function jpegSize(buffer) {
  if (buffer[0] !== 0xff || buffer[1] !== 0xd8) return null;
  let offset = 2;
  while (offset < buffer.length) {
    if (buffer[offset] !== 0xff) { offset += 1; continue; }
    const marker = buffer[offset + 1];
    const length = buffer.readUInt16BE(offset + 2);
    if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) {
      return { height: buffer.readUInt16BE(offset + 5), width: buffer.readUInt16BE(offset + 7) };
    }
    offset += 2 + length;
  }
  return null;
}

const sizeCache = new Map();
function imageSize(src) {
  const file = path.join(siteDir, src.replace(/^\.?\//, ''));
  if (sizeCache.has(file)) return sizeCache.get(file);
  let size = null;
  try {
    size = jpegSize(fs.readFileSync(file));
  } catch { size = null; }
  sizeCache.set(file, size);
  return size;
}

/* ------------------------------------------------------------------ patching */
const report = [];
for (const [file, config] of Object.entries(pages)) {
  const filePath = path.join(siteDir, file);
  if (!fs.existsSync(filePath)) continue;
  let html = fs.readFileSync(filePath, 'utf8');
  const before = html;
  const changes = [];

  const absoluteUrl = `${ORIGIN}/${file === 'index.html' ? '' : file}`;
  const canonical = `<link rel="canonical" href="${absoluteUrl}">`;

  // 1. Unique, length-checked meta description (replaces the shared one).
  const description = config.description.length > 160 ? `${config.description.slice(0, 157).trimEnd()}…` : config.description;
  const descriptionTag = `<meta name="description" content="${description}">`;
  if (/<meta name="description"[^>]*>/.test(html)) {
    html = html.replace(/<meta name="description"[^>]*>/, descriptionTag);
    changes.push('description');
  } else {
    html = html.replace('</title>', `</title>${descriptionTag}`);
    changes.push('description');
  }

  // 2. Canonical + social cards + robots + theme colour.
  if (!/rel="canonical"/.test(html)) {
    const social = [
      canonical,
      `<meta property="og:type" content="${config.type}">`,
      `<meta property="og:site_name" content="Woodex Interior">`,
      `<meta property="og:locale" content="en_PK">`,
      `<meta property="og:url" content="${absoluteUrl}">`,
      `<meta property="og:title" content="${config.title}">`,
      `<meta property="og:description" content="${description}">`,
      `<meta property="og:image" content="${absolute(config.image || 'hero.jpg')}">`,
      `<meta property="og:image:alt" content="Woodex Interior project photography">`,
      `<meta name="twitter:card" content="summary_large_image">`,
      `<meta name="twitter:title" content="${config.title}">`,
      `<meta name="twitter:description" content="${description}">`,
      `<meta name="twitter:image" content="${absolute(config.image || 'hero.jpg')}">`,
      config.noindex ? '<meta name="robots" content="noindex,follow">' : '<meta name="robots" content="index,follow">',
    ].join('');
    html = html.replace(descriptionTag, `${descriptionTag}${social}`);
    changes.push('social+canonical');
  }

  // 3. Structured data.
  if (!/application\/ld\+json/.test(html)) {
    const ld = structuredData(file, config);
    if (ld) {
      html = html.replace('</head>', `${ld}</head>`);
      changes.push('json-ld');
    }
  }

  // 4. Heading outline: footer h4 -> h3, with a hidden h2 before card grids.
  if (/<h4>/.test(html)) {
    html = html.replace(/<h4>/g, '<h3 class="footer-heading">').replace(/<\/h4>/g, '</h3>');
    changes.push('footer-h3');
  }
  if (!/All projects<\/h2>/.test(html) && /class="project-grid/.test(html)) {
    html = html.replace(/<div class="project-grid/, '<h2 class="sr-only">All projects</h2><div class="project-grid');
    changes.push('projects-h2');
  }
  if (!/Latest insights<\/h2>/.test(html) && /class="journal-grid"/.test(html)) {
    html = html.replace(/<div class="journal-grid"/, '<h2 class="sr-only">Latest insights</h2><div class="journal-grid"');
    changes.push('insights-h2');
  }
  if (!/Studio contact details<\/h2>/.test(html) && /class="contact-details"/.test(html)) {
    html = html.replace(/<div class="contact-details"/, '<h2 class="sr-only">Studio contact details</h2><div class="contact-details"');
    changes.push('contact-h2');
  }

  // 5. Buttons need an explicit type so they never submit a nearby form.
  const untitled = html.match(/<button(?![^>]*\btype=)[^>]*>/g) || [];
  if (untitled.length) {
    html = html.replace(/<button(?![^>]*\btype=)/g, '<button type="button"');
    changes.push(`button-type x${untitled.length}`);
  }

  // 6. Intrinsic dimensions, decoding and lazy-loading for content images.
  let imageFixes = 0;
  html = html.replace(/<img\b[^>]*>/g, (tag) => {
    let out = tag;
    const src = (/src="([^"]+)"/.exec(out) || [])[1] || '';
    if (!/width=/.test(out) || !/height=/.test(out)) {
      const size = imageSize(src);
      if (size) {
        out = out.replace(/<img\b/, `<img width="${size.width}" height="${size.height}"`);
        imageFixes += 1;
      }
    }
    if (!/decoding=/.test(out)) out = out.replace(/<img\b/, '<img decoding="async"');
    return out;
  });
  if (imageFixes) changes.push(`img-size x${imageFixes}`);

  // 7. Hero slides 2–3 carried empty alt text on a real photograph.
  const heroAlts = {
    'assets/hero.jpg': 'Sunlit living room layered with natural textures and warm timber',
    'assets/living.jpg': 'Architectural dining room with natural wood screens and a sculptural stair',
    'assets/lounge.jpg': 'Softly lit lounge with a woven pendant and warm neutral tones',
    'assets/detail.jpg': 'Light-filled sitting room with arched windows and exposed oak beams',
    'assets/studio.jpg': 'Studio interior with timber furniture and large windows',
    'assets/bedroom.jpg': 'Calm bedroom with natural linen and timber detailing',
    'assets/dining.jpg': 'Dining room with warm timber table and woven seating',
    'assets/office.jpg': 'Workspace corridor with glass partitions and timber details',
    'assets/kitchen.jpg': 'Kitchen with warm timber cabinetry and stone surfaces',
  };
  html = html.replace(/<img\b[^>]*class="hero-photo[^"]*"[^>]*>/g, (tag) => {
    if (!/alt=""/.test(tag)) return tag;
    const src = (/src="([^"]+)"/.exec(tag) || [])[1] || '';
    const alt = heroAlts[src];
    return alt ? tag.replace(/alt=""/, `alt="${alt}"`) : tag;
  });
  if (/class="hero-photo"/.test(html)) changes.push('hero-alt');

  // 8. Add the canonical link to the sitemap-driven QA check.
  if (html !== before && !dryRun) fs.writeFileSync(filePath, html);
  report.push({ file, changed: html !== before, changes });
}

const changed = report.filter((entry) => entry.changed);
console.log(`${dryRun ? 'Would patch' : 'Patched'} ${changed.length} of ${report.length} pages in ${siteDir}`);
for (const entry of report) {
  console.log(`  ${entry.changed ? '✓' : '·'} ${entry.file.padEnd(52)} ${entry.changes.join(', ')}`);
}
