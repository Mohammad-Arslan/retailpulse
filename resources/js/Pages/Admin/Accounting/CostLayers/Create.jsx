import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ warehouses = [], variants = [] }) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, reset } = useForm({
        product_variant_id: '',
        warehouse_id: '',
        qty: '',
        unit_cost: '',
        batch_no: '',
        received_at: new Date().toISOString().slice(0, 10),
        reason: '',
    });

    const warehouseOptions = useMemo(
        () => [
            { value: '', label: '—' },
            ...warehouses.map((warehouse) => ({
                value: String(warehouse.id),
                label: `${warehouse.code} — ${warehouse.name}`,
            })),
        ],
        [warehouses],
    );

    const variantOptions = useMemo(
        () => [
            { value: '', label: '—' },
            ...variants.map((variant) => ({
                value: String(variant.id),
                label: `${variant.sku} — ${variant.product_name ?? variant.name}`,
            })),
        ],
        [variants],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.accounting.cost-layers.store'), {
            preserveScroll: true,
            onSuccess: () => reset('qty', 'unit_cost', 'batch_no', 'reason'),
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.costLayers.title')} />
            <PageHeader
                title={t('pages.accounting.costLayers.title')}
                description={t('pages.accounting.costLayers.description')}
            />

            <div className="mb-6 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                <p>{t('pages.accounting.costLayers.warning')}</p>
            </div>

            <form onSubmit={submit}>
                <FormCard className="max-w-2xl">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.productVariant')}
                            id="product_variant_id"
                            error={errors.product_variant_id}
                            className="sm:col-span-2"
                        >
                            <Select
                                id="product_variant_id"
                                value={data.product_variant_id}
                                onChange={(value) => setData('product_variant_id', value ?? '')}
                                options={variantOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.warehouse')}
                            id="warehouse_id"
                            error={errors.warehouse_id}
                            className="sm:col-span-2"
                        >
                            <Select
                                id="warehouse_id"
                                value={data.warehouse_id}
                                onChange={(value) => setData('warehouse_id', value ?? '')}
                                options={warehouseOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.qty')}
                            id="qty"
                            error={errors.qty}
                        >
                            <input
                                id="qty"
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={data.qty}
                                onChange={(e) => setData('qty', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.unitCost')}
                            id="unit_cost"
                            error={errors.unit_cost}
                        >
                            <input
                                id="unit_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.unit_cost}
                                onChange={(e) => setData('unit_cost', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.batchNo')}
                            id="batch_no"
                            error={errors.batch_no}
                        >
                            <input
                                id="batch_no"
                                type="text"
                                value={data.batch_no}
                                onChange={(e) => setData('batch_no', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.receivedAt')}
                            id="received_at"
                            error={errors.received_at}
                        >
                            <input
                                id="received_at"
                                type="date"
                                value={data.received_at}
                                onChange={(e) => setData('received_at', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.costLayers.fields.reason')}
                            id="reason"
                            error={errors.reason}
                            className="sm:col-span-2"
                        >
                            <textarea
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                className="rp-form-input min-h-[100px]"
                                placeholder={t('pages.accounting.costLayers.reasonPlaceholder')}
                                required
                                minLength={10}
                            />
                        </AdminFormField>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <Button type="submit" variant="brand" disabled={processing}>
                            {t('pages.accounting.costLayers.submit')}
                        </Button>
                    </div>
                </FormCard>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
