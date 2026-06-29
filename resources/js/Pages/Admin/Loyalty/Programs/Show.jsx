import ApprovalsTab from '@/Components/admin/loyalty/ApprovalsTab';
import CampaignsTab from '@/Components/admin/loyalty/CampaignsTab';
import ExpiryTab from '@/Components/admin/loyalty/ExpiryTab';
import RulesTab from '@/Components/admin/loyalty/RulesTab';
import TiersTab from '@/Components/admin/loyalty/TiersTab';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const TABS = ['overview', 'rules', 'tiers', 'approvals', 'expiry', 'campaigns'];

function Show({
    tab = 'overview',
    program,
    rules = [],
    tiers = [],
    approvalPolicies = [],
    expiryRules = [],
    campaigns = [],
    options = {},
    branches = [],
    categories = [],
    roles = [],
}) {
    const { t } = useTranslation();
    const can = useCan();

    const activeTab = TABS.includes(tab) ? tab : 'overview';

    const tabItems = useMemo(
        () =>
            TABS.map((id) => ({
                id,
                label: t(`pages.loyalty.tabs.${id}`),
                count:
                    id === 'rules'
                        ? rules.length
                        : id === 'tiers'
                          ? tiers.length
                          : id === 'approvals'
                            ? approvalPolicies.length
                            : id === 'expiry'
                              ? expiryRules.length
                              : id === 'campaigns'
                                ? campaigns.length
                                : null,
            })),
        [t, rules.length, tiers.length, approvalPolicies.length, expiryRules.length, campaigns.length],
    );

    function setTab(id) {
        router.get(
            route('admin.loyalty.programs.show', program.id),
            { tab: id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleStatus() {
        const routeName =
            program.status === 'active'
                ? 'admin.loyalty.programs.deactivate'
                : 'admin.loyalty.programs.activate';
        router.post(route(routeName, program.id), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={program.name} />
            <PageHeader title={program.name} description={program.description ?? ''}>
                {can('loyalty.manage-programs') && (
                    <>
                        <Button type="button" variant="outline" onClick={toggleStatus}>
                            {program.status === 'active'
                                ? t('pages.loyalty.actions.deactivate')
                                : t('pages.loyalty.actions.activate')}
                        </Button>
                        <Link href={route('admin.loyalty.programs.edit', program.id)} className="rp-btn-outline">
                            {t('common.edit')}
                        </Link>
                    </>
                )}
                <Link href={route('admin.loyalty.programs.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

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
                        {item.count != null && item.count > 0 && (
                            <span className="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs">{item.count}</span>
                        )}
                    </button>
                ))}
            </div>

            {activeTab === 'overview' && (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-lg border bg-card p-4">
                            <p className="text-xs uppercase text-muted-foreground">{t('pages.loyalty.programs.columns.status')}</p>
                            <p className="mt-1 text-lg font-semibold capitalize">{program.status?.replace('_', ' ')}</p>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <p className="text-xs uppercase text-muted-foreground">{t('pages.loyalty.programs.columns.scope')}</p>
                            <p className="mt-1 text-lg font-semibold capitalize">{program.scope_type?.replace('_', ' ')}</p>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <p className="text-xs uppercase text-muted-foreground">{t('pages.loyalty.programs.fields.earnScope')}</p>
                            <p className="mt-1 text-lg font-semibold capitalize">{program.earn_scope}</p>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <p className="text-xs uppercase text-muted-foreground">{t('pages.loyalty.programs.fields.redeemScope')}</p>
                            <p className="mt-1 text-lg font-semibold capitalize">{program.redeem_scope}</p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-lg border bg-card p-4 text-sm">
                            <p className="font-medium">{t('pages.loyalty.programs.fields.allowCrossBranchEarn')}</p>
                            <p className="mt-1 text-muted-foreground">{program.allow_cross_branch_earn ? t('common.yes') : t('common.no')}</p>
                        </div>
                        <div className="rounded-lg border bg-card p-4 text-sm">
                            <p className="font-medium">{t('pages.loyalty.programs.fields.allowCrossBranchRedeem')}</p>
                            <p className="mt-1 text-muted-foreground">{program.allow_cross_branch_redeem ? t('common.yes') : t('common.no')}</p>
                        </div>
                    </div>

                    {program.branches?.length > 0 && (
                        <div className="rounded-lg border bg-card p-4">
                            <p className="mb-2 text-sm font-medium">{t('pages.loyalty.programs.fields.branches')}</p>
                            <p className="text-sm text-muted-foreground">
                                {program.branches.map((b) => b.name).join(', ')}
                            </p>
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={route('admin.loyalty.transactions.index')}>{t('pages.loyalty.transactions.title')}</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('admin.loyalty.reports.index')}>{t('pages.loyalty.reports.title')}</Link>
                        </Button>
                    </div>
                </div>
            )}

            {activeTab === 'rules' && (
                <RulesTab program={program} rules={rules} options={options} branches={branches} categories={categories} />
            )}

            {activeTab === 'tiers' && <TiersTab program={program} tiers={tiers} options={options} />}

            {activeTab === 'approvals' && (
                <ApprovalsTab program={program} approvalPolicies={approvalPolicies} options={options} roles={roles} />
            )}

            {activeTab === 'expiry' && <ExpiryTab program={program} expiryRules={expiryRules} options={options} />}

            {activeTab === 'campaigns' && <CampaignsTab program={program} campaigns={campaigns} options={options} />}
        </>
    );
}

export default withAdminLayout(Show);
