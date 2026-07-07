import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { journalEntryStatusLabel, journalStatusBadgeClass } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function JournalRegister({ report, filters, branches = [], statuses = [] }) {
    const { t } = useTranslation();

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...statuses.map((status) => ({
                value: status,
                label: journalEntryStatusLabel(t, status),
            })),
        ],
        [statuses, t],
    );

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.accounting.reports.journal-register'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.reports.journalRegister.title')} />
            <PageHeader
                title={t('pages.accounting.reports.journalRegister.title')}
                description={t('pages.accounting.reports.journalRegister.description')}
            >
                <Link href={route('admin.accounting.reports.index')} className="rp-btn-outline">
                    <ArrowLeft className="h-4 w-4" />
                    {t('pages.accounting.reports.backToReports')}
                </Link>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.journalEntries.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <input
                    type="date"
                    name="date_from"
                    defaultValue={filters.date_from}
                    className="rp-input w-auto"
                    aria-label={t('pages.accounting.journalEntries.filters.dateFrom')}
                />
                <input
                    type="date"
                    name="date_to"
                    defaultValue={filters.date_to}
                    className="rp-input w-auto"
                    aria-label={t('pages.accounting.journalEntries.filters.dateTo')}
                />
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={statusOptions}
                />
                {branches.length > 1 && (
                    <Select
                        name="branch_id"
                        defaultValue={filters.branch_id ? String(filters.branch_id) : ''}
                        className="w-auto min-w-[10rem]"
                        options={[
                            { value: '', label: t('pages.accounting.chartOfAccounts.allBranches') },
                            ...branches.map((branch) => ({
                                value: String(branch.id),
                                label: branch.name,
                            })),
                        ]}
                    />
                )}
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <div className="rp-card overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-500">
                            <th className="px-4 py-3">{t('pages.accounting.journalEntries.columns.number')}</th>
                            <th className="px-4 py-3">{t('common.date')}</th>
                            <th className="px-4 py-3">{t('pages.accounting.journalEntries.columns.description')}</th>
                            <th className="px-4 py-3">{t('common.branch')}</th>
                            <th className="px-4 py-3">{t('common.status')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.journalEntries.columns.debit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.journalEntries.columns.credit')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-ink-500">
                                    {t('pages.accounting.reports.empty')}
                                </td>
                            </tr>
                        ) : (
                            report.rows.map((row) => (
                                <tr key={row.id} className="border-b border-border/60">
                                    <td className="px-4 py-2">
                                        <Link
                                            href={route('admin.accounting.journal-entries.show', row.id)}
                                            className="font-medium text-teal-600 hover:underline"
                                        >
                                            {row.journal_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2">{row.journal_date}</td>
                                    <td className="px-4 py-2">{row.description ?? '—'}</td>
                                    <td className="px-4 py-2">{row.branch_name ?? '—'}</td>
                                    <td className="px-4 py-2">
                                        <span
                                            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${journalStatusBadgeClass(row.status)}`}
                                        >
                                            {journalEntryStatusLabel(t, row.status)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.total_debit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.total_credit)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                    {report.rows.length > 0 && (
                        <tfoot>
                            <tr className="bg-muted/40 font-semibold">
                                <td colSpan={5} className="px-4 py-3">
                                    {t('pages.accounting.journalEntries.totals')}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.debit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.credit)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </>
    );
}

export default withAdminLayout(JournalRegister);
