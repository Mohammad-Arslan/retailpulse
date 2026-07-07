import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { mappingKeyLabel } from '@/lib/accountingI18n';
import { Head, router, useForm } from '@inertiajs/react';
import { Link2, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyMappingForm() {
    return {
        mapping_key: '',
        account_id: '',
        branch_id: '',
        priority: '100',
        status: 'active',
    };
}

function Index({ mappings, filters, mappingKeys = [], accounts = [], branches = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm(emptyMappingForm());

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.account-mappings.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyMappingForm());
        setModalOpen(true);
    };

    const openEdit = (mapping) => {
        setEditing(mapping);
        form.clearErrors();
        form.setData({
            mapping_key: mapping.mapping_key ?? '',
            account_id: mapping.account_id ? String(mapping.account_id) : '',
            branch_id: mapping.branch_id ? String(mapping.branch_id) : '',
            priority: String(mapping.priority ?? 100),
            status: mapping.status ?? 'active',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const submitMapping = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => closeModal() };

        if (editing) {
            form.put(route('admin.accounting.account-mappings.update', editing.id), options);
        } else {
            form.post(route('admin.accounting.account-mappings.store'), options);
        }
    };

    const mappingKeyOptions = useMemo(
        () =>
            mappingKeys.map((key) => ({
                value: key,
                label: mappingKeyLabel(t, key),
            })),
        [mappingKeys, t],
    );

    const accountOptions = useMemo(
        () =>
            accounts.map((a) => ({
                value: String(a.id),
                label: `${a.code} — ${a.name}`,
            })),
        [accounts],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('common.allBranches') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'mapping_key',
                header: t('pages.accounting.accountMappings.columns.key'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Link2 className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {mappingKeyLabel(t, row.original.mapping_key)}
                            </div>
                            <div className="font-mono text-xs text-rp-text-muted">
                                {row.original.mapping_key}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'account',
                header: t('pages.accounting.accountMappings.columns.account'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-medium">{row.original.account?.name ?? '—'}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.account?.code}</div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('common.branch'),
                cell: ({ row }) => row.original.branch?.name ?? t('common.allBranches'),
            },
            {
                id: 'priority',
                header: t('pages.accounting.accountMappings.columns.priority'),
                cell: ({ row }) => row.original.priority ?? '—',
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${row.original.status === 'active' ? 'text-teal-600' : 'text-rp-text-muted'}`}
                    >
                        {row.original.status === 'active' ? t('common.active') : t('common.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (mapping) => {
        if (!can('accounting.manage-mappings')) {
            return [];
        }

        return [
            {
                label: t('common.edit'),
                type: 'edit',
                onClick: () => openEdit(mapping),
                permission: 'accounting.manage-mappings',
            },
        ];
    };

    return (
        <>
            <Head title={t('pages.accounting.accountMappings.title')} />
            <PageHeader
                title={t('pages.accounting.accountMappings.title')}
                description={t('pages.accounting.accountMappings.description')}
            >
                {can('accounting.manage-mappings') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.accounting.accountMappings.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.accountMappings.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="mapping_key"
                    defaultValue={filters.mapping_key ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={[
                        { value: '', label: t('pages.accounting.accountMappings.allKeys') },
                        ...mappingKeyOptions,
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={mappings.data}
                pagination={mappings}
                filters={filters}
                indexRoute="admin.accounting.account-mappings.index"
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.accountMappings.empty')}
            />

            <Modal show={modalOpen} onClose={closeModal} maxWidth="lg">
                <form onSubmit={submitMapping} className="p-6">
                    <h2 className="text-lg font-semibold">
                        {editing
                            ? t('pages.accounting.accountMappings.editTitle')
                            : t('pages.accounting.accountMappings.createTitle')}
                    </h2>

                    <div className="mt-5 space-y-4">
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.key')}
                            id="mapping_key"
                            error={form.errors.mapping_key}
                        >
                            <Select
                                id="mapping_key"
                                value={form.data.mapping_key}
                                onChange={(value) => form.setData('mapping_key', value ?? '')}
                                options={[
                                    { value: '', label: t('pages.accounting.accountMappings.selectKey') },
                                    ...mappingKeyOptions,
                                ]}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.account')}
                            id="account_id"
                            error={form.errors.account_id}
                        >
                            <Select
                                id="account_id"
                                value={form.data.account_id}
                                onChange={(value) => form.setData('account_id', value ?? '')}
                                options={[
                                    { value: '', label: t('pages.accounting.accountMappings.selectAccount') },
                                    ...accountOptions,
                                ]}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.branch')}
                            id="branch_id"
                            error={form.errors.branch_id}
                        >
                            <Select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                                options={branchOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.priority')}
                            id="priority"
                            error={form.errors.priority}
                        >
                            <input
                                id="priority"
                                type="number"
                                min="1"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={closeModal}>
                            {t('common.back')}
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
