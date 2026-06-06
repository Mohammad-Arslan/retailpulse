import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Scan, Search, X } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PosTopbar } from '@/Components/pos/PosTopbar';
import { PinModal } from '@/Components/pos/PinModal';
import { CartTabs } from '@/Components/pos/CartTabs';
import { CartTable } from '@/Components/pos/CartTable';
import { CartBottomBar } from '@/Components/pos/CartBottomBar';
import { cartApi, cartItemApi, searchApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { usePosKeyboard } from '@/Hooks/usePosKeyboard';
import { usePosWebSocket } from '@/Hooks/usePosWebSocket';
import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';
import { useCan } from '@/Hooks/useCan';

const PIN_INACTIVITY_MS = 30 * 60 * 1000;
const SEARCH_DEBOUNCE_MS = 300;

function ProductDropdown({ results, loading, query, onAdd, processing }) {
    if (!query) return null;

    if (loading) {
        return (
            <div className="absolute top-full left-0 right-0 z-50 mt-1 rounded-xl border border-rp-border bg-rp-surface p-3 shadow-xl">
                <p className="text-xs text-rp-text-muted">Searching…</p>
            </div>
        );
    }
    if (results.length === 0) {
        return (
            <div className="absolute top-full left-0 right-0 z-50 mt-1 rounded-xl border border-rp-border bg-rp-surface p-3 shadow-xl">
                <p className="text-xs text-rp-text-muted">No products found for "{query}"</p>
            </div>
        );
    }
    return (
        <div className="absolute top-full left-0 right-0 z-50 mt-1 max-h-80 overflow-y-auto rounded-xl border border-rp-border bg-rp-surface py-1 shadow-xl">
            {results.map((variant) => (
                <button
                    key={variant.id}
                    type="button"
                    // onMouseDown prevents the input blur from firing before onClick,
                    // which would close the dropdown before the click is processed.
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => onAdd(variant)}
                    disabled={processing || !variant.in_stock}
                    className="flex w-full items-center justify-between px-4 py-2.5 text-left hover:bg-rp-surface-subtle disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <div>
                        <p className="text-sm font-medium text-rp-text">{variant.name}</p>
                        <p className="text-xs text-rp-text-muted">{variant.sku}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-sm font-semibold text-rp-text">
                            PKR {variant.unit_price?.toLocaleString?.() ?? variant.unit_price}
                        </p>
                        {!variant.in_stock ? (
                            <p className="text-xs text-red-400">Out of stock</p>
                        ) : (
                            <p className="text-xs text-emerald-400">{variant.available_stock} in stock</p>
                        )}
                    </div>
                </button>
            ))}
        </div>
    );
}

