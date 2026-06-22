import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ customerGroup }) {
    const can = useCan();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: customerGroup.name,
        description: customerGroup.description ?? '',
        is_active: customerGroup.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.customer-groups.update', customerGroup.id));
    };

    const destroy = () => {
        if (confirm(t('confirm.deleteCustomerGroup', { name: customerGroup.name }))) {
            router.delete(route('admin.customer-groups.destroy', customerGroup.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.customerGroups.editTitle', { name: customerGroup.name })} />
            <PageHeader title={t('pages.customerGroups.editTitle', { name: customerGroup.name })}>
                <Link href={route('admin.customer-groups.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('pages.customerGroups.fields.name')} id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.customerGroups.fields.description')}
                        id="description"
                        error={errors.description}
                    >
                        <textarea
                            id="description"
                            value={data.description}
                            rows={3}
                            className="rp-form-input"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </AdminFormField>
                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('pages.customerGroups.fields.active')}
                    </label>
                </FormCard>
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.customerGroups.saveChanges')}
                    </button>
                    {can('customers.delete') && (
                        <button type="button" onClick={destroy} className="rp-btn-outline text-red-600">
                            {t('common.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
