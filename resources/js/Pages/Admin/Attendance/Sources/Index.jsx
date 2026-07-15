import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { Plug, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ sources, filters, drivers }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.attendance.sources.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const driverOptions = useMemo(
        () => [
            { value: '', label: t('pages.attendanceSources.allDrivers') },
            ...drivers.map((driver) => ({
                value: driver,
                label: t(`pages.attendanceSources.drivers.${driver}`, { defaultValue: driver }),
            })),
        ],
        [drivers, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.attendanceSources.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.attendanceSources.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.attendanceSources.columns.name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <Plug className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.name}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {t(`pages.attendanceSources.drivers.${row.original.driver}`, {
                                    defaultValue: row.original.driver,
                                })}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('pages.attendanceSources.columns.branch'),
                cell: ({ row }) => row.original.branch ?? t('pages.attendanceSources.allBranches'),
            },
            {
                id: 'status',
                header: t('pages.attendanceSources.columns.status'),
                cell: ({ row }) =>
                    t(`pages.attendanceSources.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.attendanceSources.indexTitle')} />
            <PageHeader
                title={t('pages.attendanceSources.indexTitle')}
                description={t('pages.attendanceSources.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.attendanceSources.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="driver"
                    defaultValue={filters.driver ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={driverOptions}
                />
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

            <DataTable columns={columns} data={sources.data ?? []} pagination={sources} />
        </>
    );
}

export default withAdminLayout(Index);
