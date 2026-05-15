import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    KeyRound,
    LayoutDashboard,
    Shield,
    Users,
} from 'lucide-react';

export default function Dashboard({ stats }) {
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
            sub: 'Staff accounts',
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
            bg: 'bg-teal-100',
            color: 'text-teal-500',
        },
        {
            label: 'Manage Roles',
            desc: 'Role definitions',
            href: route('admin.roles.index'),
            icon: Shield,
            bg: 'bg-violet-100',
            color: 'text-violet-500',
        },
        {
            label: 'Permissions',
            desc: 'System capabilities',
            href: route('admin.permissions.index'),
            icon: KeyRound,
            bg: 'bg-amber-100',
            color: 'text-amber-500',
        },
        {
            label: 'Dashboard',
            desc: 'Overview & metrics',
            href: route('admin.dashboard'),
            icon: LayoutDashboard,
            bg: 'bg-sand-100',
            color: 'text-ink-500',
        },
    ];

    const toneMap = {
        teal: {
            card: 'before:bg-teal-500',
            icon: 'bg-teal-100 text-teal-500',
        },
        amber: {
            card: 'before:bg-amber-500',
            icon: 'bg-amber-100 text-amber-500',
        },
        violet: {
            card: 'before:bg-violet-500',
            icon: 'bg-violet-100 text-violet-500',
        },
    };

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="font-display text-[28px] font-normal text-ink-900">
                        {greeting}, Admin. ☀️
                    </h1>
                    <p className="mt-0.5 text-[13px] text-ink-500">
                        Here&apos;s your system overview for today.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5 rounded-lg border border-sand-200 bg-white px-3.5 py-2 text-[13px] font-medium text-ink-700">
                        <Calendar className="h-3.5 w-3.5 text-ink-300" />
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
                                <div
                                    className={`flex h-[38px] w-[38px] items-center justify-center rounded-[10px] ${tone.icon}`}
                                >
                                    <Icon className="h-[18px] w-[18px]" />
                                </div>
                            </div>
                            <span className="font-display block text-[32px] leading-none text-ink-900">
                                {kpi.value}
                            </span>
                            <div className="mt-1 text-xs font-medium tracking-wide text-ink-500 uppercase">
                                {kpi.label}
                            </div>
                            <div className="mt-2.5 border-t border-sand-100 pt-2.5 text-xs text-ink-300">
                                {kpi.sub}
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="rp-card">
                <h2 className="mb-4 text-[15px] font-semibold text-ink-900">
                    Quick Actions
                </h2>
                <div className="grid gap-2.5 sm:grid-cols-2">
                    {quickActions.map((action) => {
                        const Icon = action.icon;

                        return (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="flex items-center gap-2.5 rounded-xl border-[1.5px] border-sand-200 bg-sand-50 p-3.5 transition hover:-translate-y-px hover:border-teal-400 hover:bg-teal-100"
                            >
                                <div
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] ${action.bg}`}
                                >
                                    <Icon
                                        className={`h-[17px] w-[17px] ${action.color}`}
                                    />
                                </div>
                                <div>
                                    <div className="text-[12.5px] font-semibold text-ink-700">
                                        {action.label}
                                    </div>
                                    <div className="text-[11px] text-ink-300">
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
