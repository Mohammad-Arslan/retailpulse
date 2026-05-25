import { useCallback, useEffect, useRef, useState } from 'react';
import { Package, Search } from 'lucide-react';
import { searchApi } from '@/lib/posApi';
import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';

const DEBOUNCE_MS = 300;

export function ProductSearch({ branchId, onAddProduct, inputRef: externalRef }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(-1);
    const [open, setOpen] = useState(false);
    const internalRef = useRef(null);
    const inputRef = externalRef || internalRef;
    const debounceRef = useRef(null);
    const listRef = useRef(null);

    const doSearch = useCallback(
        async (q) => {
            if (!q.trim()) {
                setResults([]);
                setOpen(false);
                return;
            }

            setLoading(true);
            try {
                const res = await searchApi.search(q, branchId);
                setResults(res.results || []);
                setOpen(true);
                setSelectedIndex(-1);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        },
        [branchId],
    );

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => doSearch(query), DEBOUNCE_MS);
        return () => clearTimeout(debounceRef.current);
    }, [query, doSearch]);

    const handleBarcodeDetected = useCallback((barcode) => {
        setQuery(barcode);
    }, []);

    useBarcodeScanner(handleBarcodeDetected, true);

    function addResult(result) {
        onAddProduct(result);
        setQuery('');
        setResults([]);
        setOpen(false);
        inputRef.current?.focus();
    }

    function handleKeyDown(e) {
        if (!open || !results.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((i) => Math.min(i + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const idx = selectedIndex >= 0 ? selectedIndex : 0;
            if (results[idx]) addResult(results[idx]);
        } else if (e.key === 'Escape') {
            setOpen(false);
            setQuery('');
        }
    }

    useEffect(() => {
        if (selectedIndex >= 0 && listRef.current) {
            listRef.current.children[selectedIndex]?.scrollIntoView({ block: 'nearest' });
        }
    }, [selectedIndex]);

    const showEmptyState = !query.trim() && !open;

    return (
        <div className="flex h-full flex-col">
            <div className="relative shrink-0">
                <Search className="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                <input
                    ref={inputRef}
                    type="search"
                    placeholder="Search by name, SKU, or scan barcode…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onFocus={() => results.length > 0 && setOpen(true)}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                    className="w-full rounded-xl border border-rp-border bg-rp-surface-subtle py-3.5 pr-14 pl-11 text-sm text-rp-text outline-none transition placeholder:text-rp-text-muted focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20"
                    autoComplete="off"
                />
                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rounded-md border border-rp-border bg-rp-surface-inset px-1.5 py-0.5 text-[10px] font-medium text-rp-text-muted">
                    F2
                </span>
                {loading && (
                    <div className="absolute top-1/2 right-12 -translate-y-1/2">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
                    </div>
                )}
            </div>

            <p className="mt-2 shrink-0 text-[11px] text-rp-text-muted">
                Barcode scan auto-detected · EAN-13, EAN-8, Code 128
            </p>

            <div className="relative mt-4 min-h-0 flex-1">
                {open && results.length > 0 && (
                    <ul
                        ref={listRef}
                        className="absolute z-40 max-h-full w-full overflow-y-auto rounded-xl border border-rp-border bg-rp-surface shadow-xl"
                    >
                        {results.map((r, i) => (
                            <li
                                key={r.id}
                                onMouseDown={() => addResult(r)}
                                className={`flex cursor-pointer items-center justify-between px-4 py-3 text-sm transition-colors ${
                                    i === selectedIndex
                                        ? 'bg-blue-500/10'
                                        : 'hover:bg-rp-surface-subtle'
                                }`}
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-rp-text">{r.name}</p>
                                    <p className="text-xs text-rp-text-muted">{r.sku}</p>
                                </div>
                                <div className="ml-4 flex shrink-0 flex-col items-end gap-1">
                                    <span className="font-semibold text-rp-text">
                                        PKR {r.unit_price.toLocaleString()}
                                    </span>
                                    {r.in_stock ? (
                                        <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">
                                            {r.available_stock} in stock
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-medium text-red-400">
                                            Out of stock
                                        </span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {open && query && results.length === 0 && !loading && (
                    <div className="absolute z-40 w-full rounded-xl border border-rp-border bg-rp-surface p-6 text-center text-sm text-rp-text-muted shadow-xl">
                        No products found for &ldquo;{query}&rdquo;
                    </div>
                )}

                {showEmptyState && (
                    <div className="flex h-full flex-col items-center justify-center text-rp-text-muted">
                        <Package className="h-16 w-16 stroke-1 opacity-20" />
                        <p className="mt-4 text-sm">Products appear here as you type</p>
                    </div>
                )}
            </div>
        </div>
    );
}
