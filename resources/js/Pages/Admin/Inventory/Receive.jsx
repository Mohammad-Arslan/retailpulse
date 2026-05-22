import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Receive({ warehouses }) {
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
        quantity: '',
        serial_numbers: [],
        notes: '',
    });

    const trackSerials = variant?.track_serials ?? false;

    const serialCount = useMemo(
        () => data.serial_numbers.filter((s) => String(s).trim() !== '').length,
        [data.serial_numbers],
    );

    useEffect(() => {
        if (variant) {
            setData('product_variant_id', variant.id);
        }
    }, [variant, setData]);

    useEffect(() => {
        if (!trackSerials) {
            setData('serial_numbers', []);

            return;
        }

        const qty = parseInt(data.quantity, 10) || 0;

        if (qty <= 0) {
            setData('serial_numbers', []);

            return;
        }

        setData((prev) => {
            const next = [...(prev.serial_numbers ?? [])];

            while (next.length < qty) {
                next.push('');
            }

            return { ...prev, serial_numbers: next.slice(0, qty) };
        });
    }, [trackSerials, data.quantity, setData]);

    const updateSerial = (index, value) => {
        const next = [...data.serial_numbers];
        next[index] = value;
        setData('serial_numbers', next);
    };

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

                    {trackSerials && data.serial_numbers.length > 0 && (
                        <AdminFormField
                            label={t('pages.inventory.fields.serialNumbers')}
                            error={errors.serial_numbers}
                        >
                            <p className="mb-2 text-xs text-rp-text-muted">
                                {t('pages.inventory.serialNumbersHint', { count: serialCount })}
                            </p>
                            <div className="max-h-56 space-y-2 overflow-y-auto">
                                {data.serial_numbers.map((serial, index) => (
                                    <input
                                        key={index}
                                        type="text"
                                        value={serial}
                                        placeholder={t('pages.inventory.serialPlaceholder', {
                                            n: index + 1,
                                        })}
                                        className="rp-form-input font-mono text-sm"
                                        onChange={(e) => updateSerial(index, e.target.value)}
                                        required
                                    />
                                ))}
                            </div>
                        </AdminFormField>
                    )}

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
