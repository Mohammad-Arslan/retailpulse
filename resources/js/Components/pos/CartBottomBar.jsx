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
import { useTranslation } from 'react-i18next';

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
    const { t } = useTranslation();
    const [moreOpen, setMoreOpen] = useState(false);

    const items = cart?.items || [];
    const isCompleting = cart?.status === 'completing';
    const isActive = cart?.status === 'active';
    const isSuspended = cart?.status === 'suspended';
    const hasItems = items.length > 0;

    const { subtotal, grandTotal, discount } = computeCartTotals(items);
    const { taxAmount, grandTotalIncTax } = taxEnabled
        ? estimateCartTax(grandTotal, defaultTaxRate, taxMode)
        : { taxAmount: 0, grandTotalIncTax: grandTotal };

    const displayTotal = taxEnabled ? grandTotalIncTax : grandTotal;

    const canPay = hasItems && (isActive || isSuspended || isCompleting);
    const canHold = hasItems && isActive && canSuspend;

    return (
        <div className="shrink-0 border-t border-[var(--pos-border)] bg-[var(--pos-bg)]">
            <div className="space-y-0.5 px-[18px] pt-3.5 pb-2">
                <div className="flex justify-between py-0.5 text-[12.5px] text-[var(--pos-text-2)]">
                    <span>{t('pages.pos.cart.subtotal')}</span>
                    <b className="pos-mono font-semibold text-[var(--pos-text-1)]">
                        {currency} {formatPkr(subtotal)}
                    </b>
                </div>
                {taxEnabled && taxRatePct > 0 ? (
                    <div className="flex justify-between py-0.5 text-[12.5px] text-[var(--pos-text-2)]">
                        <span>
                            {t('pages.pos.cart.tax')} ({taxRatePct}%)
                            {taxMode === 'inclusive' ? (
                                <span className="ml-1 text-[10px] opacity-70">incl.</span>
                            ) : null}
                        </span>
                        <b className="pos-mono font-semibold text-[var(--pos-text-1)]">
                            {currency} {formatPkr(taxAmount)}
                        </b>
                    </div>
                ) : null}
                <div className="flex justify-between py-0.5 text-[12.5px] text-[var(--pos-text-2)]">
                    <span>{t('pages.pos.cart.discount')}</span>
                    <b className="pos-mono font-semibold text-[var(--pos-text-1)]">
                        {currency} {formatPkr(discount)}
                    </b>
                </div>
                <div className="mt-1.5 flex items-center justify-between border-t border-dashed border-[var(--pos-border-strong)] pt-2.5 text-[15.5px] font-extrabold text-[var(--pos-text-1)]">
                    <span>{t('pages.pos.cart.totalDue')}</span>
                    <b className="pos-mono text-[19px] font-extrabold text-[var(--pos-teal-700)]">
                        {currency} {formatPkr(displayTotal)}
                    </b>
                </div>
            </div>

            <div className="flex gap-2 px-[18px] pt-2.5">
                <button
                    type="button"
                    onClick={onVoid}
                    disabled={processing || !hasItems || isCompleting}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] py-2 text-xs font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)] disabled:opacity-40"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                    {t('pages.pos.cart.clearCart')}
                </button>

                {(isActive || isSuspended) ? (
                    <button
                        type="button"
                        onClick={isSuspended ? onReopen : onSuspend}
                        disabled={processing || (!canHold && !isSuspended)}
                        className="flex flex-1 items-center justify-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] py-2 text-xs font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)] disabled:opacity-40"
                    >
                        {isSuspended ? (
                            <RotateCcw className="h-3.5 w-3.5" />
                        ) : (
                            <Pause className="h-3.5 w-3.5" />
                        )}
                        {isSuspended ? t('pages.pos.cart.resumeCart') : t('pages.pos.cart.holdCart')}
                    </button>
                ) : null}

                {isCompleting ? (
                    <button
                        type="button"
                        onClick={onReopen}
                        disabled={processing}
                        className="flex flex-1 items-center justify-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] py-2 text-xs font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)] disabled:opacity-40"
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        {t('pages.pos.cart.reopen')}
                    </button>
                ) : null}

                <div className="relative flex-1">
                    <button
                        type="button"
                        onClick={() => setMoreOpen((o) => !o)}
                        disabled={processing}
                        className="flex w-full items-center justify-center gap-1.5 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] py-2 text-xs font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] hover:bg-[var(--pos-bg-subtle)] disabled:opacity-40"
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                        {t('pages.pos.cart.more')}
                    </button>
                    {moreOpen ? (
                        <>
                            <button
                                type="button"
                                className="fixed inset-0 z-10"
                                onClick={() => setMoreOpen(false)}
                                tabIndex={-1}
                                aria-hidden
                            />
                            <div className="absolute bottom-full left-0 z-20 mb-1 w-44 rounded-xl border border-[var(--pos-border)] bg-[var(--pos-bg)] py-1 shadow-[var(--pos-shadow-md)]">
                                <button
                                    type="button"
                                    onClick={() => setMoreOpen(false)}
                                    className="flex w-full items-center gap-2.5 px-3 py-2 text-xs text-[var(--pos-text-2)] hover:bg-[var(--pos-bg-subtle)]"
                                >
                                    {t('pages.pos.cart.notesComingSoon')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setMoreOpen(false);
                                        onAttachCustomer?.();
                                    }}
                                    className="flex w-full items-center gap-2.5 px-3 py-2 text-xs text-[var(--pos-text-2)] hover:bg-[var(--pos-bg-subtle)]"
                                >
                                    <UserRound className="h-3.5 w-3.5 shrink-0" />
                                    {hasCustomer
                                        ? t('pages.pos.cart.changeCustomer')
                                        : t('pages.pos.cart.attachCustomer')}
                                </button>
                            </div>
                        </>
                    ) : null}
                </div>
            </div>

            <div className="flex gap-2 px-[18px] py-3">
                <button
                    type="button"
                    disabled={!canPay || processing}
                    onClick={onCheckout}
                    className="flex flex-1 flex-col items-center gap-1 rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] px-1 py-2 text-[11px] font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] disabled:opacity-40"
                >
                    <Banknote className="h-4 w-4" />
                    {t('pages.pos.cart.cash')}
                </button>
                <button
                    type="button"
                    disabled={!canPay || processing}
                    onClick={onCheckout}
                    className="flex flex-1 flex-col items-center gap-1 rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] px-1 py-2 text-[11px] font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] disabled:opacity-40"
                >
                    <CreditCard className="h-4 w-4" />
                    {t('pages.pos.cart.card')}
                </button>
                <button
                    type="button"
                    disabled={!canPay || processing}
                    onClick={onCheckout}
                    className="flex flex-1 flex-col items-center gap-1 rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] px-1 py-2 text-[11px] font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-border-strong)] disabled:opacity-40"
                >
                    <Wallet className="h-4 w-4" />
                    {t('pages.pos.cart.eWallet')}
                </button>
                <button
                    type="button"
                    onClick={onCheckout}
                    disabled={!canPay || processing}
                    className="flex flex-[1.6] flex-col items-center justify-center rounded-lg border-0 bg-linear-to-br from-[var(--pos-teal-500)] to-[var(--pos-teal-700)] text-white shadow-[var(--pos-shadow-md)] transition active:scale-[0.98] disabled:opacity-40"
                >
                    <span className="flex items-center gap-1.5 text-[13.5px] font-extrabold">
                        {t('pages.pos.cart.pay')}
                        <span className="rounded bg-white/20 px-1.5 py-0.5 text-[9.5px] font-bold">
                            F10
                        </span>
                    </span>
                </button>
            </div>

            <div className="flex flex-wrap items-center gap-x-3.5 gap-y-1 bg-[var(--pos-text-1)] px-[18px] py-1.5">
                {[
                    ['F2', t('pages.pos.quickkeys.search')],
                    ['F5', t('pages.pos.quickkeys.hold')],
                    ['F9', t('pages.pos.quickkeys.discount')],
                    ['F10', t('pages.pos.quickkeys.pay')],
                    ['F12', t('pages.pos.quickkeys.voidLine')],
                ].map(([key, label]) => (
                    <span
                        key={key}
                        className="flex items-center gap-1 text-[10.5px] font-medium text-[#c9d5d3]"
                    >
                        <b className="pos-mono rounded bg-white/12 px-1.5 py-0.5 text-[10px] font-bold text-white">
                            {key}
                        </b>
                        {label}
                    </span>
                ))}
            </div>
        </div>
    );
}
