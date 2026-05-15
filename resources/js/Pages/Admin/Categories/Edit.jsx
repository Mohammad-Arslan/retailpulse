import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ category, parentCategories }) {
    const can = useCan();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: category.name,
        parent_id: category.parent_id ?? '',
        description: category.description ?? '',
        sort_order: category.sort_order ?? 0,
        is_active: category.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.categories.update', category.id));
    };

    const destroy = () => {
        if (confirm(t('confirm.deleteCategory', { name: category.name }))) {
            router.delete(route('admin.categories.destroy', category.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.categories.editTitle', { name: category.name })} />
            <PageHeader title={t('pages.categories.editTitle', { name: category.name })}>
                <Link href={route('admin.categories.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('pages.categories.fields.name')} id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.categories.fields.parent')} id="parent_id" error={errors.parent_id}>
                        <select
                            id="parent_id"
                            value={data.parent_id}
                            className="rp-form-input"
                            onChange={(e) => setData('parent_id', e.target.value || null)}
                        >
                            <option value="">{t('pages.categories.noParent')}</option>
                            {parentCategories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </AdminFormField>
                    <AdminFormField label={t('pages.categories.fields.description')} id="description" error={errors.description}>
                        <textarea
                            id="description"
                            value={data.description}
                            rows={3}
                            className="rp-form-input"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.categories.fields.sortOrder')} id="sort_order" error={errors.sort_order}>
                        <input
                            id="sort_order"
                            type="number"
                            min="0"
                            value={data.sort_order}
                            className="rp-form-input"
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                        />
                    </AdminFormField>
                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('pages.categories.fields.active')}
                    </label>
                </FormCard>
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.categories.saveChanges')}
                    </button>
                    {can('products.delete') && (
                        <button type="button" onClick={destroy} className="rp-btn-outline text-red-600">
                            {t('common.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
