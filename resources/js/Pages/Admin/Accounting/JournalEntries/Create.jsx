import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
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
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function emptyLine(sequence = 1) {
    return {
        line_sequence: sequence,
        account_id: '',
        debit: '',
        credit: '',
        description: '',
    };
}

function Create({ accounts = [], branches = [], defaultBranchId = null }) {
    const { branch: branchContext } = usePage().props;
    const { t } = useTranslation();

    const resolvedBranchId =
        defaultBranchId ?? branchContext?.active?.id ?? branches[0]?.id ?? null;

    const { data, setData, post, processing, errors } = useForm({
        journal_date: new Date().toISOString().slice(0, 10),
        branch_id: resolvedBranchId ? String(resolvedBranchId) : '',
        reference: '',
        description: '',
        lines: [emptyLine(), emptyLine(2)],
    });

    const accountOptions = useMemo(
        () =>
            accounts.map((a) => ({
                value: String(a.id),
                label: `${a.code} — ${a.name}`,
            })),
        [accounts],
    );

    const branchOptions = useMemo(
        () =>
            (branches.length > 0 ? branches : branchContext?.options ?? []).map((b) => ({
                value: String(b.id),
                label: b.name,
            })),
        [branches, branchContext?.options],
    );

    const totalDebit = data.lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0);
    const totalCredit = data.lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0);
    const isBalanced = Math.abs(totalDebit - totalCredit) < 0.005 && totalDebit > 0;

    const updateLine = (index, patch) => {
        setData(
            'lines',
            data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
        );
    };

    const addLine = () => {
        setData('lines', [...data.lines, emptyLine(data.lines.length + 1)]);
    };

    const removeLine = (index) => {
        if (data.lines.length <= 2) {
            return;
        }
        setData(
            'lines',
            data.lines
                .filter((_, i) => i !== index)
                .map((line, i) => ({ ...line, line_sequence: i + 1 })),
        );
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.accounting.journal-entries.store'));
    };

    return (
        <>
            <Head title={t('pages.accounting.journalEntries.createTitle')} />
            <PageHeader
                title={t('pages.accounting.journalEntries.createTitle')}
                description={t('pages.accounting.journalEntries.createDescription')}
            >
                <Link href={route('admin.accounting.journal-entries.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.journalEntries.fields.journalDate')}
                            id="journal_date"
                            error={errors.journal_date}
                        >
                            <input
                                id="journal_date"
                                type="date"
                                value={data.journal_date}
                                onChange={(e) => setData('journal_date', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.branch')}
                            id="branch_id"
                            error={errors.branch_id}
                        >
                            <Select
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(value) => setData('branch_id', value ?? '')}
                                options={branchOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.journalEntries.fields.reference')}
                            id="reference"
                            error={errors.reference}
                        >
                            <input
                                id="reference"
                                value={data.reference}
                                onChange={(e) => setData('reference', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.journalEntries.fields.description')}
                            id="description"
                            error={errors.description}
                            className="sm:col-span-2"
                        >
                            <textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="rp-form-input min-h-[80px]"
                                rows={3}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <div className="rp-card overflow-hidden">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="font-semibold">{t('pages.accounting.journalEntries.linesTitle')}</h3>
                        <Button type="button" variant="outline" size="sm" onClick={addLine}>
                            <Plus className="h-4 w-4" />
                            {t('pages.accounting.journalEntries.addLine')}
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('pages.accounting.journalEntries.lineFields.account')}</TableHead>
                                    <TableHead>{t('pages.accounting.journalEntries.lineFields.debit')}</TableHead>
                                    <TableHead>{t('pages.accounting.journalEntries.lineFields.credit')}</TableHead>
                                    <TableHead>{t('pages.accounting.journalEntries.lineFields.narration')}</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.lines.map((line, index) => (
                                    <TableRow key={`line-${index}`}>
                                        <TableCell>
                                            <Select
                                                value={line.account_id}
                                                onChange={(value) => updateLine(index, { account_id: value ?? '' })}
                                                options={[
                                                    { value: '', label: t('pages.accounting.journalEntries.selectAccount') },
                                                    ...accountOptions,
                                                ]}
                                            />
                                            {errors[`lines.${index}.account_id`] && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {errors[`lines.${index}.account_id`]}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={line.debit}
                                                onChange={(e) =>
                                                    updateLine(index, {
                                                        debit: e.target.value,
                                                        credit: e.target.value ? '' : line.credit,
                                                    })
                                                }
                                                className="rp-form-input w-28"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={line.credit}
                                                onChange={(e) =>
                                                    updateLine(index, {
                                                        credit: e.target.value,
                                                        debit: e.target.value ? '' : line.debit,
                                                    })
                                                }
                                                className="rp-form-input w-28"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <input
                                                value={line.description}
                                                onChange={(e) => updateLine(index, { description: e.target.value })}
                                                className="rp-form-input min-w-[160px]"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeLine(index)}
                                                disabled={data.lines.length <= 2}
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell className="text-right font-medium">
                                        {t('pages.accounting.journalEntries.totals')}
                                    </TableCell>
                                    <TableCell className="font-semibold">{totalDebit.toFixed(2)}</TableCell>
                                    <TableCell className="font-semibold">{totalCredit.toFixed(2)}</TableCell>
                                    <TableCell colSpan={2}>
                                        {!isBalanced && (
                                            <span className="text-sm text-amber-600">
                                                {t('pages.accounting.journalEntries.unbalanced', {
                                                    difference: Math.abs(totalDebit - totalCredit).toFixed(2),
                                                })}
                                            </span>
                                        )}
                                        {isBalanced && (
                                            <span className="text-sm text-teal-600">
                                                {t('pages.accounting.journalEntries.balanced')}
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="submit" variant="brand" disabled={processing || !isBalanced}>
                        {t('pages.accounting.journalEntries.saveDraft')}
                    </Button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
