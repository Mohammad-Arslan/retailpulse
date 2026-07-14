import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ payslips }) {
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'payslipNumber',
                header: t('pages.selfServicePayslips.columns.payslipNumber'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300">
                            <FileText className="h-4 w-4" />
                        </span>
                        <span className="text-sm font-semibold text-rp-text-primary">
                            {row.original.payslip_number}
                        </span>
                    </div>
                ),
            },
            {
                id: 'payrollNumber',
                header: t('pages.selfServicePayslips.columns.payrollNumber'),
                cell: ({ row }) => row.original.payroll_number ?? '—',
            },
            {
                id: 'period',
                header: t('pages.selfServicePayslips.columns.period'),
                cell: ({ row }) => `${row.original.period_start ?? '—'} → ${row.original.period_end ?? '—'}`,
            },
            {
                id: 'netPay',
                header: t('pages.selfServicePayslips.columns.netPay'),
                cell: ({ row }) =>
                    row.original.net_pay != null
                        ? `${Number(row.original.net_pay).toLocaleString()} ${row.original.currency_code ?? ''}`
                        : '—',
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <Link
                        href={route('admin.self-service.payslips.download', { payslip: row.original.id })}
                        className="rp-btn-outline text-xs"
                        target="_blank"
                    >
                        {t('pages.selfServicePayslips.download')}
                    </Link>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.selfServicePayslips.indexTitle')} />
            <PageHeader
                title={t('pages.selfServicePayslips.indexTitle')}
                description={t('pages.selfServicePayslips.indexDescription')}
            />
            <DataTable columns={columns} data={payslips ?? []} />
        </>
    );
}

export default withAdminLayout(Index);
