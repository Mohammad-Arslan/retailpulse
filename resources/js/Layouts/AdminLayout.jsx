import BrandIcon from '@/Components/brand/BrandIcon';
import FlashAlert from '@/Components/common/FlashAlert';
import { getInitials } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import { useCan } from '@/Hooks/useCan';
import { Link, usePage } from '@inertiajs/react';
import {
    KeyRound,
    LayoutDashboard,
    LogOut,
    Menu,
    Shield,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

const NAV_SECTIONS = [
    {
        label: 'Admin',
        items: [
            {
                label: 'Dashboard',
                href: 'admin.dashboard',
                routeName: 'admin.dashboard',
                permission: 'admin.dashboard.view',
                icon: LayoutDashboard,
            },
            {
                label: 'Users',
                href: 'admin.users.index',
                routeName: 'admin.users.*',
                permission: 'users.view',
                icon: Users,
            },
            {
                label: 'Roles',
                href: 'admin.roles.index',
                routeName: 'admin.roles.*',
                permission: 'roles.view',
                icon: Shield,
            },
            {
                label: 'Permissions',
                href: 'admin.permissions.index',
                routeName: 'admin.permissions.*',
                permission: 'permissions.view',
                icon: KeyRound,
            },
        ],
    },
];

function SidebarNav({ onNavigate }) {
    const can = useCan();

    return (
        <>
            {NAV_SECTIONS.map((section) => {
                const items = section.items.filter((item) => can(item.permission));

                if (items.length === 0) {
                    return null;
                }

                return (
                    <div key={section.label} className="mb-1 px-2">
                        <span className="block px-3 py-2.5 text-[10px] font-bold tracking-widest text-ink-500 uppercase">
                            {section.label}
                        </span>
                        {items.map((item) => {
                            const Icon = item.icon;
                            const active = route().current(item.routeName);

                            return (
                                <Link
                                    key={item.href}
                                    href={route(item.href)}
                                    onClick={onNavigate}
                                    className={cn(
                                        'rp-sidebar-nav-item',
                                        active && 'rp-sidebar-nav-item--active',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/6',
                                            active && 'bg-white/20',
                                        )}
                                    >
                                        <Icon
                                            className={cn(
                                                'h-4 w-4 text-sand-300',
                                                active && 'text-white',
                                            )}
                                        />
                                    </span>
                                    <span
                                        className={cn(
                                            'flex-1 text-[13px] font-medium text-sand-300',
                                            active && 'font-semibold text-white',
                                        )}
                                    >
                                        {item.label}
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                );
            })}
        </>
    );
}

export default function AdminLayout({ children }) {
    const user = usePage().props.auth.user;
    const roles = usePage().props.auth.roles ?? [];
    const flash = usePage().props.flash;
    const [mobileOpen, setMobileOpen] = useState(false);

    const roleLabel = roles[0] ?? 'Administrator';

    const sidebar = (
        <>
            <div className="border-b border-ink-800 px-5 py-6">
                <Link
                    href={route('admin.dashboard')}
                    className="flex items-center gap-3"
                    onClick={() => setMobileOpen(false)}
                >
                    <BrandIcon className="h-9 w-9" iconClassName="h-[18px] w-[18px]" />
                    <div className="min-w-0">
                        <span className="font-display block text-[17px] leading-tight text-white">
                            RetailPulse
                        </span>
                        <span className="text-[10px] tracking-widest text-sand-300 uppercase">
                            v2.0 · Enterprise
                        </span>
                    </div>
                </Link>
            </div>

            <nav className="flex-1 overflow-y-auto py-2">
                <SidebarNav onNavigate={() => setMobileOpen(false)} />
            </nav>

            <div className="border-t border-ink-800 p-4">
                <div className="flex items-center gap-2.5 rounded-[10px] p-2.5">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-gradient-to-br from-teal-500 to-teal-300 text-[13px] font-bold text-white">
                        {getInitials(user?.name)}
                    </div>
                    <div className="min-w-0 flex-1">
                        <span className="block truncate text-[13px] font-semibold text-white">
                            {user?.name}
                        </span>
                        <span className="block truncate text-[11px] text-sand-300">
                            {roleLabel}
                        </span>
                    </div>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-lg p-1.5 text-ink-500 transition hover:bg-ink-800 hover:text-white"
                        title="Log out"
                    >
                        <LogOut className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </>
    );

    return (
        <div className="min-h-screen bg-sand-50 font-sans">
            {mobileOpen && (
                <button
                    type="button"
                    className="fixed inset-0 z-40 bg-ink-900/50 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-label="Close menu"
                />
            )}

            <aside
                className={cn(
                    'fixed top-0 left-0 z-50 flex h-full w-(--width-sidebar) flex-col bg-ink-900 transition-transform duration-200 lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <button
                    type="button"
                    className="absolute top-4 right-4 rounded-lg p-1 text-sand-300 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                >
                    <X className="h-5 w-5" />
                </button>
                {sidebar}
            </aside>

            <div className="lg:ml-(--width-sidebar)">
                <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-sand-200 bg-sand-50/95 px-4 py-3 backdrop-blur lg:hidden">
                    <button
                        type="button"
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-sand-200 bg-white"
                        onClick={() => setMobileOpen(true)}
                    >
                        <Menu className="h-5 w-5 text-ink-700" />
                    </button>
                    <span className="font-display text-lg text-ink-900">RetailPulse</span>
                </header>

                <main className="px-4 py-7 sm:px-8">
                    <FlashAlert flash={flash} />
                    {children}
                </main>
            </div>
        </div>
    );
}
