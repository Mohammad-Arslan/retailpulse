import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { journalEntryStatusLabel } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const REPORT_COLUMNS = {
    trial_balance: [
        'account_code',
        'account_name',
        'opening_debit',
        'opening_credit',
        'period_debit',
        'period_credit',
        'closing_debit',
        'closing_credit',
    ],
    profit_and_loss: ['account_code', 'account_name', 'type', 'amount'],
    balance_sheet: ['account_code', 'account_name', 'type', 'balance'],
    general_ledger: [
        'journal_date',
        'journal_number',
        'account_code',
        'description',
        'debit',
        'credit',
        'running_balance',
    ],
    cost_centre_pl: ['cost_centre_code', 'cost_centre_name', 'revenue', 'expense', 'net_income'],
    cash_flow: ['journal_date', 'journal_number', 'account_code', 'category', 'amount'],
    ar_aging: ['party_name', 'current', 'bucket_30', 'bucket_60', 'bucket_90', 'bucket_over_90', 'total'],
    ap_aging: ['party_name', 'current', 'bucket_30', 'bucket_60', 'bucket_90', 'bucket_over_90', 'total'],
    bank_book: [
        'journal_date',
        'journal_number',
        'account_code',
        'description',
        'debit',
        'credit',
        'running_balance',
    ],
    inventory_valuation: ['sku', 'product_name', 'warehouse', 'qty_remaining', 'unit_cost', 'total_value'],
    asset_register: ['asset_code', 'name', 'net_book_value'],
    fx_revaluation: [
        'journal_date',
        'journal_number',
        'account_code',
        'currency_code',
        'transaction_amount',
        'functional_amount',
        'exchange_rate',
    ],
    petty_cash: ['register', 'balance'],
    cheque_status: ['cheque_no', 'party', 'amount', 'status'],
    audit_trail: ['created_at', 'event', 'auditable_type', 'auditable_id', 'user_id'],
    unposted_journals: ['journal_number', 'journal_date', 'status', 'description', 'branch_name'],
    journal_register: ['journal_number', 'journal_date', 'description', 'branch_name', 'posted_at'],
};

function formatCell(key, value, row, reportKey, t) {
    if (key === 'account_code' && row.account_id && reportKey === 'trial_balance') {
        return (
            <Link
                href={route('admin.accounting.reports.general-ledger', { account_id: row.account_id })}
                className="font-mono text-teal-600 hover:underline"
            >
                {value}
            </Link>
        );
    }

    if (key === 'journal_number' && row.journal_entry_id) {
        return (
            <Link
                href={route('admin.accounting.journal-entries.show', row.journal_entry_id)}
                className="font-mono text-teal-600 hover:underline"
            >
                {value}
            </Link>
        );
    }

    if (key === 'status') {
        return journalEntryStatusLabel(t, value);
    }

    if (typeof value === 'number') {
        return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    return value ?? '—';
}

function Show({
    reportKey,
    reportSlug,
    title,
    filters,
    rows = [],
    totals = null,
    accounts = [],
    costCentres = [],
    canExport = false,
}) {
    const { t } = useTranslation();

    const columns = REPORT_COLUMNS[reportKey] ?? (rows[0] ? Object.keys(rows[0]) : []);

    const accountOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.reports.selectAccount') },
            ...accounts.map((a) => ({ value: String(a.id), label: `${a.code} — ${a.name}` })),
        ],
        [accounts, t],
    );

    const costCentreOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.costCentres.title') },
            ...costCentres.map((c) => ({ value: String(c.id), label: `${c.code} — ${c.name}` })),
        ],
        [costCentres, t],
    );

    const applyFilters = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route(`admin.accounting.reports.${reportSlug}`), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const exportUrl = canExport
        ? route('admin.accounting.reports.export', {
              reportKey: reportSlug,
              ...filters,
          })
        : null;

    return (
        <>
            <Head title={title} />
            <PageHeader
                title={title}
                description={t('pages.accounting.reports.description')}
                actions={
                    <Link
                        href={route('admin.accounting.reports.index')}
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        {t('pages.accounting.reports.backToReports')}
                    </Link>
                }
            />

            <form onSubmit={applyFilters} className="rp-filter-bar mb-6 flex-wrap gap-2">
                <input
                    type="date"
                    name="date_from"
                    defaultValue={filters.date_from?.slice(0, 10) ?? ''}
                    className="rp-form-input w-auto"
                />
                <input
                    type="date"
                    name="date_to"
                    defaultValue={filters.date_to?.slice(0, 10) ?? ''}
                    className="rp-form-input w-auto"
                />
                {['general_ledger', 'trial_balance'].includes(reportKey) && (
                    <Select
                        name="account_id"
                        defaultValue={filters.account_id ? String(filters.account_id) : ''}
                        className="w-auto min-w-[12rem]"
                        options={accountOptions}
                    />
                )}
                {reportKey === 'cost_centre_pl' && (
                    <Select
                        name="cost_centre_id"
                        defaultValue={filters.cost_centre_id ? String(filters.cost_centre_id) : ''}
                        className="w-auto min-w-[12rem]"
                        options={costCentreOptions}
                    />
                )}
                <Button type="submit" variant="outline">
                    {t('common.apply')}
                </Button>
                {exportUrl && (
                    <a href={exportUrl} className="inline-flex">
                        <Button type="button" variant="outline">
                            <Download className="h-4 w-4" />
                            {t('common.export')}
                        </Button>
                    </a>
                )}
            </form>

            {totals && (
                <div className="mb-4 flex flex-wrap gap-4 rounded-lg border bg-muted/30 p-4 text-sm">
                    {Object.entries(totals).map(([key, value]) => (
                        <div key={key}>
                            <span className="text-muted-foreground capitalize">
                                {key.replace(/_/g, ' ')}:{' '}
                            </span>
                            <span className="font-semibold">
                                {typeof value === 'number'
                                    ? value.toLocaleString(undefined, {
                                          minimumFractionDigits: 2,
                                          maximumFractionDigits: 2,
                                      })
                                    : value}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((col) => (
                                <TableHead key={col} className="capitalize">
                                    {col.replace(/_/g, ' ')}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length ? (
                            rows.map((row, idx) => (
                                <TableRow key={row.id ?? idx}>
                                    {columns.map((col) => (
                                        <TableCell key={col}>
                                            {formatCell(col, row[col], row, reportKey, t)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length || 1} className="py-8 text-center text-muted-foreground">
                                    {t('pages.accounting.reports.empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

export default withAdminLayout(Show);
