import { useEffect, useState } from 'react';
import { Minus, Plus, X } from 'lucide-react';
import { cartItemApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import {
    formatPkr,
    itemHasDiscount,
    lineDiscount,
    lineGross,
    lineTotal,
} from '@/lib/posCartTotals';
import { DiscountModal } from './DiscountModal';

function discountLabel(item) {
    if (item.discount_type === 'percent') {
        return `${item.discount_value}% off`;
    }

    return `PKR ${formatPkr(item.discount_value)} off`;
}

export function CartItemRow({
    cartId,
    item,
    onUpdated,
    onRemoved,
    canApplyDiscount,
    stockWarning,
    readOnly = false,
    processing = false,
}) {
    const [showDiscount, setShowDiscount] = useState(false);
    const [saving, setSaving] = useState(false);
    const [qtyInput, setQtyInput] = useState(String(item.quantity));
    const [editingQty, setEditingQty] = useState(false);
    const { error, confirmRemoveItem } = usePosDialog();

    const locked = readOnly || processing || saving;
    const hasDiscount = itemHasDiscount(item);
    const gross = lineGross(item);
    const total = lineTotal(item);
    const saved = lineDiscount(item);

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
            const msg =
                err?.response?.data?.errors?.quantity?.[0] ||
                err?.response?.data?.message ||
                'Failed to update quantity.';
            error(msg);
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
            const msg =
                err?.response?.data?.errors?.quantity?.[0] ||
                err?.response?.data?.message ||
                'Failed to update quantity.';
            error(msg);
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
            const msg =
                err?.response?.data?.errors?.discount_value?.[0] ||
                err?.response?.data?.message ||
                'Failed to apply discount.';
            throw new Error(msg);
        } finally {
            setSaving(false);
        }
    }

    return (
        <>
            <div
                className={`relative rounded-xl border p-3.5 transition-colors ${
                    stockWarning
                        ? 'border-amber-500/40 bg-amber-500/5'
                        : 'border-rp-border bg-rp-surface-subtle'
                }`}
            >
                {!readOnly && (
                    <button
                        type="button"
                        onClick={handleRemove}
                        disabled={locked}
                        className="absolute top-2.5 right-2.5 flex h-6 w-6 items-center justify-center rounded-md border border-rp-border text-rp-text-muted transition-colors hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-400 disabled:opacity-40"
                        title="Remove item"
                    >
                        <X className="h-3 w-3" />
                    </button>
                )}

                <div className={readOnly ? '' : 'pr-8'}>
                    <p className="truncate text-sm font-semibold text-rp-text">{item.name}</p>
                    <p className="text-xs text-rp-text-muted">
                        {item.sku} · {formatPkr(item.unit_price)} × {item.quantity} ={' '}
                        <span className="text-rp-text-secondary">{formatPkr(gross)}</span>
                    </p>
                </div>

                {stockWarning && (
                    <p className="mt-1.5 text-xs font-medium text-amber-400">
                        {stockWarning.message}
                    </p>
                )}

                <div className="mt-3 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        {readOnly ? (
                            <span className="text-sm font-semibold text-rp-text">
                                Qty: {item.quantity}
                            </span>
                        ) : (
                            <>
                                <button
                                    type="button"
                                    onClick={() => changeQty(-1)}
                                    disabled={locked || item.quantity <= 1}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-rp-border bg-rp-surface-inset text-rp-text-secondary transition-colors hover:border-rp-text-muted hover:text-rp-text disabled:opacity-40"
                                >
                                    <Minus className="h-3.5 w-3.5" />
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
                                    aria-label="Quantity"
                                    className="w-12 rounded-lg border border-rp-border bg-rp-surface-inset px-1 py-1 text-center text-sm font-semibold text-rp-text focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 disabled:opacity-40"
                                />
                                <button
                                    type="button"
                                    onClick={() => changeQty(1)}
                                    disabled={locked}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-rp-border bg-rp-surface-inset text-rp-text-secondary transition-colors hover:border-rp-text-muted hover:text-rp-text disabled:opacity-40"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                </button>
                            </>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {hasDiscount && (
                            <span className="rounded-md bg-orange-500/15 px-2 py-1 text-[11px] font-medium text-orange-400">
                                {discountLabel(item)} (−PKR {formatPkr(saved)})
                            </span>
                        )}

                        {!hasDiscount && canApplyDiscount && (
                            <button
                                type="button"
                                onClick={() => setShowDiscount(true)}
                                disabled={locked}
                                className="rounded-md px-2 py-1 text-[11px] font-medium text-rp-text-muted transition-colors hover:bg-rp-surface-inset hover:text-rp-text-secondary disabled:opacity-40"
                            >
                                + Discount
                            </button>
                        )}

                        {hasDiscount && canApplyDiscount && (
                            <button
                                type="button"
                                onClick={() => setShowDiscount(true)}
                                disabled={locked}
                                className="rounded-md px-2 py-1 text-[11px] font-medium text-rp-text-muted transition-colors hover:bg-rp-surface-inset hover:text-rp-text-secondary disabled:opacity-40"
                            >
                                Edit
                            </button>
                        )}

                        <div className="flex flex-col items-end leading-tight">
                            {hasDiscount && (
                                <span className="text-[11px] text-rp-text-muted line-through">
                                    PKR {formatPkr(gross)}
                                </span>
                            )}
                            <span className="text-sm font-bold text-rp-text">
                                PKR {formatPkr(total)}
                            </span>
                        </div>
                    </div>
                </div>

                {item.notes && (
                    <p className="mt-1.5 text-xs italic text-rp-text-muted">{item.notes}</p>
                )}
            </div>

            {showDiscount && (
                <DiscountModal
                    item={item}
                    onSave={handleDiscountSaved}
                    onClose={() => setShowDiscount(false)}
                />
            )}
        </>
    );
}
