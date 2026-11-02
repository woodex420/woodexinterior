// Synthetic opt-in preview only. Real Fortify requests; never point at production.
import {createRequire} from 'node:module';import fs from 'node:fs';import path from 'node:path';import {fileURLToPath} from 'node:url';import assert from 'node:assert/strict';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');const base=path.join(root,'.cache/preview');fs.mkdirSync(base+'/shots',{recursive:true});const require=createRequire(base+'/source/platform/.cache/browser/package.json');
const {chromium:pw}=require('playwright-core');const mod=require('@sparticuz/chromium');mod.setupLambdaEnvironment('/tmp/al2023/lib');
const browser=await pw.launch({executablePath:await mod.default.executablePath(),args:mod.default.args.filter(a=>!['--single-process','--disable-web-security'].includes(a)),headless:true});
const origin='http://localhost:8080',results=[];
// TEST-NET fixture isolates repeat runs of the real limiter in this trusted-proxy synthetic runtime.
const extraHTTPHeaders={'X-Forwarded-For':'192.0.2.'+(Math.floor(Math.random()*253)+1)};const context=await browser.newContext({extraHTTPHeaders,viewport:{width:390,height:844}});await context.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
async function check(name,fn){await fn();results.push({name,pass:true});console.log('PASS',name);}
async function loginPage(){await page.goto(origin+'/admin/login');await page.waitForFunction(()=>document.querySelector('[data-sign-in]')?.dataset.enhanced==='true');}
async function fields(){await page.locator('[name=email]').fill('owner@woodex.test');await page.locator('[name=password]').fill('Woodex-Preview-2026');}
try{
 await loginPage();
 assert(await page.getByLabel('Synthetic preview — not production').isVisible(),'Refusing a non-synthetic login target');
 await check('Demo credentials are prefilled but login remains unauthenticated',async()=>{assert.equal(await page.locator('[name=email]').inputValue(),'owner@woodex.test');assert.equal(await page.locator('[name=password]').inputValue(),'Woodex-Preview-2026');assert.equal(new URL(page.url()).pathname,'/admin/login');});
 for(const width of [360,390,768,1440])for(const dark of [false,true]){
  await check(`Glass login ${width}px / ${dark?'dark':'light'}`,async()=>{
   await page.setViewportSize({width,height:1000});await page.evaluate(d=>document.documentElement.classList.toggle('dark',d),dark);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   assert(await page.locator('button[type=submit]').isVisible());
   const card=await page.locator('.wx-auth-card').evaluate(e=>{const s=getComputedStyle(e);return {backdrop:s.backdropFilter,background:s.backgroundColor};});
   const reduced=await page.evaluate(()=>matchMedia('(prefers-reduced-transparency: reduce)').matches);if(reduced){assert.equal(card.backdrop,'none');}else{assert(card.backdrop.includes('blur'),JSON.stringify(card));assert(card.background.startsWith('rgba('));assert(Number(card.background.split(',').at(-1).replace(')','').trim())<=0.35);assert.equal(await page.locator('.wx-auth-card').count(),1);assert.equal(await page.locator('.wx-auth-story').count(),0);assert.equal(await page.locator('.wx-auth-demo').evaluate(e=>getComputedStyle(e).backgroundColor),'rgba(0, 0, 0, 0)');}
   const response=await page.request.get(origin+'/assets/img/svc-interior-design.webp');assert.equal(response.status(),200);
   await page.screenshot({path:base+'/shots/login104-'+width+'-'+(dark?'dark':'light')+'.png',fullPage:true});
  });
 }
 await check('Reduced transparency uses an opaque readable fallback',async()=>{
  const client=await context.newCDPSession(page);await client.send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-transparency',value:'reduce'},{name:'prefers-reduced-motion',value:'reduce'}]});
  assert(await page.evaluate(()=>matchMedia('(prefers-reduced-transparency: reduce)').matches));
  assert.equal(await page.locator('.wx-auth-card').evaluate(e=>getComputedStyle(e).backdropFilter),'none');
  assert.equal(await page.locator('.wx-auth-card').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(36, 36, 36)');
  await client.send('Emulation.setEmulatedMedia',{features:[]});await client.detach();
 });
 await page.setViewportSize({width:390,height:844});await page.evaluate(()=>document.documentElement.classList.remove('dark'));
 await page.locator('[name=password]').fill('');
 await check('Empty password produces visible feedback, including inside constrained layouts',async()=>{await page.locator('button[type=submit]').click();await page.getByRole('alert').filter({hasText:'Enter your password'}).waitFor();assert(await page.locator('button[type=submit]').isEnabled());});
 await check('Password visibility toggle is explicit and reversible',async()=>{await page.getByRole('button',{name:'Show password',exact:true}).click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'text');await page.getByRole('button',{name:'Hide password',exact:true}).click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'password');});
 await check('Mobile login has no horizontal overflow and has a new-tab fallback',async()=>{assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.equal(await page.getByRole('link',{name:'Open sign-in in a new tab'}).getAttribute('target'),'_blank');});
 await check('Bad password returns server error without a silent native POST',async()=>{await page.locator('[name=password]').fill('incorrect-synthetic-fixture');await page.locator('button[type=submit]').click();await page.getByRole('alert').filter({hasText:'credentials do not match'}).waitFor();assert.equal(new URL(page.url()).pathname,'/admin/login');assert(await page.locator('button[type=submit]').isEnabled());});
 await check('Actual async Fortify sign-in reaches dashboard and retains session after reload',async()=>{await fields();await page.locator('button[type=submit]').click();await page.waitForURL(u=>u.pathname==='/admin');await page.reload();assert.equal(new URL(page.url()).pathname,'/admin');});
 await page.setViewportSize({width:1440,height:1000});
 await check('Navigation filter expands matching permitted groups and supports clear/empty states',async()=>{
  await page.getByRole('searchbox',{name:'Filter navigation'}).fill('SEO');await page.locator('nav [data-nav-label]').filter({hasText:'SEO'}).first().waitFor({state:'visible'});
  await page.locator('[data-nav-label="Projects"]').waitFor({state:'hidden'});
  await page.getByRole('searchbox',{name:'Filter navigation'}).fill('no-screen-104');await page.getByRole('status').filter({hasText:'No matching screen'}).waitFor();
  await page.getByRole('button',{name:'Clear navigation filter'}).click();assert.equal(await page.getByRole('searchbox',{name:'Filter navigation'}).inputValue(),'');
 });
 await check('Both SEO audits switch via real Livewire requests without publishing',async()=>{
  await page.goto(origin+'/admin/website/seo-overview?audit=metadata&finding=indexing');await page.getByText('Canonical & social metadata',{exact:true}).waitFor();await page.getByRole('button',{name:'Canonical & social',exact:true}).click();await page.getByText('Canonical & social metadata',{exact:true}).waitFor();
  assert(await page.getByText('JSON-LD checks syntax only.',{exact:false}).isVisible());
  await page.getByRole('combobox',{name:'Finding category'}).selectOption('indexing');await page.getByRole('searchbox',{name:'Search metadata'}).fill('no-page-104');await page.getByText('No pages match these review filters.',{exact:false}).waitFor();
  await page.getByRole('button',{name:'Clear filters',exact:true}).click();await page.waitForFunction(()=>document.querySelector('#metadata-group')?.value==='all'&&document.querySelector('[aria-label="Search metadata"]')?.value==='');
  await page.getByRole('button',{name:/All pages/}).click();await page.waitForFunction(()=>document.querySelectorAll('main table tbody tr').length>1);
  await page.getByRole('button',{name:'Search listings',exact:true}).click();await page.getByRole('searchbox',{name:'Search listings'}).waitFor();
 });
 await check('All three Assistant diagnostics execute read-only and fit mobile',async()=>{
  await page.setViewportSize({width:390,height:844});await page.getByRole('button',{name:'Open the assistant',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Assistant',exact:true});
  await dialog.getByRole('button',{name:'Check PHP, database and sign-in health',exact:true}).click();await dialog.getByText('Runtime diagnostics — read-only.',{exact:false}).waitFor();
  await dialog.getByRole('button',{name:'Check website search metadata',exact:true}).click();await dialog.getByText('Website diagnostics — read-only.',{exact:false}).waitFor();
  await dialog.getByRole('button',{name:'Check connection setup safely',exact:true}).click();await dialog.getByText('Connection diagnostics — configuration',{exact:false}).waitFor();
  assert(await dialog.evaluate(e=>e.scrollWidth<=e.clientWidth));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await dialog.getByRole('button',{name:'Close the assistant'}).click();
 });
 await context.clearCookies();
 for(const [code,expectText] of [[419,'session expired'],[429,'Wait 30 seconds'],[503,'Sign-in could not finish']]){
  await loginPage();await fields();
  await page.route('**/admin/login',r=>r.request().method()==='POST'?r.fulfill({status:code,headers:{'Retry-After':'30'},body:'Synthetic test response'}):r.continue());
  await check('HTTP '+code+' is actionable and retry unlocks',async()=>{await page.locator('button[type=submit]').click();await page.getByRole('alert').filter({hasText:expectText}).waitFor();assert(await page.locator('button[type=submit]').isEnabled());});
  await page.unroute('**/admin/login');
 }
 await loginPage();await fields();await page.route('**/admin/login',r=>r.request().method()==='POST'?r.abort():r.continue());
 await check('Network failure is visible and never leaves the button busy',async()=>{await page.locator('button[type=submit]').click();await page.getByRole('alert').filter({hasText:'could not reach the server'}).waitFor();assert.equal(await page.locator('button[type=submit]').getAttribute('aria-busy'),null);assert(await page.locator('button[type=submit]').isEnabled());});await page.unroute('**/admin/login');
 await page.screenshot({path:base+'/shots/auth104-error-mobile.png'});
 await check('No uncaught auth JavaScript errors',async()=>assert.deepEqual(errors,[]));
 const frameContext=await browser.newContext({extraHTTPHeaders,permissions:['local-network-access']});
 await frameContext.route('**/*',r=>{const u=new URL(r.request().url());if(u.hostname==='127.0.0.1')return r.fulfill({contentType:'text/html',body:'<iframe sandbox="allow-scripts allow-same-origin" src="http://localhost:8080/admin/login" style="width:900px;height:900px"></iframe>'});return u.origin===origin?r.continue():r.abort();});
 const framePage=await frameContext.newPage();await framePage.goto('http://127.0.0.1:8080/harness');const loc=framePage.frameLocator('iframe');
 await check('Sign-in works even in a cross-site frame without allow-forms',async()=>{await loc.locator('[data-sign-in][data-enhanced=true]').waitFor();await loc.locator('[name=email]').fill('owner@woodex.test');await loc.locator('[name=password]').fill('Woodex-Preview-2026');await loc.locator('button[type=submit]').click();await loc.locator('a[href$="/admin/leads"]').first().waitFor({timeout:20000});assert(framePage.frames().some(f=>f.url().endsWith('/admin')));});
 await frameContext.close();
 const nativeContext=await browser.newContext({extraHTTPHeaders,javaScriptEnabled:false});const nativePage=await nativeContext.newPage();
 await check('JavaScript-disabled native POST still authenticates normally',async()=>{await nativePage.goto(origin+'/admin/login');await nativePage.locator('[name=email]').fill('owner@woodex.test');await nativePage.locator('[name=password]').fill('Woodex-Preview-2026');await Promise.all([nativePage.waitForURL(u=>u.pathname==='/admin'),nativePage.locator('button[type=submit]').click()]);});await nativeContext.close();
 for(const email of ['manager@woodex.test','accounts@woodex.test']){
  const roleContext=await browser.newContext({extraHTTPHeaders});const rolePage=await roleContext.newPage();
  await check('Fresh role account authenticates normally and cannot access owner health: '+email,async()=>{
   await rolePage.goto(origin+'/admin/login');await rolePage.waitForFunction(()=>document.querySelector('[data-sign-in]')?.dataset.enhanced==='true');
   await rolePage.locator('[name=email]').fill(email);await rolePage.locator('[name=password]').fill('Woodex-Preview-2026');await rolePage.locator('button[type=submit]').click();await rolePage.waitForURL(u=>u.pathname==='/admin');
   assert.equal((await rolePage.goto(origin+'/admin/system/health')).status(),403);
  });await roleContext.close();
 }
 await check('Public header and footer render approved SVG without flattening the gold dot',async()=>{
  const publicContext=await browser.newContext();await publicContext.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());const publicPage=await publicContext.newPage();
  try{await publicPage.goto(origin+'/');for(const selector of ['.brand-mark','.footer-brand img']){const image=publicPage.locator(selector).first();assert((await image.getAttribute('src')).endsWith('/assets/brand/woodex-mark.svg'));assert.equal(await image.evaluate(e=>getComputedStyle(e).filter),'none');}assert.equal((await publicPage.request.get(origin+'/assets/brand/woodex-mark.svg')).status(),200);}finally{await publicContext.close();}
 });

}catch(e){results.push({name:'browser failure',pass:false,error:e.message});console.error(e);console.error(await page.evaluate(()=>[...document.querySelectorAll('body *')].map(e=>({tag:e.tagName,class:e.className,left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width})).filter(e=>e.right>innerWidth||e.left<0).slice(0,20)));await page.screenshot({path:base+'/shots/login104-failure.png',fullPage:true});process.exitCode=1;}finally{await browser.close();fs.writeFileSync(base+'/104-brand-auth-browser.json',JSON.stringify({scope:'Local native runtime; mocked failures plus real native/async/constrained cross-site frame login; not actual Arena browser certification',results},null,2));}
