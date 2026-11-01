// Filter ONLY newly generated disposable QA passwords, never original app envs.
import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline';
const file = path.resolve('tools/docker/.env');
if (process.env.GITHUB_ACTIONS !== 'true' || !fs.existsSync(file) || fs.lstatSync(file).isSymbolicLink() || fs.statSync(file).size > 256) {
  console.error('HOLD: fresh disposable QA credential filter unavailable.'); process.exit(64);
}
const lines = fs.readFileSync(file, 'utf8').trim().split('\n');
if (lines.length !== 2 || !lines[0].match(/^WOODEX_QA_PASSWORD=[a-f0-9]{48}$/) || !lines[1].match(/^WOODEX_QA_ROOT_PASSWORD=[a-f0-9]{48}$/)) {
  console.error('HOLD: unexpected disposable QA credential format.'); process.exit(64);
}
const values = lines.map(l => l.split('=')[1]);
if (process.argv[2] === '--mask') {
  for (const value of values) console.log('::add-mask::' + value);
} else {
  for await (const line of readline.createInterface({ input: process.stdin, crlfDelay: Infinity })) {
    let output = line;
    for (const value of values) output = output.replaceAll(value, '[DISPOSABLE_QA_PASSWORD_REDACTED]');
    output = output.replace(/https?:\/\/[^\s<>"']+/g, raw => {
      try { const u = new URL(raw); u.username=''; u.password=''; u.search=''; u.hash=''; return u.toString(); } catch { return '[URL REDACTED]'; }
    });
    console.log(output);
  }
}
