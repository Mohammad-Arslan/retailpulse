import AdminFormField from '@/Components/common/AdminFormField';
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
import {
    auditEntityTypeLabel,
    auditEventLabel,
    journalEntryStatusLabel,
    reportColumnLabel,
    reportTotalLabel,
} from '@/lib/accountingI18n';
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
    audit_trail: ['occurred_at', 'event', 'entity_type', 'entity_label', 'user_name', 'changes_summary'],
    unposted_journals: ['journal_number', 'journal_date', 'status', 'description', 'branch_name'],
    journal_register: ['journal_number', 'journal_date', 'description', 'branch_name', 'posted_at'],
};

const AS_OF_REPORTS = new Set(['balance_sheet']);

function formatCell(key, value, row, reportKey, t) {
    if (key === 'account_code' && row.account_id && reportKey === 'trial_balance') {
        return (
            <Link
                href={route('admin.accounting.reports.general-ledger', { account_id: row.account_id })}
                className="font-mono text-teal-600 hover:underline dark:text-teal-400"
            >
                {value}
            </Link>
        );
    }

    if (key === 'journal_number' && row.journal_entry_id) {
        return (
            <Link
                href={route('admin.accounting.journal-entries.show', row.journal_entry_id)}
                className="font-mono text-teal-600 hover:underline dark:text-teal-400"
            >
                {value}
            </Link>
        );
    }

    if (key === 'entity_label' && row.journal_entry_id) {
        return (
            <Link
                href={route('admin.accounting.journal-entries.show', row.journal_entry_id)}
                className="text-teal-600 hover:underline dark:text-teal-400"
            >
                {value}
            </Link>
        );
    }

    if (key === 'event' && reportKey === 'audit_trail') {
        return auditEventLabel(t, value);
    }

    if (key === 'entity_type' && reportKey === 'audit_trail') {
        return auditEntityTypeLabel(t, value);
    }

    if (key === 'status') {
        return journalEntryStatusLabel(t, value);
    }

    if (key === 'occurred_at' || key === 'posted_at' || key === 'journal_date') {
        if (!value) {
            return '—';
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleString(undefined, {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                  hour: key === 'occurred_at' || key === 'posted_at' ? '2-digit' : undefined,
                  minute: key === 'occurred_at' || key === 'posted_at' ? '2-digit' : undefined,
              });
    }

    if (key === 'changes_summary') {
        return <span className="text-muted-foreground">{value ?? '—'}</span>;
    }

    if (typeof value === 'number') {
        return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    return value ?? '—';
}

function Show({
    reportKey,
    reportCardKey,
    reportSlug,
    title,
    filters,
    rows = [],
    totals = null,
    accounts = [],
    costCentres = [],
    auditEntityTypes = [],
    auditEvents = [],
    canExport = false,
}) {
    const { t } = useTranslation();
    const cardKey = reportCardKey ?? reportKey;

    const pageTitle = t(`pages.accounting.reports.cards.${cardKey}.title`, { defaultValue: title });
    const pageDescription = t(`pages.accounting.reports.cards.${cardKey}.description`);

    const columns = REPORT_COLUMNS[reportKey] ?? (rows[0] ? Object.keys(rows[0]) : []);
    const isAsOfReport = AS_OF_REPORTS.has(reportKey);

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

    const entityTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.reports.allRecordTypes') },
            ...auditEntityTypes.map((item) => ({
                value: item.value,
                label: auditEntityTypeLabel(t, item.label) || item.label,
            })),
        ],
        [auditEntityTypes, t],
    );

    const eventOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.reports.allEvents') },
            ...auditEvents.map((item) => ({
                value: item.value,
                label: auditEventLabel(t, item.value) || item.label,
            })),
        ],
        [auditEvents, t],
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
            <Head title={pageTitle} />
            <PageHeader
                title={pageTitle}
                description={pageDescription}
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

            <form onSubmit={applyFilters} className="rp-filter-bar mb-6 flex-wrap items-end gap-3">
                {isAsOfReport ? (
                    <AdminFormField label={t('pages.accounting.reports.asOfDate')} id="date_to" className="w-auto">
                        <input
                            id="date_to"
                            type="date"
                            name="date_to"
                            defaultValue={filters.date_to?.slice(0, 10) ?? ''}
                            className="rp-form-input w-auto"
                        />
                    </AdminFormField>
                ) : (
                    <>
                        <AdminFormField label={t('pages.accounting.reports.dateFrom')} id="date_from" className="w-auto">
                            <input
                                id="date_from"
                                type="date"
                                name="date_from"
                                defaultValue={filters.date_from?.slice(0, 10) ?? ''}
                                className="rp-form-input w-auto"
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.reports.dateTo')} id="date_to_range" className="w-auto">
                            <input
                                id="date_to_range"
                                type="date"
                                name="date_to"
                                defaultValue={filters.date_to?.slice(0, 10) ?? ''}
                                className="rp-form-input w-auto"
                            />
                        </AdminFormField>
                    </>
                )}
                {['general_ledger', 'trial_balance'].includes(reportKey) && (
                    <AdminFormField label={t('pages.accounting.reports.selectAccount')} id="account_id" className="min-w-[12rem]">
                        <Select
                            id="account_id"
                            name="account_id"
                            defaultValue={filters.account_id ? String(filters.account_id) : ''}
                            options={accountOptions}
                        />
                    </AdminFormField>
                )}
                {reportKey === 'cost_centre_pl' && (
                    <AdminFormField label={t('pages.accounting.costCentres.title')} id="cost_centre_id" className="min-w-[12rem]">
                        <Select
                            id="cost_centre_id"
                            name="cost_centre_id"
                            defaultValue={filters.cost_centre_id ? String(filters.cost_centre_id) : ''}
                            options={costCentreOptions}
                        />
                    </AdminFormField>
                )}
                {reportKey === 'audit_trail' && (
                    <>
                        <AdminFormField label={t('pages.accounting.reports.columns.entityType')} id="auditable_type" className="min-w-[12rem]">
                            <Select
                                id="auditable_type"
                                name="auditable_type"
                                defaultValue={filters.auditable_type ?? ''}
                                options={entityTypeOptions}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.reports.columns.event')} id="event" className="min-w-[10rem]">
                            <Select
                                id="event"
                                name="event"
                                defaultValue={filters.event ?? ''}
                                options={eventOptions}
                            />
                        </AdminFormField>
                    </>
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
                            <span className="text-muted-foreground">{reportTotalLabel(t, key)}: </span>
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

            <div className="overflow-x-auto rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((col) => (
                                <TableHead key={col}>{reportColumnLabel(t, col)}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length ? (
                            rows.map((row, idx) => (
                                <TableRow key={row.id ?? idx}>
                                    {columns.map((col) => (
                                        <TableCell key={col} className={col === 'changes_summary' ? 'max-w-md' : undefined}>
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
