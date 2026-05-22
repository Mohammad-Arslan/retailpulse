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
import { Plus, Search, Tag } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ brands, filters }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();
    const selection = useRowSelection();
    const pageRowIds = useMemo(
        () => (brands.data ?? []).map((brand) => brand.id),
        [brands.data],
    );
    const bulkActions = useCatalogBulkActions({
        entityType: 'brands',
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
        router.get(route('admin.brands.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.brands.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Tag className="h-4 w-4" />
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
                id: 'products_count',
                accessorKey: 'products_count',
                header: t('pages.brands.columns.products'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.brands.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_active
                            ? t('pages.brands.active')
                            : t('pages.brands.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (brand) => {
        const actions = [];
        if (can('products.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.brands.edit', brand.id),
                permission: 'products.update',
            });
        }
        if (can('products.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.brands.destroy', brand.id),
                permission: 'products.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteBrand', { name: brand.name }),
                },
            });
        }
        return actions;
    };

    return (
        <>
            <Head title={t('nav.brands')} />
            <PageHeader title={t('pages.brands.title')} description={t('pages.brands.description')}>
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="brands"
                        entityLabel={t('nav.brands')}
                        exportOptions={{
                            filters: {
                                search: filters.search ?? undefined,
                                is_active: filters.is_active ?? undefined,
                            },
                        }}
                        onJobStarted={trackJob}
                    />
                    {can('products.create') && (
                        <Link href={route('admin.brands.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('common.addBrand')}
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
                        placeholder={t('pages.brands.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>
            <DataTable
                columns={columns}
                data={brands.data}
                pagination={brands}
                filters={filters}
                indexRoute="admin.brands.index"
                rowActions={rowActions}
                emptyMessage={t('pages.brands.empty')}
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
