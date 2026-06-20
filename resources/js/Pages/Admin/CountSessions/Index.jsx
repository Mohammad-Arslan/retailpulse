import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'text-amber-600',
    in_progress: 'text-sky-600',
    under_review: 'text-violet-600',
    approved: 'text-teal-600',
    posted: 'text-emerald-600',
};

function Index({ sessions, filters, warehouses }) {
    const can = useCan();
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'reference',
                accessorKey: 'reference_no',
                header: t('pages.countSessions.columns.reference'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.count-sessions.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.reference_no}
                    </Link>
                ),
            },
            {
                id: 'warehouse',
                header: t('pages.countSessions.columns.warehouse'),
                cell: ({ row }) => row.original.warehouse?.name ?? '—',
            },
            {
                id: 'scope',
                header: t('pages.countSessions.columns.scope'),
                cell: ({ row }) => t(`pages.countSessions.scope.${row.original.scope_type}`),
            },
            {
                id: 'status',
                header: t('pages.countSessions.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium capitalize ${statusClass[row.original.status] ?? ''}`}
                    >
                        {t(`pages.countSessions.status.${row.original.status}`)}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.countSessions.title')} />
            <PageHeader title={t('pages.countSessions.title')}>
                <div className="flex flex-wrap gap-2">
                    {can('inventory.cycle-count') && (
                        <Link
                            href={route('admin.count-schedule-rules.index')}
                            className="rp-btn-outline"
                        >
                            <CalendarClock className="mr-1 h-4 w-4" />
                            {t('pages.countScheduleRules.title')}
                        </Link>
                    )}
                    {can('inventory.cycle-count') && (
                        <Link href={route('admin.count-sessions.create')} className="rp-btn-primary">
                            <Plus className="mr-1 h-4 w-4" />
                            {t('pages.countSessions.newSession')}
                        </Link>
                    )}
                </div>
            </PageHeader>
            <DataTable columns={columns} data={sessions.data} pagination={sessions} />
        </>
    );
}

export default withAdminLayout(Index);
