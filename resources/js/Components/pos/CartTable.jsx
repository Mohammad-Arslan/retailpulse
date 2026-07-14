import ScrollArea from '@/Components/common/ScrollArea';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { cartItemApi } from '@/lib/posApi';
import {
    estimateCartTax,
    formatPkr,
    lineDiscount,
    lineGross,
    lineTotal,
} from '@/lib/posCartTotals';
import { cn } from '@/lib/utils';
import { Minus, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DiscountModal } from './DiscountModal';

function CartLineRow({
    cartId,
    item,
    onUpdated,
    onRemoved,
    canApplyDiscount,
    stockWarning,
    readOnly,
    processing,
    taxEnabled,
    defaultTaxRate,
    taxMode,
    currency,
    taxRatePct,
}) {
    const [showDiscount, setShowDiscount] = useState(false);
    const [saving, setSaving] = useState(false);
    const [qtyInput, setQtyInput] = useState(String(item.quantity));
    const [editingQty, setEditingQty] = useState(false);
    const { error, confirmRemoveItem } = usePosDialog();
    const { t } = useTranslation();

    const locked = readOnly || processing || saving;
    const gross = lineGross(item);
    const total = lineTotal(item);
    const discount = lineDiscount(item);
    const hasDiscount = discount > 0;
    const taxAmount = taxEnabled
        ? estimateCartTax(total, defaultTaxRate, taxMode).taxAmount
        : 0;

    useEffect(() => {
        if (!editingQty) {
            setQtyInput(String(item.quantity));
        }
    }, [item.quantity, editingQty]);

    async function changeQty(delta) {
        const next = item.quantity + delta;
        if (next < 1 || locked) return;
        setSaving(true);
        try {
            const updated = await cartItemApi.update(cartId, item.id, { quantity: next });
            onUpdated(updated);
            setQtyInput(String(updated.quantity));
        } catch (err) {
            error(
                err?.response?.data?.errors?.quantity?.[0] ||
                    err?.response?.data?.message ||
                    'Failed to update quantity.',
            );
        } finally {
            setSaving(false);
        }
    }

    async function commitQty(raw) {
        const next = Number.parseInt(raw, 10);
        if (!Number.isInteger(next) || next < 1) {
            setQtyInput(String(item.quantity));
            return;
        }
        if (next === item.quantity || locked) {
            setQtyInput(String(item.quantity));
            return;
        }

        setSaving(true);
        try {
            const updated = await cartItemApi.update(cartId, item.id, { quantity: next });
            onUpdated(updated);
            setQtyInput(String(updated.quantity));
        } catch (err) {
            setQtyInput(String(item.quantity));
            error(
                err?.response?.data?.errors?.quantity?.[0] ||
                    err?.response?.data?.message ||
                    'Failed to update quantity.',
            );
        } finally {
            setSaving(false);
        }
    }

    function handleQtyChange(e) {
        const value = e.target.value;
        if (value === '' || /^\d+$/.test(value)) {
            setQtyInput(value);
        }
    }

    function handleQtyBlur() {
        setEditingQty(false);
        void commitQty(qtyInput);
    }

    function handleQtyKeyDown(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.currentTarget.blur();
        }
        if (e.key === 'Escape') {
            setQtyInput(String(item.quantity));
            setEditingQty(false);
            e.currentTarget.blur();
        }
    }

    async function handleRemove() {
        if (locked) return;
        const confirmed = await confirmRemoveItem(item.name);
        if (!confirmed) return;
        setSaving(true);
        try {
            await cartItemApi.remove(cartId, item.id);
            onRemoved(item.id);
        } catch (err) {
            error(err?.response?.data?.message || 'Failed to remove item.');
        } finally {
            setSaving(false);
        }
    }

    async function handleDiscountSaved(discountType, discountValue, approved, approverId) {
        setSaving(true);
        try {
            const updated = await cartItemApi.update(cartId, item.id, {
                discount_type: discountType,
                discount_value: discountValue,
                approved,
                approver_id: approverId,
            });
            onUpdated(updated);
            setShowDiscount(false);
        } catch (err) {
            throw new Error(
                err?.response?.data?.errors?.discount_value?.[0] ||
                    err?.response?.data?.message ||
                    'Failed to apply discount.',
            );
        } finally {
            setSaving(false);
        }
    }

    return (
        <>
            <tr
                className={cn(
                    'border-b border-[var(--pos-border)] even:bg-[var(--pos-bg-subtle)]',
                    stockWarning && 'bg-amber-50 dark:bg-amber-500/10',
                )}
            >
                <td className="px-[18px] py-2.5 align-top">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <div className="text-[12.5px] font-semibold text-[var(--pos-text-1)]">
                                {item.name}
                            </div>
                            <div className="pos-mono mt-0.5 text-[10.5px] text-[var(--pos-text-3)]">
                                {item.sku}
                            </div>
                            {stockWarning ? (
                                <div className="mt-1 text-[11px] font-medium text-amber-600">
                                    {stockWarning.message}
                                </div>
                            ) : null}
                            {hasDiscount ? (
                                <div className="mt-1 text-[11px] font-medium text-emerald-600">
                                    −{currency} {formatPkr(discount)}
                                    {gross > total ? (
                                        <span className="ml-1 text-[var(--pos-text-3)] line-through">
                                            {formatPkr(gross)}
                                        </span>
                                    ) : null}
                                </div>
                            ) : null}
                            {!readOnly && canApplyDiscount ? (
                                <button
                                    type="button"
                                    onClick={() => setShowDiscount(true)}
                                    disabled={locked}
                                    className="mt-1 inline-block text-[11px] font-semibold text-[var(--pos-teal-700)] hover:underline disabled:opacity-40"
                                >
                                    {hasDiscount
                                        ? t('pages.pos.cart.editDiscount')
                                        : t('pages.pos.cart.addDiscount')}
                                </button>
                            ) : null}
                        </div>
                        {!readOnly ? (
                            <button
                                type="button"
                                onClick={handleRemove}
                                disabled={locked}
                                className="shrink-0 text-[var(--pos-text-3)] hover:text-[var(--pos-danger)] disabled:opacity-40"
                                title={t('pages.pos.cart.removeItem')}
                                aria-label={t('pages.pos.cart.removeItem')}
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        ) : null}
                    </div>
                </td>
                <td className="px-[18px] py-2.5 text-right align-top">
                    {readOnly ? (
                        <span className="pos-mono text-[12.5px] font-semibold">{item.quantity}</span>
                    ) : (
                        <div className="ml-auto inline-flex overflow-hidden rounded-[7px] border border-[var(--pos-border)]">
                            <button
                                type="button"
                                onClick={() => changeQty(-1)}
                                disabled={locked || item.quantity <= 1}
                                className="flex h-[22px] w-[22px] items-center justify-center bg-[var(--pos-bg-sunken)] text-[13px] font-bold text-[var(--pos-text-1)] disabled:opacity-40"
                            >
                                <Minus className="h-3 w-3" />
                            </button>
                            <input
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]*"
                                value={qtyInput}
                                onChange={handleQtyChange}
                                onFocus={() => setEditingQty(true)}
                                onBlur={handleQtyBlur}
                                onKeyDown={handleQtyKeyDown}
                                disabled={locked}
                                aria-label={t('pages.pos.cart.quantity')}
                                className="pos-mono h-[22px] w-7 border-0 bg-transparent text-center text-xs font-bold text-[var(--pos-text-1)] outline-none disabled:opacity-40"
                            />
                            <button
                                type="button"
                                onClick={() => changeQty(1)}
                                disabled={locked}
                                className="flex h-[22px] w-[22px] items-center justify-center bg-[var(--pos-bg-sunken)] text-[13px] font-bold text-[var(--pos-text-1)] disabled:opacity-40"
                            >
                                <Plus className="h-3 w-3" />
                            </button>
                        </div>
                    )}
                </td>
                <td className="pos-mono px-[18px] py-2.5 text-right align-top text-[12.5px] font-semibold whitespace-nowrap">
                    {formatPkr(item.unit_price)}
                </td>
                {taxEnabled ? (
                    <td className="px-[18px] py-2.5 text-right align-top whitespace-nowrap">
                        {taxRatePct > 0 ? (
                            <span className="block text-[11px] text-[var(--pos-text-2)]">
                                {taxRatePct}%
                            </span>
                        ) : null}
                        <span className="pos-mono text-[12.5px] font-semibold">
                            {formatPkr(taxAmount)}
                        </span>
                    </td>
                ) : null}
                <td className="pos-mono px-[18px] py-2.5 text-right align-top text-[12.5px] font-bold whitespace-nowrap">
                    {formatPkr(total)}
                </td>
            </tr>

            {showDiscount ? (
                <DiscountModal
                    item={item}
                    onSave={handleDiscountSaved}
                    onClose={() => setShowDiscount(false)}
                />
            ) : null}
        </>
    );
}

