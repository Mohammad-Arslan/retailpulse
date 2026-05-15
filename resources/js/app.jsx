import '../css/app.css';
import './bootstrap';

import AppProviders from '@/Components/common/AppProviders';
import FlashToasts from '@/Components/common/FlashToasts';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AppProviders>
                <App {...props}>
                    {({ Component, key, props: pageProps }) => (
                        <>
                            {createElement(Component, { key, ...pageProps })}
                            <FlashToasts />
                        </>
                    )}
                </App>
            </AppProviders>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
