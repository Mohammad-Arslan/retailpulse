import { Plus, Search, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

export default function ComboBundleBuilder({ items, onChange, excludeProductId, error }) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);

    const search = useCallback(
        async (term) => {
            setQuery(term);
            if (term.length < 1) {
                setResults([]);
                return;
            }

            setSearching(true);
            try {
                const { data } = await axios.get(route('admin.product-variants.search'), {
                    params: {
                        q: term,
                        exclude_product_id: excludeProductId,
                    },
                });
                setResults(data);
            } finally {
                setSearching(false);
            }
        },
        [excludeProductId],
    );

    const addItem = (variant) => {
        if (items.some((i) => i.child_variant_id === variant.id)) {
            return;
        }

        onChange([
            ...items,
            {
                child_variant_id: variant.id,
                quantity: '1',
                child: variant,
            },
        ]);
        setQuery('');
        setResults([]);
    };

    const updateQty = (index, quantity) => {
        onChange(
            items.map((item, i) => (i === index ? { ...item, quantity } : item)),
        );
    };

    const removeItem = (index) => {
        onChange(items.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-4">
            {error && (
                <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            )}

            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-rp-text-muted" />
                <input
                    value={query}
                    onChange={(e) => search(e.target.value)}
                    placeholder={t('pages.products.placeholders.searchVariants')}
                    className="rp-form-input pl-9"
                />
                {searching && (
                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-rp-text-muted">
                        …
                    </span>
                )}
                {results.length > 0 && (
                    <ul className="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-rp-border bg-rp-surface shadow-lg">
                        {results.map((variant) => (
                            <li key={variant.id}>
                                <button
                                    type="button"
                                    onClick={() => addItem(variant)}
                                    className="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-teal-50 dark:hover:bg-teal-500/10"
                                >
                                    <span className="font-medium text-rp-text">
                                        {variant.product_name} — {variant.name}
                                    </span>
                                    <span className="font-mono text-xs text-rp-text-muted">
                                        {variant.sku}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-rp-text-muted">
                    {t('pages.products.bundleEmpty')}
                </p>
            ) : (
                <ul className="divide-y divide-rp-border rounded-xl border border-rp-border">
                    {items.map((item, index) => (
                        <li
                            key={item.child_variant_id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-rp-text">
                                    {item.child?.product_name ?? ''} —{' '}
                                    {item.child?.name ?? item.child?.sku}
                                </p>
                                <p className="font-mono text-xs text-rp-text-muted">
                                    {item.child?.sku}
                                </p>
                            </div>
                            <input
                                type="number"
                                min="0.0001"
                                step="any"
                                value={item.quantity}
                                onChange={(e) => updateQty(index, e.target.value)}
                                className="rp-form-input w-20 text-center"
                            />
                            <button
                                type="button"
                                onClick={() => removeItem(index)}
                                className="rounded-lg p-2 text-rp-text-muted hover:text-red-600"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <p className="flex items-center gap-1 text-xs text-rp-text-muted">
                <Plus className="h-3 w-3" />
                {t('pages.products.bundleHint')}
            </p>
        </div>
    );
}
