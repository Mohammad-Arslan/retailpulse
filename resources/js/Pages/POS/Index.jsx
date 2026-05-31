import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PosHeader } from '@/Components/pos/PosHeader';
import { PinModal } from '@/Components/pos/PinModal';
import { CategoryFilters } from '@/Components/pos/CategoryFilters';
import { ProductGrid } from '@/Components/pos/ProductGrid';
import { CartPanel } from '@/Components/pos/CartPanel';
import { CartTabs } from '@/Components/pos/CartTabs';
import { cartApi, cartItemApi, searchApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { usePosKeyboard } from '@/Hooks/usePosKeyboard';
import { usePosWebSocket } from '@/Hooks/usePosWebSocket';
import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';
import { useCan } from '@/Hooks/useCan';

const PIN_INACTIVITY_MS = 30 * 60 * 1000;
const SEARCH_DEBOUNCE_MS = 300;

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
    const [categoryId, setCategoryId] = useState(null);
    const [products, setProducts] = useState([]);
    const [catalogMeta, setCatalogMeta] = useState(null);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(24);

    const searchInputRef = useRef(null);

    const activeCart = useMemo(
        () => carts.find((c) => c.id === activeCartId) ?? null,
        [carts, activeCartId],
    );

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
        setPage(1);
    }, [debouncedQuery, categoryId, perPage]);

    useEffect(() => {
        if (!pinVerified || !branchId) return;

        setCatalogLoading(true);
        searchApi
            .catalog({
                branch_id: branchId,
                category_id: categoryId ?? undefined,
                q: debouncedQuery || undefined,
                page,
                per_page: perPage,
            })
            .then((data) => {
                setProducts(data.results || []);
                setCatalogMeta(data.meta || null);
            })
            .catch(() => {
                setProducts([]);
                setCatalogMeta(null);
            })
            .finally(() => setCatalogLoading(false));
    }, [pinVerified, branchId, categoryId, debouncedQuery, page, perPage]);

    const handleBarcodeDetected = useCallback((barcode) => {
        setSearchQuery(barcode);
        searchInputRef.current?.focus();
    }, []);

    useBarcodeScanner(handleBarcodeDetected, pinVerified);

    function handleSearchKeyDown(e) {
        if (e.key === 'Escape') {
            setSearchQuery('');
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
        if (Object.keys(stockWarnings).length > 0) {
            warning('Resolve stock warnings before proceeding to payment.');
            return;
        }
        setProcessing(true);
        try {
            await cartApi.checkout(activeCart.id);
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

    return (
        <AdminLayout fullHeight>
            <Head title="Point of Sale" />
            <div className="flex min-h-0 flex-1 flex-col">
            <PosHeader
                cartCount={carts.length}
                sessionStart={sessionStartRef.current}
                searchQuery={searchQuery}
                onSearchChange={setSearchQuery}
                onSearchKeyDown={handleSearchKeyDown}
                searchInputRef={searchInputRef}
                searchLoading={catalogLoading}
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

            <div className="flex min-h-0 flex-1">
                <div className="flex min-w-0 flex-1 flex-col">
                    <CategoryFilters
                        categories={categories}
                        activeId={categoryId}
                        onSelect={setCategoryId}
                    />

                    <ProductGrid
                        products={products}
                        loading={catalogLoading}
                        meta={catalogMeta}
                        perPage={perPage}
                        onPageChange={setPage}
                        onPerPageChange={setPerPage}
                        onAddProduct={handleAddProduct}
                        processing={processing}
                    />
                </div>

                <CartPanel
                    cart={activeCart}
                    stockWarnings={stockWarnings}
                    taxEnabled={posConfig.tax_enabled ?? true}
                    taxMode={posConfig.tax_mode ?? 'exclusive'}
                    defaultTaxRate={posConfig.default_tax_rate ?? '0.00'}
                    currency={posConfig.currency ?? 'PKR'}
                    onItemUpdated={(item) =>
                        activeCart && updateItemInCart(activeCart.id, item)
                    }
                    onItemRemoved={(itemId) =>
                        activeCart && removeItemFromCart(activeCart.id, itemId)
                    }
                    onCheckout={handleCheckout}
                    onSuspend={handleSuspend}
                    onVoid={handleVoid}
                    onReopen={handleReopenCart}
                    onAddDiscount={() =>
                        warning('Select an item in the cart and use + Discount on that row.')
                    }
                    onNote={() => warning('Cart notes are coming in a future update.')}
                    onCustomer={() => warning('Customer linking is coming in Phase 9.')}
                    canDiscount={can('pos.discount')}
                    processing={processing}
                />
            </div>
            </div>
        </AdminLayout>
    );
}
