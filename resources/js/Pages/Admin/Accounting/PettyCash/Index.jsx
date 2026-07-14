import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({
    registers = [],
    recentVouchers = [],
    branches = [],
    postableAccounts = [],
    registerModes = [],
    voucherTypes = [],
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [rejectingId, setRejectingId] = useState(null);
    const [rejectReason, setRejectReason] = useState('');

    const registerForm = useForm({
        branch_id: branches[0]?.id ? String(branches[0].id) : '',
        name: '',
        coa_account_id: '',
        opening_balance: '',
        register_mode: registerModes[0] ?? 'imprest',
    });

    const voucherForm = useForm({
        register_id: registers[0]?.id ? String(registers[0].id) : '',
        voucher_type: voucherTypes[0] ?? 'disbursement',
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        expense_account_id: '',
        description: '',
        adjustment_delta: '',
    });

    const registerColumns = useMemo(
        () => [
            { id: 'name', header: t('common.name'), cell: ({ row }) => row.original.name },
            { id: 'branch', header: t('common.branch'), cell: ({ row }) => row.original.branch_name ?? '—' },
            {
                id: 'balance',
                header: t('pages.accounting.pettyCash.columns.balance'),
                cell: ({ row }) => row.original.current_balance,
            },
            {
                id: 'mode',
                header: t('pages.accounting.pettyCash.columns.mode'),
                cell: ({ row }) =>
                    t(`pages.accounting.pettyCash.modes.${row.original.register_mode}`, {
                        defaultValue: row.original.register_mode,
                    }),
            },
        ],
        [t],
    );

    const voucherColumns = useMemo(
        () => [
            { id: 'number', header: t('common.number'), cell: ({ row }) => row.original.voucher_number },
            {
                id: 'register',
                header: t('pages.accounting.pettyCash.columns.register'),
                cell: ({ row }) => row.original.register_name ?? '—',
            },
            {
                id: 'type',
                header: t('common.type'),
                cell: ({ row }) =>
                    t(`pages.accounting.pettyCash.voucherTypes.${row.original.voucher_type}`, {
                        defaultValue: row.original.voucher_type,
                    }),
            },
            { id: 'amount', header: t('common.amount'), cell: ({ row }) => row.original.amount },
            {
                id: 'approval',
                header: t('pages.accounting.pettyCash.columns.approval'),
                cell: ({ row }) =>
                    t(`pages.accounting.pettyCash.approvalStatuses.${row.original.approval_status}`, {
                        defaultValue: row.original.approval_status,
                    }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) => {
                    const voucher = row.original;
                    if (voucher.approval_status !== 'pending' || !can('accounting.approve-petty-cash')) {
                        return '—';
                    }

                    if (rejectingId === voucher.id) {
                        return (
                            <div className="flex min-w-[14rem] flex-col gap-2">
                                <input
                                    className="rp-form-input"
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    placeholder={t('pages.accounting.pettyCash.rejectReasonPlaceholder')}
                                />
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => {
                                            router.post(
                                                route('admin.accounting.petty-cash.vouchers.reject', voucher.id),
                                                { reason: rejectReason },
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        setRejectingId(null);
                                                        setRejectReason('');
                                                    },
                                                },
                                            );
                                        }}
                                    >
                                        {t('pages.accounting.pettyCash.rejectSubmit')}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setRejectingId(null);
                                            setRejectReason('');
                                        }}
                                    >
                                        {t('common.back')}
                                    </Button>
                                </div>
                            </div>
                        );
                    }

                    return (
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="brand"
                                onClick={() =>
                                    router.post(
                                        route('admin.accounting.petty-cash.vouchers.approve', voucher.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('pages.accounting.pettyCash.approve')}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    setRejectingId(voucher.id);
                                    setRejectReason('');
                                }}
                            >
                                {t('pages.accounting.pettyCash.reject')}
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [can, rejectReason, rejectingId, t],
    );

    const modeOptions = useMemo(
        () =>
            registerModes.map((mode) => ({
                value: mode,
                label: t(`pages.accounting.pettyCash.modes.${mode}`, { defaultValue: mode }),
            })),
        [registerModes, t],
    );

    const voucherTypeOptions = useMemo(
        () =>
            voucherTypes.map((type) => ({
                value: type,
                label: t(`pages.accounting.pettyCash.voucherTypes.${type}`, { defaultValue: type }),
            })),
        [voucherTypes, t],
    );

    return (
        <>
            <Head title={t('pages.accounting.pettyCash.title')} />
            <PageHeader
                title={t('pages.accounting.pettyCash.title')}
                description={t('pages.accounting.pettyCash.description')}
            />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <DataTable
                        columns={registerColumns}
                        data={registers}
                        emptyMessage={t('pages.accounting.pettyCash.emptyRegisters')}
                    />
                    <DataTable
                        columns={voucherColumns}
                        data={recentVouchers}
                        emptyMessage={t('pages.accounting.pettyCash.emptyVouchers')}
                    />
                </div>
                <div className="space-y-6">
                    {can('accounting.manage-petty-cash') && (
                        <FormCard title={t('pages.accounting.pettyCash.createTitle')}>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    registerForm.post(route('admin.accounting.petty-cash.registers.store'), {
                                        onSuccess: () => registerForm.reset(),
                                    });
                                }}
                                className="space-y-4"
                            >
                                <AdminFormField label={t('common.branch')} error={registerForm.errors.branch_id} required>
                                    <Select
                                        options={branches.map((b) => ({ value: String(b.id), label: b.name }))}
                                        value={registerForm.data.branch_id}
                                        onChange={(value) => registerForm.setData('branch_id', value ?? '')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.fields.name')}
                                    error={registerForm.errors.name}
                                    required
                                >
                                    <input
                                        id="register_name"
                                        className="rp-form-input"
                                        value={registerForm.data.name}
                                        onChange={(e) => registerForm.setData('name', e.target.value)}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.selectGlAccount')}
                                    error={registerForm.errors.coa_account_id}
                                    required
                                >
                                    <Select
                                        options={postableAccounts.map((a) => ({
                                            value: String(a.id),
                                            label: `${a.code} — ${a.name}`,
                                        }))}
                                        value={registerForm.data.coa_account_id}
                                        onChange={(value) => registerForm.setData('coa_account_id', value ?? '')}
                                        placeholder={t('pages.accounting.pettyCash.selectGlAccount')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.fields.openingBalance')}
                                    error={registerForm.errors.opening_balance}
                                >
                                    <input
                                        id="opening_balance"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className="rp-form-input"
                                        value={registerForm.data.opening_balance}
                                        onChange={(e) => registerForm.setData('opening_balance', e.target.value)}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.columns.mode')}
                                    error={registerForm.errors.register_mode}
                                    required
                                >
                                    <Select
                                        options={modeOptions}
                                        value={registerForm.data.register_mode}
                                        onChange={(value) => registerForm.setData('register_mode', value ?? '')}
                                    />
                                </AdminFormField>
                                <Button type="submit" disabled={registerForm.processing}>
                                    {t('pages.accounting.pettyCash.createSubmit')}
                                </Button>
                            </form>
                        </FormCard>
                    )}

                    {can('accounting.manage-petty-cash') && registers.length > 0 && (
                        <FormCard title={t('pages.accounting.pettyCash.createVoucherTitle')}>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    if (!voucherForm.data.register_id) {
                                        return;
                                    }
                                    voucherForm.transform((data) => ({
                                        voucher_type: data.voucher_type,
                                        amount: data.amount,
                                        date: data.date || null,
                                        expense_account_id: data.expense_account_id || null,
                                        description: data.description || null,
                                        adjustment_delta:
                                            data.voucher_type === 'adjustment' ? data.adjustment_delta : null,
                                    }));
                                    voucherForm.post(
                                        route(
                                            'admin.accounting.petty-cash.vouchers.store',
                                            voucherForm.data.register_id,
                                        ),
                                        { onSuccess: () => voucherForm.reset('amount', 'description', 'adjustment_delta') },
                                    );
                                }}
                                className="space-y-4"
                            >
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.columns.register')}
                                    error={voucherForm.errors.register_id}
                                    required
                                >
                                    <Select
                                        options={registers.map((r) => ({ value: String(r.id), label: r.name }))}
                                        value={voucherForm.data.register_id}
                                        onChange={(value) => voucherForm.setData('register_id', value ?? '')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.fields.voucherType')}
                                    error={voucherForm.errors.voucher_type}
                                    required
                                >
                                    <Select
                                        options={voucherTypeOptions}
                                        value={voucherForm.data.voucher_type}
                                        onChange={(value) => voucherForm.setData('voucher_type', value ?? '')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('common.amount')}
                                    error={voucherForm.errors.amount}
                                    required
                                >
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        className="rp-form-input"
                                        value={voucherForm.data.amount}
                                        onChange={(e) => voucherForm.setData('amount', e.target.value)}
                                    />
                                </AdminFormField>
                                {voucherForm.data.voucher_type === 'adjustment' && (
                                    <AdminFormField
                                        label={t('pages.accounting.pettyCash.fields.adjustmentDelta')}
                                        error={voucherForm.errors.adjustment_delta}
                                        required
                                    >
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="rp-form-input"
                                            value={voucherForm.data.adjustment_delta}
                                            onChange={(e) => voucherForm.setData('adjustment_delta', e.target.value)}
                                        />
                                    </AdminFormField>
                                )}
                                <AdminFormField label={t('common.date')} error={voucherForm.errors.date}>
                                    <input
                                        type="date"
                                        className="rp-form-input"
                                        value={voucherForm.data.date}
                                        onChange={(e) => voucherForm.setData('date', e.target.value)}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.fields.expenseAccount')}
                                    error={voucherForm.errors.expense_account_id}
                                >
                                    <Select
                                        options={[
                                            { value: '', label: '—' },
                                            ...postableAccounts.map((a) => ({
                                                value: String(a.id),
                                                label: `${a.code} — ${a.name}`,
                                            })),
                                        ]}
                                        value={voucherForm.data.expense_account_id}
                                        onChange={(value) => voucherForm.setData('expense_account_id', value ?? '')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.pettyCash.fields.description')}
                                    error={voucherForm.errors.description}
                                >
                                    <textarea
                                        className="rp-form-input"
                                        rows={2}
                                        value={voucherForm.data.description}
                                        onChange={(e) => voucherForm.setData('description', e.target.value)}
                                    />
                                </AdminFormField>
                                <Button type="submit" disabled={voucherForm.processing}>
                                    {t('pages.accounting.pettyCash.createVoucherSubmit')}
                                </Button>
                            </form>
                        </FormCard>
                    )}
                </div>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
