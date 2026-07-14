import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Create() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        display_name: '',
        name: '',
        group: '',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.permissions.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.permissions.createTitle')} />

            <PageHeader
                title={t('pages.permissions.createTitle')}
                description={t('pages.permissions.createDescription')}
            >
                <Link href={route('admin.permissions.index')} className="rp-btn-outline">
                    {t('common.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField
                        label={t('pages.permissions.fields.displayName')}
                        id="display_name"
                        error={errors.display_name}
                    >
                        <input
                            id="display_name"
                            value={data.display_name}
                            className="rp-form-input"
                            onChange={(e) => setData('display_name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.permissions.fields.slug')}
                        id="name"
                        error={errors.name}
                        hint={t('pages.permissions.fields.slugHint')}
                    >
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input font-mono text-sm"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="users.create"
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.permissions.fields.group')}
                        id="group"
                        error={errors.group}
                    >
                        <input
                            id="group"
                            value={data.group}
                            className="rp-form-input"
                            onChange={(e) => setData('group', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.permissions.fields.description')}
                        id="description"
                        error={errors.description}
                    >
                        <input
                            id="description"
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </AdminFormField>
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.permissions.createSubmit')}
                    </button>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
