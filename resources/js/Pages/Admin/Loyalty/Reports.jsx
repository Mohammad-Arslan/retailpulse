import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Reports({
    tab,
    pointsEarned = [],
    pointsRedeemed = [],
    tierDistribution = [],
    topCustomers = [],
    campaignEffectiveness = [],
}) {
    const { t } = useTranslation();

    const rows = tab === 'redeemed' ? pointsRedeemed : pointsEarned;

    return (
        <>
            <Head title={t('pages.loyalty.reports.title')} />
            <PageHeader title={t('pages.loyalty.reports.title')} description={t('pages.loyalty.reports.description')} />

            <div className="mb-6 grid gap-4 lg:grid-cols-2">
                <section className="rounded-lg border bg-card p-4">
                    <h2 className="mb-3 font-semibold">{t('pages.loyalty.reports.tabs.tiers')}</h2>
                    <ul className="space-y-2 text-sm">
                        {tierDistribution.map((row, idx) => (
                            <li key={idx} className="flex justify-between border-b pb-2">
                                <span>{row.tier}</span>
                                <span>{row.customer_count} customers · {row.total_points} pts</span>
                            </li>
                        ))}
                    </ul>
                </section>
                <section className="rounded-lg border bg-card p-4">
                    <h2 className="mb-3 font-semibold">{t('pages.loyalty.reports.tabs.top')}</h2>
                    <ul className="space-y-2 text-sm">
                        {topCustomers.map((row, idx) => (
                            <li key={idx} className="flex justify-between border-b pb-2">
                                <span>{row.customer}</span>
                                <span>{row.lifetime_earned_points} pts</span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <section className="rounded-lg border bg-card p-4">
                <h2 className="mb-3 font-semibold">{t('pages.loyalty.reports.tabs.campaigns')}</h2>
                <ul className="space-y-2 text-sm">
                    {campaignEffectiveness.map((row) => (
                        <li key={row.id} className="flex justify-between border-b pb-2">
                            <span>{row.name}</span>
                            <span>{row.bonus_events} bonus events</span>
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}

export default withAdminLayout(Reports);
