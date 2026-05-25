import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import { PosHeader } from '@/Components/pos/PosHeader';
import { PinModal } from '@/Components/pos/PinModal';
import { ProductSearch } from '@/Components/pos/ProductSearch';
import { CartPanel } from '@/Components/pos/CartPanel';
import { CartTabs } from '@/Components/pos/CartTabs';
import { cartApi, cartItemApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { usePosKeyboard } from '@/Hooks/usePosKeyboard';
import { usePosWebSocket } from '@/Hooks/usePosWebSocket';
import { useCan } from '@/Hooks/useCan';

const PIN_INACTIVITY_MS = 30 * 60 * 1000;

export default function PosIndex({ hasPin, lockout: initialLockout }) {
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
            const payload = await cartApi.checkout(activeCart.id);
            sessionStorage.setItem('pos_checkout_payload', JSON.stringify(payload));
            success('Checkout ready', `Cart ID: ${payload.cart_id} · Total: PKR ${payload.grand_total.toLocaleString()}`);
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
            <PosLayout title="POS — PIN Required">
                <PinModal
                    lockout={pinLockout}
                    onVerified={() => {
                        setPinVerified(true);
                        lastActivityRef.current = Date.now();
                        sessionStartRef.current = Date.now();
                    }}
                />
            </PosLayout>
        );
    }

    return (
        <PosLayout title="Point of Sale">
            <PosHeader cartCount={carts.length} sessionStart={sessionStartRef.current} />

            <CartTabs
                carts={carts}
                activeCartId={activeCartId}
                onSelect={handleSelectCart}
                onNew={createNewCart}
                onRemove={handleRemoveCart}
                maxReached={carts.length >= 5}
                processing={processing}
            />

            <div className="flex min-h-0 flex-1 divide-x divide-rp-border">
                <div className="flex w-1/2 flex-col p-5 xl:w-3/5">
                    <ProductSearch
                        branchId={branchId}
                        onAddProduct={handleAddProduct}
                        inputRef={searchInputRef}
                    />

                    {!activeCart && carts.length === 0 && (
                        <div className="mt-8 flex flex-1 items-center justify-center">
                            <div className="text-center text-rp-text-muted">
                                <p className="text-5xl opacity-40">🛒</p>
                                <p className="mt-3 text-base font-medium text-rp-text-secondary">
                                    No carts open
                                </p>
                                <p className="mt-1 text-sm">
                                    Search for a product to start a new cart
                                </p>
                                <button
                                    type="button"
                                    onClick={createNewCart}
                                    disabled={processing}
                                    className="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50"
                                >
                                    + New Cart
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex w-1/2 flex-col xl:w-2/5">
                    <CartPanel
                        cart={activeCart}
                        stockWarnings={stockWarnings}
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
                        canDiscount={can('pos.discount')}
                        processing={processing}
                    />
                </div>
            </div>
        </PosLayout>
    );
}
