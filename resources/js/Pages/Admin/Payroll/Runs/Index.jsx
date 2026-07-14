import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ runs, entities, filters }) {
    const { t } = useTranslation();
    const canProcess = useCan('payroll.process');

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.payroll.runs.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const handleProcess = (runId) => {
        router.post(
            route('admin.payroll.runs.process', { payroll_run: runId }),
            {},
            { preserveState: false },
        );
    };

    const columns = useMemo(
        () => [
            {
                id: 'payrollNumber',
                header: t('pages.payrollRuns.columns.payrollNumber'),
                cell: ({ row }) => row.original.payroll_number ?? t('pages.payrollRuns.draft'),
            },
            {
                id: 'legalEntity',
                header: t('pages.payrollRuns.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'period',
                header: t('pages.payrollRuns.columns.period'),
                cell: ({ row }) => `${row.original.period_start} → ${row.original.period_end}`,
            },
            {
                id: 'currency',
                header: t('pages.payrollRuns.columns.currency'),
                cell: ({ row }) => row.original.currency_code,
            },
            {
                id: 'totals',
                header: t('pages.payrollRuns.columns.totals'),
                cell: ({ row }) =>
                    row.original.totals != null ? (
                        <div className="text-sm">
                            <div>{t('pages.payrollRuns.employeeCount')}: {row.original.totals.employee_count ?? 0}</div>
                            <div>{t('pages.payrollRuns.totalNet')}: {Number(row.original.totals.total_net ?? 0).toLocaleString()}</div>
                        </div>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'status',
                header: t('pages.payrollRuns.columns.status'),
                cell: ({ row }) =>
                    t(`pages.payrollRuns.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) =>
                    canProcess && row.original.status === 'draft' ? (
                        <button
                            type="button"
                            onClick={() => handleProcess(row.original.id)}
                            className="rp-btn-outline flex items-center gap-1 text-xs"
                        >
                            <Play className="h-3 w-3" />
                            {t('pages.payrollRuns.process')}
                        </button>
                    ) : null,
            },
        ],
        [t, canProcess],
    );

    return (
        <>
            <Head title={t('pages.payrollRuns.indexTitle')} />
            <PageHeader
                title={t('pages.payrollRuns.indexTitle')}
                description={t('pages.payrollRuns.indexDescription')}
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <Select name="legal_entity_id" defaultValue={filters.legal_entity_id ?? ''} className="min-w-[200px]">
                    <option value="">{t('pages.payrollRuns.allEntities')}</option>
                    {(entities ?? []).map((entity) => (
                        <option key={entity.id} value={entity.id}>
                            {entity.name}
                        </option>
                    ))}
                </Select>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[140px]">
                    <option value="">{t('pages.payrollRuns.allStatuses')}</option>
                    {['draft', 'processed', 'approved', 'posted'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.payrollRuns.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={runs.data ?? []} pagination={runs} />
        </>
    );
}

export default withAdminLayout(Index);
