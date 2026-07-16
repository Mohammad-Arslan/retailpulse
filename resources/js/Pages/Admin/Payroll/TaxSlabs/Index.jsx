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
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        legal_entity_id: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        lower_bound: '',
        upper_bound: '',
        fixed_amount: '0',
        marginal_rate: '',
        status: 'active',
    };
}

function formatAmount(value) {
    if (value == null) return '—';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function Index({ slabs, legalEntities = [], filters }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const filterEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.taxSlabs.allEntities') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const formEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.taxSlabs.fields.selectLegalEntity') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.taxSlabs.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.taxSlabs.statuses.${status}`),
            })),
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () =>
            ['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.taxSlabs.statuses.${status}`),
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
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : '',
            effective_from: row.effective_from ?? '',
            effective_to: row.effective_to ?? '',
            lower_bound: row.lower_bound ?? '',
            upper_bound: row.upper_bound ?? '',
            fixed_amount: row.fixed_amount ?? '0',
            marginal_rate: row.marginal_rate ?? '',
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
            form.put(route('admin.payroll.tax-slabs.update', editing.id), options);
        } else {
            form.post(route('admin.payroll.tax-slabs.store'), options);
        }
    };

    const destroy = async (row) => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('pages.taxSlabs.confirmDelete'),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });
        if (!confirmed) {
            return;
        }
        router.delete(route('admin.payroll.tax-slabs.destroy', row.id), { preserveScroll: true });
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.payroll.tax-slabs.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'legalEntity',
                header: t('pages.taxSlabs.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'effectiveDates',
                header: t('pages.taxSlabs.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '∞'}`,
            },
            {
                id: 'lowerBound',
                header: t('pages.taxSlabs.columns.lowerBound'),
                cell: ({ row }) => formatAmount(row.original.lower_bound),
            },
            {
                id: 'upperBound',
                header: t('pages.taxSlabs.columns.upperBound'),
                cell: ({ row }) => (row.original.upper_bound != null ? formatAmount(row.original.upper_bound) : '∞'),
            },
            {
                id: 'fixedAmount',
                header: t('pages.taxSlabs.columns.fixedAmount'),
                cell: ({ row }) => formatAmount(row.original.fixed_amount),
            },
            {
                id: 'marginalRate',
                header: t('pages.taxSlabs.columns.marginalRate'),
                cell: ({ row }) => `${row.original.marginal_rate}%`,
            },
            {
                id: 'status',
                header: t('pages.taxSlabs.columns.status'),
                cell: ({ row }) =>
                    t(`pages.taxSlabs.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('payroll.manage-tax-slabs')) {
            return [];
        }

        return [
            { label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) },
            { label: t('common.delete'), type: 'delete', onClick: () => destroy(row) },
        ];
    };

    return (
        <>
            <Head title={t('pages.taxSlabs.indexTitle')} />
            <PageHeader
                title={t('pages.taxSlabs.indexTitle')}
                description={t('pages.taxSlabs.indexDescription')}
            >
                {can('payroll.manage-tax-slabs') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.taxSlabs.createTitle')}
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
                data={slabs.data ?? []}
                pagination={slabs}
                rowActions={rowActions}
                emptyMessage={t('pages.taxSlabs.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="2xl">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.taxSlabs.editTitle') : t('pages.taxSlabs.createTitle')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.taxSlabs.fields.legalEntity')}
                            error={form.errors.legal_entity_id}
                            required
                        >
                            <Select
                                value={form.data.legal_entity_id}
                                options={formEntityOptions}
                                onChange={(v) => form.setData('legal_entity_id', v ?? '')}
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
                            label={t('pages.taxSlabs.fields.effectiveFrom')}
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
                            label={t('pages.taxSlabs.fields.effectiveTo')}
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
                            label={t('pages.taxSlabs.fields.lowerBound')}
                            error={form.errors.lower_bound}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.lower_bound}
                                onChange={(e) => form.setData('lower_bound', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.taxSlabs.fields.upperBound')}
                            error={form.errors.upper_bound}
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.upper_bound}
                                onChange={(e) => form.setData('upper_bound', e.target.value)}
                                placeholder={t('pages.taxSlabs.fields.upperBoundPlaceholder')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.taxSlabs.fields.fixedAmount')}
                            error={form.errors.fixed_amount}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.fixed_amount}
                                onChange={(e) => form.setData('fixed_amount', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.taxSlabs.fields.marginalRate')}
                            error={form.errors.marginal_rate}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.000001"
                                className="rp-form-input"
                                value={form.data.marginal_rate}
                                onChange={(e) => form.setData('marginal_rate', e.target.value)}
                                required
                            />
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing ? t('common.save') : t('pages.taxSlabs.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
