import { useCallback, useEffect, useState } from 'react';

export function useCommandPalette() {
    const [open, setOpen] = useState(false);

    const openPalette = useCallback(() => setOpen(true), []);
    const closePalette = useCallback(() => setOpen(false), []);
    const togglePalette = useCallback(() => setOpen((value) => !value), []);

    useEffect(() => {
        const onKeyDown = (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                togglePalette();
            }

            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [togglePalette]);

    return { open, openPalette, closePalette, togglePalette, setOpen };
}
