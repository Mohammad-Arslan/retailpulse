import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router } from '@inertiajs/react';
import { Search, Timer } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ records, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.overtime.records.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.overtimeRecords.allStatuses') },
            ...['pending', 'approved', 'rejected'].map((status) => ({
                value: status,
                label: t(`pages.overtimeRecords.statuses.${status}`),
            })),
        ],
        [t],
    );

    const approve = (id) => {
        router.post(route('admin.overtime.records.approve', id));
    };

    const reject = (id) => {
        router.post(route('admin.overtime.records.reject', id));
    };

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.overtimeRecords.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-300">
                            <Timer className="h-4 w-4" />
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
                id: 'date',
                header: t('pages.overtimeRecords.columns.date'),
                cell: ({ row }) => row.original.date ?? '—',
            },
            {
                id: 'regularMinutes',
                header: t('pages.overtimeRecords.columns.regularMinutes'),
                cell: ({ row }) => row.original.regular_minutes ?? '—',
            },
            {
                id: 'overtimeMinutes',
                header: t('pages.overtimeRecords.columns.overtimeMinutes'),
                cell: ({ row }) => row.original.overtime_minutes ?? '—',
            },
            {
                id: 'dayType',
                header: t('pages.overtimeRecords.columns.dayType'),
                cell: ({ row }) =>
                    t(`pages.overtimePolicies.dayTypes.${row.original.day_type}`, {
                        defaultValue: row.original.day_type,
                    }),
            },
            {
                id: 'multiplier',
                header: t('pages.overtimeRecords.columns.multiplier'),
                cell: ({ row }) => row.original.resolved_multiplier ?? '—',
            },
            {
                id: 'payUnits',
                header: t('pages.overtimeRecords.columns.payUnits'),
                cell: ({ row }) => row.original.pay_units ?? '—',
            },
            {
                id: 'status',
                header: t('pages.overtimeRecords.columns.status'),
                cell: ({ row }) =>
                    t(`pages.overtimeRecords.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) =>
                    row.original.status === 'pending' && can('overtime.approve') ? (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => approve(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.overtimeRecords.approve')}
                            </button>
                            <button
                                type="button"
                                onClick={() => reject(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.overtimeRecords.reject')}
                            </button>
                        </div>
                    ) : (
                        '—'
                    ),
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.overtimeRecords.indexTitle')} />
            <PageHeader
                title={t('pages.overtimeRecords.indexTitle')}
                description={t('pages.overtimeRecords.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.overtimeRecords.searchPlaceholder')}
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
                data={records.data ?? []}
                pagination={records}
                emptyMessage={t('pages.overtimeRecords.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
