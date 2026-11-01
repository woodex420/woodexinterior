// Sandbox-only proof for Step 48. Requires setup.sh's four demo quotations and a running preview.
// Checks both business lines, typing without blur, all three layouts, mobile width and JS errors.
// Restores the demo SQLite file in finally. Never run this against a production database.
import {createRequire} from 'node:module';
import fs from 'node:fs';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
fs.mkdirSync('/tmp/woodex-run/shots', {recursive:true});
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1600,height:1000}});
const page=await browser.newPage(); const errors=[];
page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
const base=`http://127.0.0.1:${process.env.PORT || 33143}`;
await page.goto(base+'/admin/login',{waitUntil:'networkidle0'});
await page.type('input[name=email]','owner@woodex.test'); await page.type('input[name=password]','Woodex-Preview-2026');
await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('button[type=submit]')]);

const db='/tmp/woodex-run/app/database/database.sqlite'; const backup=fs.readFileSync(db);
try {
for (const id of [4,3]) {
 await page.goto(base+'/admin/quotes/'+id,{waitUntil:'networkidle0'});
 await page.waitForSelector('iframe[title="Quotation live preview"]');
 const iframe=await page.$('iframe[title="Quotation live preview"]'); const frame=await iframe.contentFrame();
 await frame.waitForSelector('.v21-header');
 const rects=await page.evaluate(()=>{const e=document.querySelector('.quotation-editor').getBoundingClientRect(),p=document.querySelector('.quotation-preview').getBoundingClientRect();return {e:{left:e.left,right:e.right},p:{left:p.left,right:p.right}}});
 if(rects.e.right>rects.p.left)throw Error('preview overlaps editor');
 const savedTotalBefore=await page.$eval('[data-quote-total]',el=>el.innerText);
 const qty=await page.$('input[aria-label="Quantity"]');
 await qty.evaluate(el=>{el.focus();el.select()});await qty.type('2');
 await page.waitForFunction(()=>document.querySelector('iframe[title="Quotation live preview"]').contentDocument.body.innerText.includes('2'),{timeout:20000});
 await page.waitForFunction(()=>document.querySelector('[role="status"]').innerText.includes('Live editing preview'),{timeout:20000});
 if(await page.$eval('[data-quote-total]',el=>el.innerText)!==savedTotalBefore)throw Error('preview unexpectedly saved pending input');
 await frame.waitForFunction(()=>[...document.querySelectorAll('table.lines tbody tr')].some(r=>r.cells.length===6 && r.cells[2].innerText.trim()==='2'));
 if(!(await qty.evaluate(el=>document.activeElement===el)))throw Error('focus lost');
 console.log('INPUT WITHOUT BLUR',id, await frame.$eval('tbody',el=>el.innerText.slice(0,150)));
 await page.screenshot({path:'/tmp/woodex-run/shots/step48-'+id+'-classic.png',fullPage:false});
 await page.select('#quoteLayout','project');
 await page.waitForFunction(()=>document.querySelector('iframe[title="Quotation live preview"]').contentDocument.body?.dataset.quotationLayout==='project',{timeout:20000});
 console.log('PROJECT TRADES',await frame.$$eval('.trade-page',els=>els.length));
 await page.screenshot({path:'/tmp/woodex-run/shots/step48-'+id+'-project.png'});
 await page.select('#quoteLayout','single');
 await page.waitForFunction(()=>document.querySelector('iframe[title="Quotation live preview"]').contentDocument.body?.dataset.quotationLayout==='single',{timeout:20000});
 await page.screenshot({path:'/tmp/woodex-run/shots/step48-'+id+'-single.png'});
 const rate=await page.$('input[aria-label="Rate in rupees"]');
 await rate.evaluate(el=>{el.focus();el.select()}); await rate.type('12345');
 await frame.waitForFunction(()=>document.body.innerText.includes('12,345'));
 if(!(await rate.evaluate(el=>document.activeElement===el)))throw Error('rate focus lost');
 const client=await page.$('[id="client.name"]');
 await client.evaluate(el=>{el.focus();el.select()}); await client.type('Live Client Proof');
 await frame.waitForFunction(()=>document.body.innerText.includes('Live Client Proof'));
 if(!(await client.evaluate(el=>document.activeElement===el)))throw Error('client focus lost');
 const scope=await page.$('#details-scope');
 await scope.evaluate(el=>{el.focus();el.select()}); await scope.type('- Runtime scope proof');
 await frame.waitForFunction(()=>document.body.innerText.includes('Runtime scope proof'));
 if(!(await scope.evaluate(el=>document.activeElement===el)))throw Error('scope focus lost');
 console.log('RATE CLIENT SCOPE LIVE WITHOUT BLUR',id);
 await page.setViewport({width:390,height:844});
 await page.waitForFunction(()=>document.documentElement.scrollWidth<=window.innerWidth+1);
 await page.screenshot({path:'/tmp/woodex-run/shots/step48-'+id+'-phone.png'});
 await page.setViewport({width:1600,height:1000});
}
console.log('JS_ERRORS',errors); if(errors.length)throw Error(errors.join('\n'));
} finally { await browser.close(); fs.writeFileSync(db,backup); }
