import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
export function parseLog(raw) {
  const text=raw.replace(/(?:\x1b\[|\^\[\[)[0-9;]*m/g,'');
  const summary=[...text.matchAll(/^\s*Tests:\s*(.+)$/gm)].at(-1)?.[1]??null;
  const starts=[...text.matchAll(/^\s*FAILED\s+.+$/gm)];
  const failures=starts.map((m,i)=>{
    const block=text.slice(m.index, starts[i+1]?.index??text.length).trim();
    return block.length<=1100?block:block.slice(0,730)+'\n…\n'+block.slice(-350);
  });
  return {summary,failures};
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  const dir=process.argv[2];
  const records={};
  for(const group of ['unit','feature-a-f','feature-g-p','feature-q-z','arch']) {
    const file=path.join(dir,group+'.log');
    if(fs.existsSync(file)) records[group]=parseLog(fs.readFileSync(file,'utf8'));
  }
  fs.writeFileSync(path.join(dir,'summary.json'),JSON.stringify(records,null,2)+'\n');
  const escape=s=>s.replaceAll('%','%25').replaceAll('\r','%0D').replaceAll('\n','%0A');
  console.log('::notice title=Native batch counts::'+escape(JSON.stringify(Object.fromEntries(Object.entries(records).map(([k,v])=>[k,v.summary])))));
  let details=Object.entries(records).flatMap(([k,v])=>v.failures.map(f=>k+'\n'+f)).join('\n\n');
  if(!details && process.argv[3]!=='0') {
    const logs=['install.log','build.log'].filter(f=>fs.existsSync(path.join(dir,f)));
    details=logs.map(f=>f+'\n'+fs.readFileSync(path.join(dir,f),'utf8').slice(-2200)).join('\n');
  }
  // GitHub limits annotation messages to 4096 characters and errors to ten per step.
  // Keep full per-group logs as artifacts; expose a bounded batch inventory, not ANSI noise.
  for(let i=0;i<Math.min(details.length,31500);i+=3500)
    console.log('::error title=Native failure batch '+String(i/3500).padStart(2,'0')+'::'+escape(details.slice(i,i+3500)));
}
