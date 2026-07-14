import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Clone({ role }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: `${role.name}-copy`,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.clone.store', role.id));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.roles.cloneTitle', { name: role.display_name || role.name })} />

            <PageHeader
                title={t('pages.roles.cloneTitle', { name: role.display_name || role.name })}
                description={t('pages.roles.cloneDescription')}
            >
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    {t('common.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField
                        label={t('pages.roles.fields.newSlug')}
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
                        />
                    </AdminFormField>
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.roles.cloneSubmit')}
                    </button>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
