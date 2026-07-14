<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Services\BranchContextService;
use App\Services\Dashboard\HomeRouteResolver;
use App\Services\LocaleService;
use App\Services\Navigation\NavigationComposer;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\SessionKey;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Map Laravel `redirect()->with('success'|'error'|'warning')` into Inertia’s `flash` payload
     * so the SPA receives top-level `flash` (and optional `inertia:flash` events), not only props.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if ($request->hasSession()) {
            $this->syncLaravelFlashIntoInertiaFlash($request);
        }

        return parent::handle($request, $next);
    }

    private function syncLaravelFlashIntoInertiaFlash(Request $request): void
    {
        $laravel = [];

        foreach (['success', 'error', 'warning'] as $key) {
            if ($request->session()->has($key)) {
                $laravel[$key] = $request->session()->get($key);
            }
        }

        if ($laravel === []) {
            return;
        }

        $existing = Inertia::getFlashed($request);

        $request->session()->now(
            SessionKey::FlashData->value,
            array_merge($existing, $laravel),
        );
    }

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
            'locale' => fn () => $this->shareLocale($request),
            'enabledAccountingModules' => fn () => $user
                ? app(AccountingModuleGate::class)->enabledModules($branchContext?->branchId)
                : [],
            'navigation' => fn () => $user
                ? app(NavigationComposer::class)->forUser($user, $branchContext?->branchId)
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'home' => [
                'route' => $user
                    ? app(HomeRouteResolver::class)->routeName($user)
                    : 'login',
                'can_exit_to_erp' => $user
                    ? app(HomeRouteResolver::class)->canExitToErp($user)
                    : false,
            ],
            'csrf_token' => fn () => csrf_token(),
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

    /**
     * @return array<string, mixed>
     */
    private function shareLocale(Request $request): array
    {
        $service = app(LocaleService::class);
        $active = $service->resolve($request);

        return [
            'active' => $active['code'],
            'label' => $active['label'],
            'nativeLabel' => $active['native'],
            'rtl' => $active['rtl'],
            'options' => $service->switcherOptions(),
        ];
    }
}
