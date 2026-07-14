import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarRange, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ requests, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.leave.requests.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const approve = (id) => {
        router.post(route('admin.leave.requests.approve', id));
    };

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.leaveRequests.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <CalendarRange className="h-4 w-4" />
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
                id: 'leaveType',
                header: t('pages.leaveRequests.columns.leaveType'),
                cell: ({ row }) => row.original.leave_type ?? '—',
            },
            {
                id: 'dates',
                header: t('pages.leaveRequests.columns.dates'),
                cell: ({ row }) => `${row.original.start_date ?? '—'} → ${row.original.end_date ?? '—'}`,
            },
            {
                id: 'days',
                header: t('pages.leaveRequests.columns.days'),
                cell: ({ row }) => row.original.days ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leaveRequests.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leaveRequests.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) =>
                    row.original.status === 'pending' && can('leave.approve') ? (
                        <button
                            type="button"
                            onClick={() => approve(row.original.id)}
                            className="rp-btn-outline text-sm"
                        >
                            {t('pages.leaveRequests.approve')}
                        </button>
                    ) : (
                        '—'
                    ),
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.leaveRequests.indexTitle')} />
            <PageHeader
                title={t('pages.leaveRequests.indexTitle')}
                description={t('pages.leaveRequests.indexDescription')}
            >
                {can('leave.request') && (
                    <Link href={route('admin.leave.requests.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.leaveRequests.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.leaveRequests.searchPlaceholder')}
                        className="rp-input w-full pl-9"
                    />
                </div>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.leaveRequests.allStatuses')}</option>
                    {['pending', 'approved', 'rejected', 'cancelled'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.leaveRequests.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={requests.data ?? []} pagination={requests} />
        </>
    );
}

export default withAdminLayout(Index);
