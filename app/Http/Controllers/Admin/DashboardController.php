<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
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
            'salesKpis' => $dashboard->salesKpis(),
            'revenueCharts' => $dashboard->revenueCharts(),
            'superAdmin' => $isSuperAdmin
                ? $dashboard->superAdminOverview($branchId, $accessibleBranchIds)
                : null,
            'canViewProfit' => $user->can('dashboard.view-profit'),
            'widgets' => $this->visibleWidgets($user),
        ]);
    }

    /**
     * @return list<string>
     */
    private function visibleWidgets(\App\Models\User $user): array
    {
        $widgets = ['rbac', 'activity'];

        if ($user->can('dashboard.view') || $user->can('admin.dashboard.view')) {
            $widgets[] = 'operations';
        }

        if ($user->can('dashboard.view-profit')) {
            $widgets[] = 'sales';
            $widgets[] = 'revenue';
        }

        return array_values(array_unique($widgets));
    }
}
