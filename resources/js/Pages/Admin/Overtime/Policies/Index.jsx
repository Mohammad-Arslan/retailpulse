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
import { Plus, Scale } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const DAY_TYPES = ['weekday', 'weekend', 'rest_day', 'public_holiday'];
const COMPENSATION_TYPES = ['cash', 'toil', 'employee_choice'];

function defaultMultipliers() {
    return DAY_TYPES.map((day_type) => ({
        day_type,
        multiplier: day_type === 'weekday' ? '1.5' : '2',
        compensation_type: 'cash',
    }));
}

function emptyForm() {
    return {
        legal_entity_id: '',
        branch_id: '',
        daily_threshold_minutes: '480',
        weekly_threshold_minutes: '2880',
        rest_day_applies: false,
        public_holiday_applies: true,
        toil_expiry_months: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        status: 'active',
        priority: '100',
        multipliers: defaultMultipliers(),
    };
}

function formatMinutes(minutes) {
    if (minutes == null) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
}

function Index({ policies, filters, legalEntities = [], branches = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.overtimePolicies.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.overtimePolicies.statuses.${status}`),
            })),
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () =>
            ['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.overtimePolicies.statuses.${status}`),
            })),
        [t],
    );

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.overtimePolicies.fields.noLegalEntity') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.overtimePolicies.fields.noBranch') },
            ...branches.map((b) => ({ value: String(b.id), label: b.code ? `${b.name} (${b.code})` : b.name })),
        ],
        [branches, t],
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
        const byType = Object.fromEntries((row.multipliers ?? []).map((m) => [m.day_type, m]));
        form.setData({
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : '',
            branch_id: row.branch_id ? String(row.branch_id) : '',
            daily_threshold_minutes: String(row.daily_threshold_minutes ?? ''),
            weekly_threshold_minutes: row.weekly_threshold_minutes != null ? String(row.weekly_threshold_minutes) : '',
            rest_day_applies: !!row.rest_day_applies,
            public_holiday_applies: !!row.public_holiday_applies,
            toil_expiry_months: row.toil_expiry_months != null ? String(row.toil_expiry_months) : '',
            effective_from: row.effective_from ?? '',
            effective_to: row.effective_to ?? '',
            status: row.status ?? 'active',
            priority: String(row.priority ?? 100),
            multipliers: DAY_TYPES.map((day_type) => ({
                day_type,
                multiplier: byType[day_type]?.multiplier != null ? String(byType[day_type].multiplier) : '',
                compensation_type: byType[day_type]?.compensation_type ?? 'cash',
            })),
        });
        setModalOpen(true);
    };

    const setMultiplier = (dayType, field, value) => {
        form.setData(
            'multipliers',
            form.data.multipliers.map((row) => (row.day_type === dayType ? { ...row, [field]: value } : row)),
        );
    };

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
        };

        if (editing) {
            form.put(route('admin.overtime.policies.update', editing.id), options);
        } else {
            form.post(route('admin.overtime.policies.store'), options);
        }
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.overtime.policies.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'scope',
                header: t('pages.overtimePolicies.columns.scope'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                            <Scale className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.legal_entity ?? row.original.branch ?? t('pages.overtimePolicies.scopeGlobal')}
                            </div>
                            {row.original.branch && row.original.legal_entity && (
                                <div className="text-xs text-rp-text-muted">{row.original.branch}</div>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                id: 'dailyThreshold',
                header: t('pages.overtimePolicies.columns.dailyThreshold'),
                cell: ({ row }) => formatMinutes(row.original.daily_threshold_minutes),
            },
            {
                id: 'weeklyThreshold',
                header: t('pages.overtimePolicies.columns.weeklyThreshold'),
                cell: ({ row }) =>
                    row.original.weekly_threshold_minutes != null
                        ? formatMinutes(row.original.weekly_threshold_minutes)
                        : '—',
            },
            {
                id: 'multipliers',
                header: t('pages.overtimePolicies.columns.multipliers'),
                cell: ({ row }) => (
                    <div className="space-y-1 text-sm">
                        {(row.original.multipliers ?? []).map((item) => (
                            <div key={item.id ?? item.day_type} className="text-rp-text-primary">
                                {t(`pages.overtimePolicies.dayTypes.${item.day_type}`, {
                                    defaultValue: item.day_type,
                                })}
                                : {item.multiplier}x
                                {item.compensation_type && item.compensation_type !== 'cash' && (
                                    <span className="ml-1 text-xs text-rp-text-muted">
                                        ({t(`pages.overtimePolicies.compensationTypes.${item.compensation_type}`)})
                                    </span>
                                )}
                            </div>
                        ))}
                        {(row.original.multipliers ?? []).length === 0 && '—'}
                    </div>
                ),
            },
            {
                id: 'effectiveDates',
                header: t('pages.overtimePolicies.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '—'}`,
            },
            {
                id: 'priority',
                header: t('pages.overtimePolicies.columns.priority'),
                cell: ({ row }) => row.original.priority ?? '—',
            },
            {
                id: 'status',
                header: t('pages.overtimePolicies.columns.status'),
                cell: ({ row }) =>
                    t(`pages.overtimePolicies.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('overtime.manage-policies')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.overtimePolicies.indexTitle')} />
            <PageHeader
                title={t('pages.overtimePolicies.indexTitle')}
                description={t('pages.overtimePolicies.indexDescription')}
            >
                {can('overtime.manage-policies') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.overtimePolicies.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
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
                data={policies.data ?? []}
                pagination={policies}
                rowActions={rowActions}
                emptyMessage={t('pages.overtimePolicies.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="3xl">
                <ModalHeader
                    icon={Scale}
                    title={editing ? t('pages.overtimePolicies.editTitle') : t('pages.overtimePolicies.createTitle')}
                    description={t('pages.overtimePolicies.indexDescription')}
                    onClose={() => setModalOpen(false)}
                />
                <ScrollArea as="form" onSubmit={submit} className="max-h-[75vh] space-y-5 overflow-y-auto p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.legalEntity')}
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                value={form.data.legal_entity_id}
                                options={entityOptions}
                                onChange={(v) => form.setData('legal_entity_id', v ?? '')}
                                isClearable
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.overtimePolicies.fields.branch')} error={form.errors.branch_id}>
                            <Select
                                value={form.data.branch_id}
                                options={branchOptions}
                                onChange={(v) => form.setData('branch_id', v ?? '')}
                                isClearable
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.dailyThreshold')}
                            error={form.errors.daily_threshold_minutes}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                className="rp-form-input"
                                value={form.data.daily_threshold_minutes}
                                onChange={(e) => form.setData('daily_threshold_minutes', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.weeklyThreshold')}
                            error={form.errors.weekly_threshold_minutes}
                        >
                            <input
                                type="number"
                                min="0"
                                className="rp-form-input"
                                value={form.data.weekly_threshold_minutes}
                                onChange={(e) => form.setData('weekly_threshold_minutes', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.effectiveFrom')}
                            error={form.errors.effective_from}
                            required
                        >
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.data.effective_from}
                                onChange={(e) => form.setData('effective_from', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.effectiveTo')}
                            error={form.errors.effective_to}
                        >
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.data.effective_to}
                                onChange={(e) => form.setData('effective_to', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.priority')}
                            error={form.errors.priority}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                className="rp-form-input"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.status')} error={form.errors.status}>
                            <Select
                                value={form.data.status}
                                options={formStatusOptions}
                                onChange={(v) => form.setData('status', v ?? 'active')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.restDayApplies')}
                            error={form.errors.rest_day_applies}
                        >
                            <label className="rp-checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.rest_day_applies}
                                    onChange={(e) => form.setData('rest_day_applies', e.target.checked)}
                                    className="accent-teal-600"
                                />
                                {t('pages.overtimePolicies.fields.restDayAppliesHint')}
                            </label>
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.publicHolidayApplies')}
                            error={form.errors.public_holiday_applies}
                        >
                            <label className="rp-checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.public_holiday_applies}
                                    onChange={(e) => form.setData('public_holiday_applies', e.target.checked)}
                                    className="accent-teal-600"
                                />
                                {t('pages.overtimePolicies.fields.publicHolidayAppliesHint')}
                            </label>
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.overtimePolicies.fields.toilExpiryMonths')}
                            error={form.errors.toil_expiry_months}
                            hint={t('pages.overtimePolicies.fields.toilExpiryMonthsHint')}
                        >
                            <input
                                type="number"
                                min="1"
                                max="120"
                                className="rp-form-input"
                                value={form.data.toil_expiry_months}
                                onChange={(e) => form.setData('toil_expiry_months', e.target.value)}
                                placeholder={t('pages.overtimePolicies.fields.toilExpiryMonthsPlaceholder')}
                            />
                        </AdminFormField>
                    </div>

                    <div className="space-y-2 border-t border-rp-border pt-4">
                        <h4 className="rp-section-title">
                            {t('pages.overtimePolicies.fields.multipliers')}
                        </h4>
                        {form.errors.multipliers && (
                            <p className="text-sm text-destructive">{form.errors.multipliers}</p>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {DAY_TYPES.map((dayType, index) => {
                                const row = form.data.multipliers.find((m) => m.day_type === dayType) ?? {
                                    day_type: dayType,
                                    multiplier: '',
                                    compensation_type: 'cash',
                                };
                                return (
                                    <div
                                        key={dayType}
                                        className="space-y-2 rounded-xl border border-rp-border bg-rp-surface-inset/40 p-3.5"
                                    >
                                        <AdminFormField
                                            label={t(`pages.overtimePolicies.dayTypes.${dayType}`)}
                                            error={
                                                form.errors[`multipliers.${index}.multiplier`] ||
                                                form.errors[`multipliers.${index}.day_type`]
                                            }
                                            required
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                className="rp-form-input"
                                                value={row.multiplier}
                                                onChange={(e) => setMultiplier(dayType, 'multiplier', e.target.value)}
                                                required
                                            />
                                        </AdminFormField>
                                        <AdminFormField
                                            label={t('pages.overtimePolicies.fields.compensationType')}
                                            error={form.errors[`multipliers.${index}.compensation_type`]}
                                        >
                                            <Select
                                                value={row.compensation_type}
                                                options={COMPENSATION_TYPES.map((type) => ({
                                                    value: type,
                                                    label: t(`pages.overtimePolicies.compensationTypes.${type}`),
                                                }))}
                                                onChange={(v) => setMultiplier(dayType, 'compensation_type', v ?? 'cash')}
                                            />
                                        </AdminFormField>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-rp-border pt-4">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.overtimePolicies.createSubmit')}
                        </Button>
                    </div>
                </ScrollArea>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
