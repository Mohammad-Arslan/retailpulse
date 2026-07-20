import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { FileMinus, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ debitNotes = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'number',
                header: t('pages.accounting.debitNotes.columns.number'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                            <FileMinus className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">{row.original.reference_no}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.date}</div>
                        </div>
                    </div>
                ),
            },
            { id: 'supplier', header: t('common.supplier'), cell: ({ row }) => row.original.supplier_name ?? '—' },
            { id: 'branch', header: t('common.branch'), cell: ({ row }) => row.original.branch_name ?? '—' },
            { id: 'amount', header: t('common.amount'), cell: ({ row }) => row.original.amount },
            { id: 'status', header: t('pages.accounting.debitNotes.columns.status'), cell: ({ row }) => row.original.status },
            { id: 'notes', header: t('pages.accounting.debitNotes.columns.notes'), cell: ({ row }) => row.original.notes },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.accounting.debitNotes.title')} />
            <PageHeader title={t('pages.accounting.debitNotes.title')} description={t('pages.accounting.debitNotes.description')}>
                {can('procurement.manage-returns') && (
                    <Link href={route('admin.accounting.debit-notes.create')}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('pages.accounting.debitNotes.createTitle')}
                        </Button>
                    </Link>
                )}
            </PageHeader>
            <DataTable
                columns={columns}
                data={debitNotes.data ?? debitNotes}
                pagination={debitNotes.data ? debitNotes : undefined}
                emptyMessage={t('pages.accounting.debitNotes.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
