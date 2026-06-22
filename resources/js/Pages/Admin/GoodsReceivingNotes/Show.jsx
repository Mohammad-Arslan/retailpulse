import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    allocationMethodLabel,
    chargeTypeLabel,
    grnStatusLabel,
    invoiceStatusLabel,
    matchStatusLabel,
    returnStatusLabel,
} from '@/lib/procurementI18n';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Trash2 } from 'lucide-react';
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

function Show({ grn, branchId, paymentMethods = [], warehouses = [], landedCostConfig = {} }) {
    const can = useCan();
    const { t } = useTranslation();
    const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().slice(0, 10));
    const [returnReason, setReturnReason] = useState('');
    const [dispatchWarehouseId, setDispatchWarehouseId] = useState(
        warehouses[0]?.id ? String(warehouses[0].id) : '',
    );
    const [lcChargeType, setLcChargeType] = useState(landedCostConfig.charge_types?.[0] ?? '');
    const [lcAmount, setLcAmount] = useState('');
    const [lcAllocation, setLcAllocation] = useState(landedCostConfig.allocation_methods?.[0] ?? 'quantity');
    const [lcDescription, setLcDescription] = useState('');

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

    const acknowledgeReturn = (returnId) => router.post(route('admin.purchase-returns.acknowledge', returnId));

    const closeReturn = (returnId) => router.post(route('admin.purchase-returns.close', returnId));

    const addLandedCost = (e) => {
        e.preventDefault();
        router.post(route('admin.goods-receiving-notes.landed-costs.store', grn.id), {
            charge_type: lcChargeType,
            amount: Number(lcAmount),
            currency_code: grn.supplier?.currency_code ?? 'USD',
            exchange_rate: 1,
            allocation_method: lcAllocation,
            description: lcDescription || null,
        });
    };

    const removeLandedCost = (entryId) => {
        if (!confirm(t('pages.goodsReceiving.landedCost.deleteConfirm'))) return;
        router.delete(route('admin.goods-receiving-notes.landed-costs.destroy', [grn.id, entryId]));
    };

    const chargeTypeOptions = useMemo(
        () =>
            (landedCostConfig.charge_types ?? []).map((c) => ({
                value: c,
                label: chargeTypeLabel(t, c),
            })),
        [landedCostConfig.charge_types, t],
    );

    const allocationOptions = useMemo(
        () =>
            (landedCostConfig.allocation_methods ?? ['quantity', 'weight', 'value']).map((m) => ({
                value: m,
                label: allocationMethodLabel(t, m),
            })),
        [landedCostConfig.allocation_methods, t],
    );

    return (
        <>
            <Head title={grn.reference_no} />
            <PageHeader
                title={grn.reference_no}
                description={`${grn.supplier?.name} · ${grn.purchase_order?.reference_no}`}
                actions={
                    <Link href={route('admin.goods-receiving-notes.index')} className="rp-btn-outline text-sm">
                        {t('common.back')}
                    </Link>
                }
            />

            <div className="mb-6 rounded-lg border bg-card p-6 text-sm">
                <p>{t('pages.goodsReceiving.warehouse')}: {grn.warehouse?.name}</p>
                <p>{t('pages.goodsReceiving.status')}: {grnStatusLabel(t, grn.status)}</p>
                <p>{t('pages.goodsReceiving.received')}: {grn.received_at?.slice(0, 10)}</p>
                {grn.is_virtual && (
                    <p className="mt-1 font-medium text-amber-600">{t('pages.goodsReceiving.virtualReceive')}</p>
                )}
                {grn.purchase_order && (
                    <p>
                        {t('pages.goodsReceiving.po')}:{' '}
                        <Link
                            href={route('admin.purchase-orders.show', grn.purchase_order.id)}
                            className="text-teal-600 hover:underline"
                        >
                            {grn.purchase_order.reference_no}
                        </Link>
                        {grn.purchase_order.drop_ship && (
                            <span className="ml-2 text-amber-600">({t('pages.purchaseOrders.fields.dropShip')})</span>
                        )}
                    </p>
                )}
                {grn.purchase_order?.sale && (
                    <p>
                        {t('pages.goodsReceiving.linkedSale')}:{' '}
                        <Link
                            href={route('admin.sales.show', grn.purchase_order.sale.id)}
                            className="text-teal-600 hover:underline"
                        >
                            {grn.purchase_order.sale.invoice_no || `#${grn.purchase_order.sale.id}`}
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

            {can('procurement.receive-grn') && (
                <FormCard className="mb-6 max-w-none">
                    <h3 className="rp-form-label mb-4">{t('pages.goodsReceiving.landedCost.title')}</h3>
                    {(grn.landed_cost_entries?.length ?? 0) > 0 && (
                        <ul className="mb-6 space-y-3 text-sm">
                            {grn.landed_cost_entries.map((entry) => (
                                <li key={entry.id} className="rounded-lg border border-rp-border bg-rp-surface-inset p-4">
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <span className="font-medium text-rp-text">
                                            {chargeTypeLabel(t, entry.charge_type)} — {entry.amount}{' '}
                                            {entry.currency_code}
                                        </span>
                                        <span className="text-rp-text-muted">
                                            {allocationMethodLabel(t, entry.allocation_method)}
                                        </span>
                                    </div>
                                    {entry.description && (
                                        <p className="mt-1 text-rp-text-secondary">{entry.description}</p>
                                    )}
                                    {entry.allocations?.length > 0 && (
                                        <ul className="mt-2 space-y-0.5 text-xs text-rp-text-muted">
                                            {entry.allocations.map((a) => {
                                                const item = grn.items?.find((i) => i.id === a.grn_item_id);
                                                return (
                                                    <li key={a.grn_item_id}>
                                                        {item?.variant?.sku ?? `Line #${a.grn_item_id}`}:{' '}
                                                        {a.allocated_amount.toFixed(2)}
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="mt-3"
                                        onClick={() => removeLandedCost(entry.id)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        {t('common.delete')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <form onSubmit={addLandedCost} className="border-t border-rp-border pt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField label={t('pages.goodsReceiving.landedCost.chargeType')} id="lc_charge_type">
                                <Select
                                    options={chargeTypeOptions}
                                    value={lcChargeType}
                                    onChange={setLcChargeType}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.goodsReceiving.landedCost.amount')} id="lc_amount">
                                <input
                                    id="lc_amount"
                                    type="number"
                                    min="0.01"
                                    step="any"
                                    required
                                    className="rp-form-input"
                                    value={lcAmount}
                                    onChange={(e) => setLcAmount(e.target.value)}
                                    placeholder="0.00"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.goodsReceiving.landedCost.allocationMethod')}
                                id="lc_allocation"
                            >
                                <Select
                                    options={allocationOptions}
                                    value={lcAllocation}
                                    onChange={setLcAllocation}
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.goodsReceiving.landedCost.description')}
                                id="lc_description"
                            >
                                <input
                                    id="lc_description"
                                    className="rp-form-input"
                                    value={lcDescription}
                                    onChange={(e) => setLcDescription(e.target.value)}
                                    placeholder={t('pages.goodsReceiving.landedCost.descriptionPlaceholder')}
                                />
                            </AdminFormField>
                        </div>
                        <div className="mt-4">
                            <Button type="submit">{t('pages.goodsReceiving.landedCost.add')}</Button>
                        </div>
                    </form>
                </FormCard>
            )}

            {grn.supplier_invoices?.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">{t('pages.goodsReceiving.supplierInvoices')}</h3>
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
                    <h3 className="mb-3 font-medium">{t('pages.goodsReceiving.purchaseReturns')}</h3>
                    <ul className="space-y-3 text-sm">
                        {grn.purchase_returns.map((ret) => (
                            <li key={ret.id} className="rounded border p-3">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium">{ret.reference_no}</span>
                                    <span>{returnStatusLabel(t, ret.status)}</span>
                                </div>
                                {ret.reason && <p className="mt-1 text-muted-foreground">{ret.reason}</p>}
                                {ret.debit_note && (
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <span>
                                            {t('pages.goodsReceiving.debitNote')}: {ret.debit_note.reference_no}
                                        </span>
                                        <a
                                            href={route('admin.debit-notes.pdf', ret.debit_note.id)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-muted"
                                        >
                                            <FileText className="h-3.5 w-3.5" />
                                            {t('pages.goodsReceiving.downloadDebitNotePdf')}
                                        </a>
                                    </div>
                                )}
                                {can('procurement.manage-returns') && (
                                    <div className="mt-2 flex flex-wrap items-end gap-2">
                                        {ret.status === 'draft' && (
                                            <Button type="button" size="sm" onClick={() => approveReturn(ret.id)}>
                                                {t('pages.goodsReceiving.approveReturn')}
                                            </Button>
                                        )}
                                        {ret.status === 'approved' && (
                                            <>
                                                <div className="min-w-[200px]">
                                                    <label className="text-xs text-muted-foreground">
                                                        {t('pages.goodsReceiving.dispatchFrom')}
                                                    </label>
                                                    <Select
                                                        options={warehouseOptions}
                                                        value={dispatchWarehouseId}
                                                        onChange={(value) => setDispatchWarehouseId(value ?? '')}
                                                    />
                                                </div>
                                                <Button type="button" size="sm" onClick={() => dispatchReturn(ret.id)}>
                                                    {t('pages.goodsReceiving.dispatchGoods')}
                                                </Button>
                                            </>
                                        )}
                                        {ret.status === 'goods_dispatched' && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => acknowledgeReturn(ret.id)}
                                            >
                                                {t('pages.goodsReceiving.acknowledgeReturn')}
                                            </Button>
                                        )}
                                        {['goods_dispatched', 'supplier_acknowledged'].includes(ret.status) && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => issueDebitNote(ret.id)}
                                            >
                                                {t('pages.goodsReceiving.issueDebitNote')}
                                            </Button>
                                        )}
                                        {ret.status === 'debit_note_issued' && (
                                            <Button type="button" size="sm" onClick={() => closeReturn(ret.id)}>
                                                {t('pages.goodsReceiving.closeReturn')}
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
                        <h3 className="mb-3 font-medium">{t('pages.goodsReceiving.createInvoice')}</h3>
                        <label className="text-sm">{t('pages.goodsReceiving.invoiceDate')}</label>
                        <input
                            type="date"
                            className="rp-form-input mb-3 w-full"
                            value={invoiceDate}
                            onChange={(e) => setInvoiceDate(e.target.value)}
                        />
                        <Button type="submit">{t('pages.goodsReceiving.createInvoiceSubmit')}</Button>
                    </form>
                )}

                {can('procurement.manage-returns') && (
                    <form onSubmit={createReturn} className="rounded-lg border bg-card p-6">
                        <h3 className="mb-3 font-medium">{t('pages.goodsReceiving.purchaseReturn')}</h3>
                        <label className="text-sm">{t('pages.goodsReceiving.returnReason')}</label>
                        <textarea
                            className="rp-form-input mb-3 w-full"
                            rows={2}
                            value={returnReason}
                            onChange={(e) => setReturnReason(e.target.value)}
                            required
                        />
                        <Button type="submit" variant="outline">
                            {t('pages.goodsReceiving.createReturn')}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Show);
