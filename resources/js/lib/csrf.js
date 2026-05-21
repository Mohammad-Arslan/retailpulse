/**
 * CSRF helpers for axios calls outside Inertia (e.g. import/export API).
 * Uses meta tag, XSRF-TOKEN cookie, and Sanctum's csrf-cookie endpoint.
 */

export function readCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (meta) {
        return meta;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

export function syncCsrfToken(token) {
    if (!token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
        meta.setAttribute('content', token);
    }

    if (window.axios?.defaults?.headers?.common) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token;
    }
}

export function applyCsrfHeaders(headers) {
    const token = readCsrfToken();

    if (!token) {
        return headers;
    }

    if (headers && typeof headers.set === 'function') {
        headers.set('X-CSRF-TOKEN', token);
        headers.set('X-XSRF-TOKEN', token);
    } else if (headers) {
        headers['X-CSRF-TOKEN'] = token;
        headers['X-XSRF-TOKEN'] = token;
    }

    return headers;
}

let csrfCookiePromise = null;

/** Ensures the XSRF-TOKEN cookie is set and matches the session. */
export function ensureCsrfCookie() {
    if (!window.axios) {
        return Promise.resolve();
    }

    if (!csrfCookiePromise) {
        csrfCookiePromise = window.axios.get('/sanctum/csrf-cookie').finally(() => {
            const token = readCsrfToken();

            if (token) {
                syncCsrfToken(token);
            }
        });
    }

    return csrfCookiePromise;
}
