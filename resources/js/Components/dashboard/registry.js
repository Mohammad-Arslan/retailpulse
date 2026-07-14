import {
    BusinessExceptionsWidget,
    FinanceOverviewWidget,
    InventoryOverviewWidget,
    OperationsOverviewWidget,
    ProcurementOverviewWidget,
    QuickActionsWidget,
    RevenueChartsWidget,
    SalesKpisWidget,
} from '@/Components/dashboard/widgets';

/**
 * Maps widget registry ids (from DashboardComposer) to React components.
 * Adding a backend widget only requires registering it here when the UI exists.
 */
export const DASHBOARD_WIDGET_COMPONENTS = {
    business_exceptions: BusinessExceptionsWidget,
    sales_kpis: SalesKpisWidget,
    revenue_charts: RevenueChartsWidget,
    inventory_overview: InventoryOverviewWidget,
    procurement_overview: ProcurementOverviewWidget,
    finance_overview: FinanceOverviewWidget,
    operations_overview: OperationsOverviewWidget,
    quick_actions: QuickActionsWidget,
};
