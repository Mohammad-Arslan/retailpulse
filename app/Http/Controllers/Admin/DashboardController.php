<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        abort_unless(Gate::allows('admin.dashboard.view'), 403);

        $user = $request->user();
        $isSuperAdmin = $user !== null && $user->hasRole('super-admin');

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats(),
            'charts' => $dashboard->charts(),
            'superAdmin' => $isSuperAdmin ? $dashboard->superAdminOverview() : null,
        ]);
    }
}
