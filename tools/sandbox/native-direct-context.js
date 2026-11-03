// Served ONLY by the private direct-demo launcher. This file is not a production asset.
// A random page context correlates demo requests when the preview transport discards all cookies.
// It is not a Laravel session ID or a production auth token. Never append it to URLs or foreign requests.
export function demoFetch(input, init, {fetch, origin, context}) {
    const url = new URL(input instanceof Request ? input.url : input, origin);
    if (url.origin !== origin || !/^[a-f0-9]{32}$/.test(context || '')) return fetch(input, init);
    const headers = new Headers(input instanceof Request ? input.headers : undefined);
    new Headers(init?.headers).forEach((value, key) => headers.set(key, value));
    headers.set('X-Woodex-Demo-Context', context);
    return fetch(input, {...init, headers});
}
export function demoXhrContext(input, {origin, context}) {
    try {
        return new URL(input, origin).origin === origin && /^[a-f0-9]{32}$/.test(context || '') ? context : null;
    } catch {
        return null;
    }
}
if (typeof window !== 'undefined') {
    // Livewire uploads use XHR rather than fetch. Preserve their ordinary CSRF header/session too.
    const targets = new WeakMap();
    const nativeOpen = window.XMLHttpRequest.prototype.open;
    const nativeSend = window.XMLHttpRequest.prototype.send;
    window.XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        targets.set(this, url);
        return nativeOpen.call(this, method, url, ...rest);
    };
    window.XMLHttpRequest.prototype.send = function (...args) {
        const context = demoXhrContext(targets.get(this), {
            origin: window.location.origin,
            context: document.querySelector('meta[name="woodex-demo-context"]')?.content,
        });
        if (context) this.setRequestHeader('X-Woodex-Demo-Context', context);
        return nativeSend.apply(this, args);
    };
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (input, init) => demoFetch(input, init, {
        fetch: nativeFetch,
        origin: window.location.origin,
        context: document.querySelector('meta[name="woodex-demo-context"]')?.content,
    });
}
