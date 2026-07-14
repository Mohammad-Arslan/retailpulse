import AdminTopbar from '@/Components/admin/AdminTopbar';
import { ImportJobsProvider } from '@/Components/import-export/ImportJobsTray';
import CommandPalette from '@/Components/admin/CommandPalette';
import SidebarSearch from '@/Components/admin/SidebarSearch';
import BrandIcon from '@/Components/brand/BrandIcon';
import Breadcrumbs from '@/Components/common/Breadcrumbs';
import ScrollArea from '@/Components/common/ScrollArea';
import { withNavIcons } from '@/config/adminNav';
import { isAdminNavItemActive } from '@/lib/adminNav';
import { useCommandPalette } from '@/Hooks/useCommandPalette';
import { useSidebarCollapsed } from '@/Hooks/useSidebarCollapsed';
import { useTheme } from '@/Hooks/useTheme';
import { getInitials } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import { useCan } from '@/Hooks/useCan';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const SIDEBAR_SCROLL_KEY = 'rp-admin-sidebar-scroll';

function SidebarNav({ collapsed, onNavigate }) {
    const { t } = useTranslation();
    const rawNavigation = usePage().props.navigation ?? [];
    const sections = useMemo(() => withNavIcons(rawNavigation), [rawNavigation]);

    return (
        <>
            {sections.map((section) => {
                const items = section.items ?? [];

                if (items.length === 0) {
                    return null;
                }

                return (
                    <div key={section.id ?? section.labelKey} className="mb-1 px-2">
                        {!collapsed && (
                            <span className="block px-3 py-2.5 text-[10px] font-bold tracking-widest text-rp-text-muted uppercase dark:text-ink-500">
                                {t(`nav.${section.labelKey}`)}
                            </span>
                        )}
                        {items.map((item) => {
                            const Icon = item.icon;
                            const active = isAdminNavItemActive(item, items);

                            return (
                                <Link
                                    key={item.id ?? item.href}
                                    href={route(item.href)}
                                    onClick={onNavigate}
                                    title={
                                        collapsed ? t(`nav.${item.labelKey}`) : undefined
                                    }
                                    className={cn(
                                        'rp-sidebar-nav-item',
                                        collapsed && 'justify-center px-2',
                                        active && 'rp-sidebar-nav-item--active',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rp-surface-inset dark:bg-white/6',
                                            active && 'bg-white/20',
                                        )}
                                    >
                                        <Icon
                                            className={cn(
                                                'h-4 w-4 text-rp-text-secondary dark:text-ink-300',
                                                active && 'text-white',
                                            )}
                                        />
                                    </span>
                                    {!collapsed && (
                                        <span
                                            className={cn(
                                                'flex-1 text-[13px] font-medium text-rp-text-secondary dark:text-ink-300',
                                                active && 'font-semibold text-white',
                                            )}
                                        >
                                            {t(`nav.${item.labelKey}`)}
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

export default function AdminLayout({ children, fullHeight = false, posMode = false, hideTopbar = false }) {
    const user = usePage().props.auth.user;
    const roles = usePage().props.auth.roles ?? [];
    const homeRouteName = usePage().props.home?.route;
    const can = useCan();
    const { t } = useTranslation();

    const homeRoute =
        homeRouteName && homeRouteName !== 'login'
            ? homeRouteName
            : can('dashboard.view') || can('admin.dashboard.view')
              ? 'admin.dashboard'
              : 'admin.pos.index';

    const [mobileOpen, setMobileOpen] = useState(false);
    const { collapsed, toggleCollapsed } = useSidebarCollapsed();
    const { isDark, toggleTheme } = useTheme();
    const { open: paletteOpen, openPalette, closePalette } = useCommandPalette();
    const navScrollRef = useRef(null);
    const pageUrl = usePage().url;

    // Inertia remounts the layout on navigation, which resets scrollTop.
    // Restore the last position, then ensure the active item is visible.
    useLayoutEffect(() => {
        const scroller = navScrollRef.current;
        if (!scroller) return;

        const saved = sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
        if (saved !== null) {
            scroller.scrollTop = Number(saved) || 0;
        }

        const active = scroller.querySelector('.rp-sidebar-nav-item--active');
        if (active) {
            const itemRect = active.getBoundingClientRect();
            const scrollerRect = scroller.getBoundingClientRect();
            if (itemRect.top < scrollerRect.top) {
                scroller.scrollTop -= scrollerRect.top - itemRect.top;
            } else if (itemRect.bottom > scrollerRect.bottom) {
                scroller.scrollTop += itemRect.bottom - scrollerRect.bottom;
            }
        }

        sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(scroller.scrollTop));
    }, [pageUrl, collapsed]);

    useEffect(() => {
        const scroller = navScrollRef.current;
        if (!scroller) return;

        const onScroll = () => {
            sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(scroller.scrollTop));
        };

        scroller.addEventListener('scroll', onScroll, { passive: true });
        return () => scroller.removeEventListener('scroll', onScroll);
    }, []);

    const roleLabel = roles[0] ?? 'Administrator';

    const sidebarContent = (
        <>
            <div
                className={cn(
                    'border-b border-rp-border dark:border-ink-800',
                    collapsed ? 'px-3 py-5' : 'px-5 py-6',
                )}
            >
                <Link
                    href={route(homeRoute)}
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
                            <span className="font-display block text-[17px] leading-tight text-rp-text dark:text-white">
                                Retail<span className="text-teal-500 dark:text-teal-300">Pulse</span>
                            </span>
                        </div>
                    )}
                </Link>
            </div>

            <SidebarSearch collapsed={collapsed} onOpen={openPalette} />

            <ScrollArea
                ref={navScrollRef}
                as="nav"
                className="flex-1 overflow-y-auto overflow-x-hidden py-2"
            >
                <SidebarNav
                    collapsed={collapsed}
                    onNavigate={() => setMobileOpen(false)}
                />
            </ScrollArea>

            <div className="border-t border-rp-border p-3 dark:border-ink-800">
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
                                <span className="block truncate text-[13px] font-semibold text-rp-text dark:text-white">
                                    {user?.name}
                                </span>
                                <span className="block truncate text-[11px] text-rp-text-muted dark:text-ink-300">
                                    {roleLabel}
                                </span>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-lg p-1.5 text-rp-text-muted transition hover:bg-rp-surface-inset hover:text-rp-text focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none dark:text-ink-500 dark:hover:bg-ink-800 dark:hover:text-white"
                                aria-label={t('common.logOut')}
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
        <ImportJobsProvider>
        <div className="min-h-screen bg-rp-page font-sans">
            <CommandPalette open={paletteOpen} onClose={closePalette} />

            {mobileOpen && (
                <button
                    type="button"
                    className="fixed inset-0 z-40 bg-ink-900/50 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-label={t('common.closeMenu')}
                />
            )}

            <aside
                className={cn(
                    'fixed top-0 left-0 z-50 flex h-full flex-col border-r border-rp-border bg-rp-surface transition-[width,transform] duration-200 dark:border-ink-800 dark:bg-ink-900 lg:translate-x-0',
                    sidebarWidth,
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <button
                    type="button"
                    className="absolute top-4 right-4 rounded-lg p-1 text-rp-text-muted focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none dark:text-ink-300 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-label={t('common.closeMenu')}
                >
                    <X className="h-5 w-5" />
                </button>
                {sidebarContent}
            </aside>

            <div
                className={cn(
                    'flex flex-col transition-[margin] duration-200',
                    fullHeight ? 'h-screen min-h-0' : 'min-h-screen',
                    mainOffset,
                )}
            >
                {!hideTopbar && (
                    <AdminTopbar
                        collapsed={collapsed}
                        isDark={isDark}
                        onToggleCollapse={toggleCollapsed}
                        onOpenSearch={openPalette}
                        onToggleTheme={toggleTheme}
                        onOpenMobileMenu={() => setMobileOpen(true)}
                        posMode={posMode}
                    />
                )}

                <main
                    className={cn(
                        'flex min-h-0 flex-1 flex-col',
                        fullHeight ? 'overflow-hidden' : 'px-4 py-7 sm:px-8',
                    )}
                >
                    {!fullHeight && <Breadcrumbs />}
                    {children}
                </main>
            </div>
        </div>
        </ImportJobsProvider>
    );
}
