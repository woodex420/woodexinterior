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
if (typeof window !== 'undefined') {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (input, init) => demoFetch(input, init, {
        fetch: nativeFetch,
        origin: window.location.origin,
        context: document.querySelector('meta[name="woodex-demo-context"]')?.content,
    });
}
