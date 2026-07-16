import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        code: '',
        name: '',
        legal_entity_id: '',
        calculation_type: 'percentage_of_wage',
        employee_rate: '',
        employer_rate: '',
        wage_ceiling: '',
        account_mapping_key_employee: '',
        account_mapping_key_employer: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        status: 'active',
    };
}

function Index({ schemes, legalEntities = [], filters }) {
    const { t } = useTranslation();
    const can = useCan();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const filterEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.statutorySchemes.allEntities') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const formEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.statutorySchemes.fields.selectLegalEntity') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.statutorySchemes.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.statutorySchemes.statuses.${status}`),
            })),
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () =>
            ['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.statutorySchemes.statuses.${status}`),
            })),
        [t],
    );

    const calcOptions = useMemo(
        () => [
            {
                value: 'percentage_of_wage',
                label: t('pages.statutorySchemes.calculationTypes.percentage_of_wage'),
            },
        ],
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
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : '',
            calculation_type: row.calculation_type ?? 'percentage_of_wage',
            employee_rate: row.employee_rate ?? '',
            employer_rate: row.employer_rate ?? '',
            wage_ceiling: row.wage_ceiling ?? '',
            account_mapping_key_employee: row.account_mapping_key_employee ?? '',
            account_mapping_key_employer: row.account_mapping_key_employer ?? '',
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
        };

        if (editing) {
            form.put(route('admin.payroll.statutory-schemes.update', editing.id), options);
        } else {
            form.post(route('admin.payroll.statutory-schemes.store'), options);
        }
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.payroll.statutory-schemes.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.statutorySchemes.columns.code'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-semibold text-rp-text-primary">{row.original.code}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                    </div>
                ),
            },
            {
                id: 'legalEntity',
                header: t('pages.statutorySchemes.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'rates',
                header: t('pages.statutorySchemes.columns.rates'),
                cell: ({ row }) => (
                    <div className="text-sm">
                        <div>
                            {t('pages.statutorySchemes.employeeRate')}: {row.original.employee_rate}%
                        </div>
                        <div>
                            {t('pages.statutorySchemes.employerRate')}: {row.original.employer_rate}%
                        </div>
                    </div>
                ),
            },
            {
                id: 'wageCeiling',
                header: t('pages.statutorySchemes.columns.wageCeiling'),
                cell: ({ row }) =>
                    row.original.wage_ceiling != null ? Number(row.original.wage_ceiling).toLocaleString() : '—',
            },
            {
                id: 'mappingKeys',
                header: t('pages.statutorySchemes.columns.mappingKeys'),
                cell: ({ row }) => (
                    <div className="space-y-1 text-xs">
                        <div className="text-rp-text-muted">
                            {t('pages.statutorySchemes.employeeKey')}:{' '}
                            {row.original.account_mapping_key_employee ?? '—'}
                        </div>
                        <div className="text-rp-text-muted">
                            {t('pages.statutorySchemes.employerKey')}:{' '}
                            {row.original.account_mapping_key_employer ?? '—'}
                        </div>
                    </div>
                ),
            },
            {
                id: 'effectiveDates',
                header: t('pages.statutorySchemes.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '∞'}`,
            },
            {
                id: 'status',
                header: t('pages.statutorySchemes.columns.status'),
                cell: ({ row }) =>
                    t(`pages.statutorySchemes.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('payroll.manage-statutory')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.statutorySchemes.indexTitle')} />
            <PageHeader
                title={t('pages.statutorySchemes.indexTitle')}
                description={t('pages.statutorySchemes.indexDescription')}
            >
                {can('payroll.manage-statutory') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.statutorySchemes.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select
                    name="legal_entity_id"
                    defaultValue={filters.legal_entity_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={filterEntityOptions}
                />
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
                data={schemes.data ?? []}
                pagination={schemes}
                rowActions={rowActions}
                emptyMessage={t('pages.statutorySchemes.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="2xl">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.statutorySchemes.editTitle') : t('pages.statutorySchemes.createTitle')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.statutorySchemes.fields.code')} error={form.errors.code} required>
                            <input
                                className="rp-form-input font-mono"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                placeholder={t('pages.statutorySchemes.fields.codePlaceholder')}
                                disabled={!!editing}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.statutorySchemes.fields.name')} error={form.errors.name} required>
                            <input
                                className="rp-form-input"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder={t('pages.statutorySchemes.fields.namePlaceholder')}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.legalEntity')}
                            error={form.errors.legal_entity_id}
                            required
                        >
                            <Select
                                value={form.data.legal_entity_id}
                                options={formEntityOptions}
                                onChange={(v) => form.setData('legal_entity_id', v ?? '')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.calculationType')}
                            error={form.errors.calculation_type}
                        >
                            <Select
                                value={form.data.calculation_type}
                                options={calcOptions}
                                onChange={(v) => form.setData('calculation_type', v ?? 'percentage_of_wage')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.employeeRate')}
                            error={form.errors.employee_rate}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.000001"
                                className="rp-form-input"
                                value={form.data.employee_rate}
                                onChange={(e) => form.setData('employee_rate', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.employerRate')}
                            error={form.errors.employer_rate}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.000001"
                                className="rp-form-input"
                                value={form.data.employer_rate}
                                onChange={(e) => form.setData('employer_rate', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.wageCeiling')}
                            error={form.errors.wage_ceiling}
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.wage_ceiling}
                                onChange={(e) => form.setData('wage_ceiling', e.target.value)}
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
                            label={t('pages.statutorySchemes.fields.employeeMappingKey')}
                            error={form.errors.account_mapping_key_employee}
                        >
                            <input
                                className="rp-form-input font-mono"
                                value={form.data.account_mapping_key_employee}
                                onChange={(e) => form.setData('account_mapping_key_employee', e.target.value)}
                                placeholder={t('pages.statutorySchemes.fields.mappingKeyPlaceholder')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.employerMappingKey')}
                            error={form.errors.account_mapping_key_employer}
                        >
                            <input
                                className="rp-form-input font-mono"
                                value={form.data.account_mapping_key_employer}
                                onChange={(e) => form.setData('account_mapping_key_employer', e.target.value)}
                                placeholder={t('pages.statutorySchemes.fields.mappingKeyPlaceholder')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.statutorySchemes.fields.effectiveFrom')}
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
                            label={t('pages.statutorySchemes.fields.effectiveTo')}
                            error={form.errors.effective_to}
                        >
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.data.effective_to}
                                onChange={(e) => form.setData('effective_to', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.statutorySchemes.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
