import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, FileText, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { poStatusLabel } from '@/lib/procurementI18n';

const statusClass = {
    draft: 'bg-stone-100 text-stone-700 dark:bg-stone-500/20 dark:text-stone-300',
    submitted: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    approved: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    cancelled: 'bg-stone-100 text-stone-500 dark:bg-stone-500/20 dark:text-stone-400',
    closed: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
};

function Index({ orders, filters, statuses = [], suppliers = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const statusFilterOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...statuses.map((status) => ({ value: status, label: poStatusLabel(t, status) })),
        ],
        [statuses, t],
    );

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.purchase-orders.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'reference',
                accessorKey: 'reference_no',
                header: 'Reference',
                cell: ({ row }) => (
                    <Link
                        href={route('admin.purchase-orders.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.reference_no}
                    </Link>
                ),
            },
            {
                id: 'supplier',
                header: 'Supplier',
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
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusClass[row.original.status] ?? ''}`}
                    >
                        {poStatusLabel(t, row.original.status)}
                    </span>
                ),
            },
            { id: 'total', accessorKey: 'total', header: 'Total' },
            {
                id: 'expected',
                header: 'Expected',
                cell: ({ row }) => row.original.expected_delivery_date ?? '—',
            },
            {
                id: 'created',
                header: 'Created',
                cell: ({ row }) =>
                    row.original.created_at
                        ? new Date(row.original.created_at).toLocaleDateString()
                        : '—',
            },
        ],
        [t],
    );

    const rowActions = (order) => {
        const actions = [
            {
                label: t('common.view'),
                type: 'view',
                href: route('admin.purchase-orders.show', order.id),
                permission: 'procurement.view',
            },
            {
                label: t('pages.purchaseOrders.actions.printPdf'),
                type: 'view',
                icon: FileText,
                onClick: () => window.open(route('admin.purchase-orders.pdf', order.id), '_blank'),
                permission: 'procurement.view',
            },
        ];

        if (order.status === 'draft' && can('procurement.create')) {
            actions.push({
                label: t('pages.purchaseOrders.actions.submit'),
                type: 'edit',
                onClick: () => router.post(route('admin.purchase-orders.submit', order.id)),
                permission: 'procurement.create',
            });
        }

        if (order.status === 'submitted' && can('procurement.approve-po')) {
            actions.push({
                label: t('pages.purchaseOrders.actions.approve'),
                type: 'edit',
                href: route('admin.purchase-orders.show', order.id),
                permission: 'procurement.approve-po',
            });
        }

        if (order.status === 'approved' && can('procurement.receive-grn')) {
            actions.push({
                label: t('pages.purchaseOrders.actions.receiveGoods'),
                type: 'edit',
                href: route('admin.purchase-orders.show', order.id),
                permission: 'procurement.receive-grn',
            });
        }

        if (can('procurement.update') && ['draft', 'submitted', 'approved'].includes(order.status)) {
            actions.push({
                label: t('pages.purchaseOrders.actions.cancel'),
                type: 'delete',
                variant: 'destructive',
                onClick: () => {
                    if (confirm(`Cancel ${order.reference_no}?`)) {
                        router.post(route('admin.purchase-orders.cancel', order.id));
                    }
                },
                permission: 'procurement.update',
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('pages.purchaseOrders.title')} />
            <PageHeader title={t('pages.purchaseOrders.title')} description={t('pages.purchaseOrders.description')}>
                <div className="flex flex-wrap gap-2">
                    {can('procurement.view') && (
                        <Link href={route('admin.procurement.reports')} className="rp-btn-outline">
                            <ClipboardList className="h-4 w-4" />
                            Reports
                        </Link>
                    )}
                    {can('procurement.create') && (
                        <Link href={route('admin.purchase-orders.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('pages.purchaseOrders.createTitle')}
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
                        placeholder={t('pages.purchaseOrders.searchPlaceholder')}
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
                    options={[
                        { value: '', label: t('common.allSuppliers') },
                        ...mapToSelectOptions(suppliers),
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={orders.data}
                pagination={orders}
                filters={filters}
                indexRoute="admin.purchase-orders.index"
                rowActions={rowActions}
                emptyMessage={t('pages.purchaseOrders.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
