import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/ui/button';
import { Printer } from 'lucide-react';
import { checkoutApi, customerApi, loyaltyApi, saleApi } from '@/lib/checkoutApi';
import { pinApi } from '@/lib/posApi';
import { usePosDialog } from '@/Hooks/usePosDialog';
import { useTranslation } from 'react-i18next';

function customerStorageKey(cartId) {
    return `checkout-customer-${cartId}`;
}

function ManagerPinModal({ onVerified, onCancel, t }) {
    const [digits, setDigits] = useState(['', '', '', '', '', '']);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const inputRefs = useRef([]);

    useEffect(() => {
        inputRefs.current[0]?.focus();
    }, []);

    function handleDigitChange(index, value) {
        if (!/^\d?$/.test(value)) return;
        const next = [...digits];
        next[index] = value;
        setDigits(next);

        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }

        if (next.every((d) => d !== '')) {
            submitPin(next.join(''));
        }
    }

    function handleKeyDown(index, e) {
        if (e.key === 'Backspace' && !digits[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    }

    async function submitPin(pin) {
        setLoading(true);
        setError(null);

        try {
            const res = await pinApi.verify(pin);
            if (res.verified) {
                onVerified(pin);
            } else {
                setError(t('checkout.managerPin.incorrect'));
                setDigits(['', '', '', '', '', '']);
                inputRefs.current[0]?.focus();
            }
        } catch (err) {
            const msg =
                err?.response?.data?.errors?.pin?.[0] ||
                err?.response?.data?.message ||
                t('checkout.managerPin.failed');
            setError(msg);
            setDigits(['', '', '', '', '', '']);
            inputRefs.current[0]?.focus();
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div className="w-full max-w-sm rounded-2xl bg-card p-8 shadow-2xl">
                <div className="mb-6 text-center">
                    <h2 className="text-xl font-semibold">{t('checkout.managerPin.title')}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('checkout.managerPin.description')}
                    </p>
                </div>
                <div className="mb-4 flex justify-center gap-3">
                    {digits.map((digit, i) => (
                        <input
                            key={i}
                            ref={(el) => (inputRefs.current[i] = el)}
                            type="password"
                            inputMode="numeric"
                            maxLength={1}
                            value={digit}
                            onChange={(e) => handleDigitChange(i, e.target.value)}
                            onKeyDown={(e) => handleKeyDown(i, e)}
                            disabled={loading}
                            className="h-12 w-12 rounded-lg border-2 text-center text-xl font-bold focus:border-primary focus:outline-none disabled:opacity-50"
                        />
                    ))}
                </div>
                {error && <p className="mb-2 text-center text-sm text-destructive">{error}</p>}
                <div className="flex justify-center gap-2">
                    <Button type="button" variant="outline" onClick={onCancel}>
                        {t('confirm.cancel')}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function CustomerProfileCard({ profile, currency, t }) {
    if (!profile) return null;

    return (
        <div className="rounded-lg border bg-muted/30 p-3 text-sm">
            <div className="mb-2 flex items-center justify-between">
                <div className="font-medium">{profile.name}</div>
                {profile.loyalty_tier?.name && (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-200">
                        {profile.loyalty_tier.name}
                    </span>
                )}
            </div>
            {(profile.phone || profile.email) && (
                <p className="mb-2 text-xs text-muted-foreground">
                    {[profile.phone, profile.email].filter(Boolean).join(' · ')}
                </p>
            )}
            <div className="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span className="text-muted-foreground">{t('checkout.customerCard.wallet')}</span>
                    <div className="font-medium">
                        {profile.wallet_balance ?? '0.00'} {currency}
                    </div>
                </div>
                <div>
                    <span className="text-muted-foreground">{t('checkout.customerCard.storeCredit')}</span>
                    <div className="font-medium">
                        {profile.store_credit_balance ?? '0.00'} {currency}
                    </div>
                </div>
                <div>
                    <span className="text-muted-foreground">{t('checkout.customerCard.arOutstanding')}</span>
                    <div className="font-medium">
                        {profile.ar_outstanding ?? '0.00'} {currency}
                    </div>
                </div>
                <div>
                    <span className="text-muted-foreground">{t('checkout.customerCard.creditAvailable')}</span>
                    <div className="font-medium text-teal-600">
                        {profile.credit_available ?? '—'} {profile.credit_available != null ? currency : ''}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function CheckoutIndex({ cartId }) {
    const { t } = useTranslation();
    const { error, success, confirmVoidCart } = usePosDialog();

    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [bootstrap, setBootstrap] = useState(null);
    const [sale, setSale] = useState(null);
    const [selectedMethod, setSelectedMethod] = useState('cash');
    const [tenderedAmount, setTenderedAmount] = useState('');
    const [paymentAmount, setPaymentAmount] = useState('');
    const [completed, setCompleted] = useState(false);
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [customerProfile, setCustomerProfile] = useState(null);
    const [customerQuery, setCustomerQuery] = useState('');
    const [customerResults, setCustomerResults] = useState([]);
    const [bankReference, setBankReference] = useState('');
    const [bankName, setBankName] = useState('');
    const [managerPinOverride, setManagerPinOverride] = useState(null);
    const [showManagerPinModal, setShowManagerPinModal] = useState(false);
    const [pendingPayment, setPendingPayment] = useState(false);
    const [loyaltyOptions, setLoyaltyOptions] = useState(null);
    const [loyaltyPointsToRedeem, setLoyaltyPointsToRedeem] = useState('');
    const autoPrintFiredRef = useRef(false);
    const customerSearchTimer = useRef(null);

    const config = bootstrap?.config ?? {};
    const currency = bootstrap?.currency ?? 'PKR';

    const methodLabel = useCallback(
        (method) => t(`checkout.paymentMethods.${method}`, { defaultValue: method }),
        [t],
    );

    const loadCustomerProfile = useCallback(async (customerId) => {
        if (!customerId) {
            setCustomerProfile(null);
            setLoyaltyOptions(null);
            setLoyaltyPointsToRedeem('');
            return;
        }
        try {
            const profile = await customerApi.profile(customerId);
            setCustomerProfile(profile);
        } catch {
            setCustomerProfile(null);
        }
    }, []);

    const loadLoyaltyOptions = useCallback(async (customerId, loyaltyEnabled = true) => {
        if (!customerId || !loyaltyEnabled) {
            setLoyaltyOptions(null);
            setLoyaltyPointsToRedeem('');
            return;
        }
        try {
            const data = await loyaltyApi.redemptionOptions(customerId);
            setLoyaltyOptions(data.options ?? null);
        } catch {
            setLoyaltyOptions(null);
        }
    }, []);

    const loadBootstrap = useCallback(async () => {
        setLoading(true);
        try {
            const data = await checkoutApi.bootstrap(cartId);
            setBootstrap(data);

            const stored = sessionStorage.getItem(customerStorageKey(cartId));
            if (stored) {
                try {
                    const parsed = JSON.parse(stored);
                    setSelectedCustomer(parsed);
                    await loadCustomerProfile(parsed.id);
                    if (data.loyalty_enabled) {
                        await loadLoyaltyOptions(parsed.id, true);
                    }
                } catch {
                    sessionStorage.removeItem(customerStorageKey(cartId));
                }
            }

            if (data.sale_id) {
                const saleData = await saleApi.get(data.sale_id);
                setSale(saleData);
                setCompleted(saleData.status === 'completed');
                if (saleData.customer?.id) {
                    setSelectedCustomer(saleData.customer);
                    await loadCustomerProfile(saleData.customer.id);
                }
            }
        } catch (err) {
            error(err?.response?.data?.message || t('checkout.errors.loadFailed'));
        } finally {
            setLoading(false);
        }
    }, [cartId, error, loadCustomerProfile, t]);

    useEffect(() => {
        loadBootstrap();
    }, [loadBootstrap]);

    useEffect(() => {
        if (customerQuery.length < 2) {
            setCustomerResults([]);
            return undefined;
        }

        customerSearchTimer.current = setTimeout(async () => {
            try {
                const results = await customerApi.search(customerQuery);
                setCustomerResults(results);
            } catch {
                setCustomerResults([]);
            }
        }, 300);

        return () => {
            if (customerSearchTimer.current) {
                clearTimeout(customerSearchTimer.current);
            }
        };
    }, [customerQuery]);

    const handlePrint = useCallback(() => {
        const token = sale?.invoice?.public_token;
        if (!token) return;
        const url = route('invoice.print', { publicToken: token });
        const template = config.default_invoice_template ?? 'a4';
        const isTherm = template === 'thermal_80mm';
        const w = isTherm ? 380 : 960;
        const h = isTherm ? 700 : 720;
        const left = Math.max(0, Math.round((window.screen.width - w) / 2));
        const top = Math.max(0, Math.round((window.screen.height - h) / 2));
        window.open(
            url,
            '_blank',
            `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`,
        );
    }, [sale?.invoice?.public_token, config.default_invoice_template]);

    useEffect(() => {
        if (completed && config.receipt_print_mode === 'auto' && !autoPrintFiredRef.current) {
            autoPrintFiredRef.current = true;
            handlePrint();
        }
    }, [completed, config.receipt_print_mode, handlePrint]);

    const balanceDue = useMemo(() => {
        if (sale?.balance_due !== undefined) return parseFloat(sale.balance_due);
        if (bootstrap?.balance_due !== undefined) return parseFloat(bootstrap.balance_due);
        return 0;
    }, [sale, bootstrap]);

    const changeDue = useMemo(() => {
        if (selectedMethod !== 'cash' || !config.cash_change_enabled) return 0;
        const tendered = parseFloat(tenderedAmount || '0');
        if (!tendered || tendered < balanceDue) return 0;
        return Math.max(0, tendered - balanceDue);
    }, [selectedMethod, tenderedAmount, balanceDue, config.cash_change_enabled]);

    const activeCustomer = sale?.customer ?? selectedCustomer;
    const activeProfile = customerProfile;

    const creditLimitExceeded = useMemo(() => {
        if (selectedMethod !== 'credit' || !activeProfile?.credit_limit) return false;
        const limit = parseFloat(activeProfile.credit_limit);
        const outstanding = parseFloat(activeProfile.ar_outstanding ?? '0');
        const projected = outstanding + balanceDue;
        return projected > limit;
    }, [selectedMethod, activeProfile, balanceDue]);

    const paymentMethods = useMemo(() => {
        const base = config.payment_methods ?? ['cash'];
        const extras = [];
        if (activeCustomer && parseFloat(activeProfile?.wallet_balance ?? '0') > 0) {
            extras.push('wallet');
        }
        if (activeCustomer && parseFloat(activeProfile?.store_credit_balance ?? '0') > 0) {
            extras.push('store_credit');
        }
        return [...new Set([...base, ...extras])];
    }, [config.payment_methods, activeCustomer, activeProfile]);

    async function selectCustomer(customer) {
        setSelectedCustomer(customer);
        setCustomerQuery('');
        setCustomerResults([]);
        setManagerPinOverride(null);
        sessionStorage.setItem(customerStorageKey(cartId), JSON.stringify(customer));
        await loadCustomerProfile(customer.id);
        await loadLoyaltyOptions(customer.id, bootstrap?.loyalty_enabled);
    }

    function clearCustomer() {
        setSelectedCustomer(null);
        setCustomerProfile(null);
        setLoyaltyOptions(null);
        setLoyaltyPointsToRedeem('');
        setManagerPinOverride(null);
        sessionStorage.removeItem(customerStorageKey(cartId));
    }

    const loyaltyDiscountPreview = useMemo(() => {
        const points = parseInt(loyaltyPointsToRedeem || '0', 10);
        if (!loyaltyOptions || !points || points <= 0) return 0;
        const rate = loyaltyOptions.conversion_rate ?? { points: 100, currency: 100 };
        if (!rate.points) return 0;
        return Math.round((points / rate.points) * rate.currency * 100) / 100;
    }, [loyaltyOptions, loyaltyPointsToRedeem]);

    async function handleConfirmSale() {
        setProcessing(true);
        try {
            const payload = {};
            if (selectedCustomer?.id) {
                payload.customer_id = selectedCustomer.id;
            }
            const points = parseInt(loyaltyPointsToRedeem || '0', 10);
            if (points > 0) {
                payload.loyalty_points_to_redeem = points;
            }
            const confirmed = await checkoutApi.confirm(cartId, payload);
            const saleData = await saleApi.get(confirmed.id);
            setSale(saleData);
            if (saleData.customer?.id) {
                await loadCustomerProfile(saleData.customer.id);
            }
            success(t('checkout.confirmSale'), t('checkout.payment'));
        } catch (err) {
            error(err?.response?.data?.message || t('checkout.errors.confirmFailed'));
        } finally {
            setProcessing(false);
        }
    }

    async function executePayment(managerPin = null) {
        const payload = { method: selectedMethod };

        if (selectedMethod === 'cash') {
            payload.tendered_amount = parseFloat(tenderedAmount || String(balanceDue));
            payload.amount = balanceDue;
        } else if (config.split_tender_enabled && paymentAmount) {
            payload.amount = parseFloat(paymentAmount);
        } else {
            payload.amount = balanceDue;
        }

        if (selectedMethod === 'bank_transfer') {
            if (!bankReference.trim()) {
                error(t('checkout.bankReferenceRequired'));
                return;
            }
            payload.meta = {
                bank_name: bankName.trim() || null,
                reference_number: bankReference.trim(),
                deposited_at: new Date().toISOString(),
            };
        }

        if (managerPin) {
            payload.manager_pin = managerPin;
        }

        const updated = await saleApi.addPayment(sale.id, payload);

        if (updated.payment_error) {
            error(updated.payment_error);
        }

        setSale(updated);

        if (updated.status === 'completed') {
            setCompleted(true);
            sessionStorage.removeItem(customerStorageKey(cartId));
            success(t('checkout.paymentComplete'), updated.invoice?.number ?? '');
        } else {
            setTenderedAmount('');
            setPaymentAmount('');
        }
    }

    async function handlePayment() {
        if (!sale?.id) return;

        const customerId = sale.customer?.id ?? selectedCustomer?.id;
        if (
            (selectedMethod === 'credit' || selectedMethod === 'wallet' || selectedMethod === 'store_credit') &&
            !customerId
        ) {
            error(t('checkout.customerRequiredCredit'));
            return;
        }

        if (creditLimitExceeded && !managerPinOverride) {
            setPendingPayment(true);
            setShowManagerPinModal(true);
            return;
        }

        setProcessing(true);
        try {
            await executePayment(managerPinOverride);
        } catch (err) {
            const messages = err?.response?.data?.errors;
            if (messages) {
                error(Object.values(messages).flat().join(' '));
            } else {
                error(err?.response?.data?.message || t('checkout.errors.paymentFailed'));
            }
        } finally {
            setProcessing(false);
            setPendingPayment(false);
        }
    }

    function handleManagerPinVerified(pin) {
        setManagerPinOverride(pin);
        setShowManagerPinModal(false);
        if (pendingPayment) {
            setProcessing(true);
            executePayment(pin)
                .catch((err) => {
                    const messages = err?.response?.data?.errors;
                    error(
                        messages
                            ? Object.values(messages).flat().join(' ')
                            : err?.response?.data?.message || t('checkout.errors.paymentFailed'),
                    );
                })
                .finally(() => {
                    setProcessing(false);
                    setPendingPayment(false);
                });
        }
    }

    async function handleBackToPos() {
        if (sale?.id) {
            error(t('checkout.errors.saleConfirmed'));
            return;
        }

        setProcessing(true);
        try {
            await checkoutApi.abandon(cartId);
            router.visit(route('admin.pos.index'));
        } catch (err) {
            error(err?.response?.data?.message || t('checkout.errors.returnFailed'));
        } finally {
            setProcessing(false);
        }
    }

    async function handleVoid() {
        if (!sale?.id) {
            await handleBackToPos();
            return;
        }

        const ok = await confirmVoidCart();
        if (!ok) return;

        setProcessing(true);
        try {
            await saleApi.void(sale.id);
            sessionStorage.removeItem(customerStorageKey(cartId));
            router.visit(route('admin.pos.index'));
        } catch (err) {
            error(err?.response?.data?.message || t('checkout.errors.voidFailed'));
        } finally {
            setProcessing(false);
        }
    }

    if (loading) {
        return (
            <AdminLayout fullHeight>
                <Head title={t('checkout.title')} />
                <div className="flex flex-1 items-center justify-center text-muted-foreground">
                    {t('checkout.loading')}
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout fullHeight>
            <Head title={t('checkout.title')} />
            <div className="flex h-full flex-col gap-4 p-4 lg:flex-row">
                <section className="flex flex-1 flex-col rounded-lg border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold">{t('checkout.title')}</h1>
                            <p className="text-sm text-muted-foreground">
                                {t('checkout.cartLabel', { id: cartId })}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {!completed && (
                                <Button variant="outline" onClick={handleBackToPos} disabled={processing}>
                                    {t('checkout.backToPos')}
                                </Button>
                            )}
                            {!completed && (
                                <Button variant="destructive" onClick={handleVoid} disabled={processing}>
                                    {t('checkout.void')}
                                </Button>
                            )}
                            {completed && config.receipt_print_mode !== 'off' && (
                                <Button variant="outline" onClick={handlePrint}>
                                    <Printer className="mr-2 h-4 w-4" />
                                    {t('checkout.printReceipt')}
                                </Button>
                            )}
                            {completed && (
                                <Button onClick={() => router.visit(route('admin.pos.index'))}>
                                    {t('checkout.newSale')}
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2">Item</th>
                                    <th className="py-2">Qty</th>
                                    {config.tax_enabled && <th className="py-2 text-right">{t('checkout.tax')}</th>}
                                    <th className="py-2 text-right">{t('checkout.total')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(bootstrap?.items ?? []).map((item) => (
                                    <tr key={`${item.product_id}-${item.variant_id}`} className="border-b">
                                        <td className="py-2">
                                            <div className="font-medium">{item.name}</div>
                                            <div className="text-xs text-muted-foreground">{item.sku}</div>
                                        </td>
                                        <td className="py-2">{item.quantity}</td>
                                        {config.tax_enabled && (
                                            <td className="py-2 text-right text-muted-foreground">{item.tax_amount}</td>
                                        )}
                                        <td className="py-2 text-right">
                                            {config.tax_enabled ? item.line_total_inc_tax : item.line_total}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 space-y-1 border-t pt-4 text-sm">
                        <div className="flex justify-between">
                            <span>{t('checkout.subtotal')}</span>
                            <span>{bootstrap?.subtotal} {currency}</span>
                        </div>
                        {parseFloat(bootstrap?.total_discount ?? '0') > 0 && (
                            <div className="flex justify-between text-green-600">
                                <span>{t('checkout.discount')}</span>
                                <span>-{bootstrap?.total_discount} {currency}</span>
                            </div>
                        )}
                        {config.tax_enabled && (
                            <div className="flex justify-between text-muted-foreground">
                                <span>{t('checkout.tax')}</span>
                                <span>{bootstrap?.tax_total} {currency}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-lg font-semibold">
                            <span>{t('checkout.total')}</span>
                            <span>{bootstrap?.grand_total} {currency}</span>
                        </div>
                        {sale && (
                            <div className="flex justify-between text-amber-600">
                                <span>{t('checkout.balanceDue')}</span>
                                <span>{sale.balance_due} {currency}</span>
                            </div>
                        )}
                    </div>
                </section>

                <section className="w-full rounded-lg border bg-card p-4 lg:w-96">
                    {!sale ? (
                        <div className="space-y-4">
                            <h2 className="font-semibold">{t('checkout.confirmSale')}</h2>
                            <p className="text-sm text-muted-foreground">{t('checkout.confirmDescription')}</p>

                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    {t('checkout.customerOptional')}
                                </label>
                                {selectedCustomer ? (
                                    <div className="space-y-2">
                                        <CustomerProfileCard
                                            profile={activeProfile ?? selectedCustomer}
                                            currency={currency}
                                            t={t}
                                        />
                                        <Button type="button" variant="ghost" size="sm" onClick={clearCustomer}>
                                            {t('checkout.clearCustomer')}
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <input
                                            type="search"
                                            value={customerQuery}
                                            onChange={(e) => setCustomerQuery(e.target.value)}
                                            placeholder={t('checkout.customerSearchPlaceholder')}
                                            className="w-full rounded-md border px-3 py-2 text-sm"
                                        />
                                        {customerResults.length > 0 && (
                                            <div className="max-h-48 overflow-auto rounded-md border">
                                                {customerResults.map((customer) => (
                                                    <button
                                                        key={customer.id}
                                                        type="button"
                                                        className="block w-full border-b px-3 py-2 text-left text-sm hover:bg-muted/50"
                                                        onClick={() => selectCustomer(customer)}
                                                    >
                                                        <div className="font-medium">{customer.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {[customer.phone, customer.email]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </div>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {bootstrap?.loyalty_enabled && selectedCustomer && loyaltyOptions?.available_points > 0 && (
                                <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                    <h3 className="mb-2 font-medium">{t('checkout.loyalty.redeemTitle')}</h3>
                                    <p className="mb-2 text-muted-foreground">
                                        {t('checkout.loyalty.availablePoints')}: {loyaltyOptions.available_points}
                                    </p>
                                    <label className="mb-1 block text-sm font-medium" htmlFor="loyalty-points">
                                        {t('checkout.loyalty.pointsToRedeem')}
                                    </label>
                                    <input
                                        id="loyalty-points"
                                        type="number"
                                        min={loyaltyOptions.min_redeem_points ?? 0}
                                        max={loyaltyOptions.available_points}
                                        value={loyaltyPointsToRedeem}
                                        onChange={(e) => setLoyaltyPointsToRedeem(e.target.value)}
                                        className="w-full rounded-md border px-3 py-2 text-sm"
                                        placeholder="0"
                                    />
                                    {loyaltyDiscountPreview > 0 && (
                                        <p className="mt-2 text-teal-600">
                                            {t('checkout.loyalty.discountPreview')}: {loyaltyDiscountPreview.toFixed(2)} {currency}
                                        </p>
                                    )}
                                </div>
                            )}

                            <Button className="w-full" onClick={handleConfirmSale} disabled={processing}>
                                {t('checkout.confirmButton')}
                            </Button>
                        </div>
                    ) : completed ? (
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <div className="h-3 w-3 rounded-full bg-green-500" />
                                <h2 className="font-semibold text-green-600">{t('checkout.saleComplete')}</h2>
                            </div>

                            {sale.invoice && (
                                <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                    <div className="font-medium">{sale.invoice.number}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {new Date().toLocaleString()}
                                    </div>
                                </div>
                            )}

                            {(sale.payments ?? []).map((payment) => (
                                <div key={payment.id} className="rounded border p-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="font-medium">{methodLabel(payment.method)}</span>
                                        <span>{payment.amount} {currency}</span>
                                    </div>
                                </div>
                            ))}

                            {config.receipt_print_mode !== 'off' && (
                                <Button
                                    className="w-full"
                                    variant={config.receipt_print_mode === 'auto' ? 'outline' : 'default'}
                                    onClick={handlePrint}
                                >
                                    <Printer className="mr-2 h-4 w-4" />
                                    {config.receipt_print_mode === 'auto'
                                        ? t('checkout.reprintReceipt')
                                        : t('checkout.printReceipt')}
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {activeCustomer && (
                                <CustomerProfileCard
                                    profile={activeProfile ?? activeCustomer}
                                    currency={currency}
                                    t={t}
                                />
                            )}

                            <h2 className="font-semibold">{t('checkout.payment')}</h2>

                            <div className="grid grid-cols-2 gap-2">
                                {paymentMethods.map((method) => (
                                    <Button
                                        key={method}
                                        type="button"
                                        variant={selectedMethod === method ? 'default' : 'outline'}
                                        onClick={() => {
                                            setSelectedMethod(method);
                                            setManagerPinOverride(null);
                                        }}
                                    >
                                        {methodLabel(method)}
                                    </Button>
                                ))}
                            </div>

                            {creditLimitExceeded && (
                                <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200">
                                    <p className="font-medium">{t('checkout.creditLimit.warning')}</p>
                                    <p className="mt-1 text-xs">
                                        {t('checkout.creditLimit.exceeded', {
                                            projected: (
                                                parseFloat(activeProfile?.ar_outstanding ?? '0') + balanceDue
                                            ).toFixed(2),
                                            limit: activeProfile?.credit_limit,
                                        })}
                                    </p>
                                    {!managerPinOverride ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="mt-2"
                                            onClick={() => setShowManagerPinModal(true)}
                                        >
                                            {t('checkout.creditLimit.overrideButton')}
                                        </Button>
                                    ) : (
                                        <p className="mt-2 text-xs text-green-700 dark:text-green-400">
                                            {t('checkout.creditLimit.overrideRequired')} ✓
                                        </p>
                                    )}
                                </div>
                            )}

                            {selectedMethod === 'bank_transfer' && (
                                <div className="space-y-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">
                                            {t('checkout.bankName')}
                                        </label>
                                        <input
                                            type="text"
                                            value={bankName}
                                            onChange={(e) => setBankName(e.target.value)}
                                            className="w-full rounded-md border px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">
                                            {t('checkout.bankReference')}
                                        </label>
                                        <input
                                            type="text"
                                            value={bankReference}
                                            onChange={(e) => setBankReference(e.target.value)}
                                            className="w-full rounded-md border px-3 py-2 text-sm"
                                            required
                                        />
                                    </div>
                                </div>
                            )}

                            {selectedMethod === 'credit' && !sale.customer && !selectedCustomer && (
                                <p className="text-sm text-amber-600">{t('checkout.creditRequiresCustomer')}</p>
                            )}

                            {selectedMethod === 'cash' && config.cash_change_enabled && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium">
                                        {t('checkout.tenderedAmount')}
                                    </label>
                                    <input
                                        type="number"
                                        min={balanceDue}
                                        step="0.01"
                                        value={tenderedAmount}
                                        onChange={(e) => setTenderedAmount(e.target.value)}
                                        className="w-full rounded-md border px-3 py-2 text-sm"
                                        placeholder={String(balanceDue)}
                                    />
                                    {changeDue > 0 && (
                                        <p className="mt-2 text-lg font-semibold text-green-600">
                                            {t('checkout.change')}: {changeDue.toFixed(2)} {currency}
                                        </p>
                                    )}
                                </div>
                            )}

                            {config.split_tender_enabled && selectedMethod !== 'cash' && balanceDue > 0 && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium">
                                        {t('checkout.paymentAmount')}
                                    </label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        max={balanceDue}
                                        step="0.01"
                                        value={paymentAmount}
                                        onChange={(e) => setPaymentAmount(e.target.value)}
                                        className="w-full rounded-md border px-3 py-2 text-sm"
                                        placeholder={String(balanceDue)}
                                    />
                                </div>
                            )}

                            {(sale.payments ?? []).length > 0 && (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-medium">{t('checkout.appliedPayments')}</h3>
                                    {sale.payments.map((payment) => (
                                        <div key={payment.id} className="rounded border px-2 py-1 text-sm">
                                            {methodLabel(payment.method)}: {payment.amount}
                                        </div>
                                    ))}
                                </div>
                            )}

                            <Button
                                className="w-full"
                                onClick={handlePayment}
                                disabled={
                                    processing ||
                                    (creditLimitExceeded && !managerPinOverride)
                                }
                            >
                                {config.split_tender_enabled &&
                                paymentAmount &&
                                parseFloat(paymentAmount) < balanceDue
                                    ? t('checkout.partialPayment')
                                    : t('checkout.completePayment')}
                            </Button>
                        </div>
                    )}
                </section>
            </div>

            {showManagerPinModal && (
                <ManagerPinModal
                    t={t}
                    onVerified={handleManagerPinVerified}
                    onCancel={() => {
                        setShowManagerPinModal(false);
                        setPendingPayment(false);
                    }}
                />
            )}
        </AdminLayout>
    );
}
