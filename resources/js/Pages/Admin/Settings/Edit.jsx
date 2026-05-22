import SettingsFields from '@/Components/admin/SettingsFields';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ group, fields, values, can_update: canUpdate }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        values: { ...values },
    });

    const setValue = (key, value) => {
        setData('values', { ...data.values, [key]: value });
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', group.key));
    };

    return (
        <AdminLayout>
            <Head title={group.label} />

            <PageHeader title={group.label} description={group.description}>
                <Link href={route('admin.settings.index')} className="rp-btn-outline">
                    {t('pages.settings.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <SettingsFields
                        fields={fields}
                        values={data.values}
                        setValue={setValue}
                        errors={errors}
                        disabled={!canUpdate || processing}
                    />
                </FormCard>

                {canUpdate ? (
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="rp-btn-primary"
                            disabled={processing}
                        >
                            {processing
                                ? t('pages.settings.saving')
                                : t('pages.settings.save')}
                        </button>
                    </div>
                ) : (
                    <p className="text-sm text-amber-600 dark:text-amber-400">
                        {t('pages.settings.readOnlyHint')}
                    </p>
                )}
            </form>
        </AdminLayout>
    );
}
