import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import RolePill from '@/Components/common/RolePill';
import UserAvatar from '@/Components/common/UserAvatar';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ users, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.users.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'User',
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <UserAvatar name={row.original.name} />
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.email}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'roles',
                header: 'Roles',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1.5">
                        {row.original.roles?.length ? (
                            row.original.roles.map((role) => (
                                <RolePill
                                    key={role.id ?? role.name}
                                    name={role.name}
                                    displayName={role.display_name}
                                />
                            ))
                        ) : (
                            <span className="text-xs text-ink-300">—</span>
                        )}
                    </div>
                ),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs text-rp-text-secondary">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${row.original.is_active ? 'bg-teal-400 shadow-[0_0_0_2px] shadow-teal-100' : 'bg-ink-300'}`}
                        />
                        {row.original.is_active ? 'Active' : 'Inactive'}
                    </div>
                ),
            },
            {
                id: 'employee',
                header: t('pages.users.columns.linkedEmployee'),
                enableSorting: false,
                cell: ({ row }) => {
                    const employee = row.original.employee;
                    if (!employee) {
                        return <span className="text-xs text-rp-text-muted">{t('pages.users.fields.noLinkedEmployee')}</span>;
                    }
                    return (
                        <div className="text-xs text-rp-text-secondary">
                            {employee.employee_code} — {employee.first_name} {employee.last_name}
                        </div>
                    );
                },
            },
        ],
        [t],
    );

    const rowActions = (user) => {
        const actions = [];

        if (can('users.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.users.edit', user.id),
                permission: 'users.update',
            });
        }

        if (can('users.delete')) {
            actions.push({
                label: t('common.deactivate'),
                type: 'delete',
                method: 'delete',
                href: route('admin.users.destroy', user.id),
                permission: 'users.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deactivateUser', { name: user.name }),
                },
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('nav.users')} />

            <PageHeader
                title={t('pages.users.title')}
                description={t('pages.users.description')}
            >
                {can('users.create') && (
                    <Link href={route('admin.users.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('common.addUser')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('common.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={users.data}
                pagination={users}
                filters={filters}
                indexRoute="admin.users.index"
                rowActions={rowActions}
                emptyMessage={t('pages.users.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
