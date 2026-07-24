import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtime = window.__REVERB__ ?? {};
const key = runtime.key || import.meta.env.VITE_REVERB_APP_KEY;

if (typeof key === 'string' && key.length > 0) {
    const port = Number(runtime.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080);
    const scheme = runtime.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const forceTLS = scheme === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: runtime.host ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS,
        // pusher-js tries every listed transport regardless of forceTLS — with no TLS
        // termination on this deployment (scheme=http), an unconditional 'wss' entry
        // always fails with a protocol error (there's nothing serving TLS on this port).
        // Only offer the transport that actually matches the deployment's scheme.
        enabledTransports: forceTLS ? ['wss'] : ['ws'],
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
