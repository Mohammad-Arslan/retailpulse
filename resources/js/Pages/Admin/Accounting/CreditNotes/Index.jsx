import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ creditNotes = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'number',
                header: t('pages.accounting.creditNotes.columns.number'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                            <FileText className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">{row.original.credit_note_number}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.date}</div>
                        </div>
                    </div>
                ),
            },
            { id: 'customer', header: t('common.customer'), cell: ({ row }) => row.original.customer_name ?? '—' },
            { id: 'branch', header: t('common.branch'), cell: ({ row }) => row.original.branch_name ?? '—' },
            { id: 'amount', header: t('common.amount'), cell: ({ row }) => row.original.amount },
            { id: 'tax', header: t('pages.accounting.creditNotes.columns.tax'), cell: ({ row }) => row.original.tax_amount },
            { id: 'reason', header: t('pages.accounting.creditNotes.columns.reason'), cell: ({ row }) => row.original.reason },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.accounting.creditNotes.title')} />
            <PageHeader title={t('pages.accounting.creditNotes.title')} description={t('pages.accounting.creditNotes.description')}>
                {can('accounting.view') && (
                    <Link href={route('admin.accounting.credit-notes.create')}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('pages.accounting.creditNotes.createTitle')}
                        </Button>
                    </Link>
                )}
            </PageHeader>
            <DataTable
                columns={columns}
                data={creditNotes.data ?? creditNotes}
                pagination={creditNotes.data ? creditNotes : undefined}
                emptyMessage={t('pages.accounting.creditNotes.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
