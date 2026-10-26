import { DatabaseSync } from 'node:sqlite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const SCHEMA_PATH = path.join(here, 'schema.sql');

export function nowIso() {
  return new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');
}

/**
 * Open (and migrate) the Woodex OS database.
 * @param {string} dbPath  file path or ':memory:'
 */
export function openDatabase(dbPath) {
  if (dbPath !== ':memory:') fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  const db = new DatabaseSync(dbPath);
  db.exec('PRAGMA foreign_keys = ON;');
  if (dbPath !== ':memory:') db.exec('PRAGMA journal_mode = WAL;');
  db.exec(fs.readFileSync(SCHEMA_PATH, 'utf8'));

  const api = {
    raw: db,
    exec: (sql) => db.exec(sql),
    all(sql, ...params) {
      return db.prepare(sql).all(...params).map(plain);
    },
    get(sql, ...params) {
      const row = db.prepare(sql).get(...params);
      return row === undefined ? null : plain(row);
    },
    run(sql, ...params) {
      const info = db.prepare(sql).run(...params);
      return { changes: Number(info.changes), lastInsertRowid: Number(info.lastInsertRowid) };
    },
    transaction(fn) {
      db.exec('BEGIN IMMEDIATE');
      try {
        const result = fn();
        db.exec('COMMIT');
        return result;
      } catch (err) {
        try { db.exec('ROLLBACK'); } catch { /* already rolled back */ }
        throw err;
      }
    },
    close() { db.close(); },
  };
  return api;
}

function plain(row) {
  if (row && typeof row === 'object' && Object.getPrototypeOf(row) !== Object.prototype) {
    return { ...row };
  }
  return row;
}

/** Tiny key/value accessors on the settings table. */
export function getSetting(db, key, fallback = null) {
  const row = db.get('SELECT value FROM settings WHERE key = ?', key);
  if (!row) return fallback;
  return row.value;
}

export function setSetting(db, key, value) {
  db.run(
    `INSERT INTO settings (key, value, updated_at) VALUES (?, ?, ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at`,
    key, String(value), nowIso(),
  );
}

export function getJsonSetting(db, key, fallback) {
  const raw = getSetting(db, key, null);
  if (raw == null) return fallback;
  try { return JSON.parse(raw); } catch { return fallback; }
}

export function setJsonSetting(db, key, value) {
  setSetting(db, key, JSON.stringify(value));
}
