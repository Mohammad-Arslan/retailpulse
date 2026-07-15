import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ types, filters }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.leave.types.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveTypes.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.leaveTypes.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.leaveTypes.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <CalendarDays className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.name ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.code ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'isPaid',
                header: t('pages.leaveTypes.columns.isPaid'),
                cell: ({ row }) =>
                    row.original.is_paid ? t('pages.leaveTypes.paid') : t('pages.leaveTypes.unpaid'),
            },
            {
                id: 'affectsPayroll',
                header: t('pages.leaveTypes.columns.affectsPayroll'),
                cell: ({ row }) =>
                    row.original.affects_payroll ? t('common.yes') : t('common.no'),
            },
            {
                id: 'deductionComponent',
                header: t('pages.leaveTypes.columns.deductionComponent'),
                cell: ({ row }) => row.original.payroll_deduction_component_code ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leaveTypes.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leaveTypes.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.leaveTypes.indexTitle')} />
            <PageHeader
                title={t('pages.leaveTypes.indexTitle')}
                description={t('pages.leaveTypes.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.leaveTypes.searchPlaceholder')}
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

            <DataTable columns={columns} data={types.data ?? []} pagination={types} />
        </>
    );
}

export default withAdminLayout(Index);
