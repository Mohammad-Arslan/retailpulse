<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardComposer;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardComposer $composer,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user !== null && (
                $user->can('dashboard.view') || $user->can('admin.dashboard.view')
            ),
            403,
        );

        $context = app(BranchContext::class);

        return Inertia::render('Admin/Dashboard', [
            'widgets' => $composer->compose($user, $context),
        ]);
    }
}
