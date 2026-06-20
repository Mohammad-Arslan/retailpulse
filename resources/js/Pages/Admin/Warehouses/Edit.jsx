import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ warehouse }) {
    const can = useCan();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: warehouse.name,
        is_default: warehouse.is_default,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.warehouses.update', warehouse.id));
    };

    const deactivate = () => {
        if (confirm(t('confirm.deactivateWarehouse', { name: warehouse.name }))) {
            router.patch(route('admin.warehouses.deactivate', warehouse.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.warehouses.editTitle', { name: warehouse.name })} />
            <PageHeader title={t('pages.warehouses.editTitle', { name: warehouse.name })}>
                <div className="flex gap-2">
                    {can('inventory.manage-bins') && warehouse.is_active && (
                        <Link
                            href={route('admin.warehouses.bins.index', warehouse.id)}
                            className="rp-btn-outline"
                        >
                            {t('pages.warehouses.manageBins')}
                        </Link>
                    )}
                    <Link href={route('admin.warehouses.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </PageHeader>
            <form onSubmit={submit} className="w-full space-y-5">
                <FormCard className="max-w-none w-full">
                    <AdminFormField label={t('pages.warehouses.fields.branch')} id="branch">
                        <input
                            id="branch"
                            value={`${warehouse.branch_name} (${warehouse.branch_code})`}
                            className="rp-form-input w-full bg-rp-surface-inset"
                            disabled
                            readOnly
                        />
                    </AdminFormField>

                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div className="xl:col-span-6">
                            <AdminFormField
                                label={t('pages.warehouses.fields.name')}
                                id="name"
                                error={errors.name}
                            >
                                <input
                                    id="name"
                                    value={data.name}
                                    className="rp-form-input w-full"
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    disabled={!warehouse.is_active}
                                />
                            </AdminFormField>
                        </div>
                        <div className="xl:col-span-6">
                            <AdminFormField label={t('pages.warehouses.fields.code')} id="code">
                                <input
                                    id="code"
                                    value={warehouse.code}
                                    className="rp-form-input w-full bg-rp-surface-inset font-mono uppercase"
                                    disabled
                                    readOnly
                                />
                                <p className="mt-1 text-xs text-rp-text-muted">
                                    {t('pages.warehouses.codeImmutableHint')}
                                </p>
                            </AdminFormField>
                        </div>
                    </div>

                    {warehouse.is_active && (
                        <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                            <input
                                type="checkbox"
                                checked={data.is_default}
                                onChange={(e) => setData('is_default', e.target.checked)}
                            />
                            {t('pages.warehouses.fields.default')}
                        </label>
                    )}
                    {!warehouse.is_active && (
                        <p className="text-sm text-rp-text-muted">{t('pages.warehouses.inactiveNotice')}</p>
                    )}
                </FormCard>
                {warehouse.is_active && (
                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rp-btn-primary">
                            {t('pages.warehouses.saveChanges')}
                        </button>
                        {can('warehouses.deactivate') && (
                            <button type="button" onClick={deactivate} className="rp-btn-outline text-red-600">
                                {t('pages.warehouses.deactivate')}
                            </button>
                        )}
                    </div>
                )}
            </form>
        </AdminLayout>
    );
}
