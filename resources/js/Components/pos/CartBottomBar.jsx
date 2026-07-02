import { useState } from 'react';
import {
    Banknote,
    CreditCard,
    MoreHorizontal,
    Pause,
    RotateCcw,
    Trash2,
    UserRound,
    Wallet,
} from 'lucide-react';
import { computeCartTotals, estimateCartTax, formatPkr } from '@/lib/posCartTotals';

export function CartBottomBar({
    cart,
    taxEnabled,
    taxMode,
    defaultTaxRate,
    taxRatePct,
    currency,
    processing,
    onCheckout,
    onSuspend,
    onVoid,
    onReopen,
    canSuspend,
    onAttachCustomer,
    hasCustomer = false,
}) {
    const [moreOpen, setMoreOpen] = useState(false);

    const items = cart?.items || [];
    const isCompleting = cart?.status === 'completing';
    const isActive = cart?.status === 'active';
    const isSuspended = cart?.status === 'suspended';
    const isEditable = isActive || isSuspended;
    const hasItems = items.length > 0;

    const { subtotal, grandTotal, discount } = computeCartTotals(items);
    const { taxAmount, grandTotalIncTax } = taxEnabled
        ? estimateCartTax(grandTotal, defaultTaxRate, taxMode)
        : { taxAmount: 0, grandTotalIncTax: grandTotal };

    const displayTotal = taxEnabled ? grandTotalIncTax : grandTotal;

    const canPay = hasItems && (isActive || isSuspended || isCompleting);
    const canHold = hasItems && isActive && canSuspend;

    return (
        <div className="shrink-0 border-t border-rp-border bg-rp-surface">
            {/* Totals strip */}
            <div className="flex items-center gap-6 border-b border-rp-border/50 px-4 py-2.5">
                {/* Subtotal */}
                <div className="flex items-center gap-1.5">
                    <span className="text-xs text-rp-text-muted">Subtotal</span>
                    <span className="text-xs font-medium text-rp-text-secondary">{currency} {formatPkr(subtotal)}</span>
                </div>

                {/* Discount */}
                {discount > 0 && (
                    <>
                        <span className="text-xs text-rp-text-muted/40">+</span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs text-rp-text-muted">Discount</span>
                            <span className="text-xs font-medium text-emerald-400">−{currency} {formatPkr(discount)}</span>
                        </div>
                    </>
                )}

                {/* Tax */}
                {taxEnabled && taxRatePct > 0 && (
                    <>
                        <span className="text-xs text-rp-text-muted/40">+</span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs text-rp-text-muted">Tax ({taxRatePct}%)</span>
                            <span className="text-xs font-medium text-rp-text-secondary">{currency} {formatPkr(taxAmount)}</span>
                        </div>
                    </>
                )}

                <span className="text-xs font-bold text-rp-text-muted">=</span>

                {/* Grand total */}
                <div className="flex items-center gap-1.5">
                    <span className="text-sm font-bold text-rp-text">Total</span>
                    <span className="text-lg font-bold text-rp-text">{currency} {formatPkr(displayTotal)}</span>
                </div>
            </div>

            {/* Action bar */}
            <div className="flex items-center gap-2 px-4 py-3">
                {/* Left actions */}
                <div className="flex items-center gap-1.5">
                    {/* Clear Cart / Void */}
                    <button
                        type="button"
                        onClick={onVoid}
                        disabled={processing || !hasItems || isCompleting}
                        className="flex items-center gap-1.5 rounded-lg border border-rp-border px-3 py-2 text-xs font-medium text-rp-text-secondary transition hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-400 disabled:cursor-not-allowed disabled:opacity-40"
                        title="Clear Cart"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Clear Cart</span>
                    </button>

                    {/* Hold Cart */}
                    {(isActive || isSuspended) && (
                        <button
                            type="button"
                            onClick={isSuspended ? onReopen : onSuspend}
                            disabled={processing || !canHold && !isSuspended}
                            className="flex items-center gap-1.5 rounded-lg border border-rp-border px-3 py-2 text-xs font-medium text-rp-text-secondary transition hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                            title={isSuspended ? 'Resume Cart' : 'Hold Cart'}
                        >
                            {isSuspended ? (
                                <RotateCcw className="h-3.5 w-3.5" />
                            ) : (
                                <Pause className="h-3.5 w-3.5" />
                            )}
                            <span className="hidden sm:inline">{isSuspended ? 'Resume' : 'Hold Cart'}</span>
                        </button>
                    )}

                    {/* Reopen from completing */}
                    {isCompleting && (
                        <button
                            type="button"
                            onClick={onReopen}
                            disabled={processing}
                            className="flex items-center gap-1.5 rounded-lg border border-rp-border px-3 py-2 text-xs font-medium text-rp-text-secondary transition hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Reopen</span>
                        </button>
                    )}

                    {/* More dropdown */}
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setMoreOpen((o) => !o)}
                            disabled={processing}
                            className="flex items-center gap-1 rounded-lg border border-rp-border px-3 py-2 text-xs font-medium text-rp-text-secondary transition hover:border-rp-text-muted hover:text-rp-text disabled:opacity-40"
                        >
                            <MoreHorizontal className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">More</span>
                        </button>
                        {moreOpen && (
                            <>
                                <button
                                    type="button"
                                    className="fixed inset-0 z-10"
                                    onClick={() => setMoreOpen(false)}
                                    tabIndex={-1}
                                    aria-hidden
                                />
                                <div className="absolute bottom-full left-0 z-20 mb-1 w-44 rounded-xl border border-rp-border bg-rp-surface py-1 shadow-xl">
                                    <button
                                        type="button"
                                        onClick={() => setMoreOpen(false)}
                                        className="flex w-full items-center gap-2.5 px-3 py-2 text-xs text-rp-text-secondary hover:bg-rp-surface-subtle hover:text-rp-text"
                                    >
                                        Cart Notes (coming soon)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setMoreOpen(false);
                                            onAttachCustomer?.();
                                        }}
                                        className="flex w-full items-center gap-2.5 px-3 py-2 text-xs text-rp-text-secondary hover:bg-rp-surface-subtle hover:text-rp-text"
                                    >
                                        <UserRound className="h-3.5 w-3.5 shrink-0" />
                                        {hasCustomer ? 'Change Customer' : 'Attach Customer'}
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Right: payment shortcuts + Pay button */}
                <div className="flex items-center gap-2">
                    {/* Quick payment type hints */}
                    <div className="hidden items-center gap-1 md:flex">
                        <button
                            type="button"
                            disabled={!canPay || processing}
                            onClick={onCheckout}
                            className="flex items-center gap-1.5 rounded-lg border border-rp-border px-2.5 py-2 text-xs text-rp-text-muted transition hover:border-teal-400/40 hover:bg-teal-500/5 hover:text-rp-text-secondary disabled:cursor-not-allowed disabled:opacity-40"
                            title="Cash"
                        >
                            <Banknote className="h-3.5 w-3.5" />
                            <span>Cash</span>
                        </button>
                        <button
                            type="button"
                            disabled={!canPay || processing}
                            onClick={onCheckout}
                            className="flex items-center gap-1.5 rounded-lg border border-rp-border px-2.5 py-2 text-xs text-rp-text-muted transition hover:border-teal-400/40 hover:bg-teal-500/5 hover:text-rp-text-secondary disabled:cursor-not-allowed disabled:opacity-40"
                            title="Card"
                        >
                            <CreditCard className="h-3.5 w-3.5" />
                            <span>Card</span>
                        </button>
                        <button
                            type="button"
                            disabled={!canPay || processing}
                            onClick={onCheckout}
                            className="flex items-center gap-1.5 rounded-lg border border-rp-border px-2.5 py-2 text-xs text-rp-text-muted transition hover:border-teal-400/40 hover:bg-teal-500/5 hover:text-rp-text-secondary disabled:cursor-not-allowed disabled:opacity-40"
                            title="e-Wallet"
                        >
                            <Wallet className="h-3.5 w-3.5" />
                            <span>e-Wallet</span>
                        </button>
                    </div>

                    {/* Main Pay button */}
                    <button
                        type="button"
                        onClick={onCheckout}
                        disabled={!canPay || processing}
                        className="flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <CreditCard className="h-4 w-4" />
                        <span>Pay</span>
                        <kbd className="ml-1 rounded border border-emerald-400/40 bg-emerald-700/60 px-1.5 py-0.5 text-[10px] font-medium">
                            F10
                        </kbd>
                    </button>
                </div>
            </div>
        </div>
    );
}
