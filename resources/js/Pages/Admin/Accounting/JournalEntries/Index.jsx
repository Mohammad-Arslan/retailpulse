import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { journalEntryStatusLabel, journalStatusBadgeClass } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, ScrollText, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ journalEntries, filters, journalStatuses = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.journal-entries.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...journalStatuses.map((status) => ({
                value: status,
                label: journalEntryStatusLabel(t, status),
            })),
        ],
        [journalStatuses, t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'journal_number',
                header: t('pages.accounting.journalEntries.columns.number'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <ScrollText className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.accounting.journal-entries.show', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.journal_number}
                            </Link>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.journal_date?.slice(0, 10)}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'description',
                header: t('pages.accounting.journalEntries.columns.description'),
                cell: ({ row }) => row.original.description ?? '—',
            },
            {
                id: 'branch',
                header: t('common.branch'),
                cell: ({ row }) => row.original.branch?.name ?? '—',
            },
            {
                id: 'total_debit',
                header: t('pages.accounting.journalEntries.columns.debit'),
                cell: ({ row }) => row.original.total_debit ?? '0.00',
            },
            {
                id: 'total_credit',
                header: t('pages.accounting.journalEntries.columns.credit'),
                cell: ({ row }) => row.original.total_credit ?? '0.00',
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${journalStatusBadgeClass(row.original.status)}`}
                    >
                        {journalEntryStatusLabel(t, row.original.status)}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (entry) => [
        {
            label: t('common.view'),
            type: 'view',
            href: route('admin.accounting.journal-entries.show', entry.id),
            permission: 'accounting.view',
        },
    ];

    return (
        <>
            <Head title={t('pages.accounting.journalEntries.title')} />
            <PageHeader
                title={t('pages.accounting.journalEntries.title')}
                description={t('pages.accounting.journalEntries.description')}
            >
                {can('accounting.create-journal') && (
                    <Link href={route('admin.accounting.journal-entries.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.accounting.journalEntries.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.journalEntries.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <div className="flex flex-col gap-1">
                    <label className="text-xs text-muted-foreground">
                        {t('pages.accounting.journalEntries.filters.dateFrom')}
                    </label>
                    <input
                        type="date"
                        name="from"
                        defaultValue={filters.from ?? ''}
                        className="rp-form-input h-9"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs text-muted-foreground">
                        {t('pages.accounting.journalEntries.filters.dateTo')}
                    </label>
                    <input
                        type="date"
                        name="to"
                        defaultValue={filters.to ?? ''}
                        className="rp-form-input h-9"
                    />
                </div>
                <Button type="submit" variant="outline" className="self-end">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={journalEntries.data}
                pagination={journalEntries}
                filters={filters}
                indexRoute="admin.accounting.journal-entries.index"
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.journalEntries.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
