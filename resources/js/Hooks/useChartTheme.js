import { useTheme } from '@/Hooks/useTheme';
import { useMemo } from 'react';

export function useChartTheme() {
    const { isDark } = useTheme();

    return useMemo(
        () => ({
            grid: isDark ? '#46403a' : '#f3f4f6',
            tick: isDark ? '#d4c9b0' : '#9ca3af',
            tooltip: {
                backgroundColor: isDark ? '#2d2926' : '#ffffff',
                border: `1px solid ${isDark ? '#46403a' : '#e5e7eb'}`,
                borderRadius: 10,
                boxShadow: isDark
                    ? '0 8px 24px rgba(0,0,0,0.35)'
                    : '0 8px 24px rgba(26,23,20,0.08)',
                fontSize: 13,
                fontFamily: 'Sora, sans-serif',
                color: isDark ? '#faf8f5' : '#1a1714',
            },
            centerText: isDark ? '#faf8f5' : '#1a1714',
            centerSubtext: isDark ? '#b8b0a8' : '#9ca3af',
        }),
        [isDark],
    );
}