export function CartTable({
    cart,
    stockWarnings,
    taxEnabled,
    defaultTaxRate,
    taxMode,
    currency,
    onItemUpdated,
    onItemRemoved,
    canDiscount,
    processing,
}) {
    const { t } = useTranslation();
    const items = cart?.items || [];
    const isCompleting = cart?.status === 'completing';
    const isEditable = cart?.status === 'active' || cart?.status === 'suspended';
    const taxRatePct = Math.round(parseFloat(defaultTaxRate || '0') * 100);
    const lineCount = items.length;

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-[var(--pos-bg)]">
            <div className="flex shrink-0 items-center justify-between border-b border-[var(--pos-border)] px-[18px] py-3">
                <span className="text-[11px] font-extrabold tracking-[0.08em] text-[var(--pos-text-3)]">
                    {t('pages.pos.cartPanel').toUpperCase()}
                </span>
                <span className="rounded-full bg-[var(--pos-teal-50)] px-2 py-0.5 text-[11px] font-bold text-[var(--pos-teal-700)]">
                    {t('pages.pos.cart.lineCount', { count: lineCount })}
                </span>
            </div>

            {isCompleting ? (
                <p className="shrink-0 border-b border-orange-200 bg-orange-50 px-[18px] py-2 text-xs text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300">
                    {t('pages.pos.cart.lockedForPayment')}
                </p>
            ) : null}

            <ScrollArea className="pos-scroll min-h-0 flex-1 overflow-y-auto overflow-x-auto">
                <table className="w-full min-w-[320px] border-collapse">
                    <thead>
                        <tr>
                            <th className="sticky top-0 z-1 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-[18px] py-2 text-left text-[10px] font-extrabold tracking-[0.06em] text-[var(--pos-text-3)]">
                                {t('pages.pos.cart.colItem')}
                            </th>
                            <th className="sticky top-0 z-1 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-[18px] py-2 text-right text-[10px] font-extrabold tracking-[0.06em] text-[var(--pos-text-3)]">
                                {t('pages.pos.cart.colQty')}
                            </th>
                            <th className="sticky top-0 z-1 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-[18px] py-2 text-right text-[10px] font-extrabold tracking-[0.06em] text-[var(--pos-text-3)]">
                                {t('pages.pos.cart.colPrice')}
                            </th>
                            {taxEnabled ? (
                                <th className="sticky top-0 z-1 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-[18px] py-2 text-right text-[10px] font-extrabold tracking-[0.06em] text-[var(--pos-text-3)]">
                                    {t('pages.pos.cart.colTax')}
                                </th>
                            ) : null}
                            <th className="sticky top-0 z-1 border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-[18px] py-2 text-right text-[10px] font-extrabold tracking-[0.06em] text-[var(--pos-text-3)]">
                                {t('pages.pos.cart.colTotal')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={taxEnabled ? 5 : 4}
                                    className="px-[18px] py-10 text-center text-xs text-[var(--pos-text-3)]"
                                >
                                    {t('pages.pos.cart.emptyHint')}
                                </td>
                            </tr>
                        ) : (
                            items.map((item) => (
                                <CartLineRow
                                    key={item.id}
                                    cartId={cart.id}
                                    item={item}
                                    onUpdated={onItemUpdated}
                                    onRemoved={onItemRemoved}
                                    canApplyDiscount={canDiscount && isEditable}
                                    stockWarning={stockWarnings?.[item.id] || null}
                                    readOnly={isCompleting}
                                    processing={processing}
                                    taxEnabled={taxEnabled}
                                    defaultTaxRate={defaultTaxRate}
                                    taxMode={taxMode}
                                    currency={currency}
                                    taxRatePct={taxRatePct}
                                />
                            ))
                        )}
                    </tbody>
                </table>
            </ScrollArea>
        </div>
    );
}
