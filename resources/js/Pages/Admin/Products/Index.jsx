import BulkSelectionBar from '@/Components/common/BulkSelectionBar';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCatalogBulkActions } from '@/Hooks/useCatalogBulkActions';
import { useRowSelection } from '@/Hooks/useRowSelection';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ products, filters, productTypes, categories, brands, canShowCost }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();
    const selection = useRowSelection();
    const pageRowIds = useMemo(
        () => (products.data ?? []).map((product) => product.id),
        [products.data],
    );
    const exportOptions = useMemo(
        () => ({
            filters: {
                search: filters.search ?? undefined,
                type: filters.type ?? undefined,
                category_id: filters.category_id ?? undefined,
                brand_id: filters.brand_id ?? undefined,
                is_active: filters.is_active ?? undefined,
            },
        }),
        [filters],
    );
    const bulkActions = useCatalogBulkActions({
        entityType: 'products',
        selectedArray: selection.selectedArray,
        onClear: selection.clearSelection,
        onJobStarted: trackJob,
        exportOptions,
    });

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.products.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(() => {
        const cols = [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.products.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        {row.original.primary_image ? (
                            <span className="flex h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-rp-border bg-rp-surface-inset">
                                <img
                                    src={
                                        row.original.primary_image.thumbnail_url
                                        ?? row.original.primary_image.url
                                    }
                                    alt={row.original.name}
                                    className="h-full w-full object-cover"
                                />
                            </span>
                        ) : (
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                                <Package className="h-4 w-4" />
                            </span>
                        )}
                        <div>
                            {can('products.view') ? (
                                <Link
                                    href={route('admin.products.show', row.original.id)}
                                    className="text-sm font-semibold text-rp-text hover:text-teal-600 dark:hover:text-teal-300"
                                >
                                    {row.original.name}
                                </Link>
                            ) : (
                                <div className="text-sm font-semibold text-rp-text">
                                    {row.original.name}
                                </div>
                            )}
                            <div className="text-xs text-rp-text-muted">
                                {row.original.default_variant?.sku ?? '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'type',
                accessorKey: 'type',
                header: t('pages.products.columns.type'),
                cell: ({ row }) => (
                    <span className="rounded-md bg-ink-100 px-2 py-0.5 text-xs font-medium capitalize dark:bg-ink-800">
                        {t(`pages.products.types.${row.original.type}`, {
                            defaultValue: row.original.type,
                        })}
                    </span>
                ),
            },
            {
                id: 'category',
                header: t('pages.products.columns.category'),
                cell: ({ row }) => row.original.category?.name ?? '—',
            },
        ];

        if (canShowCost) {
            cols.push({
                id: 'cost',
                header: t('pages.products.columns.cost'),
                cell: ({ row }) => row.original.default_variant?.cost_price ?? '—',
            });
        }

        cols.push(
            {
                id: 'sell_price',
                header: t('pages.products.columns.price'),
                cell: ({ row }) => row.original.default_variant?.sell_price ?? '—',
            },
            {
                id: 'variants_count',
                accessorKey: 'variants_count',
                header: t('pages.products.columns.variants'),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: t('pages.products.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs text-rp-text-secondary">
                        {row.original.is_active
                            ? t('pages.products.active')
                            : t('pages.products.inactive')}
                    </span>
                ),
            },
        );

        return cols;
    }, [t, canShowCost, can]);

    const rowActions = (product) => {
        const actions = [];
        if (can('products.view')) {
            actions.push({
                label: t('common.view'),
                type: 'view',
                href: route('admin.products.show', product.id),
                permission: 'products.view',
            });
        }
        if (can('products.update')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                href: route('admin.products.edit', product.id),
                permission: 'products.update',
            });
        }
        if (can('products.delete')) {
            actions.push({
                label: t('common.delete'),
                type: 'delete',
                method: 'delete',
                href: route('admin.products.destroy', product.id),
                permission: 'products.delete',
                variant: 'destructive',
                confirm: {
                    description: t('confirm.deleteProduct', { name: product.name }),
                },
            });
        }
        return actions;
    };

    return (
        <>
            <Head title={t('nav.products')} />
            <PageHeader
                title={t('pages.products.title')}
                description={t('pages.products.description')}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="products"
                        entityLabel={t('nav.products')}
                        showMatchField
                        exportOptions={exportOptions}
                        onJobStarted={trackJob}
                    />
                    {can('products.create') && (
                        <Link href={route('admin.products.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('common.addProduct')}
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
                        placeholder={t('pages.products.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="type"
                    defaultValue={filters.type ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.products.allTypes') },
                        ...productTypes.map((type) => ({
                            value: type,
                            label: t(`pages.products.types.${type}`, { defaultValue: type }),
                        })),
                    ]}
                />
                <Select
                    name="category_id"
                    defaultValue={filters.category_id ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.products.allCategories') },
                        ...mapToSelectOptions(categories),
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>
            <DataTable
                columns={columns}
                data={products.data}
                pagination={products}
                filters={filters}
                indexRoute="admin.products.index"
                rowActions={rowActions}
                emptyMessage={t('pages.products.empty')}
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
