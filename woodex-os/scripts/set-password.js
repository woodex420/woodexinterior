#!/usr/bin/env node
/**
 * Set or reset an admin password from the server, without email or a database GUI.
 * This is the recovery path when nobody can sign in.
 *
 *   npm run set-password -- --email admin@woodexinterior.pk
 *   npm run set-password -- --email admin@woodexinterior.pk --password 'NewPassw0rd!'
 *   npm run set-password -- --list
 *
 * Without --password the script prints a strong generated password once.
 * Every other session for that account is revoked.
 */
import crypto from 'node:crypto';
import { loadConfig } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { hashPassword, passwordProblems } from '../src/lib/auth.js';
import { logActivity } from '../src/lib/audit.js';

const arg = (name, fallback = '') => {
  const index = process.argv.indexOf(`--${name}`);
  return index === -1 ? fallback : process.argv[index + 1] ?? fallback;
};

const config = loadConfig();
const db = openDatabase(config.dbPath);

if (process.argv.includes('--list')) {
  const users = db.all('SELECT id, email, name, role, status, last_login_at FROM users ORDER BY id');
  console.log(`${config.dbPath}\n`);
  console.table(users.map((user) => ({
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
    status: user.status,
    lastSignIn: user.last_login_at || 'never',
  })));
  db.close();
  process.exit(0);
}

const email = arg('email').toLowerCase().trim();
if (!email) {
  console.error('Usage: npm run set-password -- --email you@example.com [--password "…"]  |  --list');
  db.close();
  process.exit(1);
}

const user = db.get('SELECT * FROM users WHERE email = ?', email);
if (!user) {
  const emails = db.all('SELECT email FROM users ORDER BY id').map((row) => `  · ${row.email}`).join('\n');
  console.error(`No account with that email.\nExisting accounts:\n${emails}`);
  db.close();
  process.exit(1);
}

const password = arg('password') || `Wx-${crypto.randomBytes(9).toString('base64url')}`;
const problems = passwordProblems(password);
if (problems.length) {
  console.error(`Password rejected: ${problems.join(' ')}`);
  db.close();
  process.exit(1);
}

db.run("UPDATE users SET password_hash = ?, status = 'active', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?", hashPassword(password), user.id);
const revoked = db.run('DELETE FROM sessions WHERE user_id = ?', user.id).changes;
logActivity(db, { user: null, action: 'user.password_reset_cli', entity: 'user', entityId: user.id, meta: { email, sessionsRevoked: revoked } });

console.log('Password updated');
console.log(`  account  : ${user.email} (${user.role})`);
console.log(`  password : ${password}`);
console.log(`  sessions : ${revoked} revoked — sign in again`);
console.log('\nStore it in a password manager, then change it at /admin/account if you want your own.');
db.close();
