import AdminTopbar from '@/Components/admin/AdminTopbar';
import CommandPalette from '@/Components/admin/CommandPalette';
import SidebarSearch from '@/Components/admin/SidebarSearch';
import BrandIcon from '@/Components/brand/BrandIcon';
import FlashAlert from '@/Components/common/FlashAlert';
import { ADMIN_NAV_SECTIONS } from '@/config/adminNav';
import { useCommandPalette } from '@/Hooks/useCommandPalette';
import { useSidebarCollapsed } from '@/Hooks/useSidebarCollapsed';
import { useTheme } from '@/Hooks/useTheme';
import { getInitials } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import { useCan } from '@/Hooks/useCan';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, X } from 'lucide-react';
import { useState } from 'react';

function SidebarNav({ collapsed, onNavigate }) {
    const can = useCan();

    return (
        <>
            {ADMIN_NAV_SECTIONS.map((section) => {
                const items = section.items.filter((item) => can(item.permission));

                if (items.length === 0) {
                    return null;
                }

                return (
                    <div key={section.label} className="mb-1 px-2">
                        {!collapsed && (
                            <span className="block px-3 py-2.5 text-[10px] font-bold tracking-widest text-ink-500 uppercase">
                                {section.label}
                            </span>
                        )}
                        {items.map((item) => {
                            const Icon = item.icon;
                            const active = route().current(item.routeName);

                            return (
                                <Link
                                    key={item.href}
                                    href={route(item.href)}
                                    onClick={onNavigate}
                                    title={collapsed ? item.label : undefined}
                                    className={cn(
                                        'rp-sidebar-nav-item',
                                        collapsed && 'justify-center px-2',
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
                                    {!collapsed && (
                                        <span
                                            className={cn(
                                                'flex-1 text-[13px] font-medium text-sand-300',
                                                active && 'font-semibold text-white',
                                            )}
                                        >
                                            {item.label}
                                        </span>
                                    )}
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
    const { collapsed, toggleCollapsed } = useSidebarCollapsed();
    const { isDark, toggleTheme } = useTheme();
    const { open: paletteOpen, openPalette, closePalette } = useCommandPalette();

    const roleLabel = roles[0] ?? 'Administrator';

    const sidebarContent = (
        <>
            <div
                className={cn(
                    'border-b border-ink-800',
                    collapsed ? 'px-3 py-5' : 'px-5 py-6',
                )}
            >
                <Link
                    href={route('admin.dashboard')}
                    className={cn(
                        'flex items-center gap-3',
                        collapsed && 'justify-center',
                    )}
                    onClick={() => setMobileOpen(false)}
                    title="RetailPulse"
                >
                    <BrandIcon
                        className={cn(collapsed ? 'h-9 w-9' : 'h-9 w-9')}
                        iconClassName="h-[18px] w-[18px]"
                    />
                    {!collapsed && (
                        <div className="min-w-0">
                            <span className="font-display block text-[17px] leading-tight text-white">
                                RetailPulse
                            </span>
                            <span className="text-[10px] tracking-widest text-sand-300 uppercase">
                                v2.0 · Enterprise
                            </span>
                        </div>
                    )}
                </Link>
            </div>

            <SidebarSearch collapsed={collapsed} onOpen={openPalette} />

            <nav className="flex-1 overflow-y-auto overflow-x-hidden py-2">
                <SidebarNav
                    collapsed={collapsed}
                    onNavigate={() => setMobileOpen(false)}
                />
            </nav>

            <div className="border-t border-ink-800 p-3">
                <div
                    className={cn(
                        'flex items-center gap-2.5 rounded-[10px] p-2',
                        collapsed && 'justify-center',
                    )}
                >
                    <div
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-linear-to-br from-teal-500 to-teal-300 text-[13px] font-bold text-white"
                        title={collapsed ? user?.name : undefined}
                    >
                        {getInitials(user?.name)}
                    </div>
                    {!collapsed && (
                        <>
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
                        </>
                    )}
                </div>
            </div>
        </>
    );

    const sidebarWidth = collapsed
        ? 'w-(--width-sidebar-collapsed)'
        : 'w-(--width-sidebar)';

    const mainOffset = collapsed
        ? 'lg:ml-(--width-sidebar-collapsed)'
        : 'lg:ml-(--width-sidebar)';

    return (
        <div className="min-h-screen bg-rp-page font-sans">
            <CommandPalette open={paletteOpen} onClose={closePalette} />

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
                    'fixed top-0 left-0 z-50 flex h-full flex-col bg-ink-900 transition-[width,transform] duration-200 lg:translate-x-0',
                    sidebarWidth,
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
                {sidebarContent}
            </aside>

            <div className={cn('flex min-h-screen flex-col transition-[margin] duration-200', mainOffset)}>
                <AdminTopbar
                    collapsed={collapsed}
                    isDark={isDark}
                    onToggleCollapse={toggleCollapsed}
                    onOpenSearch={openPalette}
                    onToggleTheme={toggleTheme}
                    onOpenMobileMenu={() => setMobileOpen(true)}
                />

                <main className="flex-1 px-4 py-7 sm:px-8">
                    <FlashAlert flash={flash} />
                    {children}
                </main>
            </div>
        </div>
    );
}
