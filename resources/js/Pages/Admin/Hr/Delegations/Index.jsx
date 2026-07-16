import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, UserCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        from_employee_id: '',
        to_employee_id: '',
        scope: 'leave',
        effective_from: '',
        effective_to: '',
        status: 'active',
    };
}

function Index({ delegations, filters, employees = [], scopes = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrDelegations.selectEmployee') },
            ...employees.map((e) => ({ value: String(e.id), label: e.label })),
        ],
        [employees, t],
    );

    const scopeOptions = useMemo(
        () =>
            scopes.map((scope) => ({
                value: scope,
                label: t(`pages.hrDelegations.scopes.${scope}`, { defaultValue: scope }),
            })),
        [scopes, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            { value: 'active', label: t('pages.hrDelegations.statuses.active') },
            { value: 'inactive', label: t('pages.hrDelegations.statuses.inactive') },
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () => [
            { value: 'active', label: t('pages.hrDelegations.statuses.active') },
            { value: 'inactive', label: t('pages.hrDelegations.statuses.inactive') },
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
            from_employee_id: String(row.from_employee_id),
            to_employee_id: String(row.to_employee_id),
            scope: row.scope,
            effective_from: row.effective_from ?? '',
            effective_to: row.effective_to ?? '',
            status: row.status ?? 'active',
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setModalOpen(false) };
        if (editing) {
            form.put(route('admin.hr.delegations.update', editing.id), options);
        } else {
            form.post(route('admin.hr.delegations.store'), options);
        }
    };

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.hr.delegations.index'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'from',
                header: t('pages.hrDelegations.columns.from'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                            <UserCheck className="h-4 w-4" />
                        </span>
                        <span className="text-sm font-semibold text-rp-text">{row.original.from_employee}</span>
                    </div>
                ),
            },
            {
                id: 'to',
                header: t('pages.hrDelegations.columns.to'),
                cell: ({ row }) => row.original.to_employee ?? '—',
            },
            {
                id: 'scope',
                header: t('pages.hrDelegations.columns.scope'),
                cell: ({ row }) =>
                    t(`pages.hrDelegations.scopes.${row.original.scope}`, { defaultValue: row.original.scope }),
            },
            {
                id: 'effectiveFrom',
                header: t('pages.hrDelegations.columns.effectiveFrom'),
                cell: ({ row }) => row.original.effective_from ?? '—',
            },
            {
                id: 'effectiveTo',
                header: t('pages.hrDelegations.columns.effectiveTo'),
                cell: ({ row }) => row.original.effective_to ?? '—',
            },
            {
                id: 'status',
                header: t('pages.hrDelegations.columns.status'),
                cell: ({ row }) =>
                    t(`pages.hrDelegations.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('hr.manage-org')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.hrDelegations.indexTitle')} />
            <PageHeader title={t('pages.hrDelegations.indexTitle')} description={t('pages.hrDelegations.indexDescription')}>
                {can('hr.manage-org') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.hrDelegations.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select name="status" defaultValue={filters.status ?? ''} className="w-auto min-w-[10rem]" options={statusOptions} />
                <Select
                    name="scope"
                    defaultValue={filters.scope ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[{ value: '', label: t('pages.hrDelegations.allScopes') }, ...scopeOptions]}
                />
                <Button type="submit" variant="outline">
                    {t('common.apply')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={delegations.data ?? []}
                pagination={delegations}
                rowActions={rowActions}
                emptyMessage={t('pages.hrDelegations.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="lg">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.hrDelegations.editTitle') : t('pages.hrDelegations.createTitle')}
                    </h3>
                    <AdminFormField label={t('pages.hrDelegations.fields.fromEmployee')} error={form.errors.from_employee_id}>
                        <Select
                            value={form.data.from_employee_id}
                            options={employeeOptions}
                            onChange={(v) => form.setData('from_employee_id', v ?? '')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrDelegations.fields.toEmployee')} error={form.errors.to_employee_id}>
                        <Select
                            value={form.data.to_employee_id}
                            options={employeeOptions}
                            onChange={(v) => form.setData('to_employee_id', v ?? '')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrDelegations.fields.scope')} error={form.errors.scope}>
                        <Select value={form.data.scope} options={scopeOptions} onChange={(v) => form.setData('scope', v ?? 'leave')} />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrDelegations.fields.effectiveFrom')} error={form.errors.effective_from}>
                        <input
                            type="date"
                            className="rp-form-input w-full"
                            value={form.data.effective_from}
                            onChange={(e) => form.setData('effective_from', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrDelegations.fields.effectiveTo')} error={form.errors.effective_to}>
                        <input
                            type="date"
                            className="rp-form-input w-full"
                            value={form.data.effective_to}
                            onChange={(e) => form.setData('effective_to', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrDelegations.fields.status')} error={form.errors.status}>
                        <Select value={form.data.status} options={formStatusOptions} onChange={(v) => form.setData('status', v ?? 'active')} />
                    </AdminFormField>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
