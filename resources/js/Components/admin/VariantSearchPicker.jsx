import { cn } from '@/lib/utils';
import axios from 'axios';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export default function VariantSearchPicker({
    value,
    onChange,
    error,
    placeholder = 'Search SKU or product name...',
}) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);

    const search = useCallback(async (q) => {
        setLoading(true);
        try {
            const { data } = await axios.get(route('admin.product-variants.search'), {
                params: { q },
            });
            setResults(data);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const timer = setTimeout(() => search(term), 250);

        return () => clearTimeout(timer);
    }, [term, open, search]);

    const select = (variant) => {
        onChange(variant);
        setOpen(false);
        setTerm('');
    };

    return (
        <div className="relative">
            {value ? (
                <div className="flex items-center justify-between gap-2 rounded-lg border border-rp-border bg-rp-surface px-3 py-2">
                    <div>
                        <div className="text-sm font-medium text-rp-text">{value.product_name}</div>
                        <div className="text-xs text-rp-text-muted">
                            {value.name} · {value.sku}
                        </div>
                    </div>
                    <button
                        type="button"
                        className="text-xs text-teal-600 hover:underline"
                        onClick={() => onChange(null)}
                    >
                        Change
                    </button>
                </div>
            ) : (
                <>
                    <div className="rp-search-inset">
                        <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                        <input
                            value={term}
                            onChange={(e) => {
                                setTerm(e.target.value);
                                setOpen(true);
                            }}
                            onFocus={() => setOpen(true)}
                            placeholder={placeholder}
                            className="rp-search-input"
                        />
                    </div>
                    {open && (
                        <ul
                            className={cn(
                                'absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-rp-border bg-white shadow-lg dark:bg-ink-900',
                            )}
                        >
                            {loading && (
                                <li className="px-3 py-2 text-xs text-rp-text-muted">Searching…</li>
                            )}
                            {!loading && results.length === 0 && (
                                <li className="px-3 py-2 text-xs text-rp-text-muted">No variants found.</li>
                            )}
                            {results.map((variant) => (
                                <li key={variant.id}>
                                    <button
                                        type="button"
                                        className="w-full px-3 py-2 text-left hover:bg-sand-100 dark:hover:bg-ink-800"
                                        onClick={() => select(variant)}
                                    >
                                        <div className="text-sm font-medium text-rp-text">
                                            {variant.product_name}
                                        </div>
                                        <div className="text-xs text-rp-text-muted">
                                            {variant.name} · {variant.sku}
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
            {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
        </div>
    );
}
