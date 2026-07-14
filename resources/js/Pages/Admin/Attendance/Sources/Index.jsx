import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
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

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.attendanceSources.searchPlaceholder')}
                        className="rp-input w-full pl-9"
                    />
                </div>
                <Select name="driver" defaultValue={filters.driver ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.attendanceSources.allDrivers')}</option>
                    {drivers.map((driver) => (
                        <option key={driver} value={driver}>
                            {t(`pages.attendanceSources.drivers.${driver}`, { defaultValue: driver })}
                        </option>
                    ))}
                </Select>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.attendanceSources.allStatuses')}</option>
                    {['active', 'inactive'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.attendanceSources.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={sources.data ?? []} pagination={sources} />
        </>
    );
}

export default withAdminLayout(Index);
