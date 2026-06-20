import DashboardBranchesSnapshot from '@/Components/admin/DashboardBranchesSnapshot';
import DashboardRealtimeActivity from '@/Components/admin/DashboardRealtimeActivity';
import PermissionsByGroupChart from '@/Components/charts/PermissionsByGroupChart';
import StockMovementTrendChart from '@/Components/charts/StockMovementTrendChart';
import UserGrowthChart from '@/Components/charts/UserGrowthChart';
import UserStatusChart from '@/Components/charts/UserStatusChart';
import UsersByRoleChart from '@/Components/charts/UsersByRoleChart';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Box,
    Building2,
    Calendar,
    KeyRound,
    LayoutDashboard,
    LogIn,
    Package,
    Shield,
    Tag,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';

const fmtInt = (n) =>
    typeof n === 'number' ? new Intl.NumberFormat(undefined).format(n) : '—';

const fmtMoney = (n) =>
    typeof n === 'number'
        ? new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
        : '—';

function Dashboard({ stats, charts, superAdmin, salesKpis, revenueCharts, canViewProfit, widgets }) {
    const { auth } = usePage().props;
    const showSales = canViewProfit && widgets?.includes('sales');
    const showRevenue = canViewProfit && widgets?.includes('revenue');
    const showRbac = widgets?.includes('rbac') !== false;
    const rawName = auth?.user?.name?.trim() ?? '';
    const firstName = rawName ? rawName.split(/\s+/)[0] : 'there';

    const today = new Date().toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

    const hour = new Date().getHours();
    const greeting =
        hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    const kpis = [
        {
            label: 'Users',
            value: fmtInt(stats.users),
            sub: `${fmtInt(stats.active_users)} active · ${fmtInt(stats.inactive_users)} inactive`,
            icon: Users,
            tone: 'teal',
        },
        {
            label: 'Roles',
            value: fmtInt(stats.roles),
            sub: 'Access profiles',
            icon: Shield,
            tone: 'amber',
        },
        {
            label: 'Permissions',
            value: fmtInt(stats.permissions),
            sub: 'Granular controls',
            icon: KeyRound,
            tone: 'violet',
        },
    ];

    const opsQuickLinks = superAdmin
        ? [
              {
                  label: 'Branches',
                  desc: 'Locations & defaults',
                  href: route('admin.branches.index'),
                  icon: Building2,
                  iconClass:
                      'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
              },
              {
                  label: 'Products',
                  desc: 'Catalog & variants',
                  href: route('admin.products.index'),
                  icon: Package,
                  iconClass:
                      'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
              },
              {
                  label: 'Inventory',
                  desc: 'Stock levels & adjustments',
                  href: route('admin.inventory.index'),
                  icon: Warehouse,
                  iconClass:
                      'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
              },
              {
                  label: 'Stock transfers',
                  desc: 'Inter-warehouse transfers',
                  href: route('admin.stock-transfers.index'),
                  icon: Truck,
                  iconClass:
                      'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
              },
          ]
        : [];

    const quickActions = [
        ...opsQuickLinks,
        {
            label: 'Manage Users',
            desc: 'Team members & access',
            href: route('admin.users.index'),
            icon: Users,
            iconClass: 'bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300',
        },
        {
            label: 'Manage Roles',
            desc: 'Role definitions',
            href: route('admin.roles.index'),
            icon: Shield,
            iconClass: 'bg-violet-100 text-violet-500 dark:bg-violet-500/20 dark:text-violet-300',
        },
        {
            label: 'Permissions',
            desc: 'System capabilities',
            href: route('admin.permissions.index'),
            icon: KeyRound,
            iconClass: 'bg-amber-100 text-amber-500 dark:bg-amber-500/20 dark:text-amber-400',
        },
        ...(!superAdmin
            ? [
                  {
                      label: 'Dashboard',
                      desc: 'Overview & metrics',
                      href: route('admin.dashboard'),
                      icon: LayoutDashboard,
                      iconClass:
                          'bg-sand-100 text-ink-500 dark:bg-ink-700 dark:text-ink-300',
                  },
              ]
            : []),
    ];

    const toneMap = {
        teal: { card: 'before:bg-teal-500', icon: 'rp-kpi-icon-teal' },
        amber: { card: 'before:bg-amber-500', icon: 'rp-kpi-icon-amber' },
        violet: { card: 'before:bg-violet-500', icon: 'rp-kpi-icon-violet' },
        emerald: { card: 'before:bg-emerald-500', icon: 'rp-kpi-icon-teal' },
        sky: { card: 'before:bg-sky-500', icon: 'rp-kpi-icon-teal' },
        rose: { card: 'before:bg-rose-500', icon: 'rp-kpi-icon-amber' },
        sand: { card: 'before:bg-stone-400', icon: 'rp-kpi-icon-violet' },
    };

    const superKpis = superAdmin
        ? [
              {
                  label: 'Branches',
                  value: `${fmtInt(superAdmin.branches_active)} / ${fmtInt(superAdmin.branches_total)}`,
                  sub: 'Active locations · total recorded',
                  icon: Building2,
                  tone: 'emerald',
              },
              {
                  label: 'Warehouses',
                  value: fmtInt(superAdmin.warehouses),
                  sub: 'Across all branches',
                  icon: Warehouse,
                  tone: 'sky',
              },
              {
                  label: 'Catalog',
                  value: fmtInt(superAdmin.products_active),
                  sub: `${fmtInt(superAdmin.product_variants)} SKUs · ${fmtInt(superAdmin.categories)} categories · ${fmtInt(superAdmin.brands)} brands`,
                  icon: Box,
                  tone: 'sand',
              },
              {
                  label: 'Units on hand',
                  value: fmtInt(superAdmin.units_on_hand),
                  sub: 'Sum of quantity on hand',
                  icon: Package,
                  tone: 'teal',
              },
              {
                  label: 'Low-stock lines',
                  value: fmtInt(superAdmin.low_stock_lines),
                  sub:
                      superAdmin.low_stock_lines > 0
                          ? 'At or below reorder point'
                          : 'No alerts with current reorder points',
                  icon: AlertTriangle,
                  tone: 'rose',
              },
              {
                  label: 'Stock movements',
                  value: fmtInt(superAdmin.stock_movements_today),
                  sub: 'Ledger events today',
                  icon: ArrowLeftRight,
                  tone: 'amber',
              },
              {
                  label: 'Transfers',
                  value: `${fmtInt(superAdmin.transfers_draft)} / ${fmtInt(superAdmin.transfers_in_transit)}`,
                  sub: 'Draft · in transit',
                  icon: Truck,
                  tone: 'violet',
              },
              {
                  label: 'Admin sign-ins',
                  value: fmtInt(superAdmin.admin_logins_24h),
                  sub: 'Successful logins (24h)',
                  icon: LogIn,
                  tone: 'violet',
              },
          ]
        : [];

    return (
        <>
            <Head title="Dashboard" />

            <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="font-display text-[28px] font-normal text-rp-text">
                        {greeting}, {firstName}.
                    </h1>
                    <p className="rp-page-desc">
                        {superAdmin
                            ? 'Cross-branch snapshot of people, catalog, and inventory health.'
                            : "Here's your system overview for today."}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                    {superAdmin ? (
                        <span className="rp-pill-surface border border-emerald-500/25 bg-emerald-500/10 text-xs font-semibold text-emerald-800 dark:text-emerald-200">
                            Super admin
                        </span>
                    ) : null}
                    <div className="rp-pill-surface flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-rp-text-muted" />
                        {today}
                    </div>
                </div>
            </div>

            {showSales && salesKpis ? (
                <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: "Today's sales", value: salesKpis.todays_sales, icon: Package, format: fmtMoney },
                        { label: 'Gross profit', value: salesKpis.gross_profit, icon: Tag, format: fmtMoney },
                        { label: 'ATV', value: salesKpis.average_transaction_value, icon: LayoutDashboard, format: fmtMoney },
                        { label: 'Layaway balances', value: salesKpis.pending_approvals, icon: AlertTriangle, format: fmtInt },
                    ].map((kpi) => {
                        const Icon = kpi.icon;
                        const tone = toneMap.teal;
                        const format = kpi.format ?? fmtInt;
                        return (
                            <div key={kpi.label} className={`rp-kpi-card ${tone.card}`}>
                                <div className="mb-3.5">
                                    <div className={tone.icon}>
                                        <Icon className="h-[18px] w-[18px]" />
                                    </div>
                                </div>
                                <span className="rp-kpi-value">{format(kpi.value)}</span>
                                <div className="rp-kpi-label">{kpi.label}</div>
                            </div>
                        );
                    })}
                </div>
            ) : null}

            {showRbac ? (
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {kpis.map((kpi) => {
                    const Icon = kpi.icon;
                    const tone = toneMap[kpi.tone];

                    return (
                        <div
                            key={kpi.label}
                            className={`rp-kpi-card before:absolute before:top-0 before:right-0 before:h-20 before:w-20 before:rounded-bl-[80px] before:opacity-[0.06] before:content-[''] ${tone.card}`}
                        >
                            <div className="mb-3.5 flex items-center justify-between">
                                <div className={tone.icon}>
                                    <Icon className="h-[18px] w-[18px]" />
                                </div>
                            </div>
                            <span className="rp-kpi-value">{kpi.value}</span>
                            <div className="rp-kpi-label">{kpi.label}</div>
                            <div className="rp-kpi-sub">{kpi.sub}</div>
                        </div>
                    );
                })}
            </div>
            ) : null}

            {superAdmin ? (
                <div className="mb-2 flex items-end justify-between gap-3">
                    <h2 className="rp-section-title mb-0 inline-flex items-center gap-2">
                        <Tag className="h-4 w-4 text-rp-text-muted" />
                        Operations overview
                    </h2>
                </div>
            ) : null}

            {superAdmin ? (
                <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {superKpis.map((kpi) => {
                        const Icon = kpi.icon;
                        const tone = toneMap[kpi.tone];
                        const warn =
                            kpi.label === 'Low-stock lines' && superAdmin.low_stock_lines > 0;

                        return (
                            <div
                                key={kpi.label}
                                className={`rp-kpi-card before:absolute before:top-0 before:right-0 before:h-20 before:w-20 before:rounded-bl-[80px] before:opacity-[0.06] before:content-[''] ${tone.card} ${
                                    warn
                                        ? 'ring-1 ring-amber-400/40 dark:ring-amber-500/25'
                                        : ''
                                }`}
                            >
                                <div className="mb-3.5 flex items-center justify-between">
                                    <div className={tone.icon}>
                                        <Icon className="h-[18px] w-[18px]" />
                                    </div>
                                </div>
                                <span className="rp-kpi-value">{kpi.value}</span>
                                <div className="rp-kpi-label">{kpi.label}</div>
                                <div className="rp-kpi-sub">{kpi.sub}</div>
                            </div>
                        );
                    })}
                </div>
            ) : null}

            {superAdmin ? (
                <div className="mb-6 grid gap-5 lg:grid-cols-2">
                    <StockMovementTrendChart data={superAdmin.stock_movement_trend} />
                    <DashboardBranchesSnapshot branches={superAdmin.branches_preview} />
                </div>
            ) : null}

            <div className="mb-6 grid gap-5 lg:grid-cols-3">
                <UserGrowthChart data={charts.user_growth} />
                <UserStatusChart data={charts.user_status} />
                <DashboardRealtimeActivity />
            </div>

            <div className="mb-6 grid gap-5 lg:grid-cols-2">
                <UsersByRoleChart data={charts.users_by_role} />
                <PermissionsByGroupChart data={charts.permissions_by_group} />
            </div>

            <div className="rp-card">
                <h2 className="rp-section-title mb-4">
                    {superAdmin ? 'Shortcuts & administration' : 'Quick Actions'}
                </h2>
                <div className="grid gap-2.5 sm:grid-cols-2">
                    {quickActions.map((action) => {
                        const Icon = action.icon;

                        return (
                            <Link
                                key={`${action.href}-${action.label}`}
                                href={action.href}
                                className="rp-quick-action"
                            >
                                <div
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] ${action.iconClass}`}
                                >
                                    <Icon className="h-[17px] w-[17px]" />
                                </div>
                                <div>
                                    <div className="rp-quick-action-title">{action.label}</div>
                                    <div className="rp-quick-action-desc">{action.desc}</div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

export default withAdminLayout(Dashboard);
