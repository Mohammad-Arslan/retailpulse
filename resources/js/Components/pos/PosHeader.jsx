import { useEffect, useState } from 'react';
import { Search } from 'lucide-react';

function ConnectivityIndicator({ online }) {
    return (
        <div className="flex items-center gap-1.5">
            <span
                className={`h-2 w-2 rounded-full ${online ? 'bg-emerald-500' : 'animate-pulse bg-amber-500'}`}
            />
            <span className={`text-xs font-medium ${online ? 'text-emerald-500' : 'text-amber-500'}`}>
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
    const label =
        h > 0
            ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
            : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

    return <span className="text-xs text-rp-text-muted">Session {label}</span>;
}

export function PosHeader({
    cartCount,
    sessionStart,
    searchQuery,
    onSearchChange,
    onSearchKeyDown,
    searchInputRef,
    searchLoading,
}) {
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
        <header className="flex h-14 shrink-0 items-center gap-4 border-b border-rp-border bg-rp-surface px-4">
            <div className="flex min-w-[120px] items-center gap-2">
                <span className="text-sm font-semibold text-rp-text">Point of Sale</span>
                <ConnectivityIndicator online={online} />
            </div>

            <div className="relative mx-auto w-full max-w-2xl flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                <input
                    ref={searchInputRef}
                    type="search"
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                    onKeyDown={onSearchKeyDown}
                    placeholder="Search by name, SKU or scan barcode…"
                    className="w-full rounded-xl border border-rp-border bg-rp-surface-inset py-2.5 pr-14 pl-10 text-sm text-rp-text outline-none placeholder:text-rp-text-muted focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20"
                    autoComplete="off"
                />
                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rounded-md border border-rp-border bg-rp-surface px-1.5 py-0.5 text-[10px] font-medium text-rp-text-muted">
                    F2
                </span>
                {searchLoading && (
                    <div className="absolute top-1/2 right-12 -translate-y-1/2">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
                    </div>
                )}
            </div>

            <div className="flex min-w-[200px] items-center justify-end gap-3">
                <SessionTimer startedAt={sessionStart} />
                <span className="hidden text-xs text-rp-text-muted sm:inline">
                    {cartCount} cart{cartCount !== 1 ? 's' : ''} open
                </span>
            </div>
        </header>
    );
}
