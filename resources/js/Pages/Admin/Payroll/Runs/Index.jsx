import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ runs, entities, filters }) {
    const { t } = useTranslation();
    const canProcess = useCan('payroll.process');
    const canApprove = useCan('payroll.approve');
    const canPost = useCan('payroll.post');
    const canReverse = useCan('payroll.reverse');

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.payroll.runs.index'), Object.fromEntries(form), { preserveState: true });
    };

    const action = (routeName, id) => {
        router.post(route(routeName, { payroll_run: id }), {}, { preserveState: false });
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
                id: 'totals',
                header: t('pages.payrollRuns.columns.totals'),
                cell: ({ row }) =>
                    row.original.totals != null
                        ? `${t('pages.payrollRuns.totalNet')}: ${Number(row.original.totals.total_net ?? 0).toLocaleString()}`
                        : '—',
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
                cell: ({ row }) => {
                    const s = row.original.status;
                    return (
                        <div className="flex flex-wrap gap-1">
                            {canProcess && s === 'draft' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.calculate', row.original.id)}
                                >
                                    {t('pages.payrollRuns.calculate')}
                                </button>
                            )}
                            {canProcess && s === 'draft' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.submit', row.original.id)}
                                >
                                    {t('pages.payrollRuns.submit')}
                                </button>
                            )}
                            {canApprove && ['draft', 'pending_approval'].includes(s) && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.approve', row.original.id)}
                                >
                                    {t('pages.payrollRuns.approve')}
                                </button>
                            )}
                            {canPost && s === 'approved' && (
                                <button
                                    type="button"
                                    className="rp-btn-primary text-xs"
                                    onClick={() => action('admin.payroll.runs.post', row.original.id)}
                                >
                                    {t('pages.payrollRuns.post')}
                                </button>
                            )}
                            {canReverse && s === 'posted' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.reverse', row.original.id)}
                                >
                                    {t('pages.payrollRuns.reverse')}
                                </button>
                            )}
                        </div>
                    );
                },
            },
        ],
        [t, canProcess, canApprove, canPost, canReverse],
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
                    {['draft', 'pending_approval', 'approved', 'posted', 'reversed'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.payrollRuns.statuses.${status}`, { defaultValue: status })}
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
