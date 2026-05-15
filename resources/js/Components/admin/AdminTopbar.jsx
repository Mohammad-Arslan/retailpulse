import BranchSwitcher from '@/Components/admin/BranchSwitcher';
import NotificationsMenu from '@/Components/admin/NotificationsMenu';
import UserMenu from '@/Components/admin/UserMenu';
import { cn } from '@/lib/utils';
import {
    Menu,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    Sun,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

const iconBtn =
    'flex h-9 w-9 items-center justify-center rounded-lg border border-rp-border bg-rp-surface transition hover:border-teal-400';

export default function AdminTopbar({
    collapsed,
    isDark,
    onToggleCollapse,
    onOpenSearch,
    onToggleTheme,
    onOpenMobileMenu,
}) {
    const { t } = useTranslation();

    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-rp-border px-4',
                'bg-rp-surface/95 backdrop-blur',
            )}
        >
            <button
                type="button"
                onClick={onOpenMobileMenu}
                className={cn(iconBtn, 'lg:hidden')}
                aria-label={t('common.openMenu')}
            >
                <Menu className="h-4 w-4 text-rp-text-secondary" />
            </button>

            <button
                type="button"
                onClick={onToggleCollapse}
                className={cn(iconBtn, 'hidden lg:flex')}
                aria-label={
                    collapsed ? t('common.expandSidebar') : t('common.collapseSidebar')
                }
            >
                {collapsed ? (
                    <PanelLeftOpen className="h-4 w-4 text-rp-text-secondary" />
                ) : (
                    <PanelLeftClose className="h-4 w-4 text-rp-text-secondary" />
                )}
            </button>

            <button
                type="button"
                onClick={onOpenSearch}
                aria-label={t('common.commandPalette')}
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-rp-border bg-rp-surface-inset px-3 py-2 text-left transition',
                    'hover:border-teal-400 hover:bg-rp-surface sm:max-w-md lg:max-w-xl',
                )}
            >
                <Search className="h-4 w-4 shrink-0 text-rp-text-muted" />
                <span className="flex-1 truncate text-sm text-rp-text-muted">
                    {t('common.commandPalette')}
                </span>
                <kbd className="hidden rounded border border-rp-border bg-rp-surface px-1.5 py-0.5 text-[10px] text-rp-text-muted sm:inline">
                    ⌘K
                </kbd>
            </button>

            <div className="ml-auto flex items-center gap-2">
                <BranchSwitcher />
                <button
                    type="button"
                    onClick={onToggleTheme}
                    className={iconBtn}
                    aria-label={
                        isDark ? t('common.switchToLight') : t('common.switchToDark')
                    }
                >
                    {isDark ? (
                        <Sun className="h-4 w-4 text-amber-500" />
                    ) : (
                        <Moon className="h-4 w-4 text-rp-text-secondary" />
                    )}
                </button>

                <NotificationsMenu />
                <UserMenu />
            </div>
        </header>
    );
}
