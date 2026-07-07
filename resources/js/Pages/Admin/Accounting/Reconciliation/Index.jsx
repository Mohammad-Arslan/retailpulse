import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ bankAccounts = [], selectedBankAccountId = null, statementLines = [], suggestions = [] }) {
    const { t } = useTranslation();
    const importForm = useForm({ file: null });

    const lineColumns = useMemo(
        () => [
            { id: 'date', header: t('common.date'), cell: ({ row }) => row.original.transaction_date },
            { id: 'reference', header: t('common.reference'), cell: ({ row }) => row.original.reference ?? '—' },
            { id: 'description', header: t('common.description'), cell: ({ row }) => row.original.description ?? '—' },
            { id: 'debit', header: t('pages.accounting.journalEntries.columns.debit'), cell: ({ row }) => row.original.debit },
            { id: 'credit', header: t('pages.accounting.journalEntries.columns.credit'), cell: ({ row }) => row.original.credit },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const onBankChange = (bankId) => {
        router.get(route('admin.accounting.reconciliation.index'), { bank_account_id: bankId }, { preserveState: true });
    };

    const importCsv = (e) => {
        e.preventDefault();
        if (!selectedBankAccountId) return;
        importForm.post(route('admin.accounting.reconciliation.import', selectedBankAccountId), { forceFormData: true });
    };

    const matchSuggestion = (lineId, transactionId) => {
        router.post(route('admin.accounting.reconciliation.match', lineId), { journal_transaction_id: transactionId });
    };

    return (
        <>
            <Head title={t('pages.accounting.reconciliation.title')} />
            <PageHeader title={t('pages.accounting.reconciliation.title')} description={t('pages.accounting.reconciliation.description')} />
            <div className="mb-4 max-w-md">
                <Select
                    options={bankAccounts.map((b) => ({ value: String(b.id), label: `${b.bank_name} — ${b.account_title}` }))}
                    value={selectedBankAccountId ? String(selectedBankAccountId) : ''}
                    onChange={onBankChange}
                    placeholder={t('pages.accounting.reconciliation.selectBankAccount')}
                />
            </div>
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <DataTable columns={lineColumns} data={statementLines} emptyMessage={t('pages.accounting.reconciliation.emptyLines')} />
                    {suggestions.length > 0 && (
                        <FormCard title={t('pages.accounting.reconciliation.suggestionsTitle')}>
                            <ul className="space-y-2 text-sm">
                                {suggestions.map((s) => (
                                    <li key={`${s.statement_line_id}-${s.journal_transaction_id}`} className="flex items-center justify-between gap-3">
                                        <span>{s.reference ?? '—'} — {s.amount} (score {s.score})</span>
                                        <Button type="button" size="sm" variant="outline" onClick={() => matchSuggestion(s.statement_line_id, s.journal_transaction_id)}>
                                            {t('pages.accounting.reconciliation.match')}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </FormCard>
                    )}
                </div>
                <FormCard title={t('pages.accounting.reconciliation.importTitle')}>
                    <form onSubmit={importCsv} className="space-y-4">
                        <input type="file" accept=".csv,.txt" onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)} />
                        <Button type="submit" disabled={importForm.processing || !selectedBankAccountId}>{t('pages.accounting.reconciliation.importSubmit')}</Button>
                    </form>
                </FormCard>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
