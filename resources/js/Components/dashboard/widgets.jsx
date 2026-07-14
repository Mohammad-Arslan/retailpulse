import StockMovementTrendChart from '@/Components/charts/StockMovementTrendChart';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    AlertTriangle,
    ArrowLeftRight,
    Building2,
    ClipboardList,
    FileText,
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

function WidgetShell({ title, action, children, className = '' }) {
    return (
        <section className={`mb-6 ${className}`}>
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="rp-section-title mb-0">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function KpiGrid({ items }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {items.map((kpi) => {
                const Icon = kpi.icon ?? LayoutDashboard;
                const format = kpi.format ?? fmtInt;
                const card = (
                    <>
                        <div className="mb-3.5">
                            <div className="rp-kpi-icon-teal">
                                <Icon className="h-[18px] w-[18px]" />
                            </div>
                        </div>
                        <span className="rp-kpi-value">{format(kpi.value)}</span>
                        <div className="rp-kpi-label">{kpi.label}</div>
                        {kpi.sub ? <div className="rp-kpi-sub">{kpi.sub}</div> : null}
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

export function BusinessExceptionsWidget({ data }) {
    const { t } = useTranslation();
    const items = data?.items ?? [];

    return (
        <WidgetShell title={t('pages.dashboard.widgets.exceptions')}>
            {items.length === 0 ? (
                <div className="rp-card text-sm text-rp-text-muted">
                    {t('pages.dashboard.exceptions.empty')}
                </div>
            ) : (
                <ul className="divide-y divide-rp-border rounded-lg border border-rp-border bg-rp-surface">
                    {items.map((item) => {
                        const content = (
                            <div className="flex items-start gap-3 px-4 py-3">
                                <AlertTriangle
                                    className={`mt-0.5 h-4 w-4 shrink-0 ${
                                        item.severity === 'critical'
                                            ? 'text-rose-500'
                                            : item.severity === 'warning'
                                              ? 'text-amber-500'
                                              : 'text-teal-500'
                                    }`}
                                />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-rp-text">{item.title}</p>
                                    <p className="text-sm text-rp-text-secondary">{item.message}</p>
                                </div>
                            </div>
                        );

                        return (
                            <li key={item.id}>
                                {item.href ? (
                                    <Link href={item.href} className="block hover:bg-rp-surface-subtle">
                                        {content}
                                    </Link>
                                ) : (
                                    content
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </WidgetShell>
    );
}

export function SalesKpisWidget({ data }) {
    const { t } = useTranslation();
    const items = [
        {
            label: t('pages.dashboard.sales.todaysSales'),
            value: data.todays_sales,
            icon: Receipt,
            format: fmtMoney,
            href: data.sales_index_href,
        },
        {
            label: t('pages.dashboard.sales.transactions'),
            value: data.transaction_count,
            icon: Tag,
            format: fmtInt,
        },
        {
            label: t('pages.dashboard.sales.atv'),
            value: data.average_transaction_value,
            icon: LayoutDashboard,
            format: fmtMoney,
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
        });
    }

    return (
        <WidgetShell title={t('pages.dashboard.widgets.sales')}>
            <KpiGrid items={items} />
        </WidgetShell>
    );
}

export function RevenueChartsWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell title={t('pages.dashboard.widgets.revenue')}>
            <div className="grid gap-4 lg:grid-cols-2">
                <div className="rp-card">
                    <h3 className="mb-3 text-sm font-medium text-rp-text-secondary">
                        {t('pages.dashboard.sales.wowRevenue')}
                    </h3>
                    <ul className="space-y-1.5 text-sm">
                        {(data.wow_revenue ?? []).map((row) => (
                            <li key={row.label} className="flex justify-between gap-3">
                                <span className="text-rp-text-muted">{row.label}</span>
                                <span className="font-medium text-rp-text">{fmtMoney(row.amount)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="rp-card">
                    <h3 className="mb-3 text-sm font-medium text-rp-text-secondary">
                        {t('pages.dashboard.sales.momRevenue')}
                    </h3>
                    <ul className="space-y-1.5 text-sm">
                        {(data.mom_revenue ?? []).map((row) => (
                            <li key={row.label} className="flex justify-between gap-3">
                                <span className="text-rp-text-muted">{row.label}</span>
                                <span className="font-medium text-rp-text">{fmtMoney(row.amount)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </WidgetShell>
    );
}

export function InventoryOverviewWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell title={t('pages.dashboard.widgets.inventory')}>
            <KpiGrid
                items={[
                    {
                        label: t('pages.dashboard.inventory.unitsOnHand'),
                        value: data.units_on_hand,
                        icon: Warehouse,
                        href: data.inventory_index_href,
                    },
                    {
                        label: t('pages.dashboard.inventory.lowStock'),
                        value: data.low_stock_lines,
                        icon: AlertTriangle,
                        href: data.inventory_index_href,
                        warn: data.low_stock_lines > 0,
                        sub:
                            data.critical_low_stock_lines > 0
                                ? t('pages.dashboard.inventory.criticalSub', {
                                      count: data.critical_low_stock_lines,
                                  })
                                : undefined,
                    },
                    {
                        label: t('pages.dashboard.inventory.movementsToday'),
                        value: data.stock_movements_today,
                        icon: ArrowLeftRight,
                    },
                    {
                        label: t('pages.dashboard.inventory.transfers'),
                        value: `${fmtInt(data.transfers_draft)} / ${fmtInt(data.transfers_in_transit)}`,
                        icon: Truck,
                        format: (v) => v,
                        href: data.transfers_index_href,
                        sub: t('pages.dashboard.inventory.transfersSub'),
                    },
                ]}
            />
            {Array.isArray(data.stock_movement_trend) && data.stock_movement_trend.length > 0 ? (
                <div className="mt-4">
                    <StockMovementTrendChart data={data.stock_movement_trend} />
                </div>
            ) : null}
        </WidgetShell>
    );
}

export function ProcurementOverviewWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell
            title={t('pages.dashboard.widgets.procurement')}
            action={
                <Link href={data.reports_href} className="text-sm text-teal-600 hover:underline">
                    {t('pages.dashboard.procurement.viewReports')}
                </Link>
            }
        >
            <KpiGrid
                items={[
                    {
                        label: t('pages.dashboard.procurement.openPos'),
                        value: data.open_pos,
                        icon: ClipboardList,
                        href: route('admin.procurement.reports', { tab: 'open-pos' }),
                    },
                    {
                        label: t('pages.dashboard.procurement.pendingApprovals'),
                        value: data.pending_approvals,
                        icon: AlertTriangle,
                        href: route('admin.procurement.reports', { tab: 'pending-approvals' }),
                    },
                    {
                        label: t('pages.dashboard.procurement.pendingReceipts'),
                        value: data.pending_receipts,
                        icon: Package,
                        href: route('admin.procurement.reports', { tab: 'grns' }),
                    },
                    {
                        label: t('pages.dashboard.procurement.pendingInvoices'),
                        value: data.pending_invoices,
                        icon: FileText,
                        href: route('admin.procurement.reports', { tab: 'invoices' }),
                    },
                    {
                        label: t('pages.dashboard.procurement.outstandingPayables'),
                        value: data.outstanding_payables,
                        icon: Truck,
                        format: fmtMoney,
                        href: route('admin.procurement.reports', { tab: 'balances' }),
                    },
                    {
                        label: t('pages.dashboard.procurement.monthlyPurchases'),
                        value: data.monthly_purchases,
                        icon: Tag,
                        format: fmtMoney,
                    },
                    {
                        label: t('pages.dashboard.procurement.openReturns'),
                        value: data.open_returns,
                        icon: ArrowLeftRight,
                        href: route('admin.procurement.reports', { tab: 'returns' }),
                    },
                ]}
            />
            {data.top_suppliers?.length > 0 ? (
                <div className="mt-4 rounded-lg border bg-card p-4">
                    <h3 className="mb-3 text-sm font-medium text-rp-text-secondary">
                        {t('pages.dashboard.procurement.topSuppliers')}
                    </h3>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="py-2">{t('pages.dashboard.procurement.supplierColumn')}</th>
                                <th>{t('pages.dashboard.procurement.balanceColumn')}</th>
                                <th>{t('pages.dashboard.procurement.onTimeColumn')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.top_suppliers.map((s) => (
                                <tr key={s.id} className="border-b">
                                    <td className="py-2">
                                        <Link
                                            href={route('admin.suppliers.show', s.id)}
                                            className="text-teal-600 hover:underline"
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
            ) : null}
        </WidgetShell>
    );
}

export function FinanceOverviewWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell title={t('pages.dashboard.widgets.finance')}>
            <KpiGrid
                items={[
                    {
                        label: t('pages.dashboard.finance.unpostedJournals'),
                        value: data.unposted_journals,
                        icon: FileText,
                        href: data.unposted_href,
                        warn: data.unposted_journals > 0,
                    },
                    {
                        label: t('pages.dashboard.finance.bankUnmatched'),
                        value: data.bank_unmatched,
                        icon: Landmark,
                        href: data.reconciliation_href,
                        warn: data.bank_unmatched > 0,
                    },
                    {
                        label: t('pages.dashboard.finance.arAging'),
                        value: data.ar_aging_total,
                        icon: Receipt,
                        format: fmtMoney,
                        href: data.ar_aging_href,
                    },
                    {
                        label: t('pages.dashboard.finance.apAging'),
                        value: data.ap_aging_total,
                        icon: Scale,
                        format: fmtMoney,
                        href: data.ap_aging_href,
                    },
                ]}
            />
        </WidgetShell>
    );
}

export function OperationsOverviewWidget({ data }) {
    const { t } = useTranslation();

    return (
        <WidgetShell title={t('pages.dashboard.widgets.operations')}>
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
        <WidgetShell title={t('pages.dashboard.widgets.quickActions')}>
            <div className="rp-card">
                <div className="grid gap-2.5 sm:grid-cols-2">
                    {actions.map((action) => {
                        const Icon = ACTION_ICONS[action.id] ?? LayoutDashboard;

                        return (
                            <Link key={action.id} href={action.href} className="rp-quick-action">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
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
            </div>
        </WidgetShell>
    );
}
