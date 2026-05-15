import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ branches, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.branches.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.branches.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Building2 className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.code}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'currency',
                accessorKey: 'currency',
                header: t('pages.branches.columns.currency'),
            },
            {
                id: 'timezone',
                accessorKey: 'timezone',
                header: t('pages.branches.columns.timezone'),
                cell: ({ row }) => (
                    <span className="text-sm text-rp-text-secondary">
                        {row.original.timezone}
                    </span>
                ),
            },
            {
                id: 'warehouses_count',
                accessorKey: 'warehouses_count',
                header: t('pages.branches.columns.warehouses'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.branches.columns.status'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs text-rp-text-secondary">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${row.original.is_active ? 'bg-teal-400 shadow-[0_0_0_2px] shadow-teal-100' : 'bg-ink-300'}`}
                        />
                        {row.original.is_active
                            ? t('pages.branches.active')
                            : t('pages.branches.inactive')}
                    </div>
                ),
            },
        ],
        [t],
    );

    const rowActions = (branch) => {
        const actions = [];

        if (can('branches.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.branches.edit', branch.id),
                permission: 'branches.update',
            });
        }

        if (can('branches.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.branches.destroy', branch.id),
                permission: 'branches.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteBranch', { name: branch.name }),
                },
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('nav.branches')} />

            <PageHeader
                title={t('pages.branches.title')}
                description={t('pages.branches.description')}
            >
                {can('branches.create') && (
                    <Link
                        href={route('admin.branches.create')}
                        className="rp-btn-primary"
                    >
                        <Plus className="h-4 w-4" />
                        {t('common.addBranch')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.branches.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={branches.data}
                pagination={branches}
                filters={filters}
                indexRoute="admin.branches.index"
                rowActions={rowActions}
                emptyMessage={t('pages.branches.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
