import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    BookOpen,
    Building2,
    ChevronRight,
    ClipboardList,
    FileSpreadsheet,
    Landmark,
    Layers,
    Receipt,
    ScrollText,
    Shield,
    Wallet,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

const REPORT_ICONS = {
    trialBalance: BarChart3,
    profitAndLoss: Receipt,
    balanceSheet: Landmark,
    generalLedger: BookOpen,
    costCentrePl: Layers,
    cashFlow: Wallet,
    arAging: ClipboardList,
    apAging: Building2,
    bankBook: Landmark,
    inventoryValuation: FileSpreadsheet,
    assetRegister: Building2,
    fxRevaluation: Wallet,
    pettyCash: Wallet,
    chequeStatus: ScrollText,
    auditTrail: Shield,
    unpostedJournals: AlertTriangle,
    journalRegister: ScrollText,
};

function Index({ reports = [] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('pages.accounting.reports.title')} />
            <PageHeader
                title={t('pages.accounting.reports.title')}
                description={t('pages.accounting.reports.description')}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {reports.map((report) => {
                    const Icon = REPORT_ICONS[report.key] ?? BarChart3;
                    const title = t(`pages.accounting.reports.cards.${report.key}.title`);
                    const description = t(`pages.accounting.reports.cards.${report.key}.description`);
                    const available = report.available === true;

                    const cardClass = cn(
                        'rp-card group flex flex-col gap-3 p-5 transition',
                        available
                            ? 'hover:border-teal-400/40 hover:shadow-md cursor-pointer'
                            : 'opacity-75',
                    );

                    const inner = (
                        <>
                            <div className="flex items-start justify-between gap-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                                    <Icon className="h-5 w-5" />
                                </span>
                                {available && (
                                    <ChevronRight className="h-5 w-5 text-ink-400 transition group-hover:translate-x-0.5 group-hover:text-teal-500" />
                                )}
                            </div>
                            <div>
                                <h3 className="font-semibold text-ink-900 dark:text-white">{title}</h3>
                                <p className="mt-1 text-sm text-ink-500 dark:text-ink-300">{description}</p>
                            </div>
                            {!available && (
                                <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                    {t('pages.accounting.reports.comingSoon')}
                                </span>
                            )}
                        </>
                    );

                    if (available && report.route) {
                        return (
                            <a key={report.key} href={route(report.route)} className={cardClass}>
                                {inner}
                            </a>
                        );
                    }

                    return (
                        <div key={report.key} className={cardClass}>
                            {inner}
                        </div>
                    );
                })}
            </div>
        </>
    );
}

export default withAdminLayout(Index);
