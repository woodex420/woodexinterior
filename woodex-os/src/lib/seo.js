import { stripTags, excerpt } from './sanitize.js';

const absolute = (base, path) => (path?.startsWith('http') ? path : `${base.replace(/\/$/, '')}${path || ''}`);

export function buildMeta({ page = {}, config, path = '/', type = 'website', fallbackTitle }) {
  const base = config['site.url'].replace(/\/$/, '');
  const title = page.seo_title || page.title
    ? `${page.seo_title || page.title}${page.seo_title?.includes(config['site.name']) ? '' : ` | ${config['site.name']}`}`
    : fallbackTitle || config['site.name'];
  const description = page.seo_description
    || config['seo.default_description']
    || excerpt(config['site.description'], 158);
  const canonical = page.canonical || absolute(base, path);
  const ogImage = absolute(base, page.og_image || config['seo.default_og_image'] || '/assets/detail.jpg');
  const noindex = page.noindex || page.status === 'draft';
  return {
    title,
    description,
    canonical,
    ogImage,
    type,
    robots: noindex ? 'noindex, nofollow' : (config['seo.robots'] || 'index,follow'),
    siteName: config['site.name'],
    twitterCard: 'summary_large_image',
  };
}

export function organizationLd(config) {
  const base = config['site.url'].replace(/\/$/, '');
  const sameAs = config.socials?.map(([, url]) => url).filter(Boolean) || [];
  return {
    '@context': 'https://schema.org',
    '@type': 'InteriorDesignBusiness',
    '@id': `${base}/#organization`,
    name: config['site.name'],
    url: base,
    description: config['site.description'],
    image: absolute(base, config['seo.default_og_image'] || '/assets/detail.jpg'),
    email: config['contact.email'],
    telephone: config['contact.phone'],
    address: {
      '@type': 'PostalAddress',
      streetAddress: stripTags(config['contact.address_line'] || config['contact.address']),
      addressLocality: 'Lahore',
      addressCountry: 'PK',
    },
    openingHours: config['contact.hours'],
    ...(sameAs.length ? { sameAs } : {}),
  };
}

export function websiteLd(config) {
  const base = config['site.url'].replace(/\/$/, '');
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    '@id': `${base}/#website`,
    url: base,
    name: config['site.name'],
    publisher: { '@id': `${base}/#organization` },
    potentialAction: {
      '@type': 'SearchAction',
      target: `${base}/search?q={search_term_string}`,
      'query-input': 'required name=search_term_string',
    },
  };
}

export function breadcrumbLd(items, base) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: stripTags(item.label),
      item: item.href?.startsWith('http') ? item.href : `${base.replace(/\/$/, '')}${item.href}`,
    })),
  };
}

export function articleLd(insight, config) {
  const base = config['site.url'].replace(/\/$/, '');
  return {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: insight.title,
    description: insight.excerpt || excerpt(insight.body, 160),
    image: insight.cover_image ? absolute(base, insight.cover_image) : undefined,
    datePublished: insight.published_at || undefined,
    dateModified: insight.updated_at || insight.published_at || undefined,
    author: { '@type': 'Organization', name: insight.author || config['site.name'] },
    publisher: { '@id': `${base}/#organization` },
    mainEntityOfPage: `${base}/insights/${insight.slug}`,
  };
}

export function faqLd(items) {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: items.map((item) => ({
      '@type': 'Question',
      name: item.q,
      acceptedAnswer: { '@type': 'Answer', text: item.a },
    })),
  };
}

export function projectLd(project, config) {
  const base = config['site.url'].replace(/\/$/, '');
  return {
    '@context': 'https://schema.org',
    '@type': 'CreativeWork',
    name: project.title,
    description: project.summary || excerpt(project.body, 160),
    url: `${base}/portfolio/${project.slug}`,
    image: project.cover_image ? absolute(base, project.cover_image) : undefined,
    creator: { '@id': `${base}/#organization` },
    dateCreated: project.year || undefined,
  };
}

export const jsonLdScript = (obj) => `<script type="application/ld+json">${JSON.stringify(obj, null, 0).replace(/</g, '\\u003c')}</script>`;
