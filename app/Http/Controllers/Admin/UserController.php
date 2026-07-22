<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\User\CreateUserData;
use App\DTOs\User\UpdateUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\PosPinService;
use App\Services\UserService;
use App\Support\AccessControlLabels;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly BranchRepositoryInterface $branches,
        private readonly UserService $userService,
        private readonly BranchContextService $branchContext,
        private readonly PosPinService $posPin,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'is_active', 'sort', 'direction'],
        );

        return Inertia::render('Admin/Users/Index', [
            'users' => $this->users->paginate(
                $filters,
                $this->userBranchFilterIds($request),
                ListPagination::resolve($filters['per_page']),
            ),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->roleOptions(),
            'availableBranches' => $this->branches->allActive(
                $this->branchContext->accessibleBranchIds($request->user()),
            ),
            'linkableEmployees' => $this->userService->linkableEmployeeOptions(null),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->userService->create($request->user(), CreateUserData::fromRequest($request));

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('User created successfully.'));
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles', 'branches', 'employee']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames(),
                'branches' => $user->branches->map(fn ($b) => [
                    'branch_id' => $b->id,
                    'is_primary' => (bool) $b->pivot->is_primary,
                ]),
                'has_pos_pin' => $this->posPin->hasPin($user),
                'pos_pin_lockout' => $this->posPin->getLockoutStatus($user),
                'employee_id' => $user->employee?->id,
            ],
            'roles' => $this->roleOptions(),
            'availableBranches' => $this->branches->allActive(
                $this->branchContext->accessibleBranchIds(request()->user()),
            ),
            'linkableEmployees' => $this->userService->linkableEmployeeOptions($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userService->update($request->user(), $user, UpdateUserData::fromRequest($request));

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('User updated successfully.'));
    }

    public function resetPosPinLockout(User $user): RedirectResponse
    {
        $this->authorize('pos.admin');

        $this->posPin->resetLockout($user);

        return back()->with('success', __('POS PIN lockout cleared for :name.', ['name' => $user->name]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->userService->deactivate($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('User deactivated successfully.'));
    }

    /**
     * @return list<array{name: string, display_name: string}>
     */
    private function roleOptions(): array
    {
        return $this->roles->allWithPermissions()
            ->map(fn ($role) => [
                'name' => $role->name,
                'display_name' => $role->display_name ?: AccessControlLabels::forRole($role->name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>|null
     */
    private function userBranchFilterIds(Request $request): ?array
    {
        $context = app(BranchContext::class);

        if (! $context->isRestricted()) {
            return null;
        }

        if ($context->branchId !== null) {
            return [$context->branchId];
        }

        return $context->accessibleBranchIds;
    }
}
