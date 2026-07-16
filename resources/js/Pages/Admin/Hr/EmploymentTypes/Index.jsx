import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { IdCard, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        legal_entity_id: 'global',
        code: '',
        name: '',
        status: 'active',
    };
}

function Index({ employmentTypes, filters, legalEntities = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm());

    const entityOptions = useMemo(
        () => [
            { value: 'global', label: t('pages.hrEmploymentTypes.globalScope') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            { value: 'active', label: t('pages.hrEmploymentTypes.statuses.active') },
            { value: 'inactive', label: t('pages.hrEmploymentTypes.statuses.inactive') },
        ],
        [t],
    );

    const formStatusOptions = useMemo(
        () => [
            { value: 'active', label: t('pages.hrEmploymentTypes.statuses.active') },
            { value: 'inactive', label: t('pages.hrEmploymentTypes.statuses.inactive') },
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
            legal_entity_id: row.legal_entity_id ? String(row.legal_entity_id) : 'global',
            code: row.code,
            name: row.name,
            status: row.status,
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            ...form.data,
            legal_entity_id: form.data.legal_entity_id === 'global' ? null : form.data.legal_entity_id,
        };

        if (editing) {
            form.transform(() => payload).put(route('admin.hr.employment-types.update', editing.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    form.transform((data) => data);
                },
            });
        } else {
            form.transform(() => payload).post(route('admin.hr.employment-types.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    form.transform((data) => data);
                },
            });
        }
    };

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.hr.employment-types.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.hrEmploymentTypes.fields.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <IdCard className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">{row.original.name}</div>
                            <div className="font-mono text-xs text-rp-text-muted">{row.original.code}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'legal_entity_name',
                header: t('pages.hrEmploymentTypes.fields.legalEntity'),
                cell: ({ row }) => row.original.legal_entity_name ?? t('pages.hrEmploymentTypes.globalScope'),
            },
            {
                id: 'status',
                header: t('pages.hrEmploymentTypes.fields.status'),
                cell: ({ row }) => t(`pages.hrEmploymentTypes.statuses.${row.original.status}`),
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('hr.manage-settings')) {
            return [];
        }

        return [{ label: t('common.edit'), type: 'edit', onClick: () => openEdit(row) }];
    };

    return (
        <>
            <Head title={t('pages.hrEmploymentTypes.indexTitle')} />
            <PageHeader
                title={t('pages.hrEmploymentTypes.indexTitle')}
                description={t('pages.hrEmploymentTypes.indexDescription')}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="employment-types"
                        entityLabel={t('pages.hrEmploymentTypes.indexTitle')}
                        exportOptions={{
                            filters: {
                                search: filters.search ?? undefined,
                                status: filters.status ?? undefined,
                            },
                        }}
                        onJobStarted={trackJob}
                    />
                    {can('hr.manage-settings') && (
                        <Button variant="brand" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            {t('pages.hrEmploymentTypes.createTitle')}
                        </Button>
                    )}
                </div>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.hrEmploymentTypes.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select name="status" defaultValue={filters.status ?? ''} className="w-auto min-w-[10rem]" options={statusOptions} />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={employmentTypes.data ?? []}
                pagination={employmentTypes}
                rowActions={rowActions}
                emptyMessage={t('pages.hrEmploymentTypes.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="md">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing ? t('pages.hrEmploymentTypes.editTitle') : t('pages.hrEmploymentTypes.createTitle')}
                    </h3>
                    <AdminFormField label={t('pages.hrEmploymentTypes.fields.legalEntity')} error={form.errors.legal_entity_id}>
                        <Select
                            value={form.data.legal_entity_id}
                            options={entityOptions}
                            onChange={(v) => form.setData('legal_entity_id', v ?? 'global')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrEmploymentTypes.fields.code')} error={form.errors.code} required>
                        <input
                            className="rp-form-input w-full font-mono"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toLowerCase())}
                            disabled={!!editing}
                            placeholder={t('pages.hrEmploymentTypes.fields.codePlaceholder')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrEmploymentTypes.fields.name')} error={form.errors.name} required>
                        <input
                            className="rp-form-input w-full"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder={t('pages.hrEmploymentTypes.fields.namePlaceholder')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrEmploymentTypes.fields.status')} error={form.errors.status}>
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
                            {editing
                                ? t('common.save')
                                : t('pages.hrEmploymentTypes.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
