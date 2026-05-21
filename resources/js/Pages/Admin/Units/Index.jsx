import BulkSelectionBar from '@/Components/common/BulkSelectionBar';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCatalogBulkActions } from '@/Hooks/useCatalogBulkActions';
import { useRowSelection } from '@/Hooks/useRowSelection';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Scale, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ units, filters }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();
    const selection = useRowSelection();
    const pageRowIds = useMemo(
        () => (units.data ?? []).map((unit) => unit.id),
        [units.data],
    );
    const bulkActions = useCatalogBulkActions({
        entityType: 'units',
        selectedArray: selection.selectedArray,
        onClear: selection.clearSelection,
        onJobStarted: trackJob,
        exportOptions: {
            filters: {
                search: filters.search ?? undefined,
                is_active: filters.is_active ?? undefined,
            },
        },
    });

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.units.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.units.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Scale className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.abbreviation}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'products_count',
                accessorKey: 'products_count',
                header: t('pages.units.columns.products'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.units.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_active
                            ? t('pages.units.active')
                            : t('pages.units.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (unit) => {
        const actions = [];
        if (can('products.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.units.edit', unit.id),
                permission: 'products.update',
            });
        }
        if (can('products.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.units.destroy', unit.id),
                permission: 'products.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteUnit', { name: unit.name }),
                },
            });
        }
        return actions;
    };

    return (
        <>
            <Head title={t('nav.units')} />
            <PageHeader title={t('pages.units.title')} description={t('pages.units.description')}>
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="units"
                        entityLabel={t('nav.units')}
                        exportOptions={{
                            filters: {
                                search: filters.search ?? undefined,
                                is_active: filters.is_active ?? undefined,
                            },
                        }}
                        onJobStarted={trackJob}
                    />
                    {can('products.create') && (
                        <Link href={route('admin.units.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('common.addUnit')}
                        </Link>
                    )}
                </div>
            </PageHeader>
            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.units.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>
            <DataTable
                columns={columns}
                data={units.data}
                pagination={units}
                filters={filters}
                indexRoute="admin.units.index"
                rowActions={rowActions}
                emptyMessage={t('pages.units.empty')}
                selectable
                selectedIds={selection.selectedIds}
                onToggleRow={selection.toggleRow}
                onToggleAll={() => selection.toggleAll(pageRowIds)}
            />
            <BulkSelectionBar
                selectedCount={selection.selectedCount}
                onClear={selection.clearSelection}
                actions={bulkActions}
            />
        </>
    );
}

export default withAdminLayout(Index);
