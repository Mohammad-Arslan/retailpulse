import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { accountingEventStatusLabel, eventStatusBadgeClass } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, RefreshCw, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ events, filters, statuses = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.events.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...statuses.map((status) => ({
                value: status,
                label: accountingEventStatusLabel(t, status),
            })),
        ],
        [statuses, t],
    );

    const confirm = useConfirm();

    const retryEvent = async (event) => {
        const confirmed = await confirm({
            title: t('pages.accounting.events.retry'),
            description: t('pages.accounting.events.confirmRetry'),
            confirmLabel: t('pages.accounting.events.retry'),
            variant: 'default',
        });

        if (confirmed) {
            router.post(route('admin.accounting.events.retry', event.id));
        }
    };

    const emptyMessage = useMemo(() => {
        if (filters.status) {
            return t('pages.accounting.events.emptyFiltered', {
                status: accountingEventStatusLabel(t, filters.status),
            });
        }

        return t('pages.accounting.events.empty');
    }, [filters.status, t]);

    const columns = useMemo(
        () => [
            {
                id: 'event_type',
                header: t('pages.accounting.events.columns.eventType'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300">
                            <AlertCircle className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="font-mono text-sm font-medium">{row.original.event_type}</div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.source_type} #{row.original.source_id}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'processing_status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${eventStatusBadgeClass(row.original.processing_status)}`}
                    >
                        {accountingEventStatusLabel(t, row.original.processing_status)}
                    </span>
                ),
            },
            {
                id: 'retry_count',
                header: t('pages.accounting.events.columns.retries'),
                cell: ({ row }) => row.original.retry_count ?? 0,
            },
            {
                id: 'journal_entry',
                header: t('pages.accounting.events.columns.journal'),
                cell: ({ row }) =>
                    row.original.journal_entry ? (
                        <Link
                            href={route('admin.accounting.journal-entries.show', row.original.journal_entry.id)}
                            className="text-teal-600 hover:underline"
                        >
                            {row.original.journal_entry.journal_number}
                        </Link>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'error_message',
                header: t('pages.accounting.events.columns.error'),
                cell: ({ row }) => (
                    <span className="line-clamp-2 max-w-md text-xs text-rose-600">
                        {row.original.error_message ?? '—'}
                    </span>
                ),
            },
            {
                id: 'processed_at',
                header: t('pages.accounting.events.columns.processedAt'),
                cell: ({ row }) =>
                    row.original.processed_at
                        ? new Date(row.original.processed_at).toLocaleString()
                        : '—',
            },
        ],
        [t],
    );

    const rowActions = (event) => {
        if (
            !can('accounting.view') ||
            event.processing_status !== 'failed'
        ) {
            return [];
        }

        return [
            {
                label: t('pages.accounting.events.retry'),
                type: 'edit',
                icon: RefreshCw,
                onClick: () => retryEvent(event),
                permission: 'accounting.view',
            },
        ];
    };

    return (
        <>
            <Head title={t('pages.accounting.events.title')} />
            <PageHeader
                title={t('pages.accounting.events.title')}
                description={t('pages.accounting.events.description')}
            />

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.events.searchPlaceholder')}
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

            <DataTable
                columns={columns}
                data={events.data}
                pagination={events}
                filters={filters}
                indexRoute="admin.accounting.events.index"
                rowActions={rowActions}
                emptyMessage={emptyMessage}
            />
        </>
    );
}

export default withAdminLayout(Index);
