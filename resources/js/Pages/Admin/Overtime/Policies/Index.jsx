import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function formatMinutes(minutes) {
    if (minutes == null) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins}m`;
    }

    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
}

function Index({ policies, filters }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.overtime.policies.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'scope',
                header: t('pages.overtimePolicies.columns.scope'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                            <Scale className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.legal_entity ?? row.original.branch ?? t('pages.overtimePolicies.scopeGlobal')}
                            </div>
                            {row.original.branch && row.original.legal_entity && (
                                <div className="text-xs text-rp-text-muted">{row.original.branch}</div>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                id: 'dailyThreshold',
                header: t('pages.overtimePolicies.columns.dailyThreshold'),
                cell: ({ row }) => formatMinutes(row.original.daily_threshold_minutes),
            },
            {
                id: 'weeklyThreshold',
                header: t('pages.overtimePolicies.columns.weeklyThreshold'),
                cell: ({ row }) =>
                    row.original.weekly_threshold_minutes != null
                        ? formatMinutes(row.original.weekly_threshold_minutes)
                        : '—',
            },
            {
                id: 'multipliers',
                header: t('pages.overtimePolicies.columns.multipliers'),
                cell: ({ row }) => (
                    <div className="space-y-1 text-sm">
                        {(row.original.multipliers ?? []).map((item) => (
                            <div key={item.id} className="text-rp-text-primary">
                                {t(`pages.overtimePolicies.dayTypes.${item.day_type}`, {
                                    defaultValue: item.day_type,
                                })}
                                : {item.multiplier}x
                            </div>
                        ))}
                        {(row.original.multipliers ?? []).length === 0 && '—'}
                    </div>
                ),
            },
            {
                id: 'effectiveDates',
                header: t('pages.overtimePolicies.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '—'}`,
            },
            {
                id: 'priority',
                header: t('pages.overtimePolicies.columns.priority'),
                cell: ({ row }) => row.original.priority ?? '—',
            },
            {
                id: 'status',
                header: t('pages.overtimePolicies.columns.status'),
                cell: ({ row }) =>
                    t(`pages.overtimePolicies.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.overtimePolicies.indexTitle')} />
            <PageHeader
                title={t('pages.overtimePolicies.indexTitle')}
                description={t('pages.overtimePolicies.indexDescription')}
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[160px]">
                    <option value="">{t('pages.overtimePolicies.allStatuses')}</option>
                    {['active', 'inactive'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.overtimePolicies.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={policies.data ?? []} pagination={policies} />
        </>
    );
}

export default withAdminLayout(Index);
