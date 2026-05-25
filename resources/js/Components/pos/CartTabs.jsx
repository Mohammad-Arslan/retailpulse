import { CircleDashed, Plus, X } from 'lucide-react';

const STATUS_STYLES = {
    active: {
        badge: 'bg-blue-500/20 text-blue-400',
        tab: 'border-blue-500/40 bg-blue-500/10 text-blue-300',
        activeTab: 'border-blue-500 bg-blue-500/15 text-blue-200 ring-1 ring-blue-500/30',
    },
    suspended: {
        badge: 'bg-amber-500/20 text-amber-400',
        tab: 'border-amber-500/30 bg-rp-surface-subtle text-rp-text-secondary',
        activeTab: 'border-amber-500 bg-amber-500/10 text-amber-200 ring-1 ring-amber-500/30',
    },
    completing: {
        badge: 'bg-orange-500/20 text-orange-400',
        tab: 'border-orange-500/40 bg-rp-surface-subtle text-orange-300/80',
        activeTab: 'border-orange-500 bg-orange-500/10 text-orange-200 ring-1 ring-orange-500/30',
    },
};

function getStyles(status) {
    return STATUS_STYLES[status] || STATUS_STYLES.active;
}

export function CartTabs({
    carts,
    activeCartId,
    onSelect,
    onNew,
    onRemove,
    maxReached,
    processing,
}) {
    return (
        <div className="flex shrink-0 items-center gap-2 overflow-x-auto border-b border-rp-border bg-rp-surface-subtle/50 px-5 py-2.5">
            {carts.map((cart) => {
                const isActive = cart.id === activeCartId;
                const styles = getStyles(cart.status);
                const itemCount = cart.items?.length ?? 0;

                return (
                    <div
                        key={cart.id}
                        className={`group flex shrink-0 items-stretch overflow-hidden rounded-lg border transition-colors ${
                            isActive ? styles.activeTab : styles.tab
                        }`}
                    >
                        {cart.status === 'completing' && (
                            <button
                                type="button"
                                onClick={(e) => onRemove?.(cart.id, e)}
                                disabled={processing}
                                title="Dismiss cart"
                                className="flex items-center px-2 text-orange-400/70 transition-colors hover:bg-orange-500/10 hover:text-orange-300 disabled:opacity-40"
                            >
                                <CircleDashed className="h-3.5 w-3.5" />
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => onSelect(cart.id)}
                            disabled={processing}
                            title={`Ctrl+${cart.slot}`}
                            className="flex items-center gap-2 px-3 py-2 text-xs font-medium disabled:opacity-40"
                        >
                            <span>Cart {cart.slot}</span>
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase ${styles.badge}`}
                            >
                                {cart.status}
                            </span>
                            {itemCount > 0 && (
                                <span className="text-rp-text-muted">· {itemCount}</span>
                            )}
                        </button>

                        {cart.status !== 'completing' && (
                            <button
                                type="button"
                                onClick={(e) => onRemove?.(cart.id, e)}
                                disabled={processing}
                                title="Close cart"
                                className="flex items-center px-2 text-rp-text-muted opacity-60 transition-all hover:bg-red-500/10 hover:text-red-400 hover:opacity-100 disabled:opacity-40"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                );
            })}

            <button
                type="button"
                onClick={onNew}
                disabled={maxReached || processing}
                title="New cart"
                className="flex shrink-0 items-center gap-1.5 rounded-lg border border-dashed border-rp-border px-3 py-2 text-xs font-medium text-rp-text-muted transition-colors hover:border-rp-text-muted hover:text-rp-text-secondary disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus className="h-3.5 w-3.5" />
                New
            </button>
        </div>
    );
}
