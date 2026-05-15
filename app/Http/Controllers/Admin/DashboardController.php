<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): Response
    {
        abort_unless(Gate::allows('admin.dashboard.view'), 403);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->stats(),
            'charts' => $dashboard->charts(),
        ]);
    }
}
