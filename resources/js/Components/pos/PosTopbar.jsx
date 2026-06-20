import { useEffect, useState } from 'react';
import BranchSwitcher from '@/Components/admin/BranchSwitcher';
import NotificationsMenu from '@/Components/admin/NotificationsMenu';
import UserMenu from '@/Components/admin/UserMenu';
import { useTheme } from '@/Hooks/useTheme';
import { Moon, Sun } from 'lucide-react';

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
        <div className="flex items-center gap-1.5">
            <span className={`h-2 w-2 rounded-full ${online ? 'bg-emerald-500' : 'animate-pulse bg-amber-500'}`} />
            <span className={`text-xs font-medium ${online ? 'text-emerald-500' : 'text-amber-400'}`}>
                {online ? 'Online' : 'Offline'}
            </span>
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
    const label = h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

    return <span className="text-xs text-rp-text-muted">Session {label}</span>;
}

export function PosTopbar({ cartCount, sessionStart }) {
    const { isDark, toggleTheme } = useTheme();

    return (
        <header className="flex h-14 shrink-0 items-center gap-4 border-b border-rp-border bg-rp-surface px-4">
            {/* Left: POS identity */}
            <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-rp-text">Point of Sale</span>
                <OnlineIndicator />
            </div>

            {/* Right: session info + branch + user controls */}
            <div className="ml-auto flex items-center gap-3">
                <SessionTimer startedAt={sessionStart} />
                {cartCount > 0 && (
                    <span className="hidden text-xs text-rp-text-muted sm:inline">
                        {cartCount} cart{cartCount !== 1 ? 's' : ''} open
                    </span>
                )}
                <div className="h-4 w-px bg-rp-border" />
                <BranchSwitcher />
                <button
                    type="button"
                    onClick={toggleTheme}
                    className="flex h-9 w-9 items-center justify-center rounded-lg border border-rp-border bg-rp-surface transition hover:border-teal-400"
                    aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                >
                    {isDark
                        ? <Sun className="h-4 w-4 text-amber-500" />
                        : <Moon className="h-4 w-4 text-rp-text-secondary" />
                    }
                </button>
                <NotificationsMenu />
                <UserMenu />
            </div>
        </header>
    );
}
