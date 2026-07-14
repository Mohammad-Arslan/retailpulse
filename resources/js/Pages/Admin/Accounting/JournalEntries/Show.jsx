import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { useConfirmDelete } from '@/Hooks/useConfirmDelete';
import { journalEntryStatusLabel, journalStatusBadgeClass } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle, Pencil, RotateCcw, Send, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function Show({ journalEntry: entry }) {
    const can = useCan();
    const { t } = useTranslation();
    const confirm = useConfirm();
    const confirmDelete = useConfirmDelete();

    if (!entry) {
        return null;
    }

    const isBalanced = entry.total_debit === entry.total_credit;

    const canEdit = can('accounting.create-journal') && entry.status === 'draft';
    const canApprove = can('accounting.approve-journal') && entry.status === 'pending_approval';
    const canPost =
        can('accounting.post-journal') &&
        ['draft', 'approved'].includes(entry.status) &&
        isBalanced;
    const canReverse = can('accounting.reverse-journal') && entry.status === 'posted';

    const approveJournal = () => {
        router.post(route('admin.accounting.journal-entries.approve', entry.id));
    };

    const postJournal = async () => {
        const confirmed = await confirm({
            title: t('pages.accounting.journalEntries.post'),
            description: t('pages.accounting.journalEntries.confirmPost'),
            confirmLabel: t('pages.accounting.journalEntries.post'),
            variant: 'default',
        });

        if (confirmed) {
            router.post(route('admin.accounting.journal-entries.post', entry.id));
        }
    };

    const reverseJournal = async () => {
        const confirmed = await confirm({
            title: t('pages.accounting.journalEntries.reverse'),
            description: t('pages.accounting.journalEntries.confirmReverse'),
            confirmLabel: t('pages.accounting.journalEntries.reverse'),
            variant: 'destructive',
        });

        if (confirmed) {
            router.post(route('admin.accounting.journal-entries.reverse', entry.id));
        }
    };

    const deleteJournal = async () => {
        const confirmed = await confirmDelete(
            entry.journal_number,
            'pages.accounting.journalEntries.confirmDelete',
        );

        if (confirmed) {
            router.delete(route('admin.accounting.journal-entries.destroy', entry.id));
        }
    };

    return (
        <>
            <Head title={entry.journal_number} />
            <PageHeader title={entry.journal_number} description={entry.description ?? ''}>
                <Link href={route('admin.accounting.journal-entries.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
                {canEdit && (
                    <Link href={route('admin.accounting.journal-entries.edit', entry.id)} className="rp-btn-outline">
                        <Pencil className="h-4 w-4" />
                        {t('common.edit')}
                    </Link>
                )}
                {canEdit && (
                    <Button variant="outline" onClick={deleteJournal}>
                        <Trash2 className="h-4 w-4" />
                        {t('common.delete')}
                    </Button>
                )}
                {canApprove && (
                    <Button variant="brand" onClick={approveJournal}>
                        <CheckCircle className="h-4 w-4" />
                        {t('pages.accounting.journalEntries.approve')}
                    </Button>
                )}
                {canPost && (
                    <Button variant="brand" onClick={postJournal}>
                        <Send className="h-4 w-4" />
                        {t('pages.accounting.journalEntries.post')}
                    </Button>
                )}
                {canReverse && (
                    <Button variant="outline" onClick={reverseJournal}>
                        <RotateCcw className="h-4 w-4" />
                        {t('pages.accounting.journalEntries.reverse')}
                    </Button>
                )}
            </PageHeader>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border bg-card p-4 text-sm">
                    <dt className="text-muted-foreground">{t('common.status')}</dt>
                    <dd className="mt-1">
                        <span
                            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${journalStatusBadgeClass(entry.status)}`}
                        >
                            {journalEntryStatusLabel(t, entry.status)}
                        </span>
                    </dd>
                </div>
                <div className="rounded-lg border bg-card p-4 text-sm">
                    <dt className="text-muted-foreground">{t('pages.accounting.journalEntries.fields.journalDate')}</dt>
                    <dd className="mt-1 font-medium">{entry.journal_date?.slice(0, 10)}</dd>
                </div>
                <div className="rounded-lg border bg-card p-4 text-sm">
                    <dt className="text-muted-foreground">{t('common.branch')}</dt>
                    <dd className="mt-1 font-medium">{entry.branch_name ?? '—'}</dd>
                </div>
                <div className="rounded-lg border bg-card p-4 text-sm">
                    <dt className="text-muted-foreground">{t('pages.accounting.journalEntries.fields.reference')}</dt>
                    <dd className="mt-1 font-medium">{entry.reference ?? '—'}</dd>
                </div>
            </div>

            {entry.source_event && (
                <div className="mb-4 rounded-lg border border-dashed bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    {t('pages.accounting.journalEntries.sourceEvent')}:{' '}
                    <span className="font-mono">{entry.source_event}</span>
                    {entry.source_number && (
                        <span className="ml-2">({entry.source_number})</span>
                    )}
                </div>
            )}

            <div className="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="px-4">{t('pages.accounting.journalEntries.lineFields.account')}</TableHead>
                            <TableHead className="px-4 text-right">{t('pages.accounting.journalEntries.lineFields.debit')}</TableHead>
                            <TableHead className="px-4 text-right">{t('pages.accounting.journalEntries.lineFields.credit')}</TableHead>
                            <TableHead className="px-4">{t('pages.accounting.journalEntries.lineFields.narration')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(entry.transactions ?? []).map((line) => (
                            <TableRow key={line.id}>
                                <TableCell className="px-4">
                                    <div className="font-medium">{line.account_name}</div>
                                    <div className="text-xs text-muted-foreground">{line.account_code}</div>
                                </TableCell>
                                <TableCell className="px-4 text-right font-mono">
                                    {parseFloat(line.debit) > 0 ? line.debit : '—'}
                                </TableCell>
                                <TableCell className="px-4 text-right font-mono">
                                    {parseFloat(line.credit) > 0 ? line.credit : '—'}
                                </TableCell>
                                <TableCell className="px-4 text-muted-foreground">{line.description ?? '—'}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell className="px-4 text-right">{t('pages.accounting.journalEntries.totals')}</TableCell>
                            <TableCell className="px-4 text-right font-mono">{entry.total_debit ?? '0.00'}</TableCell>
                            <TableCell className="px-4 text-right font-mono">{entry.total_credit ?? '0.00'}</TableCell>
                            <TableCell />
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>

            {entry.posted_at && (
                <p className="mt-4 text-sm text-muted-foreground">
                    {t('pages.accounting.journalEntries.postedAt', {
                        date: new Date(entry.posted_at).toLocaleString(),
                        user: entry.posted_by_name ?? '—',
                    })}
                </p>
            )}

            {entry.reversal_of && (
                <p className="mt-2 text-sm text-muted-foreground">
                    {t('pages.accounting.journalEntries.reversalOf')}:{' '}
                    <Link
                        href={route('admin.accounting.journal-entries.show', entry.reversal_of.id)}
                        className="text-teal-600 hover:underline"
                    >
                        {entry.reversal_of.journal_number}
                    </Link>
                </p>
            )}
        </>
    );
}

export default withAdminLayout(Show);
