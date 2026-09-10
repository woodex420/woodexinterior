'use strict';
// Set formEndpoint to your hosted form handler to enable real submissions.
// Example: https://formspree.io/f/YOUR_FORM_ID (create your own form first).
const WOODEX = { formEndpoint: '' };
window.woodexPageCleanup?.();
const pageEvents = new AbortController();
window.woodexPageCleanup = () => pageEvents.abort();
const menu = document.querySelector('.main-nav');
const toggle = document.querySelector('.menu-toggle');
function closeMenu(){ menu?.classList.remove('open'); toggle?.setAttribute('aria-expanded','false'); toggle?.setAttribute('aria-label','Open menu'); }
toggle?.addEventListener('click',()=>{const open=menu.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close menu':'Open menu');});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu?.classList.contains('open')){closeMenu();toggle.focus();}}, {signal:pageEvents.signal});
document.addEventListener('click',e=>{if(!e.target.closest('.site-header'))closeMenu();}, {signal:pageEvents.signal});
window.matchMedia('(min-width:901px)').addEventListener('change',e=>{if(e.matches)closeMenu();}, {signal:pageEvents.signal});
// A manual carousel avoids motion distractions and gives the visitor control.
const hero = document.querySelector('.hero');
if(hero){
 const slides=[
  {tag:'Inspired interiors',title:'Spaces shaped by<br>purpose and personality',copy:'An interior is more than a beautiful room. It’s the way you feel at home. We bring natural materials, thoughtful details, and your story together to create spaces for living well.',word:'DESIGN'},
  {tag:'Timeless by nature',title:'Thoughtful details.<br>Lasting impressions.',copy:'Warm timber, honest textures, and a considered sense of balance. Discover interiors that feel personal, work beautifully, and only become better with time.',word:'CREATE'},
  {tag:'Made for everyday',title:'Room for life.<br>Space to be yourself.',copy:'From a quiet corner to a complete transformation, we shape every space around the people who use it. Beautifully practical. Distinctively yours.',word:'INSPIRE'}
 ];
 let index=0;
 function showSlide(i){index=(i+slides.length)%slides.length;const s=slides[index];hero.querySelectorAll('.hero-photo').forEach((el,j)=>el.classList.toggle('active',index===j));hero.querySelector('.hero-content .eyebrow').textContent=s.tag;hero.querySelector('h1').innerHTML=s.title;hero.querySelector('.hero-bottom p').textContent=s.copy;hero.querySelector('.hero-word').textContent=s.word;hero.querySelector('.slide-number').innerHTML=`0${index+1} <span>/ 03</span>`;hero.querySelector('.hero-content').classList.remove('slide-feedback');void hero.offsetWidth;hero.querySelector('.hero-content').classList.add('slide-feedback');hero.querySelector('.slide-announcement').textContent=`Slide ${index+1} of 3: ${s.title.replace('<br>',' ')}`;}
 hero.querySelector('[data-prev]').addEventListener('click',()=>showSlide(index-1));hero.querySelector('[data-next]').addEventListener('click',()=>showSlide(index+1));
}
// Portfolio filters retain real links to all project pages.
const filters=document.querySelectorAll('.filter-btn');
filters.forEach(button=>button.addEventListener('click',()=>{
 filters.forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
 const category=button.dataset.filter;let count=0;
 document.querySelectorAll('.portfolio-main .project-card').forEach(card=>{const match=category==='all'||card.dataset.category===category;card.hidden=!match;if(match)count++;});
 const result=document.querySelector('.filter-count');if(result)result.textContent=`${String(count).padStart(2,'0')} ${count===1?'project':'projects'}`;
}));
// Honest static-form fallback: download a local brief, never claim it was sent.
const form=document.querySelector('.enquiry-form');
if(form){
 const status=form.querySelector('.form-message'),submit=form.querySelector('button[type=submit]');let objectURL;
 submit.disabled=false;
 const requestedService=new URL(document.body.dataset.route || location.href, 'https://woodex.invalid/').searchParams.get('service');
 if(requestedService && [...form.querySelector('#service').options].some(o=>o.value===requestedService))form.querySelector('#service').value=requestedService;
 if(WOODEX.formEndpoint){submit.querySelector('.button-label').textContent='Send enquiry';form.querySelector('.form-note').textContent='Tell us a little about your space. Your enquiry will be sent securely to our studio.';form.querySelector('.consent span').textContent='I consent to Woodex Interior using these details to respond to my enquiry.';}
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(!form.reportValidity())return;status.hidden=false;status.textContent='Preparing your enquiry…';submit.disabled=true;
  const data=new FormData(form);
  if(WOODEX.formEndpoint){try{const response=await fetch(WOODEX.formEndpoint,{method:'POST',body:data,headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Unable to submit');status.textContent='Thank you. Your enquiry has been sent to Woodex Interior.';form.reset();}catch(error){status.textContent='Your enquiry could not be sent. Please try again. Your details remain in the form.';}}
  else{
   const brief='WOODEX INTERIOR — PROJECT ENQUIRY\nNot submitted. Share this brief with your chosen studio.\n\n'+[...data.entries()].filter(([key])=>key!=='consent').map(([key,value])=>`${key}: ${value}`).join('\n\n');
   if(objectURL)URL.revokeObjectURL(objectURL);objectURL=URL.createObjectURL(new Blob([brief],{type:'text/plain;charset=utf-8'}));
   status.textContent='Your brief is ready. Nothing has been sent or stored by this website. ';
   const a=document.createElement('a');a.href=objectURL;a.download='woodex-project-enquiry.txt';a.textContent='Download your enquiry brief';status.append(a);
  }
  submit.disabled=false;
 });
}
// Open only one service disclosure at a time (native details remain keyboard accessible).
document.querySelectorAll('.service-accordion details').forEach(detail=>detail.addEventListener('toggle',()=>{if(detail.open)detail.parentElement.querySelectorAll('details').forEach(other=>{if(other!==detail)other.open=false;});}));
