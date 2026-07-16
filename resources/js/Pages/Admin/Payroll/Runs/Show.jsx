import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { readCsrfToken } from '@/lib/csrf';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function GeneratePayslipForm({ itemId, label }) {
    const action = route('admin.payroll.items.payslip.generate', { payroll_item: itemId });

    return (
        <form method="POST" action={action} target="_blank" className="inline">
            <input type="hidden" name="_token" value={readCsrfToken() ?? ''} />
            <button type="submit" className="rp-btn-outline text-xs">
                {label}
            </button>
        </form>
    );
}

function Show({ run, items }) {
    const { t } = useTranslation();
    const can = useCan();
    const canProcess = can('payroll.process');
    const canApprove = can('payroll.approve');
    const canPost = can('payroll.post');
    const canReverse = can('payroll.reverse');
    const canPayslips = can('payroll.view') || can('payroll.process');

    const action = (routeName) => {
        router.post(route(routeName, { payroll_run: run.id }), {}, { preserveScroll: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.payrollRunShow.columns.employee'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-semibold text-rp-text">{row.original.employee_name ?? '—'}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.employee_code}</div>
                    </div>
                ),
            },
            {
                id: 'gross',
                header: t('pages.payrollRunShow.columns.gross'),
                cell: ({ row }) => Number(row.original.gross ?? 0).toLocaleString(),
            },
            {
                id: 'deductions',
                header: t('pages.payrollRunShow.columns.deductions'),
                cell: ({ row }) => Number(row.original.total_deductions ?? 0).toLocaleString(),
            },
            {
                id: 'employerContributions',
                header: t('pages.payrollRunShow.columns.employerContributions'),
                cell: ({ row }) => Number(row.original.total_employer_contributions ?? 0).toLocaleString(),
            },
            {
                id: 'netPay',
                header: t('pages.payrollRunShow.columns.netPay'),
                cell: ({ row }) => Number(row.original.net_pay ?? 0).toLocaleString(),
            },
            {
                id: 'payslip',
                header: t('pages.payrollRunShow.columns.payslipStatus'),
                cell: ({ row }) =>
                    row.original.payslip ? (
                        <span className="text-xs font-medium text-teal-600">
                            {row.original.payslip.emailed_at
                                ? t('pages.payrollRunShow.payslipEmailed')
                                : t('pages.payrollRunShow.payslipGenerated')}
                        </span>
                    ) : (
                        <span className="text-xs text-rp-text-muted">{t('pages.payrollRunShow.payslipPending')}</span>
                    ),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) =>
                    canPayslips ? (
                        <div className="flex flex-wrap justify-end gap-1">
                            <a
                                href={route('admin.payroll.items.payslip.download', { payroll_item: row.original.id })}
                                target="_blank"
                                rel="noreferrer"
                                className="rp-btn-outline text-xs"
                            >
                                {t('pages.payrollRunShow.download')}
                            </a>
                            <GeneratePayslipForm
                                itemId={row.original.id}
                                label={t('pages.payrollRunShow.generate')}
                            />
                        </div>
                    ) : null,
            },
        ],
        [t, canPayslips],
    );

    return (
        <>
            <Head title={t('pages.payrollRunShow.title', { number: run.payroll_number ?? t('pages.payrollRuns.draft') })} />
            <PageHeader
                title={run.payroll_number ?? t('pages.payrollRuns.draft')}
                description={`${run.legal_entity ?? '—'} · ${run.period_start} → ${run.period_end}`}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Link href={route('admin.payroll.runs.index')} className="rp-btn-outline">
                        {t('common.back')}
                    </Link>
                    {canProcess && run.status === 'draft' && (
                        <Button variant="outline" onClick={() => action('admin.payroll.runs.calculate')}>
                            {t('pages.payrollRuns.calculate')}
                        </Button>
                    )}
                    {canProcess && run.status === 'draft' && (
                        <Button variant="outline" onClick={() => action('admin.payroll.runs.submit')}>
                            {t('pages.payrollRuns.submit')}
                        </Button>
                    )}
                    {canApprove && ['draft', 'pending_approval'].includes(run.status) && (
                        <Button variant="outline" onClick={() => action('admin.payroll.runs.approve')}>
                            {t('pages.payrollRuns.approve')}
                        </Button>
                    )}
                    {canPost && run.status === 'approved' && (
                        <Button variant="brand" onClick={() => action('admin.payroll.runs.post')}>
                            {t('pages.payrollRuns.post')}
                        </Button>
                    )}
                    {canReverse && run.status === 'posted' && (
                        <Button variant="outline" onClick={() => action('admin.payroll.runs.reverse')}>
                            {t('pages.payrollRuns.reverse')}
                        </Button>
                    )}
                    {canProcess && ['approved', 'posted'].includes(run.status) && (
                        <Button variant="outline" onClick={() => action('admin.payroll.runs.payslips.email')}>
                            {t('pages.payrollRuns.emailPayslips')}
                        </Button>
                    )}
                </div>
            </PageHeader>

            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-rp-border bg-rp-surface p-4">
                    <div className="text-xs font-medium text-rp-text-muted">{t('pages.payrollRuns.columns.status')}</div>
                    <div className="mt-1 text-sm font-semibold text-rp-text">
                        {t(`pages.payrollRuns.statuses.${run.status}`, { defaultValue: run.status })}
                    </div>
                </div>
                <div className="rounded-xl border border-rp-border bg-rp-surface p-4">
                    <div className="text-xs font-medium text-rp-text-muted">{t('pages.payrollRuns.employeeCount')}</div>
                    <div className="mt-1 text-sm font-semibold text-rp-text">{run.totals?.employee_count ?? items.length}</div>
                </div>
                <div className="rounded-xl border border-rp-border bg-rp-surface p-4">
                    <div className="text-xs font-medium text-rp-text-muted">{t('pages.payrollRuns.totalNet')}</div>
                    <div className="mt-1 text-sm font-semibold text-rp-text">
                        {Number(run.totals?.total_net ?? 0).toLocaleString()} {run.currency_code}
                    </div>
                </div>
                <div className="rounded-xl border border-rp-border bg-rp-surface p-4">
                    <div className="text-xs font-medium text-rp-text-muted">{t('pages.payrollRunShow.branch')}</div>
                    <div className="mt-1 text-sm font-semibold text-rp-text">{run.branch ?? '—'}</div>
                </div>
            </div>

            <DataTable
                columns={columns}
                data={items ?? []}
                emptyMessage={t('pages.payrollRunShow.empty')}
            />
        </>
    );
}

export default withAdminLayout(Show);
