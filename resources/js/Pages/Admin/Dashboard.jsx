import PermissionsByGroupChart from '@/Components/charts/PermissionsByGroupChart';
import UserGrowthChart from '@/Components/charts/UserGrowthChart';
import UserStatusChart from '@/Components/charts/UserStatusChart';
import UsersByRoleChart from '@/Components/charts/UsersByRoleChart';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    KeyRound,
    LayoutDashboard,
    Shield,
    Users,
} from 'lucide-react';

export default function Dashboard({ stats, charts }) {
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
            value: stats.users,
            sub: `${stats.active_users} active · ${stats.inactive_users} inactive`,
            icon: Users,
            tone: 'teal',
        },
        {
            label: 'Roles',
            value: stats.roles,
            sub: 'Access profiles',
            icon: Shield,
            tone: 'amber',
        },
        {
            label: 'Permissions',
            value: stats.permissions,
            sub: 'Granular controls',
            icon: KeyRound,
            tone: 'violet',
        },
    ];

    const quickActions = [
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
        {
            label: 'Dashboard',
            desc: 'Overview & metrics',
            href: route('admin.dashboard'),
            icon: LayoutDashboard,
            iconClass: 'bg-sand-100 text-ink-500 dark:bg-ink-700 dark:text-sand-300',
        },
    ];

    const toneMap = {
        teal: { card: 'before:bg-teal-500', icon: 'rp-kpi-icon-teal' },
        amber: { card: 'before:bg-amber-500', icon: 'rp-kpi-icon-amber' },
        violet: { card: 'before:bg-violet-500', icon: 'rp-kpi-icon-violet' },
    };

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="font-display text-[28px] font-normal text-rp-text">
                        {greeting}, Admin. ☀️
                    </h1>
                    <p className="rp-page-desc">
                        Here&apos;s your system overview for today.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <div className="rp-pill-surface flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-rp-text-muted" />
                        {today}
                    </div>
                </div>
            </div>

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

            <div className="mb-6 grid gap-5 lg:grid-cols-3">
                <UserGrowthChart data={charts.user_growth} />
                <UserStatusChart data={charts.user_status} />
            </div>

            <div className="mb-6 grid gap-5 lg:grid-cols-2">
                <UsersByRoleChart data={charts.users_by_role} />
                <PermissionsByGroupChart data={charts.permissions_by_group} />
            </div>

            <div className="rp-card">
                <h2 className="rp-section-title mb-4">Quick Actions</h2>
                <div className="grid gap-2.5 sm:grid-cols-2">
                    {quickActions.map((action) => {
                        const Icon = action.icon;

                        return (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="rp-quick-action"
                            >
                                <div
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] ${action.iconClass}`}
                                >
                                    <Icon className="h-[17px] w-[17px]" />
                                </div>
                                <div>
                                    <div className="rp-quick-action-title">
                                        {action.label}
                                    </div>
                                    <div className="rp-quick-action-desc">
                                        {action.desc}
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </AdminLayout>
    );
}
