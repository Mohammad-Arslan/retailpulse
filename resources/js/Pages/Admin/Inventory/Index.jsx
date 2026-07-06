import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import InventoryPageActions from '@/Components/admin/InventoryPageActions';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { Package, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ inventory, filters, warehouses }) {
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();
    const exportOptions = useMemo(
        () => ({
            filters: {
                search: filters.search ?? undefined,
                warehouse_id: filters.warehouse_id ?? undefined,
                availability: filters.availability ?? undefined,
                quarantine: filters.quarantine ?? undefined,
                batch: filters.batch ?? undefined,
                bin: filters.bin ?? undefined,
            },
        }),
        [filters],
    );

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.inventory.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'product',
                header: t('pages.inventory.columns.product'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-semibold text-rp-text">
                            {row.original.variant.product_name}
                        </div>
                        <div className="text-xs text-rp-text-muted">
                            {row.original.variant.name} · {row.original.variant.sku}
                        </div>
                    </div>
                ),
            },
            {
                id: 'warehouse',
                header: t('pages.inventory.columns.warehouse'),
                cell: ({ row }) => (
                    <div className="text-sm text-rp-text-secondary">
                        <div>{row.original.warehouse.name}</div>
                        <div className="text-xs text-rp-text-muted">
                            {row.original.warehouse.branch?.name}
                        </div>
                    </div>
                ),
            },
            {
                id: 'batch',
                header: t('pages.inventory.columns.batch'),
                cell: ({ row }) =>
                    row.original.batch ? (
                        <span className="text-xs text-rp-text-secondary">
                            {row.original.batch.batch_no}
                            {row.original.batch.expiry_date &&
                                ` · ${row.original.batch.expiry_date}`}
                        </span>
                    ) : (
                        <span className="text-xs text-rp-text-muted">—</span>
                    ),
            },
            {
                id: 'on_hand',
                accessorKey: 'quantity_on_hand',
                header: t('pages.inventory.columns.onHand'),
            },
            {
                id: 'reserved',
                accessorKey: 'quantity_reserved',
                header: t('pages.inventory.columns.reserved'),
            },
            {
                id: 'available',
                accessorKey: 'quantity_available',
                header: t('pages.inventory.columns.available'),
            },
            {
                id: 'bin',
                header: t('pages.inventory.columns.bin'),
                cell: ({ row }) => (
                    <span className="font-mono text-xs">
                        {row.original.bin?.bin_code ?? '—'}
                    </span>
                ),
            },
            {
                id: 'quarantine',
                header: t('pages.inventory.columns.quarantine'),
                cell: ({ row }) => row.original.quantity_in_quarantine ?? 0,
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('nav.inventory')} />
            <PageHeader
                title={t('pages.inventory.title')}
                description={t('pages.inventory.description')}
            >
                <InventoryPageActions exportOptions={exportOptions} onJobStarted={trackJob} />
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.inventory.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="warehouse_id"
                    defaultValue={filters.warehouse_id ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.inventory.allWarehouses') },
                        ...warehouses.map((warehouse) => ({
                            value: String(warehouse.id),
                            label: `${warehouse.name} (${warehouse.branch_name})`,
                        })),
                    ]}
                />
                <Select
                    name="availability"
                    defaultValue={filters.availability ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.inventory.filters.allAvailability') },
                        { value: 'in_stock', label: t('pages.inventory.filters.inStock') },
                        { value: 'out_of_stock', label: t('pages.inventory.filters.outOfStock') },
                        { value: 'low_stock', label: t('pages.inventory.filters.lowStock') },
                        { value: 'reserved', label: t('pages.inventory.filters.reserved') },
                    ]}
                />
                <Select
                    name="quarantine"
                    defaultValue={filters.quarantine ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.inventory.filters.allQuarantine') },
                        { value: 'yes', label: t('pages.inventory.filters.inQuarantine') },
                        { value: 'no', label: t('pages.inventory.filters.notInQuarantine') },
                    ]}
                />
                <Select
                    name="batch"
                    defaultValue={filters.batch ?? ''}
                    className="w-auto min-w-[9rem]"
                    options={[
                        { value: '', label: t('pages.inventory.filters.allBatches') },
                        { value: 'yes', label: t('pages.inventory.filters.withBatch') },
                        { value: 'no', label: t('pages.inventory.filters.withoutBatch') },
                    ]}
                />
                <Select
                    name="bin"
                    defaultValue={filters.bin ?? ''}
                    className="w-auto min-w-[9rem]"
                    options={[
                        { value: '', label: t('pages.inventory.filters.allBins') },
                        { value: 'assigned', label: t('pages.inventory.filters.binAssigned') },
                        { value: 'unassigned', label: t('pages.inventory.filters.binUnassigned') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            {inventory.data.length === 0 ? (
                <div className="rp-card flex flex-col items-center gap-3 py-16 text-center">
                    <Package className="h-10 w-10 text-rp-text-muted" />
                    <p className="text-sm text-rp-text-secondary">{t('pages.inventory.empty')}</p>
                </div>
            ) : (
                <DataTable
                    columns={columns}
                    data={inventory.data}
                    pagination={inventory}
                    filters={filters}
                    indexRoute="admin.inventory.index"
                    emptyMessage={t('pages.inventory.empty')}
                />
            )}
        </>
    );
}

export default withAdminLayout(Index);
