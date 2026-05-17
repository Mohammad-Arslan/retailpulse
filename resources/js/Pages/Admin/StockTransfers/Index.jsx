import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'text-amber-600',
    shipped: 'text-sky-600',
    received: 'text-teal-600',
};

function Index({ transfers, filters, statuses }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.stock-transfers.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'reference',
                accessorKey: 'reference_no',
                header: t('pages.stockTransfers.columns.reference'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.stock-transfers.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.reference_no}
                    </Link>
                ),
            },
            {
                id: 'from',
                header: t('pages.stockTransfers.columns.from'),
                cell: ({ row }) => row.original.from_warehouse.name,
            },
            {
                id: 'to',
                header: t('pages.stockTransfers.columns.to'),
                cell: ({ row }) => row.original.to_warehouse.name,
            },
            {
                id: 'status',
                header: t('pages.stockTransfers.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium capitalize ${statusClass[row.original.status] ?? ''}`}
                    >
                        {t(`pages.stockTransfers.status.${row.original.status}`)}
                    </span>
                ),
            },
            {
                id: 'created',
                accessorKey: 'created_at',
                header: t('pages.stockTransfers.columns.created'),
                cell: ({ row }) =>
                    row.original.created_at
                        ? new Date(row.original.created_at).toLocaleDateString()
                        : '—',
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.stockTransfers.title')} />
            <PageHeader
                title={t('pages.stockTransfers.title')}
                description={t('pages.stockTransfers.description')}
            >
                <div className="flex gap-2">
                    <Link href={route('admin.inventory.index')} className="rp-btn-outline">
                        {t('nav.inventory')}
                    </Link>
                    {can('inventory.transfer') && (
                        <Link href={route('admin.stock-transfers.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('pages.stockTransfers.create')}
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
                        placeholder={t('pages.stockTransfers.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.stockTransfers.allStatuses') },
                        ...statuses.map((status) => ({
                            value: status,
                            label: t(`pages.stockTransfers.status.${status}`),
                        })),
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={transfers.data}
                pagination={transfers}
                filters={filters}
                indexRoute="admin.stock-transfers.index"
                emptyMessage={t('pages.stockTransfers.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
