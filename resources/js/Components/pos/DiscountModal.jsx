import { useState } from 'react';

export function DiscountModal({ item, onSave, onClose }) {
    const [type, setType] = useState(item.discount_type || 'percent');
    const [value, setValue] = useState(item.discount_value ?? '');
    const [approverPin, setApproverPin] = useState('');
    const [needsApproval, setNeedsApproval] = useState(false);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);

    const gross = item.unit_price * item.quantity;
    const pct = type === 'percent' ? Number(value) : (Number(value) / gross) * 100;
    const requiresApproval = pct > 30;

    async function handleSubmit(e) {
        e.preventDefault();
        setError(null);

        if (requiresApproval && !needsApproval) {
            setNeedsApproval(true);
            return;
        }

        setSaving(true);
        try {
            await onSave(
                type,
                Number(value),
                requiresApproval,
                null,
            );
        } catch (err) {
            setError(err.message || 'Failed to apply discount.');
        } finally {
            setSaving(false);
        }
    }

    function clearDiscount() {
        onSave(null, null, false, null);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">
                        Apply Discount
                    </h3>
                    <button
                        onClick={onClose}
                        className="rounded p-1 text-zinc-400 hover:text-zinc-600"
                    >
                        ×
                    </button>
                </div>

                <p className="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {item.name} — PKR {gross.toLocaleString()}
                </p>

                {needsApproval ? (
                    <div>
                        <p className="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                            Discounts above 30% require manager approval. Ask a manager with{' '}
                            <strong>pos.approve-discount</strong> permission to confirm.
                        </p>
                        <p className="mb-2 text-xs text-zinc-500">
                            Note: The manager must approve this discount in the system.
                        </p>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setNeedsApproval(false)}
                                className="flex-1 rounded-lg border border-zinc-300 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-600"
                            >
                                Back
                            </button>
                            <button
                                onClick={() => onSave(type, Number(value), true, null)}
                                disabled={saving}
                                className="flex-1 rounded-lg bg-amber-600 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                            >
                                Apply with Approval
                            </button>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                Discount type
                            </label>
                            <div className="flex gap-2">
                                {['percent', 'flat'].map((t) => (
                                    <button
                                        key={t}
                                        type="button"
                                        onClick={() => setType(t)}
                                        className={`flex-1 rounded-lg border py-2 text-sm font-medium transition-colors ${
                                            type === t
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600'
                                        }`}
                                    >
                                        {t === 'percent' ? '% Percent' : 'PKR Flat'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                {type === 'percent' ? 'Percentage' : 'Amount (PKR)'}
                            </label>
                            <input
                                type="number"
                                min="0"
                                max={type === 'percent' ? 100 : gross}
                                step="0.01"
                                value={value}
                                onChange={(e) => setValue(e.target.value)}
                                required
                                autoFocus
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                        </div>

                        {value && (
                            <p className="text-xs text-zinc-500">
                                New total:{' '}
                                <strong>
                                    PKR{' '}
                                    {(type === 'percent'
                                        ? gross * (1 - Number(value) / 100)
                                        : gross - Number(value)
                                    ).toLocaleString()}
                                </strong>
                                {requiresApproval && (
                                    <span className="ml-2 text-amber-600">
                                        ⚠ Requires manager approval
                                    </span>
                                )}
                            </p>
                        )}

                        {error && (
                            <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
                        )}

                        <div className="flex gap-2">
                            {item.discount_type && (
                                <button
                                    type="button"
                                    onClick={clearDiscount}
                                    className="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-600"
                                >
                                    Remove
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 rounded-lg border border-zinc-300 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-600"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={saving || !value}
                                className="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {requiresApproval ? 'Request Approval' : 'Apply'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
