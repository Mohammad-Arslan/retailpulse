import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

/**
 * @param {unknown} flash
 */
function mergeFlashSources(page) {
    const propsFlash = page?.props?.flash;
    const rootFlash = page?.flash;

    const success =
        (rootFlash && rootFlash.success) ?? (propsFlash && propsFlash.success) ?? undefined;
    const error =
        (rootFlash && rootFlash.error) ?? (propsFlash && propsFlash.error) ?? undefined;
    const warning =
        (rootFlash && rootFlash.warning) ?? (propsFlash && propsFlash.warning) ?? undefined;

    if (!success && !error && !warning) {
        return null;
    }

    return { success, error, warning };
}

/**
 * @param {{ success?: unknown, error?: unknown, warning?: unknown }} messages
 */
function pushToasts(messages) {
    if (messages.success) {
        toast.success(String(messages.success));
    }

    if (messages.error) {
        toast.error(String(messages.error));
    }

    if (messages.warning) {
        toast.warning(String(messages.warning));
    }
}

export function useFlashToasts() {
    const page = usePage();

    /** @type {React.MutableRefObject<{ url: string, digest: string } | null>} */
    const last = useRef(null);

    useEffect(() => {
        const messages = mergeFlashSources(page);

        if (!messages) {
            return;
        }

        const digest = [messages.success, messages.error, messages.warning]
            .map((value) => (value === undefined || value === null ? '' : String(value)))
            .join('\u0001');

        const prev = last.current;

        if (prev !== null && prev.url === page.url && prev.digest === digest) {
            return;
        }

        last.current = { url: page.url, digest };
        pushToasts(messages);
    }, [
        page.url,
        page.props?.flash?.success,
        page.props?.flash?.error,
        page.props?.flash?.warning,
        page.flash?.success,
        page.flash?.error,
        page.flash?.warning,
    ]);
}
