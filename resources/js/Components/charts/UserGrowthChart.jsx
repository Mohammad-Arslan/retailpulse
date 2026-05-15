import ChartCard from '@/Components/charts/ChartCard';
import { CHART_COLORS } from '@/Components/charts/chartTheme';
import { useChartTheme } from '@/Hooks/useChartTheme';
import { useTheme } from '@/Hooks/useTheme';
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
    const chart = useChartTheme();
    const { isDark } = useTheme();

    const tickStyle = { fill: chart.tick, fontSize: 11, fontFamily: 'Sora, sans-serif' };

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
                    <CartesianGrid stroke={chart.grid} vertical={false} />
                    <XAxis
                        dataKey="label"
                        axisLine={false}
                        tickLine={false}
                        tick={tickStyle}
                    />
                    <YAxis
                        allowDecimals={false}
                        axisLine={false}
                        tickLine={false}
                        tick={tickStyle}
                    />
                    <Tooltip
                        contentStyle={chart.tooltip}
                        labelStyle={{ color: chart.tick, fontWeight: 600 }}
                        formatter={(value) => [value, 'New users']}
                    />
                    <Area
                        type="monotone"
                        dataKey="count"
                        stroke={CHART_COLORS.teal}
                        strokeWidth={2.5}
                        fill="url(#userGrowthFill)"
                        dot={{
                            r: 4,
                            fill: CHART_COLORS.teal,
                            stroke: isDark ? '#2d2926' : '#fff',
                            strokeWidth: 2,
                        }}
                        activeDot={{ r: 6 }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
