import UnitFormFields from '@/Components/admin/UnitFormFields';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Create() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        abbreviation: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.units.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.units.createTitle')} />
            <PageHeader
                title={t('pages.units.createTitle')}
                description={t('pages.units.createDescription')}
            >
                <Link href={route('admin.units.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <UnitFormFields data={data} setData={setData} errors={errors} />
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.units.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
