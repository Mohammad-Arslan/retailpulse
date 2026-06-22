import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Layers, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ customerGroups, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.customer-groups.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.customerGroups.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <Layers className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.slug}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'customers_count',
                accessorKey: 'customers_count',
                header: t('pages.customerGroups.columns.customers'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.customerGroups.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_active
                            ? t('pages.customerGroups.active')
                            : t('pages.customerGroups.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (group) => {
        const actions = [];
        if (can('customers.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.customer-groups.edit', group.id),
                permission: 'customers.update',
            });
        }
        if (can('customers.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.customer-groups.destroy', group.id),
                permission: 'customers.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteCustomerGroup', { name: group.name }),
                },
            });
        }
        return actions;
    };

    return (
        <>
            <Head title={t('nav.customerGroups')} />
            <PageHeader
                title={t('pages.customerGroups.title')}
                description={t('pages.customerGroups.description')}
            >
                {can('customers.create') && (
                    <Link href={route('admin.customer-groups.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('common.addCustomerGroup')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.customerGroups.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="is_active"
                    defaultValue={filters.is_active ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.customerGroups.allStatuses') },
                        { value: '1', label: t('pages.customerGroups.active') },
                        { value: '0', label: t('pages.customerGroups.inactive') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={customerGroups.data}
                pagination={customerGroups}
                filters={filters}
                indexRoute="admin.customer-groups.index"
                rowActions={rowActions}
                emptyMessage={t('pages.customerGroups.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
