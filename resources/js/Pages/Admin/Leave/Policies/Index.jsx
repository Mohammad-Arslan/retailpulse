import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ policies, filters }) {
    const { t } = useTranslation();
    const can = useCan();

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.leave.policies.index'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leavePolicies.allStatuses') },
            { value: 'active', label: t('pages.leavePolicies.statuses.active') },
            { value: 'inactive', label: t('pages.leavePolicies.statuses.inactive') },
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'leaveType',
                header: t('pages.leavePolicies.columns.leaveType'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                            <CalendarRange className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">{row.original.leave_type}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.leave_type_code}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'legalEntity',
                header: t('pages.leavePolicies.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? t('pages.leavePolicies.scopeDefault'),
            },
            {
                id: 'accrualMethod',
                header: t('pages.leavePolicies.columns.accrualMethod'),
                cell: ({ row }) =>
                    t(`pages.leavePolicies.accrualMethods.${row.original.accrual_method}`, {
                        defaultValue: row.original.accrual_method,
                    }),
            },
            {
                id: 'excludePublicHolidays',
                header: t('pages.leavePolicies.columns.excludePublicHolidays'),
                cell: ({ row }) => (
                    <ExcludeHolidayToggle row={row.original} canEdit={can('leave.manage-policies')} />
                ),
            },
            {
                id: 'effectiveFrom',
                header: t('pages.leavePolicies.columns.effectiveFrom'),
                cell: ({ row }) => row.original.effective_from ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leavePolicies.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leavePolicies.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.leavePolicies.indexTitle')} />
            <PageHeader title={t('pages.leavePolicies.indexTitle')} description={t('pages.leavePolicies.indexDescription')} />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select name="status" defaultValue={filters.status ?? ''} className="w-auto min-w-[10rem]" options={statusOptions} />
                <Button type="submit" variant="outline">
                    {t('common.apply')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={policies.data ?? []}
                pagination={policies}
                emptyMessage={t('pages.leavePolicies.empty')}
            />
        </>
    );
}

function ExcludeHolidayToggle({ row, canEdit }) {
    const { t } = useTranslation();
    const [enabled, setEnabled] = useState(row.exclude_public_holidays);
    const [processing, setProcessing] = useState(false);

    const toggle = () => {
        if (!canEdit || processing) {
            return;
        }
        const next = !enabled;
        setProcessing(true);
        router.put(
            route('admin.leave.policies.update', row.id),
            { exclude_public_holidays: next },
            {
                preserveScroll: true,
                onSuccess: () => setEnabled(next),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <button
            type="button"
            disabled={!canEdit || processing}
            onClick={toggle}
            className={`rounded-full px-3 py-1 text-xs font-medium ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                    : 'bg-rp-surface-muted text-rp-text-muted'
            }`}
        >
            {enabled ? t('common.yes') : t('common.no')}
        </button>
    );
}

export default withAdminLayout(Index);
