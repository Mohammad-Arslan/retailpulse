import { CreditCard, Pause, Pencil, Tag, Trash2 } from 'lucide-react';
import { CartItemRow } from './CartItemRow';
import { computeCartTotals, formatPkr } from '@/lib/posCartTotals';

export function CartPanel({
    cart,
    stockWarnings,
    onItemUpdated,
    onItemRemoved,
    onCheckout,
    onSuspend,
    onVoid,
    onReopen,
    canDiscount,
    processing,
}) {
    if (!cart) {
        return (
            <div className="flex h-full items-center justify-center text-rp-text-muted">
                <p className="text-sm">No active cart</p>
            </div>
        );
    }

    const items = cart.items || [];
    const { subtotal, grandTotal, discount } = computeCartTotals(items);
    const hasWarnings = Object.keys(stockWarnings || {}).length > 0;
    const isCompleting = cart.status === 'completing';
    const isEditable = cart.status === 'active' || cart.status === 'suspended';

    return (
        <div className="flex h-full flex-col bg-rp-surface">
            <div className="border-b border-rp-border px-5 py-3">
                <p className="text-[11px] font-semibold tracking-widest text-rp-text-muted uppercase">
                    Cart {cart.slot}
                </p>
                {items.length > 0 && (
                    <p className="mt-0.5 text-sm text-blue-400">
                        {items.length} item{items.length !== 1 ? 's' : ''} · PKR {formatPkr(grandTotal)}{' '}
                        total
                    </p>
                )}
            </div>

            {isCompleting && (
                <div className="border-b border-orange-500/30 bg-orange-500/10 px-5 py-3">
                    <p className="text-xs text-orange-300">
                        This cart is locked for payment. Continue editing to change items.
                    </p>
                    <button
                        type="button"
                        onClick={onReopen}
                        disabled={processing}
                        className="mt-2 flex items-center gap-1.5 rounded-lg border border-orange-500/40 px-3 py-1.5 text-xs font-medium text-orange-300 transition-colors hover:bg-orange-500/15 disabled:opacity-40"
                    >
                        <Pencil className="h-3.5 w-3.5" />
                        Continue editing
                    </button>
                </div>
            )}

            <div className="flex-1 space-y-2 overflow-y-auto p-4">
                {items.length === 0 ? (
                    <div className="flex h-full items-center justify-center py-12 text-rp-text-muted">
                        <div className="text-center">
                            <p className="text-4xl opacity-40">🛒</p>
                            <p className="mt-2 text-sm">Cart is empty</p>
                            <p className="text-xs">Search for a product to add</p>
                        </div>
                    </div>
                ) : (
                    items.map((item) => (
                        <CartItemRow
                            key={item.id}
                            cartId={cart.id}
                            item={item}
                            onUpdated={onItemUpdated}
                            onRemoved={onItemRemoved}
                            canApplyDiscount={canDiscount && isEditable}
                            stockWarning={stockWarnings?.[item.id] || null}
                            readOnly={isCompleting}
                            processing={processing}
                        />
                    ))
                )}
            </div>

            {items.length > 0 && (
                <div className="border-t border-rp-border px-5 py-4">
                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between text-rp-text-secondary">
                            <span>Subtotal</span>
                            <span>PKR {formatPkr(subtotal)}</span>
                        </div>
                        {discount > 0 && (
                            <div className="flex justify-between text-emerald-400">
                                <span className="flex items-center gap-1.5">
                                    <Tag className="h-3.5 w-3.5" />
                                    Discount
                                </span>
                                <span>- PKR {formatPkr(discount)}</span>
                            </div>
                        )}
                        <div className="flex justify-between border-t border-rp-border pt-3 text-lg font-bold text-rp-text">
                            <span>Total</span>
                            <span>PKR {formatPkr(grandTotal)}</span>
                        </div>
                    </div>
                </div>
            )}

            <div className="border-t border-rp-border p-4">
                <div className="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        onClick={onSuspend}
                        disabled={processing || cart.status !== 'active'}
                        className="flex items-center justify-center gap-2 rounded-lg border border-rp-border bg-rp-surface-subtle py-2.5 text-xs font-medium text-rp-text-secondary transition-colors hover:bg-rp-surface-inset disabled:opacity-40"
                        title="Suspend (Ctrl+H)"
                    >
                        <Pause className="h-3.5 w-3.5" />
                        Suspend
                    </button>
                    <button
                        type="button"
                        onClick={onVoid}
                        disabled={processing || !isEditable}
                        className="flex items-center justify-center gap-2 rounded-lg border border-red-500/30 py-2.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10 disabled:opacity-40"
                        title="Void (Ctrl+V)"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                        Void
                    </button>
                </div>

                <button
                    type="button"
                    onClick={onCheckout}
                    disabled={processing || items.length === 0 || hasWarnings || !isEditable}
                    className="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-500 disabled:opacity-40"
                    title="Proceed to Payment (F10)"
                >
                    <CreditCard className="h-4 w-4" />
                    Pay
                    <span className="ml-1 rounded bg-blue-500/50 px-1.5 py-0.5 text-[10px] font-normal">
                        F10
                    </span>
                </button>

                {hasWarnings && (
                    <p className="mt-2 text-center text-xs text-amber-400">
                        Resolve stock warnings before payment
                    </p>
                )}
            </div>
        </div>
    );
}
