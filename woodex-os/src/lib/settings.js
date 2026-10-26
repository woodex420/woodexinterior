import { getJsonSetting, setJsonSetting, getSetting, setSetting } from '../db.js';

/**
 * Every editable website-wide value lives here. The admin "Settings" screen
 * writes to the settings table; templates read through getSiteConfig().
 */
export const SETTING_DEFAULTS = {
  'site.name': 'Woodex Interior',
  'site.tagline': 'Interior Studio',
  'site.description':
    'Thoughtfully designed interiors, bespoke woodwork, and spaces for living well. Explore the Woodex Interior studio.',
  'site.url': 'https://woodex.com.pk',
  'site.logo_text': 'WOODEX',
  'site.logo_sub': 'INTERIOR STUDIO',

  'contact.email': 'woodexinterior.pk@gmail.com',
  'contact.phone': '+92 322 4000768',
  'contact.whatsapp': '923224000768',
  'contact.address':
    'M-71, Zainab Tower, Model Town Link Rd<br>Phase 3 GECH Society, Lahore, 54700',
  'contact.address_line': 'M-71, Zainab Tower, Model Town Link Rd, Phase 3 GECH Society, Lahore, 54700',
  'contact.hours': 'Mon–Sat, 10:00 AM – 7:00 PM',
  'contact.map_url': 'https://maps.google.com/?q=Zainab+Tower+Model+Town+Link+Rd+Lahore',

  'social.instagram': '',
  'social.facebook': '',
  'social.linkedin': '',
  'social.pinterest': '',

  'seo.default_description':
    'Thoughtfully designed interiors, bespoke woodwork, and spaces for living well. Explore the Woodex Interior studio.',
  'seo.default_og_image': '/assets/detail.jpg',
  'seo.robots': 'index,follow',
  'seo.google_verification': '',

  'theme.ink': '#111111',
  'theme.navy': '#0f1e36',
  'theme.accent': '#a87442',
  'theme.paper': '#ffffff',
  'theme.soft': '#f5f5f3',
  'theme.beige': '#fcf2e8',

  'enquiry.notify_email': 'woodexinterior.pk@gmail.com',
  'enquiry.auto_reply': '1',
  'enquiry.honeypot': 'company_website',
  'enquiry.success_path': '/thank-you',

  'system.export_dir': 'dist',
  'system.timezone': 'Asia/Karachi',
};

export const EDITABLE_SETTINGS = Object.keys(SETTING_DEFAULTS);

export function ensureSettings(db) {
  const existing = new Set(db.all('SELECT key FROM settings').map((r) => r.key));
  for (const [key, value] of Object.entries(SETTING_DEFAULTS)) {
    if (!existing.has(key)) setSetting(db, key, value);
  }
}

export function getSiteConfig(db) {
  const config = {};
  for (const [key, fallback] of Object.entries(SETTING_DEFAULTS)) {
    config[key] = getSetting(db, key, fallback);
  }
  config.nav = {
    main: db.all(
      "SELECT label, href FROM nav_items WHERE area = 'main' AND visible = 1 ORDER BY sort_order, id",
    ),
    footer_explore: db.all(
      "SELECT label, href FROM nav_items WHERE area = 'footer_explore' AND visible = 1 ORDER BY sort_order, id",
    ),
    footer_services: db.all(
      "SELECT label, href FROM nav_items WHERE area = 'footer_services' AND visible = 1 ORDER BY sort_order, id",
    ),
    footer_legal: db.all(
      "SELECT label, href FROM nav_items WHERE area = 'footer_legal' AND visible = 1 ORDER BY sort_order, id",
    ),
  };
  config.socials = [
    ['Instagram', config['social.instagram']],
    ['Facebook', config['social.facebook']],
    ['LinkedIn', config['social.linkedin']],
    ['Pinterest', config['social.pinterest']],
  ].filter(([, url]) => url);
  return config;
}

export function setSettings(db, values) {
  const changed = [];
  for (const [key, value] of Object.entries(values)) {
    if (!EDITABLE_SETTINGS.includes(key)) continue;
    const current = getSetting(db, key, null);
    if (current !== String(value)) {
      setSetting(db, key, value);
      changed.push(key);
    }
  }
  return changed;
}

export { getJsonSetting, setJsonSetting };
