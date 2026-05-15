import ChartCard from '@/Components/charts/ChartCard';
import {
    CHART_COLORS,
    CHART_PALETTE,
    chartAxisTick,
    chartTooltipStyle,
} from '@/Components/charts/chartTheme';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

function formatGroupLabel(group) {
    return group.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function PermissionsByGroupChart({ data }) {
    const chartData = data.map((row) => ({
        ...row,
        label: formatGroupLabel(row.group),
    }));

    return (
        <ChartCard
            title="Permissions by Group"
            subtitle="Capability distribution across modules"
        >
            <ResponsiveContainer width="100%" height={220}>
                <BarChart
                    data={chartData}
                    layout="vertical"
                    margin={{ top: 4, right: 12, left: 4, bottom: 4 }}
                >
                    <CartesianGrid stroke={CHART_COLORS.sand100} horizontal={false} />
                    <XAxis
                        type="number"
                        allowDecimals={false}
                        axisLine={false}
                        tickLine={false}
                        tick={chartAxisTick}
                    />
                    <YAxis
                        type="category"
                        dataKey="label"
                        width={88}
                        axisLine={false}
                        tickLine={false}
                        tick={chartAxisTick}
                    />
                    <Tooltip
                        contentStyle={chartTooltipStyle}
                        formatter={(value) => [value, 'Permissions']}
                    />
                    <Bar dataKey="count" radius={[0, 6, 6, 0]} maxBarSize={22}>
                        {chartData.map((entry, index) => (
                            <Cell
                                key={entry.group}
                                fill={CHART_PALETTE[index % CHART_PALETTE.length]}
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
