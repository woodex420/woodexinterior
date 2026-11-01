// Curated SOURCE-ONLY resolver diagnostics, readable through GitHub's annotations API.
// Never use on application-test logs, env, DB, storage, vendor, backups or an entire workspace.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const FILES = ['lock-alignment.json', 'resolve-platform.log', 'resolve-root.log', 'native-runtime.json', 'image-build.log'];
export function safeExcerpt(text) {
  return text.replace(/\x1b\[[0-9;]*m/g, '').replace(/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/g, '')
    .replace(/https?:\/\/[^\s<>"']+/g, raw => {
      try { const u = new URL(raw); u.username = ''; u.password = ''; u.search = ''; u.hash = ''; return u.toString(); }
      catch { return '[URL omitted]'; }
    }).split('\n').slice(-45).join('\n').slice(-6000);
}
export function annotation(message) {
  return '::error title=Source-only native QA diagnostic::' + message.replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A');
}
export function collect(root) {
  const base = path.join(root, '.cache/qa82/evidence');
  // Require real non-symlink directories/files. Only known resolver/build outputs are eligible.
  for (const dir of [path.join(root, '.cache'), path.join(root, '.cache/qa82'), base]) {
    if (!fs.existsSync(dir) || fs.lstatSync(dir).isSymbolicLink() || !fs.lstatSync(dir).isDirectory()) return 'HOLD: curated resolver evidence unavailable. No broader files inspected.';
  }
  for (const name of FILES) {
    const file = path.join(base, name);
    if (!fs.existsSync(file)) continue;
    const st = fs.lstatSync(file);
    if (st.isSymbolicLink() || !st.isFile() || st.size === 0 || st.size > 16 * 1024 * 1024) continue;
    const text = safeExcerpt(fs.readFileSync(file, 'utf8'));
    return `HOLD — latest curated source-only evidence: ${name}\n${text}\nNot application/native-suite or deployment acceptance.`;
  }
  return 'HOLD: no nonempty curated resolver evidence. No broader files inspected.';
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  if (process.env.GITHUB_ACTIONS !== 'true') {
    console.error('HOLD: diagnostic entrypoint is GitHub Actions only; use pure exported functions for contract tests.');
    process.exitCode = 64;
  } else {
    console.log(annotation(collect(process.cwd())));
  }
}
