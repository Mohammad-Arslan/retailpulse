import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

function resolveFlash(flash) {
    if (!flash || typeof flash !== 'object') {
        return null;
    }

    if (flash.success || flash.error || flash.warning) {
        return flash;
    }

    return null;
}

function showFlashMessages(flash, shown) {
    const messages = resolveFlash(flash);

    if (!messages) {
        return;
    }

    if (messages.success && shown.current.success !== messages.success) {
        shown.current.success = messages.success;
        toast.success(messages.success);
    }

    if (messages.error && shown.current.error !== messages.error) {
        shown.current.error = messages.error;
        toast.error(messages.error);
    }

    if (messages.warning && shown.current.warning !== messages.warning) {
        shown.current.warning = messages.warning;
        toast.warning(messages.warning);
    }
}

export function useFlashToasts() {
    const shown = useRef({ success: null, error: null, warning: null });

    useEffect(() => {
        const removeFlash = router.on('flash', (event) => {
            showFlashMessages(event.detail.flash, shown);
        });

        const removeSuccess = router.on('success', (event) => {
            const page = event.detail.page;
            const flash = page?.flash ?? page?.props?.flash;

            showFlashMessages(flash, shown);
        });

        return () => {
            removeFlash();
            removeSuccess();
        };
    }, []);
}
