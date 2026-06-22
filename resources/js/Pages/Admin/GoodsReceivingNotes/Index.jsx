import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { grnStatusLabel } from '@/lib/procurementI18n';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-stone-100 text-stone-700 dark:bg-stone-500/20 dark:text-stone-300',
    posted: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
    cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
};

function Index({ grns, filters, statuses = [], suppliers = [] }) {
    const { t } = useTranslation();

    const statusFilterOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...statuses.map((status) => ({ value: status, label: grnStatusLabel(t, status) })),
        ],
        [statuses, t],
    );

    const supplierFilterOptions = useMemo(
        () => [
            { value: '', label: t('common.allSuppliers') },
            ...suppliers.map((s) => ({ value: String(s.id), label: s.name })),
        ],
        [suppliers, t],
    );

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.goods-receiving-notes.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'reference',
                accessorKey: 'reference_no',
                header: t('pages.goodsReceiving.columns.reference'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.goods-receiving-notes.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.reference_no}
                    </Link>
                ),
            },
            {
                id: 'supplier',
                header: t('pages.goodsReceiving.columns.supplier'),
                cell: ({ row }) =>
                    row.original.supplier ? (
                        <Link
                            href={route('admin.suppliers.show', row.original.supplier.id)}
                            className="text-sm hover:text-teal-600 hover:underline"
                        >
                            {row.original.supplier.name}
                        </Link>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'purchase_order',
                header: t('pages.goodsReceiving.columns.po'),
                cell: ({ row }) =>
                    row.original.purchase_order ? (
                        <Link
                            href={route('admin.purchase-orders.show', row.original.purchase_order.id)}
                            className="text-sm hover:text-teal-600 hover:underline"
                        >
                            {row.original.purchase_order.reference_no}
                        </Link>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'warehouse',
                accessorKey: 'warehouse',
                header: t('pages.goodsReceiving.columns.warehouse'),
            },
            {
                id: 'received',
                header: t('pages.goodsReceiving.columns.received'),
                cell: ({ row }) => row.original.received_at?.slice(0, 10) ?? '—',
            },
            {
                id: 'status',
                header: t('pages.goodsReceiving.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusClass[row.original.status] ?? ''}`}
                    >
                        {grnStatusLabel(t, row.original.status)}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.goodsReceiving.title')} />
            <PageHeader title={t('pages.goodsReceiving.title')} description={t('pages.goodsReceiving.description')} />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.goodsReceiving.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={statusFilterOptions}
                />
                <Select
                    name="supplier_id"
                    defaultValue={filters.supplier_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={supplierFilterOptions}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={grns.data ?? []}
                pagination={grns}
                filters={filters}
                indexRoute="admin.goods-receiving-notes.index"
                emptyMessage={t('pages.goodsReceiving.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
