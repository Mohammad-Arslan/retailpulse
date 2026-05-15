import NotificationsMenu from '@/Components/admin/NotificationsMenu';
import { cn } from '@/lib/utils';
import {
    Menu,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    Sun,
} from 'lucide-react';

export default function AdminTopbar({
    collapsed,
    isDark,
    onToggleSidebar,
    onToggleCollapse,
    onOpenSearch,
    onToggleTheme,
    onOpenMobileMenu,
}) {
    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex h-14 items-center gap-3 border-b px-4',
                'border-sand-200 bg-white/95 backdrop-blur dark:border-ink-800 dark:bg-ink-900/95',
            )}
        >
            <button
                type="button"
                onClick={onOpenMobileMenu}
                className="flex h-9 w-9 items-center justify-center rounded-lg border border-sand-200 bg-white lg:hidden dark:border-ink-700 dark:bg-ink-800"
                aria-label="Open menu"
            >
                <Menu className="h-4 w-4 text-ink-700 dark:text-sand-300" />
            </button>

            <button
                type="button"
                onClick={onToggleCollapse}
                className="hidden h-9 w-9 items-center justify-center rounded-lg border border-sand-200 bg-white transition hover:border-teal-400 lg:flex dark:border-ink-700 dark:bg-ink-800"
                title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            >
                {collapsed ? (
                    <PanelLeftOpen className="h-4 w-4 text-ink-700 dark:text-sand-300" />
                ) : (
                    <PanelLeftClose className="h-4 w-4 text-ink-700 dark:text-sand-300" />
                )}
            </button>

            <button
                type="button"
                onClick={onOpenSearch}
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-sand-200 bg-sand-50 px-3 py-2 text-left transition',
                    'hover:border-teal-400 hover:bg-white sm:max-w-md lg:max-w-xl',
                    'dark:border-ink-700 dark:bg-ink-800 dark:hover:border-teal-400 dark:hover:bg-ink-700',
                )}
            >
                <Search className="h-4 w-4 shrink-0 text-ink-300" />
                <span className="flex-1 truncate text-sm text-ink-300">
                    Search pages, users, actions...
                </span>
                <kbd className="hidden rounded border border-sand-200 bg-white px-1.5 py-0.5 text-[10px] text-ink-300 sm:inline dark:border-ink-600 dark:bg-ink-900">
                    ⌘K
                </kbd>
            </button>

            <div className="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    onClick={onToggleTheme}
                    className={cn(
                        'flex h-9 w-9 items-center justify-center rounded-lg border border-sand-200 bg-white transition',
                        'hover:border-teal-400 dark:border-ink-700 dark:bg-ink-800 dark:hover:border-teal-400',
                    )}
                    title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                >
                    {isDark ? (
                        <Sun className="h-4 w-4 text-amber-500" />
                    ) : (
                        <Moon className="h-4 w-4 text-ink-700" />
                    )}
                </button>

                <NotificationsMenu />
            </div>
        </header>
    );
}
