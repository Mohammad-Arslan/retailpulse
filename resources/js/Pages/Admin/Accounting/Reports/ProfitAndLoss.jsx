import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function SectionTable({ title, rows, totalLabel, total }) {
    const { t } = useTranslation();

    return (
        <div className="rp-card overflow-hidden">
            <div className="border-b border-border px-4 py-3 font-semibold text-ink-900 dark:text-white">{title}</div>
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-500">
                        <th className="px-4 py-3">{t('pages.accounting.chartOfAccounts.columns.code')}</th>
                        <th className="px-4 py-3">{t('common.name')}</th>
                        <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.amount')}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={3} className="px-4 py-8 text-center text-ink-500">
                                {t('pages.accounting.reports.empty')}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={row.account_id} className="border-b border-border/60">
                                <td className="px-4 py-2 font-mono text-xs">{row.code}</td>
                                <td className="px-4 py-2">{row.name}</td>
                                <td className="px-4 py-2 text-right tabular-nums">{formatMoney(row.amount)}</td>
                            </tr>
                        ))
                    )}
                </tbody>
                {rows.length > 0 && (
                    <tfoot>
                        <tr className="bg-muted/40 font-semibold">
                            <td colSpan={2} className="px-4 py-3">
                                {totalLabel}
                            </td>
                            <td className="px-4 py-3 text-right tabular-nums">{formatMoney(total)}</td>
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

function ProfitAndLoss({ report, filters, branches = [] }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.accounting.reports.profit-and-loss'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.reports.profitAndLoss.title')} />
            <PageHeader
                title={t('pages.accounting.reports.profitAndLoss.title')}
                description={t('pages.accounting.reports.profitAndLoss.description')}
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

            <div className="grid gap-4 lg:grid-cols-2">
                <SectionTable
                    title={t('pages.accounting.reports.profitAndLoss.revenueSection')}
                    rows={report.revenue}
                    totalLabel={t('pages.accounting.reports.profitAndLoss.totalRevenue')}
                    total={report.total_revenue}
                />
                <SectionTable
                    title={t('pages.accounting.reports.profitAndLoss.expenseSection')}
                    rows={report.expenses}
                    totalLabel={t('pages.accounting.reports.profitAndLoss.totalExpenses')}
                    total={report.total_expenses}
                />
            </div>

            <div className="rp-card mt-4 flex items-center justify-between px-4 py-4">
                <span className="font-semibold text-ink-900 dark:text-white">
                    {t('pages.accounting.reports.profitAndLoss.netIncome')}
                </span>
                <span className="text-lg font-semibold tabular-nums text-teal-600">{formatMoney(report.net_income)}</span>
            </div>
        </>
    );
}

export default withAdminLayout(ProfitAndLoss);
