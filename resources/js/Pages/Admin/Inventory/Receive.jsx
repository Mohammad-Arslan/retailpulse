import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Receive({ warehouses }) {
    const { t } = useTranslation();
    const [variant, setVariant] = useState(null);

    const { data, setData, post, processing, errors } = useForm({
        warehouse_id: warehouses[0]?.id ?? '',
        product_variant_id: '',
        batch_id: '',
        quantity: '',
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
        post(route('admin.inventory.receive.store'));
    };

    return (
        <>
            <Head title={t('pages.inventory.receiveTitle')} />
            <PageHeader
                title={t('pages.inventory.receiveTitle')}
                description={t('pages.inventory.receiveDescription')}
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
                        <select
                            id="warehouse_id"
                            value={data.warehouse_id}
                            className="rp-form-input"
                            onChange={(e) => setData('warehouse_id', e.target.value)}
                            required
                        >
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name} — {w.branch_name}
                                </option>
                            ))}
                        </select>
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
                        label={t('pages.inventory.fields.quantity')}
                        id="quantity"
                        error={errors.quantity}
                    >
                        <input
                            id="quantity"
                            type="number"
                            min="1"
                            value={data.quantity}
                            className="rp-form-input"
                            onChange={(e) => setData('quantity', e.target.value)}
                            required
                        />
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
                    {t('pages.inventory.receiveSubmit')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Receive);
