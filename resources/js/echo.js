import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtime = window.__REVERB__ ?? {};
const key = runtime.key || import.meta.env.VITE_REVERB_APP_KEY;

if (typeof key === 'string' && key.length > 0) {
    const port = Number(runtime.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080);
    const scheme = runtime.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'http';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: runtime.host ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
                    '',
            },
        },
    });
}
