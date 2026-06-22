import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    grnStatusLabel,
    invoiceStatusLabel,
    matchStatusLabel,
    returnStatusLabel,
} from '@/lib/procurementI18n';
import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

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

function Show({ grn, branchId, paymentMethods = [], warehouses = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().slice(0, 10));
    const [returnReason, setReturnReason] = useState('');
    const [dispatchWarehouseId, setDispatchWarehouseId] = useState(
        warehouses[0]?.id ? String(warehouses[0].id) : '',
    );

    const warehouseOptions = useMemo(
        () => warehouses.map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` })),
        [warehouses],
    );

    const createInvoice = (e) => {
        e.preventDefault();
        const lines =
            grn.items?.map((item) => {
                const qty = Number(item.qty_received);
                const price = Number(item.purchase_order_item?.unit_price ?? 0);
                const lineTotal = qty * price;
                return {
                    grn_item_id: item.id,
                    purchase_order_item_id: item.purchase_order_item_id,
                    product_variant_id: item.product_variant_id,
                    qty_invoiced: qty,
                    unit_price: price,
                    tax_rate: 0,
                    discount_amount: 0,
                    line_total: lineTotal,
                    functional_line_total: lineTotal,
                };
            }) ?? [];

        router.post(route('admin.goods-receiving-notes.invoices.store', grn.id), {
            invoice_date: invoiceDate,
            lines,
        });
    };

    const createReturn = (e) => {
        e.preventDefault();
        const lines =
            grn.items?.map((item) => ({
                grn_item_id: item.id,
                qty_returned: Number(item.qty_received),
            })) ?? [];

        router.post(route('admin.goods-receiving-notes.returns.store', grn.id), {
            reason: returnReason,
            lines,
        });
    };

    const resolveMatch = (matchId) => router.post(route('admin.po-match-results.resolve', matchId));

    const approveInvoice = (invoiceId) => router.post(route('admin.supplier-invoices.approve', invoiceId));

    const payInvoice = (inv) => {
        if (!branchId || !grn.supplier?.id) {
            return;
        }
        router.post(route('admin.supplier-payments.store'), {
            branch_id: branchId,
            supplier_id: grn.supplier.id,
            supplier_invoice_id: inv.id,
            amount: Number(inv.total),
            payment_method: paymentMethods[0] ?? 'cash',
            currency_code: grn.supplier.currency_code ?? 'USD',
            exchange_rate: 1,
            payment_date: new Date().toISOString().slice(0, 10),
            is_advance: false,
        });
    };

    const approveReturn = (returnId) => router.post(route('admin.purchase-returns.approve', returnId));

    const dispatchReturn = (returnId) => {
        if (!dispatchWarehouseId) {
            return;
        }
        router.post(route('admin.purchase-returns.dispatch', returnId), {
            warehouse_id: Number(dispatchWarehouseId),
        });
    };

    const issueDebitNote = (returnId) => router.post(route('admin.purchase-returns.debit-note', returnId));

    return (
        <>
            <Head title={grn.reference_no} />
            <PageHeader
                title={grn.reference_no}
                description={`${grn.supplier?.name} · ${grn.purchase_order?.reference_no}`}
                actions={
                    <Link href={route('admin.goods-receiving-notes.index')} className="rp-btn-outline text-sm">
                        Back
                    </Link>
                }
            />

            <div className="mb-6 rounded-lg border bg-card p-6 text-sm">
                <p>Warehouse: {grn.warehouse?.name}</p>
                <p>Status: {grnStatusLabel(t, grn.status)}</p>
                <p>Received: {grn.received_at}</p>
                {grn.purchase_order && (
                    <p>
                        PO:{' '}
                        <Link
                            href={route('admin.purchase-orders.show', grn.purchase_order.id)}
                            className="text-teal-600 hover:underline"
                        >
                            {grn.purchase_order.reference_no}
                        </Link>
                    </p>
                )}
                <table className="mt-4 w-full text-left">
                    <thead>
                        <tr className="border-b text-muted-foreground">
                            <th className="py-2">SKU</th>
                            <th>Qty received</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        {grn.items?.map((item) => (
                            <tr key={item.id} className="border-b">
                                <td className="py-2">{item.variant?.sku}</td>
                                <td>{item.qty_received}</td>
                                <td>{item.batch_no || '—'}</td>
                                <td>{item.expiry_date || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {grn.supplier_invoices?.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">Supplier invoices</h3>
                    <ul className="space-y-3 text-sm">
                        {grn.supplier_invoices.map((inv) => (
                            <li key={inv.id} className="rounded border p-3">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium">{inv.reference_no}</span>
                                    <span>{invoiceStatusLabel(t, inv.status)}</span>
                                </div>
                                <div>Total: {inv.total}</div>
                                {inv.match_result && (
                                    <div className="mt-2 text-amber-700 dark:text-amber-400">
                                        Match: {matchStatusLabel(t, inv.match_result.match_status)}
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

            {grn.purchase_returns?.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">Purchase returns</h3>
                    <ul className="space-y-3 text-sm">
                        {grn.purchase_returns.map((ret) => (
                            <li key={ret.id} className="rounded border p-3">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium">{ret.reference_no}</span>
                                    <span>{returnStatusLabel(t, ret.status)}</span>
                                </div>
                                {ret.reason && <p className="mt-1 text-muted-foreground">{ret.reason}</p>}
                                {ret.debit_note && (
                                    <p className="mt-1">Debit note: {ret.debit_note.reference_no}</p>
                                )}
                                {can('procurement.manage-returns') && (
                                    <div className="mt-2 flex flex-wrap items-end gap-2">
                                        {ret.status === 'draft' && (
                                            <Button type="button" size="sm" onClick={() => approveReturn(ret.id)}>
                                                Approve return
                                            </Button>
                                        )}
                                        {ret.status === 'approved' && (
                                            <>
                                                <div className="min-w-[200px]">
                                                    <label className="text-xs text-muted-foreground">Dispatch from</label>
                                                    <Select
                                                        options={warehouseOptions}
                                                        value={
                                                            warehouseOptions.find(
                                                                (o) => o.value === dispatchWarehouseId,
                                                            ) ?? null
                                                        }
                                                        onChange={(opt) => setDispatchWarehouseId(opt?.value ?? '')}
                                                    />
                                                </div>
                                                <Button type="button" size="sm" onClick={() => dispatchReturn(ret.id)}>
                                                    Dispatch goods
                                                </Button>
                                            </>
                                        )}
                                        {['goods_dispatched', 'supplier_acknowledged'].includes(ret.status) && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => issueDebitNote(ret.id)}
                                            >
                                                Issue debit note
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                {can('procurement.create') && (
                    <form onSubmit={createInvoice} className="rounded-lg border bg-card p-6">
                        <h3 className="mb-3 font-medium">Create supplier invoice</h3>
                        <label className="text-sm">Invoice date</label>
                        <input
                            type="date"
                            className="rp-input mb-3 w-full"
                            value={invoiceDate}
                            onChange={(e) => setInvoiceDate(e.target.value)}
                        />
                        <Button type="submit">Create invoice from GRN</Button>
                    </form>
                )}

                {can('procurement.manage-returns') && (
                    <form onSubmit={createReturn} className="rounded-lg border bg-card p-6">
                        <h3 className="mb-3 font-medium">Purchase return (RMA)</h3>
                        <label className="text-sm">Reason</label>
                        <textarea
                            className="rp-input mb-3 w-full"
                            rows={2}
                            value={returnReason}
                            onChange={(e) => setReturnReason(e.target.value)}
                            required
                        />
                        <Button type="submit" variant="outline">
                            Create return
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Show);
