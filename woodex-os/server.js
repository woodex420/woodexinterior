import { createApp } from './src/app.js';

const { app, config, db } = createApp();

const server = app.listen(config.port, config.host, () => {
  console.log(`\nWoodex OS v2.1 — ${config.env}`);
  console.log(`  site   : http://localhost:${config.port}`);
  console.log(`  admin  : http://localhost:${config.port}/admin`);
  console.log(`  api    : http://localhost:${config.port}/api/v1/health`);
  console.log(`  db     : ${config.dbPath}\n`);
});

function shutdown(signal) {
  console.log(`\n${signal} received — closing.`);
  server.close(() => {
    try { db.close(); } catch { /* already closed */ }
    process.exit(0);
  });
  setTimeout(() => process.exit(0), 3000).unref();
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
