import '../css/app.css';
import './bootstrap';
import './echo';

import AppProviders from '@/Components/common/AppProviders';
import FlashToasts from '@/Components/common/FlashToasts';
import LocaleSync from '@/Components/common/LocaleSync';
import { syncCsrfToken } from '@/lib/csrf';
import { createInertiaApp, router } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob('./Pages/**/*.{jsx,tsx}');

router.on('success', (event) => {
    syncCsrfToken(event.detail.page.props?.csrf_token);
});

// Safety net: a response Inertia can't render inline (no X-Inertia header — e.g. a raw error
// page) otherwise shows a jarring full-page modal with the response in an iframe. Everything the
// backend can anticipate (permission/module denials) already redirects back with a flash toast
// instead of reaching this point — this only catches what slips through that.
router.on('invalid', (event) => {
    const status = event.detail.response?.status;

    if (status === 419) {
        event.preventDefault();
        toast.error('Your session has expired. Reloading the page…');
        window.location.reload();
        return;
    }

    if (status === 403) {
        event.preventDefault();
        toast.error('You do not have permission to access this page.');
        return;
    }

    if (typeof status === 'number' && status >= 500) {
        event.preventDefault();
        toast.error('Something went wrong on our end. Please try again.');
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page =
            pages[`./Pages/${name}.tsx`]
            || pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        const mod = await page();
        return mod.default;
    },
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
