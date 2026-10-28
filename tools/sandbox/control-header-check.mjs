// Isolated copied demo only: opening chat marks its selected thread read. Never run on the owner's DB.
import fs from 'node:fs';
import {createRequire} from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
if (!fs.readFileSync('/tmp/woodex44/qa/app/.env','utf8').includes('DB_DATABASE=/tmp/woodex44/qa/app/database/database.sqlite')) throw Error('Isolated copied QA database required');
const base='http://127.0.0.1:33144';
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1440,height:1000}});
const page=await browser.newPage();const errors=[],checks=[];
page.on('pageerror',e=>errors.push(e.message));
const check=(label,ok)=>{if(!ok)throw Error(label);checks.push(label);console.log('PASS',label)};
try{
 await page.goto(base+'/admin/login',{waitUntil:'networkidle0'});
 const handle=await page.evaluateHandle(()=>[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Sign in with the demo account')));
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),handle.asElement().click()]);
 await page.click('button[aria-label^="Website chat"]');
 await page.waitForSelector('[role="dialog"][aria-label="Website chats"]',{visible:true});
 check('Circled website chat opens its real drawer',true);
 await page.screenshot({path:'/tmp/woodex-run/shots/44/44-header-chat.png'});
 await page.click('[role="dialog"][aria-label="Website chats"] button[aria-label="Close"]');
 await page.waitForSelector('[role="dialog"][aria-label="Website chats"]',{hidden:true});
 check('Chat close returns to the dashboard',true);
 // The chat backdrop has a closing transition after its dialog becomes hidden. Wait for actual pointer access.
 await page.waitForFunction(()=>{const b=document.querySelector('button[aria-label^="Notifications"]');const r=b.getBoundingClientRect();return document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)?.closest('button')===b;});
 await page.click('button[aria-label^="Notifications"]');
 await page.waitForSelector('[role="menu"] a[href$="/notifications"]',{visible:true});
 check('Notification menu opens and links to the real inbox',true);
 await page.keyboard.press('Escape');
 await page.waitForSelector('[role="menu"] a[href$="/notifications"]',{hidden:true});
 check('Escape closes notification menu',true);
 await page.keyboard.down('Control');await page.keyboard.press('k');await page.keyboard.up('Control');
 await page.waitForSelector('[role="dialog"][aria-label="Search"]',{visible:true});
 check('Ctrl K opens and focuses global search',await page.$eval('input[aria-label="Search"]',e=>document.activeElement===e));
 await page.type('input[aria-label="Search"]','No such audit person');
 await page.waitForFunction(()=>document.querySelector('[role="dialog"][aria-label="Search"]')?.innerText.includes('Nothing found'));
 check('Search renders a real empty state',true);
 await page.keyboard.press('Escape');
 await page.waitForSelector('[role="dialog"][aria-label="Search"]',{hidden:true});
 check('Search escape restores the page',true);
 check('Preview badge remains an honest environment marker',await page.$eval('header',e=>e.innerText.includes('Demo data')));
 check('No header JavaScript errors',errors.length===0);
 fs.writeFileSync('/tmp/woodex-run/shots/44/44-header-results.json',JSON.stringify({checks,errors,fixture:'copied isolated demo; opening chat may mark its messages read; no sends'},null,2)+'\n');
}catch(error){await page.screenshot({path:'/tmp/woodex44/header-failure.png'});console.log('Header JS errors',errors);throw error}finally{await browser.close()}
