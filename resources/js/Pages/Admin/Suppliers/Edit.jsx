import SupplierFormFields from '@/Components/admin/SupplierFormFields';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function mapContacts(contacts = []) {
    return contacts.map((c) => ({
        name: c.name ?? '',
        email: c.email ?? '',
        phone: c.phone ?? '',
        role: c.role ?? '',
        is_primary: c.is_primary ?? false,
    }));
}

function mapAddresses(addresses = []) {
    return addresses.map((a) => ({
        label: a.label ?? '',
        address_line_1: a.address_line_1 ?? '',
        address_line_2: a.address_line_2 ?? '',
        city: a.city ?? '',
        state: a.state ?? '',
        postal_code: a.postal_code ?? '',
        country_code: a.country_code ?? '',
        is_default: a.is_default ?? false,
    }));
}

function Edit({ supplier, currencies = [] }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        code: supplier?.code ?? '',
        name: supplier?.name ?? '',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        tax_registration_no: supplier?.tax_registration_no ?? '',
        payment_terms_days: supplier?.payment_terms_days ?? '',
        credit_terms_days: supplier?.credit_terms_days ?? '',
        currency_code: supplier?.currency_code ?? 'USD',
        notes: supplier?.notes ?? '',
        is_active: supplier?.is_active ?? true,
        contacts: mapContacts(supplier?.contacts),
        addresses: mapAddresses(supplier?.addresses),
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.suppliers.update', supplier.id));
    };

    return (
        <>
            <Head title={t('pages.suppliers.editTitle', { name: supplier?.name })} />
            <PageHeader
                title={t('pages.suppliers.editTitle', { name: supplier?.name })}
                description={supplier?.code}
            >
                <Link href={route('admin.suppliers.show', supplier.id)} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="w-full space-y-5">
                <SupplierFormFields data={data} setData={setData} errors={errors} currencies={currencies} />
                <div className="flex flex-wrap gap-2 border-t border-rp-border pt-5">
                    <Button type="submit" disabled={processing}>
                        {t('pages.suppliers.updateSubmit')}
                    </Button>
                    <Link href={route('admin.suppliers.show', supplier.id)} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Edit);
