import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function BinReport({ inventory, filters, warehouses }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.inventory.bin-report'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'bin',
                header: t('pages.bins.fields.binCode'),
                cell: ({ row }) => (
                    <span className="font-mono text-sm">{row.original.bin?.bin_code ?? '—'}</span>
                ),
            },
            {
                id: 'product',
                header: t('pages.inventory.columns.product'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-semibold">{row.original.variant.product_name}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.variant.sku}</div>
                    </div>
                ),
            },
            {
                id: 'warehouse',
                header: t('pages.inventory.columns.warehouse'),
                cell: ({ row }) => row.original.warehouse.name,
            },
            {
                id: 'on_hand',
                header: t('pages.inventory.columns.onHand'),
                cell: ({ row }) => row.original.quantity_on_hand,
            },
            {
                id: 'available',
                header: t('pages.inventory.columns.available'),
                cell: ({ row }) => row.original.quantity_available,
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.bins.binReportTitle')} />
            <PageHeader title={t('pages.bins.binReportTitle')} />
            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="min-w-[200px]">
                    <label className="mb-1 block text-xs text-rp-text-muted">
                        {t('pages.inventory.columns.warehouse')}
                    </label>
                    <Select
                        name="warehouse_id"
                        defaultValue={filters.warehouse_id ?? ''}
                        options={[
                            { value: '', label: t('pages.inventory.allWarehouses') },
                            ...warehouses.map((w) => ({ value: String(w.id), label: w.name })),
                        ]}
                    />
                </div>
                <div className="relative min-w-[200px] flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('common.searchPlaceholder')}
                        className="rp-form-input w-full pl-9"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>
            <DataTable columns={columns} data={inventory.data} pagination={inventory} />
        </>
    );
}

export default withAdminLayout(BinReport);
