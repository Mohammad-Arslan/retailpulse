import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const fmtMoney = (n) =>
    typeof n === 'number'
        ? new Intl.NumberFormat(undefined, {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          }).format(n)
        : '—';

/**
 * Lightweight SVG/CSS bar chart for daily (7D) or monthly (6M) revenue series.
 * Follows the dashboard mockup: filled teal for the most recent period, muted for prior.
 */
export default function RevenueBarChart({
    dailySeries = [],
    monthlySeries = [],
    subtitle,
}) {
    const { t } = useTranslation();
    const [range, setRange] = useState('7d');
    const series = range === '7d' ? dailySeries : monthlySeries;
    const max = Math.max(...series.map((row) => Number(row.amount) || 0), 0.01);

    return (
        <div className="rp-card flex h-full flex-col !p-[22px]">
            <div className="mb-1 flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-[15px] font-semibold text-rp-text">
                        {t('pages.dashboard.sales.revenueTrend')}
                    </h3>
                    <p className="mt-0.5 text-[12.5px] text-rp-text-muted">
                        {subtitle ??
                            (range === '7d'
                                ? t('pages.dashboard.sales.wowRevenue')
                                : t('pages.dashboard.sales.momRevenue'))}
                    </p>
                </div>
                <div className="flex gap-1 rounded-lg bg-rp-surface-inset p-0.5">
                    {[
                        { id: '7d', label: t('pages.dashboard.sales.range7d') },
                        { id: '6m', label: t('pages.dashboard.sales.range6m') },
                    ].map((opt) => (
                        <button
                            key={opt.id}
                            type="button"
                            onClick={() => setRange(opt.id)}
                            className={`rounded-md px-2.5 py-1 text-[11.5px] font-semibold transition ${
                                range === opt.id
                                    ? 'bg-rp-surface text-rp-text shadow-sm'
                                    : 'text-rp-text-muted hover:text-rp-text-secondary'
                            }`}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>
            </div>

            {series.length === 0 ? (
                <p className="mt-6 text-sm text-rp-text-muted">{t('pages.dashboard.sales.noRevenueData')}</p>
            ) : (
                <div className="mt-4 flex h-[150px] items-end gap-2.5">
                    {series.map((row, index) => {
                        const amount = Number(row.amount) || 0;
                        const heightPct = Math.max((amount / max) * 100, amount > 0 ? 4 : 2);
                        const isLatest = index === series.length - 1;

                        return (
                            <div
                                key={`${row.label}-${index}`}
                                className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                                title={`${row.label}: ${fmtMoney(amount)}`}
                            >
                                <div
                                    className={`w-full max-w-[34px] rounded-t-[6px] rounded-b-[3px] transition ${
                                        isLatest
                                            ? 'bg-gradient-to-b from-teal-400 to-teal-500'
                                            : 'bg-rp-surface-inset dark:bg-rp-border'
                                    }`}
                                    style={{ height: `${heightPct}%` }}
                                />
                                <span
                                    className={`text-[11px] font-medium ${
                                        isLatest
                                            ? 'font-bold text-teal-600 dark:text-teal-400'
                                            : 'text-rp-text-muted'
                                    }`}
                                >
                                    {row.label}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
