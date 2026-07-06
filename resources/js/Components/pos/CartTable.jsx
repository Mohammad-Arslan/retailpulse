import { useEffect, useState } from 'react';
import { Minus, Plus, ShoppingCart, X } from 'lucide-react';
import { cartItemApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { DiscountModal } from './DiscountModal';
import {
    estimateCartTax,
    formatPkr,
    lineDiscount,
    lineGross,
    lineTotal,
} from '@/lib/posCartTotals';

function TaxCell({ item, taxEnabled, defaultTaxRate, taxMode }) {
    if (!taxEnabled) return <span className="text-rp-text-muted">—</span>;
    const lt = lineTotal(item);
    const { taxAmount } = estimateCartTax(lt, defaultTaxRate, taxMode);
    return <span>{formatPkr(taxAmount)}</span>;
}

function CartTableRow({
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
}) {
    const [showDiscount, setShowDiscount] = useState(false);
    const [saving, setSaving] = useState(false);
    const [qtyInput, setQtyInput] = useState(String(item.quantity));
    const [editingQty, setEditingQty] = useState(false);
    const { error, confirmRemoveItem } = usePosDialog();

    const locked = readOnly || processing || saving;
    const gross = lineGross(item);
    const total = lineTotal(item);
    const discount = lineDiscount(item);
    const hasDiscount = discount > 0;

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
            <tr className={`group border-b border-rp-border transition-colors hover:bg-rp-surface-subtle ${stockWarning ? 'bg-amber-500/5' : ''}`}>
                {/* Item */}
                <td className="px-4 py-3">
                    <p className="text-sm font-semibold text-rp-text leading-tight">{item.name}</p>
                    <p className="text-xs text-rp-text-muted mt-0.5">{item.sku}</p>
                    {stockWarning && (
                        <p className="text-xs font-medium text-amber-400 mt-0.5">{stockWarning.message}</p>
                    )}
                </td>

                {/* Price */}
                <td className="px-4 py-3 text-sm text-rp-text-secondary whitespace-nowrap">
                    {currency} {formatPkr(item.unit_price)}
                </td>

                {/* Qty */}
                <td className="px-4 py-3">
                    {readOnly ? (
                        <span className="text-sm text-rp-text">{item.quantity}</span>
                    ) : (
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => changeQty(-1)}
                                disabled={locked || item.quantity <= 1}
                                className="flex h-7 w-7 items-center justify-center rounded-md border border-rp-border bg-rp-surface-inset text-rp-text-secondary hover:border-rp-text-muted hover:text-rp-text disabled:opacity-40 transition-colors"
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
                                aria-label="Quantity"
                                className="w-12 rounded-md border border-rp-border bg-rp-surface-inset px-1 py-1 text-center text-sm font-semibold text-rp-text focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 disabled:opacity-40"
                            />
                            <button
                                type="button"
                                onClick={() => changeQty(1)}
                                disabled={locked}
                                className="flex h-7 w-7 items-center justify-center rounded-md border border-rp-border bg-rp-surface-inset text-rp-text-secondary hover:border-rp-text-muted hover:text-rp-text disabled:opacity-40 transition-colors"
                            >
                                <Plus className="h-3 w-3" />
                            </button>
                        </div>
                    )}
                </td>

                {/* Discount */}
                <td className="px-4 py-3">
                    {hasDiscount ? (
                        <div className="flex flex-col gap-0.5">
                            <span className="text-xs font-medium text-emerald-400">
                                {item.discount_type === 'percent'
                                    ? `${item.discount_value}%`
                                    : `${currency} ${formatPkr(item.discount_value)}`}
                            </span>
                            <span className="text-xs text-rp-text-muted">−{currency} {formatPkr(discount)}</span>
                            {!readOnly && canApplyDiscount && (
                                <button
                                    type="button"
                                    onClick={() => setShowDiscount(true)}
                                    disabled={locked}
                                    className="text-left text-[10px] text-rp-text-muted hover:text-rp-text-secondary disabled:opacity-40 transition-colors"
                                >
                                    Edit
                                </button>
                            )}
                        </div>
                    ) : (
                        !readOnly && canApplyDiscount ? (
                            <button
                                type="button"
                                onClick={() => setShowDiscount(true)}
                                disabled={locked}
                                className="text-xs font-medium text-blue-400 hover:text-blue-300 disabled:opacity-40 transition-colors"
                            >
                                + Discount
                            </button>
                        ) : (
                            <span className="text-xs text-rp-text-muted">—</span>
                        )
                    )}
                </td>

                {/* Tax */}
                <td className="px-4 py-3 text-sm text-rp-text-secondary whitespace-nowrap">
                    <TaxCell
                        item={item}
                        taxEnabled={taxEnabled}
                        defaultTaxRate={defaultTaxRate}
                        taxMode={taxMode}
                    />
                </td>

                {/* Total */}
                <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-3">
                        <div className="text-right">
                            {hasDiscount && (
                                <p className="text-xs text-rp-text-muted line-through">{currency} {formatPkr(gross)}</p>
                            )}
                            <p className="text-sm font-bold text-rp-text">{currency} {formatPkr(total)}</p>
                        </div>
                        {!readOnly && (
                            <button
                                type="button"
                                onClick={handleRemove}
                                disabled={locked}
                                className="flex h-7 w-7 items-center justify-center rounded-md border border-rp-border text-rp-text-muted opacity-0 group-hover:opacity-100 hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-400 disabled:opacity-40 transition-all"
                                title="Remove item"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </td>
            </tr>

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
    const items = cart?.items || [];
    const isCompleting = cart?.status === 'completing';
    const isEditable = cart?.status === 'active' || cart?.status === 'suspended';

    if (items.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center text-center py-16">
                <ShoppingCart className="h-16 w-16 text-rp-text-muted opacity-20 mb-4" />
                <p className="text-lg font-semibold text-rp-text">Your cart is empty</p>
                <p className="text-sm text-rp-text-muted mt-1">Scan or search for a product to add it to the cart</p>
            </div>
        );
    }

    return (
        <div className="flex-1 overflow-auto">
            <table className="w-full text-left">
                <thead className="sticky top-0 z-10 bg-rp-surface border-b border-rp-border">
                    <tr>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">Item</th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">Price</th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">Qty</th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">Discount</th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">Tax</th>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {isCompleting && (
                        <tr>
                            <td colSpan={6} className="px-4 py-2">
                                <p className="text-xs text-orange-300 bg-orange-500/10 border border-orange-500/30 rounded-lg px-3 py-2">
                                    Cart locked for payment — items are read-only.
                                </p>
                            </td>
                        </tr>
                    )}
                    {items.map((item) => (
                        <CartTableRow
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
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}
