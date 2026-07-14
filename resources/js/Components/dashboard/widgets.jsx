import StockMovementTrendChart from '@/Components/charts/StockMovementTrendChart';
import RevenueBarChart from '@/Components/dashboard/RevenueBarChart';
import StatGroupCard from '@/Components/dashboard/StatGroupCard';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    ClipboardList,
    Landmark,
    LayoutDashboard,
    Package,
    Receipt,
    Scale,
    Tag,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';

const fmtInt = (n) =>
    typeof n === 'number' ? new Intl.NumberFormat(undefined).format(n) : '—';

const fmtMoney = (n) =>
    typeof n === 'number'
        ? new Intl.NumberFormat(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(n)
        : '—';

function WidgetShell({ title, action, children, className = '', id, labelStyle = false }) {
    return (
        <section id={id} className={`mb-6 ${className}`}>
            <div className="mb-3.5 flex items-center justify-between gap-3">
                <h2 className={`${labelStyle ? 'rp-section-label' : 'rp-section-title'} mb-0`}>
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function Sparkline({ points = [], direction = 'flat' }) {
    if (!Array.isArray(points) || points.length < 2) {
        return null;
    }

    const width = 70;
    const height = 24;
    const min = Math.min(...points);
    const max = Math.max(...points);
    const range = max - min || 1;
    const coords = points
        .map((value, index) => {
            const x = (index / (points.length - 1)) * width;
            const y = height - 2 - ((value - min) / range) * (height - 4);
            return `${x},${y}`;
        })
        .join(' ');

    const stroke =
        direction === 'up'
            ? 'var(--color-teal-500, #2a7c6f)'
            : direction === 'down'
              ? 'var(--color-rose-500, #b2402f)'
              : 'var(--rp-text-muted)';

    return (
        <svg
            className="pointer-events-none absolute right-3.5 bottom-3.5 opacity-70"
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            aria-hidden
        >
            <polyline points={coords} fill="none" stroke={stroke} strokeWidth="2" />
        </svg>
    );
}

function TrendBadge({ trend }) {
    if (!trend || !trend.direction) {
        return null;
    }

    const { direction, percent } = trend;
    const symbol = direction === 'up' ? '▲' : direction === 'down' ? '▼' : '—';
    const colorClass =
        direction === 'up'
            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
            : direction === 'down'
              ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
              : 'bg-rp-surface-inset text-rp-text-muted';

    return (
        <span
            className={`inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11.5px] font-bold ${colorClass}`}
        >
            {symbol} {typeof percent === 'number' ? `${percent}%` : ''}
        </span>
    );
}

function KpiGrid({ items }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {items.map((kpi) => {
                const Icon = kpi.icon ?? LayoutDashboard;
                const format = kpi.format ?? fmtInt;
                const hasTrend = Boolean(kpi.trend?.direction);
                const card = (
                    <>
                        <div className="mb-3.5 flex items-center justify-between gap-2">
                            <div className="rp-kpi-icon-teal">
                                <Icon className="h-[18px] w-[18px]" />
                            </div>
                            {hasTrend ? <TrendBadge trend={kpi.trend} /> : null}
                        </div>
                        <span className="rp-kpi-value">{format(kpi.value)}</span>
                        <div className="rp-kpi-label">{kpi.label}</div>
                        {kpi.sub ? <div className="rp-kpi-sub">{kpi.sub}</div> : null}
                        {hasTrend && Array.isArray(kpi.trend.points) ? (
                            <Sparkline points={kpi.trend.points} direction={kpi.trend.direction} />
                        ) : null}
                    </>
                );

                if (kpi.href) {
                    return (
                        <Link
                            key={kpi.label}
                            href={kpi.href}
                            className={`rp-kpi-card before:bg-teal-500 transition hover:border-teal-300 ${
                                kpi.warn ? 'ring-1 ring-amber-400/40' : ''
                            }`}
                        >
                            {card}
                        </Link>
                    );
                }

                return (
                    <div
                        key={kpi.label}
                        className={`rp-kpi-card before:bg-teal-500 ${
                            kpi.warn ? 'ring-1 ring-amber-400/40' : ''
                        }`}
                    >
                        {card}
                    </div>
                );
            })}
        </div>
    );
}

export function BusinessExceptionsWidget({ data, embedded = false }) {
    const { t } = useTranslation();
    const items = data?.items ?? [];

    const list =
        items.length === 0 ? (
            <p className="text-sm text-rp-text-muted">{t('pages.dashboard.exceptions.empty')}</p>
        ) : (
            <ul className="divide-y divide-rp-border-subtle">
                {items.map((item) => {
                    const isCritical = item.severity === 'critical';
                    const Icon = isCritical ? AlertCircle : AlertTriangle;
                    const content = (
                        <div className="flex items-start gap-3 py-3 first:pt-0.5 last:pb-0">
                            <div
                                className={`flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[9px] ${
                                    isCritical
                                        ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300'
                                        : item.severity === 'warning'
                                          ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400'
                                          : 'bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300'
                                }`}
                            >
                                <Icon className="h-[15px] w-[15px]" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-[13px] font-semibold text-rp-text">{item.title}</p>
                                <p className="text-xs text-rp-text-secondary">{item.message}</p>
                            </div>
                        </div>
                    );

                    return (
                        <li key={item.id}>
                            {item.href ? (
                                <Link href={item.href} className="block hover:opacity-80">
                                    {content}
                                </Link>
                            ) : (
                                content
                            )}
                        </li>
                    );
                })}
            </ul>
        );

    if (embedded) {
        return (
            <div id="dashboard-attention" className="rp-card flex h-full flex-col !p-[22px]">
                <div className="mb-1">
                    <h3 className="text-[15px] font-semibold text-rp-text">
                        {t('pages.dashboard.widgets.exceptions')}
                    </h3>
                    <p className="mt-0.5 text-[12.5px] text-rp-text-muted">
                        {t('pages.dashboard.exceptions.subtitle')}
                    </p>
                </div>
                <div className="mt-4 flex-1">{list}</div>
            </div>
        );
    }

    return (
        <WidgetShell id="dashboard-attention" title={t('pages.dashboard.widgets.exceptions')}>
            <div className="rp-card">{list}</div>
        </WidgetShell>
    );
}

export function SalesKpisWidget({ data }) {
    const { t } = useTranslation();
    const trends = data?.trends ?? {};
    const items = [
        {
            label: t('pages.dashboard.sales.todaysSales'),
            value: data.todays_sales,
            icon: Receipt,
            format: fmtMoney,
            href: data.sales_index_href,
            trend: trends.todays_sales,
        },
        {
            label: t('pages.dashboard.sales.transactions'),
            value: data.transaction_count,
            icon: Tag,
            format: fmtInt,
            trend: trends.transaction_count,
        },
        {
            label: t('pages.dashboard.sales.atv'),
            value: data.average_transaction_value,
            icon: LayoutDashboard,
            format: fmtMoney,
            trend: trends.average_transaction_value,
        },
        {
            label: t('pages.dashboard.sales.layaways'),
            value: data.pending_layaways,
            icon: AlertTriangle,
            format: fmtInt,
            href: data.sales_index_href,
        },
    ];

    if (data.can_view_profit && data.gross_profit != null) {
        items.splice(1, 0, {
            label: t('pages.dashboard.sales.grossProfit'),
            value: data.gross_profit,
            icon: Scale,
            format: fmtMoney,
            trend: trends.gross_profit,
        });
    }

    return (
        <WidgetShell
            title={t('pages.dashboard.sales.sectionTitle')}
            labelStyle
            action={
                data.sales_index_href ? (
                    <Link
                        href={data.sales_index_href}
                        className="text-[12.5px] font-semibold text-teal-600 hover:underline dark:text-teal-400"
                    >
                        {t('pages.dashboard.sales.viewReports')}
                    </Link>
                ) : null
            }
        >
            <KpiGrid items={items} />
        </WidgetShell>
    );
}

export function RevenueChartsWidget({ data, chartOnly = false }) {
    const { t } = useTranslation();
    const chart = (
        <RevenueBarChart
            dailySeries={data?.wow_revenue ?? []}
            monthlySeries={data?.mom_revenue ?? []}
        />
    );

    if (chartOnly) {
        return chart;
    }

    return <WidgetShell title={t('pages.dashboard.widgets.revenue')}>{chart}</WidgetShell>;
}

export function InventoryStatCard({ data }) {
    const { t } = useTranslation();
    const rows = [
        {
            label: t('pages.dashboard.inventory.unitsOnHand'),
            value: fmtInt(data.units_on_hand),
        },
        {
            label: t('pages.dashboard.inventory.lowStock'),
            value: fmtInt(data.low_stock_lines),
            tone: data.low_stock_lines > 0 ? 'warn' : undefined,
        },
        {
            label: t('pages.dashboard.inventory.movementsToday'),
            value: fmtInt(data.stock_movements_today),
        },
        {
            label: t('pages.dashboard.inventory.transfersRow'),
            value: `${fmtInt(data.transfers_draft)} · ${fmtInt(data.transfers_in_transit)}`,
        },
    ];

    if (data.critical_low_stock_lines > 0) {
        rows.splice(2, 0, {
            label: t('pages.dashboard.inventory.criticalLines'),
            value: fmtInt(data.critical_low_stock_lines),
            tone: 'danger',
        });
    }

    return (
        <StatGroupCard
            title={t('pages.dashboard.widgets.inventory')}
            icon={Warehouse}
            iconVariant="blue"
            rows={rows}
            footerHref={data.inventory_index_href}
            footerLabel={t('pages.dashboard.inventory.manageLink')}
        />
    );
}

export function InventoryTrendSection({ data }) {
    if (!Array.isArray(data?.stock_movement_trend) || data.stock_movement_trend.length === 0) {
        return null;
    }

    return (
        <div className="mt-4">
            <StockMovementTrendChart data={data.stock_movement_trend} />
        </div>
    );
}

export function InventoryOverviewWidget({ data }) {
    return (
        <>
            <InventoryStatCard data={data} />
            <InventoryTrendSection data={data} />
        </>
    );
}

export function ProcurementStatCard({ data }) {
    const { t } = useTranslation();

    return (
        <StatGroupCard
            title={t('pages.dashboard.widgets.procurement')}
            icon={ClipboardList}
            iconVariant="violet"
            rows={[
                {
                    label: t('pages.dashboard.procurement.openPos'),
                    value: fmtInt(data.open_pos),
                },
                {
                    label: t('pages.dashboard.procurement.pendingApprovals'),
                    value: fmtInt(data.pending_approvals),
                    tone: data.pending_approvals > 0 ? 'warn' : undefined,
                },
                {
                    label: t('pages.dashboard.procurement.pendingReceipts'),
                    value: fmtInt(data.pending_receipts),
                },
                {
                    label: t('pages.dashboard.procurement.pendingInvoices'),
                    value: fmtInt(data.pending_invoices),
                },
                {
                    label: t('pages.dashboard.procurement.outstandingPayables'),
                    value: fmtMoney(data.outstanding_payables),
                },
                {
                    label: t('pages.dashboard.procurement.monthlyPurchases'),
                    value: fmtMoney(data.monthly_purchases),
                },
                {
                    label: t('pages.dashboard.procurement.openReturns'),
                    value: fmtInt(data.open_returns),
                },
            ]}
            footerHref={data.reports_href}
            footerLabel={t('pages.dashboard.procurement.viewLink')}
        />
    );
}

export function ProcurementSuppliersTable({ data }) {
    const { t } = useTranslation();

    if (!data?.top_suppliers?.length) {
        return null;
    }

    return (
        <div className="mt-4 rounded-2xl border border-rp-border bg-rp-surface p-4">
            <h3 className="mb-3 text-sm font-medium text-rp-text-secondary">
                {t('pages.dashboard.procurement.topSuppliers')}
            </h3>
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-rp-border text-rp-text-muted">
                        <th className="py-2">{t('pages.dashboard.procurement.supplierColumn')}</th>
                        <th>{t('pages.dashboard.procurement.balanceColumn')}</th>
                        <th>{t('pages.dashboard.procurement.onTimeColumn')}</th>
                    </tr>
                </thead>
                <tbody>
                    {data.top_suppliers.map((s) => (
                        <tr key={s.id} className="border-b border-rp-border-subtle">
                            <td className="py-2">
                                <Link
                                    href={route('admin.suppliers.show', s.id)}
                                    className="text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    {s.name}
                                </Link>
                            </td>
                            <td>{fmtMoney(Number(s.balance))}</td>
                            <td>{s.on_time_delivery_rate ?? '—'}%</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function ProcurementOverviewWidget({ data }) {
    return (
        <>
            <ProcurementStatCard data={data} />
            <ProcurementSuppliersTable data={data} />
        </>
    );
}

export function FinanceStatCard({ data }) {
    const { t } = useTranslation();

    return (
        <StatGroupCard
            title={t('pages.dashboard.widgets.finance')}
            icon={Landmark}
            iconVariant="teal"
            rows={[
                {
                    label: t('pages.dashboard.finance.unpostedJournals'),
                    value: fmtInt(data.unposted_journals),
                    tone: data.unposted_journals > 0 ? 'warn' : undefined,
                },
                {
                    label: t('pages.dashboard.finance.bankUnmatched'),
                    value: fmtInt(data.bank_unmatched),
                    tone: data.bank_unmatched > 0 ? 'warn' : undefined,
                },
                {
                    label: t('pages.dashboard.finance.arAging'),
                    value: fmtMoney(data.ar_aging_total),
                },
                {
                    label: t('pages.dashboard.finance.apAging'),
                    value: fmtMoney(data.ap_aging_total),
                },
            ]}
            footerHref={data.ar_aging_href ?? data.unposted_href}
            footerLabel={t('pages.dashboard.finance.viewLink')}
        />
    );
}

export function FinanceOverviewWidget({ data }) {
    return <FinanceStatCard data={data} />;
}

export function OperationsOverviewWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell title={t('pages.dashboard.widgets.organization')} labelStyle>
            <KpiGrid
                items={[
                    {
                        label: t('pages.dashboard.operations.branches'),
                        value: `${fmtInt(data.branches_active)} / ${fmtInt(data.branches_total)}`,
                        icon: Building2,
                        format: (v) => v,
                        href: data.branches_href,
                        sub: t('pages.dashboard.operations.branchesSub'),
                    },
                    {
                        label: t('pages.dashboard.operations.warehouses'),
                        value: data.warehouses,
                        icon: Warehouse,
                        href: data.warehouses_href,
                    },
                    {
                        label: t('pages.dashboard.operations.products'),
                        value: data.products_active,
                        icon: Package,
                        href: data.products_href,
                        sub: t('pages.dashboard.operations.productsSub', {
                            variants: fmtInt(data.product_variants),
                            categories: fmtInt(data.categories),
                            brands: fmtInt(data.brands),
                        }),
                    },
                ]}
            />
            {data.branches_preview?.length > 0 ? (
                <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {data.branches_preview.map((branch) => (
                        <div key={branch.id} className="rp-card">
                            <p className="font-medium text-rp-text">{branch.name}</p>
                            <p className="text-xs text-rp-text-muted">{branch.code}</p>
                            <p className="mt-2 text-sm text-rp-text-secondary">
                                {t('pages.dashboard.operations.branchMeta', {
                                    warehouses: branch.warehouses_count,
                                    users: branch.users_count,
                                })}
                            </p>
                        </div>
                    ))}
                </div>
            ) : null}
        </WidgetShell>
    );
}

const ACTION_ICONS = {
    pos: LayoutDashboard,
    sales: Receipt,
    inventory: Warehouse,
    transfers: Truck,
    purchase_orders: ClipboardList,
    accounting_reports: Landmark,
    products: Package,
    customers: Users,
};

export function QuickActionsWidget({ data }) {
    const { t } = useTranslation();
    const actions = data?.actions ?? [];

    return (
        <WidgetShell title={t('pages.dashboard.widgets.quickActions')} labelStyle>
            <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {actions.map((action) => {
                    const Icon = ACTION_ICONS[action.id] ?? LayoutDashboard;

                    return (
                        <Link
                            key={action.id}
                            href={action.href}
                            className="flex items-center gap-3 rounded-[14px] border border-rp-border bg-rp-surface p-4 text-rp-text no-underline transition hover:-translate-y-0.5 hover:border-teal-400"
                        >
                            <div className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300">
                                <Icon className="h-[17px] w-[17px]" />
                            </div>
                            <div>
                                <div className="rp-quick-action-title">
                                    {t(`pages.dashboard.quickActions.${action.label_key}`)}
                                </div>
                                <div className="rp-quick-action-desc">
                                    {t(`pages.dashboard.quickActions.${action.desc_key}`)}
                                </div>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </WidgetShell>
    );
}
