import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/ui/button';
import { Printer } from 'lucide-react';
import { checkoutApi, saleApi } from '@/lib/checkoutApi';
import { usePosDialog } from '@/Hooks/usePosDialog';

const METHOD_LABELS = {
    cash: 'Cash',
    card: 'Card',
    mobile_wallet: 'Mobile Wallet',
    bank_transfer: 'Bank Transfer',
    credit: 'Credit',
};

export default function CheckoutIndex({ cartId }) {
    const { error, success, confirmVoidCart } = usePosDialog();

    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [bootstrap, setBootstrap] = useState(null);
    const [sale, setSale] = useState(null);
    const [selectedMethod, setSelectedMethod] = useState('cash');
    const [tenderedAmount, setTenderedAmount] = useState('');
    const [paymentAmount, setPaymentAmount] = useState('');
    const [completed, setCompleted] = useState(false);
    const autoPrintFiredRef = useRef(false);

    const loadBootstrap = useCallback(async () => {
        setLoading(true);
        try {
            const data = await checkoutApi.bootstrap(cartId);
            setBootstrap(data);
            if (data.sale_id) {
                const saleData = await saleApi.get(data.sale_id);
                setSale(saleData);
                setCompleted(saleData.status === 'completed');
            }
        } catch (err) {
            error(err?.response?.data?.message || 'Could not load checkout.');
        } finally {
            setLoading(false);
        }
    }, [cartId, error]);

    useEffect(() => {
        loadBootstrap();
    }, [loadBootstrap]);

    const config = bootstrap?.config ?? {};

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

    async function handleConfirmSale() {
        setProcessing(true);
        try {
            const confirmed = await checkoutApi.confirm(cartId);
            const saleData = await saleApi.get(confirmed.id);
            setSale(saleData);
            success('Sale confirmed', 'Collect payment to complete.');
        } catch (err) {
            error(err?.response?.data?.message || 'Could not confirm sale.');
        } finally {
            setProcessing(false);
        }
    }

    async function handlePayment() {
        if (!sale?.id) return;
        setProcessing(true);
        try {
            const payload = {
                method: selectedMethod,
            };

            if (selectedMethod === 'cash') {
                payload.tendered_amount = parseFloat(tenderedAmount || String(balanceDue));
                payload.amount = balanceDue;
            } else if (config.split_tender_enabled && paymentAmount) {
                payload.amount = parseFloat(paymentAmount);
            } else {
                payload.amount = balanceDue;
            }

            const updated = await saleApi.addPayment(sale.id, payload);

            if (updated.payment_error) {
                error(updated.payment_error);
            }

            setSale(updated);

            if (updated.status === 'completed') {
                setCompleted(true);
                success('Payment complete', `Invoice ${updated.invoice?.number ?? ''}`.trim());
            } else {
                setTenderedAmount('');
                setPaymentAmount('');
            }
        } catch (err) {
            const messages = err?.response?.data?.errors;
            if (messages) {
                error(Object.values(messages).flat().join(' '));
            } else {
                error(err?.response?.data?.message || 'Payment failed.');
            }
        } finally {
            setProcessing(false);
        }
    }

    async function handleBackToPos() {
        if (sale?.id) {
            error('Sale already confirmed. Complete payment or void the sale.');
            return;
        }

        setProcessing(true);
        try {
            await checkoutApi.abandon(cartId);
            router.visit(route('admin.pos.index'));
        } catch (err) {
            error(err?.response?.data?.message || 'Could not return to POS.');
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
            router.visit(route('admin.pos.index'));
        } catch (err) {
            error(err?.response?.data?.message || 'Could not void sale.');
        } finally {
            setProcessing(false);
        }
    }

    if (loading) {
        return (
            <AdminLayout fullHeight>
                <Head title="Checkout" />
                <div className="flex flex-1 items-center justify-center text-muted-foreground">
                    Loading checkout…
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout fullHeight>
            <Head title="Checkout" />
            <div className="flex h-full flex-col gap-4 p-4 lg:flex-row">
                <section className="flex flex-1 flex-col rounded-lg border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold">Checkout</h1>
                            <p className="text-sm text-muted-foreground">Cart {cartId}</p>
                        </div>
                        <div className="flex gap-2">
                            {!completed && (
                                <Button variant="outline" onClick={handleBackToPos} disabled={processing}>
                                    Back to POS
                                </Button>
                            )}
                            {!completed && (
                                <Button variant="destructive" onClick={handleVoid} disabled={processing}>
                                    Void
                                </Button>
                            )}
                            {completed && config.receipt_print_mode !== 'off' && (
                                <Button variant="outline" onClick={handlePrint}>
                                    <Printer className="mr-2 h-4 w-4" />
                                    Print Receipt
                                </Button>
                            )}
                            {completed && (
                                <Button onClick={() => router.visit(route('admin.pos.index'))}>
                                    New Sale
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
                                    {config.tax_enabled && (
                                        <th className="py-2 text-right">Tax</th>
                                    )}
                                    <th className="py-2 text-right">Total</th>
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
                            <span>Subtotal</span>
                            <span>{bootstrap?.subtotal} {bootstrap?.currency}</span>
                        </div>
                        {parseFloat(bootstrap?.total_discount ?? '0') > 0 && (
                            <div className="flex justify-between text-green-600">
                                <span>Discount</span>
                                <span>-{bootstrap?.total_discount} {bootstrap?.currency}</span>
                            </div>
                        )}
                        {config.tax_enabled && (
                            <div className="flex justify-between text-muted-foreground">
                                <span>Tax</span>
                                <span>{bootstrap?.tax_total} {bootstrap?.currency}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-lg font-semibold">
                            <span>Total</span>
                            <span>{bootstrap?.grand_total} {bootstrap?.currency}</span>
                        </div>
                        {sale && (
                            <div className="flex justify-between text-amber-600">
                                <span>Balance Due</span>
                                <span>{sale.balance_due} {bootstrap?.currency}</span>
                            </div>
                        )}
                    </div>
                </section>

                <section className="w-full rounded-lg border bg-card p-4 lg:w-96">
                    {!sale ? (
                        <div className="space-y-4">
                            <h2 className="font-semibold">Confirm Sale</h2>
                            <p className="text-sm text-muted-foreground">
                                Review the cart and confirm to begin payment collection.
                            </p>
                            <Button className="w-full" onClick={handleConfirmSale} disabled={processing}>
                                Confirm & Collect Payment
                            </Button>
                        </div>
                    ) : completed ? (
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <div className="h-3 w-3 rounded-full bg-green-500" />
                                <h2 className="font-semibold text-green-600">Sale Complete</h2>
                            </div>

                            {sale.invoice && (
                                <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                    <div className="font-medium">{sale.invoice.number}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {new Date().toLocaleString()}
                                    </div>
                                    {config.fbr_enabled && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            FBR: {sale.invoice.fbr_status ?? 'pending'}
                                            {sale.invoice.fbr_invoice_number && (
                                                <span className="ml-1 font-mono">{sale.invoice.fbr_invoice_number}</span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {(sale.payments ?? []).map((payment) => (
                                <div key={payment.id} className="rounded border p-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="font-medium">{METHOD_LABELS[payment.method] ?? payment.method}</span>
                                        <span>{payment.amount} {bootstrap?.currency}</span>
                                    </div>
                                    {payment.meta?.change_due > 0 && (
                                        <div className="flex justify-between text-green-600">
                                            <span>Change</span>
                                            <span>{Number(payment.meta.change_due).toFixed(2)} {bootstrap?.currency}</span>
                                        </div>
                                    )}
                                </div>
                            ))}

                            {config.receipt_print_mode !== 'off' && (
                                <Button
                                    className="w-full"
                                    variant={config.receipt_print_mode === 'auto' ? 'outline' : 'default'}
                                    onClick={handlePrint}
                                >
                                    <Printer className="mr-2 h-4 w-4" />
                                    {config.receipt_print_mode === 'auto' ? 'Reprint Receipt' : 'Print Receipt'}
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <h2 className="font-semibold">Payment</h2>

                            <div className="grid grid-cols-2 gap-2">
                                {(config.payment_methods ?? ['cash']).map((method) => (
                                    <Button
                                        key={method}
                                        type="button"
                                        variant={selectedMethod === method ? 'default' : 'outline'}
                                        onClick={() => setSelectedMethod(method)}
                                    >
                                        {METHOD_LABELS[method] ?? method}
                                    </Button>
                                ))}
                            </div>

                            {selectedMethod === 'cash' && config.cash_change_enabled && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium">Tendered Amount</label>
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
                                            Change: {changeDue.toFixed(2)} {bootstrap?.currency}
                                        </p>
                                    )}
                                </div>
                            )}

                            {config.split_tender_enabled && selectedMethod !== 'cash' && balanceDue > 0 && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium">Payment Amount</label>
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
                                    <h3 className="text-sm font-medium">Applied Payments</h3>
                                    {sale.payments.map((payment) => (
                                        <div key={payment.id} className="rounded border px-2 py-1 text-sm">
                                            {METHOD_LABELS[payment.method] ?? payment.method}: {payment.amount}
                                            {payment.status === 'failed' && (
                                                <span className="ml-2 text-destructive">Failed</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            <Button className="w-full" onClick={handlePayment} disabled={processing}>
                                {config.split_tender_enabled && paymentAmount && parseFloat(paymentAmount) < balanceDue
                                    ? 'Apply Partial Payment'
                                    : 'Complete Payment'}
                            </Button>
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
