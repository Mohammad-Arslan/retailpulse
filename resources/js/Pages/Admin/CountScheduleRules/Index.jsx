import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function Index({ rules }) {
    const can = useCan();
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
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
                id: 'frequency',
                header: t('pages.countScheduleRules.columns.frequency'),
                cell: ({ row }) => {
                    const freq = t(`pages.countScheduleRules.frequency.${row.original.frequency}`);
                    if (row.original.frequency === 'weekly' && row.original.day_of_week !== null) {
                        return `${freq} · ${dayNames[row.original.day_of_week]}`;
                    }
                    if (row.original.frequency === 'monthly' && row.original.day_of_month !== null) {
                        return `${freq} · ${t('pages.countScheduleRules.dayOfMonth', { day: row.original.day_of_month })}`;
                    }

                    return freq;
                },
            },
            {
                id: 'status',
                header: t('pages.countSessions.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${row.original.is_active ? 'text-teal-600' : 'text-rp-text-muted'}`}
                    >
                        {row.original.is_active
                            ? t('pages.countScheduleRules.active')
                            : t('pages.countScheduleRules.inactive')}
                    </span>
                ),
            },
            {
                id: 'last_run',
                header: t('pages.countScheduleRules.columns.lastRun'),
                cell: ({ row }) =>
                    row.original.last_run_at
                        ? new Date(row.original.last_run_at).toLocaleString()
                        : '—',
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <Link
                        href={route('admin.count-schedule-rules.edit', row.original.id)}
                        className="text-sm text-teal-600 hover:underline"
                    >
                        {t('common.edit')}
                    </Link>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.countScheduleRules.title')} />
            <PageHeader title={t('pages.countScheduleRules.title')}>
                <div className="flex gap-2">
                    <Link href={route('admin.count-sessions.index')} className="rp-btn-outline">
                        {t('pages.countScheduleRules.backToCounts')}
                    </Link>
                    {can('inventory.cycle-count') && (
                        <Link href={route('admin.count-schedule-rules.create')} className="rp-btn-primary">
                            <Plus className="mr-1 h-4 w-4" />
                            {t('pages.countScheduleRules.create')}
                        </Link>
                    )}
                </div>
            </PageHeader>
            <p className="mb-4 flex items-center gap-2 text-sm text-rp-text-muted">
                <CalendarClock className="h-4 w-4" />
                {t('pages.countScheduleRules.description')}
            </p>
            <DataTable columns={columns} data={rules.data} pagination={rules} />
        </>
    );
}

export default withAdminLayout(Index);
