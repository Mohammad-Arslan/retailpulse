import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle, FileText, Mail, Package, Send, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-stone-100 text-stone-700',
    submitted: 'bg-amber-100 text-amber-800',
    approved: 'bg-teal-100 text-teal-800',
    rejected: 'bg-rose-100 text-rose-700',
    cancelled: 'bg-stone-100 text-stone-500',
    closed: 'bg-violet-100 text-violet-800',
};

const MATCH_EXCEPTION_STATUSES = ['partially_matched', 'unmatched'];

const invoiceCanPay = (inv) => {
    if (!['approved', 'matched'].includes(inv.status)) {
        return false;
    }
    if (!inv.match_result) {
        return true;
    }

    return inv.match_result.match_status === 'fully_matched';
};

function Show({ order, config, warehouses, approval = {} }) {
    const can = useCan();
    const { t } = useTranslation();
    const { errors: serverErrors } = usePage().props;
    const [warehouseId, setWarehouseId] = useState(warehouses[0]?.id ? String(warehouses[0].id) : '');
    const [receiveLines, setReceiveLines] = useState(
        () =>
            order.items?.map((item) => ({
                purchase_order_item_id: item.id,
                qty_received: Math.max(0, Number(item.qty_ordered) - Number(item.qty_received || 0)),
                batch_no: '',
                expiry_date: '',
            })) ?? [],
    );
    const [managerPin, setManagerPin] = useState('');
    const [pinError, setPinError] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [showReject, setShowReject] = useState(false);
    const [showReceive, setShowReceive] = useState(false);

    const warehouseOptions = useMemo(
        () => warehouses.map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` })),
        [warehouses],
    );

    const needsPin = approval.requiresPin ?? Number(order.total) >= Number(config.po_approval_threshold);
    const approverHasPin = approval.approverHasPin ?? true;

    const submitPo = () => router.post(route('admin.purchase-orders.submit', order.id));
    const approvePo = () => {
        if (needsPin) {
            if (!approverHasPin) {
                setPinError(t('pages.purchaseOrders.noPinConfigured'));
                return;
            }
            if (!/^\d{6}$/.test(managerPin)) {
                setPinError(t('pages.purchaseOrders.pinRequired'));
                return;
            }
        }
        setPinError('');
        router.post(route('admin.purchase-orders.approve', order.id), { manager_pin: managerPin || null });
    };
    const rejectPo = () =>
        router.post(route('admin.purchase-orders.reject', order.id), { rejection_reason: rejectionReason });
    const cancelPo = () => router.post(route('admin.purchase-orders.cancel', order.id));
    const closePo = () => router.post(route('admin.purchase-orders.close', order.id));
    const emailPo = () => router.post(route('admin.purchase-orders.email', order.id));

    const receiveGoods = (e) => {
        e.preventDefault();
        const lines = receiveLines.filter((l) => Number(l.qty_received) > 0);
        if (!warehouseId || lines.length === 0) return;
        router.post(route('admin.purchase-orders.receive', order.id), {
            warehouse_id: Number(warehouseId),
            lines,
        });
    };

    const resolveMatch = (matchId) =>
        router.post(route('admin.po-match-results.resolve', matchId));

    const approveInvoice = (invoiceId) =>
        router.post(route('admin.supplier-invoices.approve', invoiceId));

    const payInvoice = (inv) => {
        if (!order.branch_id || !order.supplier?.id) {
            return;
        }
        router.post(route('admin.supplier-payments.store'), {
            branch_id: order.branch_id,
            supplier_id: order.supplier.id,
            supplier_invoice_id: inv.id,
            amount: Number(inv.total),
            payment_method: config.payment_methods?.[0] ?? 'cash',
            currency_code: order.currency_code,
            exchange_rate: 1,
            payment_date: new Date().toISOString().slice(0, 10),
            is_advance: false,
        });
    };

    const hasWorkflowActions =
        can('procurement.view') ||
        (order.status === 'draft' && can('procurement.create')) ||
        (order.status === 'submitted' && can('procurement.approve-po')) ||
        (order.status === 'approved' && can('procurement.receive-grn'));

    return (
        <>
            <Head title={order.reference_no} />
            <PageHeader
                title={order.reference_no}
                description={
                    order.supplier ? (
                        <Link
                            href={route('admin.suppliers.show', order.supplier.id)}
                            className="hover:text-teal-600 hover:underline"
                        >
                            {order.supplier.name}
                        </Link>
                    ) : (
                        ''
                    )
                }
            >
                <Link href={route('admin.purchase-orders.index')} className="rp-btn-outline">
                    Back to list
                </Link>
            </PageHeader>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <span
                    className={`inline-flex rounded-full px-3 py-1 text-sm font-medium capitalize ${statusClass[order.status] ?? ''}`}
                >
                    {order.status}
                </span>
                <span className="text-sm text-rp-text-muted">
                    Total: <strong className="text-rp-text">{order.total}</strong> {order.currency_code}
                </span>
                {order.drop_ship && (
                    <span className="text-sm font-medium text-amber-600">Drop ship</span>
                )}
            </div>

            {hasWorkflowActions && (
                <div className="mb-6 rounded-lg border bg-card p-5">
                    <h3 className="mb-4 font-medium">{t('pages.purchaseOrders.sections.actions')}</h3>

                    {order.status === 'submitted' && needsPin && can('procurement.approve-po') && (
                        <div className="mb-4 rounded-lg border border-amber-300/60 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
                            <AdminFormField
                                label={t('pages.purchaseOrders.fields.managerPin')}
                                id="manager_pin"
                                error={pinError || serverErrors?.manager_pin}
                                hint={approverHasPin ? t('pages.purchaseOrders.pinHint') : t('pages.purchaseOrders.noPinConfigured')}
                            >
                                <input
                                    id="manager_pin"
                                    className="rp-form-input w-full max-w-sm tracking-[0.35em] font-mono text-lg"
                                    type="password"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    pattern="\d{6}"
                                    maxLength={6}
                                    placeholder={t('pages.purchaseOrders.placeholders.managerPin')}
                                    value={managerPin}
                                    onChange={(e) => {
                                        setManagerPin(e.target.value.replace(/\D/g, '').slice(0, 6));
                                        if (pinError) setPinError('');
                                    }}
                                    disabled={!approverHasPin}
                                />
                            </AdminFormField>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2">
                        {can('procurement.view') && (
                            <a
                                href={route('admin.purchase-orders.pdf', order.id)}
                                target="_blank"
                                rel="noreferrer"
                                className="rp-btn-outline"
                            >
                                <FileText className="h-4 w-4" />
                                Print PDF
                            </a>
                        )}
                        {can('procurement.view') && (
                            <Button type="button" variant="outline" onClick={emailPo}>
                                <Mail className="h-4 w-4" />
                                Email PO
                            </Button>
                        )}
                        {order.status === 'draft' && can('procurement.create') && (
                            <Button type="button" onClick={submitPo}>
                                <Send className="h-4 w-4" />
                                Submit for approval
                            </Button>
                        )}
                        {order.status === 'submitted' && can('procurement.approve-po') && (
                            <>
                                <Button type="button" onClick={() => setShowReject((v) => !v)} variant="outline">
                                    <XCircle className="h-4 w-4" />
                                    Reject
                                </Button>
                                <Button type="button" onClick={approvePo}>
                                    <CheckCircle className="h-4 w-4" />
                                    Approve
                                </Button>
                            </>
                        )}
                        {['draft', 'submitted', 'approved'].includes(order.status) && can('procurement.update') && (
                            <Button type="button" variant="outline" onClick={cancelPo}>
                                Cancel PO
                            </Button>
                        )}
                        {order.status === 'approved' && can('procurement.receive-grn') && (
                            <>
                                <Button type="button" onClick={() => setShowReceive((v) => !v)}>
                                    <Package className="h-4 w-4" />
                                    Receive goods
                                </Button>
                                {can('procurement.update') && (
                                    <Button type="button" variant="outline" onClick={closePo}>
                                        Close PO
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}

            {showReject && (
                <div className="mb-4 rounded-lg border bg-card p-4">
                    <label className="text-sm font-medium">Rejection reason</label>
                    <textarea
                        className="rp-form-input mt-1 w-full"
                        rows={2}
                        value={rejectionReason}
                        onChange={(e) => setRejectionReason(e.target.value)}
                    />
                    <Button type="button" className="mt-2" variant="destructive" onClick={rejectPo}>
                        Confirm reject
                    </Button>
                </div>
            )}

            <div className="mb-6 rounded-lg border bg-card p-6">
                <h3 className="mb-3 font-medium">Line items</h3>
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b text-muted-foreground">
                            <th className="py-2">SKU</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Unit price</th>
                        </tr>
                    </thead>
                    <tbody>
                        {order.items?.map((item) => (
                            <tr key={item.id} className="border-b">
                                <td className="py-2">{item.variant?.sku ?? item.product_variant_id}</td>
                                <td>{item.qty_ordered}</td>
                                <td>{item.qty_received ?? 0}</td>
                                <td>{item.unit_price}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showReceive && order.status === 'approved' && (
                <form onSubmit={receiveGoods} className="mb-6 rounded-lg border border-teal-200 bg-card p-6">
                    <h3 className="mb-3 font-medium">Receive goods (GRN)</h3>
                    <div className="mb-4 max-w-xs">
                        <label className="text-sm">Warehouse</label>
                        <Select options={warehouseOptions} value={warehouseId} onChange={setWarehouseId} />
                    </div>
                    <div className="space-y-2">
                        {order.items?.map((item, idx) => (
                            <div key={item.id} className="grid gap-2 sm:grid-cols-4">
                                <span className="text-sm">{item.variant?.sku}</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="any"
                                    className="rp-form-input"
                                    placeholder="Qty"
                                    value={receiveLines[idx]?.qty_received ?? ''}
                                    onChange={(e) => {
                                        const next = [...receiveLines];
                                        next[idx] = { ...next[idx], qty_received: e.target.value };
                                        setReceiveLines(next);
                                    }}
                                />
                                <input
                                    placeholder="Batch no"
                                    className="rp-form-input"
                                    value={receiveLines[idx]?.batch_no ?? ''}
                                    onChange={(e) => {
                                        const next = [...receiveLines];
                                        next[idx] = { ...next[idx], batch_no: e.target.value };
                                        setReceiveLines(next);
                                    }}
                                />
                                <input
                                    type="date"
                                    className="rp-form-input"
                                    value={receiveLines[idx]?.expiry_date ?? ''}
                                    onChange={(e) => {
                                        const next = [...receiveLines];
                                        next[idx] = { ...next[idx], expiry_date: e.target.value };
                                        setReceiveLines(next);
                                    }}
                                />
                            </div>
                        ))}
                    </div>
                    <Button type="submit" className="mt-4">
                        Post receipt
                    </Button>
                </form>
            )}

            {order.grns?.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">Goods receiving notes</h3>
                    <ul className="space-y-2 text-sm">
                        {order.grns.map((grn) => (
                            <li key={grn.id}>
                                <Link
                                    href={route('admin.goods-receiving-notes.show', grn.id)}
                                    className="font-medium text-teal-600 hover:underline"
                                >
                                    {grn.reference_no}
                                </Link>
                                {' — '}
                                {grn.warehouse?.name} · {grn.status}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {order.supplier_invoices?.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">Supplier invoices &amp; matching</h3>
                    <ul className="space-y-3 text-sm">
                        {order.supplier_invoices.map((inv) => (
                            <li key={inv.id} className="rounded border p-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-medium">{inv.reference_no}</span>
                                    <span className="capitalize">{inv.status}</span>
                                </div>
                                <div>Total: {inv.total} {order.currency_code}</div>
                                {inv.match_result && (
                                    <div className="mt-2 text-amber-700 dark:text-amber-400">
                                        Match: {inv.match_result.match_status.replace(/_/g, ' ')}
                                        {inv.match_result.exception_reason &&
                                            ` — ${inv.match_result.exception_reason}`}
                                        {MATCH_EXCEPTION_STATUSES.includes(inv.match_result.match_status) &&
                                            can('procurement.resolve-match-exception') && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="ml-2"
                                                    onClick={() => resolveMatch(inv.match_result.id)}
                                                >
                                                    Resolve exception
                                                </Button>
                                            )}
                                    </div>
                                )}
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {can('procurement.view') && (
                                        <a
                                            href={route('admin.supplier-invoices.pdf', inv.id)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-muted"
                                        >
                                            <FileText className="h-3.5 w-3.5" />
                                            {t('pages.purchaseOrders.actions.downloadInvoicePdf')}
                                        </a>
                                    )}
                                    {inv.status === 'matched' && can('procurement.create') && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => approveInvoice(inv.id)}
                                        >
                                            Approve invoice
                                        </Button>
                                    )}
                                    {invoiceCanPay(inv) && can('procurement.process-payments') && (
                                        <Button type="button" size="sm" onClick={() => payInvoice(inv)}>
                                            Pay invoice
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}

export default withAdminLayout(Show);
