// Sandbox-only Furniture regression proof. Creates temporary demo documents, exports PDFs, then restores SQLite.
// Requires setup.sh, the demo owner and the preview on PORT (default 33144). Never run against production.
import {createRequire} from 'node:module';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1600,height:1000}});
const page=await browser.newPage();const errors=[];
page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});page.on('dialog',d=>d.accept());
const base=`http://127.0.0.1:${process.env.PORT || 33144}`;
if (process.env.PORT && process.env.PORT !== '33144') throw Error('Use isolated QA33144 only.');
const db='/tmp/woodex-furniture-qa/app/database/database.sqlite';
const backup=fs.readFileSync(db);
let proof;
const shots='/tmp/woodex-run/shots/';
const waitText=async(title,text)=>page.waitForFunction((t,v)=>document.querySelector(`iframe[title="${t}"]`)?.contentDocument?.body?.innerText.includes(v),{timeout:25000},title,text);
const fill=async(selector,value)=>{
 await page.waitForSelector(selector);
 const previous=await page.evaluate(()=>{const e=document.activeElement;const attr=[...(e?.attributes||[])].find(a=>a.name.startsWith('wire:model'));return attr && /^(lines|details)\./.test(attr.value)?{id:e.closest('[wire\\:id]')?.getAttribute('wire:id'),path:attr.value.split('.'),value:e.value}:null;});
 await page.$eval(selector,e=>e.focus());
 // Let the previous line's save-on-blur finish before acquiring the next input handle.
 if(previous?.id)await page.waitForFunction(p=>{let v=window.Livewire.find(p.id).__instance.canonical;for(const key of p.path)v=v?.[key];return String(v??'')===p.value;},{timeout:15000},previous);
 const el=await page.$(selector);await el.evaluate(e=>e.select());await el.type(value);
};
try{
execFileSync('/tmp/woodex-run/php',[fileURLToPath(new URL('./quotation-reference-proof.php',import.meta.url))],{cwd:'/tmp/woodex-furniture-qa/app'});
proof=JSON.parse(fs.readFileSync(shots+'42-reference-proof.json'));
await page.goto(base+'/admin/login',{waitUntil:'networkidle0'});
await page.type('input[name=email]','owner@woodex.test');await page.type('input[name=password]','Woodex-Preview-2026');
await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('button[type=submit]')]);
await page.goto(base+'/admin/quotes/'+proof.interior,{waitUntil:'networkidle0'});
await waitText('Quotation live preview','TOTAL ESTIMATED BUDGET');
let frame=page.frames().find(f=>f.name()===''&&f.url()==='about:srcdoc');
if(!frame)frame=page.frames().find(f=>f!==page.mainFrame());
if(!await frame.$('[data-interior-revision="2"]'))throw Error('Interior revision route');
await page.screenshot({path:shots+'42-interior-editor.png'});
await (await frame.$('body')).screenshot({path:shots+'42-interior-preview.png'});
await fill('textarea[id$="-description"]','Runtime approved Interior joinery');
await waitText('Quotation live preview','Runtime approved Interior joinery');
if(!await page.$eval('textarea[id$="-description"]',e=>document.activeElement===e))throw Error('Interior typing lost focus');
await fill('input[aria-label="Rate in rupees"]','61');
await waitText('Quotation live preview','1,490,000');
console.log('INTERIOR NEW RENDERER / LIVE DESCRIPTION / RATE / SUMMARY PASS');
await page.goto(base+'/admin/quotes/'+proof.furniture,{waitUntil:'networkidle0'});
await waitText('Quotation live preview','Imtiaz Ahmad');
frame=page.frames().find(f=>f!==page.mainFrame());
if(!await frame.$('[data-furniture-revision="4"]'))throw Error('Furniture revision route');
const cardCheck=await frame.evaluate(()=>({cards:document.querySelector('.project-cards')?.innerText,stripBeforeTerms:document.querySelector('.document-close').compareDocumentPosition(document.querySelector('.terms'))&Node.DOCUMENT_POSITION_FOLLOWING}));
if(!cardCheck.cards.toLowerCase().includes('project details')||!cardCheck.stripBeforeTerms)throw Error('Furniture cards/footer order');
await page.screenshot({path:shots+'42-furniture-editor.png'});
await (await frame.$('body')).screenshot({path:shots+'42-furniture-preview.png'});
const names=await page.$eval('[id="details.signatories_text"]',e=>e.value);
if(!names.includes('Imtiaz Ahmad')||!names.includes('Nabeel Afzal'))throw Error('Signatory input hydration');
await page.select('[id="details.show_terms"]','no');
await page.waitForFunction(()=>!document.querySelector('iframe[title="Quotation live preview"]').contentDocument.querySelector('.terms'));
await page.select('[id="details.show_signatories"]','no');
await page.waitForFunction(()=>!document.querySelector('iframe[title="Quotation live preview"]').contentDocument.querySelector('.signatures'));
if(!await page.$('[id="details.show_terms"]')||!await page.$('[id="details.show_signatories"]'))throw Error('Hidden controls disappeared');
await page.select('[id="details.show_terms"]','yes');await waitText('Quotation live preview','50% advance payment with work order.');
await page.select('[id="details.show_signatories"]','yes');await waitText('Quotation live preview','Nabeel Afzal');
await fill('[id="details.advance"]','37.5');await waitText('Quotation live preview','37.5% advance payment with work order.');
await page.setViewport({width:390,height:844});
await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1);
await page.screenshot({path:shots+'42-furniture-mobile.png',fullPage:true});
console.log('FURNITURE CARDS / FOOTER BEFORE TERMS / BOTTOM CONTROLS / SHOW-HIDE-RESTORE / ADVANCE / 390PX PASS');
console.log('JS_ERRORS',JSON.stringify(errors));if(errors.length)throw Error(errors.join('\n'));
fs.writeFileSync(shots+'42-browser-checks.json',JSON.stringify({interior_live_preview:true,interior_rate_and_summary:true,typing_focus:true,furniture_cards:true,footer_above_terms:true,default_signer_inputs:true,terms_and_signatures_hide_show:true,controls_remain_available:true,advance_term_matches_input:true,mobile_390_no_overflow:true,javascript_errors:errors},null,2));
if(process.env.REFERENCE_IFRAMES==='1'){
 execFileSync(process.execPath,[fileURLToPath(new URL('./furniture-iframe-check.mjs',import.meta.url))],{env:{...process.env,PORT:'33144',QA_FURNITURE_QUOTE_ID:String(proof.furniture),QA_INTERIOR_QUOTE_ID:String(proof.interior)},stdio:'inherit'});
}
}finally{await browser.close();fs.writeFileSync(db,backup);}
