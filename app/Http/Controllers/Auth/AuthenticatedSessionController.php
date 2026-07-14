<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use App\Services\BranchContextService;
use App\Services\Dashboard\HomeRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly BranchContextService $branchContext,
        private readonly HomeRouteResolver $homeRoute,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null) {
            $this->audit->logLogin($user, $request);
        }

        if ($user !== null) {
            $this->branchContext->initializeSession($request, $user);
        }

        if ($user !== null && ! $user->can('admin.access') && ! $user->can('pos.access')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('You do not have permission to access the application.'),
            ]);
        }

        if ($user !== null) {
            event(UserLoggedIn::fromRequest($user, $request));
        }

        $home = $this->homeRoute->url($user);

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->audit->logLogout($user, $request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
