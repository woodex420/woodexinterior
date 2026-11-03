// Controlled alternate-origin regression: raw E2B-origin requests are denied; no traffic token is read or propagated.
// Explicit native synthetic-direct launcher only. Browser uses HTTPS; every Cookie and Set-Cookie is dropped.
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const base=path.join(root,'.cache/preview');
const require=createRequire(base+'/source/platform/.cache/browser/package.json');
const {chromium}=require('playwright-core');const mod=require('@sparticuz/chromium');mod.setupLambdaEnvironment('/tmp/al2023/lib');
const {inflate}=await import(base+'/source/platform/.cache/browser/node_modules/@sparticuz/chromium/build/lambdafs.js');
await inflate(base+'/source/platform/.cache/browser/node_modules/@sparticuz/chromium/bin/al2023.tar.br');
const environment=fs.readFileSync(base+'/source/platform/.env','utf8');
assert(/^WOODEX_PREVIEW_DIRECT_ADMIN=true$/m.test(environment),'Refusing a normal authentication target');
const front='https://authorized-preview.e2b.app';
assert(new URL(environment.match(/^APP_URL=(.+)$/m)[1].trim()).host.endsWith('.e2b.app'),'Refusing a non-sandbox backend');
const browser=await chromium.launch({executablePath:await mod.default.executablePath(),args:mod.default.args.filter(a=>!['--single-process','--disable-web-security'].includes(a)),headless:true});
const context=await browser.newContext({viewport:{width:1440,height:1000}});const results=[],foreign=[],errors=[],posts=[];
let stripped=0;const statuses=[];
await context.route('**/*',async route=>{
 const request=route.request(),url=new URL(request.url());
 if(url.origin!==front){foreign.push(url.origin);return route.abort();}
 const headers={...request.headers(),'accept-encoding':'identity',cookie:''};delete headers['content-length'];
 if(request.method()==='POST')posts.push(url.pathname);
 const response=await fetch('http://127.0.0.1:8080'+url.pathname+url.search,{method:request.method(),headers,body:request.postDataBuffer()||undefined,redirect:'manual'});
 if(request.method()==='POST')statuses.push({path:url.pathname,status:response.status,contextHeader:!!headers['x-woodex-demo-context'],fetchSite:headers['sec-fetch-site']||null});
 const out=Object.fromEntries(response.headers.entries());delete out['set-cookie'];delete out['content-length'];delete out['content-encoding'];stripped++;
 await route.fulfill({status:response.status,headers:out,body:Buffer.from(await response.arrayBuffer())});
});
const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
async function check(name,fn){await fn();results.push({name,pass:true});console.log('PASS',name);}
async function admin(url='/admin'){const response=await page.goto(front+url);assert.equal(response.status(),200);assert.equal(response.headers()['x-woodex-preview'],'synthetic-direct');await page.locator('[data-direct-preview-banner]').waitFor();}
try{
 await check('Dashboard opens directly without a login POST or cookie',async()=>{await admin();assert.equal(page.url(),front+'/admin');assert.equal(posts.filter(p=>p.endsWith('/login')).length,0);assert.equal(await page.evaluate(()=>document.cookie),'');});
 await check('Dashboard survives reload and a new tab without cookies',async()=>{await page.reload();await page.locator('[data-direct-preview-banner]').waitFor();const tab=await context.newPage();try{assert.equal((await tab.goto(front+'/admin')).status(),200);await tab.locator('[data-direct-preview-banner]').waitFor();}finally{await tab.close();}});
 await check('Sidebar click remains inside the authorized wrapper origin',async()=>{
  await page.getByRole('link',{name:'Contacts',exact:true}).first().click();await page.waitForURL(front+'/admin/contacts');assert.equal(new URL(page.url()).origin,front);
 });
 await check('Transparent welcome page pauses sign-in and opens direct admin',async()=>{await page.goto(front+'/admin/login');await page.getByLabel('Direct synthetic demo — not production').waitFor();assert.equal(await page.locator('form[data-sign-in]').count(),0);assert.equal(await page.locator('input[type=password]').count(),0);assert((await page.locator('.wx-auth-card').evaluate(e=>getComputedStyle(e).backgroundColor)).startsWith('rgba('));await page.screenshot({path:base+'/shots/resume-welcome.png',fullPage:true,animations:'disabled',timeout:60000});await page.getByRole('link',{name:'Open updated admin',exact:true}).click();await page.waitForURL(front+'/admin');await page.locator('[data-direct-preview-banner]').waitFor();});
 await check('Explicit solid/glass preference still works on the paused welcome page',async()=>{await page.goto(front+'/admin/login');await page.getByRole('button',{name:'Glass appearance',exact:true}).click();assert.equal(await page.locator('.wx-auth-card').evaluate(e=>getComputedStyle(e).backdropFilter),'none');await page.reload();await page.getByRole('button',{name:'Solid contrast',exact:true}).click();assert((await page.locator('.wx-auth-card').evaluate(e=>getComputedStyle(e).backdropFilter)).includes('blur'));});
 for(const [label,url]of [['Projects','/admin/projects'],['Leads','/admin/leads'],['Vendors and costs','/admin/projects/costs'],['Website SEO','/admin/website/seo-overview']]){
  await check(label+' opens with no retained authentication session',async()=>{await admin(url);});
 }
 await check('Real Livewire SEO review actions work with cookies stripped',async()=>{
  await admin('/admin/website/seo-overview?audit=metadata&finding=indexing');await page.getByText('Canonical & social metadata',{exact:true}).waitFor();
  await page.getByRole('searchbox',{name:'Search metadata'}).fill('no-page-direct-106');await page.getByText('No pages match these review filters.',{exact:false}).waitFor();
  await page.getByRole('button',{name:'Clear filters',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[aria-label="Search metadata"]')?.value==='');
 });
 await check('Assistant executes a read-only diagnostic with no cookies',async()=>{
  await page.getByRole('button',{name:'Open the assistant',exact:true}).click();const dialog=page.getByRole('dialog',{name:'Assistant',exact:true});
  await dialog.getByRole('button',{name:'Check website search metadata',exact:true}).click();await dialog.getByText('Website diagnostics — read-only.',{exact:false}).waitFor();
  await dialog.getByRole('button',{name:'Close the assistant'}).click();
 });
 await check('Native website chat opens and closes via Livewire with no cookies',async()=>{
  await page.getByRole('button',{name:/^Website chat/}).click();const drawer=page.getByRole('dialog',{name:'Website chats',exact:true});await drawer.waitFor();
  await drawer.getByRole('button',{name:'Close',exact:true}).click();await drawer.waitFor({state:'hidden'});
 });
 await check('Direct mode still rejects a POST without the normal CSRF token',async()=>{
  const status=await page.evaluate(async()=>{const uri=document.querySelector('script[data-update-uri]').dataset.updateUri;return (await fetch(uri,{method:'POST',headers:{'Content-Type':'application/json','X-Livewire':'1'},body:JSON.stringify({components:[]})})).status;});assert.equal(status,419);
 });
 await check('Mobile direct admin fits without horizontal overflow',async()=>{await page.setViewportSize({width:390,height:844});await admin();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:base+'/shots/resume-dashboard-mobile.png',fullPage:true,animations:'disabled',timeout:60000});});
 await check('All test traffic remains on the preview origin and no browser cookie is retained',async()=>{assert.deepEqual(foreign,[]);assert.deepEqual(await context.cookies(),[]);assert.equal(posts.filter(p=>p.endsWith('/login')).length,0);assert(posts.some(p=>p.includes('/update')),'No real Livewire POST observed');assert(stripped>10);assert.deepEqual(errors,[]);});
 await page.setViewportSize({width:1440,height:1000});await admin();await page.screenshot({path:base+'/shots/resume-dashboard-desktop.png',fullPage:true,animations:'disabled',timeout:60000});
}catch(error){results.push({name:'failure',pass:false,error:error.message});console.error(error);console.error('Page errors',errors);console.error('Metadata',await page.locator('meta[name=woodex-demo-context]').count());await page.screenshot({path:base+'/shots/resume-direct-failure.png',fullPage:true,animations:'disabled',timeout:60000});process.exitCode=1;}
finally{await browser.close();fs.writeFileSync(base+'/108-preview-origin-results.json',JSON.stringify({scope:'Controlled HTTPS forwarding to the native sandbox, deliberately dropping every Cookie/Set-Cookie; not actual Arena parent certification',strippedRequests:stripped,statuses,results},null,2));}
