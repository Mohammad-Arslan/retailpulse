import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ customers = [], branches = [], taxTypes = [], defaultCurrency = 'USD' }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        customer_id: '',
        branch_id: branches[0]?.id ? String(branches[0].id) : '',
        date: new Date().toISOString().slice(0, 10),
        amount: '',
        tax_type_id: '',
        reason: '',
        currency_code: defaultCurrency,
    });

    const customerOptions = useMemo(
        () => customers.map((c) => ({ value: String(c.id), label: c.name })),
        [customers],
    );
    const branchOptions = useMemo(
        () => branches.map((b) => ({ value: String(b.id), label: b.name })),
        [branches],
    );
    const taxOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.creditNotes.noTax') },
            ...taxTypes.map((tax) => ({ value: String(tax.id), label: `${tax.name} (${tax.rate}%)` })),
        ],
        [taxTypes, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.accounting.credit-notes.store'));
    };

    return (
        <>
            <Head title={t('pages.accounting.creditNotes.createTitle')} />
            <PageHeader title={t('pages.accounting.creditNotes.createTitle')} description={t('pages.accounting.creditNotes.createDescription')}>
                <Link href={route('admin.accounting.credit-notes.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('common.customer')} error={errors.customer_id} required>
                        <Select options={customerOptions} value={data.customer_id} onChange={(v) => setData('customer_id', v)} placeholder={t('pages.accounting.creditNotes.selectCustomer')} />
                    </AdminFormField>
                    <AdminFormField label={t('common.branch')} error={errors.branch_id} required>
                        <Select options={branchOptions} value={data.branch_id} onChange={(v) => setData('branch_id', v)} />
                    </AdminFormField>
                    <AdminFormField label={t('common.date')} error={errors.date} required>
                        <input type="date" className="rp-form-input" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                    </AdminFormField>
                    <AdminFormField label={t('common.amount')} error={errors.amount} required>
                        <input type="number" step="0.01" min="0" className="rp-form-input" value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                    </AdminFormField>
                    <AdminFormField label={t('pages.accounting.creditNotes.fields.taxType')} error={errors.tax_type_id}>
                        <Select options={taxOptions} value={data.tax_type_id} onChange={(v) => setData('tax_type_id', v)} />
                    </AdminFormField>
                    <AdminFormField label={t('pages.accounting.creditNotes.fields.reason')} error={errors.reason} required>
                        <textarea className="rp-form-input min-h-24" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                    </AdminFormField>
                    <Button type="submit" disabled={processing}>
                        {t('pages.accounting.creditNotes.createSubmit')}
                    </Button>
                </FormCard>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
