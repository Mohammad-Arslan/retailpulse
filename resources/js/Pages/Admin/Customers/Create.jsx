import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export default function Create({ loyaltyTiers = [], customerGroups = [] }) {
    const { t } = useTranslation();
    const tierOptions = useMemo(() => mapToSelectOptions(loyaltyTiers), [loyaltyTiers]);
    const groupOptions = useMemo(() => mapToSelectOptions(customerGroups), [customerGroups]);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        phone: '',
        email: '',
        ntn: '',
        cnic: '',
        credit_limit: '',
        loyalty_tier_id: '',
        customer_group_id: '',
        notes: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.customers.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.customers.createTitle')} />
            <PageHeader
                title={t('pages.customers.createTitle')}
                description={t('pages.customers.createDescription')}
            >
                <Link href={route('admin.customers.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('pages.customers.fields.name')} id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.customers.fields.phone')} id="phone" error={errors.phone}>
                            <input
                                id="phone"
                                value={data.phone}
                                className="rp-form-input"
                                onChange={(e) => setData('phone', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.customers.fields.email')} id="email" error={errors.email}>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                className="rp-form-input"
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.customers.fields.ntn')} id="ntn" error={errors.ntn}>
                            <input
                                id="ntn"
                                value={data.ntn}
                                className="rp-form-input"
                                onChange={(e) => setData('ntn', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.customers.fields.cnic')} id="cnic" error={errors.cnic}>
                            <input
                                id="cnic"
                                value={data.cnic}
                                className="rp-form-input"
                                onChange={(e) => setData('cnic', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                    <AdminFormField
                        label={t('pages.customers.fields.creditLimit')}
                        id="credit_limit"
                        error={errors.credit_limit}
                    >
                        <input
                            id="credit_limit"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.credit_limit}
                            className="rp-form-input"
                            onChange={(e) => setData('credit_limit', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.customers.fields.loyaltyTier')}
                        id="loyalty_tier_id"
                        error={errors.loyalty_tier_id}
                    >
                        <Select
                            id="loyalty_tier_id"
                            options={tierOptions}
                            value={data.loyalty_tier_id}
                            placeholder={t('pages.customers.noTier')}
                            isClearable
                            onChange={(value) => setData('loyalty_tier_id', value || null)}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.customers.fields.customerGroup')}
                        id="customer_group_id"
                        error={errors.customer_group_id}
                    >
                        <Select
                            id="customer_group_id"
                            options={groupOptions}
                            value={data.customer_group_id}
                            placeholder={t('pages.customers.noGroup')}
                            isClearable
                            onChange={(value) => setData('customer_group_id', value || null)}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.customers.fields.notes')} id="notes" error={errors.notes}>
                        <textarea
                            id="notes"
                            value={data.notes}
                            rows={3}
                            className="rp-form-input"
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </AdminFormField>
                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('pages.customers.fields.active')}
                    </label>
                </FormCard>
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.customers.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
