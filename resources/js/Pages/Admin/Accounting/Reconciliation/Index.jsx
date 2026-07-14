import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyMatchRow() {
    return { journal_transaction_id: '', matched_amount: '' };
}

function statusLabel(t, status) {
    return t(`pages.accounting.reconciliation.statuses.${status}`, {
        defaultValue: status,
    });
}

function Index({
    bankAccounts = [],
    selectedBankAccountId = null,
    statementLines = [],
    suggestions = [],
    matchableTransactions = [],
}) {
    const { t } = useTranslation();
    const importForm = useForm({ file: null });
    const [matchLine, setMatchLine] = useState(null);
    const matchForm = useForm({ transactions: [emptyMatchRow()] });

    const transactionOptions = useMemo(
        () =>
            matchableTransactions.map((tx) => ({
                value: String(tx.id),
                label: tx.label,
            })),
        [matchableTransactions],
    );

    const matchSum = matchForm.data.transactions.reduce(
        (sum, row) => sum + (parseFloat(row.matched_amount) || 0),
        0,
    );
    const remaining = matchLine ? parseFloat(matchLine.remaining_amount) || 0 : 0;
    const sumExceeds = matchSum - remaining > 0.001;

    const lineColumns = useMemo(
        () => [
            { id: 'date', header: t('common.date'), cell: ({ row }) => row.original.transaction_date },
            { id: 'reference', header: t('common.reference'), cell: ({ row }) => row.original.reference ?? '—' },
            { id: 'description', header: t('common.description'), cell: ({ row }) => row.original.description ?? '—' },
            { id: 'debit', header: t('pages.accounting.journalEntries.columns.debit'), cell: ({ row }) => row.original.debit },
            { id: 'credit', header: t('pages.accounting.journalEntries.columns.credit'), cell: ({ row }) => row.original.credit },
            {
                id: 'matched',
                header: t('pages.accounting.reconciliation.columns.matched'),
                cell: ({ row }) => row.original.matched_amount,
            },
            {
                id: 'remaining',
                header: t('pages.accounting.reconciliation.columns.remaining'),
                cell: ({ row }) => row.original.remaining_amount,
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => statusLabel(t, row.original.status),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) => {
                    const line = row.original;
                    const canMatch = ['unmatched', 'suggested', 'partially_matched'].includes(line.status);

                    if (!canMatch) {
                        return '—';
                    }

                    return (
                        <Button type="button" size="sm" variant="outline" onClick={() => openMatch(line)}>
                            {t('pages.accounting.reconciliation.match')}
                        </Button>
                    );
                },
            },
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
        router.post(route('admin.accounting.reconciliation.match', lineId), {
            journal_transaction_id: transactionId,
        });
    };

    const openMatch = (line) => {
        setMatchLine(line);
        matchForm.setData({
            transactions: [
                {
                    journal_transaction_id: '',
                    matched_amount: line.remaining_amount || '',
                },
            ],
        });
        matchForm.clearErrors();
    };

    const closeMatch = () => {
        setMatchLine(null);
        matchForm.reset();
        matchForm.clearErrors();
    };

    const updateMatchRow = (index, patch) => {
        matchForm.setData(
            'transactions',
            matchForm.data.transactions.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    };

    const addMatchRow = () => {
        matchForm.setData('transactions', [...matchForm.data.transactions, emptyMatchRow()]);
    };

    const removeMatchRow = (index) => {
        if (matchForm.data.transactions.length <= 1) {
            return;
        }
        matchForm.setData(
            'transactions',
            matchForm.data.transactions.filter((_, i) => i !== index),
        );
    };

    const onTransactionPicked = (index, transactionId) => {
        const tx = matchableTransactions.find((item) => String(item.id) === String(transactionId));
        const used = matchForm.data.transactions.reduce((sum, row, i) => {
            if (i === index) return sum;
            return sum + (parseFloat(row.matched_amount) || 0);
        }, 0);
        const room = Math.max(0, remaining - used);
        const suggested = tx ? Math.min(parseFloat(tx.amount) || 0, room) : room;

        updateMatchRow(index, {
            journal_transaction_id: transactionId,
            matched_amount: suggested > 0 ? suggested.toFixed(2) : '',
        });
    };

    const submitMatch = (e) => {
        e.preventDefault();
        if (!matchLine || sumExceeds) return;

        matchForm.post(route('admin.accounting.reconciliation.match', matchLine.id), {
            preserveScroll: true,
            onSuccess: () => closeMatch(),
        });
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
                                        <span>
                                            {s.reference ?? '—'} — {s.amount}
                                            {s.remaining_amount != null && Number(s.remaining_amount) !== Number(s.amount)
                                                ? ` (${t('pages.accounting.reconciliation.columns.remaining')}: ${s.remaining_amount})`
                                                : ''}{' '}
                                            → {s.journal_reference} ({s.journal_amount}) · score {s.score}
                                        </span>
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

            <Modal show={!!matchLine} onClose={closeMatch} maxWidth="2xl">
                {matchLine && (
                    <form onSubmit={submitMatch} className="space-y-4 p-1">
                        <h2 className="text-lg font-semibold">{t('pages.accounting.reconciliation.multiMatchTitle')}</h2>
                        <p className="text-sm text-muted-foreground">
                            {t('pages.accounting.reconciliation.multiMatchHint', {
                                remaining: matchLine.remaining_amount,
                            })}
                        </p>

                        {matchForm.data.transactions.map((row, index) => (
                            <div key={index} className="grid gap-3 sm:grid-cols-[1fr_8rem_auto] items-end">
                                <AdminFormField
                                    label={t('pages.accounting.reconciliation.fields.journalTransaction')}
                                    id={`tx-${index}`}
                                    error={matchForm.errors[`transactions.${index}.journal_transaction_id`]}
                                >
                                    <Select
                                        options={transactionOptions}
                                        value={row.journal_transaction_id ? String(row.journal_transaction_id) : ''}
                                        onChange={(value) => onTransactionPicked(index, value)}
                                        placeholder={t('pages.accounting.reconciliation.selectTransaction')}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.reconciliation.fields.matchedAmount')}
                                    id={`amt-${index}`}
                                    error={matchForm.errors[`transactions.${index}.matched_amount`]}
                                >
                                    <input
                                        id={`amt-${index}`}
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        className="rp-input"
                                        value={row.matched_amount}
                                        onChange={(e) => updateMatchRow(index, { matched_amount: e.target.value })}
                                    />
                                </AdminFormField>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={matchForm.data.transactions.length <= 1}
                                    onClick={() => removeMatchRow(index)}
                                    aria-label={t('common.delete')}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Button type="button" variant="outline" size="sm" onClick={addMatchRow}>
                                <Plus className="mr-1 h-4 w-4" />
                                {t('pages.accounting.reconciliation.addTransaction')}
                            </Button>
                            <p className={`text-sm ${sumExceeds ? 'text-destructive' : 'text-muted-foreground'}`}>
                                {t('pages.accounting.reconciliation.matchSum', {
                                    sum: matchSum.toFixed(2),
                                    remaining: remaining.toFixed(2),
                                })}
                            </p>
                        </div>

                        {(matchForm.errors.matched_amount || matchForm.errors.journal_transaction_id) && (
                            <p className="text-sm text-destructive">
                                {matchForm.errors.matched_amount || matchForm.errors.journal_transaction_id}
                            </p>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={closeMatch}>
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={matchForm.processing || sumExceeds}>
                                {t('pages.accounting.reconciliation.matchSubmit')}
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
