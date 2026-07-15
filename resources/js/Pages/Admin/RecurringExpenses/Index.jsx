import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ schedules, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.expenses.recurring-expenses.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.allStatuses') },
            ...['active', 'paused', 'cancelled'].map((status) => ({
                value: status,
                label: t(`pages.recurringExpenses.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'category',
                header: t('pages.recurringExpenses.columns.category'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <CalendarClock className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.category ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.branch ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'amount',
                header: t('pages.recurringExpenses.columns.amount'),
                cell: ({ row }) =>
                    `${row.original.currency_code} ${Number(row.original.amount).toFixed(2)}`,
            },
            {
                id: 'frequency',
                header: t('pages.recurringExpenses.columns.frequency'),
                cell: ({ row }) =>
                    t(`pages.recurringExpenses.frequencies.${row.original.frequency}`, {
                        defaultValue: row.original.frequency,
                    }),
            },
            {
                id: 'nextRun',
                header: t('pages.recurringExpenses.columns.nextRun'),
                cell: ({ row }) => row.original.next_run_at ?? '—',
            },
            {
                id: 'status',
                header: t('pages.recurringExpenses.columns.status'),
                cell: ({ row }) =>
                    t(`pages.recurringExpenses.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.recurringExpenses.indexTitle')} />
            <PageHeader
                title={t('pages.recurringExpenses.indexTitle')}
                description={t('pages.recurringExpenses.indexDescription')}
            >
                {can('expenses.manage-recurring') && (
                    <Link href={route('admin.expenses.recurring-expenses.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.recurringExpenses.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.recurringExpenses.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable columns={columns} data={schedules.data ?? []} pagination={schedules} />
        </>
    );
}

export default withAdminLayout(Index);
