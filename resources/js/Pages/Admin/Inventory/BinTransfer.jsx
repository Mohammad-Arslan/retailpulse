import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function BinTransfer({ warehouses, binsByWarehouse }) {
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

    const { data, setData, post, processing, errors } = useForm({
        warehouse_id: warehouses[0]?.id ?? '',
        product_variant_id: '',
        batch_id: '',
        from_bin_id: '',
        to_bin_id: '',
        quantity: '',
        notes: '',
    });

    const binOptions = useMemo(() => {
        const bins = binsByWarehouse?.[data.warehouse_id] ?? binsByWarehouse?.[String(data.warehouse_id)] ?? [];

        return bins.map((bin) => ({
            value: String(bin.id),
            label: bin.label ?? bin.bin_code,
        }));
    }, [binsByWarehouse, data.warehouse_id]);

    useEffect(() => {
        if (variant) {
            setData('product_variant_id', variant.id);
        }
    }, [variant, setData]);

    useEffect(() => {
        setData('from_bin_id', '');
        setData('to_bin_id', '');
    }, [data.warehouse_id, setData]);

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.inventory.bin-transfer'));
    };

    return (
        <>
            <Head title={t('pages.binTransfer.title')} />
            <PageHeader
                title={t('pages.binTransfer.title')}
                description={t('pages.binTransfer.description')}
            >
                <Link href={route('admin.inventory.bin-report')} className="rp-btn-outline">
                    {t('nav.binStock')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('pages.inventory.columns.warehouse')} error={errors.warehouse_id}>
                        <Select
                            value={String(data.warehouse_id)}
                            onChange={(value) => setData('warehouse_id', value)}
                            options={warehouseOptions}
                        />
                    </AdminFormField>

                    <AdminFormField label={t('pages.inventory.columns.product')} error={errors.product_variant_id}>
                        <VariantSearchPicker value={variant} onChange={setVariant} />
                    </AdminFormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.binTransfer.fromBin')} error={errors.from_bin_id}>
                            <Select
                                value={data.from_bin_id ? String(data.from_bin_id) : ''}
                                onChange={(value) => setData('from_bin_id', value)}
                                options={[{ value: '', label: t('pages.binTransfer.selectBin') }, ...binOptions]}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.binTransfer.toBin')} error={errors.to_bin_id}>
                            <Select
                                value={data.to_bin_id ? String(data.to_bin_id) : ''}
                                onChange={(value) => setData('to_bin_id', value)}
                                options={[{ value: '', label: t('pages.binTransfer.selectBin') }, ...binOptions]}
                            />
                        </AdminFormField>
                    </div>

                    <AdminFormField label={t('pages.inventory.columns.onHand')} error={errors.quantity}>
                        <input
                            type="number"
                            min="1"
                            className="rp-form-input w-full"
                            value={data.quantity}
                            onChange={(e) => setData('quantity', e.target.value)}
                            required
                        />
                    </AdminFormField>

                    <AdminFormField label={t('pages.inventory.fields.notes')} error={errors.notes}>
                        <textarea
                            className="rp-form-input w-full"
                            rows={2}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </AdminFormField>
                </FormCard>

                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.binTransfer.submit')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(BinTransfer);
