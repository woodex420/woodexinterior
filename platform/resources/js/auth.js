/* Progressive enhancement only: normal Fortify POST/CSRF/2FA/intended redirects remain authoritative. */
export function signInFailure(status, retryAfter) {
    if (status === 419) return 'Your sign-in session expired or cookies were blocked. Reload this page, or open sign-in in a new tab.';
    if (status === 429) {
        const seconds = Number(retryAfter);
        return Number.isInteger(seconds) && seconds > 0 && seconds <= 3600
            ? `Too many sign-in attempts. Wait ${seconds} seconds and try again.`
            : 'Too many sign-in attempts. Wait a minute and try again.';
    }
    return 'Sign-in could not finish. Please try again, or open sign-in in a new tab.';
}

export function sameOriginDestination(value, origin) {
    try {
        const url = new URL(value, origin);
        return url.origin === origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}

export function installSignIn(form) {
    if (form.dataset.enhanced) return;
    form.dataset.enhanced = 'true';
    // Native validation bubbles can be suppressed inside sandboxed frames. Always show readable feedback.
    form.noValidate = true;
    const status = form.querySelector('[data-sign-in-status]');
    const submit = form.querySelector('button[type="submit"]');
    const password = form.querySelector('[name="password"]');
    const label = submit.textContent;
    let busy = false;
    const show = (message, error = true) => {
        status.textContent = message;
        status.hidden = false;
        status.setAttribute('role', error ? 'alert' : 'status');
    };
    const reset = () => {
        busy = false;
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        submit.textContent = label;
    };
    window.addEventListener('pageshow', reset);
    form.querySelector('[data-show-password]')?.addEventListener('click', (event) => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        event.currentTarget.textContent = visible ? 'Hide password' : 'Show password';
        event.currentTarget.setAttribute('aria-pressed', visible ? 'true' : 'false');
    });
    // Some preview frames suppress native form submission BEFORE a submit event is fired.
    // Explicit activation keeps validation and the real Fortify POST working in those frames.
    const requestSignIn = () => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    submit.addEventListener('click', (event) => {
        event.preventDefault();
        requestSignIn();
    });
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input[name="email"], input[name="password"]')) {
            event.preventDefault();
            requestSignIn();
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');
            show(invalid?.name === 'password' ? 'Enter your password to continue.' : 'Enter a valid email address to continue.');
            invalid?.focus();
            return;
        }
        busy = true;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = 'Signing in…';
        show('Checking your account…', false);
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        try {
            // HTML keeps Fortify's existing error bags, throttle, two-factor challenge and intended URL.
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', redirect: 'follow',
                headers: { Accept: 'text/html' },
                body: new URLSearchParams(new FormData(form)), signal: controller.signal,
            });
            if (!response.ok) {
                show(signInFailure(response.status, response.headers.get('Retry-After')));
                return;
            }
            const destination = sameOriginDestination(response.url, window.location.origin);
            if (!destination) {
                show('Sign-in returned an unexpected address. Reload this page and try again.');
                return;
            }
            if (new URL(destination).pathname === new URL(form.action).pathname) {
                const html = new DOMParser().parseFromString(await response.text(), 'text/html');
                const message = html.querySelector('[role="alert"]')?.textContent?.trim();
                show(message || 'Your sign-in session could not be retained. Enable cookies, or open sign-in in a new tab.');
                return;
            }
            show('Signed in. Opening your dashboard…', false);
            window.location.assign(destination);
        } catch {
            show(controller.signal.aborted
                ? 'Sign-in took too long. Try again, or open sign-in in a new tab.'
                : 'The sign-in request could not reach the server. Check your connection and try again.');
        } finally {
            window.clearTimeout(timeout);
            reset();
        }
    });
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('form[data-sign-in]').forEach(installSignIn);
}
