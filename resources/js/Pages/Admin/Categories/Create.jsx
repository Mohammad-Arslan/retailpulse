import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export default function Create({ parentCategories }) {
    const { t } = useTranslation();
    const parentOptions = useMemo(
        () => mapToSelectOptions(parentCategories),
        [parentCategories],
    );
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        parent_id: '',
        description: '',
        sort_order: 0,
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.categories.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.categories.createTitle')} />
            <PageHeader
                title={t('pages.categories.createTitle')}
                description={t('pages.categories.createDescription')}
            >
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
                        <Select
                            id="parent_id"
                            options={parentOptions}
                            value={data.parent_id}
                            placeholder={t('pages.categories.noParent')}
                            isClearable
                            onChange={(value) => setData('parent_id', value || null)}
                        />
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
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.categories.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
