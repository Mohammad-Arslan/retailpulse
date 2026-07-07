import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { chartOfAccountTypeLabel } from '@/lib/accountingI18n';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function TrialBalance({ report, filters, branches = [] }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.accounting.reports.trial-balance'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.reports.trialBalance.title')} />
            <PageHeader
                title={t('pages.accounting.reports.trialBalance.title')}
                description={t('pages.accounting.reports.trialBalance.description')}
            >
                <Link href={route('admin.accounting.reports.index')} className="rp-btn-outline">
                    <ArrowLeft className="h-4 w-4" />
                    {t('pages.accounting.reports.backToReports')}
                </Link>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
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
                    <Search className="h-4 w-4" />
                    {t('common.search')}
                </button>
            </form>

            <div className="rp-card overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-500">
                            <th className="px-4 py-3">{t('pages.accounting.chartOfAccounts.columns.code')}</th>
                            <th className="px-4 py-3">{t('common.name')}</th>
                            <th className="px-4 py-3">{t('pages.accounting.chartOfAccounts.columns.type')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.openingDebit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.openingCredit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.periodDebit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.periodCredit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.closingDebit')}</th>
                            <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.closingCredit')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.length === 0 ? (
                            <tr>
                                <td colSpan={9} className="px-4 py-10 text-center text-ink-500">
                                    {t('pages.accounting.reports.empty')}
                                </td>
                            </tr>
                        ) : (
                            report.rows.map((row) => (
                                <tr key={row.account_id} className="border-b border-border/60">
                                    <td className="px-4 py-2 font-mono text-xs">{row.code}</td>
                                    <td className="px-4 py-2">{row.name}</td>
                                    <td className="px-4 py-2">{chartOfAccountTypeLabel(t, row.type)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.opening_debit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.opening_credit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.period_debit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.period_credit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.closing_debit)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.closing_credit)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                    {report.rows.length > 0 && (
                        <tfoot>
                            <tr className="bg-muted/40 font-semibold">
                                <td colSpan={3} className="px-4 py-3">
                                    {t('pages.accounting.journalEntries.totals')}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.opening_debit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.opening_credit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.period_debit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.period_credit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.closing_debit)}</td>
                                <td className="px-4 py-3 text-right tabular-nums">{formatMoney(report.totals.closing_credit)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </>
    );
}

export default withAdminLayout(TrialBalance);
