import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { FolderTree, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ categories, filters, parentOptions = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState(null);

    const createForm = useForm({
        code: '',
        name: '',
        parent_id: '',
        account_mapping_key: '',
        is_group: false,
        requires_receipt: false,
        status: 'active',
    });

    const editForm = useForm({
        code: '',
        name: '',
        parent_id: '',
        account_mapping_key: '',
        is_group: false,
        requires_receipt: false,
        status: 'active',
    });

    const parentSelectOptions = useMemo(
        () => [
            { value: '', label: t('pages.expenseCategories.fields.parent') },
            ...parentOptions.map((p) => ({ value: String(p.id), label: p.name })),
        ],
        [parentOptions, t],
    );

    const statusSelectOptions = useMemo(
        () =>
            ['active', 'inactive'].map((s) => ({
                value: s,
                label: t(`pages.expenseCategories.statuses.${s}`),
            })),
        [t],
    );

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.expenses.expense-categories.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const startEdit = (category) => {
        setEditingId(category.id);
        editForm.setData({
            code: category.code,
            name: category.name,
            parent_id: category.parent_id ? String(category.parent_id) : '',
            account_mapping_key: category.account_mapping_key ?? '',
            is_group: category.is_group,
            requires_receipt: category.requires_receipt,
            status: category.status,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.expenseCategories.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <FolderTree className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">{row.original.name}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.code}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'parent',
                header: t('pages.expenseCategories.columns.parent'),
                cell: ({ row }) => row.original.parent_name ?? '—',
            },
            {
                id: 'requires_receipt',
                header: t('pages.expenseCategories.columns.requiresReceipt'),
                cell: ({ row }) => (row.original.requires_receipt ? t('common.yes') : t('common.no')),
            },
            {
                id: 'status',
                header: t('pages.expenseCategories.columns.status'),
                cell: ({ row }) =>
                    t(`pages.expenseCategories.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) =>
                    can('expenses.manage-categories') ? (
                        <button
                            type="button"
                            className="text-xs text-teal-600 hover:underline"
                            onClick={() => startEdit(row.original)}
                        >
                            {t('common.edit')}
                        </button>
                    ) : null,
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.expenseCategories.indexTitle')} />
            <PageHeader
                title={t('pages.expenseCategories.indexTitle')}
                description={t('pages.expenseCategories.indexDescription')}
            />

            {can('expenses.manage-categories') && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        createForm.post(route('admin.expenses.expense-categories.store'), {
                            preserveScroll: true,
                            onSuccess: () => createForm.reset(),
                        });
                    }}
                    className="mb-6 grid max-w-4xl gap-3 rounded-lg border border-rp-border p-4 sm:grid-cols-2"
                >
                    <h2 className="text-sm font-semibold sm:col-span-2">{t('pages.expenseCategories.createTitle')}</h2>
                    <input
                        placeholder={t('pages.expenseCategories.fields.code')}
                        value={createForm.data.code}
                        onChange={(e) => createForm.setData('code', e.target.value)}
                        className="rp-form-input"
                    />
                    <input
                        placeholder={t('pages.expenseCategories.fields.name')}
                        value={createForm.data.name}
                        onChange={(e) => createForm.setData('name', e.target.value)}
                        className="rp-form-input"
                    />
                    <Select
                        value={createForm.data.parent_id}
                        onChange={(value) => createForm.setData('parent_id', value ?? '')}
                        options={parentSelectOptions}
                        isClearable
                    />
                    <input
                        placeholder={t('pages.expenseCategories.fields.accountMappingKey')}
                        value={createForm.data.account_mapping_key}
                        onChange={(e) => createForm.setData('account_mapping_key', e.target.value)}
                        className="rp-form-input"
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.data.is_group}
                            onChange={(e) => createForm.setData('is_group', e.target.checked)}
                        />
                        {t('pages.expenseCategories.fields.isGroup')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.data.requires_receipt}
                            onChange={(e) => createForm.setData('requires_receipt', e.target.checked)}
                        />
                        {t('pages.expenseCategories.fields.requiresReceipt')}
                    </label>
                    <Button type="submit" disabled={createForm.processing} className="w-fit sm:col-span-2">
                        {t('pages.expenseCategories.createSubmit')}
                    </Button>
                </form>
            )}

            {editingId !== null && can('expenses.manage-categories') && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        editForm.put(route('admin.expenses.expense-categories.update', editingId), {
                            preserveScroll: true,
                            onSuccess: () => setEditingId(null),
                        });
                    }}
                    className="mb-6 grid max-w-4xl gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4 dark:border-teal-800 dark:bg-teal-950/20 sm:grid-cols-2"
                >
                    <h2 className="text-sm font-semibold sm:col-span-2">{t('common.edit')}</h2>
                    <input
                        value={editForm.data.code}
                        onChange={(e) => editForm.setData('code', e.target.value)}
                        className="rp-form-input"
                    />
                    <input
                        value={editForm.data.name}
                        onChange={(e) => editForm.setData('name', e.target.value)}
                        className="rp-form-input"
                    />
                    <Select
                        value={editForm.data.parent_id}
                        onChange={(value) => editForm.setData('parent_id', value ?? '')}
                        options={parentSelectOptions}
                        isClearable
                    />
                    <input
                        value={editForm.data.account_mapping_key}
                        onChange={(e) => editForm.setData('account_mapping_key', e.target.value)}
                        className="rp-form-input"
                    />
                    <Select
                        value={editForm.data.status}
                        onChange={(value) => editForm.setData('status', value ?? 'active')}
                        options={statusSelectOptions}
                    />
                    <div className="flex gap-2 sm:col-span-2">
                        <Button type="submit" disabled={editForm.processing}>
                            {t('common.save')}
                        </Button>
                        <button type="button" className="rp-btn-outline" onClick={() => setEditingId(null)}>
                            {t('confirm.cancel')}
                        </button>
                    </div>
                </form>
            )}

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.expenseCategories.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable columns={columns} data={categories.data ?? []} pagination={categories} />
        </>
    );
}

export default withAdminLayout(Index);
