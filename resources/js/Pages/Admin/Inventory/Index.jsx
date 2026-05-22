import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownToLine, Package, Search, SlidersHorizontal, Truck } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ inventory, filters, warehouses }) {
    const can = useCan();
    const { t } = useTranslation();

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
                <div className="flex flex-wrap gap-2">
                    {can('inventory.transfer') && (
                        <Link href={route('admin.stock-transfers.index')} className="rp-btn-outline">
                            <Truck className="h-4 w-4" />
                            {t('pages.inventory.transfers')}
                        </Link>
                    )}
                    {can('inventory.receive') && (
                        <Link href={route('admin.inventory.receive')} className="rp-btn-outline">
                            <ArrowDownToLine className="h-4 w-4" />
                            {t('pages.inventory.receive')}
                        </Link>
                    )}
                    {can('inventory.adjust') && (
                        <Link href={route('admin.inventory.adjust')} className="rp-btn-primary">
                            <SlidersHorizontal className="h-4 w-4" />
                            {t('pages.inventory.adjust')}
                        </Link>
                    )}
                </div>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset flex-1">
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
