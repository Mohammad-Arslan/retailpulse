import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import InputError from '@/Components/InputError';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Create({ permissionGroups }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        display_name: '',
        name: '',
        description: '',
        permissions: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.roles.createTitle')} />

            <PageHeader
                title={t('pages.roles.createTitle')}
                description={t('pages.roles.createDescription')}
            >
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    {t('common.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
                    <AdminFormField
                        label={t('pages.roles.fields.displayName')}
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
                        label={t('pages.roles.fields.slug')}
                        id="name"
                        error={errors.name}
                        hint={t('pages.roles.fields.slugHint')}
                    >
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input font-mono text-sm"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="branch-manager"
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.roles.fields.description')}
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
                </FormCard>

                <div className="rp-card max-w-4xl">
                    <h3 className="rp-form-label mb-3">{t('pages.roles.permissionsTitle')}</h3>
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) => setData('permissions', permissions)}
                    />
                    <InputError message={errors.permissions} className="mt-2" />
                </div>

                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.roles.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
