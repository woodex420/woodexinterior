import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
export function parseLog(raw) {
  const text=raw.replace(/(?:\x1b\[|\^\[\[)[0-9;]*m/g,'');
  const summary=[...text.matchAll(/^\s*Tests:\s*(.+)$/gm)].at(-1)?.[1]??null;
  const starts=[...text.matchAll(/^\s*FAILED\s+.+$/gm)];
  const failures=starts.map((m,i)=>{
    const block=text.slice(m.index, starts[i+1]?.index??text.length).replace(/^\s*─+\s*$/gm,'').trim();
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
  const resultFile=path.join(dir,'results.tsv');
  if(fs.existsSync(resultFile)) {
    for(const line of fs.readFileSync(resultFile,'utf8').trim().split('\n').slice(1)) {
      const [group,code]=line.split('\t');
      if(records[group]) records[group].exitCode=Number(code);
    }
  }
  fs.writeFileSync(path.join(dir,'summary.json'),JSON.stringify(records,null,2)+'\n');
  const escape=s=>s.replaceAll('%','%25').replaceAll('\r','%0D').replaceAll('\n','%0A');
  console.log('::notice title=Native batch counts::'+escape(JSON.stringify(Object.fromEntries(Object.entries(records).map(([k,v])=>[k,{summary:v.summary,exitCode:v.exitCode}])))));
  let details=Object.entries(records).flatMap(([k,v])=>{
    if(v.failures.length) return v.failures.map(f=>k+'\n'+f);
    if(v.exitCode) {
      const raw=fs.readFileSync(path.join(dir,k+'.log'),'utf8').replace(/(?:\x1b\[|\^\[\[)[0-9;]*m/g,'');
      const issue=raw.match(/^.*(?:warn|deprecat|risky|exception|error|multiple test|no tests).*$/im);
      return [k+' exit='+v.exitCode+'\n'+(issue?raw.slice(Math.max(0,issue.index-100),issue.index+1800):raw.slice(-2200))];
    }
    return [];
  }).join('\n\n');
  if(!details && process.argv[3]!=='0') {
    const logs=['install.log','build.log'].filter(f=>fs.existsSync(path.join(dir,f)));
    details=logs.map(f=>f+'\n'+fs.readFileSync(path.join(dir,f),'utf8').slice(-2200)).join('\n');
  }
  // GitHub limits annotation messages to 4096 characters and errors to ten per step.
  // Keep full per-group logs as artifacts; expose a bounded batch inventory, not ANSI noise.
  let offset=0;
  for(let part=0;part<9 && offset<details.length;part++) {
    let end=Math.min(offset+3500,details.length);
    while(Buffer.byteLength(details.slice(offset,end),'utf8')>3500) end--;
    console.log('::error title=Native failure batch '+String(part).padStart(2,'0')+'::'+escape(details.slice(offset,end)));
    offset=end;
  }
}
