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

function GeneralLedger({ report, filters, branches = [], accounts = [] }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.accounting.reports.general-ledger'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.reports.generalLedger.title')} />
            <PageHeader
                title={t('pages.accounting.reports.generalLedger.title')}
                description={t('pages.accounting.reports.generalLedger.description')}
            >
                <Link href={route('admin.accounting.reports.index')} className="rp-btn-outline">
                    <ArrowLeft className="h-4 w-4" />
                    {t('pages.accounting.reports.backToReports')}
                </Link>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <Select
                    name="account_id"
                    defaultValue={filters.account_id ? String(filters.account_id) : ''}
                    className="min-w-[16rem] flex-1"
                    options={[
                        { value: '', label: t('pages.accounting.reports.selectAccount') },
                        ...accounts.map((account) => ({
                            value: String(account.id),
                            label: `${account.code} — ${account.name}`,
                        })),
                    ]}
                />
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

            {!report ? (
                <div className="rp-card px-4 py-10 text-center text-sm text-ink-500">
                    {t('pages.accounting.reports.selectAccountPrompt')}
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="rp-card flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <span className="font-semibold text-ink-900 dark:text-white">
                                {report.account.code} — {report.account.name}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-4 text-ink-500">
                            <span>
                                {t('pages.accounting.reports.openingBalance')}: {formatMoney(report.opening_balance)}
                            </span>
                            <span>
                                {t('pages.accounting.reports.closingBalance')}: {formatMoney(report.closing_balance)}
                            </span>
                        </div>
                    </div>

                    <div className="rp-card overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-500">
                                    <th className="px-4 py-3">{t('common.date')}</th>
                                    <th className="px-4 py-3">{t('pages.accounting.journalEntries.columns.number')}</th>
                                    <th className="px-4 py-3">{t('pages.accounting.journalEntries.columns.description')}</th>
                                    <th className="px-4 py-3 text-right">{t('pages.accounting.journalEntries.columns.debit')}</th>
                                    <th className="px-4 py-3 text-right">{t('pages.accounting.journalEntries.columns.credit')}</th>
                                    <th className="px-4 py-3 text-right">{t('pages.accounting.reports.columns.balance')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.lines.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-ink-500">
                                            {t('pages.accounting.reports.empty')}
                                        </td>
                                    </tr>
                                ) : (
                                    report.lines.map((line) => (
                                        <tr key={line.id} className="border-b border-border/60">
                                            <td className="px-4 py-2">{line.journal_date}</td>
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={route('admin.accounting.journal-entries.show', line.journal_entry_id)}
                                                    className="text-teal-600 hover:underline"
                                                >
                                                    {line.journal_number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2">{line.description ?? '—'}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{formatMoney(line.debit)}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{formatMoney(line.credit)}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{formatMoney(line.balance)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </>
    );
}

export default withAdminLayout(GeneralLedger);
