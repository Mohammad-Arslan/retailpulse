import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { formatCurrency } from '@/lib/formatCurrency';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Info, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyLine() {
    return { variant: null, qty_ordered: '1', unit_price: '', price_override_reason: '', list_price: null };
}

function supplierCurrency(suppliers, supplierId, defaultCurrency, currencies) {
    const supplier = suppliers.find((s) => String(s.id) === String(supplierId));
    const code = supplier?.currency_code?.toUpperCase();
    const allowed = currencies.map((c) => c.value);

    if (code && allowed.includes(code)) {
        return code;
    }

    if (allowed.includes(defaultCurrency)) {
        return defaultCurrency;
    }

    return allowed[0] ?? 'USD';
}

function Create({
    suppliers,
    config,
    branchId,
    preselectedSupplierId = null,
    preselectedSaleId = null,
    currencies = [],
    defaultCurrency = 'USD',
}) {
    const { branch: branchContext, errors: serverErrors } = usePage().props;
    const { t, i18n } = useTranslation();

    const initialSupplierId =
        preselectedSupplierId != null
            ? String(preselectedSupplierId)
            : suppliers[0]?.id
              ? String(suppliers[0].id)
              : '';

    const branchOptions = useMemo(
        () =>
            (branchContext?.options ?? []).map((b) => ({
                value: String(b.id),
                label: `${b.name} (${b.code})`,
            })),
        [branchContext?.options],
    );

    const resolvedBranchId = branchId ?? branchContext?.active?.id ?? null;

    const supplierOptions = useMemo(
        () => suppliers.map((s) => ({ value: String(s.id), label: s.name })),
        [suppliers],
    );

    const [lines, setLines] = useState([emptyLine()]);
    const [formError, setFormError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [saleQuery, setSaleQuery] = useState('');
    const [saleResults, setSaleResults] = useState([]);
    const [selectedSale, setSelectedSale] = useState(
        preselectedSaleId ? { id: preselectedSaleId, label: `Sale #${preselectedSaleId}` } : null,
    );

    const { data, setData, errors } = useForm({
        branch_id: resolvedBranchId ? String(resolvedBranchId) : '',
        supplier_id: initialSupplierId,
        currency_code: supplierCurrency(suppliers, initialSupplierId, defaultCurrency, currencies),
        expected_delivery_date: '',
        notes: '',
        drop_ship: false,
        sale_id: preselectedSaleId ? String(preselectedSaleId) : '',
        exchange_rate: 1,
        lines: [],
    });

    const formatMoney = (amount) => formatCurrency(amount, data.currency_code, i18n.language);

    const approvalThreshold = useMemo(
        () => formatCurrency(config.po_approval_threshold, data.currency_code, i18n.language),
        [config.po_approval_threshold, data.currency_code, i18n.language],
    );

    useEffect(() => {
        if (!data.drop_ship || saleQuery.length < 2) {
            setSaleResults([]);
            return undefined;
        }
        const timer = setTimeout(() => {
            axios
                .get(route('admin.purchase-orders.sales.search'), { params: { q: saleQuery } })
                .then((res) => setSaleResults(res.data ?? []))
                .catch(() => setSaleResults([]));
        }, 300);
        return () => clearTimeout(timer);
    }, [saleQuery, data.drop_ship]);

    const onSupplierChange = (value) => {
        setData('supplier_id', value);
        setData('currency_code', supplierCurrency(suppliers, value, defaultCurrency, currencies));
    };

    const fetchPrice = async (variantId, qty) => {
        if (!data.supplier_id || !variantId) return null;
        try {
            const { data: body } = await window.axios.get(
                route('api.v1.suppliers.variant-price', {
                    supplier: data.supplier_id,
                    variant: variantId,
                    qty,
                }),
            );
            return body?.data?.unit_price ?? null;
        } catch {
            return null;
        }
    };

    const updateLine = async (index, patch) => {
        setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));
        setFormError('');

        if (patch.variant && data.supplier_id) {
            const qty = patch.qty_ordered ?? lines[index]?.qty_ordered ?? 1;
            const price = await fetchPrice(patch.variant.id, Number(qty) || 1);
            if (price !== null) {
                setLines((prev) =>
                    prev.map((line, i) =>
                        i === index
                            ? { ...line, unit_price: String(price), list_price: price }
                            : line,
                    ),
                );
            }
        }
    };

    const addLine = () => setLines((prev) => [...prev, emptyLine()]);

    const removeLine = (index) => {
        setLines((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== index)));
    };

    const lineTotal = (line) => {
        const qty = Number(line.qty_ordered) || 0;
        const price = Number(line.unit_price) || 0;
        return qty * price;
    };

    const estimatedTotal = lines.reduce((sum, line) => sum + lineTotal(line), 0);

    const buildPayloadLines = () =>
        lines
            .filter((line) => line.variant)
            .map((line) => ({
                product_variant_id: line.variant.id,
                qty_ordered: Number(line.qty_ordered),
                unit_price: Number(line.unit_price),
                price_override_reason: line.price_override_reason || null,
                tax_rate: 0,
            }));

    const submit = (e) => {
        e.preventDefault();
        setFormError('');

        if (!data.supplier_id) {
            setFormError(t('pages.purchaseOrders.validation.selectSupplier'));
            return;
        }

        if (!data.branch_id) {
            setFormError(t('pages.purchaseOrders.validation.selectBranch'));
            return;
        }

        const payloadLines = buildPayloadLines();

        if (payloadLines.length === 0) {
            setFormError(t('pages.purchaseOrders.validation.addLine'));
            return;
        }

        for (const [index, line] of lines.entries()) {
            if (!line.variant) continue;
            if (!line.qty_ordered || Number(line.qty_ordered) <= 0) {
                setFormError(t('pages.purchaseOrders.validation.invalidQty', { line: index + 1 }));
                return;
            }
            if (line.unit_price === '' || Number(line.unit_price) < 0) {
                setFormError(t('pages.purchaseOrders.validation.invalidPrice', { line: index + 1 }));
                return;
            }
            if (
                line.list_price !== null &&
                Math.abs(Number(line.list_price) - Number(line.unit_price)) > 0.0001 &&
                !line.price_override_reason?.trim()
            ) {
                setFormError(t('pages.purchaseOrders.validation.overrideRequired', { line: index + 1 }));
                return;
            }
        }

        if (data.drop_ship && !data.sale_id) {
            setFormError(t('pages.purchaseOrders.saleRequired'));
            return;
        }

        router.post(
            route('admin.purchase-orders.store'),
            {
                branch_id: Number(data.branch_id),
                supplier_id: Number(data.supplier_id),
                currency_code: data.currency_code,
                exchange_rate: 1,
                expected_delivery_date: data.expected_delivery_date || null,
                notes: data.notes || null,
                drop_ship: data.drop_ship,
                sale_id: data.drop_ship && data.sale_id ? Number(data.sale_id) : null,
                lines: payloadLines,
            },
            { preserveScroll: true, onStart: () => setSubmitting(true), onFinish: () => setSubmitting(false) },
        );
    };

    const displayErrors = { ...errors, ...serverErrors };

    return (
        <>
            <Head title={t('pages.purchaseOrders.createTitle')} />
            <PageHeader
                title={t('pages.purchaseOrders.createTitle')}
                description={t('pages.purchaseOrders.createDescription')}
            >
                <Link href={route('admin.purchase-orders.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            {(formError || displayErrors.lines) && (
                <div className="mb-4 max-w-4xl rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    {formError || displayErrors.lines}
                </div>
            )}

            <form onSubmit={submit} className="max-w-4xl space-y-5">
                <FormCard className="max-w-none">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {branchId ? (
                        <AdminFormField label={t('pages.purchaseOrders.fields.branch')}>
                                <input
                                    className="rp-form-input w-full bg-rp-surface-inset"
                                    readOnly
                                    value={
                                        branchContext?.active
                                            ? `${branchContext.active.name} (${branchContext.active.code})`
                                            : `Branch #${branchId}`
                                    }
                                />
                            </AdminFormField>
                        ) : (
                            <AdminFormField label={t('pages.purchaseOrders.fields.branch')} error={displayErrors.branch_id}>
                                <Select
                                    options={[{ value: '', label: t('common.selectBranch') }, ...branchOptions]}
                                    value={data.branch_id}
                                    onChange={(value) => setData('branch_id', value)}
                                />
                            </AdminFormField>
                        )}
                        <AdminFormField label={t('pages.purchaseOrders.fields.supplier')} error={displayErrors.supplier_id}>
                            <Select
                                options={
                                    supplierOptions.length
                                        ? supplierOptions
                                        : [{ value: '', label: t('pages.purchaseOrders.noSuppliers') }]
                                }
                                value={data.supplier_id}
                                onChange={onSupplierChange}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.purchaseOrders.fields.currency')} error={displayErrors.currency_code}>
                            <Select
                                options={currencies}
                                value={data.currency_code}
                                onChange={(value) => setData('currency_code', value)}
                                placeholder={t('common.selectCurrency')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.purchaseOrders.fields.expectedDelivery')}
                            error={displayErrors.expected_delivery_date}
                        >
                            <input
                                type="date"
                                className="rp-form-input w-full"
                                value={data.expected_delivery_date}
                                onChange={(e) => setData('expected_delivery_date', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.purchaseOrders.fields.options')}>
                            <label className="flex min-h-[42px] items-center gap-2 text-sm text-rp-text-secondary">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-rp-border text-teal-600"
                                    checked={data.drop_ship}
                                    onChange={(e) => {
                                        const checked = e.target.checked;
                                        setData('drop_ship', checked);
                                        if (!checked) {
                                            setData('sale_id', '');
                                            setSelectedSale(null);
                                            setSaleQuery('');
                                        }
                                    }}
                                />
                                {t('pages.purchaseOrders.fields.dropShip')}
                            </label>
                        </AdminFormField>
                    </div>
                    {data.drop_ship && (
                        <div className="mt-4 space-y-2 border-t pt-4">
                            <AdminFormField
                                label={t('pages.purchaseOrders.fields.linkedSale')}
                                error={displayErrors.sale_id}
                            >
                                {selectedSale ? (
                                    <div className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                                        <span>{selectedSale.label}</span>
                                        <button
                                            type="button"
                                            className="text-xs text-rose-600 hover:underline"
                                            onClick={() => {
                                                setSelectedSale(null);
                                                setData('sale_id', '');
                                            }}
                                        >
                                            {t('common.clear')}
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <input
                                            className="rp-form-input w-full"
                                            value={saleQuery}
                                            onChange={(e) => setSaleQuery(e.target.value)}
                                            placeholder={t('pages.purchaseOrders.placeholders.saleSearch')}
                                        />
                                        {saleResults.length > 0 && (
                                            <ul className="mt-1 max-h-40 overflow-auto rounded-md border text-sm">
                                                {saleResults.map((sale) => (
                                                    <li key={sale.id}>
                                                        <button
                                                            type="button"
                                                            className="w-full px-3 py-2 text-left hover:bg-muted"
                                                            onClick={() => {
                                                                setSelectedSale(sale);
                                                                setData('sale_id', String(sale.id));
                                                                setSaleQuery('');
                                                                setSaleResults([]);
                                                            }}
                                                        >
                                                            {sale.label}
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </>
                                )}
                            </AdminFormField>
                            <p className="text-xs text-rp-text-muted">{t('pages.purchaseOrders.dropShipHint')}</p>
                        </div>
                    )}
                    <div className="mt-4 flex items-start gap-2.5 rounded-lg border border-teal-200/70 bg-teal-50/80 px-4 py-3 text-sm text-teal-900 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-100">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-teal-600 dark:text-teal-300" />
                        <p>{t('pages.purchaseOrders.approvalHint', { threshold: approvalThreshold })}</p>
                    </div>
                </FormCard>

                <FormCard className="max-w-none">
                    <div className="mb-4 flex items-center justify-between gap-3 border-b border-rp-border pb-4">
                        <h3 className="rp-form-label mb-0">{t('pages.purchaseOrders.sections.lineItems')}</h3>
                        <button type="button" onClick={addLine} className="rp-btn-outline text-xs">
                            <Plus className="h-3.5 w-3.5" />
                            {t('pages.purchaseOrders.addLine')}
                        </button>
                    </div>

                    <div className="space-y-4">
                        {lines.map((line, index) => (
                            <div
                                key={index}
                                className="space-y-4 rounded-xl border border-rp-border bg-rp-surface-inset/50 p-4 shadow-sm"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs font-medium uppercase tracking-wide text-rp-text-muted">
                                        {t('pages.purchaseOrders.sections.lineItems')} {index + 1}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => removeLine(index)}
                                        className="rp-btn-outline border-rose-200 px-2 py-1 text-rose-500 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10"
                                        aria-label={t('common.delete')}
                                        disabled={lines.length <= 1}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>

                                <AdminFormField label={t('pages.purchaseOrders.lineColumns.product')}>
                                    <VariantSearchPicker
                                        searchRoute="api.v1.procurement.product-variants.search"
                                        value={line.variant}
                                        onChange={(variant) => updateLine(index, { variant })}
                                    />
                                </AdminFormField>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    <AdminFormField label={t('pages.purchaseOrders.fields.quantity')}>
                                        <input
                                            type="number"
                                            min="0.0001"
                                            step="any"
                                            className="rp-form-input w-full"
                                            value={line.qty_ordered}
                                            placeholder={t('pages.purchaseOrders.placeholders.qty')}
                                            onChange={(e) =>
                                                updateLine(index, { qty_ordered: e.target.value })
                                            }
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.purchaseOrders.lineColumns.unitPrice')}>
                                        <div className="relative">
                                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-rp-text-muted">
                                                {data.currency_code}
                                            </span>
                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                required
                                                className="rp-form-input w-full pl-14"
                                                value={line.unit_price}
                                                onChange={(e) =>
                                                    updateLine(index, { unit_price: e.target.value })
                                                }
                                                placeholder={t('pages.purchaseOrders.placeholders.unitPrice')}
                                            />
                                        </div>
                                    </AdminFormField>
                                    <AdminFormField
                                        label={t('pages.purchaseOrders.fields.overrideReason')}
                                        hint={t('pages.purchaseOrders.hints.overrideReason')}
                                    >
                                        <input
                                            className="rp-form-input w-full"
                                            value={line.price_override_reason}
                                            onChange={(e) =>
                                                updateLine(index, {
                                                    price_override_reason: e.target.value,
                                                })
                                            }
                                            placeholder={t('pages.purchaseOrders.placeholders.priceOverride')}
                                        />
                                    </AdminFormField>
                                </div>

                                <div className="flex justify-end text-sm">
                                    <span className="text-rp-text-muted">
                                        {t('pages.purchaseOrders.lineTotalLabel')}:{' '}
                                        <span className="font-semibold text-rp-text">
                                            {formatMoney(lineTotal(line))}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-5 flex justify-end">
                        <div className="min-w-[220px] rounded-xl border border-teal-200/70 bg-teal-50/50 px-5 py-4 text-right dark:border-teal-500/30 dark:bg-teal-500/10">
                            <span className="text-xs font-medium uppercase tracking-wide text-rp-text-muted">
                                {t('pages.purchaseOrders.estimatedTotal')}
                            </span>
                            <div className="mt-1 text-2xl font-bold text-teal-700 dark:text-teal-300">
                                {formatMoney(estimatedTotal)}
                            </div>
                        </div>
                    </div>
                </FormCard>

                <FormCard className="max-w-none">
                <AdminFormField label={t('pages.purchaseOrders.fields.notes')} error={displayErrors.notes}>
                    <textarea
                        rows={3}
                        className="rp-form-input w-full"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder={t('pages.purchaseOrders.placeholders.notes')}
                    />
                </AdminFormField>
                </FormCard>

                <div className="flex flex-wrap gap-2 pt-1">
                    <button type="submit" className="rp-btn-primary" disabled={submitting}>
                        {submitting ? t('common.saving') : t('pages.purchaseOrders.createSubmit')}
                    </button>
                    <Link href={route('admin.purchase-orders.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
