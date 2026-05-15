import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'retailpulse-sidebar-collapsed';

export function useSidebarCollapsed() {
    const [collapsed, setCollapsedState] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return localStorage.getItem(STORAGE_KEY) === 'true';
    });

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
    }, [collapsed]);

    const toggleCollapsed = useCallback(() => {
        setCollapsedState((value) => !value);
    }, []);

    const setCollapsed = useCallback((value) => {
        setCollapsedState(Boolean(value));
    }, []);

    return { collapsed, toggleCollapsed, setCollapsed };
}
