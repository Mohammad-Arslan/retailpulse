import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { FolderTree, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ categories, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.categories.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.categories.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <FolderTree className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.slug}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'parent',
                header: t('pages.categories.columns.parent'),
                cell: ({ row }) => row.original.parent?.name ?? '—',
            },
            {
                id: 'products_count',
                accessorKey: 'products_count',
                header: t('pages.categories.columns.products'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.categories.columns.status'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs text-rp-text-secondary">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${row.original.is_active ? 'bg-teal-400' : 'bg-ink-300'}`}
                        />
                        {row.original.is_active
                            ? t('pages.categories.active')
                            : t('pages.categories.inactive')}
                    </div>
                ),
            },
        ],
        [t],
    );

    const rowActions = (category) => {
        const actions = [];
        if (can('products.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.categories.edit', category.id),
                permission: 'products.update',
            });
        }
        if (can('products.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.categories.destroy', category.id),
                permission: 'products.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteCategory', { name: category.name }),
                },
            });
        }
        return actions;
    };

    return (
        <>
            <Head title={t('nav.categories')} />
            <PageHeader
                title={t('pages.categories.title')}
                description={t('pages.categories.description')}
            >
                {can('products.create') && (
                    <Link href={route('admin.categories.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('common.addCategory')}
                    </Link>
                )}
            </PageHeader>
            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.categories.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>
            <DataTable
                columns={columns}
                data={categories.data}
                pagination={categories}
                filters={filters}
                indexRoute="admin.categories.index"
                rowActions={rowActions}
                emptyMessage={t('pages.categories.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
