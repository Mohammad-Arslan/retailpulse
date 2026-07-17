import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Coins, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ encashments, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.leave.encashments.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const approve = (id) => {
        router.post(route('admin.leave.encashments.approve', id));
    };

    const reject = (id) => {
        router.post(route('admin.leave.encashments.reject', id));
    };

    const cancel = (id) => {
        router.post(route('admin.leave.encashments.cancel', id));
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveEncashments.allStatuses') },
            ...['pending', 'approved', 'rejected', 'cancelled'].map((status) => ({
                value: status,
                label: t(`pages.leaveEncashments.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.leaveEncashments.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                            <Coins className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.employee ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.employee_code ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'leaveType',
                header: t('pages.leaveEncashments.columns.leaveType'),
                cell: ({ row }) => row.original.leave_type ?? '—',
            },
            {
                id: 'days',
                header: t('pages.leaveEncashments.columns.days'),
                cell: ({ row }) => row.original.days ?? '—',
            },
            {
                id: 'createdAt',
                header: t('pages.leaveEncashments.columns.createdAt'),
                cell: ({ row }) => row.original.created_at ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leaveEncashments.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leaveEncashments.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) => {
                    if (row.original.status === 'pending' && can('leave.approve-encashment')) {
                        return (
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => approve(row.original.id)}
                                    className="rp-btn-outline text-sm"
                                >
                                    {t('pages.leaveEncashments.approve')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => reject(row.original.id)}
                                    className="rp-btn-outline text-sm"
                                >
                                    {t('pages.leaveEncashments.reject')}
                                </button>
                            </div>
                        );
                    }
                    if (row.original.status === 'pending' && can('leave.request-encashment')) {
                        return (
                            <button
                                type="button"
                                onClick={() => cancel(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.leaveEncashments.cancel')}
                            </button>
                        );
                    }
                    return '—';
                },
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.leaveEncashments.indexTitle')} />
            <PageHeader
                title={t('pages.leaveEncashments.indexTitle')}
                description={t('pages.leaveEncashments.indexDescription')}
            >
                {can('leave.request-encashment') && (
                    <Button variant="brand" asChild>
                        <Link
                            href={route('admin.leave.encashments.create')}
                            className="inline-flex items-center gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            {t('pages.leaveEncashments.createTitle')}
                        </Link>
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.leaveEncashments.searchPlaceholder')}
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
                data={encashments.data ?? []}
                pagination={encashments}
                emptyMessage={t('pages.leaveEncashments.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
