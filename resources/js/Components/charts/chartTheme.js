export const CHART_COLORS = {
    teal: '#2a7c6f',
    tealLight: '#4db8a8',
    tealMuted: '#d0f0eb',
    amber: '#c8762a',
    violet: '#6a4ac8',
    rose: '#c84a4a',
    ink300: '#9ca3af',
    ink500: '#6b7280',
    sand100: '#f3f4f6',
};

export const CHART_PALETTE = [
    CHART_COLORS.teal,
    CHART_COLORS.violet,
    CHART_COLORS.amber,
    CHART_COLORS.rose,
    CHART_COLORS.tealLight,
    '#457090',
];

export const chartTooltipStyle = {
    borderRadius: 10,
    border: `1px solid ${CHART_COLORS.sand100}`,
    boxShadow: '0 8px 24px rgba(26,23,20,0.08)',
    fontSize: 13,
    fontFamily: 'Sora, sans-serif',
};

export const chartAxisTick = {
    fill: CHART_COLORS.ink300,
    fontSize: 11,
    fontFamily: 'Sora, sans-serif',
};
