import { usePage } from '@inertiajs/react';
import { Activity, Package, User } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

function formatTime(iso) {
    try {
        return new Date(iso).toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return '';
    }
}

export default function DashboardRealtimeActivity() {
    const { auth, branch } = usePage().props;
    const branchId = branch?.active?.id;
    const [items, setItems] = useState([]);

    const push = useCallback((entry) => {
        setItems((prev) => {
            const id =
                globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;

            return [{ ...entry, clientId: id }, ...prev].slice(0, 40);
        });
    }, []);

    useEffect(() => {
        const Echo = window.Echo;

        if (!Echo || !auth?.user?.id || !branchId) {
            return undefined;
        }

        const channelName = `branch.${branchId}`;
        const channel = Echo.private(channelName);

        channel.listen('.inventory.stock-changed', (payload) => {
            push({ kind: 'inventory', payload });
        });

        channel.listen('.user.logged-in', (payload) => {
            push({ kind: 'login', payload });
        });

        return () => {
            try {
                Echo.leave(channelName);
            } catch {
                /* noop */
            }
        };
    }, [auth?.user?.id, branchId, push]);

    if (!import.meta.env.VITE_REVERB_APP_KEY) {
        return (
            <div className="rp-card">
                <h2 className="rp-section-title mb-2 inline-flex items-center gap-2">
                    <Activity className="h-4 w-4 text-rp-text-muted" />
                    Live activity
                </h2>
                <p className="text-sm text-rp-text-muted">
                    Set <code className="rounded bg-rp-bg-muted px-1 py-0.5 text-xs">VITE_REVERB_*</code> and{' '}
                    <code className="rounded bg-rp-bg-muted px-1 py-0.5 text-xs">REVERB_*</code> in{' '}
                    <code className="rounded bg-rp-bg-muted px-1 py-0.5 text-xs">.env</code>, run{' '}
                    <code className="rounded bg-rp-bg-muted px-1 py-0.5 text-xs">php artisan reverb:start</code>,
                    then rebuild front-end assets.
                </p>
            </div>
        );
    }

    if (!branchId) {
        return (
            <div className="rp-card">
                <h2 className="rp-section-title mb-2 inline-flex items-center gap-2">
                    <Activity className="h-4 w-4 text-rp-text-muted" />
                    Live activity
                </h2>
                <p className="text-sm text-rp-text-muted">
                    Select a branch in the header to subscribe to live inventory and sign-in activity for that
                    location.
                </p>
            </div>
        );
    }

    return (
        <div className="rp-card">
            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="rp-section-title inline-flex items-center gap-2">
                    <Activity className="h-4 w-4 text-rp-text-muted" />
                    Live activity
                </h2>
                <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-300">
                    Real-time
                </span>
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-rp-text-muted">
                    Waiting for events. Try receiving stock or signing in from another browser while this stays open.
                </p>
            ) : (
                <ul className="max-h-80 space-y-2 overflow-y-auto pr-1">
                    {items.map((row) => (
                        <li
                            key={row.clientId}
                            className={`rounded-lg border border-rp-border/60 bg-rp-page/40 px-3 py-2 text-sm ${
                                row.kind === 'inventory' && row.payload?.is_low_stock
                                    ? 'border-amber-400/70 bg-amber-500/5'
                                    : ''
                            }`}
                        >
                            {row.kind === 'login' ? (
                                <div className="flex gap-2">
                                    <User className="mt-0.5 h-4 w-4 shrink-0 text-violet-500" />
                                    <div>
                                        <div className="font-medium text-rp-text">
                                            {row.payload.user?.name}
                                            <span className="font-normal text-rp-text-muted"> signed in</span>
                                        </div>
                                        <div className="mt-0.5 text-xs text-rp-text-muted">
                                            {formatTime(row.payload.at)} · {row.payload.ip}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex gap-2">
                                    <Package className="mt-0.5 h-4 w-4 shrink-0 text-teal-500" />
                                    <div>
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span className="font-medium text-rp-text">
                                                {row.payload.product_name ?? 'Product'}
                                            </span>
                                            {row.payload.sku ? (
                                                <span className="rounded bg-rp-bg-muted px-1.5 py-0.5 text-xs text-rp-text-muted">
                                                    {row.payload.sku}
                                                </span>
                                            ) : null}
                                            {row.payload.is_low_stock ? (
                                                <span className="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                                    Low stock
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="mt-0.5 text-xs text-rp-text-muted">
                                            Available {row.payload.available ?? '—'}
                                            {row.payload.reorder_point != null
                                                ? ` · Reorder at ${row.payload.reorder_point}`
                                                : ''}{' '}
                                            · {formatTime(row.payload.at)}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
