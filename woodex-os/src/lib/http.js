/** JSON + validation helpers shared by every API route. */

export class ApiError extends Error {
  constructor(status, code, message, details) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export const notFound = (what = 'Resource') => new ApiError(404, 'not_found', `${what} not found.`);
export const forbidden = (message = 'You do not have permission to do that.') =>
  new ApiError(403, 'forbidden', message);
export const unauthorized = (message = 'Authentication required.') =>
  new ApiError(401, 'unauthorized', message);
export const conflict = (message) => new ApiError(409, 'conflict', message);
export const invalid = (details) =>
  new ApiError(422, 'validation_failed', 'Some fields need attention.', details);

export function sendError(res, err) {
  if (err instanceof ApiError) {
    const body = { error: { code: err.code, message: err.message } };
    if (err.details) body.error.details = err.details;
    return res.status(err.status).json(body);
  }
  console.error('[woodex-os] unhandled error:', err);
  return res.status(500).json({ error: { code: 'internal_error', message: 'Something went wrong on our side.' } });
}

export const asyncHandler = (fn) => (req, res, next) => {
  try {
    const result = fn(req, res, next);
    if (result && typeof result.catch === 'function') result.catch(next);
  } catch (err) {
    next(err);
  }
};

export function pagination(req, { defaultPerPage = 20, maxPerPage = 100 } = {}) {
  const page = Math.max(1, Number.parseInt(req.query.page ?? '1', 10) || 1);
  const perPage = Math.min(maxPerPage, Math.max(1, Number.parseInt(req.query.per_page ?? String(defaultPerPage), 10) || defaultPerPage));
  return { page, perPage, offset: (page - 1) * perPage, limit: perPage };
}

export function paged(items, total, { page, perPage }) {
  return {
    data: items,
    meta: {
      page,
      per_page: perPage,
      total,
      total_pages: Math.max(1, Math.ceil(total / perPage)),
      has_more: page * perPage < total,
    },
  };
}

/* ------------------------------------------------------------------ validation */

export function vString(value, { field, required = false, min = 0, max = 5000, label } = {}) {
  const name = label || field;
  if (value == null || value === '') {
    if (required) throw invalid({ [field]: `${name} is required.` });
    return '';
  }
  if (typeof value !== 'string') throw invalid({ [field]: `${name} must be text.` });
  const trimmed = value.trim();
  if (required && !trimmed) throw invalid({ [field]: `${name} is required.` });
  if (trimmed.length < min) throw invalid({ [field]: `${name} must be at least ${min} characters.` });
  if (trimmed.length > max) throw invalid({ [field]: `${name} must be under ${max} characters.` });
  return trimmed;
}

export function vEmail(value, { field = 'email', required = true } = {}) {
  const email = vString(value, { field, required, max: 200, label: 'Email' }).toLowerCase();
  if (email && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(email)) {
    throw invalid({ [field]: 'Enter a valid email address.' });
  }
  return email;
}

export function vSlug(value, { field = 'slug', required = true, fallbackSource } = {}) {
  let slug = value || (fallbackSource ? slugify(fallbackSource) : '');
  slug = String(slug).trim().toLowerCase();
  if (!slug && required) throw invalid({ [field]: 'Slug is required.' });
  if (slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
    throw invalid({ [field]: 'Use lowercase letters, numbers and single hyphens only.' });
  }
  if (slug.length > 120) throw invalid({ [field]: 'Slug must be under 120 characters.' });
  return slug;
}

export function vEnum(value, allowed, { field, fallback } = {}) {
  if (value == null || value === '') {
    if (fallback !== undefined) return fallback;
    throw invalid({ [field]: `${field} is required.` });
  }
  if (!allowed.includes(value)) throw invalid({ [field]: `Choose one of: ${allowed.join(', ')}.` });
  return value;
}

export function vBool(value, fallback = false) {
  if (value == null || value === '') return fallback;
  if (typeof value === 'boolean') return value;
  if (['1', 'true', 'on', 'yes'].includes(String(value).toLowerCase())) return true;
  if (['0', 'false', 'off', 'no'].includes(String(value).toLowerCase())) return false;
  return fallback;
}

export function vInt(value, { field, fallback = 0, min = -1e9, max = 1e9 } = {}) {
  const n = Number.parseInt(value, 10);
  if (Number.isNaN(n)) {
    if (fallback !== undefined) return fallback;
    throw invalid({ [field]: `${field} must be a number.` });
  }
  if (n < min || n > max) throw invalid({ [field]: `${field} must be between ${min} and ${max}.` });
  return n;
}

export function vJsonArray(value, { field = 'items' } = {}) {
  if (value == null || value === '') return [];
  if (Array.isArray(value)) return value;
  try {
    const parsed = JSON.parse(value);
    if (!Array.isArray(parsed)) throw new Error('not an array');
    return parsed;
  } catch {
    throw invalid({ [field]: 'Expected a JSON array.' });
  }
}

export function slugify(text) {
  return String(text || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 100);
}

export function csvEscape(value) {
  const str = String(value ?? '');
  return /[",\n\r]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
}

export function toCsv(rows, columns) {
  const head = columns.map((c) => csvEscape(c.label)).join(',');
  const body = rows.map((row) => columns.map((c) => csvEscape(c.value ? c.value(row) : row[c.key])).join(','));
  return [head, ...body].join('\r\n') + '\r\n';
}
