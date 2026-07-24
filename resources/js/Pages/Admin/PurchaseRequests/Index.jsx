import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { prStatusLabel } from '@/lib/procurementI18n';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-stone-100 text-stone-700 dark:bg-stone-500/20 dark:text-stone-300',
    submitted: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    approved: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    cancelled: 'bg-stone-100 text-stone-500 dark:bg-stone-500/20 dark:text-stone-400',
    converted: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
};

function Index({ requests, filters, statuses = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const statusFilterOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...statuses.map((status) => ({ value: status, label: prStatusLabel(t, status) })),
        ],
        [statuses, t],
    );

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.purchase-requests.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'reference',
                accessorKey: 'reference_no',
                header: t('pages.purchaseRequests.columns.reference'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.purchase-requests.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.reference_no}
                    </Link>
                ),
            },
            {
                id: 'status',
                header: t('pages.purchaseRequests.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusClass[row.original.status] ?? ''}`}
                    >
                        {prStatusLabel(t, row.original.status)}
                    </span>
                ),
            },
            { id: 'total', accessorKey: 'total', header: t('pages.purchaseRequests.columns.total') },
            {
                id: 'needed',
                header: t('pages.purchaseRequests.columns.neededBy'),
                cell: ({ row }) => row.original.needed_by ?? '—',
            },
            {
                id: 'lines',
                header: t('pages.purchaseRequests.columns.lines'),
                cell: ({ row }) => row.original.items_count ?? '—',
            },
            {
                id: 'created',
                header: t('pages.purchaseRequests.columns.created'),
                cell: ({ row }) =>
                    row.original.created_at ? new Date(row.original.created_at).toLocaleDateString() : '—',
            },
        ],
        [t],
    );

    const rowActions = useMemo(
        () => [
            {
                id: 'view',
                label: t('common.view'),
                onClick: (row) => router.visit(route('admin.purchase-requests.show', row.id)),
            },
            {
                id: 'submit',
                label: t('pages.purchaseRequests.actions.submit'),
                show: (row) => can('procurement.create') && row.status === 'draft',
                onClick: (row) => {
                    router.post(route('admin.purchase-requests.submit', row.id));
                },
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.purchaseRequests.title')} />
            <PageHeader title={t('pages.purchaseRequests.title')} description={t('pages.purchaseRequests.description')}>
                {can('procurement.create') && (
                    <Link href={route('admin.purchase-requests.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.purchaseRequests.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.purchaseRequests.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={statusFilterOptions}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.filter')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={requests.data ?? []}
                pagination={requests}
                rowActions={rowActions}
                emptyMessage={t('pages.purchaseRequests.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
