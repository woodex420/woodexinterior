// Mutating browser QA: ONLY the copied local database guarded by payment-ledger-fixture.php.
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium',{force:true});
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
const root='/home/user/woodexinterior';
const fixture=(mode='--inspect')=>JSON.parse(execFileSync('/tmp/woodex-run/php',[root+'/tools/sandbox/payment-ledger-fixture.php',mode],{cwd:root,encoding:'utf8'}));
const initial=fixture();
if(initial.payment_count!==0)throw Error('Fresh separate QA fixture required');
const out='/tmp/woodex46/browser';fs.mkdirSync(out,{recursive:true});
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1440,height:1000}});
const page=await browser.newPage();const checks=[],errors=[];
page.on('pageerror',e=>errors.push(e.message));
const check=(name,ok)=>{if(!ok)throw Error(name);checks.push(name);console.log('PASS',name)};
const clickText=async text=>{const h=await page.evaluateHandle(t=>[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===t),text);if(!h.asElement())throw Error('Missing button '+text);await h.asElement().click()};
const openForm=async()=>{
 await clickText('Record payment');await page.waitForSelector('#contact-search');
 await page.type('#contact-search','Isolated Payment QA');
 await page.waitForFunction(id=>[...document.querySelectorAll('button')].some(b=>b.getAttribute('wire:click')===`pickContact(${id})`),{},initial.contact_id);
 const h=await page.evaluateHandle(id=>[...document.querySelectorAll('button')].find(b=>b.getAttribute('wire:click')===`pickContact(${id})`),initial.contact_id);await h.asElement().click();
 await page.waitForFunction(id=>[...document.querySelectorAll('#invoice option')].some(o=>o.value===String(id)),{},initial.invoice_id);
 await page.select('#invoice',String(initial.invoice_id));await page.select('#method','cash');
 await page.type('#amount','50.00');
};
try{
 await page.goto('http://127.0.0.1:33144/admin/login',{waitUntil:'networkidle0'});
 const h=await page.evaluateHandle(()=>[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Sign in with the demo account')));
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),h.asElement().click()]);
 await page.goto('http://127.0.0.1:33144/admin/payments',{waitUntil:'networkidle0'});
 await openForm();await clickText('Record and issue receipt');
 await page.waitForSelector('#amount',{hidden:true});
 const after=fixture();
 check('Real payment form posts one receipt and one journal',after.payment_count===1&&after.payment_journals===1);
 check('Invoice balance and GL receivable both equal Rs50',after.balance_minor===5000&&after.receivable_minor===5000);
 await page.screenshot({path:out+'/46-payment-recorded.png',fullPage:true});
 fixture('--close');
 await openForm();await clickText('Record and issue receipt');
 await page.waitForFunction(()=>document.querySelector('#amount')?.closest('form').innerText.includes('is closed'));
 check('Closed-period validation remains visible with amount retained',await page.$eval('#amount',e=>e.value==='50.00'));
 const closed=fixture();
 check('Rejected form creates no second payment journal or balance change',closed.payment_count===1&&closed.payment_journals===1&&closed.balance_minor===5000&&closed.receivable_minor===5000);
 await page.screenshot({path:out+'/46-payment-closed-period.png',fullPage:true});
 check('No payment form JavaScript errors',errors.length===0);
 fs.writeFileSync(out+'/46-browser-results.json',JSON.stringify({checks,errors,initial,after,closed,scope:'copied local QA DB; mail array; no production or provider writes'},null,2)+'\n');
} catch(e){await page.screenshot({path:out+'/46-failure.png',fullPage:true});throw e}finally{await browser.close()}
