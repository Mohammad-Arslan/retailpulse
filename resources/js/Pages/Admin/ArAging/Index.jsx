import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ aging, filters, customerGroups = [], currency = 'PKR', snapshotDate }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.ar-aging.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'customer',
                header: t('pages.arAging.columns.customer'),
                cell: ({ row }) => (
                    <div>
                        <Link
                            href={route('admin.customers.show', row.original.customer_id)}
                            className="text-sm font-semibold text-teal-600 hover:underline"
                        >
                            {row.original.customer?.name ?? `#${row.original.customer_id}`}
                        </Link>
                        {row.original.customer?.phone && (
                            <div className="text-xs text-rp-text-muted">{row.original.customer.phone}</div>
                        )}
                    </div>
                ),
            },
            {
                id: 'customer_group',
                header: t('pages.arAging.columns.group'),
                cell: ({ row }) => row.original.customer?.customer_group?.name ?? '—',
            },
            {
                id: 'current',
                header: t('pages.arAging.columns.current'),
                cell: ({ row }) => `${row.original.current} ${currency}`,
            },
            {
                id: 'bucket_30',
                header: t('pages.arAging.columns.bucket30'),
                cell: ({ row }) => `${row.original.bucket_30} ${currency}`,
            },
            {
                id: 'bucket_60',
                header: t('pages.arAging.columns.bucket60'),
                cell: ({ row }) => `${row.original.bucket_60} ${currency}`,
            },
            {
                id: 'bucket_90',
                header: t('pages.arAging.columns.bucket90'),
                cell: ({ row }) => `${row.original.bucket_90} ${currency}`,
            },
            {
                id: 'bucket_over_90',
                header: t('pages.arAging.columns.bucketOver90'),
                cell: ({ row }) => `${row.original.bucket_over_90} ${currency}`,
            },
            {
                id: 'total_outstanding',
                header: t('pages.arAging.columns.total'),
                cell: ({ row }) => (
                    <span className="font-semibold text-amber-600">
                        {row.original.total_outstanding} {currency}
                    </span>
                ),
            },
        ],
        [currency, t],
    );

    return (
        <>
            <Head title={t('nav.arAging')} />
            <PageHeader
                title={t('pages.arAging.title')}
                description={t('pages.arAging.description', {
                    date: snapshotDate
                        ? new Date(snapshotDate).toLocaleDateString()
                        : t('pages.arAging.latestSnapshot'),
                })}
            />

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.arAging.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="customer_group_id"
                    defaultValue={filters.customer_group_id ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('pages.arAging.allGroups') },
                        ...mapToSelectOptions(customerGroups),
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={aging.data}
                pagination={aging}
                filters={filters}
                indexRoute="admin.ar-aging.index"
                emptyMessage={t('pages.arAging.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
