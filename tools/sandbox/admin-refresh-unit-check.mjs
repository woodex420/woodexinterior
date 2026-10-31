// Source/DOM contracts for the admin-only refresh. No server, DB, login or hosting calls.
import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';
import vm from 'node:vm';
import { themeColor, createThemeStore } from '../../platform/resources/js/admin/theme.js';
const read = p => fs.readFileSync(new URL('../../'+p, import.meta.url), 'utf8');
const layout = read('platform/resources/views/layouts/admin.blade.php');
const script = layout.match(/<script>([\s\S]*?)<\/script>/)[1];
const css = read('platform/resources/css/admin.css');
const light = Object.fromEntries([...css.match(/:root \{\s*--wx-page:[\s\S]*?\}/)[0].matchAll(/(--[\w-]+):\s*(#[a-f0-9]{6});/g)].map(m=>[m[1],m[2]]));
const dark = Object.fromEntries([...css.match(/:root\.dark \{[\s\S]*?\}/)[0].matchAll(/(--[\w-]+):\s*(#[a-f0-9]{6});/g)].map(m=>[m[1],m[2]]));
function luminance(hex) { return hex.slice(1).match(/../g).map(x=>parseInt(x,16)/255).map(x=>x<=0.04045?x/12.92:((x+0.055)/1.055)**2.4).reduce((s,x,i)=>s+x*[0.2126,0.7152,0.0722][i],0); }
function contrast(a,b) { const x=luminance(a),y=luminance(b);return (Math.max(x,y)+0.05)/(Math.min(x,y)+0.05); }
for (const [name,palette] of Object.entries({light,dark})) {
    test(name+' neutral semantic backgrounds and primary',()=>{
        for (const key of ['--wx-page','--wx-surface','--wx-elevated','--wx-primary','--wx-primary-hover','--wx-chat-canvas']) {
            const channels=palette[key].slice(1).match(/../g); assert.equal(channels[0],channels[1]);assert.equal(channels[1],channels[2]);
        }
    });
    for (const bg of ['--wx-page','--wx-surface','--wx-elevated']) {
        for (const fg of ['--wx-text','--wx-muted']) test(`${name} ${fg}/${bg} >=4.5`,()=>assert(contrast(palette[fg],palette[bg])>=4.5));
    }
    test(name+' primary text contrast >=4.5',()=>assert(contrast(palette['--wx-on-primary'],palette['--wx-primary'])>=4.5));
    test(name+' control/focus visibility >=3',()=>{
        for(const fg of ['--wx-control-border','--wx-focus'])assert(contrast(palette[fg],palette['--wx-surface'])>=3);
    });
}
for (const mode of ['light','dark','system','invalid',null]) for (const systemDark of [true,false]) for (const blocked of [false,true]) {
    test(`actual prepaint ${mode}/${systemDark}/blocked=${blocked}`,()=>{
        const classes=new Set(),attrs=new Map(),root={dataset:{},style:{},classList:{toggle(k,on){on?classes.add(k):classes.delete(k);}}};
        vm.runInNewContext(script,{document:{documentElement:root,querySelector:()=>({setAttribute:(k,v)=>attrs.set(k,v)})},localStorage:{getItem(k){if(blocked)throw Error('blocked');return k==='wx.theme'?mode:'collapsed';}},matchMedia:()=>({matches:systemDark})});
        const expectedMode=blocked||!['light','dark','system'].includes(mode)?'system':mode;
        const expectedDark=expectedMode==='dark'||(expectedMode==='system'&&systemDark);
        assert.equal(classes.has('dark'),expectedDark);assert.equal(root.dataset.themeMode,expectedMode);
        assert.equal(root.style.colorScheme,expectedDark?'dark':'light');assert.equal(attrs.get('content'),themeColor(expectedDark));
        assert.equal(themeColor(expectedDark),(expectedDark?dark:light)['--wx-page']);
    });
}
test('no old navy surface values remain in semantic palette',()=>{
    for(const p of [light,dark])for(const val of Object.values(p))assert(!['#0d1626','#152238','#82b3f4','#233d63'].includes(val));
});
test('sidebar shares semantic surface and Settings tabs wrap',()=>{
    assert.match(css,/\.wx-admin-header,\.wx-admin-sidebar,\.wx-surface/);
    assert.match(read('platform/resources/views/components/admin/sidebar.blade.php'),/class="wx-admin-sidebar /);
    assert.match(read('platform/resources/views/livewire/admin/system/settings-page.blade.php'),/wx-settings-tabs[^\n]*flex-wrap/);
});
test('layout retains real Livewire and Vite wiring',()=>{
    assert.match(layout,/@vite\(\['resources\/css\/admin.css', 'resources\/js\/admin.js'\]\)/);
    assert.match(layout,/@livewireScripts/);assert.match(layout,/@livewireStyles/);
});
test('actual theme store updates browser color on explicit and system preference changes',()=>{
    const events=new EventTarget(),media=new EventTarget(),classes=new Set(),attrs=new Map();media.matches=false;
    globalThis.window=events;window.matchMedia=()=>media;window.localStorage={getItem:()=>null,setItem(){}};
    globalThis.document={documentElement:{dataset:{},style:{},classList:{contains:k=>classes.has(k),toggle(k,on){on?classes.add(k):classes.delete(k);}}},querySelector:()=>({setAttribute:(k,v)=>attrs.set(k,v)})};
    const store=createThemeStore();store.init();assert.equal(attrs.get('content'),'#f6f6f6');
    store.setMode('dark');assert.equal(attrs.get('content'),'#171717');
    store.setMode('system');media.matches=true;media.dispatchEvent(new Event('change'));assert.equal(attrs.get('content'),'#171717');
    media.matches=false;media.dispatchEvent(new Event('change'));assert.equal(attrs.get('content'),'#f6f6f6');
});
