import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
export const groups = ['unit', 'feature-a-f', 'feature-g-p', 'feature-q-z', 'arch'];
export function groupFor(file) {
  if (file === 'tests/ArchTest.php') return 'arch';
  if (file.startsWith('tests/Unit/')) return 'unit';
  if (!file.startsWith('tests/Feature/')) throw new Error('Unexpected test location');
  const initial = file.slice('tests/Feature/'.length)[0].toLowerCase();
  return initial <= 'f' ? 'feature-a-f' : initial <= 'p' ? 'feature-g-p' : 'feature-q-z';
}
export function discover(root) {
  const walk = dir => fs.readdirSync(path.join(root, dir), {withFileTypes:true}).flatMap(e => {
    const name = `${dir}/${e.name}`;
    if (e.isSymbolicLink()) throw new Error('Test symlinks are not supported');
    return e.isDirectory() ? walk(name) : name.endsWith('Test.php') ? [name] : [];
  });
  return [...walk('tests/Unit'), ...walk('tests/Feature'), 'tests/ArchTest.php'].sort();
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const group = process.argv[2];
  if (!groups.includes(group)) throw new Error('Unknown test group');
  const files = discover(path.resolve('platform')).filter(file => groupFor(file) === group);
  if (!files.length) throw new Error('Empty test group');
  console.log(files.join('\n'));
}
