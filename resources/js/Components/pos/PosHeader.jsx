import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

function ConnectivityIndicator({ online }) {
    return (
        <div className="flex items-center gap-1.5">
            <span
                className={`h-2 w-2 rounded-full ${
                    online ? 'bg-emerald-500' : 'animate-pulse bg-amber-500'
                }`}
            />
            <span
                className={`text-xs font-medium ${
                    online ? 'text-emerald-500' : 'text-amber-500'
                }`}
            >
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

    const m = Math.floor(elapsed / 60);
    const s = elapsed % 60;
    const label = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

    return (
        <span className="text-xs text-rp-text-muted">
            Session {label}
        </span>
    );
}

function UserAvatar({ name }) {
    const initials = (name || '?')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[11px] font-bold text-white">
            {initials}
        </span>
    );
}

export function PosHeader({ cartCount, sessionStart }) {
    const { auth, app } = usePage().props;
    const [online, setOnline] = useState(navigator.onLine);

    useEffect(() => {
        const handleOnline = () => setOnline(true);
        const handleOffline = () => setOnline(false);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    return (
        <header className="flex h-12 shrink-0 items-center justify-between border-b border-rp-border bg-rp-surface px-5">
            <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-rp-text">
                    {app?.name || 'RetailPulse'} POS
                </span>
                <ConnectivityIndicator online={online} />
            </div>

            <div className="flex items-center gap-4">
                <SessionTimer startedAt={sessionStart} />

                <span className="text-xs text-rp-text-muted">
                    {cartCount} cart{cartCount !== 1 ? 's' : ''} open
                </span>

                <div className="flex items-center gap-2 rounded-full border border-rp-border bg-rp-surface-subtle py-1 pr-3 pl-1">
                    <UserAvatar name={auth?.user?.name} />
                    <div className="text-xs leading-tight">
                        <span className="font-medium text-rp-text">{auth?.user?.name}</span>
                        <span className="text-rp-text-muted"> — Admin</span>
                    </div>
                </div>
            </div>
        </header>
    );
}
