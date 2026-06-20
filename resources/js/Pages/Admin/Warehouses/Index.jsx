import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Boxes, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ warehouses, filters, branches }) {
    const can = useCan();
    const { t } = useTranslation();
    const showBranchFilter = branches.length > 1;

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.warehouses.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.warehouses.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Boxes className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.code}</div>
                        </div>
                    </div>
                ),
            },
            ...(showBranchFilter
                ? [
                      {
                          id: 'branch',
                          accessorKey: 'branch.name',
                          header: t('pages.warehouses.columns.branch'),
                          cell: ({ row }) => (
                              <span className="text-sm text-rp-text-secondary">
                                  {row.original.branch?.name ?? '—'}
                              </span>
                          ),
                      },
                  ]
                : []),
            {
                id: 'is_default',
                accessorKey: 'is_default',
                header: t('pages.warehouses.columns.default'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_default
                            ? t('pages.warehouses.defaultYes')
                            : t('pages.warehouses.defaultNo')}
                    </span>
                ),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.warehouses.columns.status'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs text-rp-text-secondary">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${row.original.is_active ? 'bg-teal-400 shadow-[0_0_0_2px] shadow-teal-100' : 'bg-ink-300'}`}
                        />
                        {row.original.is_active
                            ? t('pages.warehouses.active')
                            : t('pages.warehouses.inactive')}
                    </div>
                ),
            },
        ],
        [showBranchFilter, t],
    );

    const rowActions = (warehouse) => {
        const actions = [];

        if (can('warehouses.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.warehouses.edit', warehouse.id),
                permission: 'warehouses.update',
            });
        }

        if (can('warehouses.deactivate') && warehouse.is_active) {
            actions.push({
                label: t('pages.warehouses.deactivate'),
                variant: 'destructive',
                permission: 'warehouses.deactivate',
                confirm: {
                    title: t('pages.warehouses.deactivateTitle'),
                    description: t('confirm.deactivateWarehouse', { name: warehouse.name }),
                    confirmLabel: t('pages.warehouses.deactivate'),
                },
                onClick: () => {
                    router.patch(route('admin.warehouses.deactivate', warehouse.id), {}, {
                        preserveScroll: true,
                    });
                },
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('nav.warehouses')} />

            <PageHeader
                title={t('pages.warehouses.title')}
                description={t('pages.warehouses.description')}
            >
                {can('warehouses.create') && (
                    <Link href={route('admin.warehouses.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('common.addWarehouse')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.warehouses.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                {showBranchFilter && (
                    <select
                        name="branch_id"
                        defaultValue={filters.branch_id ?? ''}
                        className="rp-form-input max-w-[200px]"
                    >
                        <option value="">{t('pages.warehouses.allBranches')}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                )}
                <select
                    name="is_active"
                    defaultValue={filters.is_active ?? ''}
                    className="rp-form-input max-w-[160px]"
                >
                    <option value="">{t('pages.warehouses.allStatuses')}</option>
                    <option value="1">{t('pages.warehouses.active')}</option>
                    <option value="0">{t('pages.warehouses.inactive')}</option>
                </select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={warehouses.data}
                pagination={warehouses}
                filters={filters}
                indexRoute="admin.warehouses.index"
                rowActions={rowActions}
                emptyMessage={t('pages.warehouses.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
