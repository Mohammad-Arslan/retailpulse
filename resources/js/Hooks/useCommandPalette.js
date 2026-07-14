import { useCallback, useEffect, useState } from 'react';

/**
 * Dual-mode opener:
 * - global: ERP-wide search (top navbar + Ctrl/Cmd+K)
 * - nav: sidebar page jump only
 */
export function useCommandPalette() {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState('global');

    const closePalette = useCallback(() => setOpen(false), []);

    const openGlobalSearch = useCallback(() => {
        setMode('global');
        setOpen(true);
    }, []);

    const openNavSearch = useCallback(() => {
        setMode('nav');
        setOpen(true);
    }, []);

    useEffect(() => {
        const onKeyDown = (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setMode('global');
                setOpen((value) => !value);
            }

            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return {
        open,
        mode,
        openGlobalSearch,
        openNavSearch,
        closePalette,
        /** @deprecated use openGlobalSearch */
        openPalette: openGlobalSearch,
    };
}