export default function PosIndex({ hasPin, lockout: initialLockout, categories = [], posConfig = {} }) {
    const { branch } = usePage().props;
    const can = useCan();
    const { error, warning, success, confirmVoidCart, confirmCloseCart } = usePosDialog();

    const branchId = branch?.active?.id;

    const [pinVerified, setPinVerified] = useState(false);
    const [pinLockout, setPinLockout] = useState(initialLockout);
    const lastActivityRef = useRef(Date.now());
    const sessionStartRef = useRef(Date.now());

    const [carts, setCarts] = useState([]);
    const [activeCartId, setActiveCartId] = useState(null);
    const [stockWarnings, setStockWarnings] = useState({});
    const [processing, setProcessing] = useState(false);
    const [undoStack, setUndoStack] = useState([]);

    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [products, setProducts] = useState([]);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [searchFocused, setSearchFocused] = useState(false);

    const searchInputRef = useRef(null);
    const searchWrapRef = useRef(null);
    // Cache: query string → results array. Avoids re-fetching the same query.
    const searchCacheRef = useRef(new Map());

    const activeCart = useMemo(
        () => carts.find((c) => c.id === activeCartId) ?? null,
        [carts, activeCartId],
    );

    const taxRatePct = parseFloat(posConfig.default_tax_rate ?? '0') * 100;

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/pos-sw.js', { scope: '/' }).catch(() => {});
        }
    }, []);

    useEffect(() => {
        if (!pinVerified) return;

        cartApi.list().then((data) => {
            const list = Array.isArray(data) ? data : [];
            setCarts(list);
            if (list.length > 0) {
                setActiveCartId((current) =>
                    current && list.some((c) => c.id === current) ? current : list[0].id,
                );
            }
        });
    }, [pinVerified]);

    useEffect(() => {
        if (!pinVerified) return;

        function resetActivity() {
            lastActivityRef.current = Date.now();
        }

        window.addEventListener('mousemove', resetActivity);
        window.addEventListener('keydown', resetActivity);

        const check = setInterval(() => {
            if (Date.now() - lastActivityRef.current > PIN_INACTIVITY_MS) {
                setPinVerified(false);
            }
        }, 60_000);

        return () => {
            window.removeEventListener('mousemove', resetActivity);
            window.removeEventListener('keydown', resetActivity);
            clearInterval(check);
        };
    }, [pinVerified]);

    function replaceCart(updated) {
        setCarts((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
    }

    function removeCartFromList(cartId) {
        setCarts((prev) => {
            const next = prev.filter((c) => c.id !== cartId);
            setActiveCartId((current) => (current === cartId ? (next[0]?.id ?? null) : current));
            return next;
        });
        setStockWarnings({});
    }

    function updateItemInCart(cartId, updatedItem) {
        setCarts((prev) =>
            prev.map((c) => {
                if (c.id !== cartId) return c;
                return {
                    ...c,
                    items: c.items.map((i) => (i.id === updatedItem.id ? updatedItem : i)),
                };
            }),
        );
        setStockWarnings((prev) => {
            const next = { ...prev };
            delete next[updatedItem.id];
            return next;
        });
    }

    function removeItemFromCart(cartId, itemId) {
        setCarts((prev) =>
            prev.map((c) => {
                if (c.id !== cartId) return c;
                return { ...c, items: c.items.filter((i) => i.id !== itemId) };
            }),
        );
        setStockWarnings((prev) => {
            const next = { ...prev };
            delete next[itemId];
            return next;
        });
    }

    const handleStockChanged = useCallback(
        (event) => {
            if (!activeCart) return;

            const affected = activeCart.items?.filter(
                (i) => i.product_variant_id === event.variant_id,
            );

            if (!affected?.length) return;

            cartApi.stockWarnings(activeCart.id).then((res) => {
                setStockWarnings((prev) => ({ ...prev, ...res.warnings }));
            });
        },
        [activeCart],
    );

    usePosWebSocket(activeCart?.items, handleStockChanged);

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedQuery(searchQuery), SEARCH_DEBOUNCE_MS);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    useEffect(() => {
        if (!pinVerified || !branchId || !debouncedQuery) {
            setProducts([]);
            return;
        }

        // Return cached results instantly — no spinner, no wait.
        if (searchCacheRef.current.has(debouncedQuery)) {
            setProducts(searchCacheRef.current.get(debouncedQuery));
            return;
        }

        let cancelled = false;
        setCatalogLoading(true);
        searchApi
            .search(debouncedQuery, branchId)
            .then((data) => {
                if (cancelled) return;
                const results = data.results || [];
                searchCacheRef.current.set(debouncedQuery, results);
                setProducts(results);
            })
            .catch(() => { if (!cancelled) setProducts([]); })
            .finally(() => { if (!cancelled) setCatalogLoading(false); });

        return () => { cancelled = true; };
    }, [pinVerified, branchId, debouncedQuery]);

    const handleBarcodeDetected = useCallback((barcode) => {
        setSearchQuery(barcode);
        searchInputRef.current?.focus();
    }, []);

    useBarcodeScanner(handleBarcodeDetected, pinVerified);

    function handleSearchKeyDown(e) {
        if (e.key === 'Escape') {
            setSearchQuery('');
            searchInputRef.current?.blur();
        }
    }

    async function createNewCart() {
        if (carts.length >= 5) {
            warning(
                'You have reached the maximum of 5 concurrent carts. Please complete or void an existing cart.',
            );
            return null;
        }
        if (!branchId) {
            error('No branch context. Please set a branch from the admin panel.');
            return null;
        }

        setProcessing(true);
        try {
            const cart = await cartApi.create(branchId);
            setCarts((prev) => [...prev, cart]);
            setActiveCartId(cart.id);
            return cart;
        } catch (err) {
            error(err?.response?.data?.message || 'Could not create cart.');
            return null;
        } finally {
            setProcessing(false);
        }
    }

    async function handleSelectCart(id) {
        if (id === activeCartId || processing) return;

        const existing = carts.find((c) => c.id === id);
        if (!existing) return;

        setProcessing(true);
        try {
            let cart;
            if (existing.status === 'suspended') {
                cart = await cartApi.resume(id);
            } else {
                cart = await cartApi.get(id);
            }
            replaceCart(cart);
            setActiveCartId(id);
            setStockWarnings({});
        } catch (err) {
            error(err?.response?.data?.message || 'Could not switch cart.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleRemoveCart(cartId, e) {
        e?.stopPropagation();

        const cart = carts.find((c) => c.id === cartId);
        if (!cart || processing) return;

        if (cart.status === 'completing') {
            setProcessing(true);
            try {
                await cartApi.complete(cartId);
                removeCartFromList(cartId);
            } catch (err) {
                error(err?.response?.data?.message || 'Could not dismiss cart.');
            } finally {
                setProcessing(false);
            }
            return;
        }

        const itemCount = cart.items?.length ?? 0;
        if (itemCount > 0) {
            const confirmed = await confirmCloseCart();
            if (!confirmed) return;
        }

        setProcessing(true);
        try {
            await cartApi.void(cartId);
            removeCartFromList(cartId);
        } catch (err) {
            error(err?.response?.data?.message || 'Could not close cart.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleAddProduct(variant) {
        let cart = activeCart;

        if (!cart) {
            cart = await createNewCart();
            if (!cart) return;
        }

        if (cart.status === 'completing') {
            warning('This cart is awaiting payment. Switch to another cart or dismiss it first.');
            return;
        }

        if (!variant.in_stock) {
            warning(`"${variant.name}" is out of stock.`);
            return;
        }

        setProcessing(true);
        try {
            const item = await cartItemApi.add(cart.id, {
                product_variant_id: variant.id,
                quantity: 1,
            });
            setCarts((prev) =>
                prev.map((c) => {
                    if (c.id !== cart.id) return c;
                    const items = c.items || [];
                    const exists = items.some((i) => i.id === item.id);
                    return {
                        ...c,
                        items: exists
                            ? items.map((i) => (i.id === item.id ? item : i))
                            : [...items, item],
                    };
                }),
            );
            setUndoStack((prev) => [...prev, { cartId: cart.id, itemId: item.id }]);
            // Invalidate cache for current query so stock counts refresh next search.
            searchCacheRef.current.delete(debouncedQuery);
            setSearchQuery('');
        } catch (err) {
            const msg =
                err?.response?.data?.errors?.quantity?.[0] ||
                err?.response?.data?.message ||
                'Could not add item.';
            error(msg);
        } finally {
            setProcessing(false);
        }
    }

    async function handleSuspend() {
        if (!activeCart || activeCart.status !== 'active') return;
        setProcessing(true);
        try {
            const updated = await cartApi.suspend(activeCart.id);
            replaceCart(updated);
        } catch (err) {
            error(err?.response?.data?.message || 'Could not suspend cart.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleVoid() {
        if (!activeCart) return;

        const confirmed = await confirmVoidCart();
        if (!confirmed) return;

        setProcessing(true);
        try {
            await cartApi.void(activeCart.id);
            removeCartFromList(activeCart.id);
        } catch (err) {
            error(err?.response?.data?.message || 'Could not void cart.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleCheckout() {
        if (!activeCart || (activeCart.items || []).length === 0) return;
        if (activeCart.status === 'completing') {
            router.visit(route('admin.checkout.show', { cartId: activeCart.id }));
            return;
        }
        if (Object.keys(stockWarnings).length > 0) {
            warning('Resolve stock warnings before proceeding to payment.');
            return;
        }
        setProcessing(true);
        try {
            await cartApi.checkout(activeCart.id);
            setCarts((prev) =>
                prev.map((c) => (c.id === activeCart.id ? { ...c, status: 'completing' } : c)),
            );
            router.visit(route('admin.checkout.show', { cartId: activeCart.id }));
        } catch (err) {
            error(err?.response?.data?.message || 'Checkout failed.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleReopenCart() {
        if (!activeCart || activeCart.status !== 'completing') return;
        setProcessing(true);
        try {
            const updated = await cartApi.reopen(activeCart.id);
            replaceCart(updated);
        } catch (err) {
            error(err?.response?.data?.message || 'Could not reopen cart.');
        } finally {
            setProcessing(false);
        }
    }

    async function handleUndoLastItem() {
        const last = undoStack[undoStack.length - 1];
        if (!last) return;
        setProcessing(true);
        try {
            await cartItemApi.remove(last.cartId, last.itemId);
            removeItemFromCart(last.cartId, last.itemId);
            setUndoStack((prev) => prev.slice(0, -1));
        } finally {
            setProcessing(false);
        }
    }

    function handleSwitchSlot(slot) {
        const cart = carts.find((c) => c.slot === slot);
        if (cart) handleSelectCart(cart.id);
    }

    usePosKeyboard(
        useMemo(
            () => ({
                focusSearch: () => searchInputRef.current?.focus(),
                focusQty: () => {},
                checkout: handleCheckout,
                suspendCart: handleSuspend,
                voidCart: handleVoid,
                undoLastItem: handleUndoLastItem,
                switchSlot: handleSwitchSlot,
            }),
            [activeCart, carts, stockWarnings, processing],
        ),
    );

    if (!pinVerified) {
        return (
            <AdminLayout fullHeight>
                <Head title="POS — PIN Required" />
                <div className="flex flex-1 items-center justify-center">
                    <PinModal
                        lockout={pinLockout}
                        onVerified={() => {
                            setPinVerified(true);
                            lastActivityRef.current = Date.now();
                            sessionStartRef.current = Date.now();
                        }}
                    />
                </div>
            </AdminLayout>
        );
    }

    const showDropdown = searchFocused && searchQuery.length > 0;

    return (
        <AdminLayout fullHeight hideTopbar>
            <Head title="Point of Sale" />

            <div className="flex min-h-0 flex-1 flex-col">
                {/* Unified POS topbar */}
                <PosTopbar
                    cartCount={carts.length}
                    sessionStart={sessionStartRef.current}
                />

                {/* Cart tabs */}
                <CartTabs
                    carts={carts}
                    activeCartId={activeCartId}
                    onSelect={handleSelectCart}
                    onNew={createNewCart}
                    onRemove={handleRemoveCart}
                    maxReached={carts.length >= 5}
                    processing={processing}
                />

                {/* Search bar row */}
                <div className="shrink-0 border-b border-rp-border bg-rp-surface px-4 py-2">
                    <div ref={searchWrapRef} className="relative">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Scan className="h-4 w-4 text-rp-text-muted" />
                        </div>
                        <input
                            ref={searchInputRef}
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={handleSearchKeyDown}
                            onFocus={() => setSearchFocused(true)}
                            onBlur={() => setTimeout(() => setSearchFocused(false), 150)}
                            placeholder="Scan barcode or type product name, SKU…"
                            className="w-full rounded-xl border border-rp-border bg-rp-surface-inset py-2.5 pl-9 pr-20 text-sm text-rp-text placeholder:text-rp-text-muted focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />
                        <div className="absolute inset-y-0 right-0 flex items-center gap-1 pr-3">
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery('')}
                                    className="flex h-5 w-5 items-center justify-center rounded text-rp-text-muted hover:text-rp-text"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                            <kbd className="rounded border border-rp-border bg-rp-surface px-1.5 py-0.5 text-[10px] text-rp-text-muted">
                                F2
                            </kbd>
                        </div>

                        {showDropdown && (
                            <ProductDropdown
                                results={products}
                                loading={catalogLoading}
                                query={searchQuery}
                                onAdd={handleAddProduct}
                                processing={processing}
                            />
                        )}
                    </div>
                </div>

                {/* Cart table — flex-1 so it fills remaining space */}
                <CartTable
                    cart={activeCart}
                    stockWarnings={stockWarnings}
                    taxEnabled={posConfig.tax_enabled ?? true}
                    taxMode={posConfig.tax_mode ?? 'exclusive'}
                    defaultTaxRate={posConfig.default_tax_rate ?? '0.00'}
                    currency={posConfig.currency ?? 'PKR'}
                    onItemUpdated={(item) => activeCart && updateItemInCart(activeCart.id, item)}
                    onItemRemoved={(itemId) => activeCart && removeItemFromCart(activeCart.id, itemId)}
                    canDiscount={can('pos.discount')}
                    processing={processing}
                />

                {/* Bottom action bar + totals + Pay */}
                <CartBottomBar
                    cart={activeCart}
                    taxEnabled={posConfig.tax_enabled ?? true}
                    taxMode={posConfig.tax_mode ?? 'exclusive'}
                    defaultTaxRate={posConfig.default_tax_rate ?? '0.00'}
                    taxRatePct={taxRatePct}
                    currency={posConfig.currency ?? 'PKR'}
                    processing={processing}
                    onCheckout={handleCheckout}
                    onSuspend={handleSuspend}
                    onVoid={handleVoid}
                    onReopen={handleReopenCart}
                    canSuspend={can('pos.suspend-cart')}
                />
            </div>
        </AdminLayout>
    );
}
