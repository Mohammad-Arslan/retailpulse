import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        code: '',
        name: '',
        is_paid: true,
        affects_payroll: false,
        payroll_deduction_component_code: '',
        payroll_encashment_component_code: '',
        allow_leave_claim: true,
        allow_cash_claim: false,
        payroll_toil_payout_component_code: '',
        status: 'active',
    };
}

function Index({ types, filters }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveTypes.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.leaveTypes.statuses.${status}`),
            })),
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () =>
            ['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.leaveTypes.statuses.${status}`),
            })),
        [t],
    );

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm());
        setModalOpen(true);
    };

    const openEdit = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            code: row.code ?? '',
            name: row.name ?? '',
            is_paid: !!row.is_paid,
            affects_payroll: !!row.affects_payroll,
            payroll_deduction_component_code: row.payroll_deduction_component_code ?? '',
            payroll_encashment_component_code: row.payroll_encashment_component_code ?? '',
            allow_leave_claim: row.allow_leave_claim !== false,
            allow_cash_claim: !!row.allow_cash_claim,
            payroll_toil_payout_component_code: row.payroll_toil_payout_component_code ?? '',
            status: row.status ?? 'active',
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.put(route('admin.leave.types.update', editing.id), options);
        } else {
            form.post(route('admin.leave.types.store'), options);
        }
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.leave.types.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.leaveTypes.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <CalendarDays className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name ?? '—'}
                            </div>
                            <div className="font-mono text-xs text-rp-text-muted">
                                {row.original.code ?? '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'isPaid',
                header: t('pages.leaveTypes.columns.isPaid'),
                cell: ({ row }) =>
                    row.original.is_paid ? t('pages.leaveTypes.paid') : t('pages.leaveTypes.unpaid'),
            },
            {
                id: 'affectsPayroll',
                header: t('pages.leaveTypes.columns.affectsPayroll'),
                cell: ({ row }) =>
                    row.original.affects_payroll ? t('common.yes') : t('common.no'),
            },
            {
                id: 'deductionComponent',
                header: t('pages.leaveTypes.columns.deductionComponent'),
                cell: ({ row }) => row.original.payroll_deduction_component_code ?? '—',
            },
            {
                id: 'encashmentComponent',
                header: t('pages.leaveTypes.columns.encashmentComponent'),
                cell: ({ row }) => row.original.payroll_encashment_component_code ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leaveTypes.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leaveTypes.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('leave.manage-types')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.leaveTypes.indexTitle')} />
            <PageHeader
                title={t('pages.leaveTypes.indexTitle')}
                description={t('pages.leaveTypes.indexDescription')}
            >
                {can('leave.manage-types') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.leaveTypes.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.leaveTypes.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={types.data ?? []}
                pagination={types}
                rowActions={rowActions}
                emptyMessage={t('pages.leaveTypes.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="md">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.leaveTypes.editTitle') : t('pages.leaveTypes.createTitle')}
                    </h3>
                    <AdminFormField label={t('pages.leaveTypes.fields.code')} error={form.errors.code} required>
                        <input
                            className="rp-form-input w-full font-mono"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                            disabled={!!editing}
                            placeholder={t('pages.leaveTypes.fields.codePlaceholder')}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.leaveTypes.fields.name')} error={form.errors.name} required>
                        <input
                            className="rp-form-input w-full"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder={t('pages.leaveTypes.fields.namePlaceholder')}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.leaveTypes.fields.isPaid')} error={form.errors.is_paid}>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={!!form.data.is_paid}
                                onChange={(e) => form.setData('is_paid', e.target.checked)}
                            />
                            {t('pages.leaveTypes.fields.isPaidHint')}
                        </label>
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.leaveTypes.fields.affectsPayroll')}
                        error={form.errors.affects_payroll}
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={!!form.data.affects_payroll}
                                onChange={(e) => form.setData('affects_payroll', e.target.checked)}
                            />
                            {t('pages.leaveTypes.fields.affectsPayrollHint')}
                        </label>
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.leaveTypes.fields.deductionComponent')}
                        error={form.errors.payroll_deduction_component_code}
                    >
                        <input
                            className="rp-form-input w-full font-mono"
                            value={form.data.payroll_deduction_component_code}
                            onChange={(e) =>
                                form.setData('payroll_deduction_component_code', e.target.value)
                            }
                            placeholder={t('pages.leaveTypes.fields.deductionComponentPlaceholder')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.leaveTypes.fields.encashmentComponent')}
                        error={form.errors.payroll_encashment_component_code}
                    >
                        <input
                            className="rp-form-input w-full font-mono"
                            value={form.data.payroll_encashment_component_code}
                            onChange={(e) =>
                                form.setData('payroll_encashment_component_code', e.target.value)
                            }
                            placeholder={t('pages.leaveTypes.fields.encashmentComponentPlaceholder')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.leaveTypes.fields.allowLeaveClaim')}
                        error={form.errors.allow_leave_claim}
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={!!form.data.allow_leave_claim}
                                onChange={(e) => form.setData('allow_leave_claim', e.target.checked)}
                            />
                            {t('pages.leaveTypes.fields.allowLeaveClaimHint')}
                        </label>
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.leaveTypes.fields.allowCashClaim')}
                        error={form.errors.allow_cash_claim}
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={!!form.data.allow_cash_claim}
                                onChange={(e) => form.setData('allow_cash_claim', e.target.checked)}
                            />
                            {t('pages.leaveTypes.fields.allowCashClaimHint')}
                        </label>
                    </AdminFormField>
                    {form.data.allow_cash_claim && (
                        <AdminFormField
                            label={t('pages.leaveTypes.fields.toilPayoutComponent')}
                            error={form.errors.payroll_toil_payout_component_code}
                        >
                            <input
                                className="rp-form-input w-full font-mono"
                                value={form.data.payroll_toil_payout_component_code}
                                onChange={(e) =>
                                    form.setData('payroll_toil_payout_component_code', e.target.value)
                                }
                                placeholder={t('pages.leaveTypes.fields.toilPayoutComponentPlaceholder')}
                            />
                        </AdminFormField>
                    )}
                    <AdminFormField label={t('pages.leaveTypes.fields.status')} error={form.errors.status}>
                        <Select
                            value={form.data.status}
                            options={formStatusOptions}
                            onChange={(v) => form.setData('status', v ?? 'active')}
                        />
                    </AdminFormField>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.leaveTypes.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
