import '../css/app.css';
import './bootstrap';
import './echo';

import AppProviders from '@/Components/common/AppProviders';
import FlashToasts from '@/Components/common/FlashToasts';
import LocaleSync from '@/Components/common/LocaleSync';
import { syncCsrfToken } from '@/lib/csrf';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('success', (event) => {
    syncCsrfToken(event.detail.page.props?.csrf_token);
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        syncCsrfToken(props.initialPage?.props?.csrf_token);

        const root = createRoot(el);

        root.render(
            <AppProviders>
                <App {...props}>
                    {({ Component, key, props: pageProps }) => (
                        <>
                            <LocaleSync locale={pageProps.locale} />
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
