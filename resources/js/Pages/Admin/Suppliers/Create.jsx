import SupplierFormFields from '@/Components/admin/SupplierFormFields';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Create({ currencies = [], defaultCurrency = 'USD' }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        email: '',
        phone: '',
        tax_registration_no: '',
        payment_terms_days: '',
        credit_terms_days: '',
        currency_code: defaultCurrency,
        notes: '',
        is_active: true,
        contacts: [],
        addresses: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.suppliers.store'));
    };

    return (
        <>
            <Head title={t('pages.suppliers.createTitle')} />
            <PageHeader title={t('pages.suppliers.createTitle')} description={t('pages.suppliers.createDescription')}>
                <Link href={route('admin.suppliers.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="w-full space-y-5">
                <SupplierFormFields data={data} setData={setData} errors={errors} currencies={currencies} />
                <div className="flex flex-wrap gap-2 border-t border-rp-border pt-5">
                    <Button type="submit" disabled={processing}>
                        {t('pages.suppliers.createSubmit')}
                    </Button>
                    <Link href={route('admin.suppliers.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
