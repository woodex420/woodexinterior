// Pure helper and branding source contracts; no backend, provider or production requests.
import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';
import { signInFailure, sameOriginDestination } from '../../platform/resources/js/auth.js';
const read = p => fs.readFileSync(new URL('../../' + p, import.meta.url), 'utf8');
test('expired sessions explain cookies and a new-tab recovery', () => {
    assert.match(signInFailure(419), /cookies were blocked/);
    assert.match(signInFailure(419), /new tab/);
});
test('throttle details are bounded integers, never reflected HTML', () => {
    assert.match(signInFailure(429, '30'), /Wait 30 seconds/);
    for (const value of ['<script>', '-1', '0', '3601', 'NaN']) {
        assert.equal(signInFailure(429, value), 'Too many sign-in attempts. Wait a minute and try again.');
    }
});
test('server errors give a retry without exposing response details', () => {
    assert.match(signInFailure(503), /Please try again/);
});
test('redirects retain the same origin and allow intended and two-factor destinations', () => {
    for (const url of ['/admin', '/admin/two-factor-challenge', '/admin/projects?tab=costs']) {
        assert.equal(sameOriginDestination(url, 'https://preview.example'), 'https://preview.example' + url);
    }
    for (const url of ['https://evil.example/admin', '//evil.example/', 'javascript:alert(1)', 'data:text/html,example', 'http://preview.example/admin', null, undefined, '', '   ']) {
        assert.equal(sameOriginDestination(url, 'https://preview.example'), null);
    }
});
test('enhancement keeps explicit frame activation, normal CSRF form data and native fallback', () => {
    const source = read('platform/resources/js/auth.js');
    for (const value of ["submit.addEventListener('click'", "event.key === 'Enter'", "new URLSearchParams(new FormData(form))", "credentials: 'same-origin'", '20000', 'if (busy) return;']) assert(source.includes(value));
    const view = read('platform/resources/views/auth/login.blade.php');
    assert(view.includes('@csrf'));
    assert(view.includes('method="POST"'));
    assert(view.includes('SyntheticPreview::credentials()'));
});
test('active public HTML references use the filled SVG instead of the retired raster mark', () => {
    function walk(url) { return fs.readdirSync(url, {withFileTypes:true}).flatMap(e => e.isDirectory() ? walk(new URL(e.name+'/',url)) : e.name.endsWith('.html') ? [new URL(e.name,url)] : []); }
    const pages = walk(new URL('../../platform/public/', import.meta.url));
    assert.equal(pages.length, 142, 'Public URL contract must remain unchanged');
    for (const page of pages) {
        const html = fs.readFileSync(page, 'utf8');
        assert(!html.includes('img-f941b08b9510'), page.pathname);
        assert(!html.includes('/assets/img/woodex-logo.png'), page.pathname);
        assert(html.includes('/assets/brand/woodex-mark.svg'), page.pathname);
    }
    for (const view of ['errors/404', 'admin/website/builder']) {
        assert(!read('platform/resources/views/'+view+'.blade.php').includes('img-f941b08b9510'));
    }
});

test('proxy-safe sign-in never follows server redirects or posts to an unvalidated form address', () => {
    const source = read('platform/resources/js/auth.js');
    assert(source.includes("sameOriginDestination(form.getAttribute('action'), window.location.origin)"));
    assert(source.includes("redirect: 'error'"));
    assert(source.includes("Accept: 'application/json'"));
    assert(!source.includes('fetch(form.action'));
    assert(read('platform/resources/views/auth/login.blade.php').includes("route('login.store', [], false)"));
});
test('explicit glass appearance has a persisted solid-contrast alternative', () => {
    assert(read('platform/resources/views/layouts/auth.blade.php').includes('data-auth-glass="glass"'));
    assert(read('platform/resources/css/auth.css').includes(':root[data-auth-glass="solid"]'));
    assert(read('platform/resources/js/auth.js').includes("localStorage.setItem('wx.auth.glass', value)"));
});
