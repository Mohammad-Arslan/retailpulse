import ChartCard from '@/Components/charts/ChartCard';
import { CHART_PALETTE } from '@/Components/charts/chartTheme';
import { useChartTheme } from '@/Hooks/useChartTheme';
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

function formatRoleLabel(role) {
    return role.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function UsersByRoleChart({ data }) {
    const chart = useChartTheme();
    const tickStyle = { fill: chart.tick, fontSize: 11, fontFamily: 'Sora, sans-serif' };

    const chartData = data.map((row) => ({
        ...row,
        label: formatRoleLabel(row.role),
    }));

    return (
        <ChartCard title="Users by Role" subtitle="Team members per access profile">
            <ResponsiveContainer width="100%" height={220}>
                <BarChart data={chartData} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                    <CartesianGrid stroke={chart.grid} vertical={false} />
                    <XAxis
                        dataKey="label"
                        axisLine={false}
                        tickLine={false}
                        tick={tickStyle}
                        interval={0}
                        angle={chartData.length > 4 ? -20 : 0}
                        textAnchor={chartData.length > 4 ? 'end' : 'middle'}
                        height={chartData.length > 4 ? 56 : 30}
                    />
                    <YAxis
                        allowDecimals={false}
                        axisLine={false}
                        tickLine={false}
                        tick={tickStyle}
                    />
                    <Tooltip
                        contentStyle={chart.tooltip}
                        formatter={(value) => [value, 'Users']}
                        labelFormatter={(_, payload) =>
                            payload?.[0]?.payload?.role ?? ''
                        }
                    />
                    <Bar dataKey="count" radius={[6, 6, 0, 0]} maxBarSize={48}>
                        {chartData.map((entry, index) => (
                            <Cell
                                key={entry.role}
                                fill={CHART_PALETTE[index % CHART_PALETTE.length]}
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
