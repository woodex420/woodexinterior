// Post-release-guard native proof. Run after theme-chat-check.mjs against the same isolated QA copy.
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
const fixture=()=>JSON.parse(execFileSync('/tmp/woodex-run/php',['/home/user/woodexinterior/tools/sandbox/theme-chat-fixture.php'],{encoding:'utf8'}));
const qa=fixture(),base='http://127.0.0.1:33144',out='/tmp/woodex48/browser';
if(!qa.browser_id)throw Error('Run the guarded full browser walkthrough first');
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1440,height:1000}});
const page=await browser.newPage(),checks=[],errors=[];
page.on('pageerror',e=>errors.push(e.message));
const check=(name,ok)=>{if(!ok)throw Error(name);checks.push(name);console.log('PASS '+name)};
const settle=()=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
const action=async selector=>{
 const response=page.waitForResponse(r=>{try{const p=JSON.parse(r.request().postData()||'{}');return r.request().method()==='POST'&&p.components?.some(c=>JSON.parse(c.snapshot).memo.name==='admin.conversations.live-chat-bell'&&c.calls?.some(a=>['openThread','__dispatch','closeDrawer'].includes(a.method)));}catch{return false;}});
 await page.click(selector);const result=await(await response).json();await settle();return result;
};
try {
 await page.goto(base+'/admin/login',{waitUntil:'domcontentloaded'});
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.click('#wx-preview-login button')]);
 await page.goto(base+'/admin/leads?tab=web_chat',{waitUntil:'domcontentloaded'});
 await page.waitForFunction(()=>!!window.Alpine?.store('theme'));await page.select('[aria-label="Color theme"]','dark');
 await action('[wire\\:key="web-lead-'+qa.browser_id+'"] button');await page.waitForSelector('aside[aria-label="Website chats"] [aria-label="Reply"]',{visible:true});await settle();
 await page.type('aside[aria-label="Website chats"] [aria-label="Reply"]','Retained same-thread draft');
 const same=await action('nav[aria-label="Website conversations"] button[aria-pressed="true"]');check('Same-thread server response retains the draft',same.components.some(c=>JSON.parse(c.snapshot).data.draft==='Retained same-thread draft'));
 check('Clicking the selected thread keeps the actual typed draft',await page.$eval('aside[aria-label="Website chats"] [aria-label="Reply"]',e=>e.value==='Retained same-thread draft'));
 await action('aside[aria-label="Website chats"] [aria-label="Close"]');await settle();
 await action('[wire\\:key="web-lead-'+qa.browser_id+'"] button');await settle();
 check('Reopening the same thread from the tracking row keeps the draft',await page.$eval('aside[aria-label="Website chats"] [aria-label="Reply"]',e=>e.value==='Retained same-thread draft'));
 await page.screenshot({path:out+'/48-same-thread-draft-guard.png'});
 check('Draft-only interactions send no customer messages',fixture().browser_message_count===qa.browser_message_count);
 await action('aside[aria-label="Website chats"] [aria-label="Close"]');await settle();
 check('No JavaScript page errors after the final guard',errors.length===0);
 fs.writeFileSync(out+'/48-draft-guard-results.json',JSON.stringify({checks,errors,scope:'isolated QA only; no sends'},null,2)+'\n');
} finally {await browser.close();}
