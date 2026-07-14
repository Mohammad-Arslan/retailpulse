import { Head, router, usePage } from '@inertiajs/react';
import ScrollArea from '@/Components/common/ScrollArea';
import { Building2, Scan, UserRound, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import PosLayout from '@/Layouts/PosLayout';
import { PosTopbar } from '@/Components/pos/PosTopbar';
import { PinModal } from '@/Components/pos/PinModal';
import { CartTabs } from '@/Components/pos/CartTabs';
import { CartTable } from '@/Components/pos/CartTable';
import { CartBottomBar } from '@/Components/pos/CartBottomBar';
import { PosCatalogFilters } from '@/Components/pos/PosCatalogFilters';
import { ProductGrid } from '@/Components/pos/ProductGrid';
import { cartApi, cartItemApi, searchApi } from '@/lib/posApi';
import { customerApi } from '@/lib/checkoutApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { usePosKeyboard } from '@/Hooks/usePosKeyboard';
import { usePosWebSocket } from '@/Hooks/usePosWebSocket';
import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';
import { useCan } from '@/Hooks/useCan';
import { useTranslation } from 'react-i18next';

const PIN_INACTIVITY_MS = 30 * 60 * 1000;
const SEARCH_DEBOUNCE_MS = 300;

function customerStorageKey(cartId) {
    return `checkout-customer-${cartId}`;
}

export default function PosIndex({
    hasPin,
    lockout: initialLockout,
    categories = [],
    brands = [],
    posConfig = {},
}) {
    const { branch } = usePage().props;
    const can = useCan();
    const { t } = useTranslation();
    const { error, warning, success, confirmVoidCart, confirmCloseCart } = usePosDialog();

    const branchId = branch?.active?.id;
    const currency = posConfig.currency ?? 'PKR';

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
    const [categoryId, setCategoryId] = useState(null);
    const [brandId, setBrandId] = useState(null);
    const [filterCategories, setFilterCategories] = useState(categories);
    const [filterBrands, setFilterBrands] = useState(brands);
    const [catalogProducts, setCatalogProducts] = useState([]);
    const [catalogMeta, setCatalogMeta] = useState(null);
    const [catalogPage, setCatalogPage] = useState(1);
    const [catalogPerPage, setCatalogPerPage] = useState(48);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [catalogTick, setCatalogTick] = useState(0);

    const [customerQuery, setCustomerQuery] = useState('');
    const [customerResults, setCustomerResults] = useState([]);
    const [customerLoading, setCustomerLoading] = useState(false);
    const [customerFocused, setCustomerFocused] = useState(false);
    const [cartCustomers, setCartCustomers] = useState({});
    const customerSearchTimer = useRef(null);

    const searchInputRef = useRef(null);
    const customerInputRef = useRef(null);
    const customerRowRef = useRef(null);

    const activeCart = useMemo(
        () => carts.find((c) => c.id === activeCartId) ?? null,
        [carts, activeCartId],
    );

    const selectedCustomer = activeCartId ? cartCustomers[activeCartId] ?? null : null;

    useEffect(() => {
        if (!activeCartId) return;
        const stored = sessionStorage.getItem(customerStorageKey(activeCartId));
        if (!stored) return;
        try {
            const parsed = JSON.parse(stored);
            setCartCustomers((prev) => ({ ...prev, [activeCartId]: parsed }));
        } catch {
            sessionStorage.removeItem(customerStorageKey(activeCartId));
        }
    }, [activeCartId]);

    useEffect(() => {
        if (customerQuery.length < 2) {
            setCustomerResults([]);
            setCustomerLoading(false);
            return undefined;
        }

        setCustomerLoading(true);
        customerSearchTimer.current = setTimeout(async () => {
            try {
                const results = await customerApi.search(customerQuery);
                setCustomerResults(results);
            } catch {
                setCustomerResults([]);
            } finally {
                setCustomerLoading(false);
            }
        }, SEARCH_DEBOUNCE_MS);

        return () => {
            if (customerSearchTimer.current) {
                clearTimeout(customerSearchTimer.current);
            }
        };
    }, [customerQuery]);

    async function attachCustomer(customer) {
        let cartId = activeCartId;
        if (!cartId) {
            const cart = await createNewCart();
            if (!cart) return;
            cartId = cart.id;
        }
        setCartCustomers((prev) => ({ ...prev, [cartId]: customer }));
        sessionStorage.setItem(customerStorageKey(cartId), JSON.stringify(customer));
        setCustomerQuery('');
        setCustomerResults([]);
    }

    function clearCustomer() {
        if (!activeCartId) return;
        setCartCustomers((prev) => {
            const next = { ...prev };
            delete next[activeCartId];
            return next;
        });
        sessionStorage.removeItem(customerStorageKey(activeCartId));
    }

    function focusCustomerSearch() {
        customerRowRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (selectedCustomer) {
            clearCustomer();
            window.setTimeout(() => customerInputRef.current?.focus(), 0);
            return;
        }
        customerInputRef.current?.focus();
    }

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
        setFilterCategories(categories);
        setFilterBrands(brands);
    }, [categories, brands]);

    useEffect(() => {
        setCategoryId(null);
        setBrandId(null);
        setCatalogPage(1);
        setSearchQuery('');
        setDebouncedQuery('');

        if (!branchId) {
            setFilterCategories([]);
            setFilterBrands([]);
            return undefined;
        }

        let cancelled = false;
        searchApi
            .filters(branchId)
            .then((data) => {
                if (cancelled) return;
                setFilterCategories(data.categories || []);
                setFilterBrands(data.brands || []);
            })
            .catch(() => {
                if (cancelled) return;
                setFilterCategories(categories);
                setFilterBrands(brands);
            });

        return () => {
            cancelled = true;
        };
    }, [branchId]);

    useEffect(() => {
        if (!pinVerified || !branchId) {
            setCatalogProducts([]);
            setCatalogMeta(null);
            return undefined;
        }

        let cancelled = false;
        setCatalogLoading(true);

        searchApi
            .catalog({
                branch_id: branchId,
                category_id: categoryId || undefined,
                brand_id: brandId || undefined,
                q: debouncedQuery || undefined,
                page: catalogPage,
                per_page: catalogPerPage,
            })
            .then((data) => {
                if (cancelled) return;
                setCatalogProducts(data.results || []);
                setCatalogMeta(data.meta || null);
            })
            .catch(() => {
                if (cancelled) return;
                setCatalogProducts([]);
                setCatalogMeta(null);
            })
            .finally(() => {
                if (!cancelled) setCatalogLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [
        pinVerified,
        branchId,
        categoryId,
        brandId,
        debouncedQuery,
        catalogPage,
        catalogPerPage,
        catalogTick,
    ]);

    const refreshCatalog = useCallback(() => {
        setCatalogTick((tick) => tick + 1);
    }, []);

    useBarcodeScanner((barcode) => {
        setSearchQuery(barcode);
        searchInputRef.current?.focus();
    }, pinVerified);

    function handleSearchKeyDown(e) {
        if (e.key === 'Escape') {
            setSearchQuery('');
            searchInputRef.current?.blur();
        }
        if (
            e.key === 'Enter' &&
            catalogProducts.length === 1 &&
            (catalogProducts[0].in_stock ||
                catalogProducts[0].tracks_inventory === false ||
                catalogProducts[0].product_type === 'service' ||
                catalogProducts[0].product_type === 'digital')
        ) {
            e.preventDefault();
            handleAddProduct(catalogProducts[0]);
        }
    }

    function handleCategoryChange(id) {
        setCategoryId(id);
        setCatalogPage(1);
    }

    function handleBrandChange(id) {
        setBrandId(id);
        setCatalogPage(1);
    }

    function handleCatalogPageChange(page) {
        setCatalogPage(page);
    }

    function handleCatalogPerPageChange(n) {
        setCatalogPerPage(n);
        setCatalogPage(1);
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

        const sellableWithoutStock =
            variant.tracks_inventory === false ||
            variant.product_type === 'service' ||
            variant.product_type === 'digital';

        if (!sellableWithoutStock && !variant.in_stock) {
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
            setSearchQuery('');
            refreshCatalog();
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
            if (selectedCustomer) {
                sessionStorage.setItem(
                    customerStorageKey(activeCart.id),
                    JSON.stringify(selectedCustomer),
                );
            }
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
            <PosLayout>
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
            </PosLayout>
        );
    }

    return (
        <PosLayout>
            <Head title="Point of Sale" />

            <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <PosTopbar
                    cartCount={carts.length}
                    sessionStart={sessionStartRef.current}
                    onLock={() => setPinVerified(false)}
                />

                <CartTabs
                    carts={carts}
                    activeCartId={activeCartId}
                    onSelect={handleSelectCart}
                    onNew={createNewCart}
                    onRemove={handleRemoveCart}
                    maxReached={carts.length >= 5}
                    processing={processing}
                />

                {/* Catalog (left) + cart pane (right) */}
                <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden lg:flex-row">
                    <section className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-b border-[var(--pos-border)] lg:border-r lg:border-b-0">
                        <div
                            ref={customerRowRef}
                            className="flex shrink-0 flex-col gap-2.5 px-4 pt-3.5 pb-2.5 sm:flex-row sm:px-5"
                        >
                            <div className="relative w-full sm:max-w-[340px] sm:shrink-0">
                                {selectedCustomer ? (
                                    <div className="flex items-center justify-between gap-2 rounded-lg border border-[var(--pos-teal-500)] bg-[var(--pos-teal-50)] px-3 py-2">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <UserRound className="h-4 w-4 shrink-0 text-[var(--pos-teal-700)]" />
                                            <div className="min-w-0">
                                                <p className="truncate text-[13px] font-semibold text-[var(--pos-text-1)]">
                                                    {selectedCustomer.name}
                                                </p>
                                                {selectedCustomer.phone ? (
                                                    <p className="truncate text-[11px] text-[var(--pos-text-3)]">
                                                        {selectedCustomer.phone}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={clearCustomer}
                                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[var(--pos-text-3)] hover:text-[var(--pos-text-1)]"
                                            aria-label="Remove customer"
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ) : (
                                    <div className="relative flex items-center gap-2.5 rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] px-3 py-2">
                                        <UserRound className="h-4 w-4 shrink-0 text-[var(--pos-text-3)]" />
                                        <input
                                            ref={customerInputRef}
                                            type="search"
                                            value={customerQuery}
                                            onChange={(e) => setCustomerQuery(e.target.value)}
                                            onFocus={() => setCustomerFocused(true)}
                                            onBlur={() => setTimeout(() => setCustomerFocused(false), 150)}
                                            placeholder={t('pages.pos.customerPlaceholder')}
                                            className="w-full border-0 bg-transparent text-[13px] text-[var(--pos-text-1)] outline-none placeholder:text-[var(--pos-text-3)]"
                                        />
                                        {customerFocused && customerQuery.length >= 2 ? (
                                            <ScrollArea className="absolute top-full left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] py-1 shadow-[var(--pos-shadow-md)]">
                                                {customerLoading ? (
                                                    <p className="px-4 py-2.5 text-xs text-[var(--pos-text-3)]">
                                                        {t('pages.pos.searching')}
                                                    </p>
                                                ) : customerResults.length === 0 ? (
                                                    <p className="px-4 py-2.5 text-xs text-[var(--pos-text-3)]">
                                                        {t('pages.pos.noCustomers', { query: customerQuery })}
                                                    </p>
                                                ) : (
                                                    customerResults.map((customer) => (
                                                        <button
                                                            key={customer.id}
                                                            type="button"
                                                            onMouseDown={(e) => e.preventDefault()}
                                                            onClick={() => attachCustomer(customer)}
                                                            disabled={processing}
                                                            className="block w-full px-4 py-2 text-left hover:bg-[var(--pos-bg-subtle)] disabled:opacity-50"
                                                        >
                                                            <p className="text-sm font-medium text-[var(--pos-text-1)]">
                                                                {customer.name}
                                                            </p>
                                                            <p className="text-xs text-[var(--pos-text-3)]">
                                                                {[customer.phone, customer.email]
                                                                    .filter(Boolean)
                                                                    .join(' · ')}
                                                            </p>
                                                        </button>
                                                    ))
                                                )}
                                            </ScrollArea>
                                        ) : null}
                                    </div>
                                )}
                            </div>

                            <div className="relative flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-[var(--pos-border)] bg-[var(--pos-bg)] px-3 py-2">
                                <Scan className="h-4 w-4 shrink-0 text-[var(--pos-text-3)]" />
                                <input
                                    ref={searchInputRef}
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => {
                                        setSearchQuery(e.target.value);
                                        setCatalogPage(1);
                                    }}
                                    onKeyDown={handleSearchKeyDown}
                                    placeholder={t('pages.pos.searchPlaceholder')}
                                    className="w-full border-0 bg-transparent text-[13px] text-[var(--pos-text-1)] outline-none placeholder:text-[var(--pos-text-3)]"
                                />
                                {searchQuery ? (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchQuery('');
                                            setCatalogPage(1);
                                        }}
                                        className="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[var(--pos-text-3)] hover:text-[var(--pos-text-1)]"
                                        aria-label={t('common.clear')}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                ) : null}
                                <kbd className="pos-mono shrink-0 rounded border border-[var(--pos-border)] bg-[var(--pos-bg-sunken)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--pos-text-3)]">
                                    F2
                                </kbd>
                            </div>
                        </div>

                        {!branchId ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center">
                                <Building2 className="h-10 w-10 text-[var(--pos-text-3)] opacity-40" />
                                <p className="text-sm font-medium text-[var(--pos-text-1)]">
                                    {t('pages.pos.selectBranchTitle')}
                                </p>
                                <p className="text-xs text-[var(--pos-text-3)]">
                                    {t('pages.pos.selectBranchHint')}
                                </p>
                            </div>
                        ) : (
                            <>
                                <PosCatalogFilters
                                    categories={filterCategories}
                                    brands={filterBrands}
                                    categoryId={categoryId}
                                    brandId={brandId}
                                    onCategoryChange={handleCategoryChange}
                                    onBrandChange={handleBrandChange}
                                />
                                <ProductGrid
                                    products={catalogProducts}
                                    loading={catalogLoading}
                                    meta={catalogMeta}
                                    perPage={catalogPerPage}
                                    onPageChange={handleCatalogPageChange}
                                    onPerPageChange={handleCatalogPerPageChange}
                                    onAddProduct={handleAddProduct}
                                    processing={processing}
                                    currency={currency}
                                />
                            </>
                        )}
                    </section>

                    <section className="flex min-h-[min(42vh,320px)] w-full min-w-0 flex-col overflow-hidden bg-[var(--pos-bg)] sm:min-h-[min(45vh,380px)] lg:h-auto lg:min-h-0 lg:w-[440px] lg:max-w-[440px] lg:shrink-0">
                        <CartTable
                            cart={activeCart}
                            stockWarnings={stockWarnings}
                            taxEnabled={posConfig.tax_enabled ?? true}
                            taxMode={posConfig.tax_mode ?? 'exclusive'}
                            defaultTaxRate={posConfig.default_tax_rate ?? '0.00'}
                            currency={currency}
                            onItemUpdated={(item) => activeCart && updateItemInCart(activeCart.id, item)}
                            onItemRemoved={(itemId) => activeCart && removeItemFromCart(activeCart.id, itemId)}
                            canDiscount={can('pos.discount')}
                            processing={processing}
                        />
                        <CartBottomBar
                            cart={activeCart}
                            taxEnabled={posConfig.tax_enabled ?? true}
                            taxMode={posConfig.tax_mode ?? 'exclusive'}
                            defaultTaxRate={posConfig.default_tax_rate ?? '0.00'}
                            taxRatePct={taxRatePct}
                            currency={currency}
                            processing={processing}
                            onCheckout={handleCheckout}
                            onSuspend={handleSuspend}
                            onVoid={handleVoid}
                            onReopen={handleReopenCart}
                            canSuspend={can('pos.suspend-cart')}
                            onAttachCustomer={focusCustomerSearch}
                            hasCustomer={Boolean(selectedCustomer)}
                        />
                    </section>
                </div>
            </div>
        </PosLayout>
    );
}
