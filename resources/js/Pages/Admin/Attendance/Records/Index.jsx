import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Clock, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ records, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.attendance.records.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const formatMinutes = (minutes) => {
        if (minutes == null) {
            return '—';
        }

        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;

        return `${hours}h ${remainder}m`;
    };

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.attendanceRecords.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                            <Clock className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.employee ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.employee_code ?? '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('pages.attendanceRecords.columns.branch'),
                cell: ({ row }) => row.original.branch ?? '—',
            },
            {
                id: 'source',
                header: t('pages.attendanceRecords.columns.source'),
                cell: ({ row }) => row.original.source ?? '—',
            },
            {
                id: 'clockIn',
                header: t('pages.attendanceRecords.columns.clockIn'),
                cell: ({ row }) => row.original.clock_in ?? '—',
            },
            {
                id: 'clockOut',
                header: t('pages.attendanceRecords.columns.clockOut'),
                cell: ({ row }) => row.original.clock_out ?? '—',
            },
            {
                id: 'workedMinutes',
                header: t('pages.attendanceRecords.columns.workedMinutes'),
                cell: ({ row }) => formatMinutes(row.original.worked_minutes),
            },
            {
                id: 'status',
                header: t('pages.attendanceRecords.columns.status'),
                cell: ({ row }) =>
                    t(`pages.attendanceRecords.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.attendanceRecords.indexTitle')} />
            <PageHeader
                title={t('pages.attendanceRecords.indexTitle')}
                description={t('pages.attendanceRecords.indexDescription')}
            >
                {can('attendance.record') && (
                    <Link href={route('admin.attendance.records.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.attendanceRecords.manualClockTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.attendanceRecords.searchPlaceholder')}
                        className="rp-input w-full pl-9"
                    />
                </div>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.attendanceRecords.allStatuses')}</option>
                    {['open', 'closed', 'adjusted'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.attendanceRecords.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={records.data ?? []} pagination={records} />
        </>
    );
}

export default withAdminLayout(Index);
