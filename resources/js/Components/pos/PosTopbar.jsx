import BranchSwitcher from '@/Components/admin/BranchSwitcher';
import NotificationsMenu from '@/Components/admin/NotificationsMenu';
import UserMenu from '@/Components/admin/UserMenu';
import { useCan } from '@/Hooks/useCan';
import { useTheme } from '@/Hooks/useTheme';
import { getInitials } from '@/lib/avatar';
import { Link, router, usePage } from '@inertiajs/react';
import { LayoutDashboard, Lock, LogOut, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

function OnlineIndicator() {
    const [online, setOnline] = useState(navigator.onLine);

    useEffect(() => {
        const on = () => setOnline(true);
        const off = () => setOnline(false);
        window.addEventListener('online', on);
        window.addEventListener('offline', off);
        return () => {
            window.removeEventListener('online', on);
            window.removeEventListener('offline', off);
        };
    }, []);

    return (
        <div
            className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-semibold ${
                online
                    ? 'bg-[var(--pos-success-bg)] text-[var(--pos-success)]'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
            }`}
        >
            <span
                className={`h-1.5 w-1.5 rounded-full ${online ? 'bg-[var(--pos-success)]' : 'animate-pulse bg-amber-500'}`}
            />
            {online ? 'Online' : 'Offline'}
        </div>
    );
}

function SessionTimer({ startedAt }) {
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        const interval = setInterval(() => {
            setElapsed(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);
        return () => clearInterval(interval);
    }, [startedAt]);

    const h = Math.floor(elapsed / 3600);
    const m = Math.floor((elapsed % 3600) / 60);
    const s = elapsed % 60;
    const label =
        h > 0
            ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
            : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

    return (
        <span className="text-[11.5px] font-medium text-[var(--pos-text-2)]">
            Session <b className="pos-mono font-semibold text-[var(--pos-text-1)]">{label}</b>
        </span>
    );
}

export function PosTopbar({ cartCount, sessionStart, onLock, title, subtitle }) {
    const { isDark, toggleTheme } = useTheme();
    const { t } = useTranslation();
    const can = useCan();
    const { auth, home, branch } = usePage().props;

    const canExitToErp =
        can('dashboard.view') || can('admin.dashboard.view') || can('admin.access');

    const erpRoute =
        home?.route && home.route !== 'login' && home.route !== 'admin.pos.index'
            ? home.route
            : can('dashboard.view') || can('admin.dashboard.view')
              ? 'admin.dashboard'
              : 'help-support.index';

    const registerLabel = subtitle ?? branch?.active?.name ?? t('pages.pos.registerFallback');
    const primaryRole = Array.isArray(auth?.roles) ? auth.roles[0] : null;
    const roleLabel = String(primaryRole ?? 'Cashier').replaceAll('-', ' ');
    const heading = title ?? t('pages.pos.title');

    return (
        <header className="flex shrink-0 items-center justify-between gap-3 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-3 py-2.5 sm:px-5">
            <div className="flex min-w-0 items-center gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <div className="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-md bg-linear-to-br from-[var(--pos-teal-500)] to-[var(--pos-teal-700)] text-[12px] font-extrabold text-white shadow-[var(--pos-shadow-sm)]">
                        RP
                    </div>
                    <div className="min-w-0">
                        <div className="truncate text-[14.5px] font-bold tracking-tight text-[var(--pos-text-1)]">
                            {heading}
                        </div>
                        <div className="truncate text-[11px] font-medium text-[var(--pos-text-3)]">
                            {registerLabel}
                        </div>
                    </div>
                </div>

                <div className="hidden h-4 w-px bg-[var(--pos-border-strong)] sm:block" />
                <div className="hidden sm:block">
                    <OnlineIndicator />
                </div>
                <div className="hidden h-4 w-px bg-[var(--pos-border-strong)] lg:block" />
                <div className="hidden lg:block">
                    <SessionTimer startedAt={sessionStart} />
                </div>
                {cartCount > 0 ? (
                    <span className="hidden text-[11.5px] font-medium text-[var(--pos-text-2)] xl:inline">
                        Carts open <b className="text-[var(--pos-text-1)]">{cartCount}</b>
                    </span>
                ) : null}
            </div>

            <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <BranchSwitcher className="inline-flex max-w-[9rem] border-[var(--pos-border)] sm:max-w-[11rem]" />
                {typeof onLock === 'function' ? (
                    <button
                        type="button"
                        onClick={onLock}
                        className="flex h-9 items-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] px-2.5 text-[12.5px] font-semibold text-[var(--pos-text-1)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)]"
                    >
                        <Lock className="h-3.5 w-3.5" />
                        <span className="hidden md:inline">{t('pages.pos.lockScreen')}</span>
                    </button>
                ) : null}
                {canExitToErp ? (
                    <button
                        type="button"
                        onClick={() => router.visit(route(erpRoute))}
                        className="flex h-9 items-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] px-2.5 text-[12.5px] font-semibold text-[var(--pos-text-1)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)]"
                    >
                        <LayoutDashboard className="h-3.5 w-3.5" />
                        <span className="hidden lg:inline">{t('pages.pos.exitToErp')}</span>
                    </button>
                ) : null}
                <button
                    type="button"
                    onClick={toggleTheme}
                    className="flex h-9 w-9 items-center justify-center rounded-[7px] text-[var(--pos-text-2)] transition hover:bg-[var(--pos-bg-subtle)]"
                    aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                >
                    {isDark ? <Sun className="h-4 w-4 text-amber-500" /> : <Moon className="h-4 w-4" />}
                </button>
                <NotificationsMenu />
                <div className="hidden items-center gap-2 rounded-[7px] border border-[var(--pos-border)] py-1 pr-2.5 pl-1.5 sm:flex">
                    <div className="flex h-[26px] w-[26px] items-center justify-center rounded-md bg-[var(--pos-teal-600)] text-[11px] font-bold text-white">
                        {getInitials(auth?.user?.name)}
                    </div>
                    <div className="min-w-0">
                        <div className="truncate text-[12.5px] font-semibold text-[var(--pos-text-1)]">
                            {auth?.user?.name}
                        </div>
                        <div className="truncate text-[10.5px] capitalize text-[var(--pos-text-3)]">
                            {roleLabel}
                        </div>
                    </div>
                </div>
                <div className="sm:hidden">
                    <UserMenu />
                </div>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex h-9 w-9 items-center justify-center rounded-[7px] text-[var(--pos-text-2)] transition hover:bg-rose-500/10 hover:text-rose-500"
                    aria-label={t('common.logOut')}
                >
                    <LogOut className="h-4 w-4" />
                </Link>
            </div>
        </header>
    );
}
