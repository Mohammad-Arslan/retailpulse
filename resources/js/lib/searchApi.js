import { ensureCsrfCookie } from '@/lib/csrf';

/**
 * @param {string} q
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function globalSearch(q, options = {}) {
    await ensureCsrfCookie();
    const { data } = await window.axios.get(route('api.v1.search', undefined, false), {
        params: { q },
        signal: options.signal,
    });
    return data;
}
