/**
 * Small allow-list HTML sanitiser.
 *
 * Woodex OS stores a handful of rich-text fields written by trusted editors, but
 * "trusted" is not a security control: an editor account (or a stolen session)
 * must not be able to persist <script>, event handlers, or javascript: URLs into
 * the public site. Everything rendered with <%- %> in the templates passes
 * through this function first.
 */

const ALLOWED_TAGS = new Set([
  'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'dd', 'div', 'dl', 'dt',
  'em', 'figcaption', 'figure', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'mark',
  'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td',
  'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
]);

const ALLOWED_ATTRS = new Set([
  'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel', 'width', 'height',
  'loading', 'decoding', 'colspan', 'rowspan', 'scope', 'lang', 'dir', 'style', 'aria-label',
  'aria-hidden', 'role', 'fetchpriority',
]);

const VOID_TAGS = new Set(['br', 'hr', 'img']);
const DROP_WITH_CONTENT = new Set([
  'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form',
  'input', 'button', 'textarea', 'select', 'link', 'meta', 'base',
]);

const SAFE_URL = /^(https?:\/\/|mailto:|tel:|\/|#|\?|\.\/|\.\.\/)/i;

function sanitizeUrl(value) {
  const cleaned = String(value).replace(/[\u0000-\u001F\u007F\s]+/g, ' ').trim();
  if (!cleaned) return '';
  // Decode HTML entities that could hide the scheme (e.g. &#106;avascript:)
  const decoded = cleaned
    .replace(/&#x([0-9a-f]+);?/gi, (_, h) => String.fromCharCode(parseInt(h, 16)))
    .replace(/&#(\d+);?/g, (_, d) => String.fromCharCode(Number(d)))
    .replace(/&colon;/gi, ':')
    .replace(/&amp;/gi, '&');
  if (/^\s*(javascript|vbscript|file|blob)\s*:/i.test(decoded)) return '';
  if (/^\s*data\s*:/i.test(decoded) && !/^\s*data:image\/(png|jpe?g|gif|webp);base64,/i.test(decoded)) return '';
  if (!SAFE_URL.test(decoded) && !/^data:image\//i.test(decoded)) return '';
  return cleaned;
}

function sanitizeStyle(value) {
  return String(value)
    .replace(/expression\s*\(/gi, '')
    .replace(/url\s*\(\s*(['"]?)\s*(javascript|vbscript|data):/gi, 'url($1blocked:')
    .replace(/behavior\s*:/gi, '')
    .replace(/-moz-binding/gi, '');
}

function sanitizeAttrs(raw) {
  const out = [];
  const attrRe = /([a-zA-Z_:][-\w:.]*)\s*(?:=\s*("[^"]*"|'[^']*'|[^\s"'=<>`]+))?/g;
  let m;
  while ((m = attrRe.exec(raw))) {
    const name = m[1].toLowerCase();
    if (!ALLOWED_ATTRS.has(name)) continue;
    if (name.startsWith('on')) continue;
    let value = m[2] ?? '';
    if (value.startsWith('"') || value.startsWith("'")) value = value.slice(1, -1);
    if (name === 'href' || name === 'src') {
      value = sanitizeUrl(value);
      if (!value) continue;
    } else if (name === 'style') {
      value = sanitizeStyle(value);
    } else {
      value = value.replace(/[<>]/g, '');
    }
    out.push(`${name}="${String(value).replace(/"/g, '&quot;')}"`);
  }
  return out.length ? ' ' + out.join(' ') : '';
}

export function sanitizeHtml(input, { allowImages = true } = {}) {
  if (input == null) return '';
  const html = String(input);
  let output = '';
  let i = 0;
  const tagRe = /<(\/?)([a-zA-Z][\w:-]*)((?:[^<>"']|"[^"]*"|'[^']*')*?)(\/?)>/g;
  let match;

  while ((match = tagRe.exec(html))) {
    output += html.slice(i, match.index);
    i = tagRe.lastIndex;
    const closing = match[1] === '/';
    const name = match[2].toLowerCase();

    if (DROP_WITH_CONTENT.has(name)) {
      if (!closing) {
        const endRe = new RegExp(`</${name}\\s*>`, 'i');
        const end = html.slice(i).search(endRe);
        i = end === -1 ? html.length : i + end + `</${name}>`.length;
        tagRe.lastIndex = i;
      }
      continue;
    }
    if (!ALLOWED_TAGS.has(name)) continue;
    if (!allowImages && name === 'img') continue;

    if (closing) {
      if (!VOID_TAGS.has(name)) output += `</${name}>`;
      continue;
    }
    const attrs = sanitizeAttrs(match[3] || '');
    output += VOID_TAGS.has(name) ? `<${name}${attrs}>` : `<${name}${attrs}>`;
  }
  output += html.slice(i);
  return output.trim();
}

/** Strip every tag — used for excerpts, meta descriptions and plain-text fields. */
export function stripTags(input) {
  return String(input ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function excerpt(input, length = 160) {
  const text = stripTags(input);
  if (text.length <= length) return text;
  return text.slice(0, length - 1).replace(/\s+\S*$/, '') + '…';
}
