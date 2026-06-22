<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Procurement\ProcurementAlertService;
use App\Services\Procurement\ProcurementDashboardService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        ProcurementDashboardService $procurement,
        ProcurementAlertService $procurementAlerts,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user !== null && (
                $user->can('dashboard.view') || $user->can('admin.dashboard.view')
            ),
            403,
        );

        $context = app(BranchContext::class);
        $branchId = $context->branchId;
        $accessibleBranchIds = $context->accessibleBranchIds;
        $isSuperAdmin = $user->hasRole('super-admin');

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats($branchId, $accessibleBranchIds),
            'charts' => $dashboard->charts($branchId, $accessibleBranchIds),
            'salesKpis' => $dashboard->salesKpis($branchId, $accessibleBranchIds),
            'revenueCharts' => $dashboard->revenueCharts($branchId, $accessibleBranchIds),
            'superAdmin' => $isSuperAdmin
                ? $dashboard->superAdminOverview($branchId, $accessibleBranchIds)
                : null,
            'canViewProfit' => $user->can('dashboard.view-profit'),
            'widgets' => $this->visibleWidgets($user),
            'procurementKpis' => $user->can('procurement.view')
                ? $procurement->kpis($branchId)
                : null,
            'procurementAlerts' => $user->can('procurement.view')
                ? $procurementAlerts->recentUnreadForUser($user)->map(fn ($alert) => [
                    'id' => $alert->id,
                    'type' => $alert->type,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'link_route' => $alert->link_route,
                    'link_params' => $alert->link_params ?? [],
                    'created_at' => $alert->created_at?->toIso8601String(),
                ])
                : [],
        ]);
    }

    /**
     * @return list<string>
     */
    private function visibleWidgets(User $user): array
    {
        $widgets = ['rbac', 'activity'];

        if ($user->can('dashboard.view') || $user->can('admin.dashboard.view')) {
            $widgets[] = 'operations';
        }

        if ($user->can('dashboard.view-profit')) {
            $widgets[] = 'sales';
            $widgets[] = 'revenue';
        }

        if ($user->can('procurement.view')) {
            $widgets[] = 'procurement';
        }

        return array_values(array_unique($widgets));
    }
}
