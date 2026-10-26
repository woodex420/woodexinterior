export function logActivity(db, { user, action, entity = null, entityId = null, ip = null, meta = null }) {
  db.run(
    `INSERT INTO activity_log (user_id, user_email, action, entity, entity_id, ip, meta, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, strftime('%Y-%m-%dT%H:%M:%SZ','now'))`,
    user?.id ?? null,
    user?.email ?? 'system',
    action,
    entity,
    entityId == null ? null : String(entityId),
    ip,
    meta ? JSON.stringify(meta).slice(0, 2000) : null,
  );
}

export function recentActivity(db, limit = 25) {
  return db.all('SELECT * FROM activity_log ORDER BY id DESC LIMIT ?', limit);
}
