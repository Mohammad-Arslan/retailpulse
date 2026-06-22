import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search, UserRound } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ suppliers, filters }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.suppliers.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.suppliers.columns.supplier'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <UserRound className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.suppliers.show', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.name}
                            </Link>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.code}
                                {[row.original.phone, row.original.email].filter(Boolean).length > 0 &&
                                    ` · ${[row.original.phone, row.original.email].filter(Boolean).join(' · ')}`}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'balance',
                accessorKey: 'balance',
                header: t('pages.suppliers.columns.balance'),
                cell: ({ row }) => (
                    <span className={Number(row.original.balance) > 0 ? 'font-medium text-amber-700' : ''}>
                        {row.original.balance}
                    </span>
                ),
            },
            {
                id: 'purchase_orders_count',
                header: t('pages.suppliers.columns.pos'),
                cell: ({ row }) => row.original.purchase_orders_count ?? 0,
            },
            {
                id: 'active',
                header: t('pages.suppliers.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${row.original.is_active ? 'text-teal-600' : 'text-rp-text-muted'}`}
                    >
                        {row.original.is_active ? t('common.active') : t('common.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (supplier) => {
        const actions = [
            {
                label: t('common.view'),
                type: 'view',
                href: route('admin.suppliers.show', supplier.id),
                permission: 'procurement.view',
            },
        ];

        if (can('procurement.manage-suppliers')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.suppliers.edit', supplier.id),
                permission: 'procurement.manage-suppliers',
            });
        }

        actions.push({
            label: t('pages.suppliers.actions.statementPdf'),
            type: 'view',
            icon: FileText,
            onClick: () => window.open(route('admin.suppliers.statement.pdf', supplier.id), '_blank'),
            permission: 'procurement.view',
        });

        if (can('procurement.view')) {
            actions.push({
                label: t('pages.suppliers.actions.purchaseOrders'),
                type: 'view',
                href: route('admin.purchase-orders.index', { supplier_id: supplier.id }),
                permission: 'procurement.view',
            });
        }

        if (can('procurement.create')) {
            actions.push({
                label: t('pages.suppliers.actions.newPurchaseOrder'),
                type: 'edit',
                href: route('admin.purchase-orders.create', { supplier_id: supplier.id }),
                permission: 'procurement.create',
            });
        }

        if (can('procurement.manage-suppliers') && supplier.is_active) {
            actions.push({
                label: t('pages.suppliers.actions.emailStatement'),
                type: 'edit',
                onClick: () => router.post(route('admin.suppliers.send-statement', supplier.id)),
                permission: 'procurement.manage-suppliers',
            });
            actions.push({
                label: t('pages.suppliers.actions.deactivate'),
                type: 'delete',
                variant: 'destructive',
                onClick: () => {
                    if (confirm(t('pages.suppliers.actions.deactivateConfirm', { name: supplier.name }))) {
                        router.post(route('admin.suppliers.deactivate', supplier.id));
                    }
                },
                permission: 'procurement.manage-suppliers',
            });
        }

        if (can('procurement.manage-suppliers')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.suppliers.destroy', supplier.id),
                permission: 'procurement.manage-suppliers',
                variant: 'destructive',
                confirm: { description: t('pages.suppliers.actions.deleteConfirm', { name: supplier.name }) },
            });
        }

        return actions;
    };

    const exportFilters = {
        search: filters.search ?? undefined,
        is_active: filters.is_active ?? undefined,
    };

    return (
        <>
            <Head title={t('pages.suppliers.title')} />
            <PageHeader title={t('pages.suppliers.title')} description={t('pages.suppliers.description')}>
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="suppliers"
                        entityLabel={t('pages.suppliers.title')}
                        exportOptions={{ filters: exportFilters }}
                        onJobStarted={trackJob}
                    />
                    {can('procurement.manage-suppliers') && (
                        <Link href={route('admin.suppliers.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('pages.suppliers.createTitle')}
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
                        placeholder={t('pages.suppliers.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="is_active"
                    defaultValue={filters.is_active ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('common.allStatuses') },
                        { value: '1', label: t('common.active') },
                        { value: '0', label: t('common.inactive') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={suppliers.data}
                pagination={suppliers}
                filters={filters}
                indexRoute="admin.suppliers.index"
                rowActions={rowActions}
                emptyMessage={t('pages.suppliers.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
