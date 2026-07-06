import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, UserRound } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({
    customers,
    filters,
    loyaltyTiers = [],
    customerGroups = [],
    canViewCredit = false,
    legacyLoyaltyEnabled = false,
}) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.customers.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.customers.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <UserRound className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.customers.show', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.name}
                            </Link>
                            <div className="text-xs text-rp-text-muted">
                                {[row.original.phone, row.original.email].filter(Boolean).join(' · ') || '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            ...(legacyLoyaltyEnabled
                ? [
                      {
                          id: 'loyalty_tier',
                          header: t('pages.customers.columns.tier'),
                          cell: ({ row }) => row.original.loyalty_tier?.name ?? '—',
                      },
                  ]
                : []),
            {
                id: 'customer_group',
                header: t('pages.customers.columns.group'),
                cell: ({ row }) => row.original.customer_group?.name ?? '—',
            },
            ...(canViewCredit
                ? [
                      {
                          id: 'credit_limit',
                          header: t('pages.customers.columns.creditLimit'),
                          cell: ({ row }) =>
                              row.original.credit_limit != null ? row.original.credit_limit : '—',
                      },
                  ]
                : []),
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.customers.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_active
                            ? t('pages.customers.active')
                            : t('pages.customers.inactive')}
                    </span>
                ),
            },
        ],
        [t, canViewCredit, legacyLoyaltyEnabled],
    );

    const rowActions = (customer) => {
        const actions = [];

        actions.push({
            label: t('common.view'),
            type: 'view',
            href: route('admin.customers.show', customer.id),
            permission: 'customers.view',
        });

        if (can('customers.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.customers.edit', customer.id),
                permission: 'customers.update',
            });
        }

        if (can('customers.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.customers.destroy', customer.id),
                permission: 'customers.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteCustomer', { name: customer.name }),
                },
            });
        }

        return actions;
    };

    const exportFilters = {
        search: filters.search ?? undefined,
        loyalty_tier_id: legacyLoyaltyEnabled ? (filters.loyalty_tier_id ?? undefined) : undefined,
        customer_group_id: filters.customer_group_id ?? undefined,
        is_active: filters.is_active ?? undefined,
    };

    return (
        <>
            <Head title={t('nav.customers')} />
            <PageHeader
                title={t('pages.customers.title')}
                description={t('pages.customers.description')}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="customers"
                        entityLabel={t('nav.customers')}
                        exportOptions={{ filters: exportFilters }}
                        onJobStarted={trackJob}
                    />
                    {can('customers.create') && (
                        <Link href={route('admin.customers.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('common.addCustomer')}
                        </Link>
                    )}
                </div>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.customers.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                {legacyLoyaltyEnabled && (
                    <Select
                        name="loyalty_tier_id"
                        defaultValue={filters.loyalty_tier_id ?? ''}
                        className="w-auto min-w-[10rem]"
                        options={[
                            { value: '', label: t('pages.customers.allTiers') },
                            ...mapToSelectOptions(loyaltyTiers),
                        ]}
                    />
                )}
                <Select
                    name="customer_group_id"
                    defaultValue={filters.customer_group_id ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.customers.allGroups') },
                        ...mapToSelectOptions(customerGroups),
                    ]}
                />
                <Select
                    name="is_active"
                    defaultValue={filters.is_active ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.customers.allStatuses') },
                        { value: '1', label: t('pages.customers.active') },
                        { value: '0', label: t('pages.customers.inactive') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={customers.data}
                pagination={customers}
                filters={filters}
                indexRoute="admin.customers.index"
                rowActions={rowActions}
                emptyMessage={t('pages.customers.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
