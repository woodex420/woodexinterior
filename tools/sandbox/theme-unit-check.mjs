// No browser or npm dependency: exercise the actual native theme store, including blocked storage.
import assert from 'node:assert/strict';
import test from 'node:test';
import {createThemeStore, themeMode, resolvedDark} from '../../platform/resources/js/admin/theme.js';
function fixture({stored = null, systemDark = false, blocked = false} = {}) {
    const events = new EventTarget(), media = new EventTarget(), classes = new Set();
    media.matches = systemDark;
    const saved = new Map(stored ? [['wx.theme', stored]] : []);
    globalThis.window = events;
    window.matchMedia = () => media;
    window.localStorage = {getItem(k) {if(blocked) throw Error('blocked');return saved.get(k) ?? null;},setItem(k,v){if(blocked)throw Error('blocked');saved.set(k,v);}};
    globalThis.document = {documentElement:{dataset:{},style:{},classList:{contains:k=>classes.has(k),toggle(k,on){on?classes.add(k):classes.delete(k);}}},querySelector:()=>({setAttribute(){}})};
    const store = createThemeStore(); store.init();
    return {store, media, saved, storage(value,key='wx.theme'){const e=new Event('storage');Object.assign(e,{key,newValue:value});events.dispatchEvent(e);}};
}
test('normalizes mode and resolves explicit preferences',()=>{
    assert.equal(themeMode('unknown'),'system');assert.equal(themeMode('dark'),'dark');
    assert.equal(resolvedDark('light',true),false);assert.equal(resolvedDark('dark',false),true);
});
test('retains legacy wx.theme values',()=>{
    assert.equal(fixture({stored:'light',systemDark:true}).store.dark,false);
    assert.equal(fixture({stored:'dark'}).store.dark,true);
});
test('System follows device changes',()=>{
    const {store,media}=fixture();media.matches=true;media.dispatchEvent(new Event('change'));
    assert.equal(store.dark,true);assert.equal(document.documentElement.dataset.themeMode,'system');
});
test('manual mode ignores device changes and persists',()=>{
    const {store,media,saved}=fixture();store.setMode('light');media.matches=true;media.dispatchEvent(new Event('change'));
    assert.equal(store.dark,false);assert.equal(saved.get('wx.theme'),'light');
});
test('blocked localStorage remains usable in memory',()=>{
    const {store}=fixture({blocked:true,systemDark:true});assert.equal(store.dark,true);
    assert.doesNotThrow(()=>store.setMode('light'));assert.equal(store.dark,false);
});
test('cross-tab mode updates and storage clearing resolve consistently',()=>{
    const f=fixture({systemDark:true});f.storage('light');assert.equal(f.store.dark,false);
    f.storage(null,null);assert.equal(f.store.mode,'system');assert.equal(f.store.dark,true);
});
test('invalid stored preferences use System and toggle still works',()=>{
    const {store}=fixture({stored:'corrupt',systemDark:true});assert.equal(store.mode,'system');
    store.toggle();assert.equal(store.mode,'light');assert.equal(document.documentElement.style.colorScheme,'light');
});
