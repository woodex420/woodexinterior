// Read-only browser audit: navigation, Control Centre filters, shell and mobile. No production sends/writes.
import fs from 'node:fs';
import { createRequire } from 'node:module';
process.env.AWS_LAMBDA_JS_RUNTIME ??= 'nodejs22.x';
if (!fs.existsSync('/tmp/al2023/lib')) fs.rmSync('/tmp/chromium', {force:true});
const require=createRequire('/tmp/browser/');
const chromium=(await import(require.resolve('@sparticuz/chromium'))).default;
const puppeteer=(await import(require.resolve('puppeteer-core'))).default;
const base=process.env.QA_BASE || 'http://127.0.0.1:33143';
if (!/^http:\/\/(127\.0\.0\.1|localhost):33143$/.test(base)) throw Error('Local read-only audit origin required');
const out='/tmp/woodex-run/shots/44';fs.mkdirSync(out,{recursive:true});
const browser=await puppeteer.launch({executablePath:await chromium.executablePath(),args:[...chromium.args,'--no-sandbox'],headless:true,defaultViewport:{width:1440,height:1000}});
const page=await browser.newPage();
const errors=[],checks=[],routes=[];
page.on('pageerror',e=>errors.push({path:new URL(page.url()).pathname,error:e.message}));
const check=(name,ok)=>{if(!ok)throw Error(name);checks.push(name);console.log('PASS',name)};
const go=async path=>{const r=await page.goto(base+path,{waitUntil:'networkidle0',timeout:60000});return r;};
try {
 await go('/admin/login');
 const login=await page.evaluateHandle(()=>[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Sign in with the demo account')));
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),login.asElement().click()]);
 check('Authenticated native Control Centre loads',!page.url().includes('login')&&await page.$('[aria-label="Performance period"]'));
 const links=await page.$$eval('aside[aria-label="Main navigation"] a[href]',els=>[...new Set(els.map(a=>new URL(a.href).pathname+new URL(a.href).search))].filter(u=>u.startsWith('/admin')));
 await page.screenshot({path:out+'/44-dashboard-desktop.png',fullPage:true});
 await page.select('[aria-label="Performance period"]','today');
 await page.waitForFunction(()=>document.querySelector('section[aria-label="Period performance"] h2')?.textContent==='Today');
 await page.select('[aria-label="Performance business line"]','furniture');
 await page.waitForFunction(()=>document.querySelector('section[aria-label="Period performance"]')?.innerText.includes('Allocated collections'));
 check('Date and business-line filters rerender real period metrics',await page.$eval('section[aria-label="Period performance"]',e=>e.innerText.includes('excludes unallocated cash')));
 const report=await page.$eval('section[aria-label="Period performance"] a',a=>new URL(a.href).pathname+new URL(a.href).search);
 await go(report);
 check('Dashboard drill-down preserves report period and business line',await page.$eval('[aria-label="Revenue business line"]',e=>e.value==='furniture')&&new URL(page.url()).searchParams.get('preset')==='today');
 await page.select('[aria-label="Revenue business line"]','');
 await page.waitForFunction(()=>document.querySelector('main')?.innerText.includes('Collections include verified receipts'));
 check('Report filter changes receipt attribution wording truthfully',true);
 await go('/admin?tab=operations');
 check('Operations tab shows native modules and review queues',await page.$eval('main',e=>e.innerText.includes('Operations control')&&e.innerText.includes('Payment verification')&&e.innerText.includes('Marketing')));
 await page.screenshot({path:out+'/44-operations-desktop.png',fullPage:true});
 await go('/admin/approvals');
 await page.type('[aria-label="Search approvals"]','No such synthetic audit client');
 await page.waitForFunction(()=>document.querySelector('main')?.innerText.includes('Nothing waiting'));
 check('Approval search has a genuine empty state',true);
 await go('/admin/reports?tab=sources');
 check('Reports expose permitted original ledger sources',await page.$eval('main',e=>e.innerText.includes('Trial balance')&&e.innerText.includes('Profit & loss')));
 await go('/admin?tab=system');
 check('System tab renders native health checks',await page.$eval('main',e=>e.innerText.includes('System health')&&e.innerText.includes('Scheduled tasks')));
 await go('/admin');
 await page.select('[aria-label="Color theme"]','dark');
 await page.waitForFunction(()=>document.documentElement.classList.contains('dark'));
 await page.reload({waitUntil:'networkidle0'});
 check('Theme choice survives reload',await page.$eval('html',e=>e.classList.contains('dark')));
 await page.select('[aria-label="Color theme"]','light');
 await page.click('[aria-label="Toggle sidebar"]');
 await page.waitForFunction(()=>localStorage.getItem('wx.sidebar')==='collapsed');
 check('Desktop sidebar toggle persists',true);
 await page.click('[aria-label="Toggle sidebar"]');
 await page.setViewport({width:390,height:844});
 await go('/admin');
 check('Control Centre fits390px viewport',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
 await page.screenshot({path:out+'/44-dashboard-mobile.png',fullPage:true});
 await page.click('[aria-label="Toggle sidebar"]');
 await page.waitForFunction(()=>document.querySelector('aside')?.getBoundingClientRect().left>=-1,{timeout:5000});
 check('Mobile navigation opens',true);
 await page.keyboard.press('Escape');
 await page.waitForFunction(()=>document.querySelector('aside')?.getBoundingClientRect().right<=1,{timeout:5000});
 check('Escape closes mobile navigation',true);
 await page.setViewport({width:1440,height:1000});
 // Every visible navigation destination gets an independent load. This is landing coverage, not every action.
 for(const path of links){
  try{const response=await go(path);const row={path,status:response.status(),final_path:new URL(page.url()).pathname,coverage:'landing_only',js_errors:errors.filter(e=>e.path===new URL(page.url()).pathname)};routes.push(row);console.log('ROUTE',row.status,path);}
  catch(error){routes.push({path,status:'navigation_failed',error:error.message.split('\n')[0],coverage:'landing_only'});}
 }
 const failed=routes.filter(r=>r.status!==200||r.final_path==='/admin/login');
 fs.writeFileSync(out+'/44-browser-results.json',JSON.stringify({checks,routes,errors,coverage_warning:'All navigation landings loaded; this is not exhaustive CRUD, provider delivery or every-action proof.'},null,2)+'\n');
 check('All discovered navigation pages load without auth bounce',failed.length===0);
 check('No browser JavaScript errors',errors.length===0);
 console.log('CONTROL CENTRE BROWSER AUDIT PASS',routes.length,'navigation destinations');
} finally {await browser.close()}
