import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Adjust({ warehouses, reasons }) {
    const { t } = useTranslation();
    const [variant, setVariant] = useState(null);
    const warehouseOptions = useMemo(
        () =>
            warehouses.map((warehouse) => ({
                value: String(warehouse.id),
                label: `${warehouse.name} — ${warehouse.branch_name}`,
            })),
        [warehouses],
    );
    const reasonOptions = useMemo(
        () =>
            reasons.map((reason) => ({
                value: reason.value,
                label: reason.label,
            })),
        [reasons],
    );

    const { data, setData, post, processing, errors } = useForm({
        warehouse_id: warehouses[0]?.id ?? '',
        product_variant_id: '',
        batch_id: '',
        quantity: '',
        reason: 'adjustment',
        notes: '',
    });

    useEffect(() => {
        if (variant) {
            setData('product_variant_id', variant.id);
        }
    }, [variant, setData]);

    const submit = (e) => {
        e.preventDefault();
        if (!variant) {
            return;
        }
        post(route('admin.inventory.adjust.store'));
    };

    return (
        <>
            <Head title={t('pages.inventory.adjustTitle')} />
            <PageHeader
                title={t('pages.inventory.adjustTitle')}
                description={t('pages.inventory.adjustDescription')}
            >
                <Link href={route('admin.inventory.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-xl space-y-5">
                <FormCard>
                    <AdminFormField
                        label={t('pages.inventory.fields.warehouse')}
                        id="warehouse_id"
                        error={errors.warehouse_id}
                    >
                        <Select
                            id="warehouse_id"
                            options={warehouseOptions}
                            value={data.warehouse_id}
                            onChange={(value) => setData('warehouse_id', value)}
                            required
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.inventory.fields.variant')}
                        error={errors.product_variant_id}
                    >
                        <VariantSearchPicker
                            value={variant}
                            onChange={setVariant}
                            error={errors.product_variant_id}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.inventory.fields.reason')}
                        id="reason"
                        error={errors.reason}
                    >
                        <Select
                            id="reason"
                            options={reasonOptions}
                            value={data.reason}
                            onChange={(value) => setData('reason', value)}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.inventory.fields.quantitySigned')}
                        id="quantity"
                        error={errors.quantity}
                    >
                        <input
                            id="quantity"
                            type="number"
                            value={data.quantity}
                            className="rp-form-input"
                            onChange={(e) => setData('quantity', e.target.value)}
                            required
                        />
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('pages.inventory.quantitySignedHint')}
                        </p>
                    </AdminFormField>

                    <AdminFormField label={t('pages.inventory.fields.notes')} id="notes">
                        <textarea
                            id="notes"
                            value={data.notes}
                            rows={2}
                            className="rp-form-input"
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </AdminFormField>
                </FormCard>

                <button type="submit" disabled={processing || !variant} className="rp-btn-primary">
                    {t('pages.inventory.adjustSubmit')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Adjust);
