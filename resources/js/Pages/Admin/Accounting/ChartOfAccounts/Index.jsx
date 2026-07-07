import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    accountTypeBadgeClass,
    chartOfAccountTypeLabel,
} from '@/lib/accountingI18n';
import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

function emptyAccountForm() {
    return {
        code: '',
        name: '',
        type: 'asset',
        parent_id: '',
        is_group: false,
        is_postable: true,
        branch_id: '',
        currency_code: '',
        status: 'active',
    };
}

function Index({ accounts, filters, parentOptions, accountTypes = ACCOUNT_TYPES, branches }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm(emptyAccountForm());

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.chart-of-accounts.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyAccountForm());
        setModalOpen(true);
    };

    const openEdit = (account) => {
        setEditing(account);
        form.clearErrors();
        form.setData({
            code: account.code ?? '',
            name: account.name ?? '',
            type: account.type ?? 'asset',
            parent_id: account.parent_id ? String(account.parent_id) : '',
            is_group: Boolean(account.is_group),
            is_postable: account.is_postable !== false,
            branch_id: account.branch_id ? String(account.branch_id) : '',
            currency_code: account.currency_code ?? '',
            status: account.status ?? 'active',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const submitAccount = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => closeModal() };

        if (editing) {
            form.put(route('admin.accounting.chart-of-accounts.update', editing.id), options);
        } else {
            form.post(route('admin.accounting.chart-of-accounts.store'), options);
        }
    };

    const typeOptions = useMemo(
        () =>
            accountTypes.map((type) => ({
                value: type,
                label: chartOfAccountTypeLabel(t, type),
            })),
        [accountTypes, t],
    );

    const parentSelectOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.chartOfAccounts.noParent') },
            ...parentOptions.map((p) => ({
                value: String(p.id),
                label: `${p.code} — ${p.name}`,
            })),
        ],
        [parentOptions, t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'code',
                accessorKey: 'code',
                header: t('pages.accounting.chartOfAccounts.columns.code'),
                cell: ({ row }) => (
                    <div
                        className="flex items-center gap-3"
                        style={{ paddingLeft: `${(row.original.depth ?? 0) * 16}px` }}
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <BookOpen className="h-4 w-4" />
                        </span>
                        <div>
                            <button
                                type="button"
                                className="text-left text-sm font-semibold text-teal-600 hover:underline"
                                onClick={() => can('accounting.manage-coa') && openEdit(row.original)}
                            >
                                {row.original.code}
                            </button>
                            <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'type',
                header: t('pages.accounting.chartOfAccounts.columns.type'),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${accountTypeBadgeClass(row.original.type)}`}
                    >
                        {chartOfAccountTypeLabel(t, row.original.type)}
                    </span>
                ),
            },
            {
                id: 'is_group',
                header: t('pages.accounting.chartOfAccounts.columns.group'),
                cell: ({ row }) =>
                    row.original.is_group ? t('common.yes') : t('common.no'),
            },
            {
                id: 'is_postable',
                header: t('pages.accounting.chartOfAccounts.columns.postable'),
                cell: ({ row }) =>
                    row.original.is_postable ? t('common.yes') : t('common.no'),
            },
            {
                id: 'branch',
                header: t('common.branch'),
                cell: ({ row }) => row.original.branch?.name ?? t('pages.accounting.chartOfAccounts.allBranches'),
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
        [can, t],
    );

    const rowActions = (account) => {
        if (!can('accounting.manage-coa')) {
            return [];
        }

        return [
            {
                label: t('common.edit'),
                type: 'edit',
                onClick: () => openEdit(account),
                permission: 'accounting.manage-coa',
            },
        ];
    };

    return (
        <>
            <Head title={t('pages.accounting.chartOfAccounts.title')} />
            <PageHeader
                title={t('pages.accounting.chartOfAccounts.title')}
                description={t('pages.accounting.chartOfAccounts.description')}
            >
                {can('accounting.manage-coa') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.accounting.chartOfAccounts.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.chartOfAccounts.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="type"
                    defaultValue={filters.type ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.accounting.chartOfAccounts.allTypes') },
                        ...typeOptions,
                    ]}
                />
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('common.allStatuses') },
                        { value: 'active', label: t('common.active') },
                        { value: 'inactive', label: t('common.inactive') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={accounts?.data ?? []}
                pagination={accounts}
                filters={filters}
                indexRoute="admin.accounting.chart-of-accounts.index"
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.chartOfAccounts.empty')}
            />

            <Modal show={modalOpen} onClose={closeModal} maxWidth="2xl">
                <form onSubmit={submitAccount} className="p-6">
                    <h2 className="text-lg font-semibold text-ink-900 dark:text-white">
                        {editing
                            ? t('pages.accounting.chartOfAccounts.editTitle', { code: editing.code })
                            : t('pages.accounting.chartOfAccounts.createTitle')}
                    </h2>
                    <p className="mt-1 text-sm text-rp-text-muted">
                        {t('pages.accounting.chartOfAccounts.formDescription')}
                    </p>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.chartOfAccounts.fields.code')}
                            id="code"
                            error={form.errors.code}
                        >
                            <input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.chartOfAccounts.fields.name')}
                            id="name"
                            error={form.errors.name}
                        >
                            <input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.chartOfAccounts.fields.type')}
                            id="type"
                            error={form.errors.type}
                        >
                            <Select
                                id="type"
                                value={form.data.type}
                                onChange={(value) => form.setData('type', value ?? '')}
                                options={typeOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.chartOfAccounts.fields.parent')}
                            id="parent_id"
                            error={form.errors.parent_id}
                        >
                            <Select
                                id="parent_id"
                                value={form.data.parent_id}
                                onChange={(value) => form.setData('parent_id', value ?? '')}
                                options={parentSelectOptions}
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
                                options={[
                                    { value: '', label: t('common.allBranches') },
                                    ...branches.map((b) => ({
                                        value: String(b.id),
                                        label: b.name,
                                    })),
                                ]}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.chartOfAccounts.fields.currency')}
                            id="currency_code"
                            error={form.errors.currency_code}
                        >
                            <input
                                id="currency_code"
                                value={form.data.currency_code}
                                onChange={(e) => form.setData('currency_code', e.target.value)}
                                className="rp-form-input"
                                placeholder={t('common.selectCurrency')}
                            />
                        </AdminFormField>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_group}
                                onChange={(e) => form.setData('is_group', e.target.checked)}
                            />
                            {t('pages.accounting.chartOfAccounts.fields.isGroup')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_postable}
                                onChange={(e) => form.setData('is_postable', e.target.checked)}
                            />
                            {t('pages.accounting.chartOfAccounts.fields.isPostable')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.status === 'active'}
                                onChange={(e) =>
                                    form.setData('status', e.target.checked ? 'active' : 'inactive')
                                }
                            />
                            {t('common.active')}
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={closeModal}>
                            {t('common.back')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {editing
                                ? t('pages.accounting.chartOfAccounts.updateSubmit')
                                : t('pages.accounting.chartOfAccounts.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
