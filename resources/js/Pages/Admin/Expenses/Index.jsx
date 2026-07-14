import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Receipt, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ expenses, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.expenses.expenses.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'number',
                header: t('pages.expenses.columns.number'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Receipt className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.expenses.expenses.show', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.expense_number}
                            </Link>
                            <div className="text-xs text-rp-text-muted">{row.original.category ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('pages.expenses.columns.branch'),
                cell: ({ row }) => row.original.branch ?? '—',
            },
            {
                id: 'amount',
                header: t('pages.expenses.columns.amount'),
                cell: ({ row }) =>
                    `${row.original.currency_code} ${Number(row.original.amount).toFixed(2)}`,
            },
            {
                id: 'date',
                header: t('pages.expenses.columns.date'),
                cell: ({ row }) => row.original.expense_date ?? '—',
            },
            {
                id: 'status',
                header: t('pages.expenses.columns.status'),
                cell: ({ row }) => (
                    <span className="text-xs font-medium text-rp-text-secondary">
                        {t(`pages.expenses.statuses.${row.original.status}`, {
                            defaultValue: row.original.status,
                        })}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.expenses.indexTitle')} />
            <PageHeader
                title={t('pages.expenses.indexTitle')}
                description={t('pages.expenses.indexDescription')}
            >
                {can('expenses.create') && (
                    <Link href={route('admin.expenses.expenses.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.expenses.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.expenses.searchPlaceholder')}
                        className="rp-input w-full pl-9"
                    />
                </div>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.expenses.allStatuses')}</option>
                    {['draft', 'pending_approval', 'posted'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.expenses.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={expenses.data ?? []} pagination={expenses} />
        </>
    );
}

export default withAdminLayout(Index);
