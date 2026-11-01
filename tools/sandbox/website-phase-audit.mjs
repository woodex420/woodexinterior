// Read-only browser baseline on the guarded, existing isolated QA copy; never initialize fixtures here.
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
execFileSync('/tmp/woodex-run/php',['/home/user/woodexinterior/tools/sandbox/theme-chat-fixture.php','--inspect']);
const out='/tmp/woodex49/browser',base='http://127.0.0.1:33144';
fs.mkdirSync(out,{recursive:true});
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1440,height:1000}});
const page=await browser.newPage(),errors=[],results=[];
page.on('pageerror',e=>errors.push(e.message));
const paths=['media','seo-overview','seo','speed','health','theme','settings'];
try {
 await page.goto(base+'/admin/login',{waitUntil:'domcontentloaded'});
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.click('#wx-preview-login button')]);
 for(const slug of paths){
  const path='/admin/website/'+slug;
  const response=await page.goto(base+path,{waitUntil:'domcontentloaded'});
  await page.waitForFunction(()=>!!window.Alpine?.store('theme'));
  await page.select('[aria-label="Color theme"]','dark');
  await page.waitForFunction(()=>document.documentElement.classList.contains('dark'));
  const desktop=await page.evaluate(()=>({heading:document.querySelector('main h1')?.innerText,overflow:document.documentElement.scrollWidth>innerWidth+1,previewFrames:document.querySelector('main').querySelectorAll('iframe').length,background:getComputedStyle(document.body).backgroundColor,ttfbMs:Math.round(performance.getEntriesByType('navigation')[0].responseStart),domMs:Math.round(performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd)}));
  await page.screenshot({path:out+'/49-'+slug+'-desktop.png'});
  // Second direct request is a warm local sample, not a Lighthouse/production benchmark.
  const warmResponse=await page.goto(base+path,{waitUntil:'domcontentloaded'});
  const warm=await page.evaluate(()=>({ttfbMs:Math.round(performance.getEntriesByType('navigation')[0].responseStart),domMs:Math.round(performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd)}));
  await page.setViewport({width:390,height:844});
  await page.waitForFunction(()=>document.querySelector('aside[aria-label="Main navigation"]').getBoundingClientRect().right<=1);
  const mobile=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,scrollWidth:document.documentElement.scrollWidth}));
  if(['media','theme','settings','speed'].includes(slug))await page.screenshot({path:out+'/49-'+slug+'-mobile.png'});
  results.push({path,status:response.status(),warmStatus:warmResponse.status(),desktop,warm,mobile});
  console.log(JSON.stringify(results.at(-1)));
  await page.setViewport({width:1440,height:1000});
 }
 await page.goto(base+'/admin/website/media',{waitUntil:'domcontentloaded'});
 const navPromise=page.waitForFunction(()=>location.pathname==='/admin/website/seo-overview');
 await page.click('aside[aria-label="Main navigation"] a[href$="/admin/website/seo-overview"]');await navPromise;
 await page.waitForSelector('main h1');
 const nav={path:new URL(page.url()).pathname,heading:await page.$eval('main h1',e=>e.innerText)};
 await page.click('[aria-label^="Website chat"]');
 await page.waitForSelector('aside[aria-label="Website chats"]',{visible:true});
 const chat=await page.$eval('aside[aria-label="Website chats"]',e=>({width:Math.round(e.getBoundingClientRect().width),height:Math.round(e.getBoundingClientRect().height),hasThreadNavigation:!!e.querySelector('nav[aria-label="Website conversations"]')}));
 await page.screenshot({path:out+'/49-chat-existing-drawer.png'});
 fs.writeFileSync(out+'/results.json',JSON.stringify({scope:'isolated QA read-only browser baseline, no saves/uploads/deletes/provider calls; two local page-load samples, not production benchmarks',results,nav,chat,errors},null,2)+'\n');
 if(results.some(r=>r.status!==200||r.warmStatus!==200)||errors.length)throw Error('One or more page status/JavaScript checks failed; inspect results.json');
} finally {await browser.close();}
