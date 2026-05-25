import {
    Banknote,
    CreditCard,
    MoreHorizontal,
    Pause,
    Pencil,
    StickyNote,
    Tag,
    Trash2,
    User,
    Wallet,
} from 'lucide-react';
import { CartItemRow } from './CartItemRow';
import { computeCartTotals, formatPkr } from '@/lib/posCartTotals';
import { cn } from '@/lib/utils';

function ToolbarButton({ icon: Icon, label, onClick, disabled, destructive }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={label}
            className={cn(
                'flex flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[10px] font-medium transition-colors disabled:opacity-40',
                destructive
                    ? 'text-red-400 hover:bg-red-500/10'
                    : 'text-rp-text-muted hover:bg-rp-surface-subtle hover:text-rp-text-secondary',
            )}
        >
            <Icon className="h-4 w-4" />
            {label}
        </button>
    );
}

export function CartPanel({
    cart,
    stockWarnings,
    onItemUpdated,
    onItemRemoved,
    onCheckout,
    onSuspend,
    onVoid,
    onReopen,
    onAddDiscount,
    onNote,
    onCustomer,
    canDiscount,
    processing,
}) {
    if (!cart) {
        return (
            <div className="flex h-full w-[380px] shrink-0 items-center justify-center border-l border-rp-border bg-rp-surface text-rp-text-muted xl:w-[420px]">
                <p className="text-sm">No active cart</p>
            </div>
        );
    }

    const items = cart.items || [];
    const { subtotal, grandTotal, discount } = computeCartTotals(items);
    const tax = 0;
    const hasWarnings = Object.keys(stockWarnings || {}).length > 0;
    const isCompleting = cart.status === 'completing';
    const isEditable = cart.status === 'active' || cart.status === 'suspended';
    const isSuspended = cart.status === 'suspended';

    return (
        <aside className="flex h-full w-[380px] shrink-0 flex-col border-l border-rp-border bg-rp-surface xl:w-[420px]">
            <div className="border-b border-rp-border px-4 py-3">
                <div className="flex items-center justify-between gap-2">
                    <div>
                        <p className="text-sm font-semibold text-rp-text">Cart {cart.slot}</p>
                        {items.length > 0 && (
                            <p className="text-xs text-blue-400">
                                {items.length} items · PKR {formatPkr(grandTotal)}
                            </p>
                        )}
                    </div>
                    {isSuspended && (
                        <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-400">
                            Suspended
                        </span>
                    )}
                </div>

                <div className="mt-2 flex justify-between gap-1">
                    <ToolbarButton icon={Pause} label="Hold" onClick={onSuspend} disabled={processing || cart.status !== 'active'} />
                    <ToolbarButton icon={StickyNote} label="Note" onClick={onNote} disabled={processing || !isEditable} />
                    <ToolbarButton icon={Trash2} label="Delete" onClick={onVoid} disabled={processing || !isEditable} destructive />
                    <ToolbarButton icon={User} label="Customer" onClick={onCustomer} disabled={processing} />
                    <ToolbarButton icon={MoreHorizontal} label="More" onClick={() => {}} disabled />
                </div>
            </div>

            {isCompleting && (
                <div className="border-b border-orange-500/30 bg-orange-500/10 px-4 py-3">
                    <p className="text-xs text-orange-300">Cart locked for payment.</p>
                    <button
                        type="button"
                        onClick={onReopen}
                        disabled={processing}
                        className="mt-2 flex items-center gap-1.5 text-xs font-medium text-orange-300 hover:underline disabled:opacity-40"
                    >
                        <Pencil className="h-3.5 w-3.5" />
                        Continue editing
                    </button>
                </div>
            )}

            <div className="flex-1 space-y-2 overflow-y-auto p-3">
                {items.length === 0 ? (
                    <div className="flex h-full items-center justify-center py-12 text-rp-text-muted">
                        <div className="text-center">
                            <p className="text-4xl opacity-40">🛒</p>
                            <p className="mt-2 text-sm">Cart is empty</p>
                            <p className="text-xs">Tap a product to add</p>
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
                <div className="border-t border-rp-border px-4 py-3">
                    {canDiscount && isEditable && (
                        <button
                            type="button"
                            onClick={onAddDiscount}
                            className="mb-3 text-xs font-medium text-blue-400 hover:underline"
                        >
                            + Add Discount
                        </button>
                    )}

                    <div className="space-y-1.5 text-sm">
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
                        <div className="flex justify-between text-rp-text-secondary">
                            <span>Tax (16%)</span>
                            <span>PKR {formatPkr(tax)}</span>
                        </div>
                        <div className="flex justify-between border-t border-rp-border pt-2 text-xl font-bold text-rp-text">
                            <span>Total</span>
                            <span>PKR {formatPkr(grandTotal + tax)}</span>
                        </div>
                    </div>
                </div>
            )}

            <div className="border-t border-rp-border p-4">
                <button
                    type="button"
                    onClick={onCheckout}
                    disabled={processing || items.length === 0 || hasWarnings || !isEditable}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-blue-500 disabled:opacity-40"
                >
                    <CreditCard className="h-4 w-4" />
                    Pay
                    <span className="rounded bg-blue-500/50 px-1.5 py-0.5 text-[10px] font-normal">F10</span>
                </button>

                <div className="mt-2 grid grid-cols-4 gap-2">
                    {[
                        { icon: Banknote, label: 'Cash' },
                        { icon: CreditCard, label: 'Card' },
                        { icon: Wallet, label: 'e-Wallet' },
                        { icon: MoreHorizontal, label: 'More' },
                    ].map(({ icon: Icon, label }) => (
                        <button
                            key={label}
                            type="button"
                            onClick={onCheckout}
                            disabled={processing || items.length === 0 || !isEditable}
                            className="flex flex-col items-center gap-1 rounded-lg border border-rp-border py-2 text-[10px] font-medium text-rp-text-secondary transition-colors hover:bg-rp-surface-subtle disabled:opacity-40"
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </button>
                    ))}
                </div>

                {hasWarnings && (
                    <p className="mt-2 text-center text-xs text-amber-400">
                        Resolve stock warnings before payment
                    </p>
                )}
            </div>
        </aside>
    );
}
