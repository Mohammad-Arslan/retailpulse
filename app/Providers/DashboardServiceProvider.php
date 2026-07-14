<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Dashboard\Contracts\DashboardWidget;
use App\Services\Dashboard\DashboardWidgetRegistry;
use App\Services\Dashboard\Widgets\BusinessExceptionsWidget;
use App\Services\Dashboard\Widgets\FinanceOverviewWidget;
use App\Services\Dashboard\Widgets\InventoryOverviewWidget;
use App\Services\Dashboard\Widgets\OperationsOverviewWidget;
use App\Services\Dashboard\Widgets\ProcurementOverviewWidget;
use App\Services\Dashboard\Widgets\QuickActionsWidget;
use App\Services\Dashboard\Widgets\RevenueChartsWidget;
use App\Services\Dashboard\Widgets\SalesKpisWidget;
use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<DashboardWidget>>
     */
    private const WIDGETS = [
        BusinessExceptionsWidget::class,
        SalesKpisWidget::class,
        RevenueChartsWidget::class,
        InventoryOverviewWidget::class,
        ProcurementOverviewWidget::class,
        FinanceOverviewWidget::class,
        OperationsOverviewWidget::class,
        QuickActionsWidget::class,
    ];

    public function register(): void
    {
        $this->app->singleton(DashboardWidgetRegistry::class, function ($app): DashboardWidgetRegistry {
            $registry = new DashboardWidgetRegistry;

            foreach (self::WIDGETS as $widgetClass) {
                /** @var DashboardWidget $widget */
                $widget = $app->make($widgetClass);
                $registry->register($widget);
            }

            return $registry;
        });
    }
}
