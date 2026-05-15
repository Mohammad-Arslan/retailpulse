import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyLine() {
    return { product_variant_id: '', quantity: 1, variant: null };
}

function Create({ warehouses }) {
    const { t } = useTranslation();
    const [lines, setLines] = useState([emptyLine()]);

    const { data, setData, processing, errors } = useForm({
        from_warehouse_id: warehouses[0]?.id ?? '',
        to_warehouse_id: warehouses[1]?.id ?? warehouses[0]?.id ?? '',
        notes: '',
        lines: [],
    });

    const updateLine = (index, patch) => {
        setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    };

    const addLine = () => setLines((prev) => [...prev, emptyLine()]);

    const removeLine = (index) => {
        setLines((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== index)));
    };

    const submit = (e) => {
        e.preventDefault();

        const payload = lines
            .filter((line) => line.variant)
            .map((line) => ({
                product_variant_id: line.variant.id,
                batch_id: line.batch_id || null,
                quantity: Number(line.quantity),
            }));

        if (payload.length === 0) {
            return;
        }

        router.post(route('admin.stock-transfers.store'), {
            from_warehouse_id: data.from_warehouse_id,
            to_warehouse_id: data.to_warehouse_id,
            notes: data.notes,
            lines: payload,
        });
    };

    return (
        <>
            <Head title={t('pages.stockTransfers.createTitle')} />
            <PageHeader
                title={t('pages.stockTransfers.createTitle')}
                description={t('pages.stockTransfers.createDescription')}
            >
                <Link href={route('admin.stock-transfers.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-3xl space-y-5">
                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.stockTransfers.fields.fromWarehouse')}
                            error={errors.from_warehouse_id}
                        >
                            <select
                                value={data.from_warehouse_id}
                                className="rp-form-input"
                                onChange={(e) => setData('from_warehouse_id', e.target.value)}
                            >
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name} ({w.branch_name})
                                    </option>
                                ))}
                            </select>
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.stockTransfers.fields.toWarehouse')}
                            error={errors.to_warehouse_id}
                        >
                            <select
                                value={data.to_warehouse_id}
                                className="rp-form-input"
                                onChange={(e) => setData('to_warehouse_id', e.target.value)}
                            >
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name} ({w.branch_name})
                                    </option>
                                ))}
                            </select>
                        </AdminFormField>
                    </div>
                    {errors.lines && (
                        <p className="text-xs text-rose-500">{errors.lines}</p>
                    )}
                </FormCard>

                <FormCard>
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="rp-form-label">{t('pages.stockTransfers.sections.items')}</h3>
                        <button type="button" onClick={addLine} className="rp-btn-outline text-xs">
                            <Plus className="h-3.5 w-3.5" />
                            {t('pages.stockTransfers.addLine')}
                        </button>
                    </div>
                    <div className="space-y-4">
                        {lines.map((line, index) => (
                            <div
                                key={index}
                                className="flex flex-col gap-3 rounded-lg border border-rp-border p-4 sm:flex-row sm:items-end"
                            >
                                <div className="flex-1">
                                    <AdminFormField label={t('pages.inventory.fields.variant')}>
                                        <VariantSearchPicker
                                            value={line.variant}
                                            onChange={(variant) =>
                                                updateLine(index, { variant })
                                            }
                                        />
                                    </AdminFormField>
                                </div>
                                <AdminFormField
                                    label={t('pages.inventory.fields.quantity')}
                                    className="w-28"
                                >
                                    <input
                                        type="number"
                                        min="1"
                                        value={line.quantity}
                                        className="rp-form-input"
                                        onChange={(e) =>
                                            updateLine(index, {
                                                quantity: e.target.value,
                                            })
                                        }
                                    />
                                </AdminFormField>
                                <button
                                    type="button"
                                    onClick={() => removeLine(index)}
                                    className="rp-btn-outline border-rose-200 text-rose-500"
                                    aria-label={t('common.delete')}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                </FormCard>

                <AdminFormField label={t('pages.inventory.fields.notes')}>
                    <textarea
                        value={data.notes}
                        rows={2}
                        className="rp-form-input"
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </AdminFormField>

                <button type="submit" className="rp-btn-primary">
                    {t('pages.stockTransfers.createSubmit')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
