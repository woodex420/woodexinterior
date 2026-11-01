import crypto from 'node:crypto';
import { nowIso } from '../db.js';

export const SESSION_COOKIE = 'woodex_sid';
export const SESSION_TTL_SECONDS = 12 * 60 * 60;
const SCRYPT = { N: 16384, r: 8, p: 1, keylen: 64 };

/* ------------------------------------------------------------------ passwords */

export function hashPassword(password) {
  const salt = crypto.randomBytes(16);
  const key = crypto.scryptSync(String(password), salt, SCRYPT.keylen, {
    N: SCRYPT.N, r: SCRYPT.r, p: SCRYPT.p, maxmem: 64 * 1024 * 1024,
  });
  return `scrypt$${SCRYPT.N}$${SCRYPT.r}$${SCRYPT.p}$${salt.toString('base64')}$${key.toString('base64')}`;
}

export function verifyPassword(password, stored) {
  try {
    const [scheme, N, r, p, saltB64, keyB64] = String(stored).split('$');
    if (scheme !== 'scrypt') return false;
    const salt = Buffer.from(saltB64, 'base64');
    const expected = Buffer.from(keyB64, 'base64');
    const actual = crypto.scryptSync(String(password), salt, expected.length, {
      N: Number(N), r: Number(r), p: Number(p), maxmem: 64 * 1024 * 1024,
    });
    return crypto.timingSafeEqual(expected, actual);
  } catch {
    return false;
  }
}

export function passwordProblems(password) {
  const problems = [];
  if (typeof password !== 'string' || password.length < 10) problems.push('Use at least 10 characters.');
  if (password && password.length > 200) problems.push('Use fewer than 200 characters.');
  if (password && !/[a-zA-Z]/.test(password)) problems.push('Include at least one letter.');
  if (password && !/\d/.test(password)) problems.push('Include at least one number.');
  return problems;
}

/* ------------------------------------------------------------------- sessions */

export const CAPABILITIES = {
  owner: ['content:read', 'content:write', 'media:write', 'enquiries:read', 'enquiries:write',
    'settings:write', 'users:manage', 'tokens:manage', 'audit:read', 'system:export'],
  editor: ['content:read', 'content:write', 'media:write', 'enquiries:read', 'enquiries:write'],
  viewer: ['content:read', 'enquiries:read'],
};

export function can(user, capability) {
  if (!user) return false;
  return (CAPABILITIES[user.role] || []).includes(capability);
}

export function parseCookies(header = '') {
  const out = {};
  for (const part of String(header).split(';')) {
    const idx = part.indexOf('=');
    if (idx < 0) continue;
    const key = part.slice(0, idx).trim();
    if (!key) continue;
    out[key] = decodeURIComponent(part.slice(idx + 1).trim());
  }
  return out;
}

export function createSession(db, userId, { ip, userAgent } = {}) {
  const id = crypto.randomBytes(32).toString('hex');
  const csrf = crypto.randomBytes(32).toString('hex');
  const expires = new Date(Date.now() + SESSION_TTL_SECONDS * 1000);
  db.run(
    `INSERT INTO sessions (id, user_id, csrf, ip, user_agent, expires_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    id, userId, csrf, ip ?? null, (userAgent ?? '').slice(0, 250),
    expires.toISOString().replace(/\.\d{3}Z$/, 'Z'), nowIso(),
  );
  db.run('DELETE FROM sessions WHERE expires_at < ?', nowIso());
  return { id, csrf, expires };
}

export function destroySession(db, sessionId) {
  if (!sessionId) return;
  db.run('DELETE FROM sessions WHERE id = ?', sessionId);
}

export function sessionCookie(session, { secure = false } = {}) {
  const parts = [
    `${SESSION_COOKIE}=${session.id}`,
    'Path=/',
    'HttpOnly',
    'SameSite=Lax',
    `Max-Age=${SESSION_TTL_SECONDS}`,
  ];
  if (secure) parts.push('Secure');
  return parts.join('; ');
}

export function clearCookie() {
  return `${SESSION_COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0`;
}

/** Populate req.user / req.session from the session cookie (or Bearer token). */
export function attachAuth(db) {
  return function attachAuthMiddleware(req, _res, next) {
    req.user = null;
    req.session = null;
    const cookies = parseCookies(req.headers.cookie || '');
    const sid = cookies[SESSION_COOKIE];
    if (sid) {
      const row = db.get(
        `SELECT s.id AS sid, s.csrf, s.expires_at, u.id, u.email, u.name, u.role, u.status
           FROM sessions s JOIN users u ON u.id = s.user_id
          WHERE s.id = ?`,
        sid,
      );
      if (row && row.status === 'active' && row.expires_at > nowIso()) {
        req.session = { id: row.sid, csrf: row.csrf, expiresAt: row.expires_at };
        req.user = { id: row.id, email: row.email, name: row.name, role: row.role };
      } else if (row) {
        db.run('DELETE FROM sessions WHERE id = ?', sid);
      }
    }

    const bearer = /^Bearer\s+(.+)$/i.exec(req.headers.authorization || '');
    if (!req.user && bearer) {
      const hash = crypto.createHash('sha256').update(bearer[1].trim()).digest('hex');
      const token = db.get(
        `SELECT t.id, t.scopes, t.expires_at, t.revoked_at, u.id AS uid, u.email, u.name, u.role, u.status
           FROM api_tokens t JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ?`,
        hash,
      );
      const expired = token?.expires_at && token.expires_at < nowIso();
      if (token && !token.revoked_at && !expired && token.status === 'active') {
        req.user = { id: token.uid, email: token.email, name: token.name, role: token.role, viaToken: true };
        req.tokenScopes = String(token.scopes || '').split(',').map((s) => s.trim()).filter(Boolean);
        db.run('UPDATE api_tokens SET last_used_at = ? WHERE id = ?', nowIso(), token.id);
      }
    }
    next();
  };
}

export function csrfTokenFor(req) {
  return req.session?.csrf || '';
}

export function csrfValid(req, provided) {
  if (!req.session?.csrf) return false;
  const expected = Buffer.from(req.session.csrf);
  const actual = Buffer.from(String(provided || ''));
  if (expected.length !== actual.length) return false;
  return crypto.timingSafeEqual(expected, actual);
}

export function readCsrf(req) {
  return req.body?._csrf || req.get('x-csrf-token') || '';
}

/** Simple in-memory sliding-window limiter (per process). */
export function createRateLimiter({ windowMs, max, keyFn }) {
  const hits = new Map();
  return function limit(req, res, next) {
    const key = keyFn ? keyFn(req) : clientIp(req);
    const now = Date.now();
    const list = (hits.get(key) || []).filter((t) => now - t < windowMs);
    if (list.length >= max) {
      const retryAfter = Math.ceil((windowMs - (now - list[0])) / 1000);
      res.set('Retry-After', String(retryAfter));
      return res.status(429).json({
        error: { code: 'rate_limited', message: 'Too many requests. Please try again later.', retryAfter },
      });
    }
    list.push(now);
    hits.set(key, list);
    if (hits.size > 5000) {
      for (const [k, v] of hits) if (!v.length || now - v[v.length - 1] > windowMs) hits.delete(k);
    }
    next();
  };
}

export function clientIp(req) {
  const forwarded = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim();
  return forwarded || req.socket?.remoteAddress || 'unknown';
}
