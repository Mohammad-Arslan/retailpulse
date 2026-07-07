import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ taxTypes = [], taxDirections = [], calculationMethods = [] }) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        rate: '',
        tax_direction: taxDirections[0] ?? 'sales',
        calculation_method: calculationMethods[0] ?? 'exclusive',
        effective_from: new Date().toISOString().slice(0, 10),
    });

    const columns = useMemo(
        () => [
            { id: 'code', header: t('common.code'), cell: ({ row }) => row.original.code },
            { id: 'name', header: t('common.name'), cell: ({ row }) => row.original.name },
            { id: 'rate', header: t('pages.accounting.taxTypes.columns.rate'), cell: ({ row }) => `${row.original.rate}%` },
            { id: 'direction', header: t('pages.accounting.taxTypes.columns.direction'), cell: ({ row }) => row.original.tax_direction },
            { id: 'method', header: t('pages.accounting.taxTypes.columns.method'), cell: ({ row }) => row.original.calculation_method },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const directionOptions = useMemo(
        () => taxDirections.map((value) => ({
            value,
            label: t(`pages.accounting.taxTypes.directions.${value}`, { defaultValue: value }),
        })),
        [taxDirections, t],
    );

    const methodOptions = useMemo(
        () => calculationMethods.map((value) => ({
            value,
            label: t(`pages.accounting.taxTypes.methods.${value}`, { defaultValue: value }),
        })),
        [calculationMethods, t],
    );

    return (
        <>
            <Head title={t('pages.accounting.taxTypes.title')} />
            <PageHeader title={t('pages.accounting.taxTypes.title')} description={t('pages.accounting.taxTypes.description')} />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <DataTable columns={columns} data={taxTypes} emptyMessage={t('pages.accounting.taxTypes.empty')} />
                </div>
                <FormCard title={t('pages.accounting.taxTypes.createTitle')}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('admin.accounting.tax-types.store'), { onSuccess: () => form.reset() });
                        }}
                        className="space-y-4"
                    >
                        <AdminFormField label={t('common.name')} error={form.errors.name} required>
                            <input id="tax_name" className="rp-form-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.taxTypes.fields.code')} error={form.errors.code} required>
                            <input id="tax_code" className="rp-form-input" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.taxTypes.fields.rate')} error={form.errors.rate} required>
                            <input id="tax_rate" type="number" min="0" step="0.0001" className="rp-form-input" value={form.data.rate} onChange={(e) => form.setData('rate', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.taxTypes.fields.direction')} error={form.errors.tax_direction} required>
                            <Select options={directionOptions} value={form.data.tax_direction} onChange={(value) => form.setData('tax_direction', value ?? '')} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.taxTypes.fields.method')} error={form.errors.calculation_method} required>
                            <Select options={methodOptions} value={form.data.calculation_method} onChange={(value) => form.setData('calculation_method', value ?? '')} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.taxTypes.fields.effectiveFrom')} error={form.errors.effective_from} required>
                            <input id="effective_from" type="date" className="rp-form-input" value={form.data.effective_from} onChange={(e) => form.setData('effective_from', e.target.value)} />
                        </AdminFormField>
                        <Button type="submit" disabled={form.processing}>{t('pages.accounting.taxTypes.createSubmit')}</Button>
                    </form>
                </FormCard>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
