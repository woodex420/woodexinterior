// Browser CSS/layout contracts only. Synthetic markup uses actual shared classes,
// built assets, pure PHP navigation data and actual pre-paint resolver. NO backend/login/data writes.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const scratch=path.join(root,'.cache/ui79');
const require=createRequire(path.join(scratch,'package.json'));
const {default:puppeteer}=await import(require.resolve('puppeteer-core'));
const {default:chromium}=await import(require.resolve('@sparticuz/chromium'));
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const manifest=JSON.parse(read('platform/public/build/manifest.json'));
const layout=read('platform/resources/views/layouts/admin.blade.php');
const prepaint=layout.match(/<script>([\s\S]*?)<\/script>/)[1];
const sidebar=read('platform/resources/views/components/admin/sidebar.blade.php').match(/class="(wx-admin-sidebar[^"]+)"/)[1];
const header=read('platform/resources/views/components/admin/header.blade.php').match(/<header class="([^"]+)"/)[1];
const settings=read('platform/resources/views/livewire/admin/system/settings-page.blade.php').match(/class="(wx-settings-tabs[^"]+)"/)[1];
const nav=JSON.parse(fs.readFileSync(path.join(scratch,'nav-after.json'),'utf8')).find(g=>g.title==='Website');
assert.equal(nav.items.length,7);
const escape=s=>s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
function html(mode) {
 return `<!doctype html><html class="h-full"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f6f6f6"><script>localStorage.setItem('wx.theme',${JSON.stringify(mode)});</script><script>${prepaint}</script><link rel="stylesheet" href="/build/${manifest['resources/css/admin.css'].file}"></head><body class="wx-admin h-full"><div class="min-h-screen xl:flex"><aside class="${sidebar}" aria-label="Fixture navigation"><div class="flex h-[72px] shrink-0 items-center px-2 text-navy-900 dark:text-white">WOODEX — LOCAL STYLE QA</div><nav><h2 class="mb-2 px-3 text-theme-xs text-gray-500">${escape(nav.title)}</h2><ul>${nav.items.map(i=>`<li><a href="#fixture" class="menu-item menu-item-inactive">${escape(i.label)}</a></li>`).join('')}</ul></nav></aside><div class="flex min-w-0 flex-1 flex-col xl:ml-(--wx-sidebar-w)"><header class="${header}"><span class="text-theme-sm">${mode} appearance</span><span class="ml-auto text-theme-xs text-gray-500">Local fixture · not live</span></header><main class="mx-auto w-full max-w-(--breakpoint-2xl) flex-1 p-4 md:p-6"><h1 class="mb-2 text-title-sm text-gray-800 dark:text-white/90">Admin appearance check</h1><p class="mb-6 text-gray-500">Synthetic layout contract only. No production records, login, saves or backend acceptance.</p><section class="wx-surface rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><h2 class="mb-3 text-theme-xl">Settings</h2><div id="tabs" class="${settings}">${['Business','Sales rules','Lead rules','Notifications','WhatsApp','Bot'].map(t=>`<button class="rounded-md px-4 py-1.5 text-theme-sm">${t}</button>`).join('')}</div><label class="block">Example field<input id="input" class="wx-input mt-2" placeholder="Local fixture only"></label><button id="primary" class="wx-primary-button mt-4 inline-flex h-10 items-center rounded-lg px-4" type="button">Appearance sample</button></section></main></div></div></body></html>`;
}
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:chromium.args,headless:true});
const cases=[],failures=[],requests=[];
try {
 for(const width of [360,390,768,1440])for(const systemDark of [false,true])for(const mode of ['light','dark','system']) {
  const page=await browser.newPage();
  try {
   await page.setViewport({width,height:1000});await page.emulateMediaFeatures([{name:'prefers-color-scheme',value:systemDark?'dark':'light'}]);
   await page.setRequestInterception(true);
   page.on('request',async r=>{
    try {
     const u=new URL(r.url());
     if(u.origin!=='http://ui79.test'){requests.push('blocked external');return await r.abort();}
     if(u.pathname==='/')return await r.respond({status:200,contentType:'text/html; charset=utf-8',body:html(mode)});
     const base=path.join(root,'platform/public/build'),target=path.resolve(base,'.'+u.pathname.replace(/^\/build/,''));
     if(!u.pathname.startsWith('/build/')||!target.startsWith(base+path.sep)||!fs.existsSync(target)){requests.push(u.pathname);return await r.respond({status:404,body:'Not found'});}
     const types={'.css':'text/css','.js':'text/javascript','.woff2':'font/woff2','.otf':'font/otf'};
     await r.respond({status:200,contentType:types[path.extname(target)]||'application/octet-stream',body:fs.readFileSync(target)});
    } catch(e){failures.push(String(e));try{await r.abort();}catch{}}
   });
   await page.goto('http://ui79.test/',{waitUntil:'networkidle0',timeout:30000});
   const data=await page.evaluate(()=>{
    const style=s=>getComputedStyle(document.querySelector(s));
    return {page:style('body').backgroundColor,surface:style('.wx-surface').backgroundColor,sidebar:style('aside').backgroundColor,primary:style('#primary').backgroundColor,onPrimary:style('#primary').color,input:style('#input').backgroundColor,meta:document.querySelector('meta[name="theme-color"]').content,overflow:document.documentElement.scrollWidth>innerWidth+1,tabRows:new Set([...document.querySelectorAll('#tabs button')].map(e=>Math.round(e.getBoundingClientRect().top))).size};
   });
   const dark=mode==='dark'||(mode==='system'&&systemDark);
   assert.equal(data.page,dark?'rgb(23, 23, 23)':'rgb(246, 246, 246)');assert.equal(data.surface,dark?'rgb(33, 33, 33)':'rgb(255, 255, 255)');assert.equal(data.sidebar,data.surface);assert.equal(data.input,data.surface);assert.equal(data.primary,dark?'rgb(229, 229, 229)':'rgb(48, 48, 48)');assert.equal(data.onPrimary,dark?'rgb(23, 23, 23)':'rgb(255, 255, 255)');assert.equal(data.meta,dark?'#171717':'#f6f6f6');assert.equal(data.overflow,false);if(width<=390)assert(data.tabRows>1);
   cases.push({width,systemDark,mode,passed:true,checks:data});
   if(width===1440&&systemDark&&mode==='dark')await page.screenshot({path:path.join(root,'docs/audit/evidence/79/charcoal-layout-fixture.png'),fullPage:true});
   if(width===390&&!systemDark&&mode==='light')await page.screenshot({path:path.join(root,'docs/audit/evidence/79/light-mobile-fixture.png'),fullPage:true});
  } finally {await page.close();}
 }
 assert.deepEqual(requests,[]);assert.deepEqual(failures,[]);
} finally {await browser.close();fs.writeFileSync(path.join(scratch,'browser-results.json'),JSON.stringify({scope:'Synthetic shared CSS/layout contracts, not Laravel/live backend or complete app acceptance',cases,requestFailures:requests,errors:failures},null,2)+'\n');}
console.log(`PASS ${cases.length} browser cases across360/390/768/1440, Light/Dark/System and both system preferences; no overflow or missing asset requests. NOT backend acceptance.`);
