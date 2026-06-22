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
import { formatCurrency } from '@/lib/formatCurrency';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, FileText, Trash2 } from 'lucide-react';
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
    const { t, i18n } = useTranslation();
    const { errors: serverErrors } = usePage().props;
    const currencyCode = grn.supplier?.currency_code ?? 'USD';
    const formatMoney = (amount) => formatCurrency(amount, currencyCode, i18n.language);
    const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().slice(0, 10));
    const [returnReason, setReturnReason] = useState('');
    const [dispatchWarehouseId, setDispatchWarehouseId] = useState(
        warehouses[0]?.id ? String(warehouses[0].id) : '',
    );
    const [lcChargeType, setLcChargeType] = useState(landedCostConfig.charge_types?.[0] ?? '');
    const [lcAmount, setLcAmount] = useState('');
    const [lcAllocation, setLcAllocation] = useState(landedCostConfig.allocation_methods?.[0] ?? 'quantity');
    const [lcDescription, setLcDescription] = useState('');
    const [manualAllocations, setManualAllocations] = useState(() =>
        (grn.items ?? []).map((item) => ({ grn_item_id: item.id, amount: '' })),
    );
    const [returnLines, setReturnLines] = useState(() =>
        (grn.items ?? []).map((item) => ({
            grn_item_id: item.id,
            product_variant_id: item.product_variant_id,
            sku: item.variant?.sku ?? '',
            unitPrice: Number(item.purchase_order_item?.unit_price ?? 0),
            qtyOrdered: Number(item.qty_ordered ?? 0),
            qtyReceivedOnPo: Number(item.qty_received_on_po ?? 0),
            qtyRemainingOnPo: Math.max(
                0,
                Number(item.qty_ordered ?? 0) - Number(item.qty_received_on_po ?? 0),
            ),
            maxQty: Number(item.qty_returnable ?? item.qty_received ?? 0),
            qty_returned: '',
        })),
    );

    const hasReturnableQty = returnLines.some((line) => line.maxQty > 0);

    const returnTotal = useMemo(
        () =>
            returnLines.reduce((sum, line) => {
                const qty = Number(line.qty_returned) || 0;
                return sum + qty * line.unitPrice;
            }, 0),
        [returnLines],
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
        const lines = returnLines
            .filter((line) => Number(line.qty_returned) > 0)
            .map((line) => {
                const qty = Number(line.qty_returned);
                const unitCost = line.unitPrice;
                return {
                    grn_item_id: line.grn_item_id,
                    product_variant_id: line.product_variant_id,
                    qty_returned: qty,
                    unit_cost: unitCost,
                    line_total: qty * unitCost,
                };
            });

        if (lines.length === 0) {
            return;
        }

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
        const payload = {
            charge_type: lcChargeType,
            amount: Number(lcAmount),
            currency_code: grn.supplier?.currency_code ?? 'USD',
            exchange_rate: 1,
            allocation_method: lcAllocation,
            description: lcDescription || null,
        };

        if (lcAllocation === 'manual') {
            payload.manual_allocations = manualAllocations
                .filter((row) => Number(row.amount) > 0)
                .map((row) => ({
                    grn_item_id: row.grn_item_id,
                    amount: Number(row.amount),
                }));
        }

        router.post(route('admin.goods-receiving-notes.landed-costs.store', grn.id), payload);
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

            {grn.purchase_order?.can_receive_more && (
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                    <div className="flex items-start gap-2.5">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>{t('pages.goodsReceiving.partialReceiptNotice')}</p>
                    </div>
                    <Link
                        href={route('admin.purchase-orders.show', grn.purchase_order.id)}
                        className="shrink-0 font-medium text-teal-700 hover:underline dark:text-teal-300"
                    >
                        {t('pages.goodsReceiving.receiveRemaining')}
                    </Link>
                </div>
            )}

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
                            <th className="py-2">{t('pages.purchaseOrders.lineColumns.sku')}</th>
                            <th>{t('pages.goodsReceiving.qtyOrdered')}</th>
                            <th>{t('pages.goodsReceiving.columns.qtyReceived')}</th>
                            <th>{t('pages.goodsReceiving.qtyRemainingOnPo')}</th>
                            <th>{t('pages.goodsReceiving.columns.batch')}</th>
                            <th>{t('pages.goodsReceiving.columns.expiry')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {grn.items?.map((item) => {
                            const remaining = Math.max(
                                0,
                                Number(item.qty_ordered ?? 0) - Number(item.qty_received_on_po ?? 0),
                            );

                            return (
                                <tr key={item.id} className="border-b">
                                    <td className="py-2">{item.variant?.sku}</td>
                                    <td>{item.qty_ordered ?? '—'}</td>
                                    <td>{item.qty_received}</td>
                                    <td>
                                        {remaining > 0 ? (
                                            <span className="font-medium text-amber-700 dark:text-amber-300">
                                                {remaining}
                                            </span>
                                        ) : (
                                            '0'
                                        )}
                                    </td>
                                    <td>{item.batch_no || '—'}</td>
                                    <td>{item.expiry_date || '—'}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {(can('procurement.create') || can('procurement.receive-grn')) && (
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
                        {lcAllocation === 'manual' && (
                            <div className="mt-4 rounded-lg border border-rp-border p-4">
                                <p className="mb-3 text-sm font-medium text-rp-text">
                                    {t('pages.goodsReceiving.landedCost.manualAllocations')}
                                </p>
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b text-muted-foreground">
                                            <th className="py-2">{t('pages.purchaseOrders.lineColumns.sku')}</th>
                                            <th>{t('pages.goodsReceiving.landedCost.allocatedAmount')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {manualAllocations.map((row, index) => {
                                            const item = grn.items?.find((i) => i.id === row.grn_item_id);
                                            return (
                                                <tr key={row.grn_item_id} className="border-b">
                                                    <td className="py-2">{item?.variant?.sku ?? '—'}</td>
                                                    <td>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="any"
                                                            className="rp-form-input w-32"
                                                            value={row.amount}
                                                            onChange={(e) => {
                                                                const next = [...manualAllocations];
                                                                next[index] = { ...row, amount: e.target.value };
                                                                setManualAllocations(next);
                                                            }}
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <div className="mt-4 flex justify-end border-t border-rp-border pt-4">
                            <Button type="submit" variant="brand">{t('pages.goodsReceiving.landedCost.add')}</Button>
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
                                <div>{t('pages.goodsReceiving.totalLabel')}: {inv.total}</div>
                                {inv.match_result && (
                                    <div className="mt-2 text-amber-700 dark:text-amber-400">
                                        {t('pages.goodsReceiving.matchLabel')}: {matchStatusLabel(t, inv.match_result.match_status)}
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
                                                    {t('pages.purchaseOrders.actions.resolveException')}
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
                                            {t('pages.purchaseOrders.actions.approveInvoice')}
                                        </Button>
                                    )}
                                    {invoiceCanPay(inv) && can('procurement.process-payments') && (
                                        <Button type="button" size="sm" onClick={() => payInvoice(inv)}>
                                            {t('pages.purchaseOrders.actions.payInvoice')}
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
                                    <div className="mt-3 flex flex-wrap justify-end gap-2 border-t border-rp-border pt-3">
                                        {ret.status === 'draft' && (
                                            <Button type="button" size="sm" variant="brand" onClick={() => approveReturn(ret.id)}>
                                                {t('pages.goodsReceiving.approveReturn')}
                                            </Button>
                                        )}
                                        {ret.status === 'approved' && (
                                            <>
                                                <div className="mr-auto min-w-[200px]">
                                                    <label className="text-xs text-rp-text-muted">
                                                        {t('pages.goodsReceiving.dispatchFrom')}
                                                    </label>
                                                    <Select
                                                        options={warehouseOptions}
                                                        value={dispatchWarehouseId}
                                                        onChange={(value) => setDispatchWarehouseId(value ?? '')}
                                                    />
                                                </div>
                                                <Button type="button" size="sm" variant="brand" onClick={() => dispatchReturn(ret.id)}>
                                                    {t('pages.goodsReceiving.dispatchGoods')}
                                                </Button>
                                            </>
                                        )}
                                        {ret.status === 'goods_dispatched' && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => acknowledgeReturn(ret.id)}
                                            >
                                                {t('pages.goodsReceiving.acknowledgeReturn')}
                                            </Button>
                                        )}
                                        {['goods_dispatched', 'supplier_acknowledged'].includes(ret.status) && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="brand"
                                                onClick={() => issueDebitNote(ret.id)}
                                            >
                                                {t('pages.goodsReceiving.issueDebitNote')}
                                            </Button>
                                        )}
                                        {ret.status === 'debit_note_issued' && (
                                            <Button type="button" size="sm" variant="brand" onClick={() => closeReturn(ret.id)}>
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

            <div className="space-y-6">
                {can('procurement.create') && (
                    <div className="rounded-lg border bg-card p-6">
                        <h3 className="mb-3 font-medium">{t('pages.goodsReceiving.createInvoice')}</h3>
                        <form onSubmit={createInvoice}>
                            <AdminFormField label={t('pages.goodsReceiving.invoiceDate')} id="invoice_date">
                                <input
                                    id="invoice_date"
                                    type="date"
                                    className="rp-form-input w-full max-w-xs"
                                    value={invoiceDate}
                                    onChange={(e) => setInvoiceDate(e.target.value)}
                                />
                            </AdminFormField>
                            <div className="mt-4 flex justify-end border-t border-rp-border pt-4">
                                <Button type="submit" variant="brand">{t('pages.goodsReceiving.createInvoiceSubmit')}</Button>
                            </div>
                        </form>
                    </div>
                )}

                {can('procurement.manage-returns') && (
                    <div className="rounded-lg border bg-card p-6">
                        <h3 className="mb-1 font-medium">{t('pages.goodsReceiving.purchaseReturn')}</h3>
                        <p className="mb-4 text-sm text-rp-text-muted">
                            {hasReturnableQty
                                ? t('pages.goodsReceiving.hints.returnQty')
                                : t('pages.goodsReceiving.noReturnableQty')}
                        </p>

                        {(serverErrors?.reason || serverErrors?.lines || serverErrors?.['lines.0.qty_returned']) && (
                            <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                                {serverErrors.reason || serverErrors.lines || serverErrors['lines.0.qty_returned']}
                            </div>
                        )}

                        <form onSubmit={createReturn} className="space-y-4">
                            <AdminFormField label={t('pages.goodsReceiving.returnReason')} id="return_reason">
                                <textarea
                                    id="return_reason"
                                    className="rp-form-input w-full"
                                    rows={2}
                                    value={returnReason}
                                    onChange={(e) => setReturnReason(e.target.value)}
                                    required
                                    disabled={!hasReturnableQty}
                                />
                            </AdminFormField>

                            <div className="overflow-hidden rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2">{t('pages.purchaseOrders.lineColumns.sku')}</th>
                                            <th className="px-3 py-2">{t('pages.goodsReceiving.qtyReturnable')}</th>
                                            <th className="px-3 py-2">{t('pages.purchaseOrders.lineColumns.unitPrice')}</th>
                                            <th className="px-3 py-2">{t('pages.goodsReceiving.returnQty')}</th>
                                            <th className="px-3 py-2 text-right">{t('pages.goodsReceiving.returnLineTotal')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {returnLines.map((line, index) => {
                                            const qty = Number(line.qty_returned) || 0;
                                            const lineTotal = qty * line.unitPrice;

                                            return (
                                                <tr key={line.grn_item_id} className="border-b">
                                                    <td className="px-3 py-2 font-medium">{line.sku || '—'}</td>
                                                    <td className="px-3 py-2">{line.maxQty}</td>
                                                    <td className="px-3 py-2">{formatMoney(line.unitPrice)}</td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max={line.maxQty}
                                                            step="any"
                                                            className="rp-form-input w-24"
                                                            value={line.qty_returned}
                                                            disabled={line.maxQty <= 0}
                                                            onChange={(e) => {
                                                                const next = [...returnLines];
                                                                next[index] = { ...line, qty_returned: e.target.value };
                                                                setReturnLines(next);
                                                            }}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-medium">
                                                        {qty > 0 ? formatMoney(lineTotal) : '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-rp-border pt-4">
                                <p className="text-sm text-rp-text-muted">
                                    {t('pages.goodsReceiving.returnLineTotal')}:{' '}
                                    <span className="font-semibold text-rp-text">{formatMoney(returnTotal)}</span>
                                </p>
                                <Button
                                    type="submit"
                                    variant="brand"
                                    disabled={!hasReturnableQty}
                                >
                                    {t('pages.goodsReceiving.createReturn')}
                                </Button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Show);
