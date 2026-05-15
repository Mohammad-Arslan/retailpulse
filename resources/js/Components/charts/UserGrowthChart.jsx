import ChartCard from '@/Components/charts/ChartCard';
import {
    CHART_COLORS,
    chartAxisTick,
    chartTooltipStyle,
} from '@/Components/charts/chartTheme';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export default function UserGrowthChart({ data }) {
    return (
        <ChartCard
            title="New Users"
            subtitle="Accounts created in the last 7 days"
            className="lg:col-span-2"
        >
            <ResponsiveContainer width="100%" height={220}>
                <AreaChart data={data} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                    <defs>
                        <linearGradient id="userGrowthFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={CHART_COLORS.teal} stopOpacity={0.2} />
                            <stop offset="100%" stopColor={CHART_COLORS.teal} stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid stroke={CHART_COLORS.sand100} vertical={false} />
                    <XAxis
                        dataKey="label"
                        axisLine={false}
                        tickLine={false}
                        tick={chartAxisTick}
                    />
                    <YAxis
                        allowDecimals={false}
                        axisLine={false}
                        tickLine={false}
                        tick={chartAxisTick}
                    />
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        labelStyle={{ color: CHART_COLORS.ink500, fontWeight: 600 }}
                        formatter={(value) => [value, 'New users']}
                    />
                    <Area
                        type="monotone"
                        dataKey="count"
                        stroke={CHART_COLORS.teal}
                        strokeWidth={2.5}
                        fill="url(#userGrowthFill)"
                        dot={{ r: 4, fill: CHART_COLORS.teal, stroke: '#fff', strokeWidth: 2 }}
                        activeDot={{ r: 6 }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
