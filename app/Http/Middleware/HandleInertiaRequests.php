<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BranchContextService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $branchContext = app()->bound(BranchContext::class)
            ? app(BranchContext::class)
            : ($user ? app(BranchContextService::class)->resolve($request) : null);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email') : null,
                'roles' => $user ? $user->getRoleNames() : [],
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'branch' => fn () => $this->shareBranch($request, $user, $branchContext),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shareBranch(Request $request, mixed $user, ?BranchContext $context): ?array
    {
        if ($user === null || $context === null) {
            return null;
        }

        $service = app(BranchContextService::class);

        return [
            'active' => $service->activeBranchPayload($context),
            'options' => $service->switcherOptions($user),
            'canViewAll' => ! $context->isRestricted(),
            'isAllBranches' => $context->isAllBranches(),
        ];
    }
}
