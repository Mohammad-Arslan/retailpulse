import AdminFormField from '@/Components/common/AdminFormField';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleDollarSign, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        code: '',
        name: '',
        type: 'earning',
        calculation_type: 'fixed',
        basis_component_id: '',
        rate: '',
        taxable: true,
        account_mapping_key: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        legal_entity_id: '',
        status: 'active',
    };
}

function Index({ components, filters, legalEntities = [], basisOptions = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const typeOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.allTypes') },
            ...['earning', 'deduction', 'employer_contribution', 'statutory', 'reimbursement'].map((type) => ({
                value: type,
                label: t(`pages.payComponents.types.${type}`),
            })),
        ],
        [t],
    );

    const formTypeOptions = useMemo(
        () =>
            ['earning', 'deduction', 'employer_contribution', 'statutory', 'reimbursement'].map((type) => ({
                value: type,
                label: t(`pages.payComponents.types.${type}`),
            })),
        [t],
    );

    const calcOptions = useMemo(
        () =>
            ['fixed', 'percentage_of', 'table_lookup'].map((type) => ({
                value: type,
                label: t(`pages.payComponents.calculationTypes.${type}`),
            })),
        [t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.payComponents.statuses.${status}`),
            })),
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () =>
            ['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.payComponents.statuses.${status}`),
            })),
        [t],
    );

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.fields.noLegalEntity') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const basisSelectOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.fields.noBasis') },
            ...basisOptions
                .filter((c) => !editing || c.id !== editing.id)
                .map((c) => ({ value: String(c.id), label: `${c.code} — ${c.name}` })),
        ],
        [basisOptions, editing, t],
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
            type: row.type ?? 'earning',
            calculation_type: row.calculation_type === 'formula' ? 'fixed' : (row.calculation_type ?? 'fixed'),
            basis_component_id: row.basis_component_id ? String(row.basis_component_id) : '',
            rate: row.rate ?? '',
            taxable: !!row.taxable,
            account_mapping_key: row.account_mapping_key ?? '',
            effective_from: row.effective_from ?? '',
            effective_to: row.effective_to ?? '',
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : '',
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
            form.put(route('admin.payroll.pay-components.update', editing.id), options);
        } else {
            form.post(route('admin.payroll.pay-components.store'), options);
        }
    };

    const destroy = async (row) => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('pages.payComponents.confirmDelete', { name: row.name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });
        if (!confirmed) {
            return;
        }
        router.delete(route('admin.payroll.pay-components.destroy', row.id), { preserveScroll: true });
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.payroll.pay-components.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.payComponents.columns.code'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300">
                            <CircleDollarSign className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">{row.original.code}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'type',
                header: t('pages.payComponents.columns.type'),
                cell: ({ row }) =>
                    t(`pages.payComponents.types.${row.original.type}`, { defaultValue: row.original.type }),
            },
            {
                id: 'calculationType',
                header: t('pages.payComponents.columns.calculationType'),
                cell: ({ row }) =>
                    t(`pages.payComponents.calculationTypes.${row.original.calculation_type}`, {
                        defaultValue: row.original.calculation_type,
                    }),
            },
            {
                id: 'basis',
                header: t('pages.payComponents.columns.basis'),
                cell: ({ row }) => row.original.basis_component ?? '—',
            },
            {
                id: 'rate',
                header: t('pages.payComponents.columns.rate'),
                cell: ({ row }) => (row.original.rate != null ? `${row.original.rate}%` : '—'),
            },
            {
                id: 'taxable',
                header: t('pages.payComponents.columns.taxable'),
                cell: ({ row }) => (row.original.taxable ? t('common.yes') : t('common.no')),
            },
            {
                id: 'accountMappingKey',
                header: t('pages.payComponents.columns.accountMappingKey'),
                cell: ({ row }) => row.original.account_mapping_key ?? '—',
            },
            {
                id: 'status',
                header: t('pages.payComponents.columns.status'),
                cell: ({ row }) =>
                    t(`pages.payComponents.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('payroll.manage-components')) {
            return [];
        }

        return [
            { label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) },
            { label: t('common.delete'), type: 'delete', onClick: () => destroy(row) },
        ];
    };

    return (
        <>
            <Head title={t('pages.payComponents.indexTitle')} />
            <PageHeader
                title={t('pages.payComponents.indexTitle')}
                description={t('pages.payComponents.indexDescription')}
            >
                {can('payroll.manage-components') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.payComponents.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.payComponents.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="type"
                    defaultValue={filters.type ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={typeOptions}
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
                data={components.data ?? []}
                pagination={components}
                rowActions={rowActions}
                emptyMessage={t('pages.payComponents.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="2xl">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.payComponents.editTitle') : t('pages.payComponents.createTitle')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.payComponents.fields.code')} error={form.errors.code} required>
                            <input
                                className="rp-form-input font-mono"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                placeholder={t('pages.payComponents.fields.codePlaceholder')}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.payComponents.fields.name')} error={form.errors.name} required>
                            <input
                                className="rp-form-input"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder={t('pages.payComponents.fields.namePlaceholder')}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.payComponents.fields.type')} error={form.errors.type}>
                            <Select
                                value={form.data.type}
                                options={formTypeOptions}
                                onChange={(v) => form.setData('type', v ?? 'earning')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payComponents.fields.calculationType')}
                            error={form.errors.calculation_type}
                        >
                            <Select
                                value={form.data.calculation_type}
                                options={calcOptions}
                                onChange={(v) => form.setData('calculation_type', v ?? 'fixed')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payComponents.fields.basisComponent')}
                            error={form.errors.basis_component_id}
                        >
                            <Select
                                value={form.data.basis_component_id}
                                options={basisSelectOptions}
                                onChange={(v) => form.setData('basis_component_id', v ?? '')}
                                isClearable
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.payComponents.fields.rate')} error={form.errors.rate}>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.rate}
                                onChange={(e) => form.setData('rate', e.target.value)}
                                placeholder={t('pages.payComponents.fields.ratePlaceholder')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payComponents.fields.accountMappingKey')}
                            error={form.errors.account_mapping_key}
                        >
                            <input
                                className="rp-form-input font-mono"
                                value={form.data.account_mapping_key}
                                onChange={(e) => form.setData('account_mapping_key', e.target.value)}
                                placeholder={t('pages.payComponents.fields.accountMappingKeyPlaceholder')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payComponents.fields.legalEntity')}
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                value={form.data.legal_entity_id}
                                options={entityOptions}
                                onChange={(v) => form.setData('legal_entity_id', v ?? '')}
                                isClearable
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payComponents.fields.effectiveFrom')}
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
                            label={t('pages.payComponents.fields.effectiveTo')}
                            error={form.errors.effective_to}
                        >
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.data.effective_to}
                                onChange={(e) => form.setData('effective_to', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.status')} error={form.errors.status}>
                            <Select
                                value={form.data.status}
                                options={formStatusOptions}
                                onChange={(v) => form.setData('status', v ?? 'active')}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.payComponents.fields.taxable')} error={form.errors.taxable}>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.taxable}
                                    onChange={(e) => form.setData('taxable', e.target.checked)}
                                />
                                {t('pages.payComponents.fields.taxableHint')}
                            </label>
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.payComponents.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
