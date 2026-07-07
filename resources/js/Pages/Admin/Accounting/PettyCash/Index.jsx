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

function Index({ registers = [], recentVouchers = [], branches = [], postableAccounts = [], registerModes = [] }) {
    const { t } = useTranslation();
    const form = useForm({
        branch_id: branches[0]?.id ? String(branches[0].id) : '',
        name: '',
        coa_account_id: '',
        opening_balance: '',
        register_mode: registerModes[0] ?? 'imprest',
    });

    const registerColumns = useMemo(
        () => [
            { id: 'name', header: t('common.name'), cell: ({ row }) => row.original.name },
            { id: 'branch', header: t('common.branch'), cell: ({ row }) => row.original.branch_name ?? '—' },
            { id: 'balance', header: t('pages.accounting.pettyCash.columns.balance'), cell: ({ row }) => row.original.current_balance },
            { id: 'mode', header: t('pages.accounting.pettyCash.columns.mode'), cell: ({ row }) => row.original.register_mode },
        ],
        [t],
    );

    const voucherColumns = useMemo(
        () => [
            { id: 'number', header: t('common.number'), cell: ({ row }) => row.original.voucher_number },
            { id: 'register', header: t('pages.accounting.pettyCash.columns.register'), cell: ({ row }) => row.original.register_name ?? '—' },
            { id: 'type', header: t('common.type'), cell: ({ row }) => row.original.voucher_type },
            { id: 'amount', header: t('common.amount'), cell: ({ row }) => row.original.amount },
        ],
        [t],
    );

    const modeOptions = useMemo(
        () => registerModes.map((mode) => ({
            value: mode,
            label: t(`pages.accounting.pettyCash.modes.${mode}`, { defaultValue: mode }),
        })),
        [registerModes, t],
    );

    return (
        <>
            <Head title={t('pages.accounting.pettyCash.title')} />
            <PageHeader title={t('pages.accounting.pettyCash.title')} description={t('pages.accounting.pettyCash.description')} />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <DataTable columns={registerColumns} data={registers} emptyMessage={t('pages.accounting.pettyCash.emptyRegisters')} />
                    <DataTable columns={voucherColumns} data={recentVouchers} emptyMessage={t('pages.accounting.pettyCash.emptyVouchers')} />
                </div>
                <FormCard title={t('pages.accounting.pettyCash.createTitle')}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('admin.accounting.petty-cash.registers.store'), { onSuccess: () => form.reset() });
                        }}
                        className="space-y-4"
                    >
                        <AdminFormField label={t('common.branch')} error={form.errors.branch_id} required>
                            <Select
                                options={branches.map((b) => ({ value: String(b.id), label: b.name }))}
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.pettyCash.fields.name')} error={form.errors.name} required>
                            <input id="register_name" className="rp-form-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.pettyCash.selectGlAccount')} error={form.errors.coa_account_id} required>
                            <Select
                                options={postableAccounts.map((a) => ({ value: String(a.id), label: `${a.code} — ${a.name}` }))}
                                value={form.data.coa_account_id}
                                onChange={(value) => form.setData('coa_account_id', value ?? '')}
                                placeholder={t('pages.accounting.pettyCash.selectGlAccount')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.pettyCash.fields.openingBalance')} error={form.errors.opening_balance}>
                            <input id="opening_balance" type="number" min="0" step="0.01" className="rp-form-input" value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', e.target.value)} />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.pettyCash.columns.mode')} error={form.errors.register_mode} required>
                            <Select options={modeOptions} value={form.data.register_mode} onChange={(value) => form.setData('register_mode', value ?? '')} />
                        </AdminFormField>
                        <Button type="submit" disabled={form.processing}>{t('pages.accounting.pettyCash.createSubmit')}</Button>
                    </form>
                </FormCard>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
