import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ bankAccounts = [], branches = [], postableAccounts = [], currencies = [] }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        branch_id: '',
        coa_account_id: '',
        bank_name: '',
        account_title: '',
        account_number_masked: '',
        currency_code: currencies[0]?.code ?? 'USD',
    });

    const columns = useMemo(
        () => [
            {
                id: 'bank',
                header: t('pages.accounting.bankAccounts.columns.bank'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <Landmark className="h-4 w-4 text-teal-600" />
                        <div>
                            <div className="font-semibold">{row.original.bank_name}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.account_title}</div>
                        </div>
                    </div>
                ),
            },
            { id: 'account', header: t('pages.accounting.bankAccounts.columns.glAccount'), cell: ({ row }) => row.original.coa_account ?? '—' },
            { id: 'branch', header: t('common.branch'), cell: ({ row }) => row.original.branch_name ?? '—' },
            { id: 'currency', header: t('common.currency'), cell: ({ row }) => row.original.currency_code },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.accounting.bank-accounts.store'), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title={t('pages.accounting.bankAccounts.title')} />
            <PageHeader title={t('pages.accounting.bankAccounts.title')} description={t('pages.accounting.bankAccounts.description')} />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <DataTable columns={columns} data={bankAccounts} emptyMessage={t('pages.accounting.bankAccounts.empty')} />
                </div>
                <FormCard title={t('pages.accounting.bankAccounts.createTitle')}>
                    <form onSubmit={submit} className="space-y-4">
                        <AdminFormField label={t('common.branch')} error={errors.branch_id}>
                            <Select
                                options={[{ value: '', label: t('common.allBranches') }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))]}
                                value={data.branch_id}
                                onChange={(value) => setData('branch_id', value ?? '')}
                                placeholder={t('common.branch')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.bankAccounts.selectGlAccount')} error={errors.coa_account_id} required>
                            <Select
                                options={postableAccounts.map((a) => ({ value: String(a.id), label: `${a.code} — ${a.name}` }))}
                                value={data.coa_account_id}
                                onChange={(value) => setData('coa_account_id', value ?? '')}
                                placeholder={t('pages.accounting.bankAccounts.selectGlAccount')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.bankAccounts.fields.bankName')} error={errors.bank_name} required>
                            <input
                                id="bank_name"
                                className="rp-form-input"
                                value={data.bank_name}
                                onChange={(e) => setData('bank_name', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.bankAccounts.fields.accountTitle')} error={errors.account_title} required>
                            <input
                                id="account_title"
                                className="rp-form-input"
                                value={data.account_title}
                                onChange={(e) => setData('account_title', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.bankAccounts.fields.accountNumber')} error={errors.account_number_masked}>
                            <input
                                id="account_number_masked"
                                className="rp-form-input"
                                value={data.account_number_masked}
                                onChange={(e) => setData('account_number_masked', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.currency')} error={errors.currency_code} required>
                            <Select
                                options={currencies.map((c) => ({ value: c.code, label: `${c.code} — ${c.name}` }))}
                                value={data.currency_code}
                                onChange={(value) => setData('currency_code', value ?? '')}
                            />
                        </AdminFormField>
                        <Button type="submit" disabled={processing}>{t('pages.accounting.bankAccounts.createSubmit')}</Button>
                    </form>
                </FormCard>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
