import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ roles, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.roles.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'display_name',
                accessorKey: 'display_name',
                header: t('pages.roles.columns.displayName'),
                cell: ({ row }) => (
                    <div>
                        <span className="text-sm font-semibold text-rp-text">
                            {row.original.display_name || row.original.name}
                            {row.original.is_system && (
                                <span className="ms-2 text-[11px] text-rp-text-muted">
                                    ({t('pages.roles.systemBadge')})
                                </span>
                            )}
                        </span>
                        <div className="mt-0.5 font-mono text-[11px] text-rp-text-muted">
                            {row.original.name}
                        </div>
                    </div>
                ),
            },
            {
                id: 'description',
                accessorKey: 'description',
                header: t('pages.roles.columns.description'),
                cell: ({ row }) => (
                    <span className="text-sm text-rp-text-secondary">
                        {row.original.description || '—'}
                    </span>
                ),
            },
            {
                id: 'permissions_count',
                accessorKey: 'permissions_count',
                header: t('pages.roles.columns.permissions'),
                cell: ({ row }) => (
                    <span className="inline-flex rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-semibold text-teal-500">
                        {row.original.permissions_count}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (role) => {
        const actions = [];

        if (can('roles.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.roles.edit', role.id),
                permission: 'roles.update',
            });
        }

        if (can('roles.clone')) {
            actions.push({
                label: t('common.clone'),
                type: 'view',
                href: route('admin.roles.clone', role.id),
                permission: 'roles.clone',
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('nav.roles')} />

            <PageHeader
                title={t('pages.roles.title')}
                description={t('pages.roles.description')}
            >
                {can('roles.create') && (
                    <Link href={route('admin.roles.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('common.addRole')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.roles.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={roles.data}
                pagination={roles}
                filters={filters}
                indexRoute="admin.roles.index"
                rowActions={rowActions}
            />
        </>
    );
}

export default withAdminLayout(Index);
