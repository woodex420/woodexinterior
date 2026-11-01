// Isolated, mutating Estimations workflow proof. Never point at the owner's active preview.
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import fs from 'node:fs';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const port=Number(process.env.PORT || 33144);
if(port!==33144)throw Error('This mutating proof is restricted to isolated QA port 33144.');
const root='/tmp/woodex-w-qa/app';
const db=root+'/database/database.sqlite';
const rateFile=root+'/public/assets/js/estimator-rates.js';
const original=fs.readFileSync(db), ratesOriginal=fs.readFileSync(rateFile);
const shots='/tmp/w-implementation/';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1600,height:1000}});
const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
const base=`http://127.0.0.1:${port}`;
const fill=async(selector,value)=>{await page.waitForSelector(selector);await page.$eval(selector,e=>e.focus());const e=await page.$(selector);await e.evaluate(e=>e.select());await e.type(value);};
const waitText=text=>page.waitForFunction(t=>document.body.innerText.includes(t),{timeout:25000},text);
const phone=async(label)=>{await page.setViewport({width:390,height:844});await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:shots+label+'-phone.png',fullPage:true});await page.setViewport({width:1600,height:1000});};
const pdf=async(id,name)=>{const bytes=await page.evaluate(async id=>{const r=await fetch('/admin/quotes/'+id+'/pdf');if(!r.ok)throw Error('PDF HTTP '+r.status);return Array.from(new Uint8Array(await r.arrayBuffer()));},id);const b=Buffer.from(bytes);if(!b.subarray(0,5).equals(Buffer.from('%PDF-')))throw Error('Not PDF');fs.writeFileSync(shots+name+'.pdf',b);};
try {
 const fixture=JSON.parse(execFileSync('/tmp/woodex-run/php',[fileURLToPath(new URL('./estimations-proof.php',import.meta.url))],{cwd:root,encoding:'utf8'}).trim());
 await page.goto(base+'/admin/login',{waitUntil:'networkidle0'});await page.type('input[name=email]','owner@woodex.test');await page.type('input[name=password]','Woodex-Preview-2026');await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('button[type=submit]')]);
 await page.goto(base+'/admin/estimations',{waitUntil:'networkidle0'});await waitText('W QA saved snapshot');await page.screenshot({path:shots+'hub-desktop.png',fullPage:true});await phone('hub');
 await page.goto(base+'/admin/estimations?tab=rates',{waitUntil:'networkidle0'});await waitText('Public estimator service rates');await fill('#bookName','W QA before change');await page.click('[wire\\:click="namedBook(\'save\')"]');await waitText('“W QA before change” saved.');
 await fill('#rate-0-standard','275');await page.click('[wire\\:click="publishRates"]');await waitText('Published. The public estimator');await page.screenshot({path:shots+'rates-desktop.png',fullPage:true});await phone('rates');console.log('NATIVE RATES NAMED BACKUP AND REAL PUBLISH PASS');
 const parity=JSON.parse(execFileSync('/tmp/woodex-run/php',[fileURLToPath(new URL('./estimations-proof.php',import.meta.url)),'parity'],{cwd:root,encoding:'utf8'}).trim());
 await page.goto(base+'/estimator/',{waitUntil:'networkidle0'});await page.waitForSelector('#est-services button');
 for(const expected of parity){
  const result=await page.evaluate(c=>{
   const si=window.WX_RATES.services.findIndex(s=>s.key===c.service);document.querySelectorAll('#est-services button')[si].click();
   const fi=window.WX_RATES.finishes.findIndex(f=>f.key===c.finish);document.querySelectorAll('#est-finishes button')[fi].click();
   const q=document.querySelector('#est-qty');q.value=c.quantity;q.dispatchEvent(new Event('input',{bubbles:true}));
   const money=text=>Math.round(Number(text.replace(/[^0-9.]/g,''))*(text.includes('crore')?10000000:text.includes('lac')?100000:1)*100);
   return {low:money(document.querySelector('#est-low').textContent),high:money(document.querySelector('#est-high').textContent),selected:money(document.querySelector('#est-sel strong').textContent.split('—').pop())};
  },expected);
  for(const k of ['low','high','selected'])if(result[k]!==expected[k])throw Error('PUBLIC V1 PARITY '+JSON.stringify({expected,result}));
 }
 console.log('AUTHENTIC PUBLIC V1 JS VS NATIVE PHP: '+parity.length+' SERVICE/FINISH CASES PASS');
 await page.goto(base+'/admin/website/estimator',{waitUntil:'networkidle0'});if(!page.url().includes('tab=rates'))throw Error('Legacy route not redirected');
 await page.goto(base+'/admin/estimations/'+fixture.estimate_id,{waitUntil:'networkidle0'});await waitText('Saved snapshot');await waitText('Rs 2,500');await waitText('Rs 2,750');
 await page.click('[wire\\:click="addLine"]');await page.waitForSelector('select[wire\\:model\\.live="catalogLines.0.catalog_item_id"]');
 const option=await page.$eval('select[wire\\:model\\.live="catalogLines.0.catalog_item_id"]',e=>e.options[1].textContent.trim());if(option.startsWith('—'))throw Error('Blank catalogue label');
 const item=await page.$eval('select[wire\\:model\\.live="catalogLines.0.catalog_item_id"]',e=>e.options[1].value);await page.select('select[wire\\:model\\.live="catalogLines.0.catalog_item_id"]',item);await waitText('Rs 72,750');await phone('builder-with-addon');console.log('ADDON LIVE PRICING AND PHONE WIDTH PASS');
 await page.click('[wire\\:click="removeLine(0)"]');await page.waitForFunction(()=>!document.querySelector('select[wire\\:model\\.live="catalogLines.0.catalog_item_id"]'));
 await page.click('[wire\\:click="convert"]');await page.waitForFunction(()=>/\/admin\/quotes\/\d+$/.test(location.pathname),{timeout:25000});const interior=Number(new URL(page.url()).pathname.split('/').pop());await page.waitForSelector('iframe[title="Quotation live preview"]');await pdf(interior,'w-interior-quotation');console.log('INTERIOR SAVED SNAPSHOT CONVERSION AFTER RATE CHANGE PASS');
 await page.goto(base+'/admin/estimations?tab=enquiries',{waitUntil:'networkidle0'});await waitText('W QA Furniture Client');await waitText('Confirm quantity or unknown/retired service');await page.screenshot({path:shots+'enquiries-desktop.png',fullPage:true});await phone('enquiries');
 await page.goto(base+'/admin/estimations/'+fixture.furniture_id,{waitUntil:'networkidle0'});await page.click('[wire\\:click="convert"]');await page.waitForFunction(()=>/\/admin\/quotes\/\d+$/.test(location.pathname),{timeout:25000});const furniture=Number(new URL(page.url()).pathname.split('/').pop());await page.waitForFunction(()=>document.querySelector('iframe[title="Quotation live preview"]')?.contentDocument?.querySelector('[data-furniture-revision="2"]'));
 await pdf(furniture,'w-furniture-quotation');await page.screenshot({path:shots+'furniture-converted.png'});console.log('FURNITURE ENQUIRY CONVERSION AND A4 PREVIEW PASS');
 await page.goto(base+'/admin/estimations/'+fixture.estimate_id,{waitUntil:'networkidle0'});await waitText('Converted');await waitText('Rs 2,500');if(await page.$('[wire\\:click="save"]'))throw Error('Converted estimate is editable');console.log('CONVERTED HISTORICAL SNAPSHOT READONLY PASS');
 console.log('JS_ERRORS',JSON.stringify(errors));if(errors.length)throw Error(errors.join('\n'));
} finally {await browser.close();fs.writeFileSync(db,original);fs.writeFileSync(rateFile,ratesOriginal);}
