import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ suppliers = [], branches = [], defaultCurrency = 'USD' }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        supplier_id: '',
        branch_id: branches[0]?.id ? String(branches[0].id) : '',
        date: new Date().toISOString().slice(0, 10),
        amount: '',
        reason: '',
        currency_code: defaultCurrency,
    });

    const supplierOptions = useMemo(
        () => suppliers.map((s) => ({ value: String(s.id), label: s.name })),
        [suppliers],
    );
    const branchOptions = useMemo(
        () => branches.map((b) => ({ value: String(b.id), label: b.name })),
        [branches],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.accounting.debit-notes.store'));
    };

    return (
        <>
            <Head title={t('pages.accounting.debitNotes.createTitle')} />
            <PageHeader title={t('pages.accounting.debitNotes.createTitle')} description={t('pages.accounting.debitNotes.createDescription')}>
                <Link href={route('admin.accounting.debit-notes.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('common.supplier')} error={errors.supplier_id} required>
                        <Select options={supplierOptions} value={data.supplier_id} onChange={(v) => setData('supplier_id', v)} placeholder={t('pages.accounting.debitNotes.selectSupplier')} />
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
                    <AdminFormField label={t('pages.accounting.debitNotes.fields.reason')} error={errors.reason} required>
                        <textarea className="rp-form-input min-h-24" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                    </AdminFormField>
                    <Button type="submit" disabled={processing}>
                        {t('pages.accounting.debitNotes.createSubmit')}
                    </Button>
                </FormCard>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
