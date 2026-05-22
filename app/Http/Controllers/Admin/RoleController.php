<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Role\CreateRoleData;
use App\DTOs\Role\UpdateRoleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloneRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Role;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\RoleService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly RoleService $roleService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'sort', 'direction'],
        );

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $this->roles->paginate(
                $filters,
                ListPagination::resolve($filters['per_page']),
            ),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Create', [
            'permissionGroups' => $this->formatPermissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $this->roleService->create(CreateRoleData::fromRequest($request));

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role created successfully.'));
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => $this->formatPermissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roleService->update($role, UpdateRoleData::fromRequest($request));

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role updated successfully.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->roleService->delete($role);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role deleted successfully.'));
    }

    public function cloneForm(Role $role): Response
    {
        $this->authorize('clone', $role);

        return Inertia::render('Admin/Roles/Clone', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
        ]);
    }

    public function cloneRole(CloneRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('clone', $role);

        $cloned = $this->roleService->clone($role, $request->validated('name'));

        return redirect()
            ->route('admin.roles.edit', $cloned)
            ->with('success', __('Role cloned successfully.'));
    }

    /**
     * @return array<string, list<array{id: int, name: string, description: string|null}>>
     */
    private function formatPermissionGroups(): array
    {
        return $this->permissions->allGrouped()
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
            ])->values()->all())
            ->all();
    }
}
