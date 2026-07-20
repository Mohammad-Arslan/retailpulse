import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import ModalHeader from '@/Components/common/ModalHeader';
import PageHeader from '@/Components/common/PageHeader';
import ScrollArea from '@/Components/common/ScrollArea';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarRange, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const ACCRUAL_METHODS = ['fixed_annual', 'monthly_accrual', 'per_worked_hours'];
const NEGATIVE_LEAVE_BALANCE_POLICIES = ['block', 'warn', 'allow'];

function emptyForm() {
    return {
        leave_type_id: '',
        legal_entity_id: '',
        accrual_method: 'monthly_accrual',
        accrual_rate: '',
        max_balance: '',
        carry_forward_limit: '',
        carry_forward_expiry_months: '',
        negative_leave_balance_policy: 'block',
        proration_on_join: false,
        exclude_public_holidays: true,
        exclude_weekends: true,
        short_leave_max_hours: '',
        short_leave_max_requests_per_month: '',
        out_station_deducts_balance: false,
        encashment_allowed: false,
        encashment_max_days: '',
        encashment_requires_approval: true,
        year_end_excess_disposition: 'expire',
        effective_from: '',
        effective_to: '',
        status: 'active',
    };
}

function Index({ policies, filters, leaveTypes = [], legalEntities = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.leave.policies.index'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leavePolicies.allStatuses') },
            { value: 'active', label: t('pages.leavePolicies.statuses.active') },
            { value: 'inactive', label: t('pages.leavePolicies.statuses.inactive') },
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () => [
            { value: 'active', label: t('pages.leavePolicies.statuses.active') },
            { value: 'inactive', label: t('pages.leavePolicies.statuses.inactive') },
        ],
        [t],
    );

    const leaveTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leavePolicies.fields.selectLeaveType') },
            ...leaveTypes.map((type) => ({
                value: String(type.id),
                label: `${type.code} — ${type.name}`,
            })),
        ],
        [leaveTypes, t],
    );

    const legalEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.leavePolicies.scopeDefault') },
            ...legalEntities.map((entity) => ({
                value: String(entity.id),
                label: entity.legal_name,
            })),
        ],
        [legalEntities, t],
    );

    const accrualMethodOptions = useMemo(
        () =>
            ACCRUAL_METHODS.map((method) => ({
                value: method,
                label: t(`pages.leavePolicies.accrualMethods.${method}`),
            })),
        [t],
    );

    const negativeLeaveBalancePolicyOptions = useMemo(
        () =>
            NEGATIVE_LEAVE_BALANCE_POLICIES.map((policy) => ({
                value: policy,
                label: t(`pages.leavePolicies.negativeLeaveBalancePolicies.${policy}`),
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
            leave_type_id: row.leave_type_id ? String(row.leave_type_id) : '',
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : '',
            accrual_method: row.accrual_method ?? 'monthly_accrual',
            accrual_rate: row.accrual_rate ?? '',
            max_balance: row.max_balance ?? '',
            carry_forward_limit: row.carry_forward_limit ?? '',
            carry_forward_expiry_months:
                row.carry_forward_expiry_months !== null && row.carry_forward_expiry_months !== undefined
                    ? String(row.carry_forward_expiry_months)
                    : '',
            negative_leave_balance_policy: row.negative_leave_balance_policy ?? 'block',
            proration_on_join: !!row.proration_on_join,
            exclude_public_holidays: !!row.exclude_public_holidays,
            exclude_weekends: row.exclude_weekends !== false,
            short_leave_max_hours: row.short_leave_max_hours ?? '',
            short_leave_max_requests_per_month:
                row.short_leave_max_requests_per_month !== null && row.short_leave_max_requests_per_month !== undefined
                    ? String(row.short_leave_max_requests_per_month)
                    : '',
            out_station_deducts_balance: !!row.out_station_deducts_balance,
            encashment_allowed: !!row.encashment_allowed,
            encashment_max_days: row.encashment_max_days ?? '',
            encashment_requires_approval: row.encashment_requires_approval !== false,
            year_end_excess_disposition: row.year_end_excess_disposition ?? 'expire',
            effective_from: row.effective_from ?? '',
            effective_to: row.effective_to ?? '',
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
            form.put(route('admin.leave.policies.update', editing.id), options);
        } else {
            form.post(route('admin.leave.policies.store'), options);
        }
    };

    const columns = useMemo(
        () => [
            {
                id: 'leaveType',
                header: t('pages.leavePolicies.columns.leaveType'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <CalendarRange className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">{row.original.leave_type}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.leave_type_code}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'legalEntity',
                header: t('pages.leavePolicies.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? t('pages.leavePolicies.scopeDefault'),
            },
            {
                id: 'accrualMethod',
                header: t('pages.leavePolicies.columns.accrualMethod'),
                cell: ({ row }) =>
                    t(`pages.leavePolicies.accrualMethods.${row.original.accrual_method}`, {
                        defaultValue: row.original.accrual_method,
                    }),
            },
            {
                id: 'accrualRate',
                header: t('pages.leavePolicies.columns.accrualRate'),
                cell: ({ row }) => row.original.accrual_rate ?? '—',
            },
            {
                id: 'excludePublicHolidays',
                header: t('pages.leavePolicies.columns.excludePublicHolidays'),
                cell: ({ row }) => (
                    <ExcludeHolidayToggle row={row.original} canEdit={can('leave.manage-policies')} />
                ),
            },
            {
                id: 'excludeWeekends',
                header: t('pages.leavePolicies.columns.excludeWeekends'),
                cell: ({ row }) => (row.original.exclude_weekends ? t('common.yes') : t('common.no')),
            },
            {
                id: 'effectiveFrom',
                header: t('pages.leavePolicies.columns.effectiveFrom'),
                cell: ({ row }) => row.original.effective_from ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leavePolicies.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leavePolicies.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
        ],
        [can, t],
    );

    const rowActions = (row) => {
        if (!can('leave.manage-policies')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.leavePolicies.indexTitle')} />
            <PageHeader title={t('pages.leavePolicies.indexTitle')} description={t('pages.leavePolicies.indexDescription')}>
                {can('leave.manage-policies') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.leavePolicies.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select name="status" defaultValue={filters.status ?? ''} className="w-auto min-w-[10rem]" options={statusOptions} />
                <Button type="submit" variant="outline">
                    {t('common.apply')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={policies.data ?? []}
                pagination={policies}
                rowActions={rowActions}
                emptyMessage={t('pages.leavePolicies.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="3xl">
                <ModalHeader
                    icon={CalendarRange}
                    title={editing ? t('pages.leavePolicies.editTitle') : t('pages.leavePolicies.createTitle')}
                    description={t('pages.leavePolicies.indexDescription')}
                    onClose={() => setModalOpen(false)}
                />
                <ScrollArea as="form" onSubmit={submit} className="max-h-[75vh] space-y-5 overflow-y-auto p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.leavePolicies.fields.leaveType')}
                            id="leave_type_id"
                            error={form.errors.leave_type_id}
                            required
                        >
                            <Select
                                id="leave_type_id"
                                value={form.data.leave_type_id}
                                onChange={(value) => form.setData('leave_type_id', value ?? '')}
                                options={leaveTypeOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.leavePolicies.fields.legalEntity')}
                            id="legal_entity_id"
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                id="legal_entity_id"
                                value={form.data.legal_entity_id}
                                onChange={(value) => form.setData('legal_entity_id', value ?? '')}
                                options={legalEntityOptions}
                                isClearable
                            />
                        </AdminFormField>
                    </div>

                    <div className="space-y-3 border-t border-rp-border pt-4">
                        <h4 className="rp-section-title">{t('pages.leavePolicies.sections.accrual')}</h4>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.accrualMethod')}
                                id="accrual_method"
                                error={form.errors.accrual_method}
                                required
                            >
                                <Select
                                    id="accrual_method"
                                    value={form.data.accrual_method}
                                    onChange={(value) => form.setData('accrual_method', value ?? 'monthly_accrual')}
                                    options={accrualMethodOptions}
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.accrualRate')}
                                id="accrual_rate"
                                error={form.errors.accrual_rate}
                                required
                            >
                                <input
                                    id="accrual_rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    value={form.data.accrual_rate}
                                    onChange={(e) => form.setData('accrual_rate', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.accrualRatePlaceholder')}
                                    className="rp-form-input"
                                    required
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.maxBalance')}
                                id="max_balance"
                                error={form.errors.max_balance}
                            >
                                <input
                                    id="max_balance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.max_balance}
                                    onChange={(e) => form.setData('max_balance', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.maxBalancePlaceholder')}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.carryForwardLimit')}
                                id="carry_forward_limit"
                                error={form.errors.carry_forward_limit}
                            >
                                <input
                                    id="carry_forward_limit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.carry_forward_limit}
                                    onChange={(e) => form.setData('carry_forward_limit', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.carryForwardLimitPlaceholder')}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.carryForwardExpiryMonths')}
                                id="carry_forward_expiry_months"
                                error={form.errors.carry_forward_expiry_months}
                            >
                                <input
                                    id="carry_forward_expiry_months"
                                    type="number"
                                    min="0"
                                    max="120"
                                    value={form.data.carry_forward_expiry_months}
                                    onChange={(e) => form.setData('carry_forward_expiry_months', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.carryForwardExpiryMonthsPlaceholder')}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.negativeLeaveBalancePolicy')}
                                id="negative_leave_balance_policy"
                                error={form.errors.negative_leave_balance_policy}
                                hint={t('pages.leavePolicies.fields.negativeLeaveBalancePolicyHint')}
                                required
                            >
                                <Select
                                    id="negative_leave_balance_policy"
                                    value={form.data.negative_leave_balance_policy}
                                    onChange={(value) => form.setData('negative_leave_balance_policy', value ?? 'block')}
                                    options={negativeLeaveBalancePolicyOptions}
                                />
                            </AdminFormField>
                        </div>
                        <label className="rp-checkbox-label rounded-lg border border-rp-border p-3">
                            <input
                                type="checkbox"
                                checked={!!form.data.proration_on_join}
                                onChange={(e) => form.setData('proration_on_join', e.target.checked)}
                                className="accent-teal-600"
                            />
                            {t('pages.leavePolicies.fields.prorationOnJoinHint')}
                        </label>
                    </div>

                    <div className="space-y-3 border-t border-rp-border pt-4">
                        <h4 className="rp-section-title">{t('pages.leavePolicies.sections.dayCounting')}</h4>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="rp-checkbox-label rounded-lg border border-rp-border p-3">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.exclude_public_holidays}
                                    onChange={(e) => form.setData('exclude_public_holidays', e.target.checked)}
                                    className="accent-teal-600"
                                />
                                {t('pages.leavePolicies.fields.excludePublicHolidaysHint')}
                            </label>
                            <label className="rp-checkbox-label rounded-lg border border-rp-border p-3">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.exclude_weekends}
                                    onChange={(e) => form.setData('exclude_weekends', e.target.checked)}
                                    className="accent-teal-600"
                                />
                                {t('pages.leavePolicies.fields.excludeWeekendsHint')}
                            </label>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.shortLeaveMaxHours')}
                                id="short_leave_max_hours"
                                error={form.errors.short_leave_max_hours}
                            >
                                <input
                                    id="short_leave_max_hours"
                                    type="number"
                                    step="0.25"
                                    min="0.25"
                                    max="24"
                                    value={form.data.short_leave_max_hours}
                                    onChange={(e) => form.setData('short_leave_max_hours', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.shortLeaveMaxHoursPlaceholder')}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.shortLeaveMaxRequestsPerMonth')}
                                id="short_leave_max_requests_per_month"
                                error={form.errors.short_leave_max_requests_per_month}
                            >
                                <input
                                    id="short_leave_max_requests_per_month"
                                    type="number"
                                    min="1"
                                    max="31"
                                    value={form.data.short_leave_max_requests_per_month}
                                    onChange={(e) => form.setData('short_leave_max_requests_per_month', e.target.value)}
                                    placeholder={t('pages.leavePolicies.fields.shortLeaveMaxRequestsPerMonthPlaceholder')}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                        </div>
                        <label className="rp-checkbox-label rounded-lg border border-rp-border p-3">
                            <input
                                type="checkbox"
                                checked={!!form.data.out_station_deducts_balance}
                                onChange={(e) => form.setData('out_station_deducts_balance', e.target.checked)}
                                className="accent-teal-600"
                            />
                            {t('pages.leavePolicies.fields.outStationDeductsBalanceHint')}
                        </label>
                    </div>

                    <div className="space-y-3 border-t border-rp-border pt-4">
                        <h4 className="rp-section-title">{t('pages.leavePolicies.sections.encashment')}</h4>
                        <label className="rp-checkbox-label rounded-lg border border-rp-border p-3">
                            <input
                                type="checkbox"
                                checked={!!form.data.encashment_allowed}
                                onChange={(e) => form.setData('encashment_allowed', e.target.checked)}
                                className="accent-teal-600"
                            />
                            {t('pages.leavePolicies.fields.encashmentAllowedHint')}
                        </label>
                        {form.data.encashment_allowed && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField
                                    label={t('pages.leavePolicies.fields.encashmentMaxDays')}
                                    id="encashment_max_days"
                                    error={form.errors.encashment_max_days}
                                >
                                    <input
                                        id="encashment_max_days"
                                        type="number"
                                        step="0.25"
                                        min="0.25"
                                        value={form.data.encashment_max_days}
                                        onChange={(e) => form.setData('encashment_max_days', e.target.value)}
                                        placeholder={t('pages.leavePolicies.fields.encashmentMaxDaysPlaceholder')}
                                        className="rp-form-input"
                                    />
                                </AdminFormField>
                                <label className="rp-checkbox-label self-end rounded-lg border border-rp-border p-3">
                                    <input
                                        type="checkbox"
                                        checked={!!form.data.encashment_requires_approval}
                                        onChange={(e) =>
                                            form.setData('encashment_requires_approval', e.target.checked)
                                        }
                                        className="accent-teal-600"
                                    />
                                    {t('pages.leavePolicies.fields.encashmentRequiresApprovalHint')}
                                </label>
                            </div>
                        )}
                    </div>

                    <div className="space-y-3 border-t border-rp-border pt-4">
                        <h4 className="rp-section-title">{t('pages.leavePolicies.sections.scheduleAndStatus')}</h4>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.effectiveFrom')}
                                id="effective_from"
                                error={form.errors.effective_from}
                                required
                            >
                                <input
                                    id="effective_from"
                                    type="date"
                                    value={form.data.effective_from}
                                    onChange={(e) => form.setData('effective_from', e.target.value)}
                                    className="rp-form-input"
                                    required
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.effectiveTo')}
                                id="effective_to"
                                error={form.errors.effective_to}
                            >
                                <input
                                    id="effective_to"
                                    type="date"
                                    value={form.data.effective_to}
                                    onChange={(e) => form.setData('effective_to', e.target.value)}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.leavePolicies.fields.status')} id="status" error={form.errors.status}>
                                <Select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(value) => form.setData('status', value ?? 'active')}
                                    options={formStatusOptions}
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leavePolicies.fields.yearEndExcessDisposition')}
                                id="year_end_excess_disposition"
                                error={form.errors.year_end_excess_disposition}
                                hint={t('pages.leavePolicies.fields.yearEndExcessDispositionHint')}
                            >
                                <Select
                                    id="year_end_excess_disposition"
                                    value={form.data.year_end_excess_disposition}
                                    onChange={(value) => form.setData('year_end_excess_disposition', value ?? 'expire')}
                                    options={[
                                        { value: 'expire', label: t('pages.leavePolicies.yearEndExcessDispositions.expire') },
                                        { value: 'encash', label: t('pages.leavePolicies.yearEndExcessDispositions.encash') },
                                    ]}
                                />
                            </AdminFormField>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-rp-border pt-4">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.leavePolicies.createSubmit')}
                        </Button>
                    </div>
                </ScrollArea>
            </Modal>
        </>
    );
}

function ExcludeHolidayToggle({ row, canEdit }) {
    const { t } = useTranslation();
    const [enabled, setEnabled] = useState(row.exclude_public_holidays);
    const [processing, setProcessing] = useState(false);

    const toggle = () => {
        if (!canEdit || processing) {
            return;
        }
        const next = !enabled;
        setProcessing(true);
        router.put(
            route('admin.leave.policies.update', row.id),
            { exclude_public_holidays: next },
            {
                preserveScroll: true,
                onSuccess: () => setEnabled(next),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <button
            type="button"
            disabled={!canEdit || processing}
            onClick={toggle}
            className={`rounded-full px-3 py-1 text-xs font-medium ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                    : 'bg-rp-surface-muted text-rp-text-muted'
            }`}
        >
            {enabled ? t('common.yes') : t('common.no')}
        </button>
    );
}

export default withAdminLayout(Index);
