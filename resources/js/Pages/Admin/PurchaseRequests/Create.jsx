import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { formatCurrency } from '@/lib/formatCurrency';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Info, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyLine() {
    return { variant: null, qty: '1', estimated_unit_cost: '0', preferred_supplier_id: '', notes: '' };
}

function Create({
    suppliers = [],
    warehouses = [],
    config,
    branchId,
    currencies = [],
    defaultCurrency = 'USD',
}) {
    const { branch: branchContext, errors: serverErrors } = usePage().props;
    const { t, i18n } = useTranslation();

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
        () => [
            { value: '', label: t('pages.purchaseRequests.noPreferredSupplier') },
            ...suppliers.map((s) => ({ value: String(s.id), label: s.name })),
        ],
        [suppliers, t],
    );
    const warehouseOptions = useMemo(
        () => [
            { value: '', label: t('pages.purchaseRequests.noWarehouse') },
            ...warehouses.map((w) => ({ value: String(w.id), label: w.name })),
        ],
        [warehouses, t],
    );

    const [lines, setLines] = useState([emptyLine()]);
    const [formError, setFormError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const { data, setData, errors } = useForm({
        branch_id: resolvedBranchId ? String(resolvedBranchId) : '',
        warehouse_id: '',
        currency_code: defaultCurrency,
        exchange_rate: '1',
        needed_by: '',
        notes: '',
    });

    const displayErrors = { ...errors, ...(serverErrors ?? {}) };
    const approvalThreshold = config?.pr_approval_threshold ?? 5000;

    const estimatedTotal = useMemo(
        () =>
            lines.reduce((sum, line) => {
                const qty = Number(line.qty) || 0;
                const cost = Number(line.estimated_unit_cost) || 0;
                return sum + qty * cost;
            }, 0),
        [lines],
    );

    const updateLine = (index, patch) => {
        setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    };

    const submit = (e) => {
        e.preventDefault();
        setFormError('');

        if (!data.branch_id) {
            setFormError(t('pages.purchaseRequests.validation.selectBranch'));
            return;
        }

        if (!lines.some((line) => line.variant?.id)) {
            setFormError(t('pages.purchaseRequests.validation.addLine'));
            return;
        }

        for (let index = 0; index < lines.length; index++) {
            const line = lines[index];
            if (!line.variant?.id) {
                continue;
            }
            if (!(Number(line.qty) > 0)) {
                setFormError(t('pages.purchaseRequests.validation.invalidQty', { line: index + 1 }));
                return;
            }
            if (Number(line.estimated_unit_cost) < 0 || line.estimated_unit_cost === '') {
                setFormError(t('pages.purchaseRequests.validation.invalidCost', { line: index + 1 }));
                return;
            }
        }

        setSubmitting(true);
        router.post(
            route('admin.purchase-requests.store'),
            {
                ...data,
                lines: lines
                    .filter((line) => line.variant?.id)
                    .map((line) => ({
                        product_variant_id: line.variant.id,
                        qty: line.qty,
                        estimated_unit_cost: line.estimated_unit_cost,
                        preferred_supplier_id: line.preferred_supplier_id || null,
                        notes: line.notes || null,
                    })),
            },
            {
                onFinish: () => setSubmitting(false),
                onError: () => setSubmitting(false),
            },
        );
    };

    return (
        <>
            <Head title={t('pages.purchaseRequests.createTitle')} />
            <PageHeader
                title={t('pages.purchaseRequests.createTitle')}
                description={t('pages.purchaseRequests.createDescription')}
            >
                <Link href={route('admin.purchase-requests.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-6">
                {formError && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                        {formError}
                    </div>
                )}

                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {branchOptions.length > 1 ? (
                            <AdminFormField label={t('pages.purchaseRequests.fields.branch')} error={displayErrors.branch_id}>
                                <Select
                                    options={branchOptions}
                                    value={data.branch_id}
                                    onChange={(value) => setData('branch_id', value ?? '')}
                                />
                            </AdminFormField>
                        ) : (
                            <AdminFormField label={t('pages.purchaseRequests.fields.branch')}>
                                <input
                                    className="rp-form-input"
                                    value={branchOptions[0]?.label ?? data.branch_id}
                                    disabled
                                    readOnly
                                />
                            </AdminFormField>
                        )}
                        <AdminFormField label={t('pages.purchaseRequests.fields.warehouse')} error={displayErrors.warehouse_id}>
                            <Select
                                options={warehouseOptions}
                                value={data.warehouse_id}
                                onChange={(value) => setData('warehouse_id', value ?? '')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.purchaseRequests.fields.currency')} error={displayErrors.currency_code}>
                            <Select
                                options={currencies}
                                value={data.currency_code}
                                onChange={(value) => setData('currency_code', value ?? defaultCurrency)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.purchaseRequests.fields.neededBy')} error={displayErrors.needed_by}>
                            <input
                                type="date"
                                className="rp-form-input"
                                value={data.needed_by}
                                onChange={(e) => setData('needed_by', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                    <p className="mt-4 flex items-start gap-2 text-xs text-rp-text-muted">
                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        {t('pages.purchaseRequests.approvalHint', { threshold: approvalThreshold })}
                    </p>
                </FormCard>

                <FormCard>
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="rp-form-label mb-0">{t('pages.purchaseRequests.sections.lineItems')}</h3>
                        <button type="button" className="rp-btn-outline" onClick={() => setLines((prev) => [...prev, emptyLine()])}>
                            <Plus className="h-4 w-4" />
                            {t('pages.purchaseRequests.addLine')}
                        </button>
                    </div>

                    <div className="space-y-4">
                        {lines.map((line, index) => (
                            <div key={index} className="rounded-lg border border-rp-border p-4 dark:border-ink-700">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-sm font-medium text-rp-text">
                                        {t('pages.purchaseRequests.sections.lineItems')} {index + 1}
                                    </span>
                                    {lines.length > 1 && (
                                        <button
                                            type="button"
                                            className="text-rose-600 hover:underline"
                                            onClick={() => setLines((prev) => prev.filter((_, i) => i !== index))}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <AdminFormField label={t('pages.purchaseRequests.lineColumns.product')}>
                                        <VariantSearchPicker
                                            searchRoute="api.v1.procurement.product-variants.search"
                                            value={line.variant}
                                            onChange={(variant) => updateLine(index, { variant })}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.purchaseRequests.fields.preferredSupplier')}>
                                        <Select
                                            options={supplierOptions}
                                            value={line.preferred_supplier_id}
                                            onChange={(value) => updateLine(index, { preferred_supplier_id: value ?? '' })}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.purchaseRequests.fields.quantity')}>
                                        <input
                                            type="number"
                                            min="0.0001"
                                            step="any"
                                            className="rp-form-input"
                                            value={line.qty}
                                            onChange={(e) => updateLine(index, { qty: e.target.value })}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.purchaseRequests.lineColumns.estimatedCost')}>
                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            className="rp-form-input"
                                            value={line.estimated_unit_cost}
                                            onChange={(e) => updateLine(index, { estimated_unit_cost: e.target.value })}
                                        />
                                    </AdminFormField>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 flex justify-end text-sm font-medium text-rp-text">
                        {t('pages.purchaseRequests.estimatedTotal')}:{' '}
                        {formatCurrency(estimatedTotal, data.currency_code, i18n.language)}
                    </div>
                </FormCard>

                <FormCard>
                    <AdminFormField label={t('pages.purchaseRequests.fields.notes')} error={displayErrors.notes}>
                        <textarea
                            className="rp-form-input min-h-[80px]"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder={t('pages.purchaseRequests.placeholders.notes')}
                        />
                    </AdminFormField>
                    <div className="mt-4 flex justify-end gap-2 border-t border-rp-border pt-4">
                        <Link href={route('admin.purchase-requests.index')} className="rp-btn-outline">
                            {t('common.cancel')}
                        </Link>
                        <button type="submit" className="rp-btn-primary" disabled={submitting}>
                            {submitting ? t('common.saving') : t('pages.purchaseRequests.createSubmit')}
                        </button>
                    </div>
                </FormCard>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
