import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ cheques = [], customers = [], chequeTypes = [], defaultCurrency = 'USD' }) {
    const { t } = useTranslation();
    const form = useForm({
        type: chequeTypes[0] ?? 'received',
        party_type: 'App\\Models\\Customer',
        party_id: '',
        amount: '',
        cheque_no: '',
        bank: '',
        due_date: '',
        currency_code: defaultCurrency,
    });

    const columns = useMemo(
        () => [
            { id: 'cheque_no', header: t('pages.accounting.cheques.columns.chequeNo'), cell: ({ row }) => row.original.cheque_no },
            { id: 'type', header: t('common.type'), cell: ({ row }) => row.original.type },
            { id: 'bank', header: t('pages.accounting.cheques.columns.bank'), cell: ({ row }) => row.original.bank ?? '—' },
            { id: 'amount', header: t('common.amount'), cell: ({ row }) => `${row.original.currency_code} ${row.original.amount}` },
            { id: 'due_date', header: t('pages.accounting.cheques.columns.dueDate'), cell: ({ row }) => row.original.due_date ?? '—' },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const typeOptions = useMemo(
        () => chequeTypes.map((type) => ({
            value: type,
            label: t(`pages.accounting.cheques.types.${type}`, { defaultValue: type }),
        })),
        [chequeTypes, t],
    );

    const customerOptions = useMemo(
        () => customers.map((c) => ({ value: String(c.id), label: c.name })),
        [customers],
    );

    return (
        <>
            <Head title={t('pages.accounting.cheques.title')} />
            <PageHeader title={t('pages.accounting.cheques.title')} description={t('pages.accounting.cheques.description')} />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <DataTable columns={columns} data={cheques} emptyMessage={t('pages.accounting.cheques.empty')} />
                </div>
                <FormCard title={t('pages.accounting.cheques.createTitle')}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('admin.accounting.cheques.store'), { onSuccess: () => form.reset() });
                        }}
                        className="space-y-4"
                    >
                        <AdminFormField label={t('pages.accounting.cheques.fields.type')} error={form.errors.type} required>
                            <Select options={typeOptions} value={form.data.type} onChange={(value) => form.setData('type', value ?? '')} />
                        </AdminFormField>
                        <AdminFormField label={t('common.customer')} error={form.errors.party_id} required>
                            <Select
                                options={customerOptions}
                                value={form.data.party_id}
                                onChange={(value) => form.setData('party_id', value ?? '')}
                                placeholder={t('pages.accounting.cheques.selectCustomer')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.cheques.fields.chequeNo')} error={form.errors.cheque_no} required>
                            <input id="cheque_no" className="rp-form-input" value={form.data.cheque_no} onChange={(e) => form.setData('cheque_no', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('common.amount')} error={form.errors.amount} required>
                            <input id="cheque_amount" type="number" min="0.01" step="0.01" className="rp-form-input" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.cheques.fields.bank')} error={form.errors.bank}>
                            <input id="cheque_bank" className="rp-form-input" value={form.data.bank} onChange={(e) => form.setData('bank', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.cheques.fields.dueDate')} error={form.errors.due_date}>
                            <input id="due_date" type="date" className="rp-form-input" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                        </AdminFormField>
                        <Button type="submit" disabled={form.processing}>{t('pages.accounting.cheques.createSubmit')}</Button>
                    </form>
                </FormCard>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
