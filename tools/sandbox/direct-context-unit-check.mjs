import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const code=fs.readFileSync(new URL('./native-direct-context.js',import.meta.url),'utf8');
const {demoFetch}=await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'));
const origin='https://preview.example';const context='ab'.repeat(16);
function capture(){let call;return {fetch:(input,init)=>{call={input,init};return Promise.resolve('ok');},get call(){return call;}};}
test('direct context preserves normal CSRF and fetch options on same-origin requests',async()=>{
 const state=capture(),signal=new AbortController().signal;
 await demoFetch('/livewire/update',{method:'POST',headers:{'X-CSRF-TOKEN':'fixture-token'},body:'fixture-body',signal},{fetch:state.fetch,origin,context});
 assert.equal(state.call.init.headers.get('X-Woodex-Demo-Context'),context);
 assert.equal(state.call.init.headers.get('X-CSRF-TOKEN'),'fixture-token');
 assert.equal(state.call.init.body,'fixture-body');assert.equal(state.call.init.signal,signal);
});
test('context is never added to a foreign or protocol-relative foreign request',async()=>{
 for(const url of ['https://external.invalid/api','//external.invalid/api']){const state=capture();await demoFetch(url,undefined,{fetch:state.fetch,origin,context});assert.equal(state.call.init,undefined);}
});
test('missing or malformed context leaves native fetch untouched',async()=>{
 for(const value of [null,undefined,'','x'.repeat(32),'ab'.repeat(17),'../../session']){const state=capture(),init={method:'GET'};await demoFetch('/admin',init,{fetch:state.fetch,origin,context:value});assert.equal(state.call.init,init);}
});
test('Request objects retain their original headers',async()=>{
 const state=capture(),request=new Request(origin+'/livewire/update',{headers:{'X-CSRF-TOKEN':'fixture-token'}});
 await demoFetch(request,{headers:{'X-Livewire':'1'}},{fetch:state.fetch,origin,context});
 assert.equal(state.call.input,request);assert.equal(state.call.init.headers.get('X-CSRF-TOKEN'),'fixture-token');assert.equal(state.call.init.headers.get('X-Livewire'),'1');
});
