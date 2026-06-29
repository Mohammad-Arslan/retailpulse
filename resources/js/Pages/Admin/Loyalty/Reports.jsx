import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const TABS = ['earned', 'redeemed', 'expired', 'customers', 'tiers', 'branches', 'campaigns', 'top'];

function Reports({
    tab = 'earned',
    pointsEarned = [],
    pointsRedeemed = [],
    pointsExpired = [],
    customerLoyalty = [],
    tierDistribution = [],
    branchLoyalty = [],
    campaignEffectiveness = [],
    topCustomers = [],
}) {
    const { t } = useTranslation();
    const activeTab = TABS.includes(tab) ? tab : 'earned';

    const tabItems = useMemo(
        () => TABS.map((id) => ({ id, label: t(`pages.loyalty.reports.tabs.${id}`) })),
        [t],
    );

    function setTab(id) {
        router.get(
            route('admin.loyalty.reports.index'),
            { tab: id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function renderTable(headers, rows, emptyColSpan) {
        return (
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                            {headers.map((h) => (
                                <th key={h} className="px-3 py-2">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={emptyColSpan} className="px-3 py-8 text-center text-muted-foreground">
                                    {t('pages.loyalty.reports.empty')}
                                </td>
                            </tr>
                        ) : (
                            rows
                        )}
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <>
            <Head title={t('pages.loyalty.reports.title')} />
            <PageHeader title={t('pages.loyalty.reports.title')} description={t('pages.loyalty.reports.description')} />

            <div className="mb-6 flex flex-wrap gap-1 border-b">
                {tabItems.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setTab(item.id)}
                        className={`px-4 py-2 text-sm font-medium transition ${
                            activeTab === item.id
                                ? 'border-b-2 border-teal-600 text-teal-600'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {activeTab === 'earned' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.branch'),
                        t('pages.loyalty.reports.columns.totalPoints'),
                        t('pages.loyalty.reports.columns.transactions'),
                    ],
                    pointsEarned.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.branch}</td>
                            <td className="px-3 py-2">{row.total_points}</td>
                            <td className="px-3 py-2">{row.transaction_count}</td>
                        </tr>
                    )),
                    3,
                )}

            {activeTab === 'redeemed' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.branch'),
                        t('pages.loyalty.reports.columns.totalPoints'),
                        t('pages.loyalty.reports.columns.transactions'),
                    ],
                    pointsRedeemed.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.branch}</td>
                            <td className="px-3 py-2">{row.total_points}</td>
                            <td className="px-3 py-2">{row.transaction_count}</td>
                        </tr>
                    )),
                    3,
                )}

            {activeTab === 'expired' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.branch'),
                        t('pages.loyalty.reports.columns.totalPoints'),
                        t('pages.loyalty.reports.columns.transactions'),
                    ],
                    pointsExpired.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.branch}</td>
                            <td className="px-3 py-2">{row.total_points}</td>
                            <td className="px-3 py-2">{row.transaction_count}</td>
                        </tr>
                    )),
                    3,
                )}

            {activeTab === 'customers' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.customer'),
                        t('pages.loyalty.reports.columns.phone'),
                        t('pages.loyalty.reports.columns.tier'),
                        t('pages.loyalty.reports.columns.availablePoints'),
                        t('pages.loyalty.reports.columns.lifetimeEarned'),
                    ],
                    customerLoyalty.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.customer}</td>
                            <td className="px-3 py-2">{row.phone ?? '—'}</td>
                            <td className="px-3 py-2">{row.tier ?? '—'}</td>
                            <td className="px-3 py-2">{row.available_points}</td>
                            <td className="px-3 py-2">{row.lifetime_earned_points}</td>
                        </tr>
                    )),
                    5,
                )}

            {activeTab === 'tiers' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.tier'),
                        t('pages.loyalty.reports.columns.customers'),
                        t('pages.loyalty.reports.columns.totalPoints'),
                    ],
                    tierDistribution.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.tier}</td>
                            <td className="px-3 py-2">{row.customer_count}</td>
                            <td className="px-3 py-2">{row.total_points}</td>
                        </tr>
                    )),
                    3,
                )}

            {activeTab === 'branches' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.branch'),
                        t('pages.loyalty.reports.columns.customers'),
                        t('pages.loyalty.reports.columns.availablePoints'),
                        t('pages.loyalty.reports.columns.lifetimeEarned'),
                        t('pages.loyalty.reports.columns.redeemed'),
                    ],
                    branchLoyalty.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.branch}</td>
                            <td className="px-3 py-2">{row.customer_count}</td>
                            <td className="px-3 py-2">{row.available_points}</td>
                            <td className="px-3 py-2">{row.lifetime_earned}</td>
                            <td className="px-3 py-2">{row.redeemed_points}</td>
                        </tr>
                    )),
                    5,
                )}

            {activeTab === 'campaigns' &&
                renderTable(
                    [t('pages.loyalty.campaigns.fields.name'), t('pages.loyalty.reports.columns.bonusEvents')],
                    campaignEffectiveness.map((row) => (
                        <tr key={row.id} className="border-b">
                            <td className="px-3 py-2">{row.name}</td>
                            <td className="px-3 py-2">{row.bonus_events}</td>
                        </tr>
                    )),
                    2,
                )}

            {activeTab === 'top' &&
                renderTable(
                    [
                        t('pages.loyalty.reports.columns.customer'),
                        t('pages.loyalty.reports.columns.tier'),
                        t('pages.loyalty.reports.columns.lifetimeEarned'),
                        t('pages.loyalty.reports.columns.availablePoints'),
                    ],
                    topCustomers.map((row, idx) => (
                        <tr key={idx} className="border-b">
                            <td className="px-3 py-2">{row.customer}</td>
                            <td className="px-3 py-2">{row.tier ?? '—'}</td>
                            <td className="px-3 py-2">{row.lifetime_earned_points}</td>
                            <td className="px-3 py-2">{row.available_points}</td>
                        </tr>
                    )),
                    4,
                )}
        </>
    );
}

export default withAdminLayout(Reports);
