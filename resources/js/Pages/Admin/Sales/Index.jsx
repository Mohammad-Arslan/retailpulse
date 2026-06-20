import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    completed: 'text-teal-600',
    pending_payment: 'text-amber-600',
    partially_paid: 'text-sky-600',
    voided: 'text-muted-foreground',
    refunded: 'text-rose-600',
};

function Index({ sales, filters, statuses }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.sales.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'invoice',
                header: t('pages.sales.columns.invoice'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.sales.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.invoice?.number ?? `#${row.original.id}`}
                    </Link>
                ),
            },
            {
                id: 'status',
                header: t('pages.sales.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium capitalize ${statusClass[row.original.status] ?? ''}`}
                    >
                        {row.original.status?.replace('_', ' ')}
                    </span>
                ),
            },
            {
                id: 'customer',
                header: t('pages.sales.columns.customer'),
                cell: ({ row }) => row.original.customer?.name ?? '—',
            },
            {
                id: 'cashier',
                header: t('pages.sales.columns.cashier'),
                cell: ({ row }) => row.original.cashier?.name ?? '—',
            },
            {
                id: 'total',
                header: t('pages.sales.columns.total'),
                cell: ({ row }) => `${row.original.grand_total} ${row.original.currency}`,
            },
            {
                id: 'completed',
                accessorKey: 'completed_at',
                header: t('pages.sales.columns.completed'),
                cell: ({ row }) =>
                    row.original.completed_at
                        ? new Date(row.original.completed_at).toLocaleString()
                        : '—',
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.sales.title')} />
            <PageHeader title={t('pages.sales.title')} description={t('pages.sales.description')} />

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="min-w-[220px] flex-1">
                    <label className="mb-1 block text-sm font-medium">{t('common.search')}</label>
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            name="search"
                            defaultValue={filters.search ?? ''}
                            placeholder={t('pages.sales.searchPlaceholder')}
                            className="rp-form-input pl-9"
                        />
                    </div>
                </div>
                <div className="w-44">
                    <label className="mb-1 block text-sm font-medium">{t('pages.sales.columns.status')}</label>
                    <Select
                        name="status"
                        options={[
                            { value: '', label: 'All' },
                            ...statuses.map((status) => ({
                                value: status,
                                label: status.replace('_', ' '),
                            })),
                        ]}
                        value={filters.status ?? ''}
                        onChange={(value) =>
                            router.get(
                                route('admin.sales.index'),
                                { ...filters, status: value || undefined },
                                { preserveState: true },
                            )
                        }
                    />
                </div>
                <label className="flex items-center gap-2 pb-2 text-sm">
                    <input
                        type="checkbox"
                        name="include_historical"
                        value="1"
                        defaultChecked={Boolean(filters.include_historical)}
                        className="accent-teal-500"
                    />
                    {t('pages.sales.includeHistorical')}
                </label>
                <button type="submit" className="rp-btn-primary">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={sales.data} pagination={sales} />
        </>
    );
}

export default withAdminLayout(Index);
