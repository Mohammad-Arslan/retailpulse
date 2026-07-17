import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ runs }) {
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'scope',
                header: t('pages.leaveYearEndRuns.columns.scope'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <CalendarRange className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.legal_entity ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.employee ?? t('pages.leaveYearEndRuns.entityWide')}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'period',
                header: t('pages.leaveYearEndRuns.columns.period'),
                cell: ({ row }) => row.original.period_label,
            },
            {
                id: 'entitlementsProcessed',
                header: t('pages.leaveYearEndRuns.columns.entitlementsProcessed'),
                cell: ({ row }) => row.original.entitlements_processed,
            },
            {
                id: 'carriedForward',
                header: t('pages.leaveYearEndRuns.columns.carriedForward'),
                cell: ({ row }) => row.original.carried_forward,
            },
            {
                id: 'expired',
                header: t('pages.leaveYearEndRuns.columns.expired'),
                cell: ({ row }) => row.original.expired,
            },
            {
                id: 'encashed',
                header: t('pages.leaveYearEndRuns.columns.encashed'),
                cell: ({ row }) => row.original.encashed,
            },
            {
                id: 'executedAt',
                header: t('pages.leaveYearEndRuns.columns.executedAt'),
                cell: ({ row }) => row.original.executed_at ?? '—',
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.leaveYearEndRuns.indexTitle')} />
            <PageHeader
                title={t('pages.leaveYearEndRuns.indexTitle')}
                description={t('pages.leaveYearEndRuns.indexDescription')}
            />

            <DataTable
                columns={columns}
                data={runs.data ?? []}
                pagination={runs}
                emptyMessage={t('pages.leaveYearEndRuns.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
