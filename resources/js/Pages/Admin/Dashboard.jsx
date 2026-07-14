import HealthStrip from '@/Components/dashboard/HealthStrip';
import {
    BusinessExceptionsWidget,
    FinanceStatCard,
    InventoryStatCard,
    InventoryTrendSection,
    OperationsOverviewWidget,
    ProcurementStatCard,
    ProcurementSuppliersTable,
    QuickActionsWidget,
    RevenueChartsWidget,
    SalesKpisWidget,
} from '@/Components/dashboard/widgets';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function widgetById(widgets, id) {
    return widgets.find((widget) => widget.id === id) ?? null;
}

function Dashboard({ widgets = [] }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const rawName = auth?.user?.name?.trim() ?? '';
    const firstName = rawName ? rawName.split(/\s+/)[0] : t('pages.dashboard.greetingFallback');

    const today = new Date().toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? t('pages.dashboard.greetingMorning')
            : hour < 17
              ? t('pages.dashboard.greetingAfternoon')
              : t('pages.dashboard.greetingEvening');

    const exceptions = widgetById(widgets, 'business_exceptions');
    const sales = widgetById(widgets, 'sales_kpis');
    const revenue = widgetById(widgets, 'revenue_charts');
    const inventory = widgetById(widgets, 'inventory_overview');
    const procurement = widgetById(widgets, 'procurement_overview');
    const finance = widgetById(widgets, 'finance_overview');
    const operations = widgetById(widgets, 'operations_overview');
    const quickActions = widgetById(widgets, 'quick_actions');

    const exceptionItems = exceptions?.data?.items ?? [];
    const hasOperationsRow = Boolean(inventory || procurement || finance);

    return (
        <>
            <Head title={t('pages.dashboard.title')} />

            <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="font-display text-[28px] font-normal text-rp-text sm:text-[34px]">
                        {greeting}, {firstName}.
                    </h1>
                    <p className="rp-page-desc">{t('pages.dashboard.description')}</p>
                </div>
                <div className="rp-pill-surface flex items-center gap-1.5 self-start rounded-full sm:self-auto">
                    <Calendar className="h-3.5 w-3.5 text-rp-text-muted" />
                    {today}
                </div>
            </div>

            {widgets.length === 0 ? (
                <div className="rp-card text-sm text-rp-text-muted">
                    {t('pages.dashboard.empty')}
                </div>
            ) : (
                <>
                    {exceptions ? <HealthStrip items={exceptionItems} /> : null}

                    {sales ? <SalesKpisWidget data={sales.data} /> : null}

                    {(revenue || exceptions) && (
                        <div className="mb-6 grid gap-4 lg:grid-cols-[1.55fr_1fr] lg:items-stretch">
                            {revenue ? (
                                <RevenueChartsWidget data={revenue.data} chartOnly />
                            ) : null}
                            {exceptions ? (
                                <BusinessExceptionsWidget data={exceptions.data} embedded />
                            ) : null}
                        </div>
                    )}

                    {hasOperationsRow ? (
                        <section className="mb-6">
                            <div className="mb-3.5">
                                <h2 className="rp-section-label">
                                    {t('pages.dashboard.sections.operations')}
                                </h2>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {inventory ? <InventoryStatCard data={inventory.data} /> : null}
                                {procurement ? (
                                    <ProcurementStatCard data={procurement.data} />
                                ) : null}
                                {finance ? <FinanceStatCard data={finance.data} /> : null}
                            </div>
                            {inventory ? <InventoryTrendSection data={inventory.data} /> : null}
                            {procurement ? (
                                <ProcurementSuppliersTable data={procurement.data} />
                            ) : null}
                        </section>
                    ) : null}

                    {operations ? <OperationsOverviewWidget data={operations.data} /> : null}
                    {quickActions ? <QuickActionsWidget data={quickActions.data} /> : null}
                </>
            )}
        </>
    );
}

export default withAdminLayout(Dashboard);
