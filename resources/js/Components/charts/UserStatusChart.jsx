import ChartCard from '@/Components/charts/ChartCard';
import { CHART_COLORS } from '@/Components/charts/chartTheme';
import { useChartTheme } from '@/Hooks/useChartTheme';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export default function UserStatusChart({ data }) {
    const chart = useChartTheme();
    const total = data.reduce((sum, row) => sum + row.count, 0);
    const inactiveColor = chart.tick;

    return (
        <ChartCard title="User Status" subtitle="Active vs inactive accounts">
            <ResponsiveContainer width="100%" height={220}>
                <PieChart>
                    <Pie
                        data={data}
                        dataKey="count"
                        nameKey="status"
                        cx="50%"
                        cy="50%"
                        innerRadius={58}
                        outerRadius={82}
                        paddingAngle={3}
                    >
                        {data.map((entry) => (
                            <Cell
                                key={entry.status}
                                fill={
                                    entry.status === 'Active'
                                        ? CHART_COLORS.teal
                                        : inactiveColor
                                }
                                stroke="none"
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        contentStyle={chart.tooltip}
                        formatter={(value, name) => [value, name]}
                    />
                    <text
                        x="50%"
                        y="50%"
                        textAnchor="middle"
                        dominantBaseline="middle"
                        fill={chart.centerText}
                        fontSize={24}
                        fontFamily="Instrument Serif, serif"
                    >
                        {total}
                    </text>
                    <text
                        x="50%"
                        y="58%"
                        textAnchor="middle"
                        dominantBaseline="middle"
                        fill={chart.centerSubtext}
                        fontSize={11}
                        fontFamily="Sora, sans-serif"
                    >
                        total
                    </text>
                </PieChart>
            </ResponsiveContainer>
            <div className="mt-2 flex justify-center gap-4">
                {data.map((row) => (
                    <div
                        key={row.status}
                        className="flex items-center gap-1.5 text-xs text-rp-text-secondary"
                    >
                        <span
                            className="h-2 w-2 rounded-full"
                            style={{
                                background:
                                    row.status === 'Active'
                                        ? CHART_COLORS.teal
                                        : inactiveColor,
                            }}
                        />
                        {row.status} ({row.count})
                    </div>
                ))}
            </div>
        </ChartCard>
    );
}
